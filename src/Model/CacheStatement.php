<?php
namespace Oblind\Model;

use Oblind\Cache\BaseCache;

class CacheStatement extends Statement {
  protected $prefix;
  protected $pure;

  function __construct($class) {
    parent::__construct($class);
    $this->prefix = $class::PREFIX;
    $this->pure = $class::pure();
  }

  protected function cache($a, BaseCache $cache) {
    $l = strlen($this->prefix);
    $ps = $cache->keys($this->prefix . '*');
    foreach($ps as $p)
      $p = substr($p, $l);
    $t = [];
    $pr = $this->class::getPrimary();
    foreach($a as $m) {
      $f = true;
      foreach($ps as $p)
        if($m->{$pr} == $p) {
          $f = false;
          break;
        }
      if($f)
        $t[] = $m;
    }
    foreach($t as $m)
      $cache->set($this->prefix . $m->{$pr}, json_encode($m->getData(), JSON_UNESCAPED_UNICODE));
  }

  protected function prune(\stdClass $m, $col) {
    if(is_array($col)) {
      foreach(array_keys(get_object_vars($m)) as $p)
        if(!in_array($p, $col))
          unset($m->$p);
    } elseif($col != '*')
      foreach(array_keys(get_object_vars($m)) as $p)
        if($p != $col)
          unset($m->$p);
    return new $this->class($m);
  }

  protected function match($key, $col, BaseCache $cache) {
    if($this->condition) {
      $i = 0;
      $cs = [];
      foreach($this->condition as $k => $v)
        if(is_string($k)) {
          if(is_array($v)) {
            for($j = 0, $c = count($v); $j < $c; $j += 2) {
              if(is_string($v[$j]))
                $v[$j] = '\'' . addslashes($v[$j]) . '\'';
              $cs[] = "(\$m->$k {$v[$j + 1]} {$v[$j]})";
            }
          } else {
            if(is_string($v))
              $v = '\'' . addslashes($v) . '\'';
            $op = $this->condition[$i++] ?? '==';
            if($op == '=')
              $op = '==';
            $cs[] = "(\$m->$k $op $v)";
          }
          /*if($m->$k != $v) {
            $f = false;
            break;
          }*/
        }
      $c = 'return ' . implode(' && ', $cs) . ';';
    } else
      $c = null;
    if(is_array($key)) {
      $r = [];
      foreach($key as $k) {
        if(($m = json_decode($cache->get($k))) && (!$c || eval($c)))
          $r[] = $this->prune($m, $col);
      }
      return $r;
    } elseif(($m = json_decode($cache->get($key))) && (!$c || eval($c)))
      return $this->prune($m, $col);
  }

  function find($primary, $col = '*') {
    _getcache:
    try {
      $c = $this->class::getCache();
      if(is_array($primary)) {
        $r = [];
        foreach($primary as $v) {
          if($t = $this->match($this->prefix . $v, $col, $c))
            $r[] = $t;
        }
        $r = new Collection($r);
      } else
        $r = $this->match($this->prefix . $primary, $col, $c);
    } catch(Throwable $e) {
      goto _getcache;
    }
    $this->class::putCache($c);
    return $r;
  }

  function first($col = '*'): ?BaseModel {
    _getcache:
    try {
      $c = $this->class::getCache();
      $ks = $c->keys("$this->prefix*");
      if($ks) {
        foreach($ks as $k)
          if($r = $this->match($k, $col, $c))
            goto _end;
        if(!$this->pure) {
          if($r = parent::first($col))
            $this->cache([$r], $c);
        } else
          $r = null;
      } else
        $r = null;
    } catch(Throwable $e) {
      goto _getcache;
    }
    _end:
    $this->class::putCache($c);
    return $r;
  }

  function get($col = '*'): ?Collection {
    _getcache:
    try {
      $c = $this->class::getCache();
      if($this->pure) {
        $r = $this->match($c->keys($this->prefix . '*'), $col, $c);
        if($r) {
          $orderBy = $this->orderBy ?: [$this->class::getPrimary(), false];
          $k = $orderBy[0];
          if(is_string($r[0]->$k))
            if($orderBy[1] == 'desc')
              usort($r, function($a, $b) use($k) {
                return strcmp($b->$k, $a->$k);
              });
            else
              usort($r, function($a, $b) use($k) {
                return strcmp($a->$k, $b->$k);
              });
          elseif($orderBy[1] == 'desc')
            usort($r, function($a, $b) use($k) {
              return $b->$k <=> $a->$k;
            });
          else
            usort($r, function($a, $b) use($k) {
              return $a->$k <=> $b->$k;
            });
        }
        $r = new Collection($r);
      } else {
        $r = parent::get($col);
        $this->cache($r->toArray(), $c);
      }
    } catch(Throwable $e) {
      goto _getcache;
    }
    $this->class::putCache($c);
    return $r;
  }
}

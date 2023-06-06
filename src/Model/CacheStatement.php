<?php
namespace Oblind\Model;

use Oblind\Cache\BaseCache;

class CacheStatement extends Statement {
  protected string $prefix;
  protected bool $pure;

  function __construct($class) {
    parent::__construct($class);
    $this->prefix = $class::PREFIX;
    $this->pure = $class::pure();
  }

  protected function cache($a, BaseCache $cache) {
    $l = strlen($this->prefix) + 1;
    $ps = $cache->keys($this->prefix . ':*');
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
      $cache->set($this->prefix . ':' . $m->{$pr}, json_encode($m->getData(), JSON_UNESCAPED_UNICODE));
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
      $cdts = [];
      foreach($this->condition as $cdt) {
        $i = 0;
        $cs = [];
        $ks = [];
        foreach($cdt as $k => $v) {
          if(is_string($k)) {
            $ks[] = $k;
            if(is_array($v)) {
              for($j = 0, $c = count($v); $j < $c; $j += 2) {
                if(is_string($v[$j]))
                  $v[$j] = '\'' . addslashes($v[$j]) . '\'';
                elseif($v[$j] === null)
                  $v[$j] = 'null';
                $op = $v[$j + 1] ?? '==';
                $cs[] = "(\$m->$k $op {$v[$j]})";
              }
            } else {
              if(is_string($v))
                $v = '\'' . addslashes($v) . '\'';
              elseif($v === null)
                $v = 'null';
              $op = $cdt[$i++] ?? '==';
              if($op == '=')
                $op = '==';
              $cs[] = "property_exists(\$m, '$k') && (\$m->$k $op $v)";
            }
            /*if($m->$k != $v) {
              $f = false;
              break;
            }*/
          }
        }
        $cdts[] = '(' . implode(' && ', $cs) . ')';
      }
      /*if($ks)
        array_unshift($cs, 'isset(' . implode(', ', array_map(function($k) {
          return "\$m->$k";
        }, $ks)) . ')');
      */
      $c = 'return ' . implode(' || ', $cdts) . ';';
    } else
      $c = null;
    if(is_array($key)) {
      $r = [];
      foreach($key as $k) {
        if(($m = json_decode($cache->get($k))) && (!$c || eval($c)))
          $r[] = $this->prune($m, $col);
      }
      return $r;
    } elseif(($m = json_decode($cache->get($key)))) {
      if(!$c || eval($c))
        return $this->prune($m, $col);
    }
  }

  function find($primary, $col = '*') {
    $c = 0;
    _getcache:
    try {
      $cache = $this->class::getCache();
      if(is_array($primary)) {
        $r = [];
        foreach($primary as $v) {
          if($t = $this->match($this->prefix . ':' . $v, $col, $cache))
            $r[] = $t;
        }
        $r = new Collection($r);
      } else
        $r = $this->match($this->prefix . ':' . $primary, $col, $cache);
    } catch(\Throwable $e) {
      if($c++ < 10) {
        usleep(50000);
        goto _getcache;
      } else
        throw $e;
    }
    $this->class::putCache($cache);
    return $r;
  }

  function first($col = '*'): ?BaseModel {
    $c = 0;
    _getcache:
    try {
      $cache = $this->class::getCache();
      $ks = $cache->keys("$this->prefix:*");
      if($ks) {
        foreach($ks as $k)
          if($r = $this->match($k, $col, $cache))
            goto _end;
        if(!$this->pure) {
          if($r = parent::first($col))
            $this->cache([$r], $cache);
        } else
          $r = null;
      } else
        $r = null;
    } catch(\Throwable $e) {
      if($c++ < 10) {
        usleep(50000);
        goto _getcache;
      } else
        throw $e;
    }
    _end:
    $this->class::putCache($cache);
    return $r;
  }

  function get($col = '*'): Collection {
    $c = 0;
    _getcache:
    try {
      $cache = $this->class::getCache();
      if($this->pure) {
        $r = $this->match($cache->keys($this->prefix . ':*'), $col, $cache);
        if($r) {
          $orderBy = $this->orderBy ?: [$this->class::getPrimary(), false];
          $k = $orderBy[0];
          if(is_string($r[0]->$k)) {
            if($orderBy[1] == 'desc')
              usort($r, function($a, $b) use($k) {
                return strcmp($b->$k, $a->$k);
              });
            else
              usort($r, function($a, $b) use($k) {
                return strcmp($a->$k, $b->$k);
              });
          } elseif($orderBy[1] == 'desc') {
            usort($r, function($a, $b) use($k) {
              return $b->$k <=> $a->$k;
            });
          } else
            usort($r, function($a, $b) use($k) {
              return $a->$k <=> $b->$k;
            });
        }
        $result = new Collection($r ?? []);
      } else {
        $result = parent::get($col);
        $this->cache($result->toArray(), $cache);
      }
      $this->class::putCache($cache);
      return $result;
    } catch(\Throwable $e) {
      if($c++ < 10) {
        usleep(50000);
        goto _getcache;
      } else
        throw $e;
    }
  }
}

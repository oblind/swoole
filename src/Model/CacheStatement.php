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

  protected function match($k, $col, BaseCache $cache) {
    if($m = json_decode($cache->get($k))) {
      $f = true;
      if(is_array($this->condition))
        foreach($this->condition as $c => $v)
          if($m->$c != $v) {
            $f = false;
            break;
          }
      if($f) {
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
    }
  }

  function find($primary, $col = '*') {
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
    $this->class::putCache($c);
    return $r;
  }

  function first($col = '*') {
    $c = $this->class::getCache();
    $ks = $c->keys("$this->prefix*");
    foreach($ks as $k)
      if($r = $this->match($k, $col, $c))
        goto _end;
    if(!$this->pure) {
      if($r = parent::first($col))
        $this->cache([$r], $c);
    } else
      $r = null;
    _end:
    $this->class::putCache($c);
    return $r;
  }

  function get($col = '*') {
    $c = $this->class::getCache();
    if($this->pure) {
      $ks = $c->keys($this->prefix . '*');
      $r = [];
      foreach($ks as $k)
        if($m = $this->match($k, $col, $c))
          $r[] = $m;
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
    $this->class::putCache($c);
    return $r;
  }
}

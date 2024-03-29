<?php
namespace Oblind\Model;

use Oblind\Cache\BaseCache;
use Oblind\Model\CacheStatement;

abstract class CacheModel extends BaseModel {
  const PREFIX = '';
  protected static bool $pure = true;
  protected static bool $loaded = false;

  abstract static function getCache(): BaseCache;

  abstract static function putCache(BaseCache $cache);

  static function where($condition, $params = null): Statement {
    return (new CacheStatement(get_called_class()))->where($condition, $params);
  }

  static function orderBy(string $by, bool $order = false): Statement {
    return (new CacheStatement(get_called_class()))->orderBy($by, $order);
  }

  static function find($primary, $col = '*') {
    return (new CacheStatement(get_called_class()))->find($primary, $col);
  }

  static function get($col = '*'): Collection {
    return (new CacheStatement(get_called_class()))->get($col);
  }

  static function first($col = '*'): ?BaseModel {
    return (new CacheStatement(get_called_class()))->first($col);
  }

  static function clear() {
    $c = 0;
    _getcache:
    try {
      $cache = static::getCache();
      $cache->clear();
      $cache->delete('_loaded');
    } catch(\Throwable $e) {
      echo "EXCEPTION\n" . $e;
      if($c++ < 10) {
        usleep(50000);
        goto _getcache;
      } else
        throw $e;
    }
    static::putCache($cache);
  }

  static function pure() {
    return static::$pure;
  }

  static function loaded(): bool {
    $c = 0;
    _getcache:
    try {
      $cache = static::getCache();
      $r = static::$loaded || (bool)$cache->get('_loaded');
    } catch(\Throwable $e) {
      echo "EXCEPTION\n" . $e;
      if($c++ < 10) {
        usleep(50000);
        goto _getcache;
      } else
        throw $e;
    }
    static::putCache($cache);
    return $r;
  }

  static function load(int $id = 0) {
    $c = 0;
    _getcache:
    try {
      $cache = static::getCache();
      static::setReturnRaw(true);
      if($id) {
        if($m = parent::find($id)) {
          $cache->set(static::PREFIX . ':' . $m->{static::$primary}, json_encode($m->getData(), JSON_UNESCAPED_UNICODE));
        }
      } else {
        if(!static::$loaded) {
          if(!$cache->get('_loaded'))
            $cache->set('_loaded', 1);
          static::$loaded = true;
        }
        foreach(parent::get()->toArray() as $m)
          $cache->set(static::PREFIX . ':' . $m->{static::$primary}, json_encode($m->getData(), JSON_UNESCAPED_UNICODE));
      }
      static::setReturnRaw(false);
    } catch(\Throwable $e) {
      echo "EXCEPTION\n" . $e;
      if($c++ < 10) {
        usleep(50000);
        goto _getcache;
      } else
        throw $e;
    }
    static::putCache($cache);
  }

  function getData() {
    return $this->_data;
  }

  function save(): int {
    $create = $this->_create;
    $r = parent::save();
    static::setReturnRaw(true);
    $c = static::getCache();
    if($create) {
      $m = parent::find($this->{static::$primary});
      $this->_data = $m->_data;
    }
    $c->set(static::PREFIX . ':' . $this->{static::$primary}, json_encode($this->_data, JSON_UNESCAPED_UNICODE));
    static::setReturnRaw(false);
    static::putCache($c);
    return $r;
  }

  function delete(): int|false {
    $r = parent::delete();
    $c = static::getCache();
    $c->delete(static::PREFIX . ':' . $this->{static::$primary});
    static::putCache($c);
    return $r;
  }
}

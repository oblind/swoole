<?php
namespace Oblind\Model;

use Oblind\Model\CacheStatement;
use Oblind\Cache\CacheTrait;

abstract class CacheModel extends BaseModel {
  use CacheTrait;

  protected static $pure = true;
  protected static $loaded;

  static function where($condition, $params = null): Statement {
    return (new CacheStatement(get_called_class()))->where($condition, $params);
  }

  static function orderBy(string $by, bool $order = false): Statement {
    return (new CacheStatement(get_called_class()))->orderBy($by, $order);
  }

  static function find($primary, string $col = '*') {
    return (new CacheStatement(get_called_class()))->find($primary, $col);
  }

  static function get(string $col = '*'): ?Collection {
    return (new CacheStatement(get_called_class()))->get($col);
  }

  static function first(string $col = '*'): ?BaseModel {
    return (new CacheStatement(get_called_class()))->first($col);
  }

  static function clear() {
    $c = static::getCache();
    $c->clear();
    $c->delete('_loaded');
    static::putCache($c);
  }

  static function pure() {
    return static::$pure;
  }

  static function loaded() {
    $c = static::getCache();
    $r = static::$loaded || $c->get('_loaded');
    static::putCache($c);
    return $r;
  }

  static function load() {
    $c = static::getCache();
    if(!static::$loaded) {
      if(!$c->get('_loaded'))
        $c->set('_loaded', 1);
      static::$loaded = true;
    }
    static::setReturnRaw(true);
    foreach(parent::get()->toArray() as $m)
      $c->set(static::PREFIX . $m->{static::$primary}, json_encode($m->getData(), JSON_UNESCAPED_UNICODE));
    static::setReturnRaw(false);
    static::putCache($c);
  }

  function getData() {
    return $this->_data;
  }

  function save() {
    parent::save();
    static::setReturnRaw(true);
    $c = static::getCache();
    $c->set(static::PREFIX . $this->{static::$primary}, json_encode($this->_data, JSON_UNESCAPED_UNICODE));
    static::setReturnRaw(false);
    static::putCache($c);
  }

  function delete() {
    parent::delete();
    ($c = static::getCache())->delete(static::PREFIX . $this->{static::$primary});
    static::putCache($c);
  }
}

<?php
namespace Oblind\Model;

use Oblind\Model\CacheStatement;
use Oblind\Cache\CacheTrait;

abstract class CacheModel extends BaseModel {
  use CacheTrait;

  protected static $pure = true;
  protected static $loaded;

  static function __callStatic($method, $param) {
    return (new CacheStatement(get_called_class()))->$method(...$param);
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
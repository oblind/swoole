<?php
namespace Oblind\Cache;

use Psr\SimpleCache\CacheInterface;

abstract class BaseCache implements CacheInterface {
  /**@var \SplQueue $pool */
  protected static $pool;

  static function createCache() {
    return new static;
  }

  static function initPool() {
    static::$pool = new \SplQueue;
  }

  static function getCache(): BaseCache {
    if(static::$pool->count())
      return static::$pool->pop();
    return static::createCache();
  }

  static function putCache(BaseCache $cache) {
    static::$pool->push($cache);
  }

  abstract function keys($pattern);
}

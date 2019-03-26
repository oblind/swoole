<?php
namespace Oblind\Cache;

trait CacheTrait {
  /**@var \SplQueue */
  protected static $pool;

  abstract static function createCache(): BaseCache;

  static function initCachePool() {
    static::$pool = new \SplQueue;
  }

  static function getCache(): BaseCache {
    if(static::$pool->count())
      return static::$pool->pop();
    return new static;
  }

  static function putCache(BaseCache $cache) {
    static::$pool->push($cache);
  }
}

<?php
namespace Oblind\Cache;

trait CacheTrait {
  protected static bool $persistent;
  protected static \SplQueue $pool;

  abstract static function createCache(): BaseCache;

  static function initCachePool(bool $persistent = true) {
    static::$persistent = $persistent;
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
}

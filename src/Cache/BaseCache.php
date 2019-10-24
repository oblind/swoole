<?php
namespace Oblind\Cache;

use Psr\SimpleCache\CacheInterface;

abstract class BaseCache implements CacheInterface {
  use CacheTrait;

  static function createCache(): BaseCache {
    return new static;
  }

  abstract function keys($pattern);
}

BaseCache::initCachePool();

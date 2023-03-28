<?php
namespace Oblind\Cache;

use Psr\SimpleCache\CacheInterface;

abstract class BaseCache implements CacheInterface {
  use CacheTrait;

  static function createCache(bool $persistent = true): BaseCache {
    return new static($persistent);
  }

  abstract function keys($pattern);
}

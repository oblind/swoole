<?php
namespace Oblind\Cache;

use Psr\SimpleCache\CacheInterface;

abstract class BaseCache implements CacheInterface {
  use CacheTrait;

  static function createCache(bool $persistent = true): BaseCache {
    return new static($persistent);
  }

  abstract function keys(string $pattern = '*');

  abstract function setIfExists($key, $value, $ttl = null): bool;

  abstract function setIfNotExists($key, $value, $ttl = null): bool;
}

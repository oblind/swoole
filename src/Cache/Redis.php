<?php
namespace Oblind\Cache;

class Redis extends BaseCache {
  public static $host = 'localhost';
  public static $port = 6379;
  public static $timeout = 3;
  /**@var \Redis $redis */
  public $redis;

  static function initPool() {
    static::initPool();
    static::putCache(static::getCache());
  }

  function __construct(string $prefix = null) {
    $this->redis = new \Redis;
    $this->redis->pconnect(static::$host, static::$port, static::$timeout, $prefix);
  }

  function __call($name, $arguments) {
    return $this->redis->$name(...$arguments);
  }

  function keys($pattern) {
    return $this->redis->keys($pattern);
  }

  function get($key, $default = null) {
    return $this->redis->get($key) ?? $default;
  }

  function set($key, $value, $ttl = null) {
    if(is_array($key))
      foreach($key as $k)
        $this->set($k, $value);
    else
      $this->redis->set($key, $value, $ttl);
  }

  function delete($key) {
    $this->redis->delete($key);
  }

  function clear() {
    $this->redis->flushAll();
  }

  function getMultiple($keys, $default = null) {
    if(!(is_array($keys) || $keys instanceof \Traversable))
      throw new InvalidArgumentException;
    $r = [];
    foreach($keys as $k)
      $r[$k] = $this->get($k, $default);
    return $r;
  }

  function setMultiple($values, $ttl = null) {
    if(!(is_array($values) || $values instanceof \Traversable))
      throw new InvalidArgumentException;
    foreach($values as $k => $v)
      $this->set($k, $v);
  }

  function deleteMultiple($keys) {
    if(!(is_array($values) || $values instanceof \Traversable))
      throw new InvalidArgumentException;
    foreach($keys as $k)
      $this->delete($k);
  }

  function has($key) {
    return $this->keys($key) ? true : false;
  }
}

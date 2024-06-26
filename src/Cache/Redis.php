<?php
namespace Oblind\Cache;

use Oblind\Application;

class Redis extends BaseCache {
  public \Redis $redis;

  function __construct() {
    $cfg = array_merge([
      'host' => 'localhost',
      'port' => 6379,
      'timeout' => 3,
      'index' => 0
    ], Application::config()['redis'] ?? []);
    $this->redis = new \Redis;
    $c = 100;
    while(1) {
      try {
        if(static::$persistent)
          $this->redis->pconnect($cfg['host'], $cfg['port'], $cfg['timeout']);
        else
          $this->redis->connect($cfg['host'], $cfg['port'], $cfg['timeout']);
        if($password = $cfg['password'] ?? null) {
          $this->redis->auth($password);
        }
        $this->redis->select($cfg['index']);
        break;
      } catch(\Throwable $e) {
        if($c--)
          usleep(50000);
        else
          throw $e;
      }
    }
  }

  function __call($name, $arguments) {
    return $this->redis->$name(...$arguments);
  }

  function keys(string $pattern = '*') {
    return $this->redis->keys($pattern);
  }

  function setIfExists($key, $value, $ttl = null): bool {
    return $this->redis->set($key, $value, $ttl ? ['xx', 'ex' => $ttl] : null);
  }

  function setIfNotExists($key, $value, $ttl = null): bool {
    return $this->redis->set($key, $value, $ttl ? ['nx', 'ex' => $ttl] : null);
  }

  function get($key, $default = null) {
    $c = 0;
    _retry:
    try {
      return $this->redis->get($key) ?? $default;
    } catch(\Throwable $e) {
      if($c++ < 3) {
        usleep(50000);
        goto _retry;
      } else
        throw $e;
    }
  }

  function set($key, $value, $ttl = null) {
    $c = 0;
    _retry:
    try {
      if(is_array($key))
        foreach($key as $k)
          $this->set($k, $value);
      else
        $this->redis->set($key, $value, $ttl);
    } catch(\Throwable $e) {
      if($c++ < 3) {
        usleep(50000);
        goto _retry;
      } else
        throw $e;
    }
  }

  function delete($key) {
    $this->redis->del($key);
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
    if(!(is_array($keys) || $keys instanceof \Traversable))
      throw new InvalidArgumentException;
    foreach($keys as $k)
      $this->delete($k);
  }

  function has($key) {
    return $this->keys($key) ? true : false;
  }
}

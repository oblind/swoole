<?php
namespace Oblind\Cache;

use Oblind\Application;

class Redis extends BaseCache {
  /**@var \Redis */
  public $redis;

  function __construct(string $prefix = null) {
    $cfg = array_merge([
      'host' => 'localhost',
      'port' => 6379,
      'timeout' => 3,
      'index' => 0
    ], Application::config()['redis'] ?? []);
    $this->redis = new \Redis;
    $this->redis->pconnect($cfg['host'], $cfg['port'], $cfg['timeout']);
    $this->redis->select($cfg['index']);
  }

  function __call($name, $arguments) {
    return $this->redis->$name(...$arguments);
  }

  function keys($pattern) {
    return $this->redis->keys($pattern);
  }

  function get($key, $default = null) {
    try {
      return $this->redis->get($key) ?? $default;
    } catch(\Throwable $e) {
      $s = "\n\n\n\n" . $e->getMessage();
      echo "$s\n\n\n";
    }
  }

  function set($key, $value, $ttl = null) {
    try {
      if(is_array($key))
        foreach($key as $k)
          $this->set($k, $value);
      else
        $this->redis->set($key, $value, $ttl);
    } catch(\Throwable $e) {
      $s = "\n\n\n\n" . $e->getMessage();
      echo "$s\n\n\n";
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

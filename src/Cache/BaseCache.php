<?php
namespace Oblind\Cache;

use Psr\SimpleCache\CacheInterface;

abstract class BaseCache implements CacheInterface {
  abstract function keys($pattern);
}

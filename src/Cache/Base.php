<?php
namespace Oblind\Cache;

use Psr\SimpleCache\CacheInterface;

abstract class Base implements CacheInterface {
  abstract function keys($pattern);
}

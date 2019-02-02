<?php
namespace Oblind\Cache;

use Psr\SimpleCache\InvalidArgumentException as InvalidArgument;

class InvalidArgumentException extends \Exception implements InvalidArgument {
}

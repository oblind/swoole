<?php
namespace Oblind\Http;

use Yaf\Request\Http;

abstract class Middleware {
  abstract function handle(Http $request, $next): void;
}

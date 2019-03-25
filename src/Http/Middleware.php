<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;

abstract class Middleware {
  abstract function handle(Request $request, Response $response, callable $next): void;
}

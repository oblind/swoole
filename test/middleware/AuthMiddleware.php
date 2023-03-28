<?php
namespace Swoole;

use Oblind\Http\Middleware;
use Oblind\Http\RequestInfo;
use Swoole\Http\Request;
use Swoole\Http\Response;

class AuthMiddleware extends Middleware {
  function handle(Request $request, Response $response, RequestInfo $info, callable $next): void {
    echo "auth middleware {$info->route->name}\n";
    $next($request, $response, $info);
  }
}

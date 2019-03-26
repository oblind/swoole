<?php
namespace Swoole;

use Oblind\Http\Middleware;
use Swoole\Http\Request;
use Swoole\Http\Response;

class AuthMiddleware extends Middleware {
  function handle(Request $request, Response $response, callable $next): void {
    echo "auth middleware {$request->route->name}\n";
    $next($request, $response);
  }
}

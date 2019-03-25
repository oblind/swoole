<?php
namespace Swoole;

use Oblind\Http\Middleware;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Oblind\Http\Route\BaseRoute;

class AuthMiddleware extends Middleware {
  function handle(Request $request, Response $response, BaseRoute $route, callable $next): void {
    $response->write("auth middleware $route->action<br>\n");
    $next($request, $response, $route);
  }
}

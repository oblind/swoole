<?php
namespace Oblind\Http\Route;

use Swoole\Http\Request;
use Oblind\Http\RequestInfo;
use Oblind\Http\Router;
use Oblind\Http\Controller;
use Oblind\Http\Middleware;

abstract class BaseRoute {
  public Router $router;
  public string $name;
  public ?Controller $controller;
  public ?string $action;
  public array $params;
  public array $middlewares = [];

  function __construct(Router $router, Controller $controller = null, string $action = null, array $params = []) {
    $this->router = $router;
    $this->controller = $controller;
    $this->action = $action;
    $this->params = [];
  }

  static function getField(string $uri, int &$offset): ?string {
    $l = strlen($uri);
    if($l && $offset < $l) {
      $p = strpos($uri, '/', $offset);
      if($p !== false) {
        $r = substr($uri, $offset, $p - $offset);
        $offset = $p == $l - 1 ? false : $p + 1;
      } else {
        $r = substr($uri, $offset);
        $offset = false;
      }
      return $r;
    } else {
      $offset = false;
      return null;
    }
  }

  static function getFields(string $uri): array {
    $p = 0;
    $r = [];
    if($uri && $uri[0] == '/')
      $uri = substr($uri, 1);
    do {
      $f = static::getField($uri, $p);
      if($f !== null)
        $r[] = $f;
    } while($p !== false);
    return $r;
  }

  function addMiddleware(Middleware $middleware, int $index = -1): BaseRoute {
    if($index == -1)
      $this->middlewares[] = $middleware;
    else
      array_splice($this->middlewares, $index, 0, $middleware);
    return $this;
  }

  abstract function route(Request $request): ?RequestInfo;
}

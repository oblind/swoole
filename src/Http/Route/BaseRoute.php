<?php
namespace Oblind\Http\Route;

use Swoole\Http\Request;
use Oblind\Http\Router;
use Oblind\Http\Controller;

abstract class BaseRoute {
  /**@var Router */
  public $router;
  /**@var string */
  public $name;
  /**@var Controller */
  public $controller;
  /**@var string */
  public $action;
  /**@var array */
  public $params;
  /**@var array */
  public $middlewares;

  function __construct(Router $router, Controller $controller = null, string $action = null, array $params = null) {
    $this->router = $router;
    $this->controller = $controller;
    $this->action = $action;
    $this->params = $params ?? [];
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

  function insertMiddleware(array $middlewares, int $index = -1) {
    if(!$this->middlewares)
      $this->middlewares = [];
    if($index == -1)
      $this->middlewares = array_merge($this->middlewares, $middlewares);
    else {
      $t = array_splice($this->middlewares, 0, $index);
      $this->middlewares = array_merge($t, $middlewares, $this->middlewares);
    }
  }

  abstract function route(Request $request): bool;
}

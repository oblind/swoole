<?php
namespace Oblind\Http\Route;

use Swoole\Http\Request;
use Oblind\Http\Router;
use Oblind\Http\Controller;

abstract class BaseRoute {
  /**@var Router $router */
  public $router;
  /**@var string $module */
  public $module;
  /**@var Controller $controller */
  public $controller;
  /**@var string $action */
  public $action;
  /**@var array $params */
  public $params;

  function __construct(Router $router, string $module = null, string $controller = null, string $action = null, array $params = null) {
    $this->router = $router;
    $this->module = $module;
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

  abstract function route(Request $request): bool;
}

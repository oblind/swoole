<?php
namespace Oblind\Http\Route;

use Swoole\Http\Request;
use Oblind\Http\Router;

class Rewrite extends BaseRoute {
  public string $rule;

  function __construct(Router $router, string $rule, array $route) {
    $this->router = $router;
    $this->rule = $rule;
    parent::__construct($route['controller'], $route['action']);
  }

  function route(Request $request): bool {
    return true;
  }
}

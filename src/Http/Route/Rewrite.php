<?php
namespace Oblind\Http\Route;

use Swoole\Http\Request;

class Rewrite extends BaseRoute {
  /**@var string $rule */
  public $rule;

  function __construct(string $rule, array $route) {
    $this->rule = $route['rule'];
    parent::__construct($route['module'], $route['controller'], $route['action']);
  }

  function route(Request $request): bool {
    return true;
  }
}

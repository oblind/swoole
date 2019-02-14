<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Oblind\Http\Route\BaseRoute;
use Oblind\Http\Route\Restful;
use Oblind\Http\Controller;

class Router {
  /**@var array $routes */
  public $routes = [];
  /**@var BaseRoute */
  public $defaultRoute;
  /**@var array $controllers */
  public $controllers = [];

  function __construct() {
    $this->defaultRoute = $this->getDefaultRoute();
  }

  function getDefaultRoute(): BaseRoute {
    return new Restful($this);
  }

  function addController(Controller $controller, string $module = null) {
    $c = str_replace('\\', '/', strtolower(get_class($controller)));
    if(substr($c, -10) == 'controller')
      $c = substr($c, 0, strlen($c) - 10);
    if($module) {
      $c = ($p = strrpos($c, '/')) === false ? $c : substr($c, $p + 1);
      if($module != '/')
        $c = "$module/$c";
    }
    $this->controllers["/$c"] = $controller;
  }

  function addRewrite(string $method, string $rule, array $route, $middleware = null) {
    $route['method'] = $method;
    if($middleware)
      if(isset($route['middleware']))
        $route['middleware'] = array_merge($route['middleware'], $middleware);
      else
        $route['middleware'] = $middleware;
    $this->$routes[] = new Route\Rewrite($rule, $route);
  }

  function middleware(Middleware $middleware, Closure $callback) {
    $ir = new InnerRouter;
    $callback($ir);
    foreach($ir->routes as $r)
      $this->act($r[0], $r[1], $r[2], $r[3], $r[4]);
  }

  function prefix(string $prefix, Closure $callback) {
    if(($l = strlen($prefix)) && $prefix[$l - 1] != '/')
      $prefix .= '/';
    $ir = new InnerRouter;
    $callback($ir);
    foreach($ir->routes as $r)
      $this->act($r[0], "$prefix{$r[1]}", $r[2], $r[3], $r[4]);
  }

  function get(string $rule, array $route) {
    $this->act('GET', $rule, $route, $name, $router);
  }

  function post(string $rule, array $route) {
    $this->act('POST', $rule, $route, $name, $router);
  }

  function put(string $rule, array $route) {
    $this->act('PUT', $rule, $route, $name, $router);
  }

  function delete(string $rule, array $route) {
    $this->act('DELETE', $rule, $route, $name, $router);
  }

  function route(Request $request, Response $response): bool {
    foreach($this->routes as $r)
      if($r->route($request)) {
        $this->controllers["{$r->route['module']}\\{$r->route['controller']}"]->$r->route['action']();
        return true;
      }
    if($this->defaultRoute->route($request)) {
      $this->dispatch($this->defaultRoute->controller, $this->defaultRoute, $request, $response);
      return true;
    } else
      return false;
  }

  function dispatch(Controller $controller, BaseRoute $route, Request $request, Response $response) {
    $controller->request = $request;
    $controller->response = $response;
    $controller->route = $route;
    switch($request->server['request_method']) {
    case 'GET':
      if(method_exists($controller, 'indexAction'))  //index
        $controller->indexAction();
      break;
    case 'POST':
      if(method_exists($controller, 'storeAction'))
        $controller->storeAction();
      break;
    case 'PUT':
      if(method_exists($controller, 'updateAction'))
        $controller->updateAction();
      break;
    case 'DELETE':
      if(method_exists($controller, 'destroyAction'))
        $controller->destroyAction();
      break;
    }
  }
}

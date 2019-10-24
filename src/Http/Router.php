<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Oblind\Http\Route\BaseRoute;
use Oblind\Http\Route\Restful;
use Oblind\Http\Controller;
use Oblind\Http\Pipeline;

class Router {
  /**@var array */
  public $routes = [];
  /**@var BaseRoute */
  public $curRoute;
  /**@var BaseRoute */
  public $defaultRoute;
  /**@var array */
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
      $c = $module == '/' ? '' : "$module/$c";
    }
    $controller->router = $this;
    $r = (object)[
      'controller' => $controller,
      'args' => []
    ];
    foreach((new \ReflectionClass($controller))->getMethods() as $m)
      if(substr($m->name, -6) == 'Action') {
        if($ps = $m->getParameters()) {
          $a = [];
          foreach($ps as $p)
            $a[$p->name] = (string)$p->getType();
          $r->args[substr($m->name, 0, strlen($m->name) - 6)] = $a;
        }
      }
    $this->controllers["/$c"] = $r;
  }

  function addRewrite(string $method, string $rule, array $route, $middleware = null) {
    $route['method'] = $method;
    if($middleware)
      if(isset($route['middleware']))
        $route['middleware'] = array_merge($route['middleware'], $middleware);
      else
        $route['middleware'] = $middleware;
    $this->routes[] = new Route\Rewrite($this, $rule, $route);
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

  function route(Request $request): bool {
    $this->curRoute = null;
    foreach($this->routes as $r)
      if($r->route($request)) {
        $this->curRoute = $r;
        return true;
      }
    if($this->defaultRoute->route($request)) {
      $this->curRoute = $this->defaultRoute;
      return true;
    }
    return false;
  }

  function resole(Request $request, Response $response) {
    $c = $request->controller;
    $c->request = $request;
    $c->response = $response;
    $c->{"{$c->request->action}Action"}(...($request->args ?? []));
  }

  function dispatch(Request $request, Response $response): bool {
    if($this->route($request)) {
      if((($m = $request->server['request_method']) == 'POST' || $m == 'PUT') && strpos($request->header['content-type'] ?? 0, 'application/json') !== false)
        $request->post = json_decode($request->rawContent(), true);
      if($this->curRoute->middlewares) {
        $p = new Pipeline;
        $p->send($request, $response);
        foreach($this->curRoute->middlewares as $m) {
          $in = in_array($this->curRoute->name, $m->exceptions);
          if($m->blacklistMode) {
            if($in)
              $p->pipe([$m, 'handle']);
          } elseif(!$in)
            $p->pipe([$m, 'handle']);
        }
        $p->then([$this, 'resole']);
      } else
        $this->resole($request, $response);
      return true;
    }
    return false;
  }
}

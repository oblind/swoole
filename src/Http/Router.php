<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Oblind\Http\Route\BaseRoute;
use Oblind\Http\Route\Restful;
use Oblind\Http\Controller;
use Oblind\Http\Pipeline;
use Oblind\WebSocket;

class Router {
  public WebSocket $svr;
  public array $routes = [];
  public ?BaseRoute $curRoute;
  public BaseRoute $defaultRoute;
  public array $controllers = [];

  function __construct(WebSocket $svr) {
    $this->svr = $svr;
    $this->defaultRoute = $this->getDefaultRoute();
  }

  function getDefaultRoute(): BaseRoute {
    return new Restful($this);
  }

  function addController(Controller $controller, string $module = null): Router {
    $c = str_replace('\\', '/', strtolower(get_class($controller)));
    if(substr($c, -10) == 'controller')
      $c = substr($c, 0, strlen($c) - 10);
    if($module) {
      $c = ($p = strrpos($c, '/')) === false ? $c : substr($c, $p + 1);
      $c = $module == '/' ? '' : "$module/$c";
    }
    $controller->svr = $this->svr;
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
            $a[$p->name] = $p->getType()->getName();
          $r->args[substr($m->name, 0, strlen($m->name) - 6)] = $a;
        }
      }
    $this->controllers["/$c"] = $r;
    return $this;
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

  function middleware(Middleware $middleware, callable $callback) {
    $ir = new InnerRouter;
    $callback($ir);
    foreach($ir->routes as $r)
      $this->act($r[0], $r[1], $r[2], $r[3], $r[4]);
  }

  function prefix(string $prefix, callable $callback) {
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
    try {
      $c->{"{$c->request->action}Action"}(...($request->args ?? []));
    } catch(\Throwable $e) {
      try {
        $s = $e->getMessage();
        $msg = $s . "\nStack trace:";
        if($ec = \Oblind\ERROR_STRING[$e->getCode()] ?? null)
          $msg = "$ec: $msg";
        foreach($e->backtrace ?? debug_backtrace() as $i => $l) {
          $msg .= "\n#$i " . (isset($l['file']) ? "{$l['file']}({$l['line']})" : '[internal function]') . ': ';
          if(isset($l['class']))
            $msg .= "{$l['class']}{$l['type']}";
          $msg .= "{$l['function']}()";
        }
        echo "$msg\n";
        $c->svr->log($msg);
        $response->status(RES_BAD_REQUEST);
        $response->end($request->header['x-requested-with'] ?? 0 == 'XMLHttpRequest' ? $s : str_replace("\n", "<br>\n", $msg));
      } catch(\Throwable $e) { //response已关闭
      }
    }
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

<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Oblind\Http\Route\BaseRoute;
use Oblind\Http\Route\Restful;
use Oblind\Http\Controller;
use Oblind\Http\Pipeline;
use Oblind\WebSocket;

const RES_BAD_REQUEST = 400;
const RES_NO_PERMISSION = 401;
const RES_FORBIDEN = 403;
const RES_NOT_FOUND = 404;
const RES_NOT_ALLOWED = 405;
const RES_INTERNAL_SERVER_ERROR = 500;

function isLocalIp(string $ip): bool {
  return ($h = substr($ip, 0, 4)) == '192.' || $h == '127.' || substr($ip, 0, 3) == '10.';
}

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
      'args' => [],
      'segments' => []
    ];
    foreach((new \ReflectionClass($controller))->getMethods() as $m)
      if(substr($m->name, -6) == 'Action') {
        $name = substr($m->name, 0, strlen($m->name) - 6);
        if(preg_match_all('/[A-Z][^A-Z]*/', $name, $us)) {
          $r->segments = array_map(fn($s) => lcfirst($s), $us[0]);
        }
        if($ps = $m->getParameters()) {
          $a = [];
          for($i = 3, $pc = count($ps); $i < $pc; $i++)
            $a[$ps[$i]->name] = $ps[$i]->getType()->getName();
          $r->args[$name] = $a;
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

  function route(Request $request): ?RequestInfo {
    $this->curRoute = null;
    foreach($this->routes as $r)
      if($info = $r->route($request)) {
        $this->curRoute = $r;
        return $info;
      }
    if($info = $this->defaultRoute->route($request)) {
      $this->curRoute = $this->defaultRoute;
      return $info;
    }
    return null;
  }

  function resole(Request $request, Response $response, RequestInfo $info) {
    $c = $info->controller;
    try {
      /*echo "===============\n";
      echo "{$request->server['request_method']} {$request->server['request_uri']}"
        , isset($request->server['query_string']) ? "?{$request->server['query_string']}\n" : "\n";
      echo $c::class . "->{$info->action}Action\n";
      if($request->files) {
        foreach($request->files as $f)
          echo "  {$f['name']}\n";
      }
      echo "\n";
      */
      if($info->args)
        $c->{"{$info->action}Action"}($request, $response, $info, ...($info->args));
      else
        $c->{"{$info->action}Action"}($request, $response, $info);
    } catch(\Throwable $e) {
      $msg = "EXCEPTION in {$request->server['request_method']} {$request->server['request_uri']}\n" . $e;
      $c->svr->show($msg);
      $response->status(RES_BAD_REQUEST);
      $response->end($request->header['x-requested-with'] ?? 0 == 'XMLHttpRequest' ? $msg : str_replace("\n", "<br>\n", $msg));
    }
  }

  function dispatch(Request $request, Response $response): bool {
    if($info = $this->route($request)) {
      if((($method = $request->server['request_method']) == 'POST' || $method == 'PUT') && ($request->header['content-type'] ?? 0) == 'application/json')
        $request->post = json_decode($request->rawContent(), true);
      if($this->curRoute->middlewares) {
        $p = new Pipeline;
        $p->send($request, $response, $info);
        foreach($this->curRoute->middlewares as $m) {
          $in = in_array($this->curRoute->name, $m->exceptions);
          try {
            if($m->blacklistMode) {
              if($in)
                $p->pipe([$m, 'handle']);
            } elseif(!$in)
              $p->pipe([$m, 'handle']);
          } catch(\Throwable $e) {
            $this->svr->show("EXCEPTION:\n" . $e);
          }
        }
        $p->then([$this, 'resole']);
      } else
        $this->resole($request, $response, $info);
      return true;
    }
    return false;
  }
}

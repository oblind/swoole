<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Oblind\Http\Route\BaseRoute;

class Pipeline {
  protected $pipes = [];
  /**@var Request */
  protected $request;
  /**@var Response */
  protected $response;
  /**@var BaseRoute */
  protected $route;

  function pipe($pipe): Pipeline {
    $this->pipes[] = $pipe;
    return $this;
  }

  function send(Request $request, Response $response, BaseRoute $route): Pipeline {
    $this->request = $request;
    $this->response = $response;
    $this->route = $route;
    return $this;
  }

  function then(callable $resole) {
    if($this->pipes) {
      array_reduce(array_reverse($this->pipes), function(callable $next, callable $cur) {
        return function(Request $request, Response $response, BaseRoute $route) use($next, $cur) {
          call_user_func($cur, $request, $response, $route, $next);
        };
      }, $resole)($this->request, $this->response, $this->route);
    }
  }
}
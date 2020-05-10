<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Oblind\Http\Route\BaseRoute;

class Pipeline {
  protected array $pipes = [];
  protected Request $request;
  protected Response $response;

  function pipe($pipe): Pipeline {
    $this->pipes[] = $pipe;
    return $this;
  }

  function send(Request $request, Response $response): Pipeline {
    $this->request = $request;
    $this->response = $response;
    return $this;
  }

  function then(callable $resole) {
    if($this->pipes) {
      array_reduce(array_reverse($this->pipes), function(callable $next, callable $cur) {
        return function(Request $request, Response $response) use($next, $cur) {
          call_user_func($cur, $request, $response, $next);
        };
      }, $resole)($this->request, $this->response);
    } else
      $resole($this->request, $this->response);
  }
}
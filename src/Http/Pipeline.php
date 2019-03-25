<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;

class Pipeline {
  protected $pipes = [];
  /**@var Request $request */
  protected $request;
  /**@var Response $response */
  protected $response;

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
    }
  }
}
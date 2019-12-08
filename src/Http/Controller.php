<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Oblind\Application;
use Swoole\Timer;

class Controller {
  /**@var array */
  protected array $listeners = [];
  /**@var Router */
  public Router $router;
  /**@var Request */
  public Request $request;
  /**@var Response */
  public Response $response;

  function write($msg) {
    if(is_object($msg) || is_array($msg))
      $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
    $this->response->write($msg);
  }

  function end($msg = null) {
    if(is_object($msg) || is_array($msg)) {
      $this->response->header('Content-Type', 'application/json; charset=utf-8');
      $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
    } else
      $this->response->header('Content-Type', 'text/html; charset=utf-8');
    $l = strlen($msg) / 1024;
    if($l > 160) { //过大, 以文件形式发送
      $f = tmpfile();
      fwrite($f, $msg);
      $this->response->sendfile(stream_get_meta_data($f)['uri']);
      //网速200k/s
      Timer::after(500 * (ceil($l / 100) + 1), function() use($f) {
        fclose($f);
      });
    } else
      $this->response->end($msg);
  }

  function error($msg, $code = RES_BAD_REQUEST) {
    $this->response->status($code);
    $this->end($msg);
  }

  function forward(string $path, string $action, array $params = null) {
    if($c = $this->router->controllers[$path] ?? null) {
      $c = $c->controller;
      $c->request = $this->request;
      $c->response = $this->response;
      if($params)
        $c->request->params = $params;
      $c->{"{$action}Action"}();
    }
  }

  function view(string $filename) {
    $p = Application::config()->viewPath ?? './view';
    $this->response->sendfile("$p/$filename");
  }

  function subscribe(string $event, callable $listener) {
    if(!isset($this->listeners[$event]))
      $this->listeners[$event] = [];
    if(!in_array($listener, $this->listeners[$event]))
      $this->listeners[$event][] = $listener;
  }

  function publish(string $event, $data) {
    if($ls = $this->listeners[$event] ?? null)
      foreach($ls as $l)
        $l($data);
  }
}

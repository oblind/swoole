<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Oblind\Application;
use Swoole\Timer;

class Controller {
  /**@var Router */
  public $router;
  /**@var Request */
  public $request;
  /**@var Response */
  public $response;

  function write($msg) {
    if(is_object($msg) || is_array($msg))
      $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
    $this->response->write($msg);
  }

  function end($msg = null) {
    if(is_object($msg) || is_array($msg))
      $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
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
}

<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Oblind\Application;
use Oblind\WebSocket;
use Swoole\Timer;

class Controller {
  public WebSocket $svr;
  public Router $router;
  public Request $request;
  public Response $response;

  function write($msg) {
    if(is_object($msg) || is_array($msg))
      $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
    $this->response->write($msg);
  }

  function end($msg = null, Response $res = null) {
    if(!$res)
      $res = $this->response;
    if(is_object($msg) || is_array($msg)) {
      $res->header('Content-Type', 'application/json; charset=utf-8');
      $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
    } else
      $res->header('Content-Type', 'text/html; charset=utf-8');
    $l = strlen($msg) / 1024;
    if($l > 160) { //过大, 以文件形式发送
      $f = tmpfile();
      fwrite($f, $msg);
      $res->sendfile(stream_get_meta_data($f)['uri']);
      //网速200k/s
      Timer::after(500 * (ceil($l / 100) + 1), function() use($f) {
        fclose($f);
      });
    } else
      $res->end($msg);
  }

  function error($msg, int $code = RES_BAD_REQUEST, Response $res = null) {
    if(!$res)
      $res = $this->response;
    $res->status($code);
    $this->end(['error' => $msg], $res);
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
    $p = Application::config()['viewPath'] ?? './view';
    $this->response->sendfile("$p/$filename");
  }

  //向用户/设备转发命令
  function publish(string $dest, int $id, string $cmd, $data = null, array $params = null) {
    $this->router->svr->publish($dest, $id, $cmd, $data, $params);
  }
}

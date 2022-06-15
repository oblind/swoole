<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Timer;
use Oblind\Http;
use Oblind\Application;
use Oblind\WebSocket;
use Oblind\Model\BaseModel;

class Controller {
  public WebSocket $svr;
  public Router $router;
  public Request $request;
  public Response $response;

  static protected function removeDir(string $path): int {
    if(is_dir($path)) {
      if($fs = scandir($path))
        foreach($fs as $f)
          if($f != '.' && $f != '..') {
            $p = "$path/$f";
            if(is_dir($p))
              static::removeDir($p);
            else
              unlink($p);
          }
      rmdir($path);
      return 0;
    }
    return -1;
  }

  static function removeModel(BaseModel $m, string $path, bool $reset = true) {
    $m->delete();
    if($reset)
      $m::resetAutoIncrement();
    static::removeDir($path);
  }

  function write($msg, Response $response) {
    if(is_object($msg) || is_array($msg))
      $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
    $response->write($msg);
  }

  function end($msg, Response $response) {
    if(is_object($msg) || is_array($msg)) {
      $response->header('Content-Type', 'application/json; charset=utf-8');
      $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
    } else
      $response->header('Content-Type', 'text/html; charset=utf-8');
    $l = $msg ? strlen($msg) / 1024 : 0;
    if($l > 160) { //过大, 以文件形式发送
      $fn = tempnam('/tmp', 'res');
      file_put_contents($fn, $msg);
      $response->sendfile($fn);
    } else
      $response->end($msg);
  }

  function error($msg, int $code, Response $response) {
    $response->status($code);
    $this->end(is_string($msg) ? ['error' => $msg] : $msg, $response);
  }

  function forward(string $path, string $action, Request $request, Response $response, ...$args) {
    if($c = $this->router->controllers[$path] ?? null) {
      $c = $c->controller;
      $c->{"{$action}Action"}($request, $response, ...$args);
    }
  }

  function view(string $filename, Response $response) {
    $p = Application::config()['viewPath'] ?? './view';
    $response->sendfile("$p/$filename");
  }

  //向用户/设备转发命令
  function publish(string $dest, int $id, string $cmd, $data = null, array $params = null) {
    $this->svr->publish($dest, $id, $cmd, $data, $params);
  }
}

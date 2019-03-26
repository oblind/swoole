<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Oblind\Application;

class Controller {
  /**@var Router */
  public $router;
  /**@var Request */
  public $request;
  /**@var Response */
  public $response;

  function forward(string $path, string $action, array $params = null) {
    if($c = $this->router->controllers[$path]) {
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

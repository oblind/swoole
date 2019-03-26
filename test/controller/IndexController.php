<?php
use Swoole\Http\Request;
use Swoole\Http\Response;
use Oblind\Http\Controller;
use Oblind\Http\Route\BaseRoute;

class IndexController extends Controller {
  function indexAction() {
    $this->response->end(json_encode($this->request->params));
    //$this->view('index.htm');
  }
}

<?php
use Swoole\Http\Request;
use Swoole\Http\Response;
use Oblind\Http\Controller;

class IndexController extends Controller {
  function indexAction(Request $request, Response $response) {
    $response->end(json_encode($request->params));
    //$this->view('index.htm', $response);
  }
}

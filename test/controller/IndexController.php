<?php
use Swoole\Http\Request;
use Swoole\Http\Response;
use Oblind\Http\RequestInfo;
use Oblind\Http\Controller;

class IndexController extends Controller {
  function indexAction(Request $request, Response $response, RequestInfo $info) {
    $response->end(json_encode($info->params));
    //$this->view('index.htm', $response);
  }
}

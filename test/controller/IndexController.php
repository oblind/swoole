<?php
use Oblind\Http\Controller;

class IndexController extends Controller {
  function indexAction(Request $request, Response $response, BaseRoute $route) {
    $this->view('index.htm');
  }
}

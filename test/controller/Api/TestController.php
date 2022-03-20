<?php
namespace Swoole\Api;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Oblind\Http\Controller;
//use Oblind\Http\Validator;

class TestController extends Controller {
  function indexAction(Request $request, Response $response) {
    $p = $request->params;
    if(array_key_exists('query', $p))
      echo "query\n";
    $this->forward('/', 'index', $request, $response);
    //$r = Validator::valid(['email' => 'oblind@163.com', 'password' => '123'], ['email' => 'email', 'password' => 'min:6'], $err);
    //$response->end($r ? 'ok' : $err);
  }
}

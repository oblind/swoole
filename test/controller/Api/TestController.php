<?php
namespace Swoole\Api;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Oblind\Http\Controller;
use Oblind\Http\Validator;
use Oblind\Http\Route\BaseRoute;

class TestController extends Controller {
  function indexAction() {
    $p = $this->request->params;
    if(array_key_exists('query', $p))
      echo "query\n";
    $this->forward('/', 'index');
    /*$r = Validator::valid(['email' => 'oblind@163.com', 'password' => '123'], ['email' => 'email', 'password' => 'min:6'], $err);
    $this->response->end($r ? 'ok' : $err);
    */
  }
}

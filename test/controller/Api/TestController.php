<?php
namespace Swoole\Api;

use Oblind\Http\Controller;
use Oblind\Http\Validator;

class TestController extends Controller {
  function indexAction() {
    $p = $this->route->params;
    if(array_key_exists('query', $p))
      echo "query\n";
    $r = Validator::valid(['email' => 'oblind@163.com', 'password' => '123'], ['email' => 'email', 'password' => 'min:6'], $err);
    $this->response->end($r ? 'ok' : $err);
  }
}

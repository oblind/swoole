<?php
namespace Tyer\Api;

use Oblind\Http\Controller;

class TestController extends Controller {
  function indexAction() {
    echo "indexAction\n";
    var_dump($this->route->params);
    $this->response->end('index action');
  }
}

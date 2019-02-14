<?php
require '../vendor/autoload.php';
require './controller/IndexController.php';
require './controller/Api/TestController.php';

use Oblind\WebSocket\Server;
use Oblind\Http\LanguagePort;
use Oblind\Language;
use Tyer\Api\TestController;

class WebSocket extends Server {

  function onWorkerStart(int $wid) {
    echo "$wid worker start\n";
  }
}

$svr = new WebSocket('127.0.0.1', 9201);
$http = new LanguagePort($svr, '127.0.0.1', 9200);
$http->router->addController(new IndexController, '/');
$http->router->addController(new TestController);

$svr->start();

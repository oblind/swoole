<?php
require '../vendor/autoload.php';
require './controller/IndexController.php';
require './controller/Api/TestController.php';

use Swoole\Http\Request;
use Swoole\Http\Response;
use Oblind\WebSocket\Server;
use Oblind\Http\Port;
use Tyer\Api\TestController;

class WebSocketServer extends Server {

  function onWorkerStart(int $wid) {
    echo "$wid worker start\n";
  }
}

$svr = new WebSocketServer('127.0.0.1', 9201);
$http = new Port($svr, '127.0.0.1', 9200);
$http->router->addController(new IndexController, '/');
$http->router->addController(new TestController);
var_dump($http->router->controllers);

$svr->start();

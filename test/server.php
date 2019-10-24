<?php
require '../vendor/autoload.php';
require './controller/IndexController.php';
require './controller/Api/TestController.php';
require './middleware/AuthMiddleware.php';

use Swoole\Server as SwooleServer;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server as SwooleWebSocket;
use Oblind\WebSocket as Server;
use Oblind\Http\LanguageHttpPort;
use Swoole\Api\TestController;
use Swoole\AuthMiddleware;
use Oblind\Application;

class WebSocket extends Server {

  function onWorkerStart(SwooleServer $svr, int $wid) {
    echo "$wid worker start\n";
  }

  function onMessage(SwooleWebSocket $svr, Frame $f) {

  }
}

class App extends Application {

  function onStart() {
    $svr = new WebSocket('0.0.0.0', 9201);
    $http = new LanguageHttpPort($svr, '0.0.0.0', 9200);
    $http->router->addController(new IndexController, '/');
    $http->router->addController(new TestController);
    $http->router->defaultRoute->insertMiddleware([new AuthMiddleware]);
    $svr->start();
  }
}

$app = new App;
$app->run();

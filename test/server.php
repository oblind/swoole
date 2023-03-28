<?php
require '../vendor/autoload.php';
require './controller/IndexController.php';
require './controller/Api/TestController.php';
require './middleware/AuthMiddleware.php';

use Swoole\WebSocket\Frame;
use Oblind\WebSocket as Server;
use Oblind\Http\LanguageHttpPort;
use Swoole\Api\TestController;
use Swoole\AuthMiddleware;
use Oblind\Application;
use Oblind\Cache\BaseCache;
use Oblind\Cache\Redis;

class WebSocket extends Server {

  function getCache(): BaseCache {
    return Redis::getCache();
  }

  function onWorkerStart(int $wid) {
    echo "$wid worker start\n";
  }

  function onMessage(Frame $f) {

  }
}

class App extends Application {

  function onStart() {
    $svr = new WebSocket('0.0.0.0', 9201);
    $http = new LanguageHttpPort($svr, '0.0.0.0', 9200);
    $http->router->addController(new IndexController, '/');
    $http->router->addController(new TestController);
    $http->router->defaultRoute->addMiddleware(new AuthMiddleware);
    $svr->start();
  }
}

$app = new App;
$app->run();

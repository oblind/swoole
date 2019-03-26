<?php
require '../vendor/autoload.php';
require './controller/IndexController.php';
require './controller/Api/TestController.php';
require './middleware/AuthMiddleware.php';

use Oblind\WebSocket as Server;
use Oblind\Http\LanguagePort;
use Swoole\Api\TestController;
use Swoole\AuthMiddleware;
use Oblind\Application;

class WebSocket extends Server {

  function onWorkerStart(int $wid) {
    parent::onWorkerStart($wid);
    echo "$wid worker start\n";
  }
}

$app = new Application;
$svr = new WebSocket('0.0.0.0', 9201);
$http = new LanguagePort($svr, '0.0.0.0', 9200);
$http->router->addController(new IndexController, '/');
$http->router->addController(new TestController);
$http->router->defaultRoute->insertMiddleware([new AuthMiddleware]);

$svr->start();

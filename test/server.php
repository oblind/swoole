<?php
require '../vendor/autoload.php';
require './Http.php';

use Swoole\Http\Request;
use Swoole\Http\Response;
use Oblind\Server\WebSocket;

class WebSocketServer extends WebSocket {

  function onWorkerStart(int $wid) {
    echo "$wid worker start\n";
  }
}

$svr = new WebSocketServer('127.0.0.1', 9201);
$http = new Http($svr, '127.0.0.1', 9200);

$svr->start();

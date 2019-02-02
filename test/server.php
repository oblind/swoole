<?php
require '../vendor/autoload.php';

use Swoole\Http\Request;
use Swoole\Http\Response;
use Oblind\Server\WebSocket;

class WebSocketServer extends WebSocket {

  function onWorkerStart(int $wid) {
    echo "$wid worker start\n";
  }
}

$svr = new WebSocketServer('127.0.0.1', 9201);

$http = $svr->addHttpServer('127.0.0.1', 9200, function(Request $request, Response $response, $svr) {
  $response->end(json_encode(['wid' => $svr->worker_id, 'svr' => $request->server], JSON_UNESCAPED_UNICODE));
});

$svr->start();

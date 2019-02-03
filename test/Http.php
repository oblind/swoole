<?php

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Server;
use Oblind\Server\HttpPort;

class Http extends HttpPort {

  function onRequest(Request $request, Response $response, Server $svr) {
    $response->end(json_encode(['wid' => $svr->worker_id, 'svr' => $request->server], JSON_UNESCAPED_UNICODE));
  }
}

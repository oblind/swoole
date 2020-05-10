<?php
namespace Oblind\Http;

use Swoole\Server\Port;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Oblind\WebSocket;

class HttpPort {
  public WebSocket $svr;
  public Port $http;
  public Router $router;

  function __construct(WebSocket $svr, string $host, int $port, int $type = SWOOLE_SOCK_TCP) {
    $this->svr = $svr;
    $this->http = $svr->addListener($host, $port, $type);
    $this->router = new Router($svr);
    $this->http->on('request', function(Request $request, Response $response) {
      $this->onRequest($request, $response);
    });
  }

  function pageNotFound(Request $request, Response $response) {
    if($request->header['x-requested-with'] ?? 0 == 'XMLHttpRequest')
      $response->end('page not found');
    else
      $response->end("<!DOCTYPE html>
<html>
<head>
  <meta name=\"viewport\" content=\"width=device-width\">
</head>
<body>
  {$request->server['request_uri']}
  <h1>page not found</h1>
</body>
</html>");
  }

  function onRequest(Request $request, Response $response) {
    if(!$this->router->dispatch($request, $response)) {
      $response->status(RES_NOT_FOUND);
      $response->header('content-type', 'text/html;charset=utf-8');
      $this->pageNotFound($request, $response);
    }
  }
}

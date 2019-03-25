<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Server;

const RES_BAD_REQUEST = 400;
const RES_NO_PERMISSION = 401;
const RES_FORBIDEN = 403;
const RES_NOT_FOUND = 404;
const RES_NOT_ALLOWED = 405;

class Port {
  /**@var \Swoole\Websocket\Server */
  public $svr;
  /**@var string */
  public $host;
  /**@var int */
  public $port;
  /**@var \Swoole\Server\Port */
  public $http;
  /**@var Router */
  public $router;

  function __construct(Server $svr, string $host = 'localhost', int $port = 0) {
    $this->svr = $svr;
    $this->host = $host;
    $this->port = $port;
    $this->http = $svr->addListener($host, $port, SWOOLE_SOCK_TCP);
    $this->router = new Router;
    $this->http->on('request', function(Request $request, Response $response) {
      $this->onRequest($request, $response, $this->svr);
    });
  }

  function pageNotFound(Request $request, Response $response) {
    $response->status(RES_NOT_FOUND);
    $response->end("<!DOCTYPE html>
<html>
<head>
  <meta name=\"viewport\" content=\"width=device-width\">
</head>
<body>
  {$request->server['request_uri']}
  <h1>404: page not found</h1>
</body>
</html>");
  }

  function onRequest(Request $request, Response $response, Server $svr) {
    if(!$this->router->dispatch($request, $response))
      $this->pageNotFound($request, $response);
  }
}

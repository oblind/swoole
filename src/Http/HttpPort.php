<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Server;

class HttpPort {
  /**@var \Swoole\Server */
  public $svr;
  /**@var string */
  public $host;
  /**@var int */
  public $port;
  /**@var \Swoole\Server\Port */
  public $http;
  /**@var Router */
  public $router;

  function __construct(Server $svr, string $host, int $port, int $type = SWOOLE_SOCK_TCP) {
    $this->svr = $svr;
    $this->host = $host;
    $this->port = $port;
    $this->http = $svr->addListener($host, $port, $type);
    $this->router = new Router;
    $this->http->on('request', function(Request $request, Response $response) {
      $this->onRequest($request, $response);
    });
  }

  function pageNotFound(Request $request, Response $response) {
    $response->status(RES_NOT_FOUND);
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
    if(!$this->router->dispatch($request, $response))
      $this->pageNotFound($request, $response);
  }
}
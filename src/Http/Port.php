<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Server;

class Port {
  /**@var \Swoole\Websocket\Server $svr */
  public $svr;
  /**@var string $host */
  public $host;
  /**@var int $port */
  public $port;
  /**@var \Swoole\Server\Port $http */
  public $http;
  /**@var Router $router */
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

  function onRequest(Request $request, Response $response, Server $svr) {
    $this->router->route($request, $response);
  }
}

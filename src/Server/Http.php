<?php
namespace Oblind\Server;

use Swoole\WebSocket\Server;
use Swoole\Server\Port;

class HttpPort {
  /**@var \Swoole\Websocket\Server $svr */
  public $svr;
  /**@var string $host */
  public $host;
  /**@var int $port */
  public $port;
  /**@var \Swoole\Server\Port $http */
  public $http;

  function __construct(Server $svr, string $host = 'localhost', int $port = 0) {
    $this->svr = $svr;
    $this->host = $host;
    $this->port = $port;
    $this->http = $svr->addListener($host, $port, SWOOLE_SOCK_TCP);
    $this->http->on('request', function() {
      $this->onRequest();
    });
  }

  function onRequest() {

  }
}

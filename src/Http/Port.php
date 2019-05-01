<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Server;

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
    $this->http->set([
      'open_http_protocol' => true,
      'package_max_length' => 0x18000000,  //1.5M
    ]);
    var_dump($this->http->setting);
    $this->router = new Router;
    $this->http->on('request', function(Request $request, Response $response) {
      $this->onRequest($request, $response);
    });

    /*$this->http->on('receive', function(Server $svr, int $fd, int $rid, string $data) {
      echo "onReceive, length: ", strlen($data), "\n";
    });*/

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
    if(($m = $request->server['request_method']) == 'POST')
      var_dump($request);
    elseif($m == 'PUT') {
      var_dump($request);
      echo 'raw: ', strlen($request->rawContent()), "\n";
    }
    if(!$this->router->dispatch($request, $response))
      $this->pageNotFound($request, $response);
  }
}

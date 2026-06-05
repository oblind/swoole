<?php

namespace Oblind;

class SocketPort {
  public WebSocket $svr;
  public string $host;
  public int $port;
  public \Swoole\Server\Port $socket;

  function __construct(WebSocket $svr, string $host, int $port, $type = SWOOLE_SOCK_TCP) {
    $this->svr = $svr;
    $this->host = $host;
    $this->port = $port;
    $socket = $svr->addListener($host, $port, $type);
    $socket->set(['open_http_protocol' => false]);

    $socket->on('connect', function(WebSocket $svr, int $fd, int $reactorId) {
      $this->onConnect($svr, $fd, $reactorId);
    });

    $socket->on('receive', function(WebSocket $svr, int $fd, int $reactorId, string $data) {
      try {
        $this->onReceive($svr, $fd, $reactorId, $data);
      } catch(\Throwable $e) {
        $svr->show("EXCEPTION\n" . $e);
      }
    });

    $socket->on('close', function(WebSocket $svr, int $fd) {
      $this->onClose($svr, $fd);
    });

    $this->socket = $socket;
  }

  function server(): WebSocket {
    return $this->svr;
  }

  function onConnect(WebSocket $svr, int $fd, int $reactorId) {
  }

  function onReceive(WebSocket $svr, int $fd, int $reactorId, string $data) {
  }

  function onClose(WebSocket $svr, int $fd) {
  }

  function send(int $fd, $data) {
    if(is_array($data) || is_object($data))
      $this->svr->send($fd, json_encode($data, JSON_UNESCAPED_UNICODE) . "\n");
    else
      $this->svr->send($fd, $data);
  }

  function publish(string $dest, int $id, string $cmd, $data, array $params = null) {
    $this->svr->publish($dest, $id, $cmd, $data, $params);
  }
}

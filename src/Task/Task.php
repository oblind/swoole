<?php
namespace Oblind\Task;
use Oblind\WebSocket;

abstract class Task {
  public WebSocket $svr;

  function __construct(Websocket $webSocket) {
    $this->svr = $webSocket;
  }
  abstract function match(int $time): bool;
  abstract function execute();
}
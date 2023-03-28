<?php
namespace Oblind\Task;
use Oblind\WebSocket;

abstract class Task {
  public WebSocket $svr;
  public bool $busy = false;

  function __construct(Websocket $webSocket) {
    $this->svr = $webSocket;
  }

  function beforeExecute() {
    $this->busy = true;
  }

  function success() {
    $this->busy = false;
  }

  function fail() {
    $this->busy = false;
  }

  abstract function match(int $time): bool;
  abstract function execute();
}

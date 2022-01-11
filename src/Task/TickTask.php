<?php
namespace Oblind\Task;
use Oblind\WebSocket;
use Oblind\Task\Task;

abstract class TickTask extends Task {
  public int $tick;
  protected int $time;

  function __construct(WebSocket $webSocket, int $seconds) {
    assert($seconds > 0);
    parent::__construct($webSocket);
    $this->tick = $seconds;
    $this->time = time() + $seconds;
  }

  function match(int $time): bool {
    if($time >= $this->time) {
      $this->time += $this->tick;
      return true;
    }
    return false;
  }
}

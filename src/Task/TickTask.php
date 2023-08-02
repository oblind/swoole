<?php
namespace Oblind\Task;
use Oblind\WebSocket;
use Oblind\Task\Task;

abstract class TickTask extends Task {
  public int $tick;
  protected int $nextTime;

  function __construct(WebSocket $webSocket, int $seconds, string $nextTime = null) {
    assert($seconds > 0);
    parent::__construct($webSocket);
    $this->tick = $seconds;
    $this->nextTime = ($nextTime ? strtotime($nextTime) : time()) + rand(10, 20);
  }

  function match(int $time): bool {
    if($time >= $this->nextTime) {
      $this->nextTime += $this->tick;
      return true;
    }
    return false;
  }

  function nextTime(string $nextTime) {
    $this->nextTime = strtotime($nextTime);
  }
}

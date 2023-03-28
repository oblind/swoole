<?php
namespace Oblind\Task;
use Oblind\WebSocket;
use Oblind\Task\Task;

abstract class DailyTask extends Task {
  public string $at;
  protected int $time;

  function __construct(WebSocket $webSocket, string $at = null) {
    parent::__construct($webSocket);
    if($at) {
      $this->at = $at;
      $this->update(time());
    }
  }

  function success() {
    parent::success();
    $this->update(time() + 1);
  }

  function match(int $time): bool {
    return $time >= $this->time;
  }

  protected function update($time) {
    $this->time = strtotime(date('Y-m-d') . ' ' . $this->at);
    if($this->time < $time)
      $this->time += 86400;
    if($this->svr->master_pid)
      $this->svr->show('next time: ' . date('Y-m-d H:i:s', $this->time));
  }
}

<?php
namespace Oblind;

use Swoole\Server as SwooleServer;
use Swoole\Server\Task;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\Websocket\Server as WebSocketServer;

class WebSocket extends WebSocketServer {
  /**@var \Language */
  public $lang;
  /**@var string 日志路径 */
  public $logFile = 'log.txt';

  function __construct(string $host, int $port = 0, int $mode = SWOOLE_PROCESS, int $sock_type = SWOOLE_SOCK_TCP) {
    parent::__construct($host, $port, $mode, $sock_type);

    $this->on('managerStart', function(SwooleServer $svr) {
      $this->onManagerStart();
    });

    $this->on('shutdown', function(SwooleServer $svr) {
      $this->onShutdown();
    });

    $this->on('workerStart', function(SwooleServer $svr, int $wid) {
      if($this->taskworker)
        $this->onTaskWorkerStart($wid);
      else
        $this->onWorkerStart($wid);
    });

    $this->on('workerStop', function(SwooleServer $svr, int $wid) {
      if($this->taskworker)
        $this->onTastWorkerStop($wid);
      else
        $this->onWorkerStop($wid);
    });

    $this->on('pipeMessage', function(SwooleServer $svr, $src_wid, $d) {
      $this->onPipeMessage($src_wid, $d);
    });

    /*$this->on('task', function(SwooleServer $svr, $tid, $wid, $data) {
      $this->onTask($tid, $wid, $data);
    });*/
    $this->on('task', function(SwooleServer $svr, Task $task) {
      $this->onTask($task->id, $task->worker_id, $task->data);
    });

    $this->on('finish', function(SwooleServer $svr, int $tid, string $data) {
      $this->onFinish($tid, $data);
    });

    $this->on('open', function(SwooleServer $svr, $req) {
      $this->onOpen($req);
    });

    $this->on('close', function(SwooleServer $svr, int $fd, int $rid) {
      $this->onClose($fd, $rid);
    });

    $this->on('message', function(SwooleServer $svr, Frame $f) {
      $this->onMessage($f);
    });
  }

  function onManagerStart() {
  }

  function onShutdown() {
  }

  function onWorkerStart(int $wid) {
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
      throw new \Exception($errstr, $errno);
    });
    \Swoole\Runtime::enableCoroutine();
  }

  function onWorkerStop(int $wid) {
  }

  function onTaskWorkerStart($tid) {
    cli_set_process_title("brk_task$tid");
    $this->logs = [];
    $this->tick(2000, function() {
      if($this->logs)
        $this->writeLogs();
    });
  }

  function onTastWorkerStop(int $tid) {
    $this->writeLogs();
  }

  function onTask($tid, $wid, $data) {
    $data = json_decode($data);
    switch($data->cmd) {
    case 'log':
      $this->logs[] = date('y-m-d H:i:s') . "|$data->log\n";
      break;
    case 'writeLogs':
      $this->writeLogs();
    }
  }

  function onFinish(int $tid, string $data) {
  }

  function onPipeMessage($src_wid, $d) {
    if($d = json_decode($d))
      $this->onPublish($d);
  }

  function onOpen(Request $req) {
  }

  function onClose(int $fd, int $rid) {
  }

  function onMessage(Frame $f) {
  }

  function push($fd, $data, $opcode = 1, $finish = true) {
    return ($info = $this->getClientInfo($fd)) && ($info['websocket_status'] ?? 0) ? parent::push($fd, $data, $opcode, $finish) : false;
  }

  static function toObj(&$a) {
    $a = (object)$a;
    foreach($a as $k => $v)
      if(is_array($v))
        $a->$k = static::toObj($v);
    return $a;
  }

  function publish($d) {
    if(is_array($d))
      $d = static::toObj($d);
    $m = json_encode($d);
    for($i = 0, $c = swoole_cpu_num(); $i < $c; $i++)
      if($i != $this->worker_id)
        $this->sendMessage($m, $i);
    $this->onPublish($d);
  }

  function onPublish($d) {
  }

  function trans(string $k): string {
    return $this->lang[$k];
  }

  function log($l) {
    $this->task(json_encode(['cmd' => 'log', 'log' => $l]));
  }

  function writeLogs() {
    if($this->taskworker) {
      //清除文件缓存, 否则filesize 返回值不变
      clearstatcache();
      //超过256K压缩存档
      if(file_exists($this->logFile) && filesize($this->logFile) >= 0x40000) {
        $i = 0;
        $f0 = '../log/brake.log';
        while(file_exists($f = "../log/brake$i.log.bz2"))
          $i++;
        if($bz = bzopen($f, 'w')) {
          bzwrite($bz, file_get_contents($f0));
          bzclose($bz);
          unlink($f0);
        }
      }
      foreach($this->logs as $l)
        error_log($l, 3, $this->logFile);
      $this->logs = [];
    } else
      $this->task(json_encode(['cmd' => 'writeLogs']));
  }

  function restart() {
    $this->log('RESTARTING...');
    $this->publish(['cmd' => 'restart']);
  }
}

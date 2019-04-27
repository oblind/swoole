<?php
namespace Oblind;

use Swoole\Server as SwooleServer;
use Swoole\Server\Task;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\Websocket\Server as WebSocketServer;

class WebSocket extends WebSocketServer {
  /**@var string 日志路径 */
  public $logFile = 'log/log.txt';
  /**@var int 日志文件大小, 超出后会被压缩存档 */
  public $logFileSize = 0x40000;

  function __construct(string $host, int $port = 0, int $mode = SWOOLE_PROCESS, int $sock_type = SWOOLE_SOCK_TCP) {
    parent::__construct($host, $port, $mode, $sock_type);
    $p = dirname($this->logFile);
    if(!is_dir($p)) //建立日志目录
      mkdir($p);
    $this->set(['task_enable_coroutine' => true]);
    foreach(['Start', 'Shutdown', 'ManagerStart', 'Finish',
      'Open', 'Close', 'Message'] as $e)
      $this->on($e, [$this, "on$e"]);

    $this->on('workerStart', function(SwooleServer $svr, int $wid) {
      set_error_handler(function($errno, $errstr, $errfile, $errline) {
        throw new \Exception($errstr, $errno);
      });
      \Swoole\Runtime::enableCoroutine();
      if($this->taskworker) {
        $this->logs = [];
        $this->tick(2000, function() {
          if($this->logs)
            $this->writeLogs();
        });
        $this->onTaskWorkerStart($svr, $wid);
      } else
        $this->onWorkerStart($svr, $wid);
    });

    $this->on('workerStop', function(SwooleServer $svr, int $wid) {
      if($this->taskworker) {
        $this->writeLogs();
        $this->onTastWorkerStop($svr, $wid);
      } else
        $this->onWorkerStop($svr, $wid);
    });

    $this->on('task', function(SwooleServer $svr, Task $task) {
      if($data = json_decode($task->data)) {
        switch($data->cmd ?? null) {
          case 'log':
            $this->logs[] = date('y-m-d H:i:s') . "|$data->log\n";
            break;
          case 'writeLogs':
            $this->writeLogs();
        }
      }
      $this->onTask($svr, $task);
    });

    $this->on('pipeMessage', function(SwooleServer $svr, int $src_wid, $d) {
      if($d = json_decode($d))
        $this->onPublish($d);
      $this->onPipeMessage($svr, $src_wid, $d);
    });
  }

  function onStart(SwooleServer $svr) {
  }

  function onShutdown(SwooleServer $svr) {
  }

  function onManagerStart(SwooleServer $svr) {
  }

  function onWorkerStart(SwooleServer $svr, int $wid) {
  }

  function onWorkerStop(SwooleServer $svr, int $wid) {
  }

  function onTaskWorkerStart(SwooleServer $svr, int $tid) {
  }

  function onTastWorkerStop(SwooleServer $svr, int $tid) {
  }

  function onTask(SwooleServer $svr, Task $task) {
  }

  function onFinish(SwooleServer $svr, int $tid, string $data) {
  }

  function onPipeMessage(SwooleServer $svr, int $src_wid, $d) {
  }

  function onOpen(SwooleServer $svr, Request $req) {
  }

  function onClose(SwooleServer $svr, int $fd, int $rid) {
  }

  function onMessage(SwooleServer $svr, Frame $f) {
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
    if($this->taskworker)
      $this->logs[] = date('y-m-d H:i:s') . "|$l\n";
    else
      $this->task(json_encode(['cmd' => 'log', 'log' => $l]));
  }

  function writeLogs() {
    if($this->taskworker) {
      //清除文件缓存, 否则filesize 返回值不变
      clearstatcache();
      //日志文件超过限制后压缩存档
      if(file_exists($this->logFile) && filesize($this->logFile) >= $this->logFileSize) {
        $i = 0;
        $p = dirname(realpath($this->logFile)) . '/' . Application::app()::$prefix;
        while(file_exists($f =  "$p$i.log.bz2"))
          $i++;
        if($bz = bzopen($f, 'w')) {
          bzwrite($bz, file_get_contents($this->logFile));
          bzclose($bz);
          unlink($this->logFile);
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

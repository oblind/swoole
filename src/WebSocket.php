<?php
namespace Oblind;

use Swoole\Server as SwooleServer;
use Swoole\Server\Task;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\Websocket\Server as SwooleWebSocket;
use Swoole\Table;
use Oblind\Cache\BaseCache;
use Oblind\Model\BaseModel;

const ERROR_STRING = [
  E_ERROR => 'E_ERROR',
  E_WARNING => 'E_WARNING',
  E_PARSE => 'E_PARSE',
  E_NOTICE => 'E_NOTICE',
  E_CORE_ERROR => 'E_CORE_ERROR',
  E_CORE_WARNING => 'E_CORE_WARNING',
  E_COMPILE_ERROR => 'E_COMPILE_ERROR',
  E_COMPILE_WARNING => 'E_COMPILE_WARNING',
  E_USER_ERROR => 'E_USER_ERROR',
  E_USER_WARNING => 'E_USER_WARNING',
  E_USER_NOTICE => 'E_USER_NOTICE',
  E_STRICT => 'E_STRICT',
  E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
  E_DEPRECATED => 'E_DEPRECATED',
  E_USER_DEPRECATED => 'E_USER_DEPRECATED',
];
const E_FATAL = E_ERROR | E_USER_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_RECOVERABLE_ERROR | E_PARSE;

abstract class WebSocket extends SwooleWebSocket {
  const MAX_TABLE_SIZE = 1024;

  /**@var string 日志路径 */
  public string $logFile = 'log/log.txt';
  /**@var int 日志文件大小, 超出后会被压缩存档 */
  public int $logFileSize = 0x20000;  //128k

  /**@var \Swoole\Table */
  public Table $tblProds;
  /**@var \Swoole\Table */
  public Table $tblUsers;
  /**@var array */
  public array $prods = [];
  /**@var array */
  public array $users = [];
  /**@var array */
  public array $guests = [];
  /**@var bool */
  public bool $init = false;

  function __construct(string $host, int $port = 0, int $mode = SWOOLE_PROCESS, int $sock_type = SWOOLE_SOCK_TCP) {
    $app = Application::app();
    $config = $app::config();
    $setting = [
      'task_enable_coroutine' => true,
      'task_worker_num' => 1,
      'package_max_length' => 0x400000,  //4M
      'heartbeat_idle_time' => 600,
      'heartbeat_check_interval' => 60,
      'http_gzip_level' => 6,
      'websocket_compression' => true, //开启压缩
      'pid_file' => $app::$pidFile,
      'log_file' => $this->logFile,
    ];
    if($config['ssl']['enabled'] ?? 0) {
      $sock_type |= SWOOLE_SSL;
      $setting['ssl_cert_file'] = $config['ssl']['certFile'] ?? '/etc/ssl/certs/ssl-cert-snakeoil.pem';
      $setting['ssl_key_file'] = $config['ssl']['keyFile'] ?? '/etc/ssl/private/ssl-cert-snakeoil.key';
    }
    $setting['open_http2_protocol'] = $config['http2'] ?? false;
    $setting['daemonize'] = $config['daemonize'] ?? false;
    parent::__construct($host, $port, $mode, $sock_type);
    $this->set($setting);

    $p = dirname($this->logFile);
    if(!is_dir($p)) //建立日志目录
      mkdir($p);
    foreach(['Shutdown', 'Finish', 'Open', 'Close', 'Message'] as $e)
      $this->on($e, function(SwooleWebSocket $svr, ...$args) use($e) {
        $e = "on$e";
        $this->$e(...$args);
      });

    $this->on('start', function(SwooleServer $svr) {
      cli_set_process_title(Application::app()::$prefix . '_master');
      $this->onStart();
    });

    $this->on('managerStart', function(SwooleServer $svr) {
      cli_set_process_title(Application::app()::$prefix . '_manager');
      $this->onManagerStart();
    });

    $this->on('workerStart', function(SwooleServer $svr, int $wid) {
      //将普通错误转为异常
      set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline) {
        $e = new \Exception("$errstr in $errfile($errline)", $errno);
        //不保留参数
        $e->backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS);
        throw $e;
      });
      //记录致命错误
      register_shutdown_function(function() {
        $e = error_get_last();
        //if($e && ($e['type'] & E_FATAL)) {
        if($e) {
          $this->onCrash();
          $msg = ERROR_STRING[$e['type']] . ": {$e['message']} in {$e['file']}({$e['line']})";
          $this->log($msg, true);
          echo "$msg\n";
        }
      });
      \Swoole\Runtime::enableCoroutine();
      //初始化缓存池
      BaseCache::initCachePool();
      BaseModel::initDatabasePool();
      //预置连接
      BaseCache::putCache($this->getCache());
      BaseModel::putDatabase(BaseModel::getDatabase());
      if($this->taskworker) {
        cli_set_process_title(Application::app()::$prefix . "_task$wid");
        $this->logs = [];
        $this->tick(2000, function() {
          if($this->logs)
            $this->writeLogs();
        });
        $this->onTaskWorkerStart($wid);
      } else {
        cli_set_process_title(Application::app()::$prefix . "_worker$wid");
        $this->onWorkerStart($wid);
      }
    });

    $this->on('workerStop', function(SwooleServer $svr, int $wid) {
      if($this->taskworker) {
        $this->writeLogs();
        $this->onTastWorkerStop($wid);
      } else
        $this->onWorkerStop($wid);
    });

    $this->on('task', function(SwooleServer $svr, Task $task) {
      if($d = json_decode($task->data)) {
        switch($d->cmd ?? null) {
          case 'log':
            $this->addLog($d->log);
            break;
          case 'writeLogs':
            $this->writeLogs();
        }
      }
      $this->onTask($d, $task);
    });

    $this->on('pipeMessage', function(SwooleServer $svr, int $src_wid, $s) {
      if($d = json_decode($s))
        $this->onPublish($d, $s);
      $this->onPipeMessage($src_wid, $s);
    });
  }

  function onStart() {
  }

  function onShutdown() {
  }

  function onCrash() {
  }

  function onManagerStart() {
  }

  function onWorkerStart(int $wid) {
  }

  function onWorkerStop(int $wid) {
  }

  function onTaskWorkerStart(int $tid) {
  }

  function onTastWorkerStop(int $tid) {
  }

  function onTask(\stdClass $cmd, Task $task) {
  }

  function onFinish(int $tid, string $data) {
  }

  function onPipeMessage(int $src_wid, $d) {
  }

  function onOpen(Request $req) {
  }

  function onClose(int $fd, int $rid) {
  }

  abstract function onMessage(Frame $f);

  abstract function getCache(): BaseCache;

  function push($fd, $data, $opcode = 1, $finish = 1) {
    if($this->isEstablished($fd)) {
      if(is_array($data) || is_object($data))
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
      if(is_string($data) && strlen($data) > 31)
        $finish |= SWOOLE_WEBSOCKET_FLAG_COMPRESS;
      return parent::push($fd, $data, $opcode, $finish);
    }
  }

  static function toObj(&$a) {
    $a = (object)$a;
    foreach($a as $k => $v)
      if(is_array($v) && $v && !array_key_exists(0, $v))
        $a->$k = static::toObj($v);
    return $a;
  }

  function publish(?string $dest, int $id, string $cmd, $data = null, array $params = null) {
    if(is_array($data))
      $data = static::toObj($data);
    $d = new \stdClass;
    $d->dest = $dest;
    $d->id = $id;
    $d->cmd = $cmd;
    if($data)
      $d->data = $data;
    if($params)
      $d->params = $params;
    $m = json_encode($d);
    for($i = 0, $c = $this->setting['worker_num']; $i < $c; $i++)
      if($i != $this->worker_id)
        $this->sendMessage($m, $i);
    $this->onPublish($d, $m);
  }

  function onPublish(\stdClass $d, string $raw) {
  }

  protected function addLog($l) {
    $this->logs[] = '[' . date(DATE_ATOM) . "] $l\n";
  }

  function log(string $l, bool $force = false) {
    if($this->taskworker && $this->worker_id == $this->setting['worker_num'] || $force)
      $this->addLog($l);
    else
      $this->task(json_encode(['cmd' => 'log', 'log' => $l]), 0);
  }

  function writeLogs(bool $force = false) {
    if($this->taskworker || $force) {
      //清除文件缓存, 否则filesize 返回值不变
      clearstatcache();
      //日志文件超过限制后压缩存档
      if(file_exists($this->logFile) && filesize($this->logFile) >= $this->logFileSize) {
        $i = 0;
        $p = dirname(realpath($this->logFile)) . '/' . Application::app()::$prefix;
        while(file_exists($f =  "$p$i.log.bz2"))
          $i++;
        if(copy($this->logFile, "compress.bzip2://$f"))
          unlink($this->logFile);
      }
      foreach($this->logs as $l)
        error_log($l, 3, $this->logFile);
      $this->logs = [];
    } else
      $this->task(json_encode(['cmd' => 'writeLogs']), 0);
  }

  function restart() {
    $this->log('RESTARTING...');
    $this->publish(null, 0, 'restart', null);
  }
}

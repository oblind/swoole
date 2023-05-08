<?php
namespace Oblind;

use Swoole\Server as SwooleServer;
use Swoole\Server\Task;
use Swoole\WebSocket\Frame;
use Swoole\Websocket\Server as SwooleWebSocket;
use Swoole\Timer;
use Swoole\Table;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Oblind\Cache\BaseCache;
use Oblind\Model\BaseModel;
use Oblind\Http\Router;

class PublishMessage {
  public string $dest;
  public int $id;
  public string $cmd;
  public $data;
  public ?array $params;

  function __construct(string $dest, int $id, string $cmd, $data = null, array $params = null) {
    $this->dest = $dest;
    $this->id = $id;
    $this->cmd = $cmd;
    if($data !== null)
      $this->data = $data;
    if($params !== null)
      $this->params = $params;
  }
};

abstract class WebSocket extends SwooleWebSocket {
  //路由
  public Router $router;
  protected ?array $header = null;
  //日志路径
  public string $logFile = './log/log.txt';
  //日志文件大小, 超出后会被压缩存档
  public int $logFileSize = 0x20000;  //128k
  protected bool $savingLog = false;
  //跨进程设备列表, id => prod
  public Table $tblProds;
  //跨进程用户列表, id => user
  public Table $tblUsers;
  //product fd => id
  public Table $tblProdIds;
  //user fd => id
  public Table $tblUserIds;
  //worker本地设备列表
  public array $prods = [];
  //worker本地用户列表
  public array $users = [];
  //日志
  public Logger $logger;

  function __construct(string $host, int $port = 0, int $mode = \SWOOLE_PROCESS, int $sock_type = \SWOOLE_SOCK_TCP) {
    $app = Application::app();
    $config = $app::config();
    $setting = [
      'task_enable_coroutine' => true,
      'task_worker_num' => 1,
      'package_max_length' => 0x400000,  //4M
      'heartbeat_idle_time' => 600,
      'heartbeat_check_interval' => 60,
      'http_compression_level' => 6,
      'compression_min_length' => 128,
      'websocket_compression' => true, //开启压缩
      'pid_file' => $app::$pidFile,
      'log_file' => $this->logFile,
    ];
    if($workerNum = $config['server']['workerNum'] ?? 0) {
      $setting['worker_num'] = $workerNum;
      echo "worker_num: $workerNum\n";
    }
    if($config['ssl']['enabled'] ?? 0) {
      $sock_type |= \SWOOLE_SSL;
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
    $this->logger = new Logger($this->logFile, $this->logFileSize);

    $this->router = new Router($this);
    $this->header = $config['server']['header'] ?? null;

    foreach(['Shutdown', 'Finish', 'Close', 'Disconnect', 'Message'] as $e)
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
        throw new \Exception("$errstr in $errfile($errline)", $errno);
      });
      //记录致命错误
      register_shutdown_function(function() {
        $e = error_get_last();
        //if($e && ($e['type'] & E_FATAL)) {
        if($e) {
          $this->onCrash();
          $msg = 'CRITICAL ERROR! ' . ERROR_STRING[$e['type']] . ": {$e['message']} in {$e['file']}({$e['line']})";
          $this->show($msg, true);
        }
      });
      //初始化独立缓存池
      BaseCache::initCachePool();
      BaseModel::initDatabasePool();
      //预置连接
      BaseCache::putCache($this->getCache());
      BaseModel::putDatabase(BaseModel::getDatabase());
      if($this->taskworker) {
        cli_set_process_title(Application::app()::$prefix . "_task$wid");
        Timer::tick(2000, function() {
          $this->logger->writeLogs();
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

    $this->on('open', function(SwooleWebSocket $svr, \Swoole\Http\Request $request) {
      $this->onOpen($request);
    });

    $this->on('request', function(\Swoole\Http\Request $request, \Swoole\Http\Response $response) {
      if($this->header)
        foreach($this->header as $k => $v)
          $response->header($k, $v);
      $this->onRequest($request, $response);
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

    $this->on('pipeMessage', function(SwooleServer $svr, int $src_wid, $msg) {
      if(($d = json_decode($msg)) && isset($d->dest))
        $this->onPublish($d->dest, $d->id, $d->cmd, $d->data ?? null, $d->params ?? null);
      $this->onPipeMessage($src_wid, $msg);
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

  function onOpen(Request $request) {
  }

  function onClose(int $fd, int $rid) {
  }

  function onDisconnect($fd) {
  }

  function pageNotFound(Request $request, Response $response) {
    $e = _('page not found');
    if(($request->header['x-requested-with'] ?? null) == 'XMLHttpRequest')
      $response->end($e);
    else
      $response->end("<!DOCTYPE html>
<html>
<head>
  <meta name=\"viewport\" content=\"width=device-width\">
</head>
<body style=\"text-align: center\">
<img width=\"200\" src=\"data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZlcnNpb249IjEuMSIgdmlld0JveD0iMCAwIDQ4IDQ4Ij4KPGRlZnM+Cgk8bGluZWFyR3JhZGllbnQgaWQ9ImxnIiB4MT0iMCIgeTE9IjEwMCUiIHgyPSIwIiB5Mj0iMCI+CgkJPHN0b3Agb2Zmc2V0PSIwIiBzdHlsZT0ic3RvcC1jb2xvcjojQkJERUZCIi8+CgkJPHN0b3Agb2Zmc2V0PSIxMDAlIiBzdHlsZT0ic3RvcC1jb2xvcjojNWFmIi8+Cgk8L2xpbmVhckdyYWRpZW50Pgo8L2RlZnM+CjxnIGZpbGw9IiM2MTYxNjEiPgoJPHJlY3QgeD0iMzQuNiIgeT0iMjguMSIgdHJhbnNmb3JtPSJtYXRyaXgoLjcwNyAtLjcwNyAuNzA3IC43MDcgLTE1LjE1NCAzNi41ODYpIiB3aWR0aD0iNCIgaGVpZ2h0PSIxNyIvPgoJPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMTYiLz4KPC9nPgo8cmVjdCB4PSIzNi4yIiB5PSIzMi4xIiB0cmFuc2Zvcm09Im1hdHJpeCguNzA3IC0uNzA3IC43MDcgLjcwNyAtMTUuODM5IDM4LjIzOSkiIGZpbGw9IiMzNzQ3NEYiIHdpZHRoPSI0IiBoZWlnaHQ9IjEyLjMiLz4KPGNpcmNsZSBmaWxsPSJ1cmwoI2xnKSIgY3g9IjIwIiBjeT0iMjAiIHI9IjEzIi8+CjxwYXRoIGZpbGw9IiNCQkRFRkIiIGQ9Ik0yNi45LDE0LjJjLTEuNy0yLTQuMi0zLjItNi45LTMuMnMtNS4yLDEuMi02LjksMy4yYy0wLjQsMC40LTAuMywxLjEsMC4xLDEuNGMwLjQsMC40LDEuMSwwLjMsMS40LTAuMSBDMTYsMTMuOSwxNy45LDEzLDIwLDEzczQsMC45LDUuNCwyLjVjMC4yLDAuMiwwLjUsMC40LDAuOCwwLjRjMC4yLDAsMC41LTAuMSwwLjYtMC4yQzI3LjIsMTUuMywyNy4yLDE0LjYsMjYuOSwxNC4yeiIvPgo8L3N2Zz4K\"><br>
{$request->server['request_uri']}
<h2>$e</h2>
</body>
</html>");
  }

  function onRequest(Request $request, Response $response) {
    try {
      if(!$this->router->dispatch($request, $response)) {
        $response->status(\Oblind\Http\RES_NOT_FOUND);
        $response->header('content-type', 'text/html;charset=utf-8');
        $this->pageNotFound($request, $response);
      }
    } catch(\Throwable $e) {
      $this->show("EXCEPTION\n" . $e);
    }
  }

  abstract function onMessage(Frame $f);

  abstract function getCache(): BaseCache;

  function push(int $fd, $data, int $opcode = SWOOLE_WEBSOCKET_OPCODE_TEXT, int $flags = SWOOLE_WEBSOCKET_FLAG_FIN): bool {
    if($this->isEstablished($fd)) {
      if(is_array($data) || is_object($data))
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
      if(is_string($data) && strlen($data) > 31)
        $flags |= \SWOOLE_WEBSOCKET_FLAG_COMPRESS;
      return parent::push($fd, $data, $opcode, $flags);
    }
    return true;
  }

  static function toObj(&$a) {
    $a = (object)$a;
    foreach($a as $k => $v)
      if(is_array($v) && $v && !array_key_exists(0, $v))
        $a->$k = static::toObj($v);
    return $a;
  }

  function publish(string $dest, int $id, string $cmd, $data = null, array $params = null) {
    //if(is_array($data))
    //  $data = static::toObj($data);
    $d = new PublishMessage($dest, $id, $cmd, $data, $params);
    $m = json_encode($d, JSON_UNESCAPED_UNICODE);
    for($i = 0, $c = $this->setting['worker_num']; $i < $c; $i++)
      if($i != $this->worker_id)
        $this->sendMessage($m, $i);
    $this->onPublish($dest, $id, $cmd, $data, $params);
  }

  function onPublish(string $dest, int $id, string $cmd, $data = null, $params = null) {
  }

  protected function addLog($l) {
    $this->logger->addLog($l);
  }

  function log(string $l, bool $force = false) {
    if($this->taskworker && $this->worker_id == $this->setting['worker_num'] || $force) {
      $this->addLog($l);
      if($force)
        $this->writeLogs(true);
    } else
      $this->task(json_encode(['cmd' => 'log', 'log' => $l]), 0);
  }

  function show(string $l, bool $force = false) {
    echo date('y-m-d H:i:s') . "| $l\n";
    $this->log($l, $force);
  }

  function writeLogs(bool $force = false) {
    if($this->taskworker || $force) {
      $this->logger->writeLogs();
    } else
      $this->task(json_encode(['cmd' => 'writeLogs']), 0);
  }

  function restart() {
    $this->log('RESTARTING...');
    $this->publish('system', 0, 'restart', null);
  }
}

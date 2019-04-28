<?php
namespace Oblind;

use Swoole\Process;

class Application {
  const START = 0;
  const RESTART = 1;
  const STOP = 2;
  /**@var Application */
  protected static $app;
  /**@var string */
  public static $configFile = 'config.json';
  /**@var string */
  public static $pidFile = 'server.pid';
  /**@var \stdClass */
  protected static $config;
  /**@var string */
  public static $prefix = 'app';

  static function app(): Application {
    return static::$app;
  }

  static function config(): \stdClass {
    return static::$config;
  }

  function onStart(){
  }

  function run() {
    $pid = file_exists(static::$pidFile) ? intval(file_get_contents(static::$pidFile)) : 0;
    //检测pid是否运行中
    if($pid && !Process::kill($pid, 0))
      $pid = 0;
    if($_SERVER['argc'] > 1) {
      $s = strtolower($_SERVER['argv'][1]);
      $cmd = $s == 'restart' ? static::RESTART : ($s == 'stop' ? static::STOP : static::START);
    } else
      $cmd = static::START;
    if($cmd == static::RESTART || $cmd == static::STOP) { //重启/关闭
      if($pid) {
        echo "stopping, pid: $pid\n";
        Process::kill($pid);
        if(static::$prefix) {
          exec('ps aux|grep ' . static::$prefix . '_', $r);
          if(($c = count($r) - 2) > 0) {
            $s = [];
            $myid = getmypid();
            for($i = 0; $i < $c; $i++)
              if(preg_match('/.+?(\d+)/', $r[$i], $a) && $a[1] != $myid)
                $s[] = "sudo kill -9 {$a[1]}";
            if($s)
              exec(implode(' && ', $s));
            if(file_exists(static::$pidFile))
              unlink(static::$pidFile);
          }
        }
      }
    } elseif($pid)
      exit;
    if($cmd == static::START || $cmd == static::RESTART) {
      if($pid)
        sleep(1);
      if(file_exists(static::$configFile)) {
        static::$config = json_decode(file_get_contents(static::$configFile));
        static::$app = $this;
      } else
        throw new \Exception('config file ' . static::$configFile . " not found\n");
      $this->onStart();
    }
  }
}

function app() {
  return Application::app();
}

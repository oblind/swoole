<?php
namespace Oblind;

use Swoole\Process;

class Application {
  const START = 0;
  const RESTART = 1;
  const STOP = 2;
  /**@var Application */
  protected static self $app;
  /**@var string */
  public static string $configFile = 'config.json';
  /**@var string */
  public static string $pidFile = 'server.pid';
  /**@var array */
  protected static array $config = [];
  /**@var string */
  public static string $prefix = 'app';
  /**@var string */
  public static string $daemonizeFlag = '-d';

  static function app(): Application {
    return static::$app;
  }

  static function loadConfig(?string $configFile = null) {
    if(!$configFile)
      $configFile = static::$configFile;
    if(file_exists($configFile)) {
      static::$config = json_decode(file_get_contents($configFile), true);
      $default = [
        'db' => [
          'type' => 'mysql',
          'host' => '127.0.0.1',
          'port' => 3306,
          'timeout' => 7200,
        ],
        'cache' => 'redis',
        'redis' => [
          'host' => '127.0.0.1',
          'index' => 1
        ],
        'ssl' => [
          'enabled' => true,
          'certFile' => '/etc/ssl/certs/ssl-cert-snakeoil.pem',
          'keyFile' => '/etc/ssl/private/ssl-cert-snakeoil.key'
        ],
        'http2' => true
      ];
      foreach($default as $k => $v)
        if(is_array($v))
          static::$config[$k] = array_merge($v, static::$config[$k] ?? []);
        elseif(!isset(static::$config[$k]))
          static::$config[$k] = $v;
    } else
      throw new \Exception('config file ' . $configFile . " not found\n");
  }

  static function config(): array {
    return static::$config;
  }

  function log($log) {
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
        sleep(1);
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
      static::loadConfig();
      static::$app = $this;
      static::$config['daemonize'] = in_array(static::$daemonizeFlag, $_SERVER['argv']);
      echo 'daemonize: ', static::$config['daemonize'] ? 'yes' : 'no', "\n";
      $this->onStart();
    }
  }
}

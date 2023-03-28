<?php
namespace Oblind;

class Logger {
  //日志路径
  public string $logFile;
  //日志文件大小, 超出后会被压缩存档
  public int $logFileSize;
  //压缩后文件前缀
  public string $prefix;
  protected bool $savingLog = false;
  protected array $logs = [];

  function __construct(string $logFile = './log/log.txt', int $logFileSize = 0x20000, string $prefix = null) { //128k
    $this->logFile = $logFile;
    $this->logFileSize = $logFileSize;
    $this->prefix = $prefix ?? Application::app()::$prefix;
    $p = dirname($this->logFile);
    if(!is_dir($p)) //建立日志目录
      mkdir($p);
  }

  function addLog(string $l) {
    $this->logs[] = '[' . date(DATE_ATOM) . "] $l\n";
  }

  function writeLogs() {
    if(!$this->savingLog) {
      $this->savingLog = true;
      //清除文件缓存, 否则filesize 返回值不变
      clearstatcache();
      //日志文件超过限制后压缩存档
      if(file_exists($this->logFile) && filesize($this->logFile) >= $this->logFileSize) {
        $p = dirname(realpath($this->logFile)) . '/' . $this->prefix;
        $f = $p . date('y-m-d_H') . '.log.bz2';
        if(copy($this->logFile, "compress.bzip2://$f"))
          file_put_contents($this->logFile, '');
      }
      $this->savingLog = false;
    }
    if($this->logs) {
      foreach($this->logs as $l)
        error_log($l, 3, $this->logFile);
      $this->logs = [];
    }
  }
}

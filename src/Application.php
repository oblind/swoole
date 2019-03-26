<?php
namespace Oblind;

class Application {
  /**@var Application */
  protected static $app;
  /**@var \stdClass */
  protected static $config;

  static function app($path = null): Application {
    if(static::$app)
      return static::$app;
    if($path || ($path = 'config.json') && file_exists($path))
      static::$config = json_decode(file_get_contents($path));
    else
      echo "warnning: config file $path not found\n";
    static::$app = new static;
    return static::$app;
  }

  static function config(): \stdClass {
    return static::app()::$config;
  }
}

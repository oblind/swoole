<?php
namespace Oblind;

class Application {
  /**@var Application */
  protected static $app;
  /**@var \stdClass */
  protected static $config;

  function __construct(string $configPath = 'config.json') {
    if(file_exists($configPath)) {
      static::$config = json_decode(file_get_contents($configPath));
      static::$app = $this;
    } else
      throw new \Exception("config file $configPath not found\n");
  }

  static function app(): Application {
    return static::$app;
  }

  static function config(): \stdClass {
    return static::$config;
  }
}

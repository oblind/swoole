<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;

class Controller {
  /**@var \stdClass */
  static $defaultConfig;
  /**@var \stdClass */
  public $config;
  /**@var Request */
  public $request;
  /**@var Response */
  public $response;
  /**@var Route\BaseRoute */
  public $route;

  function __construct($config = null) {
    $this->config = $config ?? static::$defaultConfig;
  }

  function view(string $filename) {
    echo "{$this->config->viewPath}/$filename\n";
    echo file_get_contents("{$this->config->viewPath}/$filename");
    $this->response->sendfile("{$this->config->viewPath}/$filename");
  }
}

Controller::$defaultConfig = (object)['viewPath' => './view'];

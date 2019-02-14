<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;

class Controller {
  /**@var \stdClass $defaultConfig */
  static $defaultConfig;
  /**@var \stdClass $config */
  public $config;
  /**@var Request $request */
  public $request;
  /**@var Response $response */
  public $response;
  /**@var Route\BaseRoute $route */
  public $route;

  function __construct($config = null) {
    $this->config = $config ?? static::$defaultConfig;
  }

  function view(string $filename) {
    $this->response->sendfile("{$this->config->viewPath}/$filename");
  }
}

Controller::$defaultConfig = (object)['viewPath' => './view'];

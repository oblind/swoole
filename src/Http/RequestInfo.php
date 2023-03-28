<?php
namespace Oblind\Http;

use stdClass;

class RequestInfo {
  public Controller $controller;
  public Route\BaseRoute $route;
  public string $action;
  public array $params = [];
  public array $args = [];
  public stdClass $data;
  public \Swoole\Http\Request $request;

  function __construct() {
    $this->data = new stdClass;
  }
}

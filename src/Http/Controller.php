<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;

class Controller {
  /**@var Request $request */
  public $request;
  /**@var Response $response */
  public $response;
  /**@var Route $route */
  public $route;
}

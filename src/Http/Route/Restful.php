<?php
namespace Oblind\Http\Route;

use Swoole\Http\Request;
use Oblind\Http\Router;

class Restful extends BaseRoute {

  /**@var array $route */
  public $route;

  function route(Request $request): bool {
    $this->route = [];
    $uri = $request->server['request_uri'];
    foreach($this->router->controllers as $n => $c) {
      $l = strlen($n);
      if($uri == $n || ($l < strlen($uri) && substr($uri, 0, $l) == $n && $uri[$l] == '/')) {
        $this->controller = $c;
        $this->params = [];
        $fs = static::getFields(substr($uri, $l));
        $n = count($fs);
        $i = 0;
        while($i < $n) {
          $this->params[$fs[$i]] = $fs[$i + 1] ?? null;
          $i += 2;
        }
        return true;
      }
    }
    /*if($this->module)
      $this->route['module'] = $this->module;
    elseif($c > 2)
      $this->route['module'] = $f[$i++];
    else
      return false;
    if($this->controller)
      $this->route['controller'] = $this->controller;
    elseif($c > 1)
      $this->route['controller'] = $f[$i++];
    else
      return false;
    if($this->action)
      $this->route['action'] = $this->controller;
    elseif($c)
      $this->route['action'] = $f[$i++];
    else
      return false;
    while($i < $c) {
      $this->params[$f[$i]] = $f[$i + 1] ?? null;
      $i += 2;
    }
    echo "$this->module $this->controller $this->action\n";
    */
    //var_dump($this->params);
    return false;
  }
}

<?php
namespace Oblind\Http\Route;

use Swoole\Http\Request;

class Restful extends BaseRoute {

  function route(Request $request): bool {
    $this->action = null;
    $uri = $request->server['request_uri'];
    foreach($this->router->controllers as $n => $c) {
      $l = strlen($n);
      if($uri == $n || ($l < strlen($uri) && substr($uri, 0, $l) == $n && $uri[$l] == '/')) {
        if(($m = $request->server['request_method']) == 'GET' && method_exists($c, 'indexAction'))
          $this->action = 'index';
        elseif($m == 'POST' && method_exists($c, 'storeAction'))
          $this->action = 'store';
        elseif($m == 'PUT' && method_exists($c, 'updateAction'))
          $this->action = 'update';
        elseif($m == 'DELETE' && method_exists($c, 'destroyAction'))
          $this->action = 'destroy';
        else
          continue;
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
    return false;
  }
}

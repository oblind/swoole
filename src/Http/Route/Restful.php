<?php
namespace Oblind\Http\Route;

use Swoole\Http\Request;

class Restful extends BaseRoute {

  function route(Request $request): bool {
    $uri = $request->server['request_uri'];
    foreach($this->router->controllers as $n => $c) {
      $l = strlen($n);
      if($uri == $n || ($l < strlen($uri) && substr($uri, 0, $l) == $n && $uri[$l] == '/')) {
        if(($m = $request->server['request_method']) == 'GET' && method_exists($c, 'indexAction'))
          $request->action = 'index';
        elseif($m == 'POST' && method_exists($c, 'storeAction'))
          $request->action = 'store';
        elseif($m == 'PUT' && method_exists($c, 'updateAction'))
          $request->action = 'update';
        elseif($m == 'DELETE' && method_exists($c, 'destroyAction'))
          $request->action = 'destroy';
        else
          continue;
        $request->controller = $c;
        $request->params = [];
        $fs = static::getFields(substr($uri, $l));
        $n = count($fs);
        $i = 0;
        while($i < $n) {
          $request->params[$fs[$i]] = $fs[$i + 1] ?? null;
          $i += 2;
        }
        $request->route = $this;
        $c = get_class($c);
        $p = strrpos($c, '\\');
        $this->name = lcfirst(substr($c, $p + 1, strrpos($c, 'Controller') - $p - 1)) . ucfirst($request->action);
        return true;
      }
    }
    return false;
  }
}

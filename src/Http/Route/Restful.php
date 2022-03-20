<?php
namespace Oblind\Http\Route;

use Swoole\Http\Request;

class Restful extends BaseRoute {

  function route(Request $request): bool {
    $ms = ['GET' => 'index', 'POST' => 'store', 'PUT' => 'update', 'DELETE' => 'destroy', 'PATCH' => 'patch', 'HEAD' => 'head', 'TRACE' => 'trace', 'OPTIONS' => 'options'];
    $uri = $request->server['request_uri'] ?? null;
    $a = $ms[$request->server['request_method']] ?? null;
    if(!$uri || !$a)
      return false;
    foreach($this->router->controllers as $n => $r) {
      $l = strlen($n);
      $c = $r->controller;
      if($uri == $n || ($l < strlen($uri) && substr($uri, 0, $l) == $n && $uri[$l] == '/')) {
        $fs = static::getFields(substr($uri, $l));
        $i = 0;
        if($fs && ($m = $a . ucfirst($fs[0])) && method_exists($c, "{$m}Action")) {
          $request->action = $m;
          $i = 1;
        } elseif(method_exists($c, "{$a}Action"))
          $request->action = $a;
        else
          continue;
        $request->controller = $c;
        $request->params = [];
        $request->args = [];        
        $n = count($fs);
        foreach($r->args as $m => $ts) {
          if($m == $request->action) {
            foreach($ts as $t) {
              $request->args[] = $i < $n ? ($t == 'int' ? intval($fs[$i]) : $fs[$i]) : null;
              $i++;
            }
            break;
          }
        }
        while($i < $n) {
          $request->params[$fs[$i]] = $fs[$i + 1] ?? null;
          $i += 2;
        }
        /*if($i > 1 && method_exists($c, "{$fs[$i - 2]}Action")) {
          $request->action = $fs[$i - 2];
          var_dump($request->args);
          foreach ($request->params as $p)
            $request->args[] = $p;
        }*/
        $request->route = $this;
        $c = get_class($c);
        $p = strrpos($c, '\\');
        if($p === false) $p = -1;
        $this->name = lcfirst(substr($c, $p + 1, strrpos($c, 'Controller') - $p - 1)) . ucfirst($request->action);
        return true;
      }
    }
    return false;
  }
}

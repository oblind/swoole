<?php
namespace Oblind\Http\Route;

use Swoole\Http\Request;
use Oblind\Http\RequestInfo;

class Restful extends BaseRoute {

  function route(Request $request): ?RequestInfo {
    $ms = [
      'GET' => 'index', 'POST' => 'store', 'PUT' => 'update',
      'DELETE' => 'destroy', 'PATCH' => 'patch', 'HEAD' => 'head',
      'TRACE' => 'trace', 'OPTIONS' => 'options'
    ];
    $uri = $request->server['request_uri'] ?? null;
    $uri1 = strtolower($uri);
    $a = $ms[$request->server['request_method']] ?? null;
    if(!$uri || !$a)
      return null;
    foreach($this->router->controllers as $n => $r) {
      $l = strlen($n);
      $c = $r->controller;
      if($uri1 == $n || ($l < strlen($uri1) && substr($uri1, 0, $l) == $n && $uri1[$l] == '/')) {
        $fs = static::getFields(substr($uri, $l));
        $i = 0;
        if($fs && ($m = $a . ucfirst($fs[0])) && method_exists($c, "{$m}Action")) {
          $info = new RequestInfo;
          $info->action = $m;
          $i = 1;
        } elseif(method_exists($c, "{$a}Action")) {
          $info = new RequestInfo;
          $info->action = $a;
        } else
          continue;
        $info->controller = $c;
        $n = count($fs);
        foreach($r->args as $m => $ts) {
          if($m == $info->action) {
            foreach($ts as $t) {
              $info->args[] = $i < $n ? ($t == 'int' ? intval($fs[$i]) : $fs[$i]) : null;
              $i++;
            }
            break;
          }
        }
        while($i < $n) {
          $info->params[$fs[$i]] = $fs[$i + 1] ?? null;
          $i += 2;
        }
        /*if($i > 1 && method_exists($c, "{$fs[$i - 2]}Action")) {
          $info->action = $fs[$i - 2];
          var_dump($info->args);
          foreach ($info->params as $p)
            $info->args[] = $p;
        }*/
        $info->route = $this;
        $c = get_class($c);
        $p = strrpos($c, '\\');
        if($p === false) $p = -1;
        $this->name = lcfirst(substr($c, $p + 1, strrpos($c, 'Controller') - $p - 1)) . ucfirst($info->action);
        return $info;
      }
    }
    return null;
  }
}

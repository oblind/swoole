<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Server;
use Oblind\Language;

class LanguagePort extends Port {
  function onRequest(Request $request, Response $response, Server $svr) {
    if($l = $request->header['accept-language'] ?? null) {
      if($p = strpos($l, ','))
        $l = substr($l, 0, $p);
      Language::set($l);
    }
    parent::onRequest($request, $response, $svr);
  }
}

<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Server;
use Oblind\Language;

class LanguagePort extends Port {
  function pageNotFound(Request $request, Response $response) {
    $response->status(RES_NOT_FOUND);
    $e = _('404: page not found');
    $response->end("<!DOCTYPE html>
<html>
<head>
  <meta name=\"viewport\" content=\"width=device-width\">
</head>
<body>
  {$request->server['request_uri']}
  <h1>$e</h1>
</body>
</html>");
  }

  function onRequest(Request $request, Response $response, Server $svr) {
    if($l = $request->header['accept-language'] ?? null) {
      if($p = strpos($l, ','))
        $l = substr($l, 0, $p);
      Language::set($l);
    }
    parent::onRequest($request, $response, $svr);
  }
}

Language::addTranslation(['404: page not found' => '很抱歉, 您要访问的页面不存在 !'], 'zh-cn');
Language::addTranslation(['404: page not found' => '很抱歉，您要訪問的頁面不存在 !'], 'zh-tw');

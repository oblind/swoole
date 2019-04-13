<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Oblind\Language;

class LanguagePort extends Port {
  function pageNotFound(Request $request, Response $response) {
    $response->status(RES_NOT_FOUND);
    $e = _('page not found');
    if($request->header['x-requested-with'] ?? 0 == 'XMLHttpRequest')
      $response->end($e);
    else
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

  function onRequest(Request $request, Response $response) {
    if($l = $request->header['accept-language'] ?? null) {
      if($p = strpos($l, ','))
        $l = substr($l, 0, $p);
      Language::set($l);
    }
    parent::onRequest($request, $response);
  }
}

Language::addTranslation(['page not found' => '很抱歉, 您要访问的页面不存在 !'], 'zh-cn');
Language::addTranslation(['page not found' => '很抱歉，您要訪問的頁面不存在 !'], 'zh-tw');

<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Oblind\Language;

class LanguageHttpPort extends HttpPort {
  function pageNotFound(Request $request, Response $response) {
    $e = _('page not found');
    if($request->header['x-requested-with'] ?? 0 === 'XMLHttpRequest')
      $response->end($e);
    else
      $response->end("<!DOCTYPE html>
<html>
<head>
  <meta name=\"viewport\" content=\"width=device-width\">
</head>
<body style=\"text-align: center\">
  <img width=\"200\" src=\"data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZlcnNpb249IjEuMSIgdmlld0JveD0iMCAwIDQ4IDQ4Ij4KPGRlZnM+Cgk8bGluZWFyR3JhZGllbnQgaWQ9ImxnIiB4MT0iMCIgeTE9IjEwMCUiIHgyPSIwIiB5Mj0iMCI+CgkJPHN0b3Agb2Zmc2V0PSIwIiBzdHlsZT0ic3RvcC1jb2xvcjojQkJERUZCIi8+CgkJPHN0b3Agb2Zmc2V0PSIxMDAlIiBzdHlsZT0ic3RvcC1jb2xvcjojNWFmIi8+Cgk8L2xpbmVhckdyYWRpZW50Pgo8L2RlZnM+CjxnIGZpbGw9IiM2MTYxNjEiPgoJPHJlY3QgeD0iMzQuNiIgeT0iMjguMSIgdHJhbnNmb3JtPSJtYXRyaXgoLjcwNyAtLjcwNyAuNzA3IC43MDcgLTE1LjE1NCAzNi41ODYpIiB3aWR0aD0iNCIgaGVpZ2h0PSIxNyIvPgoJPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMTYiLz4KPC9nPgo8cmVjdCB4PSIzNi4yIiB5PSIzMi4xIiB0cmFuc2Zvcm09Im1hdHJpeCguNzA3IC0uNzA3IC43MDcgLjcwNyAtMTUuODM5IDM4LjIzOSkiIGZpbGw9IiMzNzQ3NEYiIHdpZHRoPSI0IiBoZWlnaHQ9IjEyLjMiLz4KPGNpcmNsZSBmaWxsPSJ1cmwoI2xnKSIgY3g9IjIwIiBjeT0iMjAiIHI9IjEzIi8+CjxwYXRoIGZpbGw9IiNCQkRFRkIiIGQ9Ik0yNi45LDE0LjJjLTEuNy0yLTQuMi0zLjItNi45LTMuMnMtNS4yLDEuMi02LjksMy4yYy0wLjQsMC40LTAuMywxLjEsMC4xLDEuNGMwLjQsMC40LDEuMSwwLjMsMS40LTAuMSBDMTYsMTMuOSwxNy45LDEzLDIwLDEzczQsMC45LDUuNCwyLjVjMC4yLDAuMiwwLjUsMC40LDAuOCwwLjRjMC4yLDAsMC41LTAuMSwwLjYtMC4yQzI3LjIsMTUuMywyNy4yLDE0LjYsMjYuOSwxNC4yeiIvPgo8L3N2Zz4K\"><br>
  {$request->server['request_uri']}
  <h2>$e</h2>
</body>
</html>");
  }
  /*PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+CiAgPHBhdGggZmlsbD0iYmx1ZSIgZD0iTTE2LjMxOTg1NzQsMTQuOTA1NjQzOSBMMjEuNzA3MTA2OCwyMC4yOTI4OTMyIEwyMC4yOTI4OTMyLDIxLjcwNzEwNjggTDE0LjkwNTY0MzksMTYuMzE5ODU3NCBDMTMuNTUwOTYwMSwxNy4zNzI5MTg0IDExLjg0ODcxMTUsMTggMTAsMTggQzUuNTgxNzIyLDE4IDIsMTQuNDE4Mjc4IDIsMTAgQzIsNS41ODE3MjIgNS41ODE3MjIsMiAxMCwyIEMxNC40MTgyNzgsMiAxOCw1LjU4MTcyMiAxOCwxMCBDMTgsMTEuODQ4NzExNSAxNy4zNzI5MTg0LDEzLjU1MDk2MDEgMTYuMzE5ODU3NCwxNC45MDU2NDM5IFogTTEwLDE2IEMxMy4zMTM3MDg1LDE2IDE2LDEzLjMxMzcwODUgMTYsMTAgQzE2LDYuNjg2MjkxNSAxMy4zMTM3MDg1LDQgMTAsNCBDNi42ODYyOTE1LDQgNCw2LjY4NjI5MTUgNCwxMCBDNCwxMy4zMTM3MDg1IDYuNjg2MjkxNSwxNiAxMCwxNiBaIi8+Cjwvc3ZnPgo=*/
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

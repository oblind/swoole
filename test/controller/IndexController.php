<?php
use Oblind\Http\Controller;

class IndexController extends Controller {
  function indexAction() {
    var_dump($this->route->params);
    $this->response->end('<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
</head>
<body>
  <a href="/test">test</a>
</body>
</html>
');
  }
}

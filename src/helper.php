<?php

use Oblind\Application;

const RES_BAD_REQUEST = 400;
const RES_NO_PERMISSION = 401;
const RES_FORBIDEN = 403;
const RES_NOT_FOUND = 404;
const RES_NOT_ALLOWED = 405;

function base64url_encode($s) {
  $s = base64_encode($s);
  if($l = strpos($s, '='))
    $s = substr($s, 0, $l);
  return str_replace('/', '_', str_replace('+', '-', $s));
}

function base64url_decode(string $s): string {
  if($l = strlen($s) % 4)
    $s .= str_repeat('=', 4 - $l);
  return base64_decode(str_replace('_', '/', str_replace('-', '+', $s)));
}

function app() {
  return Application::app();
}

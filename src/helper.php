<?php
namespace Oblind;

const ERROR_STRING = [
  E_ERROR => 'E_ERROR',
  E_WARNING => 'E_WARNING',
  E_PARSE => 'E_PARSE',
  E_NOTICE => 'E_NOTICE',
  E_CORE_ERROR => 'E_CORE_ERROR',
  E_CORE_WARNING => 'E_CORE_WARNING',
  E_COMPILE_ERROR => 'E_COMPILE_ERROR',
  E_COMPILE_WARNING => 'E_COMPILE_WARNING',
  E_USER_ERROR => 'E_USER_ERROR',
  E_USER_WARNING => 'E_USER_WARNING',
  E_USER_NOTICE => 'E_USER_NOTICE',
  E_STRICT => 'E_STRICT',
  E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
  E_DEPRECATED => 'E_DEPRECATED',
  E_USER_DEPRECATED => 'E_USER_DEPRECATED',
];
const E_FATAL = E_ERROR | E_USER_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_RECOVERABLE_ERROR | E_PARSE;

/*function format_backtrace(\Throwable $e): string {
  $msg = $e->getMessage() . "\nin " . $e->getFile() . '(' . $e->getLine() . "):\nStack trace\n";
  // . $e->getTraceAsString();
  if($ec = \Oblind\ERROR_STRING[$e->getCode()] ?? null)
    $msg = "$ec: $msg";
  //不保留参数
  foreach(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS) as $i => $l) {
    $msg .= "\n#$i " . (isset($l['file']) ? "{$l['file']}({$l['line']})" : '[internal function]') . ': ';
    if(isset($l['class']))
      $msg .= "{$l['class']}{$l['type']}";
    $msg .= "{$l['function']}()";
  }
  return $msg;
}*/

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

function base642image(string $url, &$ext): string|false {
  if(str_starts_with($url, 'data:image/') && ($p = strpos($url, ';base64,'))) {
    $ext = substr($url, 11, $p - 11);
    if($q = strpos($ext, '+'))
      $ext = substr($ext, 0, $q);
    return base64_decode(substr($url, $p + 8));
  }
  return false;
}

<?php

namespace Oblind {
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

  function format_backtrace(\Throwable $e): string {
    $s = $e->getMessage();
    $msg = $s . "\nStack trace:";
    if($ec = \Oblind\ERROR_STRING[$e->getCode()] ?? null)
      $msg = "$ec: $msg";
    foreach($e->backtrace ?? debug_backtrace() as $i => $l) {
      $msg .= "\n#$i " . (isset($l['file']) ? "{$l['file']}({$l['line']})" : '[internal function]') . ': ';
      if(isset($l['class']))
        $msg .= "{$l['class']}{$l['type']}";
      $msg .= "{$l['function']}()";
    }
    return $msg;
  }

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
};

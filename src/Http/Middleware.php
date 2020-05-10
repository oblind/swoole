<?php
namespace Oblind\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;

abstract class Middleware {
  public array $exceptions;
  public bool $blacklistMode;

  /**
   * 中间件过滤器
   *
   * @param array $exceptions 例外情况列表
   * @param boolean $black 黑名单模式
   */
  function __construct(array $exceptions = [], bool $blacklistMode = false) {
    $this->exceptions = $exceptions;
    $this->blacklistMode = $blacklistMode;
  }

  abstract function handle(Request $request, Response $response, callable $next): void;
}

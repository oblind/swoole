<?php
namespace Oblind\Http;

use Oblind\Language;

class Validator {
  protected static $validators = [];
  protected static $fields = [];

  static function __callStatic($name, $a) {
    return isset(static::$validators[$name]) && static::$validators[$name](...$a);
  }

  static function register($name, $validator) {
    static::$validators[$name] = $validator;
  }

  static function setFields($fields) {
    static::$fields = array_merge(static::$fields, $fields);
  }

  static function min($value, ?array $arg, &$err): bool {
    $r = strlen($value) >= ($a = intval($arg[0]));
    if(!$r)
      $err = sprintf(_(":attribute minimal length %d"), $a);
    return $r;
  }

  static function max($value, ?array $arg, &$err): bool {
    $r = strlen($value) <= ($a = intval($arg[0]));
    if(!$r)
      $err = sprintf(_(":attribute maximal length %d"), $a);
    return $r;
  }

  static function between($value, ?array $arg, &$err): bool {
    $a = intval($arg[0]);
    $b = intval($arg[1]);
    if($a > $b)
      [$a, $b] = [$b, $a];
    $r = ($l = strlen($value)) >= $a && $l <= $b;
    if(!$r)
      $err = sprintf(_(":attribute length between %d-%d"), $a, $b);
    return $r;
  }

  static function email($value, ?array $arg, &$err): bool {
    $r = preg_match('/^\w+@\w+\.\w+/', $value) > 0;
    if(!$r)
      $err = _(':attribute format invalid');
    return $r;
  }

  static function confirmed($value, ?array $arg, &$err, $values, $field): bool {
    $r = $value == ($values[$field.'_confirmation'] ?? null);
    if(!$r)
      $err = _('inconsistent :attribute and confirmation :attribute');
    return $r;
  }

  protected static function val(array $values, string $field, string $rule, &$err, array $fields): bool {
    if($l = strpos($rule, ':')) {
      $a = [];
      $q = $l + 1;
      while(($p = strpos($rule, ',', $q)) != false) {
        $a[] = substr($rule, $q, $p - $q);
        $q = $p + 1;
      }
      $a[] = substr($rule, $q);
      $rule = substr($rule, 0, $l);
    } else
      $a = null;
    if($rule == 'required') {
      $r = isset($values[$field]);
      if(!$r)
        $err = str_replace(':attribute', _($fields[$field] ?? static::$fields[$field] ?? $field), _(':attribute required'));
      return $r;
    } elseif(isset($values[$field]) && !static::$rule($values[$field], $a, $err, $values, $field)) {
      $err = str_replace(':attribute', _($fields[$field] ?? static::$fields[$field] ?? $field), $err);
      return false;
    }
    return true;
  }

  static function valid(array $values, array $rules, &$err, array $fields = []): bool {
    foreach($rules as $field => $r) {
      $n = 0;
      while(($m = strpos($r, '|', $n)) != false) {
        if(!static::val($values, $field, substr($r, $n, $m - $n), $err, $fields))
          return false;
        $n = $m + 1;
      }
      if(!static::val($values, $field, substr($r, $n), $err, $fields))
        return false;
    }
    return true;
  }
}

Language::addTranslation([
  'name' => '用户名',
  'email' => '电子邮箱',
  'password' => '密码',
  'confirmation' => '确认密码',
  ':attribute minimal length %d' => ':attribute 至少 %d 个字符',
  ':attribute maximal length %d' => ':attribute 最多 %d 个字符',
  ':attribute length between %d-%d' => ':attribute 长度范围 %d-%d',
  ':attribute format invalid' => ':attribute 格式错误',
  'inconsistent :attribute and confirmation :attribute' => ':attribute 和 确认:attribute 不一致',
  ':attribute required' => '请输入 :attribute'
], 'zh-cn');

/*Validator::setFields([
  'name' => _('name'),
  'email' => _('email'),
  'password' => _('password'),
  'password_confirmation' => _('confirmation')
]);
*/

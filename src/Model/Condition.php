<?php
namespace Oblind\Model;

class Condition {
  protected $parent;
  public $condition;

  static function check(&$op, &$value) {
    if($value === null) {
      $op = '=';
      $value = $op;
    }
  }

  function __construct(Condition $parent, string $key, $op, $value = null) {
    $this->parent = $parent;
    static::check($op, $value);
    $this->condition = [$key => [$op, $value]];
  }

  function where(string $key, $op, $value = null): Condition {
    static::check($op, $value);
    $this->condition[$key] = [$op, $value];
    return $this;
  }

  function or(string $key, $op, $value = null): Condition {
    static::check($op, $value);
    if(!$this->parent)
      $this->parent = [$this->condition];
    $this->parent[] = [[$key => [$op, $value]]];
    return $this;
  }

  function get() {
    return $this->statement->get();
  }

  function first() {
    return $this->statement->first();
  }
}

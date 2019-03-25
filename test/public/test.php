<?php
class A {
  public $a;

  function f(array $a) {
    $this->a = array_merge($this->a ?: [], $a, null);
    var_dump($this->a);
  }
}

$a = new A;
$a->f([3]);
$a->f([5]);

<?php
namespace Oblind\Model;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use ArrayIterator;
use JsonSerializable;
use Traversable;

class Collection extends Decachable implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable {
  protected $_cacheItemClass;

  function __construct($data, $parent = null, $parentKey = null, $cacheItemClass = null) {
    $this->_cacheItemClass = $cacheItemClass;
    parent::__construct($data, $parent, $parentKey);
  }

  function __tostring() {
    return json_encode($this->jsonSerialize());
  }

  public function decache() {
    if($this->_cacheItemClass) {
      $this->_decaching = true;
      foreach($this->_data as $k => $v) {
        $this->_data[$k] = new $this->_cacheItemClass($v, $this, $k);
        if($this->_data[$k] instanceof Decachable)
          $this->_data[$k]->decache();
      }
      $this->_decaching = false;
    }
  }

  function toArray() {
    return $this->_data;
  }

  function offsetExists($offset): bool {
    return isset($this->_data[$offset]);
  }

  function offsetGet($offset): mixed {
    return is_array($this->_data) ? $this->_data[$offset] : $this->_data->$offset;
  }

  function offsetSet($offset, $value): void {
    $this->_data[$offset] = $value;
    if(!$this->_decaching && $this->_parent)
      $this->_parent->onChange($this->_parentKey);
  }

  function offsetUnset($offset): void {
    unset($this->_data[$offset]);
    if($this->_parent)
      $this->_parent->onChange($this->_parentKey);
  }

  function count(): int {
    return count($this->_data);
  }

  function getIterator(): Traversable {
    return new ArrayIterator($this->_data);
  }

  function jsonSerialize(): mixed {
    $r = [];
    if($this->_data)
      foreach($this->_data as $v)
        $r[] = $v instanceof JsonSerializable ? $v->jsonSerialize() : $v;
    return $r;
  }

  /*function splice($start, ...$args) {
    if(array_splice($this->_data, $start, ...$args) && $this->_parent)
      $this->_parent->onChange($this->_parentKey);
  }*/
}

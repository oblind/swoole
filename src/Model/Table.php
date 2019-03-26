<?php
namespace Oblind\Model;

use Oblind\Model\BaseModel;

class Table extends BaseModel {
  
  function __construct($name) {
    if(is_string($name)) {
      static::$tableNames[get_called_class()] = $name;
      parent::__construct();
    } else
      parent::__construct($name);
  }

  /**
   * return a new BaseTable with table named $name
   */
  static function open(string $name) {
    return new static($name);
  }
}

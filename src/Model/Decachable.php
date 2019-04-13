<?php
namespace Oblind\Model;

abstract class Decachable {
  /**@var \stdClass */
  protected $_data;
  protected $_parent;
  protected $_parentKey;
  protected $_decaching;
  protected $_save = [];

  abstract function decache();

  function __construct(&$data, $parent = null, $parentKey = null) {
    $this->_data = $data;
    $this->_parent = $parent;
    $this->_parentKey = $parentKey;
  }

  function onChange($k) {
    if(!($this->_decaching || in_array($k, $this->_save))) {
      $this->_save[] = $k;
      if($this->_parent)
        $this->_parent->onChange($this->_parentKey);
    }
  }

  function save() {
    if(is_array($this->_data))
      foreach($this->_save as $f)
        $this->_data[$f]->save();
    else
      foreach($this->_save as $f)
        $this->_data->$f->save();
  }
}

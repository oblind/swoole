<?php
namespace Oblind\Model;

class Statement {
  /**@var BaseModel */
  protected $class;
  protected $condition;
  protected $orderBy;

  function __construct(string $class) {
    $this->class = $class;
  }

  protected function statement($col) {
    _getdb:
    try {
      $db = $this->class::getDatabase();
      $sql = 'select ' . (is_array($col) ? implode(', ', $col) : $col) . ' from ' . $this->class::getTableName();
      if($this->condition) {
        $sql .= ' where ' . (($a = is_array($this->condition)) ? implode(' and ', array_map(function($k) {
          return $k . '=?';
        }, array_keys($this->condition))) : $this->condition);
        if($this->orderBy) {
          $sql .= " order by {$this->orderBy[0]}";
          if($this->orderBy[1])
            $sql .= " {$this->orderBy[1]}";
        }
        $s = $db->prepare($sql);
        $s->execute($a ? array_values($this->condition) : null);
      } else {
        if($this->orderBy) {
          $sql .= " order by {$this->orderBy[0]}";
          if($this->orderBy[1])
            $sql .= " {$this->orderBy[1]}";
        }
        $s = $db->query($sql);
      }
    } catch(\Throwable $e) {
      if($e->getCode() == 2006)
        goto _getdb;
      else
        throw $e;
    }
    $this->class::putDatabase($db);
    return $s;
  }

  function where($condition) {
    $this->condition = $condition;
    return $this;
  }

  function orderBy($by, $order = false) {
    $this->orderBy = [$by, $order ? strtolower($order) : $order];
    return $this;
  }

  function find($primary, $col = '*') {
    $p = $this->class::getPrimary();
    if(is_array($primary)) {
      $r = [];
      foreach($primary as $v)
        if($t = $this->where([$p => $v])->first($col))
          $r[] = $t;
      return new Collection($r);
    }
    return $this->where([$p => $primary])->first($col);
  }

  function get($col = '*') {
    $r = [];
    if($s = $this->statement($col)) {
      foreach($s as $v)
        $r[] = new $this->class($v);
      return new Collection($r);
    }
  }

  function first($col = '*') {
    if(($s = $this->statement($col)) && ($r = $s->fetch()))
      return new $this->class($r);
  }
}

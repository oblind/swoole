<?php
namespace Oblind\Model;

class Statement {
  /**@var BaseModel */
  protected $class;
  protected $condition;
  protected $params;
  protected $orderBy;

  function __construct(string $class) {
    $this->class = $class;
  }

  protected function statement($col) {
    _getdb:
    try {
      $db = $this->class::getDatabase();
    } catch(\Throwable $e) {
      if($e->getCode() == 2006)
        goto _getdb;
      else
        throw $e;
    }
    $sql = 'select ' . (is_array($col) ? implode(', ', array_map(function($v) {
      return "`$v`";
    }, $col)) : $col) . ' from `' . $this->class::getTableName() . '`';
    if($this->condition) {
      $sql .= ' where ';
      if(is_array($this->condition)) {
        $this->params = array_values($this->condition);
        $sql .= implode(' and ', array_map(function($k) {
          return "`$k`=?";
        }, array_keys($this->condition)));
      } else
        $sql .= $this->condition;
      if($this->orderBy) {
        $sql .= " order by `{$this->orderBy[0]}`";
        if($this->orderBy[1])
          $sql .= " {$this->orderBy[1]}";
      }
      $s = $db->prepare($sql);
      $s->execute($this->params);
    } else {
      if($this->orderBy) {
        $sql .= " order by `{$this->orderBy[0]}`";
        if($this->orderBy[1])
          $sql .= " `{$this->orderBy[1]}`";
      }
      $s = $db->query($sql);
    }
    $this->class::putDatabase($db);
    return $s;
  }

  function where($condition, $params = null): Statement {
    $this->condition = $condition;
    $this->params = $params;
    return $this;
  }

  function orderBy(string $by, bool $order = false): Statement {
    $this->orderBy = [$by, $order ? strtolower($order) : $order];
    return $this;
  }

  function find($primary, string $col = '*') {
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

  function get(string $col = '*'): ?Collection {
    $r = [];
    if($s = $this->statement($col)) {
      foreach($s as $v)
        $r[] = new $this->class($v);
      return new Collection($r);
    }
  }

  function first(string $col = '*') {
    if(($s = $this->statement($col)) && ($r = $s->fetch()))
      return new $this->class($r);
  }
}

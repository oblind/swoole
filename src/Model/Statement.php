<?php
namespace Oblind\Model;

use Swoole\Database\PDOStatementProxy;

class Statement {
  /**@var string */
  protected string $class;
  protected $condition;
  /**@var array */
  protected ?array $params = null;
  /**@var array */
  protected ?array $orderBy = null;
  /**@var array */
  protected ?array $limit = null;

  function __construct(string $class) {
    $this->class = $class;
  }

  static function error(\Throwable $e): bool {
    $msg = $e->getMessage();
    foreach([
      'MySQL server has gone away',
      ' bytes failed with errno=',
      'Wrong COM_STMT_PREPARE response size',
      ' has already been bound to another coroutine',
      'Packets out of order',
      'SQLSTATE[HY000]',
    ] as $m)
      if(strpos($msg, $m) !== false) {
        echo $e->getCode(), ": $msg\n";
        return true;
      }
    return false;
  }

  protected function addCondition(string &$sql) {
    if($this->condition) {
      $sql .= ' where ';
      if(is_array($this->condition)) {
        $this->params = [];
        $cs = [];
        foreach($this->condition as $k => $v) {
          if($v === null)
            $cs[] = "$k is null";
          else {
            $cs[] = "$k = ?";
            $this->params[] = $v;
          }
        }
        $sql .= implode(' and ', $cs);
        /*$this->params = array_values($this->condition);
        $sql .= implode(' and ', array_map(function($k) {
          return "`$k`=?";
        }, array_keys($this->condition)));
        */
      } else
        $sql .= $this->condition;
    }
  }

  protected function statement($col): ?PDOStatementProxy {
    $c = 0;
    _getdb:
    try {
      $db = $this->class::getDatabase();
      $sql = 'select ' . (is_array($col) ? implode(', ', array_map(function($v) {
        return "`$v`";
      }, $col)) : $col) . ' from `' . $this->class::getTableName() . '`';
      $this->addCondition($sql);
      if($this->orderBy) {
        $sql .= " order by `{$this->orderBy[0]}`";
        if($this->orderBy[1])
          $sql .= " {$this->orderBy[1]}";
      }
      if($this->limit)
        $sql .= ' limit ' . ($this->limit[1] ? "{$this->limit[0]}, {$this->limit[1]}" : $this->limit[0]);
      if($this->condition) {
        $s = $db->prepare($sql);
        $s->execute($this->params);
      } else
        $s = $db->query($sql);
    } catch(\Throwable $e) {
      if(static::error($e) && $c++ < 3)
        goto _getdb;
      else
        throw $e;
    }
    $this->class::putDatabase($db);
    return $s;
  }

  function where($condition, ?array $params = null): Statement {
    $this->condition = $condition;
    $this->params = $params;
    return $this;
  }

  function orderBy(string $by, ?string $order = null): Statement {
    $this->orderBy = [$by, $order ? strtolower($order) : $order];
    return $this;
  }

  function limit(int $offset, int $rows = null): Statement {
    $this->limit = [$offset, $rows];
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

  function get($col = '*'): Collection {
    $r = [];
    $s = $this->statement($col);
    if($s && $s->rowCount())
      foreach($s->fetchAll() as $v)
        $r[] = new $this->class($v);
    return new Collection($r);
  }

  function entries($col = '*') {
    $s = $this->statement($col);
    foreach($s as $v)
      yield $v;
  }

  function first($col = '*'): ?BaseModel {
    if(($s = $this->statement($col)) && ($r = $s->fetch()))
      return new $this->class($r);
    return null;
  }

  function count(): int {
    $c = 0;
    _getdb:
    try {
      $db = $this->class::getDatabase();
      $sql = 'select count(*) c from `' . $this->class::getTableName() . '`';
      $this->addCondition($sql);
      if($this->condition) {
        $s = $db->prepare($sql);
        $s->execute($this->params);
      } else
        $s = $db->query($sql);
    } catch(\Throwable $e) {
      if(static::error($e) && $c++ < 3)
        goto _getdb;
      else
        throw $e;
    }
    $this->class::putDatabase($db);
    return $s->fetch()->c;
  }
}

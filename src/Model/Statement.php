<?php
namespace Oblind\Model;

use Swoole\Database\PDOStatementProxy;

class Statement {
  protected string $class;
  protected ?PDOStatementProxy $stmt;
  protected array $condition = [];
  protected array $params = [];
  protected string|array|null $groupBy = null;
  protected ?array $orderBy = null;
  protected ?array $limit = null;

  function __construct(string $class, PDOStatementProxy $stmt = null) {
    $this->class = $class;
    $this->stmt = $stmt;
  }

  /*static function error(\Throwable $e): bool {
    $msg = $e->getMessage();
    foreach([
      'MySQL server has gone away',
      ' bytes failed with errno=',
      'Wrong COM_STMT_PREPARE response size',
      ' has already been bound to another coroutine',
      'Packets out of order',
      'SQLSTATE[HY000]',
      'Connection was killed',
    ] as $m)
      if(strpos($msg, $m) !== false) {
        echo $e->getCode(), ": $msg\n";
        return true;
      }
    return false;
  }*/

  protected function addCondition(string &$sql) {
    if($this->condition) {
      $sql .= ' where ';
      $sqls = [];
      foreach($this->condition as $cdt) {
        if(is_array($cdt)) {
          $cs = [];
          foreach($cdt as $k => $v) {
            if($v === null)
              $cs[] = "`$k` is null";
            else {
              $cs[] = "`$k` = ?";
              $this->params[] = $v;
            }
          }
          $sqls[] = '(' . implode(' and ', $cs) . ')';
          /*$this->params = array_values($this->condition);
          $sql .= implode(' and ', array_map(function($k) {
            return "`$k`=?";
          }, array_keys($this->condition)));
          */
        } else
          $sqls[] = $cdt;
      }
      $sql .= implode(' or ', $sqls);
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
      if($this->groupBy) {
        $sql .= " group by " . (is_string($this->groupBy) ? "`$this->groupBy`" : implode(', ', array_map(function($v) {
          return "`$v`";
        }, $this->groupBy)));
      }
      if($this->orderBy) {
        $sql .= " order by {$this->orderBy[0]}";
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
      echo "EXCEPTION\n" . $e;
      if($c++ < 10) {
        usleep(50000);
        goto _getdb;
      } else
        throw $e;
    }
    $this->class::putDatabase($db);
    return $s;
  }

  function where($condition, ?array $params = null): Statement {
    $this->condition = [$condition];
    if($params)
      array_splice($this->params, count($this->params), 0, $params);
    return $this;
  }

  function orWhere($condition, array $params = null): Statement {
    $this->condition[] = $condition;
    if($params)
      array_splice($this->params, count($this->params), 0, $params);
    return $this;
  }

  function groupBy(string|array $by): Statement {
    $this->groupBy = $by;
    return $this;
  }

  function orderBy(string $by, ?string $order = null): Statement {
    $this->orderBy = [$by, $order ? strtolower($order) : $order];
    return $this;
  }

  function limit(int $offsetOrCount, int $count = null): Statement {
    $this->limit = [$offsetOrCount, $count];
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

  function get(array|string $col = '*'): Collection {
    $r = [];
    $s = $this->stmt ?? $this->statement($col);
    if($s && $s->rowCount()) {
      $intFields = $this->class::$intFields;
      $floatFields = $this->class::$floatFields;
      foreach($s->fetchAll() as $d) {
        if($intFields)
          foreach($intFields as $k)
            if(isset($d->$k))
              $d->$k = intval($d->$k);
        if($floatFields)
          foreach($floatFields as $k)
            if(isset($d->$k))
              $d->$k = floatval($d->$k);
        $r[] = new $this->class($d);
      }
    }
    return new Collection($r);
  }

  function entries($col = '*') {
    $s = $this->stmt ?? $this->statement($col);
    if($s && $s->rowCount()) {
      $intFields = $this->class::$intFields;
      $floatFields = $this->class::$floatFields;
      while($d = $s->fetch()) {
        if($intFields) {
          foreach($intFields as $k)
            if(isset($d->$k))
              $d->$k = intval($d->$k);
        }
        if($floatFields) {
          foreach($floatFields as $k)
            if(isset($d->$k))
              $d->$k = floatval($d->$k);
        }
        yield new $this->class($d);
      }
    }
  }

  function first(array|string $col = '*') {
    if(($s = $this->stmt ?? $this->statement($col)) && ($r = $s->fetch())) {
      if($intFields = $this->class::$intFields) {
        foreach($intFields as $k)
          if(isset($r->$k))
            $r->$k = intval($r->$k);
      }
      return new $this->class($r);
    }
    return null;
  }

  function count(): int {
    $db = $this->class::getDatabase();
    $sql = 'select count(*) c from `' . $this->class::getTableName() . '`';
    $this->addCondition($sql);
    if($this->condition) {
      $s = $db->prepare($sql);
      $s->execute($this->params);
    } else
      $s = $db->query($sql);
    $this->class::putDatabase($db);
    $rows = $s->fetch();
    if(isset($rows->c))
      return $rows->c;
    else
      return -1;
  }
}

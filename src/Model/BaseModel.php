<?php
namespace Oblind\Model;

use ReflectionClass;
use Swoole\Database\PDOConfig;
use Swoole\Database\PDOPool;
use Swoole\Database\PDOProxy;
use Oblind\Application;

class BaseModel extends Decachable implements \JsonSerializable, \IteratorAggregate {
  protected static PDOPool $dbPool;
  protected static array $tableNames = [];
  protected static string $primary = 'id';
  protected static bool $autoIncrease = true;
  protected static PDOConfig $config;
  protected static int $returnRawCount = 0;
  protected static ?array $hiddenFields = null;
  protected static ?array $jsonFields = null;
  protected static ?array $cacheFields = null;
  protected static ?array $cacheClasses = null;
  protected static ?array $cacheItemClasses = null;
  public static ?array $intFields = null;
  public static ?array $floatFields = null;

  protected bool $_create = false;
  protected $_data;
  protected array $_col = [];

  function __construct($data = null, $parent = null, $parentKey = null) {
    $this->_parent = $parent;
    $this->_parentKey = $parentKey;
    if($data) {
      if(static::$jsonFields)
        foreach(static::$jsonFields as $f)
          if(property_exists($data, $f) && is_string($data->$f))
            $data->$f = json_decode($data->$f);
      $this->_data = $data;
    } else {
      $this->_data = new \stdClass;
      $this->_create = true;
    }
  }

  static function initDatabasePool() {
    $cfg = Application::config()['db'];
    static::$config = (new PDOConfig)->withHost($cfg['host'])
    ->withPort($cfg['port'])->withDbname($cfg['database'])
    ->withUsername($cfg['user'])->withPassword($cfg['password'])
    ->withOptions([
      //持久连接
      \PDO::ATTR_PERSISTENT => true,
      //返回对象, FETCH_ASSOC: 返回数组
      \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
      //不对数字转化字符串
      //\PDO::ATTR_EMULATE_PREPARES => false,
      //抛出异常
      \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
    ]);
    static::$dbPool = new PDOPool(static::$config);
  }

  static function delay(int $c): bool {
    return Statement::delay($c);
  }

  static function getDatabase(): PDOProxy {
    $c = 0;
    _getdb:
    try {
      $r = static::$dbPool->get();
      return $r;
    } catch(\Throwable $e) {
      if($c++ < 100) {
        usleep(50000);
        goto _getdb;
      } else
        throw $e;
    }
    /*if(static::$dbPool->count())
      return static::$dbPool->pop();
    $cfg = static::$config;
    $c = 0;
    _getdb:
    try {
      $db = new \PDO(
        "{$cfg['type']}:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']}", $cfg['user'], $cfg['password'], [
          //持久连接
          \PDO::ATTR_PERSISTENT => true,
          //返回对象, FETCH_ASSOC: 返回数组
          \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
          //不对数字转化字符串
          \PDO::ATTR_EMULATE_PREPARES => false,
          //抛出异常
          \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ]
      );
      return $db;
    } catch(\Throwable $e) {
      if($c++ < 3)
        goto _getdb;
      else
        throw $e;
    }*/
  }

  static function putDatabase(PDOProxy $db) {
    static::$dbPool->put($db);
  }

  function &__get(string $k) {
    return $this->_data->$k;
  }

  function __set(string $k, $v) {
    if((!static::$cacheFields || !in_array($k, static::$cacheFields))
      && (!property_exists($this->_data, $k) || $this->_data->$k !== $v || is_array($v) || $v instanceof \stdClass)
      && !in_array($k, $this->_col))
      $this->_col[] = $k;
    $this->_data->$k = $v;
    if(!$this->_decaching && $this->_parent)
      $this->_parent->onChange($this->_parentKey);
  }

  function __isset(string $k) {
    return isset($this->_data->$k);
  }

  function __unset(string $k) {
    unset($this->_data->$k);
  }

  function __tostring() {
    return json_encode($this->jsonSerialize(), JSON_UNESCAPED_UNICODE);
  }

  static function where($condition, ?array $params = null): Statement {
    return (new Statement(get_called_class()))->where($condition, $params);
  }

  static function orderBy(string $by, bool $order = false): Statement {
    return (new Statement(get_called_class()))->orderBy($by, $order);
  }

  static function limit(int $offset, int $rows = null): Statement {
    return (new Statement(get_called_class()))->limit($offset, $rows);
  }

  static function find($primary, $col = '*') {
    return (new Statement(get_called_class()))->find($primary, $col);
  }

  static function get($col = '*'): Collection {
    return (new Statement(get_called_class()))->get($col);
  }

  static function first($col = '*'): ?BaseModel {
    return (new Statement(get_called_class()))->first($col);
  }

  static function count(): int {
    return (new Statement(get_called_class()))->count();
  }

  static function setReturnRaw($raw) {
    if($raw)
      static::$returnRawCount++;
    elseif(static::$returnRawCount)
      static::$returnRawCount--;
  }

  static function getPrimary() {
    return static::$primary;
  }

  static function exec(string $sql): int {
    $c = 0;
    _getdb:
    try {
      $db = static::getDatabase();
      $r = $db->exec($sql);
    } catch(\Throwable $e) {
      if(static::delay($c++))
        goto _getdb;
      else
        throw $e;
    }
    static::putDatabase($db);
    return $r;
  }

  static function query(string $sql): \PDOStatement {
    $c = 0;
    _getdb:
    try {
      $db = static::getDatabase();
      $r = $db->query($sql);
    } catch(\Throwable $e) {
      if(static::delay($c++))
        goto _getdb;
      else
        throw $e;
    }
    static::putDatabase($db);
    return $r;
  }

  static function resetAutoIncrement() {
    return static::exec('alter table ' . static::getTableName() . ' auto_increment=1');
  }

  static function setCache(string $field, bool $cached) {
    if($cached) {
      if(!in_array($field, static::$cacheFields))
        static::$cacheFields[] = $field;
    } elseif(($i = array_search($field, static::$cacheFields)) !== false)
      array_splice(static::$cacheFields, $i, 1);
  }

  static function addHiddenField($hidden) {
    if(is_array($hidden))
      static::$hiddenFields = array_merge(static::$hiddenFields, $hidden);
    else
      static::$hiddenFields[] = $hidden;
  }

  protected static function hideFields($d, string $class) {
    foreach($class::$hiddenFields as $f)
      if(property_exists($d, $f))
        unset($d->$f);
    if($class::$cacheFields)
      foreach($class::$cacheFields as $f)
        if(isset($d->$f))
          if(isset($class::$cacheClasses[$f])) {
            if($d->$f instanceof \stdClass)
              static::hideFields($d->$f, $class::$cacheClasses[$f]);
          } elseif(is_array($d->$f) && isset($class::$cacheItemClasses[$f]))
            foreach($d->$f as $v)
              static::hideFields($v, $class::$cacheItemClasses[$f]);
  }

  function decache() {
    if(static::$cacheFields) {
      $this->_decaching = true;
      foreach(static::$cacheFields as $f)
        if(isset($this->$f))
          if(isset(static::$cacheClasses[$f])) {
            $this->$f = new static::$cacheClasses[$f]($this->$f, $this, $f);
            if($this->$f instanceof Decachable)
              $this->$f->decache();
          } elseif(isset(static::$cacheItemClasses[$f])) {
            $this->$f = new Collection($this->$f, $this, $f, static::$cacheItemClasses[$f]);
            $this->$f->decache();
          }
      $this->_decaching = false;
    }
  }

  //implement JsonSerializable
  function jsonSerialize() {
    if(static::$hiddenFields && !static::$returnRawCount) {
      $r = (object)array_diff_key(get_object_vars($this->_data), array_flip(static::$hiddenFields));
      static::hideFields($r, get_called_class());
      return $r;
    } else
      return $this->_data;
  }

  //implement IteratorAggregate
  function getIterator(): \Traversable {
    return new \ArrayIterator($this->_data);
  }

  static function setTableName(string $class, string $tableName = null) {
    if(!$tableName) {
      $tableName = $class;
      $class = get_called_class();
    }
    static::$tableNames[$class] = $tableName;
  }

  static function getTableName(): string {
    $cn = get_called_class();
    if(!isset(static::$tableNames[$cn])) {
      $s = $cn;
      if($c = strrpos($s, '\\')) $c++;
      else $c = 0;
      $l = substr($s, -5) == 'Model' ? strlen($s) - 5 : 0;
      if($c || $l)
        $s = substr($s, $c, $l - $c);
      $s[0] = strtolower($s[0]);
      /*for($i = 1, $l = strlen($s); $i < $l; $i++) {
        if(($c = ord($s[$i])) >= 65 && $c <= 90) {
          $s = substr_replace($s, '_'.chr($c + 32), $i, 1);
          $i++; $l++;
        }
      }*/
      $l = strlen($s) - 1;
      $c = $s[$l];
      $cs = substr($s, -2);
      if($c == 's' || $c == 'x' || $cs == 'ch' || $cs == 'sh')
        static::$tableNames[$cn] = $s . 'es';
      elseif($c == 'y' && !strpos('^aeiou', $s[$l - 1])) {
        $s[$l] = 'i';
        static::$tableNames[$cn] = $s . 'es';
      } else
        static::$tableNames[$cn] = $s . 's';
    }
    return static::$tableNames[$cn];
  }

  function save() {
    if($this->_col) {
      foreach($this->_col as $col)
        $v[] = static::$jsonFields && in_array($col, static::$jsonFields) && $this->$col !== null
          ? json_encode($this->$col, JSON_UNESCAPED_UNICODE) : $this->$col;
      $c = 0;
      _getdb:
      try {
        $db = static::getDatabase();
        $cs = [];
        if($this->_create) {
          foreach($this->_col as $col)
            $cs[] = "`$col`";
          $s = $db->prepare('insert into ' . static::getTableName() . ' (' . implode(', ', $cs) . ') values (' . implode(', ', array_fill(0, count($this->_col), '?')) . ')');
          if(!$s->execute($v))
            goto _getdb;
          if(static::$autoIncrease)
            $this->{static::$primary} = intval($db->lastInsertId(static::$primary));
          $this->_create = false;
        } else {
          $k = [];
          foreach($this->_col as $col)
            $k[] = "`$col`=?";
          $sql = 'update ' . static::getTableName() . ' set ' . implode(', ', $k) . ' where ' . static::$primary . '=' . $this->{static::$primary};
          $s = $db->prepare($sql);
          //$s = $db->prepare('update ' . static::getTableName() . ' set ' . implode(', ', $k) . ' where ' . static::$primary . '=' . $this->{static::$primary});
          if(!$s->execute($v))
            goto _getdb;
        }
      } catch(\Throwable $e) {
        if(static::delay($c++))
          goto _getdb;
        else
          throw $e;
      }
      $this->_col = [];
      static::putDatabase($db);
    }
    parent::save();
  }

  function delete() {
    $c = 0;
    _getdb:
    try {
      $db = static::getDatabase();
      $r = $db->exec('delete from ' . static::getTableName() . ' where ' . static::$primary . '=' . $this->{static::$primary});
    } catch(\Throwable $e) {
      if(static::delay($c++))
        goto _getdb;
      else
        throw $e;
    }
    static::putDatabase($db);
    return $r;
  }
}

BaseModel::initDatabasePool();

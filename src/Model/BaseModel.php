<?php
namespace Oblind\Model;

use PDO;
use PDOStatement;
use JsonSerializable;
use Oblind\Application;

class BaseModel extends Decachable implements JsonSerializable {
  /**@var \SplQueue */
  protected static $dbPool;
  /**@var array */
  protected static $tableNames = [];
  /**@var string */
  protected static $primary = 'id';
  /**@var int */
  protected static $returnRawCount = 0;
  /**@var array */
  protected static $hidden;
  /**@var array */
  protected static $jsonFields;
  /**@var array */
  protected static $cacheFields;
  /**@var array */
  protected static $cacheClasses;
  /**@var array */
  protected static $cacheItemClasses;
  /**@var bool */
  protected $_create;
  /**@var \stdClass */
  protected $_data;
  /**@var array */
  protected $_col = [];

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

  static function init() {
    static::$dbPool = new \SplQueue;
  }

  static function getDatabase(): PDO {
    if(static::$dbPool->count())
      return static::$dbPool->pop();
    $cfg = Application::config()->db;
    _getdb:
    try {
      $db = new PDO(
        "$cfg->type:host=$cfg->host;port=$cfg->port;dbname=$cfg->database", $cfg->user, $cfg->password, [
          //持久连接
          PDO::ATTR_PERSISTENT => true,
          //返回对象, FETCH_ASSOC: 返回数组
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
          //不对数字转化字符串
          PDO::ATTR_EMULATE_PREPARES => false,
          //抛出异常
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
      );
    } catch(\Throwable $e) {
      if($e->getCode() == 2006)
        goto _getdb;
    }
    return $db;
  }

  static function putDatabase(PDO $db) {
    static::$dbPool->push($db);
  }

  function &__get($k) {
    return $this->_data->$k;
  }

  function __set($k, $v) {
    if((!static::$cacheFields || !in_array($k, static::$cacheFields))
      && (!property_exists($this->_data, $k) || $this->_data->$k !== $v || is_array($v) || $v instanceof \stdClass)
      && !in_array($k, $this->_col))
      $this->_col[] = $k;
    $this->_data->$k = $v;
    if(!$this->_decaching && $this->_parent)
      $this->_parent->onChange($this->_parentKey);
  }

  function __isset($k) {
    return isset($this->_data->$k);
  }

  function __unset($k) {
    unset($this->_data->$k);
  }

  function __tostring() {
    return json_encode($this->jsonSerialize(), JSON_UNESCAPED_UNICODE);
  }

  static function __callStatic($method, $param) {
    return (new Statement(get_called_class()))->$method(...$param);
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

  static function exec($sql): int {
    _getdb:
    try {
      $db = static::getDatabase();
      $r = $db->exec($sql);
    } catch(\Throwable $e) {
      if($e->getCode() == 2006)
        goto _getdb;
    }
    static::putDatabase($db);
    return $r;
  }

  static function query($sql): PDOStatement {
    _getdb:
    try {
      $db = static::getDatabase();
      $r = $db->query($sql);
    } catch(\Throwable $e) {
      if($e->getCode() == 2006)
        goto _getdb;
    }
    static::putDatabase($db);
    return $r;
  }

  static function resetAutoIncrement() {
    return static::exec('alter table ' . static::getTableName() . ' auto_increment=1');
  }

  static function setCache($field, $cached) {
    if($cached) {
      if(!in_array($field, static::$cacheFields))
        static::$cacheFields[] = $field;
    } elseif(($i = array_search($field, static::$cacheFields)) !== false)
      array_splice(static::$cacheFields, $i, 1);
  }

  static function addHidden($hidden) {
    if(is_array($hidden))
      static::$hidden = array_merge(static::$hidden, $hidden);
    else
      static::$hidden[] = $hidden;
  }

  protected static function hideFields($d, $class) {
    foreach($class::$hidden as $f)
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

  function jsonSerialize() {
    if(static::$hidden && !static::$returnRawCount) {
      $r = (object)array_diff_key(get_object_vars($this->_data), array_flip(static::$hidden));
      static::hideFields($r, get_called_class());
      return $r;
    } else
      return $this->_data;
  }

  static function getTableName() {
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
      foreach($this->_col as $c)
        $v[] = static::$jsonFields && in_array($c, static::$jsonFields) ? json_encode($this->$c, JSON_UNESCAPED_UNICODE) : $this->$c;
      _getdb:
      try {
        $db = static::getDatabase();
        $cs = [];
        if($this->_create) {
          foreach($this->_col as $c)
            $cs[] = "`$c`";
          $s = $db->prepare('insert into ' . static::getTableName() . ' (' . implode(', ', $cs) . ') values (' . implode(', ', array_fill(0, count($this->_col), '?')) . ')');
          $s->execute($v);
          if($primary = $db->lastInsertId())
            $this->{static::$primary} = $primary;
          $this->_create = false;
        } else {
          $k = [];
          foreach($this->_col as $c)
            $k[] = "`$c`=?";
          $sql = 'update ' . static::getTableName() . ' set ' . implode(', ', $k) . ' where ' . static::$primary . '=' . $this->{static::$primary};
          $s = $db->prepare($sql);
          //$s = $db->prepare('update ' . static::getTableName() . ' set ' . implode(', ', $k) . ' where ' . static::$primary . '=' . $this->{static::$primary});
          $r = $s->execute($v);
        }
      } catch(\Throwable $e) {
        if($e->getCode() == 2006)
          goto _getdb;
      }
      $this->_col = [];
      static::putDatabase($db);
    }
    parent::save();
  }

  function delete() {
    _getdb:
    try {
      $db = static::getDatabase();
      $r = $db->exec('delete from ' . static::getTableName() . ' where ' . static::$primary . '=' . $this->{static::$primary});
    } catch(\Throwable $e) {
      if($e->getCode() == 2006)
        goto _getdb;
    }
    static::putDatabase($db);
    return $r;
  }
}

BaseModel::init();

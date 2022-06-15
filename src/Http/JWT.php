<?php
namespace Oblind\Http;
use Oblind;
use stdClass;

class JWT {
  static function encode($o, string $password): string {
    //格式: {payload} . password
    return Oblind\base64url_encode(json_encode($o) . '.' . crypt($password, md5(rand(0x7fff, 0xffff))));
  }

  static function decode(string $s, string $password): ?stdClass {
    $s = Oblind\base64url_decode($s);
    if($p = strpos($s, '.')) {
      $salt = substr($s, $p + 1);
      if(crypt($password, $salt) == $salt)
        return json_decode(substr($s, 0, $p));
    }
    return null;
  }
}

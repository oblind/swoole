<?php
namespace Oblind\Http;

class JWT {
  static function encode($o, string $password): string {
    //格式: {payload} . password
    return base64url_encode(json_encode($o) . '.' . crypt($password, md5(rand(0x7fff, 0xffff))));
  }

  static function decode(string $s, $password) {
    $s = base64url_decode($s);
    if($p = strpos($s, '.')) {
      $t = substr($s, $p + 1);
      if(crypt($password, $t) == $t)
        return json_decode(substr($s, 0, $p));
    }
  }
}

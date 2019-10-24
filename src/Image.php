<?php
namespace Oblind;

use Oblind\Language;
use Swoole\MySQL\Exception;

class Image {

  static function resize(string $f, int $width, int $height, &$ext): bool {
    $exts = [1 => 'gif', 2 => 'jpg', 3 => 'png', 6 => 'bmp'];
    [$w0, $h0, $t] = getimagesize($f);
    if($t) {
      $ext = $exts[$t];
      if($w0 > $width || $h0 > $height) {
        switch($t) {
        case 1: //gif
          $src = imagecreatefromgif($f);
          break;
        case 2: //jpg
          $src = imagecreatefromjpeg($f);
          break;
        case 3: //png
          $src = imagecreatefrompng($f);
          break;
        case 6: //bmp
          $src = imagecreatefrombmp($f);
          break;
        default:
          throw new Exception(_('unsupported image format'));
        }
        $w = $w0;
        $h = $h0;
        if($w / $h > $width / $height) {
          if($w > $width) {
            $h = $h * $width / $w;
            $w = $width;
          }
        } elseif($h > $height) {
          $w = $w * $height / $h;
          $h = $height;
        }
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagecopyresampled($img, $src, 0, 0, 0, 0, $w, $h, $w0, $h0);
        switch($t) {
        case 1:
          imagegif($img, $f);
          break;
        case 2:
          imagejpeg($img, $f);
          break;
        case 3:
          imagepng($img, $f);
          break;
        case 6:
          imagebmp($img, $f);
        }
        imagedestroy($img);
        return true;
      }
    }
    return false;
  }
}

Language::addTranslation(['unsupported image format'], 'zh-cn');

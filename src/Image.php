<?php
namespace Oblind;

use Oblind\Language;
use Swoole\MySQL\Exception;

class Image {

  static function resize(string $f, int $width, int $height, &$ext): bool {
    $exts = [IMAGETYPE_GIF => 'gif', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_BMP => 'bmp'];
    [$w0, $h0, $t] = getimagesize($f);
    if($t) {
      $ext = $exts[$t];
      if($w0 > $width || $h0 > $height) {
        switch($t) {
        case IMAGETYPE_GIF: //gif
          $src = imagecreatefromgif($f);
          break;
        case IMAGETYPE_JPEG://jpg
          $src = imagecreatefromjpeg($f);
          break;
        case IMAGETYPE_PNG: //png
          $src = imagecreatefrompng($f);
          break;
        case IMAGETYPE_BMP: //bmp
          $src = imagecreatefrombmp($f);
          break;
        default:
          throw new \Exception(_('unsupported image format'));
        }
        $w = $w0;
        $h = $h0;
        if($w / $h > $width / $height) {
          if($w > $width) {
            $h = round($h * $width / $w);
            $w = $width;
          }
        } elseif($h > $height) {
          $w = round($w * $height / $h);
          $h = $height;
        }
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagecopyresampled($img, $src, 0, 0, 0, 0, $w, $h, $w0, $h0);
        switch($t) {
        case IMAGETYPE_GIF:
          imagegif($img, $f);
          break;
        case IMAGETYPE_JPEG:
          imagejpeg($img, $f);
          break;
        case IMAGETYPE_PNG:
          imagepng($img, $f);
          break;
        case IMAGETYPE_BMP:
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

<?php
/***
 *
 * PHP image preview/ thumbnails maker
 * Muayyad Saleh Alsadi <alsadi@gmail.com>
 * this file is in public domain
 * 
 * the latest version can be obtained from 
 * http://git.ojuba.org/cgit/php_img_preview/snapshot/php_img_preview-master.tar.bz2
 ***/

include_once(dirname(__FILE__) . "/settings.php");

function error($msg, $code=404) {
  header('Content-Type: text/plain; charset=UTF-8', TRUE, $code);
  die($msg);
}

function save_as_jpg($src, $dst, $w, $h) {
  if ($w>Conf::$max_width || $h>Conf::$max_height) return error('too big');
  if (isset(Conf::$sizes) && (!isset(Conf::$sizes[$w.'x'.$h]) || !Conf::$sizes[$w.'x'.$h] )) error('not allowed size');
  @mkdir(dirname($dst), 0777, true);
  if (!is_dir(dirname($dst))) error('could not create directory');
  list($w_src, $h_src, $type)=getimagesize($src);
  if ($type!=IMAGETYPE_GIF && $type!=IMAGETYPE_JPEG && $type!=IMAGETYPE_PNG) error('unsupported image type');
  $ratio = $w_src/$h_src;
  if ($w/$h > $ratio) $w = floor($h*$ratio); else $h = floor($w/$ratio);
  switch ($type)
  {
    case IMAGETYPE_GIF: $img_src = imagecreatefromgif($src); break;
    case IMAGETYPE_JPEG: $img_src = imagecreatefromjpeg($src); break;
    case IMAGETYPE_PNG: $img_src = imagecreatefrompng($src); break;
  }
  $img_dst = imagecreatetruecolor($w, $h);  //  resample
  imagecopyresampled($img_dst, $img_src, 0, 0, 0, 0, $w, $h, $w_src, $h_src);
  //  save new image
  
  if (!imagejpeg($img_dst, $dst)) error("could not save image");
  imagedestroy($img_src);
  imagedestroy($img_dst);
}

function lock_aquire($lock_fn, $t=LOCK_EX) {
  $fh = fopen($lock_fn, 'w+');
  flock($fh, $t);
  return $fh;
}

function lock_release($l, $lock_fn) {
  flock($l, LOCK_UN);
  fclose($l);
  @unlink($lock_fn);
  return 0;
}

$fn=substr(strstr($_SERVER['PHP_SELF'], 'index.php'),10);
$locks_d=dirname(__FILE__).'/locks/';
@mkdir(dirname($locks_d), 0777, true);
if (!is_dir(dirname($locks_d))) die("could not create directory [$lock_d]\n");
$lock_fn=$locks_d.md5($fn);
if (!$fn) error('missing file name');;
if (strstr($fn, '..')) error('double dots not allowed');
$abs_dst=dirname(__FILE__).'/'.$fn;

$a=array();
if (!preg_match('/^([0-9]+)x([0-9]+)\/(.*)\.jpg/',$fn,$a)) error('wrong file name syntax');
$w=$a[1];
$h=$a[2];
if (Conf::$orig[0]!='/') Conf::$orig=dirname(__FILE__).'/'.Conf::$orig.'/';
$src=Conf::$orig.'/'.$a[3];
if (!file_exists($src)) error('original file does not exists!');

$l=lock_aquire($lock_fn);
if (!file_exists($abs_dst)) {
  save_as_jpg($src, $abs_dst."~", $w, $h);
  rename($abs_dst."~", $abs_dst);
}
header('Content-Type: image/jpeg', TRUE, 200);
print file_get_contents($abs_dst);
lock_release($l, $lock_fn);


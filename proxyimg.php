<?php

//setup
$cache = 1; //1 - ON, 0 - OFF
$deleting = 0; //1 - ON, 0 - OFF
$debug = 0; //1 - ON, 0 - OFF

if($debug) error_reporting(E_ALL);
$url = $_SERVER['QUERY_STRING'];
if(empty($url)) $url = $_SERVER['REDIRECT_QUERY_STRING'];
if($debug) echo "ProxyUrl: '$url'<br>\n";
if($url == '') { 
  if($debug) echo "ERROR: Empty url.<br>\n";
  header('HTTP/1.0 404 Not Found'); die(); 
}
$mustdel = substr($url, -6) === "&del=1";
if($mustdel) $url = substr($url,0,strlen($mustdel)-7);
$db = new SQLite3('data.db');
$statement = $db->prepare('SELECT real_url FROM proxy_image where my_url = :my_url');
$statement->bindValue(':my_url', "/".$url);
$result = $statement->execute();
$row = $result->fetchArray();
if($row == false) { 
  if($debug) echo "ERROR: Not found in data.db<br>\n";
  header('HTTP/1.0 404 Not Found'); die(); 
}
//you can delete images using : http://door.ru/pics/images.png?del=1
if($mustdel && $deleting) {
  $statement = $db->prepare('DELETE FROM proxy_image where my_url = :my_url');
  $statement->bindValue(':my_url', "/".$url);
  $statement->execute();
  header('HTTP/1.0 404 Not Found'); die();
}
$url = $row['real_url'];
if($debug) echo "RealUrl: '$url'<br>\n";
if($url == '') { header('HTTP/1.0 404 Not Found'); die(); }

$mimeType = 'image/jpeg';
if(strstr($url, '.png')!=FALSE)$mimeType = 'image/png';
if(strstr($url, '.gif')!=FALSE)$mimeType = 'image/gif';
if($debug) echo "MimeType: '$mimeType'<br>\n";

$cachefile = md5($url);
if(!file_exists($cachefile))
{
  $curl = curl_init($url);
  @curl_setopt($curl, CURLOPT_TIMEOUT, 60);
  @curl_setopt($curl, CURLOPT_REFERER, $url);
  @curl_setopt($curl, CURLOPT_HEADER, 0);
  @curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); 
  @curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); 
  @curl_setopt($curl, CURLOPT_BINARYTRANSFER, 1);
  @curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); 
  @curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
  @curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)"); 
  if ($cache) curl_setopt($curl, CURLOPT_FILE, fopen($cachefile,"wb"));
  $resp = curl_exec($curl);
  curl_close($curl);
  header("Content-Type: $mimeType");
  if ($cache)
  {
    if($debug) echo "Save to cache<br>\n";
    //uniq_image($cachefile, $mimeType); //uncomment to mirror&rotate
    readfile($cachefile);
  }
  if($debug) echo "LoadedImgSize:".strlen($resp)."<br>\n";
  echo $resp;
}
else
{
	if($debug) echo "Read from cache: $cachefile<br>\n";
    header("Content-Type: $mimeType");
    readfile($cachefile);
}
function uniq_image($cachefile, $mimeType)
{
  if(!function_exists('imagecrop')){ 
    function imagecrop($src, array $rect)
    {
      $dest = imagecreatetruecolor($rect['width'], $rect['height']);
      imagecopy($dest,$src,0,0,$rect['x'],$rect['y'],$rect['width'],$rect['height']);
      return $dest;
    }
  }
  if(!function_exists('imageflip')) {
    function imageflip($image, $mode) {
      $max_x = imagesx($image) - 1;
      $half_x = $max_x / 2;
      $sy = imagesy($image);
      $temp_image = imageistruecolor($image)? imagecreatetruecolor(1, $sy): imagecreate(1, $sy);
      for ($x = 0; $x < $half_x; ++$x) {
        imagecopy($temp_image, $image, 0, 0, $x, 0, 1, $sy);
        imagecopy($image, $image, $x, 0, $max_x - $x, 0, 1, $sy);
        imagecopy($image, $temp_image, $max_x - $x, 0, 0, 0, 1, $sy);
      }
      imagedestroy($temp_image);
    }
  }
  //create image
  $img = imagecreatefromstring(file_get_contents($cachefile));
  //size
  $sw = imagesx($img);
  $sh = imagesy($img);

  //rotate angle
  $degrees = -10;
  $bgcolor = imagecolorallocate($img, 255, 255, 255); //white bg
  $img = imagerotate($img, $degrees, $bgcolor);
  $rw = imagesx($img);
  $rh = imagesy($img);

  //crop
  $img = imagecrop($img, array(
    'x' => $rw * (1 - $sw / $rw) * 0.5,
    'y' => $rh * (1 - $sh / $rh) * 0.5,
    'width' => $sw,
    'height'=> $sh
  ));
  
  //mirror
  imageflip($img, 1); //1 = IMG_FLIP_HORIZONTAL

  //save
  switch ($mimeType) {
        case 'image/png':
            imagepng($img, $cachefile);
            break;
        case 'image/gif':
            imagegif($img, $cachefile);
            break;
        default:
            imagejpeg($img, $cachefile);
            break;
    }  
  
  //close
  imagedestroy($img);
}
?>
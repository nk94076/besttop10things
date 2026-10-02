<?php
declare(strict_types=1);
// Image pipeline (GD): WebP conversion of uploads, plus generated share images for every post:
//  - social: 1200×630 JPG for Open Graph / X / Google Discover
//  - pin:    1000×1500 JPG for Pinterest (photo on top, title panel below)
// Generated files live in /uploads/og/ and are rebuilt only when the title, image or design version changes.

const IMG_VERSION = '1';
const IMG_FONT_DIR = __DIR__ . '/../fonts';

function publicDir(): string {
 $d=(string)($_SERVER['DOCUMENT_ROOT']??'');
 return $d!==''&&is_file($d.'/index.php')?$d:ROOT.'/../www.besttop10things.com';
}

// Open a local raster image (/uploads/… or /assets/…); remote URLs and SVGs are not read.
function gdOpen(string $url): ?GdImage {
 if(!preg_match('~^/(uploads|assets)/[A-Za-z0-9_./-]+\.(jpe?g|png|webp)$~i',$url)||str_contains($url,'..'))return null;
 $file=publicDir().$url;
 if(!is_file($file)||filesize($file)>25*1024*1024)return null;
 $info=@getimagesize($file);if(!$info||$info[0]*$info[1]>40000000)return null;
 $img=match($info['mime']){'image/jpeg'=>@imagecreatefromjpeg($file),'image/png'=>@imagecreatefrompng($file),'image/webp'=>@imagecreatefromwebp($file),default=>false};
 return $img?:null;
}

// Copy $src into a w×h area of $dst, cropped to fill (like CSS object-fit: cover).
function gdCover(GdImage $dst, GdImage $src, int $x, int $y, int $w, int $h): void {
 $sw=imagesx($src);$sh=imagesy($src);$scale=max($w/$sw,$h/$sh);
 $cw=(int)round($w/$scale);$ch=(int)round($h/$scale);
 imagecopyresampled($dst,$src,$x,$y,(int)(($sw-$cw)/2),(int)(($sh-$ch)/2),$w,$h,$cw,$ch);
}

// Word-wrap $text to $maxWidth, shrinking the font until it fits in $maxLines.
function gdWrap(string $text, string $font, float $size, int $maxWidth, int $maxLines, float $minSize): array {
 for(;$size>=$minSize;$size-=2){
  $lines=[];$line='';
  foreach(preg_split('/\s+/u',trim($text)) as $word){
   $try=$line===''?$word:"$line $word";
   $box=imagettfbbox($size,0,$font,$try);
   if($box[2]-$box[0]>$maxWidth&&$line!==''){$lines[]=$line;$line=$word;}else $line=$try;
  }
  if($line!=='')$lines[]=$line;
  if(count($lines)<=$maxLines)return [$size,$lines];
 }
 $lines=array_slice($lines,0,$maxLines);$lines[$maxLines-1]=rtrim(mb_substr($lines[$maxLines-1],0,max(1,mb_strlen($lines[$maxLines-1])-1)),' ,.;:').'…';
 return [$minSize,$lines];
}

function gdColor(GdImage $im, string $hex, int $alpha=0): int {
 $hex=ltrim($hex,'#');return imagecolorallocatealpha($im,hexdec(substr($hex,0,2)),hexdec(substr($hex,2,2)),hexdec(substr($hex,4,2)),$alpha);
}

// Brand gradient used when a post has no usable raster photo.
function gdBrandFill(GdImage $im, int $x, int $y, int $w, int $h): void {
 [$a,$b]=[[15,58,74],[13,138,140]];
 for($i=0;$i<$h;$i++){$t=$i/max(1,$h-1);imagefilledrectangle($im,$x,$y+$i,$x+$w-1,$y+$i,imagecolorallocate($im,(int)($a[0]+($b[0]-$a[0])*$t),(int)($a[1]+($b[1]-$a[1])*$t),(int)($a[2]+($b[2]-$a[2])*$t)));}
}

// Returns the public URL of the generated image for a post ($kind: 'social' or 'pin'), building it if needed.
function shareImage(array $post, string $kind): ?string {
 if(!function_exists('imagettftext')||!in_array($kind,['social','pin'],true))return null;
 $title=trim((string)$post['title']);$cat=(string)($post['category']??'');
 $key=substr(md5(IMG_VERSION.$kind.$title.$cat.$post['image'].(is_file(publicDir().$post['image'])?filemtime(publicDir().$post['image']):'')),0,12);
 $name=preg_replace('/[^a-z0-9-]/','',substr((string)$post['slug'],0,80))."-$kind-$key.jpg";
 $dir=publicDir().'/uploads/og';$url="/uploads/og/$name";
 if(is_file("$dir/$name"))return $url;
 if(!is_dir($dir)&&!@mkdir($dir,0755,true))return null;
 $serif=IMG_FONT_DIR.'/DejaVuSerif-Bold.ttf';$sans=IMG_FONT_DIR.'/DejaVuSans-Bold.ttf';
 $photo=gdOpen((string)$post['image']);
 $host=preg_replace('/^www\./','',(string)parse_url(siteBase(),PHP_URL_HOST));
 if($kind==='social'){
  [$W,$H]=[1200,630];$im=imagecreatetruecolor($W,$H);
  $photo?gdCover($im,$photo,0,0,$W,$H):gdBrandFill($im,0,0,$W,$H);
  // darken the lower part so the title is readable on any photo
  for($i=0;$i<$H;$i++){$a=(int)max(0,min(127,127-($i-$H*0.25)/($H*0.75)*105));imagefilledrectangle($im,0,$i,$W,$i,imagecolorallocatealpha($im,8,20,28,$a));}
  [$size,$lines]=gdWrap($title,$serif,46,$W-120,3,30);
  $y=$H-60-(count($lines)-1)*(int)($size*1.35);
  if($cat!==''){imagettftext($im,17,0,60,$y-(int)($size*1.25)-8,gdColor($im,'7fe0d6'),$sans,mb_strtoupper($cat));}
  foreach($lines as $i=>$l)imagettftext($im,$size,0,60,$y+$i*(int)($size*1.35),gdColor($im,'ffffff'),$serif,$l);
  imagettftext($im,16,0,$W-60-(imagettfbbox(16,0,$sans,$host)[2]),44,gdColor($im,'ffffff',20),$sans,$host);
 }else{
  [$W,$H]=[1000,1500];$im=imagecreatetruecolor($W,$H);
  $ph=900;$photo?gdCover($im,$photo,0,0,$W,$ph):gdBrandFill($im,0,0,$W,$ph);
  imagefilledrectangle($im,0,$ph,$W,$H,gdColor($im,'0f3a4a'));
  imagefilledrectangle($im,0,$ph,$W,$ph+10,gdColor($im,'12a08e'));
  [$size,$lines]=gdWrap($title,$serif,58,$W-140,5,34);
  $y=$ph+80+(int)$size;
  if($cat!==''){imagettftext($im,22,0,70,$ph+70,gdColor($im,'7fe0d6'),$sans,mb_strtoupper($cat));$y+=20;}
  foreach($lines as $i=>$l)imagettftext($im,$size,0,70,$y+$i*(int)($size*1.32),gdColor($im,'ffffff'),$serif,$l);
  imagettftext($im,24,0,70,$H-60,gdColor($im,'cfe9e6'),$sans,$host.'  ›  Read the guide');
 }
 $ok=imagejpeg($im,"$dir/$name.tmp",85)&&rename("$dir/$name.tmp","$dir/$name");
 // keep only the newest version of this post's image
 foreach(glob("$dir/".preg_replace('/[^a-z0-9-]/','',substr((string)$post['slug'],0,80))."-$kind-*.jpg")?:[] as $old)if(basename($old)!==$name)@unlink($old);
 return $ok?$url:null;
}

// Uploads: convert JPG/PNG to WebP (max 1920px wide). Returns the new file name, or the original on failure.
function toWebp(string $dir, string $name, int $maxWidth=1920): string {
 if(!function_exists('imagewebp'))return $name;
 $src=gdOpen('/uploads/'.$name)??(is_file("$dir/$name")?(@imagecreatefromstring((string)file_get_contents("$dir/$name"))?:null):null);
 if(!$src)return $name;
 $w=imagesx($src);$h=imagesy($src);
 if($w>$maxWidth){$nh=(int)round($h*$maxWidth/$w);$dst=imagecreatetruecolor($maxWidth,$nh);imagealphablending($dst,false);imagesavealpha($dst,true);imagecopyresampled($dst,$src,0,0,0,0,$maxWidth,$nh,$w,$h);$src=$dst;}
 else{imagepalettetotruecolor($src);imagealphablending($src,false);imagesavealpha($src,true);}
 $new=preg_replace('/\.(jpe?g|png|webp)$/i','',$name).'.webp';
 if(!imagewebp($src,"$dir/$new.tmp",82)||!rename("$dir/$new.tmp","$dir/$new"))return $name;
 return $new;
}

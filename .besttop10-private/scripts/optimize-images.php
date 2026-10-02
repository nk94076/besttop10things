<?php
declare(strict_types=1);
// Usage: php scripts/optimize-images.php [--dry-run] [--delete-originals] [--share]
// Converts existing JPG/PNG uploads to WebP (max 1920px wide) and updates every reference to them
// (featured images, share images, images inside articles, logo and default share image).
// Originals are kept unless --delete-originals is given, so old links and caches keep working.
// --share also pre-generates the 1200×630 social image and the Pinterest pin for every live post.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/bootstrap.php';
$opts=getopt('',['dry-run','delete-originals','share']);$dry=isset($opts['dry-run']);
$dir=publicDir().'/uploads';$saved=0;$n=0;

foreach(glob("$dir/*.{jpg,png,JPG,PNG}",GLOB_BRACE)?:[] as $file){
 $name=basename($file);if(!preg_match('/^[a-f0-9]{32}\.(jpg|png)$/i',$name))continue;
 $old='/uploads/'.$name;$before=filesize($file);
 if($dry){printf("would convert %s (%d KB)\n",$name,$before/1024);continue;}
 $webp=toWebp($dir,$name);if($webp===$name){echo "skipped $name (could not convert)\n";continue;}
 $new='/uploads/'.$webp;$after=filesize("$dir/$webp");$saved+=$before-$after;$n++;
 db()->beginTransaction();
 run('UPDATE reviews SET image=? WHERE image=?',[$new,$old]);
 run('UPDATE reviews SET og_image=? WHERE og_image=?',[$new,$old]);
 run('UPDATE reviews SET body=replace(body,?,?) WHERE instr(body,?)>0',[$old,$new,$old]);
 run("UPDATE settings SET value=? WHERE key IN ('logo','og_image') AND value=?",[$new,$old]);
 db()->commit();
 if(isset($opts['delete-originals']))@unlink($file);
 printf("%s → %s  %d KB → %d KB\n",$name,$webp,$before/1024,$after/1024);
}
echo $dry?"Dry run: nothing changed.\n":"$n image".($n===1?'':'s')." converted, ".round($saved/1048576,1)." MB saved.\n";

if(isset($opts['share'])&&!$dry){
 $k=0;foreach(query('SELECT r.*,c.name AS category FROM reviews r JOIN categories c ON c.id=r.category_id WHERE '.live()) as $p){if(shareImage($p,'social')&&shareImage($p,'pin'))$k++;}
 echo "Share images ready for $k posts.\n";
}

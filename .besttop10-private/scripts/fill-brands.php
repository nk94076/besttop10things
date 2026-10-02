<?php
declare(strict_types=1);
// Usage: php scripts/fill-brands.php [file.json]
// Fills brand, brand_about and cta_url from the article data file for posts
// that do not have a brand yet. Other fields and edited posts are left alone.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/bootstrap.php';
$file=$argv[1]??ROOT.'/data/articles.json';
$items=json_decode((string)@file_get_contents($file),true);
if(!is_array($items)){fwrite(STDERR,"Cannot read $file\n");exit(1);}
$n=0;
foreach($items as $a){
 if(empty($a['brand']))continue;
 $q=db()->prepare("UPDATE reviews SET brand=?,brand_about=?,cta_url=? WHERE slug=? AND brand=''");
 $q->execute([$a['brand'],$a['brand_about']??'',$a['cta_url']??'',slug($a['slug']??$a['title'])]);
 $n+=$q->rowCount();
}
echo "Filled brand details for $n post".($n===1?'':'s').".\n";

<?php
declare(strict_types=1);
// Usage: php scripts/import-articles.php [file.json] [--hide-samples]
// Imports articles (title, category, image, excerpt, body). Existing slugs are updated, so it is safe to re-run.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/bootstrap.php';
$args=array_slice($argv,1);
$hideSamples=in_array('--hide-samples',$args,true);
$file=array_values(array_filter($args,fn($a)=>$a!=='--hide-samples'))[0]??ROOT.'/data/articles.json';
$items=json_decode((string)@file_get_contents($file),true);
if(!is_array($items)){fwrite(STDERR,"Cannot read $file\n");exit(1);}
$db=db();$db->beginTransaction();
try{
 $added=$updated=0;
 foreach($items as $a){
  foreach(['title','category','body'] as $k) if(trim((string)($a[$k]??''))==='') throw new RuntimeException("Missing $k in: ".($a['title']??'?'));
  $catSlug=slug($a['category']);
  $cat=query('SELECT id FROM categories WHERE slug=?',[$catSlug])[0]['id']??null;
  if(!$cat){run('INSERT INTO categories(name,slug,icon) VALUES (?,?,?)',[$a['category'],$catSlug,'grid']);$cat=(int)$db->lastInsertId();}
  $image=(string)($a['image']??'/assets/hero.jpg');
  if(!safeImage($image)) throw new RuntimeException("Unsafe image for: {$a['title']}");
  $slug=slug($a['slug']??$a['title']);
  $excerpt=trim((string)($a['excerpt']??''))?:mb_substr(strip_tags($a['body']),0,200);
  $now=date('c');
  $fields=[$cat,$a['title'],$excerpt,$a['body'],$image,(float)($a['score']??0),(string)($a['pros']??''),(string)($a['cons']??''),(string)($a['verdict']??''),(string)($a['author']??'Editorial team'),(string)($a['status']??'published'),(string)($a['brand']??''),(string)($a['brand_about']??''),(string)($a['cta_url']??'')];
  if($id=query('SELECT id FROM reviews WHERE slug=?',[$slug])[0]['id']??null){
   run('UPDATE reviews SET category_id=?,title=?,excerpt=?,body=?,image=?,score=?,pros=?,cons=?,verdict=?,author=?,status=?,brand=?,brand_about=?,cta_url=?,demo=0,updated_at=? WHERE id=?',[...$fields,$now,$id]);$updated++;
  }else{
   run('INSERT INTO reviews(category_id,title,excerpt,body,image,score,pros,cons,verdict,author,status,brand,brand_about,cta_url,slug,featured,demo,created_at,updated_at,published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,0,?,?,?)',[...$fields,$slug,$now,$now,now()]);$added++;
  }
 }
 if($hideSamples) run('UPDATE reviews SET status="draft" WHERE demo=1');
 $db->commit();
 echo "Imported: $added new, $updated updated".($hideSamples?', sample reviews moved to draft':'').".\n";
}catch(Throwable $e){$db->rollBack();fwrite(STDERR,$e->getMessage()."\n");exit(1);}

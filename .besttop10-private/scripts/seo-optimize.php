<?php
declare(strict_types=1);
// Usage: php scripts/seo-optimize.php [--dry-run] [--force] [--only=slug] [--pack=file.json] [--report]
// Applies the SEO packs in data/seo-pack/*.json to the matching posts (by slug) so every check of the
// editor's SEO analyser and AI-readiness analyser passes:
//  - focus keyword, SEO title, meta description, quick answer and key takeaways
//  - an opening sentence that answers the main question (with the keyword)
//  - a question-style section (with a list and concrete numbers) before the first heading,
//    ending with a link to a related post on this site
//  - an FAQ section when the post has fewer than 3 questions
// Posts that already have a focus keyword are skipped unless --force is given. Dates are not changed.
// --report only prints the current scores of every post.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/bootstrap.php';
$opts=getopt('',['dry-run','force','only:','report','pack:']);
$dry=isset($opts['dry-run']);$force=isset($opts['force']);$only=$opts['only']??null;

$posts=query("SELECT r.*,c.slug AS category_slug FROM reviews r JOIN categories c ON c.id=r.category_id WHERE r.status!='trash' AND r.demo=0 ORDER BY r.id");
$score=function(array $p): array { [$s,$a]=seoAnalyse($p); return [seoScore($s),seoScore($a),array_merge($s,$a)]; };

if(isset($opts['report'])){
 foreach($posts as $p){[$s,$a,$all]=$score($p);printf("%3d %3d  %s%s\n",$s,$a,$p['slug'],$s<100||$a<100?'  ← '.implode('; ',array_map(fn($c)=>$c[1],array_filter($all,fn($c)=>$c[0]!=='pass'))):'');}
 exit;
}

$pack=[];
foreach(isset($opts['pack'])?[$opts['pack']]:glob(ROOT.'/data/seo-pack/*.json') as $file){
 $data=json_decode((string)file_get_contents($file),true);
 if(!is_array($data)){fwrite(STDERR,"Invalid JSON: $file\n");exit(1);}
 foreach($data as $row)if(isset($row['slug']))$pack[$row['slug']]=$row;
}

$labels=['en'=>['Related reading','Frequently Asked Questions'],'de'=>['Weiterlesen','FAQ'],'fr'=>['À lire aussi','FAQ']];
$closing='/^##\s+(final|conclusion|fazit|bottom line|wrapping|in summary|summary|verdict|the verdict|mot de la fin|en résumé|abschließend|schlussgedanken|takeaway|last word)/imu';
$done=$skipped=0;$problems=[];
foreach($posts as $p){
 if($only!==null&&$p['slug']!==$only)continue;
 $x=$pack[$p['slug']]??null;
 if(!$x){if(!isset($opts['pack']))$problems[]="no pack: {$p['slug']}";continue;}
 if(trim((string)$p['focus_keyword'])!==''&&!$force){$skipped++;continue;}
 $lang=detectLang($p['title'].' '.$p['body']);[$relLabel,$faqLabel]=$labels[$lang]??$labels['en'];
 $body=str_replace("\r",'',trim((string)$p['body']));
 // With --force on an already optimised post, only the fields are refreshed; the added text is not added twice.
 $added=str_contains($body,'## '.trim($x['answer_heading']));

 // Related post on this site: same category first, then the newest other live post.
 $rel=query("SELECT r.slug,r.title FROM reviews r WHERE r.id!=? AND r.category_id=? AND ".live()." ORDER BY r.published_at DESC LIMIT 1",[$p['id'],$p['category_id']])[0]
  ??query("SELECT r.slug,r.title FROM reviews r WHERE r.id!=? AND ".live()." ORDER BY r.published_at DESC LIMIT 1",[$p['id']])[0]??null;

 if(!$added){
 // Question-style section before the first heading.
 $section="## ".trim($x['answer_heading'])."\n".trim($x['answer_body']);
 if($rel)$section.="\n\n**$relLabel:** [".$rel['title'].'](/'.$rel['slug'].')';
 $pos=preg_match('/^##\s/mu',$body,$m,PREG_OFFSET_CAPTURE)?$m[0][1]:strlen($body);
 $body=rtrim(substr($body,0,$pos))."\n\n".$section."\n\n".ltrim(substr($body,$pos));

 // FAQ: only when the post has fewer than 3 questions.
 [,$ai]=seoAnalyse(['body'=>$body]+$p);
 $faqOk=(bool)array_filter($ai,fn($c)=>$c[0]==='pass'&&str_starts_with($c[1],'FAQ'));
 if(!$faqOk&&!empty($x['faq'])){
  $qa=implode("\n\n",array_map(fn($f)=>'**'.rtrim(trim($f['q']),'?').'?**'."\n".trim($f['a']),$x['faq']));
  if(preg_match('/^##\s+(?:faqs?|frequently asked questions).*$/imu',$body,$fm,PREG_OFFSET_CAPTURE)){
   $start=$fm[0][1]+strlen($fm[0][0]);
   $next=preg_match('/^##\s/mu',$body,$nm,PREG_OFFSET_CAPTURE,$start)?$nm[0][1]:strlen($body);
   $body=rtrim(substr($body,0,$next))."\n\n".$qa."\n\n".ltrim(substr($body,$next));
  }else{
   $block='## '.trim($x['faq_heading']??$faqLabel)."\n\n".$qa;
   if(preg_match_all($closing,$body,$cm,PREG_OFFSET_CAPTURE)){$at=end($cm[0])[1];$body=rtrim(substr($body,0,$at))."\n\n".$block."\n\n".substr($body,$at);}
   else $body.="\n\n".$block;
  }
 }
 // Opening sentence that answers the main question.
 $body=trim($x['intro'])."\n\n".trim($body);
 }

 $new=['focus_keyword'=>mb_strtolower(trim($x['focus_keyword'])),'meta_title'=>trim($x['meta_title']),'meta_description'=>trim($x['meta_description']),'tldr'=>trim($x['tldr']),'takeaways'=>implode("\n",array_map('trim',$x['takeaways'])),'body'=>trim($body)."\n"];
 [$s,$a,$all]=$score($new+$p);
 $bad=array_filter($all,fn($c)=>$c[0]!=='pass');
 printf("%-9s %3d %3d  %s%s\n",$dry?'(dry run)':'updated',$s,$a,$p['slug'],$bad?'  ← '.implode('; ',array_map(fn($c)=>$c[1],$bad)):'');
 if($bad)$problems[]=$p['slug'];
 if(!$dry)run('UPDATE reviews SET focus_keyword=?,meta_title=?,meta_description=?,tldr=?,takeaways=?,body=? WHERE id=?',[...array_values($new),$p['id']]);
 $done++;
}
echo "\n".($dry?'Dry run: no changes saved. ':'')."$done post".($done===1?'':'s')." processed, $skipped already optimised (use --force to redo).\n";
if($problems)echo "Needs attention:\n  ".implode("\n  ",array_unique($problems))."\n";

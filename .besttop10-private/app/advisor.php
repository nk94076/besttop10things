<?php
declare(strict_types=1);
// Content Advisor: keyword ideas, a per-post "content doctor", AI-generated fixes and drafts.
// Nothing here publishes or rewrites an article on its own: fixes are applied by an editor (with undo),
// drafts stay drafts. The only optional automatic change is SEO title/description for low-CTR pages.

const STOPWORDS = ['a','an','the','and','or','for','to','of','in','on','with','your','you','how','what','is','are','best','vs','do','does','can','my','i','it','at','by','from','this','that','which','why','when'];

function kwTokens(string $s): array {
 $w=preg_split('/[^\p{L}\p{N}]+/u',mb_strtolower($s),-1,PREG_SPLIT_NO_EMPTY);
 return array_values(array_unique(array_filter($w,fn($t)=>mb_strlen($t)>2&&!in_array($t,STOPWORDS,true))));
}

// ---- Keyword ideas -------------------------------------------------------

// Google's public autocomplete (what people actually type). Cached per seed for 7 days.
function suggestKeywords(string $seed): array {
 $ck='suggest_cache_'.md5($seed);$c=json_decode(setting($ck),true);
 if(is_array($c)&&time()-($c['t']??0)<7*86400)return $c['s'];
 if($mock=getenv('SUGGEST_MOCK_DIR')){$s=json_decode((string)@file_get_contents($mock.'/'.preg_replace('/[^a-z0-9]+/','-',mb_strtolower($seed)).'.json'),true)?:[];}
 else{
  $ch=curl_init('https://suggestqueries.google.com/complete/search?client=firefox&hl=en&q='.rawurlencode($seed));
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>6,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_USERAGENT=>'Mozilla/5.0']);
  $raw=(string)curl_exec($ch);curl_close($ch);
  $j=json_decode(mb_convert_encoding($raw,'UTF-8','UTF-8, ISO-8859-1'),true);$s=is_array($j[1]??null)?array_values(array_filter($j[1],'is_string')):[];
  usleep(250000);
 }
 run('INSERT INTO settings(key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value',[$ck,json_encode(['t'=>time(),'s'=>$s])]);
 return $s;
}

// Which live post (if any) already targets a keyword.
function coveringPost(string $kw): ?array {
 static $posts=null;
 $posts??=query("SELECT r.id,r.slug,r.title,lower(r.focus_keyword) AS fk FROM reviews r WHERE r.status!='trash'");
 $k=mb_strtolower(trim($kw));$t=kwTokens($k);
 foreach($posts as $p)if($p['fk']!==''&&($p['fk']===$k||str_contains($k,$p['fk'])&&mb_strlen($p['fk'])>=mb_strlen($k)*0.7))return $p;
 if(count($t)>=2)foreach($posts as $p){$tt=kwTokens($p['title'].' '.$p['fk']);if(!array_diff($t,$tt))return $p;}
 return null;
}

// Category whose posts share the most words with the keyword.
function guessCategory(string $kw): int {
 static $docs=null;
 $docs??=array_map(fn($r)=>[(int)$r['category_id'],kwTokens($r['t'])],query("SELECT category_id,title||' '||focus_keyword||' '||c.name AS t FROM reviews r JOIN categories c ON c.id=r.category_id WHERE ".live()));
 $score=[];$t=kwTokens($kw);foreach($docs as [$c,$w])$score[$c]=($score[$c]??0)+count(array_intersect($t,$w));
 arsort($score);return (int)(array_key_first($score)??(query('SELECT id FROM categories ORDER BY id LIMIT 1')[0]['id']??0));
}

// "Easy win" score 0–100: long-tail, question-shaped, already showing in Google, and not covered yet.
function ideaScore(string $kw, int $impr, float $pos): int {
 $words=count(preg_split('/\s+/',trim($kw)));$s=30;
 $s+=min(25,max(0,$words-2)*8);
 if(preg_match('/^(how|what|which|why|when|is|are|can|do|does|should|best)\b/i',$kw)||str_contains($kw,' vs '))$s+=10;
 if($impr>0){$s+=min(25,(int)(log10($impr+1)*10));if($pos>=8&&$pos<=40)$s+=10;}
 return max(0,min(100,$s));
}

// Rebuild the idea list from category names, focus keywords and (if connected) Search Console.
function refreshKeywordIdeas(int $maxSeeds=30): int {
 $seeds=[];
 foreach(categories() as $c)if((int)$c['total']>0){foreach(['best '.$c['name'],'how to choose '.$c['name'],$c['name'].' tips',$c['name'].' for beginners'] as $s)$seeds[]=[$s,(int)$c['id']];}
 foreach(query("SELECT focus_keyword,category_id FROM reviews r WHERE ".live()." AND focus_keyword!='' ORDER BY published_at DESC LIMIT 20") as $r){$seeds[]=[$r['focus_keyword'],(int)$r['category_id']];$seeds[]=['best '.$r['focus_keyword'],(int)$r['category_id']];}
 $seeds=array_slice($seeds,0,$maxSeeds);$n=0;
 $gsc=[];if(gscKey()&&setting('gsc_property')!==''){try{foreach(gscQuery(['query'],date('Y-m-d',strtotime('-30 days')),date('Y-m-d',strtotime('-2 days')),1000) as $r)$gsc[mb_strtolower($r['keys'][0])]=$r;}catch(Throwable){}}
 $add=function(string $kw,int $cat,string $src)use(&$n,$gsc){
  $kw=trim(preg_replace('/\s+/',' ',mb_strtolower($kw)));if(mb_strlen($kw)<6||mb_strlen($kw)>90||count(kwTokens($kw))<1)return;
  $g=$gsc[$kw]??null;$cov=coveringPost($kw);
  run('INSERT INTO keyword_ideas(keyword,category_id,source,impressions,position,score,status,post_id,created_at) VALUES (?,?,?,?,?,?,?,?,?)
   ON CONFLICT(keyword) DO UPDATE SET impressions=excluded.impressions,position=excluded.position,score=excluded.score,post_id=coalesce(keyword_ideas.post_id,excluded.post_id),status=CASE WHEN excluded.post_id IS NOT NULL AND keyword_ideas.status="new" THEN "covered" ELSE keyword_ideas.status END',
   [$kw,$cat,$src,(int)($g['impressions']??0),(float)($g['position']??0),$cov?0:ideaScore($kw,(int)($g['impressions']??0),(float)($g['position']??0)),$cov?'covered':'new',$cov['id']??null,date('c')]);
  $n++;
 };
 foreach($seeds as [$seed,$cat])foreach(suggestKeywords($seed) as $s)$add($s,$cat,'google');
 foreach($gsc as $k=>$r)if($r['position']>10&&$r['impressions']>=5)$add($k,guessCategory($k),'search console');
 run('INSERT INTO settings(key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value',['ideas_refreshed',date('c')]);
 return $n;
}

// ---- Content doctor ------------------------------------------------------

// Issues for every live post, worst first. Each issue: [severity 1–3, code, title, detail].
function diagnosePosts(): array {
 $posts=query('SELECT r.*,c.name AS category FROM reviews r JOIN categories c ON c.id=r.category_id WHERE '.live().' ORDER BY r.published_at DESC');
 $bodies=implode("\n",array_column($posts,'body'));
 $byPage=[];$byQuery=[];$gscOn=false;
 if(gscKey()&&setting('gsc_property')!==''){try{
  foreach(gscQuery(['query','page'],date('Y-m-d',strtotime('-30 days')),date('Y-m-d',strtotime('-2 days')),5000) as $r){
   $p=postForUrl($r['keys'][1]);if(!$p)continue;$byPage[$p['id']][]=$r;$byQuery[mb_strtolower($r['keys'][0])][$p['id']]=$r;
  }$gscOn=true;}catch(Throwable){}}
 $fk=[];foreach($posts as $p)if(trim($p['focus_keyword'])!=='')$fk[mb_strtolower(trim($p['focus_keyword']))][]=$p['id'];
 $out=[];
 foreach($posts as $p){
  $iss=[];$id=(int)$p['id'];$age=(time()-strtotime((string)($p['published_at']??$p['created_at'])))/86400;
  [$seo,$ai]=seoAnalyse($p);$s1=seoScore($seo);$s2=seoScore($ai);
  if($s1<100||$s2<100)$iss[]=[$s1<70||$s2<70?3:2,'analyser',"SEO $s1/100 · AI $s2/100",implode('; ',array_map(fn($c)=>$c[1],array_filter(array_merge($seo,$ai),fn($c)=>$c[0]!=='pass')))];
  $wc=str_word_count(strip_tags($p['body']));
  if($wc<800)$iss[]=[2,'thin',"Short article ($wc words)",'Competing pages usually cover the topic in more depth. Add sections that answer related questions, with practical steps and examples.'];
  $upd=(time()-strtotime((string)$p['updated_at']))/86400;
  if($upd>180)$iss[]=[1,'stale','Not updated for '.round($upd).' days','Refresh facts, availability and the year in the title where relevant; Google favours recently maintained guides.'];
  if(!str_contains($bodies,'](/'.$p['slug'].')')&&!str_contains($bodies,'href="/'.$p['slug'].'"'))$iss[]=[2,'orphan','No other article links here','Internal links help Google find and value this page. Link to it from 2–3 related articles (the automatic links only cover focus keywords).'];
  $kw=mb_strtolower(trim($p['focus_keyword']));
  if($kw!==''&&count($fk[$kw]??[])>1)$iss[]=[3,'cannibal','Shares its focus keyword with another post','Two pages targeting "'.$kw.'" compete with each other. Merge them or give each a different keyword.'];
  if($gscOn){
   $rows=$byPage[$id]??[];$impr=array_sum(array_column($rows,'impressions'));
   if(!$rows&&$age>21)$iss[]=[3,'invisible','No Google impressions in 30 days','Google is not showing this page at all. Check it in Search Console › URL Inspection (is it indexed?), add internal links to it, and make the focus keyword more specific (long-tail).'];
   if($rows){
    $avg=array_sum(array_map(fn($r)=>$r['position']*$r['impressions'],$rows))/max(1,$impr);
    if($avg>10&&$avg<=30)$iss[]=[2,'page2','Ranking on page 2–3 (avg. position '.round($avg,1).')','Close to page 1: expand the sections that match the searches below, add FAQs and internal links.'];
    $missing=[];$text=mb_strtolower(strip_tags($p['body'].' '.$p['title']));
    foreach($rows as $r){$q=mb_strtolower($r['keys'][0]);$t=kwTokens($q);if($r['impressions']>=5&&$t&&count(array_filter($t,fn($w)=>str_contains($text,$w)))<count($t))$missing[]=$q;}
    if($missing)$iss[]=[2,'missing','Searches the article does not answer','Google shows this page for: '.implode(', ',array_slice($missing,0,6)).'. Add a section or FAQ that covers them.'];
    foreach($rows as $r)if($r['position']<=10&&$r['impressions']>=30&&$r['ctr']<expectedCtr($r['position'])*0.6){$iss[]=[2,'ctr','Low click-through for "'.$r['keys'][0].'"','Position '.round($r['position'],1).' but only '.round($r['ctr']*100,1).'% click. Rewrite the SEO title and description to be more specific and compelling.'];break;}
    foreach($rows as $r){$q=mb_strtolower($r['keys'][0]);if(count($byQuery[$q]??[])>1&&$r['impressions']>=10){$iss[]=[2,'cannibal-gsc','Competes with another page for "'.$q.'"','Google alternates between two of your pages for this search. Consolidate or differentiate them.'];break;}}
   }
  }
  usort($iss,fn($a,$b)=>$b[0]<=>$a[0]);
  $out[]=['post'=>$p,'issues'=>$iss,'priority'=>array_sum(array_column($iss,0)),'missing'=>$missing??[],'scores'=>[$s1,$s2]];
  unset($missing);
 }
 usort($out,fn($a,$b)=>$b['priority']<=>$a['priority']);
 return $out;
}

// ---- AI: fixes for a post, and new drafts ---------------------------------

function fixesSchema(): array {
 $str=['type'=>'string'];
 return aiObject([
  'summary'=>$str,
  'meta_title'=>$str,'meta_description'=>$str,'tldr'=>$str,
  'new_sections'=>['type'=>'array','items'=>aiObject(['heading'=>$str,'markdown'=>$str])],
  'faq'=>['type'=>'array','items'=>aiObject(['q'=>$str,'a'=>$str])],
 ],'fixes');
}

function generateFixes(int $postId): array {
 $d=null;foreach(diagnosePosts() as $x)if((int)$x['post']['id']===$postId)$d=$x;
 if(!$d)throw new RuntimeException('Post not found or not published.');
 $p=$d['post'];
 $issues=implode("\n",array_map(fn($i)=>'- '.$i[2].': '.$i[3],$d['issues']))?:'- No major issues; improve depth and usefulness.';
 $prompt="Improve this article so it ranks better and answers searchers fully.\n\nTitle: {$p['title']}\nCategory: {$p['category']}\nFocus keyword: ".($p['focus_keyword']?:'(none)')."\nCurrent SEO title: ".($p['meta_title']?:$p['title'])."\nCurrent meta description: ".($p['meta_description']?:$p['excerpt'])."\n\nProblems found:\n$issues\n\n".
  ($d['missing']?"Searches Google shows it for that it does not answer: ".implode('; ',$d['missing'])."\n\n":'').
  "Return:\n- summary: 1–2 sentences explaining what you changed and why.\n- meta_title: 40–60 characters, includes the focus keyword, specific and click-worthy (no clickbait, no year unless the article is about that year).\n- meta_description: 130–155 characters, includes the focus keyword, states the benefit.\n- tldr: a 1–3 sentence direct answer to the main question (max 300 characters).\n- new_sections: 1–3 NEW sections (heading without ##, 120–250 words of Markdown each) that fill the gaps above — especially the unanswered searches. Do not repeat what the article already says.\n- faq: 2–4 new questions (ending in ?) with 1–3 sentence answers, not already in the article.\n\nARTICLE (Markdown):\n".mb_substr($p['body'],0,60000);
 $fix=aiJson($prompt,fixesSchema());
 run('INSERT INTO ai_suggestions(post_id,data,created_at) VALUES (?,?,?) ON CONFLICT(post_id) DO UPDATE SET data=excluded.data,created_at=excluded.created_at',[$postId,json_encode($fix,JSON_UNESCAPED_UNICODE),date('c')]);
 return $fix;
}

function saveRevision(array $p, string $note): void {
 run('INSERT INTO post_revisions(post_id,title,meta_title,meta_description,tldr,takeaways,body,note,created_at) VALUES (?,?,?,?,?,?,?,?,?)',
  [$p['id'],$p['title'],$p['meta_title'],$p['meta_description'],$p['tldr']??'',$p['takeaways']??'',$p['body'],$note,date('c')]);
}

// Apply the chosen parts of a suggestion. $parts: meta, tldr, sections (indexes), faq (indexes).
function applyFixes(int $postId, array $parts): string {
 $p=query('SELECT * FROM reviews WHERE id=?',[$postId])[0]??null;$fix=json_decode((string)(query('SELECT data FROM ai_suggestions WHERE post_id=?',[$postId])[0]['data']??''),true);
 if(!$p||!$fix)throw new RuntimeException('No suggestion to apply.');
 saveRevision($p,'Before AI fixes');
 $body=str_replace("\r",'',trim($p['body']));$done=[];
 $sections=array_values(array_intersect_key($fix['new_sections']??[],array_flip(array_map('intval',(array)($parts['sections']??[])))));
 if($sections){
  $block=implode("\n\n",array_map(fn($s)=>'## '.trim(ltrim($s['heading'],'# '))."\n".trim($s['markdown']),$sections));
  // before the FAQ or the closing section, else at the end
  if(preg_match('/^##\s+(faqs?|frequently asked questions|final|conclusion|fazit|bottom line|in summary|verdict)/imu',$body,$m,PREG_OFFSET_CAPTURE))$body=rtrim(substr($body,0,$m[0][1]))."\n\n$block\n\n".substr($body,$m[0][1]);
  else $body.="\n\n$block";
  $done[]=count($sections).' section'.(count($sections)>1?'s':'');
 }
 $faq=array_values(array_intersect_key($fix['faq']??[],array_flip(array_map('intval',(array)($parts['faq']??[])))));
 if($faq){
  $qa=implode("\n\n",array_map(fn($f)=>'**'.rtrim(trim($f['q']),'?').'?**'."\n".trim($f['a']),$faq));
  if(preg_match('/^##\s+(?:faqs?|frequently asked questions).*$/imu',$body,$fm,PREG_OFFSET_CAPTURE)){
   $start=$fm[0][1]+strlen($fm[0][0]);$next=preg_match('/^##\s/mu',$body,$nm,PREG_OFFSET_CAPTURE,$start)?$nm[0][1]:strlen($body);
   $body=rtrim(substr($body,0,$next))."\n\n$qa\n\n".ltrim(substr($body,$next));
  }elseif(preg_match('/^##\s+(final|conclusion|fazit|bottom line|in summary|verdict)/imu',$body,$cm,PREG_OFFSET_CAPTURE))$body=rtrim(substr($body,0,$cm[0][1]))."\n\n## Frequently Asked Questions\n\n$qa\n\n".substr($body,$cm[0][1]);
  else $body.="\n\n## Frequently Asked Questions\n\n$qa";
  $done[]=count($faq).' FAQ'.(count($faq)>1?'s':'');
 }
 $meta=[$p['meta_title'],$p['meta_description']];if(!empty($parts['meta'])){$meta=[mb_substr(trim($fix['meta_title']),0,200),mb_substr(trim($fix['meta_description']),0,500)];$done[]='SEO title & description';}
 $tldr=$p['tldr'];if(!empty($parts['tldr'])){$tldr=mb_substr(trim($fix['tldr']),0,700);$done[]='quick answer';}
 if(!$done)throw new RuntimeException('Select at least one change to apply.');
 run('UPDATE reviews SET body=?,meta_title=?,meta_description=?,tldr=?,updated_at=? WHERE id=?',[trim($body)."\n",$meta[0],$meta[1],$tldr,date('c'),$postId]);
 run('DELETE FROM ai_suggestions WHERE post_id=?',[$postId]);
 indexNowPing([reviewUrl($p)]);
 return 'Applied: '.implode(', ',$done).'.';
}

function undoRevision(int $revId): string {
 $r=query('SELECT * FROM post_revisions WHERE id=?',[$revId])[0]??null;if(!$r)throw new RuntimeException('Revision not found.');
 $cur=query('SELECT * FROM reviews WHERE id=?',[$r['post_id']])[0]??null;if(!$cur)throw new RuntimeException('The post no longer exists.');
 saveRevision($cur,'Before undo');
 run('UPDATE reviews SET title=?,meta_title=?,meta_description=?,tldr=?,takeaways=?,body=?,updated_at=? WHERE id=?',[$r['title'],$r['meta_title'],$r['meta_description'],$r['tldr'],$r['takeaways'],$r['body'],date('c'),$r['post_id']]);
 return 'Restored the version from '.date('M j, g:i a',strtotime($r['created_at'])).'.';
}

function draftSchema(): array {
 $str=['type'=>'string'];
 return aiObject(['title'=>$str,'meta_title'=>$str,'meta_description'=>$str,'focus_keyword'=>$str,'excerpt'=>$str,'tldr'=>$str,
  'takeaways'=>['type'=>'array','items'=>$str],'body_markdown'=>$str],'draft');
}

// Write a full draft for a keyword idea. Saved as a draft for an editor to review, add affiliate links and publish.
function writeDraft(int $ideaId): int {
 $i=query('SELECT * FROM keyword_ideas WHERE id=?',[$ideaId])[0]??null;if(!$i)throw new RuntimeException('Idea not found.');
 $cat=query('SELECT * FROM categories WHERE id=?',[(int)$i['category_id']])[0]??query('SELECT * FROM categories ORDER BY id LIMIT 1')[0];
 $related=array_map(fn($r)=>'- ['.$r['title'].'](/'.$r['slug'].')',query('SELECT title,slug FROM reviews r WHERE '.live().' AND category_id=? ORDER BY published_at DESC LIMIT 6',[$cat['id']]));
 $prompt="Write a complete, genuinely useful article for the search query: \"{$i['keyword']}\" (category: {$cat['name']}).\n\n".
  "Requirements:\n- 1,200–1,800 words of Markdown in body_markdown. Start with a 2–3 sentence direct answer that uses the focus keyword. Then 5–8 ## sections (several phrased as questions), at least one bulleted list, practical steps, honest trade-offs and who each option suits. End with a '## Frequently Asked Questions' section (4–5 **Question?** lines, each followed by a short answer) and a short '## Final Thoughts'.\n".
  "- Do not mention specific product prices, specs, ratings or test results; give criteria and rules of thumb instead. Do not claim we tested anything.\n".
  "- Link naturally to 1–3 of these related articles on our site where relevant (use the exact Markdown links):\n".($related?implode("\n",$related):'(none yet)')."\n".
  "- title: an engaging H1 (max 70 characters). meta_title: 40–60 characters with the focus keyword. meta_description: 130–155 characters with the focus keyword. focus_keyword: the main 2–4 word phrase. excerpt: 1–2 sentences for article cards. tldr: 1–3 sentence quick answer (max 300 characters). takeaways: 3–5 short lines.";
 $d=aiJson($prompt,draftSchema(),32000);
 $slug=slug($d['title']);$base=$slug;$n=2;while(in_array($slug,RESERVED_SLUGS,true)||query('SELECT id FROM reviews WHERE slug=?',[$slug]))$slug=$base.'-'.$n++;
 $author=(query("SELECT author FROM reviews r WHERE ".live()." GROUP BY author ORDER BY COUNT(*) DESC LIMIT 1")[0]['author']??'Editorial team');
 run('INSERT INTO reviews(category_id,title,slug,excerpt,body,image,score,pros,cons,verdict,author,status,featured,demo,meta_title,meta_description,focus_keyword,tldr,takeaways,created_at,updated_at,published_at) VALUES (?,?,?,?,?,?,0,"","","",?,"draft",0,0,?,?,?,?,?,?,?,?)',
  [$cat['id'],mb_substr($d['title'],0,200),$slug,mb_substr($d['excerpt'],0,600),trim($d['body_markdown']),'/assets/hero.jpg',$author,mb_substr($d['meta_title'],0,200),mb_substr($d['meta_description'],0,500),mb_strtolower(mb_substr($d['focus_keyword'],0,100)),mb_substr($d['tldr'],0,700),implode("\n",array_slice($d['takeaways'],0,6)),date('c'),date('c'),now()]);
 $id=(int)db()->lastInsertId();
 run('UPDATE keyword_ideas SET status="drafted",post_id=? WHERE id=?',[$id,$ideaId]);
 return $id;
}

// ---- Queue (AI calls can take a minute; the browser may give up first) ----
function processAiQueue(int $limit=3): array {
 @set_time_limit(600);ignore_user_abort(true);$log=[];
 foreach(query("SELECT id,keyword FROM keyword_ideas WHERE status='queued' ORDER BY id LIMIT ?",[$limit]) as $i){
  try{$pid=writeDraft((int)$i['id']);$log[]="Draft written: {$i['keyword']} (#$pid)";}
  catch(Throwable $e){run("UPDATE keyword_ideas SET status='new' WHERE id=?",[$i['id']]);$log[]="Draft failed for {$i['keyword']}: ".$e->getMessage();}
 }
 foreach(query("SELECT post_id FROM ai_suggestions WHERE data='{\"pending\":true}' ORDER BY created_at LIMIT ?",[$limit]) as $s){
  try{generateFixes((int)$s['post_id']);$log[]="Fixes ready for post #{$s['post_id']}";}
  catch(Throwable $e){run('DELETE FROM ai_suggestions WHERE post_id=?',[$s['post_id']]);$log[]="Fixes failed for post #{$s['post_id']}: ".$e->getMessage();}
 }
 return $log;
}
function queueFixes(int $postId): void { run('INSERT INTO ai_suggestions(post_id,data,created_at) VALUES (?,?,?) ON CONFLICT(post_id) DO UPDATE SET data=excluded.data,created_at=excluded.created_at',[$postId,'{"pending":true}',date('c')]); }

// Optional automatic mode (off by default): rewrite only the SEO title/description of low-CTR pages.
function autoFixMeta(int $max=3): array {
 $log=[];
 foreach(array_slice(array_filter(diagnosePosts(),fn($d)=>in_array('ctr',array_column($d['issues'],1),true)),0,$max) as $d){
  $p=$d['post'];
  if(query("SELECT 1 FROM post_revisions WHERE post_id=? AND note='Before automatic title update' AND created_at>?",[$p['id'],date('c',time()-30*86400)]))continue; // at most monthly per post
  try{
   $r=aiJson("Rewrite the SEO title and meta description of this article to earn more clicks from Google for the search \"".($p['focus_keyword']?:$p['title'])."\". Keep them accurate to the article.\nTitle: {$p['title']}\nCurrent SEO title: ".($p['meta_title']?:$p['title'])."\nCurrent description: ".($p['meta_description']?:$p['excerpt'])."\nExcerpt: {$p['excerpt']}\n\nmeta_title: 40–60 characters with the focus keyword. meta_description: 130–155 characters with the focus keyword.",
    aiObject(['meta_title'=>['type'=>'string'],'meta_description'=>['type'=>'string']],'meta'),2000,'low');
   saveRevision($p,'Before automatic title update');
   run('UPDATE reviews SET meta_title=?,meta_description=?,updated_at=? WHERE id=?',[mb_substr(trim($r['meta_title']),0,200),mb_substr(trim($r['meta_description']),0,500),date('c'),$p['id']]);
   $log[]="Title updated: {$p['title']}";
  }catch(Throwable $e){$log[]="Title update failed for {$p['title']}: ".$e->getMessage();}
 }
 return $log;
}

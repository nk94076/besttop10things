<?php
declare(strict_types=1);
// Usage (cron, e.g. hourly): php scripts/content-advisor.php
// - finishes queued AI drafts and fixes
// - weekly: refreshes keyword ideas; if enabled in Admin › Content Advisor, prepares AI fixes for the
//   3 posts with the biggest problems (optionally applying them automatically), and (optional) rewrites
//   SEO titles of low-CTR pages automatically
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/bootstrap.php';
foreach(processAiQueue(5) as $l)echo date('Y-m-d H:i ')."$l\n";
$last=strtotime(setting('advisor_weekly_run')?:'2000-01-01');
if(time()-$last<7*86400)exit;
run('INSERT INTO settings(key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value',['advisor_weekly_run',date('c')]);
echo date('Y-m-d H:i ').'Keyword ideas refreshed: '.refreshKeywordIdeas()."\n";
if(aiAvailable()&&setting('advisor_weekly_fixes')==='1'){
 $n=0;foreach(diagnosePosts() as $d){if($n>=3)break;if(!$d['issues']||query('SELECT 1 FROM ai_suggestions WHERE post_id=?',[$d['post']['id']]))continue;queueFixes((int)$d['post']['id']);$n++;}
 foreach(processAiQueue(3) as $l)echo date('Y-m-d H:i ')."$l\n";
 // Fully automatic mode (opt-in): apply the prepared fixes. A revision is saved first; undo in History.
 if(setting('advisor_auto_apply')==='1')foreach(query("SELECT post_id,data FROM ai_suggestions WHERE data!='{\"pending\":true}'") as $sg){
  $f=json_decode($sg['data'],true);if(!$f)continue;
  try{echo date('Y-m-d H:i ').'Auto-applied #'.$sg['post_id'].': '.applyFixes((int)$sg['post_id'],['meta'=>1,'tldr'=>1,'sections'=>array_keys($f['new_sections']??[]),'faq'=>array_keys($f['faq']??[])])."\n";}
  catch(Throwable $e){echo date('Y-m-d H:i ').'Auto-apply failed for #'.$sg['post_id'].': '.$e->getMessage()."\n";}
 }
}
if(aiAvailable()&&setting('advisor_auto_meta')==='1')foreach(autoFixMeta() as $l)echo date('Y-m-d H:i ')."$l\n";

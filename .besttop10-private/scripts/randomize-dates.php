<?php
declare(strict_types=1);
// Usage: php scripts/randomize-dates.php [--from=2026-05-01] [--to=2026-08-30] [--dry-run] [--check]
// Gives every post (published, drafts, samples and trash; scheduled posts are left alone)
// a random publish date and daytime between --from and --to (inclusive), and sets its
// created and updated dates to the same value.
// --check only lists posts that have any date outside the range, without changing anything.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/bootstrap.php';
$opts=getopt('',['from::','to::','dry-run','check']);
$fromDay=$opts['from']??'2026-05-01';$toDay=$opts['to']??'2026-08-30';
$from=strtotime($fromDay.' 00:00:00');$to=strtotime($toDay.' 23:59:59');
if(!$from||!$to||$from>=$to){fwrite(STDERR,"Invalid --from/--to dates.\n");exit(1);}

// A post is outside the range if any of its three dates falls outside it.
$outside=fn(array $p)=>array_filter([$p['published_at'],$p['created_at'],$p['updated_at']],fn($d)=>$d!==null&&$d!==''&&(substr($d,0,10)<$fromDay||substr($d,0,10)>$toDay));
$all=query("SELECT id,title,status,published_at,created_at,updated_at FROM reviews ORDER BY id");
$scheduled=fn(array $p)=>$p['status']==='published'&&($p['published_at']??'')>now();

if(isset($opts['check'])){
 $bad=array_filter($all,fn($p)=>!$scheduled($p)&&$outside($p));
 foreach($bad as $p)printf("%-9s %-10s %s  (%s)\n",$p['status'],substr((string)$p['published_at'],0,10),mb_strimwidth($p['title'],0,60,'…'),implode(', ',array_unique(array_map(fn($d)=>substr($d,0,10),$outside($p)))));
 echo count($bad)." post".(count($bad)===1?' has':'s have')." a date outside $fromDay – $toDay.\n";
 exit;
}

$dry=isset($opts['dry-run']);$n=0;
db()->beginTransaction();
foreach($all as $p){
 if($scheduled($p))continue;
 // Daytime publishing hours (8am–9pm) look natural.
 $ts=strtotime(date('Y-m-d',random_int($from,$to)))+random_int(8*3600,21*3600);
 printf("%-10s %-9s %s  %s\n",$dry?'(dry run)':'updated',$p['status'],date('M j, Y g:i a',$ts),mb_strimwidth($p['title'],0,60,'…'));
 if(!$dry)run('UPDATE reviews SET published_at=?,created_at=?,updated_at=? WHERE id=?',[date('Y-m-d\TH:i:s',$ts),date('c',$ts),date('c',$ts),$p['id']]);
 $n++;
}
db()->commit();
echo ($dry?'Dry run: no changes saved. ':'')."$n post".($n===1?'':'s')." between ".date('M j, Y',$from)." and ".date('M j, Y',$to).".\n";

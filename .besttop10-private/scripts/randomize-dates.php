<?php
declare(strict_types=1);
// Usage: php scripts/randomize-dates.php [--from=2026-05-01] [--to=2026-08-30] [--dry-run]
// Gives every live published post (sample reviews and scheduled posts excluded) a random publish date and time
// between --from and --to (inclusive). Created and updated dates are set to the same value.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/bootstrap.php';
$opts=getopt('',['from::','to::','dry-run']);
$from=strtotime(($opts['from']??'2026-05-01').' 00:00:00');
$to=strtotime(($opts['to']??'2026-08-30').' 23:59:59');
if(!$from||!$to||$from>=$to){fwrite(STDERR,"Invalid --from/--to dates.\n");exit(1);}
$dry=isset($opts['dry-run']);
$posts=query("SELECT id,title FROM reviews WHERE status='published' AND demo=0 AND (published_at IS NULL OR published_at<=?) ORDER BY id",[now()]);
db()->beginTransaction();
foreach($posts as $p){
 // Daytime publishing hours (8am–9pm) look natural.
 $day=strtotime(date('Y-m-d',random_int($from,$to)));
 $ts=$day+random_int(8*3600,21*3600);
 $local=date('Y-m-d\TH:i:s',$ts);
 printf("%-10s %s  %s\n",$dry?'(dry run)':'updated',date('M j, Y g:i a',$ts),mb_strimwidth($p['title'],0,70,'…'));
 if(!$dry)run('UPDATE reviews SET published_at=?,created_at=?,updated_at=? WHERE id=?',[$local,date('c',$ts),date('c',$ts),$p['id']]);
}
db()->commit();
echo ($dry?'Dry run: no changes saved. ':'').count($posts)." post".(count($posts)===1?'':'s')." between ".date('M j, Y',$from)." and ".date('M j, Y',$to).".\n";

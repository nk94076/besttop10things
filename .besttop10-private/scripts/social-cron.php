<?php
declare(strict_types=1);
// Usage (cron, e.g. every hour): php scripts/social-cron.php
// Pins posts that went live in the last 7 days and are not on Pinterest yet — this covers scheduled posts,
// which become public after the editor clicked "Schedule". Requires auto-pinning to be switched on.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/bootstrap.php';
if(setting('pinterest_auto')!=='1'){echo "Auto-pinning is off (Admin › SEO & Code › Pinterest).\n";exit;}
$since=date('Y-m-d\TH:i:s',time()-7*86400);
foreach(query('SELECT r.id,r.title FROM reviews r WHERE '.live().' AND r.published_at>=? ORDER BY r.published_at',[$since]) as $p){
 if(pinnedAlready((int)$p['id']))continue;
 $msg=autoSocial((int)$p['id']);echo $p['title'].': '.($msg??'skipped')."\n";
 sleep(2); // stay well under Pinterest rate limits
}

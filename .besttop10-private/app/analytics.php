<?php
declare(strict_types=1);
// First-party website analytics: page views, visits, sources, countries, devices, clicks, scroll depth and time on page.
// Pages are cached by Varnish, so /assets/app.js reports each view with a small beacon to POST /t.
// Data lives in its own database (storage/analytics.sqlite) so the site's main database stays small and fast.
// Countries: Cloudflare's CF-IPCountry header when present, otherwise the free DB-IP "IP to Country Lite"
// database (CC BY 4.0), downloaded by scripts/geoip-update.php into storage/geoip.sqlite.

const ANALYTICS_DB = ROOT . '/storage/analytics.sqlite';
const GEOIP_DB = ROOT . '/storage/geoip.sqlite';

function adb(): PDO {
 static $a=null;
 if($a)return $a;
 $path=getenv('ANALYTICS_DB')?:ANALYTICS_DB;
 if(!is_dir(dirname($path)))mkdir(dirname($path),0750,true);
 $a=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $a->exec('PRAGMA busy_timeout=5000; PRAGMA journal_mode=WAL; PRAGMA synchronous=NORMAL;');
 $a->exec('CREATE TABLE IF NOT EXISTS hits (id INTEGER PRIMARY KEY, pv TEXT NOT NULL UNIQUE, ts TEXT NOT NULL, day TEXT NOT NULL, visitor TEXT NOT NULL, session TEXT NOT NULL, is_new INTEGER NOT NULL DEFAULT 0, entry INTEGER NOT NULL DEFAULT 0,
  path TEXT NOT NULL, title TEXT NOT NULL DEFAULT "", post_id INTEGER, referrer TEXT NOT NULL DEFAULT "", ref_host TEXT NOT NULL DEFAULT "", source TEXT NOT NULL DEFAULT "Direct",
  utm_source TEXT NOT NULL DEFAULT "", utm_medium TEXT NOT NULL DEFAULT "", utm_campaign TEXT NOT NULL DEFAULT "", country TEXT NOT NULL DEFAULT "", tz TEXT NOT NULL DEFAULT "", lang TEXT NOT NULL DEFAULT "",
  device TEXT NOT NULL DEFAULT "", browser TEXT NOT NULL DEFAULT "", os TEXT NOT NULL DEFAULT "", screen TEXT NOT NULL DEFAULT "", ip TEXT NOT NULL DEFAULT "", is_admin INTEGER NOT NULL DEFAULT 0,
  seconds INTEGER NOT NULL DEFAULT 0, scroll INTEGER NOT NULL DEFAULT 0);
 CREATE INDEX IF NOT EXISTS hits_day ON hits(day);
 CREATE INDEX IF NOT EXISTS hits_visitor ON hits(visitor);
 CREATE TABLE IF NOT EXISTS events (id INTEGER PRIMARY KEY, ts TEXT NOT NULL, day TEXT NOT NULL, pv TEXT NOT NULL, visitor TEXT NOT NULL, path TEXT NOT NULL, kind TEXT NOT NULL, target TEXT NOT NULL DEFAULT "", label TEXT NOT NULL DEFAULT "", is_admin INTEGER NOT NULL DEFAULT 0);
 CREATE INDEX IF NOT EXISTS events_day ON events(day);');
 return $a;
}
function aquery(string $sql, array $p=[]): array { $q=adb()->prepare($sql);$q->execute($p);return $q->fetchAll(); }
function arun(string $sql, array $p=[]): void { $q=adb()->prepare($sql);$q->execute($p); }

// ---- Country lookup ----
// 16-byte binary form of an address (IPv4 mapped to ::ffff:a.b.c.d) so ranges compare as plain strings.
function ipKey(string $ip): ?string {
 $b=@inet_pton($ip);if($b===false)return null;
 return strlen($b)===4?str_repeat("\0",10)."\xff\xff".$b:$b;
}
function geoCountry(string $ip): string {
 $cf=strtoupper(preg_replace('/[^A-Za-z]/','',(string)($_SERVER['HTTP_CF_IPCOUNTRY']??'')));
 if(strlen($cf)===2&&$cf!=='XX')return $cf;
 $file=getenv('GEOIP_DB')?:GEOIP_DB;
 if(!is_file($file)||!($k=ipKey($ip)))return '';
 static $g=null;
 try{
  $g??=new PDO('sqlite:'.$file,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $q=$g->prepare('SELECT cc,e FROM ranges WHERE s<=? ORDER BY s DESC LIMIT 1');$q->execute([$k]);$r=$q->fetch(PDO::FETCH_NUM);
  return $r&&strcmp($k,$r[1])<=0?$r[0]:'';
 }catch(Throwable){return '';}
}
// Downloads the monthly DB-IP country file and rebuilds storage/geoip.sqlite. Returns the number of ranges.
function geoipUpdate(?string $csvGz=null): int {
 if($csvGz===null){
  $raw='';
  foreach([date('Y-m'),date('Y-m',strtotime('first day of last month'))] as $m){
   $ch=curl_init("https://download.db-ip.com/free/dbip-country-lite-$m.csv.gz");
   curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>120,CURLOPT_CONNECTTIMEOUT=>10]);
   $raw=(string)curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
   if($code===200&&strlen($raw)>100000)break;$raw='';
  }
  if($raw==='')throw new RuntimeException('Could not download the country database from db-ip.com. Try again later.');
  $csvGz=tempnam(sys_get_temp_dir(),'geo');file_put_contents($csvGz,$raw);
 }
 $file=getenv('GEOIP_DB')?:GEOIP_DB;$tmp=$file.'.new';@unlink($tmp);
 $g=new PDO('sqlite:'.$tmp,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $g->exec('PRAGMA journal_mode=OFF; PRAGMA synchronous=OFF; CREATE TABLE ranges (s BLOB PRIMARY KEY, e BLOB NOT NULL, cc TEXT NOT NULL) WITHOUT ROWID');
 $ins=$g->prepare('INSERT OR REPLACE INTO ranges VALUES (?,?,?)');$n=0;
 $g->beginTransaction();
 $fh=gzopen($csvGz,'r');if(!$fh)throw new RuntimeException('The country database file is unreadable.');
 while(($row=fgetcsv($fh,0,',','"',''))!==false){
  if(count($row)<3||!preg_match('/^[A-Z]{2}$/',$row[2])||!($s=ipKey($row[0]))||!($e=ipKey($row[1])))continue;
  $ins->execute([$s,$e,$row[2]]);$n++;
 }
 gzclose($fh);$g->commit();$g=null;
 if($n<1000)throw new RuntimeException("The country database looks incomplete ($n ranges); kept the old one.");
 rename($tmp,$file);@chmod($file,0640);
 run('INSERT INTO settings(key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value',['geoip_updated',now()]);
 return $n;
}

// ---- Collecting ----
// POST /t from app.js. Kinds: pv (page view), end (time on page + scroll depth), click.
function collectBeacon(): void {
 header('Cache-Control: no-store, private');header('X-Robots-Tag: noindex');
 $d=json_decode(substr((string)file_get_contents('php://input'),0,8000),true);
 if(!is_array($d)||!preg_match('/^[a-f0-9]{16,32}$/',(string)($d['pv']??''))){http_response_code(400);exit;}
 $ua=mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,400);$agent=parseAgent($ua);
 if($agent['is_bot']||setting('analytics_off')==='1'){http_response_code(204);exit;}
 $str=fn(string $k,int $max)=>mb_substr(trim(preg_replace('/[\x00-\x1f]/u','',(string)($d[$k]??''))),0,$max);
 $admin=isset($_SESSION['admin'])?1:0;$ts=now();$day=substr($ts,0,10);
 try{
  if($d['k']==='pv'){
   captureVisit();
   $visitor=(string)$_COOKIE['btv'];$new=!aquery('SELECT 1 FROM hits WHERE visitor=? LIMIT 1',[$visitor]);
   $path=(string)parse_url($str('u',500),PHP_URL_PATH)?:'/';
   parse_str((string)parse_url($str('u',1000),PHP_URL_QUERY),$q);
   $ref=$str('r',500);$host=strtolower((string)parse_url($ref,PHP_URL_HOST));$own=strtolower((string)parse_url('http://'.($_SERVER['HTTP_HOST']??''),PHP_URL_HOST));
   $internal=$host!==''&&preg_replace('/^www\./','',$host)===preg_replace('/^www\./','',$own);
   $session=preg_match('/^[a-f0-9]{16}$/',(string)($d['s']??''))?$d['s']:substr($d['pv'],0,16);
   $utm=fn($k)=>mb_substr(trim((string)($q[$k]??'')),0,100);
   $post=preg_match('~^/([a-z0-9-]+)$~',$path,$m)?(query("SELECT id FROM reviews WHERE slug=?",[$m[1]])[0]['id']??null):null;
   $ip=clientIp();
   arun('INSERT OR IGNORE INTO hits(pv,ts,day,visitor,session,is_new,entry,path,title,post_id,referrer,ref_host,source,utm_source,utm_medium,utm_campaign,country,tz,lang,device,browser,os,screen,ip,is_admin) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[
    $d['pv'],$ts,$day,$visitor,$session,$new?1:0,$internal?0:1,$path,$str('t',200),$post,$internal?'':$ref,$internal?'':preg_replace('/^www\./','',$host),
    $utm('utm_source')?:($internal?'Internal':trafficSource($host)),$utm('utm_source'),$utm('utm_medium'),$utm('utm_campaign'),geoCountry($ip),
    preg_match('~^[A-Za-z_]+(/[A-Za-z0-9_+\-]+){0,2}$~',$str('z',60))?$str('z',60):'',preg_match('/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,8})?$/',$str('l',20))?$str('l',20):'',
    $agent['device'],$agent['browser'],$agent['os'],preg_match('/^\d{2,5}x\d{2,5}$/',$str('w',12))?$str('w',12):'',$ip,$admin]);
  }elseif($d['k']==='end'){
   arun('UPDATE hits SET seconds=MAX(seconds,?),scroll=MAX(scroll,?) WHERE pv=?',[min(3600,max(0,(int)($d['sec']??0))),min(100,max(0,(int)($d['sc']??0))),$d['pv']]);
  }elseif($d['k']==='click'){
   $hit=aquery('SELECT visitor,path FROM hits WHERE pv=?',[$d['pv']])[0]??null;if(!$hit){http_response_code(204);exit;}
   if((int)(aquery('SELECT COUNT(*) n FROM events WHERE pv=?',[$d['pv']])[0]['n']??0)>=200){http_response_code(204);exit;}
   $kind=in_array($d['c']??'',['link','outbound','affiliate','button','download'],true)?$d['c']:'link';
   arun('INSERT INTO events(ts,day,pv,visitor,path,kind,target,label,is_admin) VALUES (?,?,?,?,?,?,?,?,?)',[$ts,$day,$d['pv'],$hit['visitor'],$hit['path'],$kind,$str('h',500),$str('x',120),$admin]);
  }
 }catch(Throwable $e){error_log('analytics: '.$e->getMessage());}
 http_response_code(204);exit;
}

// Keeps the database small: raw rows for 13 months, IP addresses for 90 days.
function analyticsPrune(): void {
 arun('DELETE FROM hits WHERE day<?',[date('Y-m-d',strtotime('-400 days'))]);
 arun('DELETE FROM events WHERE day<?',[date('Y-m-d',strtotime('-400 days'))]);
 arun("UPDATE hits SET ip='' WHERE ip!='' AND day<?",[date('Y-m-d',strtotime('-90 days'))]);
}

// Flag emoji for a 2-letter country code, plus a readable name.
function countryLabel(string $cc): string {
 if(!preg_match('/^[A-Z]{2}$/',$cc))return 'Unknown';
 $flag=mb_chr(0x1F1E6+ord($cc[0])-65).mb_chr(0x1F1E6+ord($cc[1])-65);
 $name=class_exists('Locale')?\Locale::getDisplayRegion('-'.$cc,'en'):$cc;
 return $flag.' '.($name&&$name!==$cc?$name:$cc);
}

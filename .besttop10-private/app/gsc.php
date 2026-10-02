<?php
declare(strict_types=1);
// Google Search Console (Search Analytics API) with a service account.
// Setup: Google Cloud → enable "Google Search Console API" → create a service account → JSON key.
// In Search Console → Settings → Users and permissions, add the service account e-mail (Restricted is enough).
// The key is stored in .besttop10-private/storage/gsc-key.json (outside the web root, mode 600), never in the DB.

const GSC_KEY_FILE = ROOT . '/storage/gsc-key.json';
const GSC_CACHE_TTL = 21600; // 6 hours

function gscKey(): ?array {
 if(!is_file(GSC_KEY_FILE))return null;
 $k=json_decode((string)file_get_contents(GSC_KEY_FILE),true);
 return is_array($k)&&isset($k['client_email'],$k['private_key'])?$k:null;
}
function gscSaveKey(string $json): array {
 $k=json_decode($json,true);
 if(!is_array($k)||($k['type']??'')!=='service_account'||!isset($k['client_email'],$k['private_key'])||!openssl_pkey_get_private($k['private_key']))
  throw new RuntimeException('Paste the full JSON key of a Google Cloud service account.');
 $k=array_intersect_key($k,array_flip(['type','client_email','private_key','token_uri','project_id']));
 if(!is_dir(dirname(GSC_KEY_FILE)))mkdir(dirname(GSC_KEY_FILE),0750,true);
 file_put_contents(GSC_KEY_FILE,json_encode($k));@chmod(GSC_KEY_FILE,0600);
 run('DELETE FROM settings WHERE key LIKE ?',['gsc_cache_%']);
 return $k;
}

function gscHttp(string $url, array $opts): array {
 $ch=curl_init($url);
 curl_setopt_array($ch,$opts+[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>8]);
 $body=(string)curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
 $data=json_decode($body,true);
 if($code<200||$code>=300){$msg=$data['error']['message']??$data['error_description']??($err?:"HTTP $code");throw new RuntimeException('Search Console: '.$msg);}
 return is_array($data)?$data:[];
}

function gscToken(): string {
 $k=gscKey();if(!$k)throw new RuntimeException('Search Console is not connected yet.');
 $cached=json_decode(setting('gsc_cache_token'),true);
 if(is_array($cached)&&($cached['exp']??0)>time()+60&&($cached['for']??'')===$k['client_email'])return $cached['token'];
 $b64=fn($s)=>rtrim(strtr(base64_encode($s),'+/','-_'),'=');
 $now=time();$aud=$k['token_uri']??'https://oauth2.googleapis.com/token';
 $unsigned=$b64(json_encode(['alg'=>'RS256','typ'=>'JWT'])).'.'.$b64(json_encode(['iss'=>$k['client_email'],'scope'=>'https://www.googleapis.com/auth/webmasters.readonly','aud'=>$aud,'iat'=>$now,'exp'=>$now+3600]));
 if(!openssl_sign($unsigned,$sig,$k['private_key'],OPENSSL_ALGO_SHA256))throw new RuntimeException('Could not sign the Search Console request.');
 $r=gscHttp($aud,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$unsigned.'.'.$b64($sig)])]);
 run('INSERT INTO settings(key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value',['gsc_cache_token',json_encode(['token'=>$r['access_token'],'exp'=>$now+(int)($r['expires_in']??3600),'for'=>$k['client_email']])]);
 return $r['access_token'];
}

// Search Analytics rows for a date range and dimensions, cached for 6 hours.
function gscQuery(array $dimensions, string $start, string $end, int $limit=1000, bool $refresh=false): array {
 $property=setting('gsc_property');if($property==='')throw new RuntimeException('Enter your Search Console property first.');
 $ckey='gsc_cache_'.md5($property.implode(',',$dimensions).$start.$end.$limit);
 if(!$refresh){$c=json_decode(setting($ckey),true);if(is_array($c)&&time()-($c['t']??0)<GSC_CACHE_TTL)return $c['rows'];}
 if($mock=getenv('GSC_MOCK_DIR')){ // test fixtures: <dims>.json
  $rows=json_decode((string)@file_get_contents($mock.'/'.(implode('-',$dimensions)?:'totals').'.json'),true)['rows']??[];
 }else{
  $r=gscHttp('https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode($property).'/searchAnalytics/query',[CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.gscToken(),'Content-Type: application/json'],
   CURLOPT_POSTFIELDS=>json_encode(['startDate'=>$start,'endDate'=>$end,'dimensions'=>$dimensions,'rowLimit'=>$limit,'dataState'=>'all'])]);
  $rows=$r['rows']??[];
 }
 run('INSERT INTO settings(key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value',[$ckey,json_encode(['t'=>time(),'rows'=>$rows])]);
 return $rows;
}

// Typical click-through rate for a Google position (approximate industry curve) to flag weak titles.
function expectedCtr(float $pos): float { return match(true){$pos<1.5=>0.28,$pos<2.5=>0.15,$pos<3.5=>0.10,$pos<4.5=>0.07,$pos<5.5=>0.05,$pos<7.5=>0.035,$pos<10.5=>0.025,default=>0.01}; }

// Post id for a Search Console page URL (https://host/slug), if it is one of our articles.
function postForUrl(string $url): ?array {
 static $map=null;
 if($map===null){$map=[];foreach(query("SELECT id,slug,title FROM reviews WHERE status!='trash'") as $r)$map[$r['slug']]=$r;}
 $slug=rawurldecode(trim((string)parse_url($url,PHP_URL_PATH),'/'));
 return $map[$slug]??null;
}

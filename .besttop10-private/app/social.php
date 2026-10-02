<?php
declare(strict_types=1);
// Pinterest auto-posting (API v5). Setup: developers.pinterest.com → create an app → generate an access token
// with the scopes boards:read, pins:read, pins:write → paste it in Admin › SEO & Code › Pinterest.
// Each post is pinned once (logged in social_posts); "Pin now" in the posts list can retry or re-pin.

function pinterestToken(): string { return trim(setting('pinterest_token')); }

function pinterestApi(string $method, string $path, ?array $body=null): array {
 $ch=curl_init('https://api.pinterest.com/v5'.$path);
 curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>8,
  CURLOPT_HTTPHEADER=>['Authorization: Bearer '.pinterestToken(),'Content-Type: application/json']]+($body!==null?[CURLOPT_POSTFIELDS=>json_encode($body)]:[]));
 $raw=(string)curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
 $data=json_decode($raw,true);
 if($code<200||$code>=300)throw new RuntimeException('Pinterest: '.($data['message']??($err?:"HTTP $code")));
 return is_array($data)?$data:[];
}

function pinterestBoards(): array {
 $out=[];$bookmark=null;
 do{$r=pinterestApi('GET','/boards?page_size=100'.($bookmark?'&bookmark='.rawurlencode($bookmark):''));foreach($r['items']??[] as $b)$out[$b['id']]=$b['name'];$bookmark=$r['bookmark']??null;}while($bookmark&&count($out)<500);
 return $out;
}

// Pin a live post to the configured board. Returns the pin id; logs the result either way.
function pinPost(int $postId): string {
 $p=query('SELECT r.*,c.name AS category FROM reviews r JOIN categories c ON c.id=r.category_id WHERE r.id=? AND '.live(),[$postId])[0]??null;
 if(!$p)throw new RuntimeException('Only published posts can be pinned.');
 $board=setting('pinterest_board');if(pinterestToken()===''||$board==='')throw new RuntimeException('Connect Pinterest and choose a board first.');
 $img=shareImage($p,'pin');if(!$img)throw new RuntimeException('Could not create the pin image.');
 $desc=trim(($p['tldr']??'')!==''?$p['tldr']:$p['excerpt']);
 try{
  $r=pinterestApi('POST','/pins',['board_id'=>$board,'title'=>mb_substr($p['meta_title']?:$p['title'],0,100),'description'=>mb_substr($desc,0,500),'link'=>siteBase().reviewUrl($p).'?utm_source=pinterest&utm_medium=social',
   'alt_text'=>mb_substr($p['title'],0,500),'media_source'=>['source_type'=>'image_url','url'=>siteBase().$img]]);
  run('INSERT INTO social_posts(post_id,network,status,remote_id,message,created_at) VALUES (?,?,?,?,?,?)',[$postId,'pinterest','ok',(string)($r['id']??''),'',date('c')]);
  return (string)($r['id']??'');
 }catch(RuntimeException $e){
  run('INSERT INTO social_posts(post_id,network,status,remote_id,message,created_at) VALUES (?,?,?,?,?,?)',[$postId,'pinterest','error','',mb_substr($e->getMessage(),0,300),date('c')]);
  throw $e;
 }
}
function pinnedAlready(int $postId): bool { return (bool)query("SELECT 1 FROM social_posts WHERE post_id=? AND network='pinterest' AND status='ok' LIMIT 1",[$postId]); }

// Called after publishing: auto-pin when enabled. Never blocks publishing.
function autoSocial(int $postId): ?string {
 if(setting('pinterest_auto')!=='1'||pinterestToken()===''||setting('pinterest_board')===''||pinnedAlready($postId))return null;
 try{pinPost($postId);return 'Pinned to Pinterest.';}catch(Throwable $e){return 'Pinterest: '.$e->getMessage();}
}

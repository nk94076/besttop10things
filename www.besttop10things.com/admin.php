<?php
require __DIR__.'/../.besttop10-private/app/bootstrap.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
const UPLOAD_DIR = __DIR__.'/uploads';
const PER_PAGE = 10;
$error='';$view=(string)($_GET['view']??'dashboard');
$logged=isset($_SESSION['admin']);
if($logged && time()-($_SESSION['active']??0)>3600){unset($_SESSION['admin']);$logged=false;}
if($logged)$_SESSION['active']=time();

function flash(string $message, string $to): never { $_SESSION['flash']=$message; redirect($to); }
function backTo(string $fallback): string { $r=(string)($_POST['return']??''); return str_starts_with($r,'/admin.php')?$r:$fallback; }
function plainText(string $md): string { return trim(preg_replace('/\s+/',' ',str_replace(['**','#'],'',preg_replace('~!?\[([^\]]*)\]\([^)]*\)~','$1',$md)))); }
function autoExcerpt(string $body): string { $t=plainText($body); return mb_strlen($t)>200?rtrim(mb_substr($t,0,197)).'…':$t; }
function postState(array $r): string { return $r['status']==='published'&&($r['published_at']??'')>now()?'scheduled':$r['status']; }
function storeUpload(array $file): string {
 if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||$file['size']>5*1024*1024)throw new RuntimeException('Upload a JPG, PNG or WebP image under 5 MB.');
 $info=@getimagesize($file['tmp_name']);$extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
 if(!$info||!isset($extensions[$info['mime']])||$info[0]>10000||$info[1]>10000)throw new RuntimeException('Choose a valid JPG, PNG or WebP image, up to 10,000 pixels per side.');
 if(!is_dir(UPLOAD_DIR)&&!mkdir(UPLOAD_DIR,0755,true))throw new RuntimeException('Image storage is unavailable.');
 $name=bin2hex(random_bytes(16)).'.'.$extensions[$info['mime']];
 if(!move_uploaded_file($file['tmp_name'],UPLOAD_DIR.'/'.$name))throw new RuntimeException('The image could not be saved.');
 // Smaller, faster images: convert to WebP (max 1920px wide) and drop the original.
 $webp=toWebp(UPLOAD_DIR,$name);
 if($webp!==$name){@unlink(UPLOAD_DIR.'/'.$name);$name=$webp;}
 return '/uploads/'.$name;
}
function mediaFiles(): array {
 $files=[];
 foreach(glob(UPLOAD_DIR.'/*.{jpg,png,webp}',GLOB_BRACE)?:[] as $path) if(preg_match('~/([a-f0-9]{32}\.(jpg|png|webp))$~',$path,$m)) $files[]=['url'=>'/uploads/'.$m[1],'name'=>$m[1],'size'=>filesize($path),'time'=>filemtime($path)];
 usort($files,fn($a,$b)=>$b['time']<=>$a['time']);
 return $files;
}
function libraryImages(): array {
 $images=array_column(mediaFiles(),'url');
 foreach(glob(__DIR__.'/assets/{,articles/}*.{jpg,svg}',GLOB_BRACE)?:[] as $p) if(basename($p)!=='favicon.svg') $images[]=substr($p,strlen(__DIR__));
 return $images;
}
function aicon(string $name, string $class='icon'): string {
 static $p=['trophy'=>'<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0zM7 6H4a3 3 0 0 0 3 4M17 6h3a3 3 0 0 1-3 4"/>','bulb'=>'<path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2V16h5v-.1c0-.8.4-1.5 1-2A6 6 0 0 0 12 3z"/>','code'=>'<path d="m8 8-4 4 4 4M16 8l4 4-4 4M13.5 5l-3 14"/>','home'=>'<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z" fill="currentColor" stroke="none"/>','file'=>'<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>','image'=>'<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>','folder'=>'<path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>','users'=>'<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/>','gear'=>'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>','chart'=>'<path d="M4 20V10M10 20V4M16 20v-7M21 20H3"/>','pen'=>'<path d="M4 20h16M14.5 4.5l3 3L8 17H5v-3z"/>','clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>','trash'=>'<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6"/>','send'=>'<path d="M21 3 10 14M21 3l-7 18-4-7-7-4z"/>','calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>','right'=>'<path d="m9 6 6 6-6 6"/>','down'=>'<path d="m6 9 6 6 6-6"/>','plus'=>'<path d="M12 5v14M5 12h14"/>','search'=>'<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>','sun'=>'<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>','user'=>'<circle cx="12" cy="8" r="4" fill="currentColor" stroke="none"/><path d="M4 21a8 8 0 0 1 16 0z" fill="currentColor" stroke="none"/>','logout'=>'<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h11"/>','menu'=>'<path d="M3 6h18M3 12h18M3 18h18"/>','crown'=>'<path d="M3 8l4.5 4L12 5l4.5 7L21 8l-2 11H5z" fill="currentColor" stroke="none"/>'];
 return '<svg class="'.e($class).'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($p[$name]??$p['file']).'</svg>';
}
function pageHead(string $icon, string $title, string $sub, string $actions=''): string {
 return '<div class="page-head panel-head"><div class="head-with-icon"><span class="head-icon">'.aicon($icon).'</span><div><h1 class="page-title">'.e($title).'</h1><p class="page-sub">'.e($sub).'</p></div></div>'.($actions?'<div class="head-actions">'.$actions.'</div>':'').'</div>';
}
// Filters for the Clicks screen, read from the query string. Returns [SQL where, params, values].
function clickFilters(): array {
 $f=['from'=>(string)($_GET['from']??date('Y-m-d',strtotime('-29 days'))),'to'=>(string)($_GET['to']??date('Y-m-d')),'post'=>(int)($_GET['post']??0),'link'=>(int)($_GET['link']??0),'source'=>trim((string)($_GET['source']??'')),'device'=>trim((string)($_GET['device']??'')),'placement'=>trim((string)($_GET['placement']??'')),'country'=>trim((string)($_GET['country']??'')),'q'=>trim((string)($_GET['q']??'')),'bots'=>!empty($_GET['bots']),'admins'=>!empty($_GET['admins'])];
 foreach(['from','to'] as $k)if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$f[$k]))$f[$k]=$k==='from'?date('Y-m-d',strtotime('-29 days')):date('Y-m-d');
 $w=['k.created_at>=?','k.created_at<?'];$p=[$f['from'],date('Y-m-d',strtotime($f['to'].' +1 day'))];
 if(!$f['bots'])$w[]='k.is_bot=0';
 if(!$f['admins'])$w[]='k.is_admin=0';
 foreach(['post'=>'k.post_id','link'=>'k.link_id'] as $k=>$col)if($f[$k]){$w[]="$col=?";$p[]=$f[$k];}
 foreach(['source'=>'k.source','device'=>'k.device','placement'=>'k.placement','country'=>'k.country'] as $k=>$col)if($f[$k]!==''){$w[]="$col=?";$p[]=$f[$k];}
 if($f['q']!==''){$w[]='(l.url LIKE ? OR k.visitor=? OR k.ip=? OR k.anchor LIKE ? OR k.referrer LIKE ?)';array_push($p,"%{$f['q']}%",$f['q'],$f['q'],"%{$f['q']}%","%{$f['q']}%");}
 return [implode(' AND ',$w),$p,$f];
}
function clickUrl(array $f, array $over=[]): string {
 $q=array_merge($f,$over);$q['bots']=$q['bots']?1:null;$q['admins']=$q['admins']?1:null;
 return '/admin.php?'.http_build_query(array_filter(['view'=>'clicks']+$q,fn($v)=>$v!==null&&$v!==''&&$v!==0));
}
function imageInUse(string $url): bool { return (bool)query('SELECT id FROM reviews WHERE image=? OR instr(body,?)>0 LIMIT 1',[$url,$url]); }
function savePost(array $in, int $id): int {
 $title=trim((string)($in['title']??''));
 if($title===''||strlen($title)>200)throw new RuntimeException('Enter a title of 1–200 characters.');
 $category=(int)($in['category_id']??0);
 if(!query('SELECT id FROM categories WHERE id=?',[$category]))throw new RuntimeException('Choose a valid category.');
 $score=filter_var(($in['score']??'')===''?0:$in['score'],FILTER_VALIDATE_FLOAT);
 if($score===false||$score<0||$score>10)throw new RuntimeException('Score must be between 0 and 10.');
 $body=trim((string)($in['body']??''));if($body==='')throw new RuntimeException('Write some content before saving.');
 $image=trim((string)($in['image']??''))?:'/assets/hero.jpg';
 if(!safeImage($image))throw new RuntimeException('Featured image must be an HTTPS URL or an image from the media library.');
 $slug=slug(trim((string)($in['slug']??''))?:$title);
 if(in_array($slug,RESERVED_SLUGS,true))throw new RuntimeException("The permalink “{$slug}” is reserved for a site page. Choose another.");
 if(query('SELECT id FROM reviews WHERE slug=? AND id!=?',[$slug,$id]))throw new RuntimeException('This permalink is already used. Choose another.');
 $date=(string)($in['published_at']??'');
 $publishedAt=preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',$date)?$date.':00':now();
 $status=in_array($in['status']??'',['published','draft'],true)?$in['status']:'draft';
 $meta=[trim((string)($in['meta_title']??'')),trim((string)($in['meta_description']??''))];
 if(strlen($meta[0])>200||strlen($meta[1])>500)throw new RuntimeException('SEO title or description is too long.');
 $brand=[trim((string)($in['brand']??'')),trim((string)($in['brand_about']??'')),trim((string)($in['cta_url']??''))];
 if($brand[2]!==''&&!preg_match('~^https://\S+$~i',$brand[2]))throw new RuntimeException('The brand link must start with https://.');
 if(strlen($brand[0])>80||strlen($brand[1])>500)throw new RuntimeException('Brand name or description is too long.');
 $adv=[trim((string)($in['focus_keyword']??'')),trim((string)($in['seo_canonical']??'')),(string)($in['seo_robots']??''),trim((string)($in['og_image']??'')),(string)($in['schema_type']??''),trim((string)($in['tldr']??'')),trim(str_replace("\r",'',(string)($in['takeaways']??''))),trim((string)($in['custom_schema']??''))];
 if(strlen($adv[0])>100)throw new RuntimeException('Focus keyword is too long (100 characters max).');
 if($adv[1]!==''&&!preg_match('~^https://\S+$~i',$adv[1]))throw new RuntimeException('The canonical URL must be a full https:// address.');
 if(!in_array($adv[2],['','noindex, follow','index, nofollow','noindex, nofollow'],true))$adv[2]='';
 if($adv[3]!==''&&!safeImage($adv[3]))throw new RuntimeException('The social share image must be an HTTPS URL or an image from the media library.');
 if(!array_key_exists($adv[4],SCHEMA_TYPES))$adv[4]='';
 if(strlen($adv[5])>700||strlen($adv[6])>2000)throw new RuntimeException('The quick answer or key takeaways are too long.');
 if($adv[7]!==''&&(strlen($adv[7])>20000||!is_array(json_decode($adv[7],true))))throw new RuntimeException('Custom schema must be valid JSON-LD (a JSON object), e.g. {"@type":"Product",…}.');
 $values=[$category,$title,$slug,trim((string)($in['excerpt']??''))?:autoExcerpt($body),$body,$image,$score,trim((string)($in['pros']??'')),trim((string)($in['cons']??'')),trim((string)($in['verdict']??'')),trim((string)($in['author']??''))?:'Editorial team',$status,empty($in['featured'])?0:1,empty($in['demo'])?0:1,$meta[0],$meta[1],...$brand,...$adv,$publishedAt,date('c')];
 $cols='category_id=?,title=?,slug=?,excerpt=?,body=?,image=?,score=?,pros=?,cons=?,verdict=?,author=?,status=?,featured=?,demo=?,meta_title=?,meta_description=?,brand=?,brand_about=?,cta_url=?,focus_keyword=?,seo_canonical=?,seo_robots=?,og_image=?,schema_type=?,tldr=?,takeaways=?,custom_schema=?,published_at=?,updated_at=?';
 if($id){
  if(!query('SELECT id FROM reviews WHERE id=?',[$id]))throw new RuntimeException('Post not found.');
  $before=query('SELECT slug,status FROM reviews WHERE id=?',[$id])[0];
  run("UPDATE reviews SET $cols WHERE id=?",[...$values,$id]);
  // A published post that changes its URL keeps its old links working (and its Google ranking).
  if($before['slug']!==$slug&&$before['status']==='published')addRedirect('/'.$before['slug'],'/'.$slug);
  return $id;
 }
 run('INSERT INTO reviews(category_id,title,slug,excerpt,body,image,score,pros,cons,verdict,author,status,featured,demo,meta_title,meta_description,brand,brand_about,cta_url,focus_keyword,seo_canonical,seo_robots,og_image,schema_type,tldr,takeaways,custom_schema,published_at,updated_at,created_at) VALUES ('.implode(',',array_fill(0,30,'?')).')',[...$values,date('c')]);
 return (int)db()->lastInsertId();
}

if($_SERVER['REQUEST_METHOD']==='POST'){
 checkCsrf(); $action=(string)($_POST['action']??'');
 try {
  if($action==='login'){
   $ip=$_SERVER['REMOTE_ADDR']??'local';$attempt=query('SELECT * FROM login_attempts WHERE ip=?',[$ip])[0]??null;
   if($attempt && (int)$attempt['attempts']>=5 && time()-(int)$attempt['last_at']<900)throw new RuntimeException('Too many attempts. Please try again in 15 minutes.');
   $user=query('SELECT * FROM admins WHERE email=?',[strtolower(trim((string)($_POST['email']??'')))])[0]??null;
   if(!$user||!password_verify((string)($_POST['password']??''),$user['password'])){
    $count=$attempt&&time()-(int)$attempt['last_at']<900?(int)$attempt['attempts']+1:1;
    run('INSERT INTO login_attempts(ip,attempts,last_at) VALUES (?,?,?) ON CONFLICT(ip) DO UPDATE SET attempts=excluded.attempts,last_at=excluded.last_at',[$ip,$count,time()]);
    throw new RuntimeException('Email or password is incorrect.');
   }
   run('DELETE FROM login_attempts WHERE ip=?',[$ip]);session_regenerate_id(true);$_SESSION['admin']=$user['id'];$_SESSION['active']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));redirect('/admin.php');
  }
  if(!$logged){http_response_code(403);throw new RuntimeException('Please sign in.');}
  if($action==='logout'){$_SESSION=[];session_destroy();redirect('/admin.php');}
  if($action==='preview'){header('Content-Type: text/html; charset=utf-8');echo renderBody((string)($_POST['body']??''));exit;}

  if($action==='save_post'){
   $in=$_POST;$id=(int)($in['id']??0);
   $in['status']=($in['intent']??'')==='publish'?'published':'draft';
   $upload=$_FILES['image_upload']??null;
   if($upload&&($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$in['image']=storeUpload($upload);
   elseif(trim((string)($in['image']??''))===''&&!empty($in['image_pick']))$in['image']=(string)$in['image_pick'];
   $id=savePost($in,$id);
   $state=postState(query('SELECT status,published_at FROM reviews WHERE id=?',[$id])[0]);
   if($state==='published')indexNowPing([reviewUrl(query('SELECT slug FROM reviews WHERE id=?',[$id])[0]),'/','/sitemap.xml']);
   $social=$state==='published'?autoSocial($id):null;
   flash(['published'=>'Post published.','scheduled'=>'Post scheduled.','draft'=>'Draft saved.'][$state].($social?' '.$social:''),'/admin.php?view=edit&id='.$id);
  }
  if($action==='quick_draft'){
   $cat=(int)db()->query('SELECT id FROM categories ORDER BY id LIMIT 1')->fetchColumn();
   $id=savePost(['title'=>$_POST['title']??'','body'=>$_POST['body']??'','category_id'=>$cat,'status'=>'draft'],0);
   flash('Draft saved. Add a category and image when you are ready.','/admin.php?view=edit&id='.$id);
  }
  if($action==='post_status'||$action==='bulk'){
   $ids=array_map('intval',$action==='bulk'?(array)($_POST['ids']??[]):[(int)($_POST['id']??0)]);
   $do=(string)($_POST[$action==='bulk'?'bulk_action':'do']??'');
   if(!$ids||!array_filter($ids))throw new RuntimeException('Select at least one post.');
   $in=implode(',',array_fill(0,count($ids),'?'));
   $map=['publish'=>"status='published'",'draft'=>"status='draft'",'trash'=>"status='trash'",'restore'=>"status='draft'"];
   if(isset($map[$do]))run("UPDATE reviews SET {$map[$do]},updated_at=? WHERE id IN ($in)",[date('c'),...$ids]);
   elseif($do==='delete')run("DELETE FROM reviews WHERE status='trash' AND id IN ($in)",$ids);
   else throw new RuntimeException('Choose a bulk action.');
   if($do==='publish')indexNowPing(array_map('reviewUrl',query("SELECT slug FROM reviews r WHERE id IN ($in) AND ".live(),$ids)));
   $n=count($ids);$labels=['publish'=>'published','draft'=>'moved to drafts','trash'=>'moved to the Trash','restore'=>'restored from the Trash','delete'=>'permanently deleted'];
   flash("$n post".($n>1?'s':'')." {$labels[$do]}.",backTo('/admin.php?view=posts'));
  }
  if($action==='ai_test'){
   $prov=($_POST['provider']??'')==='claude'?'claude':'gemini';
   $set=fn($v)=>run('INSERT INTO settings(key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value',['ai_status_'.$prov,$v]);
   try{$msg=aiTest($prov);$set('ok|'.$msg.'|'.date('c'));flash('✓ '.providerName($prov).' is working: '.$msg.'.','/admin.php?view=seo#ai');}
   catch(Throwable $e){$set('error|'.$e->getMessage().'|'.date('c'));throw new RuntimeException(providerName($prov).' test failed: '.$e->getMessage());}
  }
  if($action==='ai_key'){
   $save=fn($k,$v)=>run('INSERT INTO settings(key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value',[$k,$v]);
   if(isset($_POST['remove_gemini'])){@unlink(GEMINI_KEY_FILE);$save('ai_status_gemini','');flash('Gemini key removed.','/admin.php?view=seo#ai');}
   if(isset($_POST['remove_claude'])){@unlink(AI_KEY_FILE);$save('ai_status_claude','');flash('Claude key removed.','/admin.php?view=seo#ai');}
   $prov=($_POST['ai_provider']??'')==='claude'?'claude':'gemini';$save('ai_provider',$prov);
   $draft=in_array($_POST['ai_draft_provider']??'',['gemini','claude'],true)?$_POST['ai_draft_provider']:'';$save('ai_draft_provider',$draft);
   $save('ai_fallback',isset($_POST['ai_fallback'])?'1':'0');
   $gm=trim((string)($_POST['gemini_model']??''));if($gm!==''&&!preg_match('/^[a-z0-9.\-]{3,60}$/',$gm))throw new RuntimeException('Gemini model names look like gemini-flash-latest.');$save('gemini_model',$gm);
   $ws=trim((string)($_POST['ai_workspace_id']??''));
   if($ws!==''&&!preg_match('/^wrkspc_[A-Za-z0-9]{10,60}$/',$ws))throw new RuntimeException('A workspace ID looks like wrkspc_… (Console › Settings › Workspaces).');
   $save('ai_workspace_id',$ws);
   $key=trim((string)($_POST['anthropic_key']??''));if($key!==''){aiSaveKey($key);$save('ai_status_claude','');}
   $gk=trim((string)($_POST['gemini_key']??''));if($gk!==''){geminiSaveKey($gk);$save('ai_status_gemini','');}
   if(!aiAvailable())throw new RuntimeException('Paste at least one key: Gemini (free) or Claude.');
   flash('AI settings saved. Click “Test” next to each AI to check it works.','/admin.php?view=seo#ai');
  }
  if($action==='merge_post'){
   $src=(int)($_POST['id']??0);$dst=(int)($_POST['into']??0);
   $a=query('SELECT id,slug,title FROM reviews WHERE id=?',[$src])[0]??null;$b=query('SELECT id,slug,title FROM reviews WHERE id=? AND '.str_replace('r.','',live()),[$dst])[0]??null;
   if(!$a||!$b||$src===$dst)throw new RuntimeException('Choose a different, published post to merge into.');
   saveRevision(query('SELECT * FROM reviews WHERE id=?',[$src])[0],'Merged into: '.$b['title']);
   run("UPDATE reviews SET status='trash',updated_at=? WHERE id=?",[date('c'),$src]);
   addRedirect('/'.$a['slug'],'/'.$b['slug']);
   indexNowPing(['/'.$a['slug'],'/'.$b['slug']]);
   flash('“'.$a['title'].'” now redirects (301) to “'.$b['title'].'” and was moved to the Trash. Copy any unique tips from it into the remaining post.','/admin.php?view=edit&id='.$dst);
  }
  if(in_array($action,['advisor_settings','refresh_ideas','idea_status','idea_draft','ai_fixes','apply_fixes','discard_fixes','undo_revision'],true)){
   $tab=(string)($_POST['tab']??'doctor');$back='/admin.php?view=advisor&tab='.rawurlencode($tab);
   if($action==='advisor_settings'){foreach(['advisor_weekly_fixes','advisor_auto_meta','advisor_auto_apply'] as $k)run('INSERT INTO settings(key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value',[$k,isset($_POST[$k])?'1':'0']);flash('Content Advisor settings saved.',$back);}
   if($action==='refresh_ideas'){@set_time_limit(300);$n=refreshKeywordIdeas();flash("$n keyword ideas checked.",$back);}
   if($action==='idea_status'){$st=(string)($_POST['status']??'');if(!in_array($st,['new','dismissed'],true))$st='new';run('UPDATE keyword_ideas SET status=? WHERE id=?',[$st,(int)($_POST['id']??0)]);flash($st==='dismissed'?'Idea hidden.':'Idea restored.',$back);}
   if($action==='idea_draft'){
    $iid=(int)($_POST['id']??0);run("UPDATE keyword_ideas SET status='queued' WHERE id=? AND status IN ('new','dismissed')",[$iid]);
    processAiQueue(1);$pid=(int)(query('SELECT post_id FROM keyword_ideas WHERE id=? AND status=\'drafted\'',[$iid])[0]['post_id']??0);
    if($pid)flash('Draft written. Review it, add your affiliate links and an image, then publish.','/admin.php?view=edit&id='.$pid);
    flash('The draft is being written in the background. Refresh this page in a minute.',$back);
   }
   if($action==='ai_fixes'){$pid=(int)($_POST['id']??0);queueFixes($pid);$log=processAiQueue(1);flash(($log?end($log):'Working on it… refresh in a minute.'),$back.'#post-'.$pid);}
   if($action==='discard_fixes'){run('DELETE FROM ai_suggestions WHERE post_id=?',[(int)($_POST['id']??0)]);flash('Suggestion discarded.',$back);}
   if($action==='apply_fixes'){$pid=(int)($_POST['id']??0);flash(applyFixes($pid,['meta'=>isset($_POST['meta']),'tldr'=>isset($_POST['tldr']),'sections'=>(array)($_POST['sections']??[]),'faq'=>(array)($_POST['faq']??[])]).' You can undo it under History.',$back.'#post-'.$pid);}
   if($action==='undo_revision'){flash(undoRevision((int)($_POST['id']??0)),'/admin.php?view=advisor&tab=history');}
  }
  if($action==='gsc_settings'){
   $prop=trim((string)($_POST['gsc_property']??''));
   if($prop!==''&&!preg_match('~^(sc-domain:[a-z0-9.-]+|https?://[^\s]+/)$~i',$prop))throw new RuntimeException('Use the property exactly as in Search Console, e.g. sc-domain:besttop10things.com or https://www.besttop10things.com/');
   run('INSERT INTO settings(key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value',['gsc_property',$prop]);
   $json=trim((string)($_POST['gsc_key']??''));if($json!=='')gscSaveKey($json);
   if(isset($_POST['gsc_disconnect'])){@unlink(GSC_KEY_FILE);run('DELETE FROM settings WHERE key LIKE ?',['gsc_cache_%']);}
   flash('Search Console settings saved.','/admin.php?view=search');
  }
  if($action==='pin_post'){
   $pid=(int)($_POST['id']??0);pinPost($pid);
   flash('Pinned to Pinterest.',backTo('/admin.php?view=posts'));
  }
  if($action==='pinterest'){
   $token=trim((string)($_POST['pinterest_token']??''));
   $save=fn($k,$v)=>run('INSERT INTO settings(key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value',[$k,$v]);
   if(isset($_POST['disconnect'])){foreach(['pinterest_token','pinterest_board','pinterest_boards','pinterest_auto'] as $k)$save($k,'');flash('Pinterest disconnected.','/admin.php?view=seo#pinterest');}
   if($token!==''){if(!preg_match('/^[A-Za-z0-9_\-.:]{20,2000}$/',$token))throw new RuntimeException('That does not look like a Pinterest access token.');$save('pinterest_token',$token);}
   if(pinterestToken()==='')throw new RuntimeException('Paste a Pinterest access token.');
   $boards=pinterestBoards();$save('pinterest_boards',json_encode($boards,JSON_UNESCAPED_UNICODE));
   $board=(string)($_POST['pinterest_board']??'');if($board!==''&&!isset($boards[$board]))throw new RuntimeException('Choose one of your boards.');
   $save('pinterest_board',$board!==''?$board:(string)(array_key_first($boards)??''));
   $save('pinterest_auto',isset($_POST['pinterest_auto'])?'1':'0');
   flash('Pinterest connected: '.count($boards).' board'.(count($boards)===1?'':'s').' found.','/admin.php?view=seo#pinterest');
  }
  if($action==='duplicate'){
   $r=query('SELECT * FROM reviews WHERE id=?',[(int)($_POST['id']??0)])[0]??null;if(!$r)throw new RuntimeException('Post not found.');
   $base=$r['slug'].'-copy';$slug=$base;for($i=2;query('SELECT id FROM reviews WHERE slug=?',[$slug]);$i++)$slug="$base-$i";
   $id=savePost(['title'=>$r['title'].' (copy)','slug'=>$slug,'status'=>'draft','featured'=>0,'published_at'=>'','seo_canonical'=>'']+$r,0);
   flash('Post duplicated as a draft.','/admin.php?view=edit&id='.$id);
  }

  if($action==='upload_media'){
   $files=$_FILES['files']??null;$n=0;
   if($files&&is_array($files['name']))foreach(array_keys($files['name']) as $i){if($files['error'][$i]===UPLOAD_ERR_NO_FILE)continue;storeUpload(['name'=>$files['name'][$i],'tmp_name'=>$files['tmp_name'][$i],'error'=>$files['error'][$i],'size'=>$files['size'][$i]]);$n++;}
   if(!$n)throw new RuntimeException('Choose one or more images to upload.');
   flash("$n image".($n>1?'s':'').' uploaded.','/admin.php?view=media');
  }
  if($action==='delete_media'){
   $name=(string)($_POST['name']??'');if(!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/',$name)||!is_file(UPLOAD_DIR.'/'.$name))throw new RuntimeException('Image not found.');
   if(imageInUse('/uploads/'.$name))throw new RuntimeException('This image is used by a post. Replace it in the post first.');
   unlink(UPLOAD_DIR.'/'.$name);flash('Image deleted.','/admin.php?view=media');
  }

  if($action==='save_category'){
   $name=trim((string)($_POST['name']??''));$id=(int)($_POST['id']??0);if($name===''||strlen($name)>60)throw new RuntimeException('Category name must be 1–60 characters.');$slug=slug($name);
   if(query('SELECT id FROM categories WHERE slug=? AND id!=?',[$slug,$id]))throw new RuntimeException('A category with this name already exists.');
   $icon=(string)($_POST['icon']??'grid');if(!in_array($icon,['tech','shopping','travel','gadgets','home','grid'],true))$icon='grid';
   if($id)run('UPDATE categories SET name=?,slug=?,icon=? WHERE id=?',[$name,$slug,$icon,$id]);else run('INSERT INTO categories(name,slug,icon) VALUES (?,?,?)',[$name,$slug,$icon]);
   if($id&&isset($_POST['intro'])){$intro=trim(str_replace("\r",'',(string)$_POST['intro']));if(strlen($intro)>8000)throw new RuntimeException('The hub introduction is too long.');run('UPDATE categories SET intro=? WHERE id=?',[$intro,$id]);}
   flash('Category saved.','/admin.php?view=categories');
  }
  if($action==='delete_category'){
   $id=(int)($_POST['id']??0);if(query('SELECT id FROM reviews WHERE category_id=? LIMIT 1',[$id]))throw new RuntimeException('Move or delete this category’s posts first, including drafts and the Trash.');run('DELETE FROM categories WHERE id=?',[$id]);flash('Category deleted.','/admin.php?view=categories');
  }

  if($action==='add_user'){
   $email=strtolower(trim((string)($_POST['email']??'')));$password=(string)($_POST['password']??'');
   if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');
   if(strlen($password)<12)throw new RuntimeException('Password must be at least 12 characters.');
   if(query('SELECT id FROM admins WHERE email=?',[$email]))throw new RuntimeException('A user with this email already exists.');
   run('INSERT INTO admins(email,password) VALUES (?,?)',[$email,password_hash($password,PASSWORD_DEFAULT)]);flash('User added.','/admin.php?view=users');
  }
  if($action==='delete_user'){
   $id=(int)($_POST['id']??0);if($id===(int)$_SESSION['admin'])throw new RuntimeException('You cannot delete your own account.');
   run('DELETE FROM admins WHERE id=?',[$id]);flash('User deleted.','/admin.php?view=users');
  }
  if($action==='settings'){
   foreach(['site_name','tagline','description'] as $k){$value=trim((string)($_POST[$k]??''));if($value===''||strlen($value)>500)throw new RuntimeException('Complete all settings (maximum 500 characters each).');}
   foreach(['site_name','tagline','description'] as $k)run('INSERT INTO settings(key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value',[$k,trim($_POST[$k])]);
   flash('Settings saved.','/admin.php?view=settings');
  }
  if($action==='appearance'){
   $save=fn(string $k,string $v)=>run('INSERT INTO settings(key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value',[$k,$v]);
   // Logo and social image: upload wins, then library pick, then URL field; a remove box clears it.
   foreach(['logo','og_image'] as $k){
    $upload=$_FILES[$k.'_upload']??null;
    if(!empty($_POST[$k.'_remove']))$value='';
    elseif($upload&&($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$value=storeUpload($upload);
    elseif(!empty($_POST[$k.'_pick']))$value=trim((string)$_POST[$k.'_pick']);
    else $value=trim((string)($_POST[$k]??''));
    if($value!==''&&!safeImage($value))throw new RuntimeException('Logo and social image must be HTTPS URLs or images from the media library.');
    $save($k,$value);
   }
   $menu=[];
   foreach((array)($_POST['menu_label']??[]) as $i=>$label){
    $label=trim((string)$label);$url=trim((string)($_POST['menu_url'][$i]??''));$type=($_POST['menu_type'][$i]??'')==='categories'?'categories':'link';
    if($label===''&&$url==='')continue;
    if($label===''||strlen($label)>40)throw new RuntimeException('Each menu label must be 1–40 characters.');
    if(!safeMenuUrl($url))throw new RuntimeException("The link for “{$label}” must start with / or https://.");
    $menu[]=['label'=>$label,'url'=>$url,'type'=>$type];
   }
   if(!$menu)throw new RuntimeException('Add at least one menu item.');
   if(count($menu)>10)throw new RuntimeException('Use at most 10 menu items.');
   $save('menu',json_encode($menu,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
   $norm=fn($v)=>mb_strtolower(trim(preg_replace('/[\s[:punct:]]+/u',' ',$v)));if(trim((string)($_POST['seo_title']??''))!==''&&$norm((string)$_POST['seo_title'])===$norm(setting('description')))throw new RuntimeException('The homepage title repeats the meta description. Use a short branded title, e.g. “'.setting('site_name').' | Reviews, Comparisons & Buying Guides”.');
   foreach(['seo_title'=>200,'meta_keywords'=>500,] as $k=>$max){$v=trim((string)($_POST[$k]??''));if(strlen($v)>$max)throw new RuntimeException('An SEO field is too long.');$save($k,$v);}
   flash('Appearance saved.','/admin.php?view=appearance');
  }
  if($action==='save_author'){
   $aid=(int)($_POST['id']??0);$name=trim((string)($_POST['name']??''));
   if($name===''||mb_strlen($name)>80)throw new RuntimeException('Enter a name of 1–80 characters.');
   $slugA=slug((string)($_POST['slug']??'')?:$name);
   if(query('SELECT id FROM authors WHERE (slug=? OR lower(name)=lower(?)) AND id!=?',[$slugA,$name,$aid]))throw new RuntimeException('Another author already uses this name or URL.');
   $f=[];foreach(['role'=>120,'bio'=>3000,'expertise'=>300,'links'=>2000] as $k=>$max){$f[$k]=trim(str_replace("\r",'',(string)($_POST[$k]??'')));if(mb_strlen($f[$k])>$max)throw new RuntimeException(ucfirst($k).' is too long.');}
   foreach(array_filter(explode("\n",$f['links']),'trim') as $u)if(!preg_match('~^https://\S+$~',trim($u)))throw new RuntimeException('Profile links must each start with https://.');
   $avatar=trim((string)($_POST['avatar']??''));
   $up=$_FILES['avatar_upload']??null;if($up&&($up['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)$avatar=storeUpload($up);
   if($avatar!==''&&!safeImage($avatar))throw new RuntimeException('The photo must be an uploaded image or an https:// URL.');
   $old=$aid?(query('SELECT name FROM authors WHERE id=?',[$aid])[0]['name']??null):null;
   if($aid&&$old===null)throw new RuntimeException('Author not found.');
   if($aid)run('UPDATE authors SET name=?,slug=?,role=?,bio=?,avatar=?,expertise=?,links=? WHERE id=?',[$name,$slugA,$f['role'],$f['bio'],$avatar,$f['expertise'],$f['links'],$aid]);
   else{run('INSERT INTO authors(name,slug,role,bio,avatar,expertise,links,created_at) VALUES (?,?,?,?,?,?,?,?)',[$name,$slugA,$f['role'],$f['bio'],$avatar,$f['expertise'],$f['links'],date('c')]);$aid=(int)db()->lastInsertId();}
   // Renaming an author keeps their posts attached.
   if($old!==null&&$old!==$name)run('UPDATE reviews SET author=? WHERE lower(author)=lower(?)',[$name,$old]);
   flash('Author saved.','/admin.php?view=authors&id='.$aid);
  }
  if($action==='delete_author'){run('DELETE FROM authors WHERE id=?',[(int)($_POST['id']??0)]);flash('Author profile deleted. Their posts keep the name as plain text.','/admin.php?view=authors');}
  if($action==='save_methodology'){
   $m=trim(str_replace("\r",'',(string)($_POST['methodology']??'')));if(strlen($m)>30000)throw new RuntimeException('The methodology text is too long.');
   run('INSERT INTO settings(key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value',['methodology',$m]);
   flash('"How we review" page saved.','/admin.php?view=authors#methodology');
  }
  if($action==='seo_settings'){
   $vals=[];
   foreach(['google_verification'=>200,'bing_verification'=>200,'yandex_verification'=>200,'pinterest_verification'=>200,'ga_id'=>30,'org_about'=>600,'org_email'=>200,'org_same_as'=>2000,'llms_intro'=>1500,'code_head'=>20000,'code_body'=>20000,'code_footer'=>20000,'code_domains'=>1000,'ads_txt'=>10000] as $k=>$max){$v=trim(str_replace("\r",'',(string)($_POST[$k]??'')));if(strlen($v)>$max)throw new RuntimeException('A field is too long.');$vals[$k]=$v;}
   foreach(['google_verification','bing_verification','yandex_verification','pinterest_verification'] as $k){if(preg_match('/content=["\']([^"\']+)["\']/i',$vals[$k],$m))$vals[$k]=$m[1];if($vals[$k]!==''&&!preg_match('/^[A-Za-z0-9_\-=.:]{4,200}$/',$vals[$k]))throw new RuntimeException('Paste only the verification code (the content="…" value).');}
   if($vals['ga_id']!==''&&!preg_match('/^G-[A-Z0-9]{4,20}$/',$vals['ga_id']))throw new RuntimeException('The Google Analytics ID looks like G-XXXXXXXXXX.');
   if($vals['org_email']!==''&&!filter_var($vals['org_email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid contact email.');
   foreach(array_filter(explode("\n",$vals['org_same_as']),'trim') as $u)if(!preg_match('~^https://\S+$~',trim($u)))throw new RuntimeException('Social profile links must each start with https://.');
   $vals['ai_search']=($_POST['ai_search']??'')==='allow'?'allow':'block';$vals['ai_training']=($_POST['ai_training']??'')==='allow'?'allow':'block';
   foreach($vals as $k=>$v)run('INSERT INTO settings(key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value',[$k,$v]);
   flash('SEO settings saved.','/admin.php?view=seo');
  }
  if($action==='indexnow_all'){
   $urls=['/','/reviews','/top-10','/categories'];foreach(categories() as $c)if($c['total'])$urls[]='/category/'.rawurlencode($c['slug']);
   foreach(query('SELECT r.slug FROM reviews r WHERE '.live()) as $x)$urls[]=reviewUrl($x);
   flash(indexNowPing($urls)?count($urls).' URLs sent to IndexNow (Bing, ChatGPT search, Yandex…).':'IndexNow did not accept the request. Try again later.','/admin.php?view=seo');
  }
  if(in_array($action,['file_upload','file_save','file_mkdir','file_delete','file_rename'],true)){
   $root=(string)($_POST['root']??'public');$rel=(string)($_POST['path']??'');$dir=$action==='file_save'||$action==='file_delete'||$action==='file_rename'?dirname($rel):$rel;$dir=$dir==='.'?'':$dir;
   $back='/admin.php?'.http_build_query(['view'=>'files','root'=>$root,'path'=>$dir]);
   if($action==='file_upload'){$n=0;$files=$_FILES['files']??[];foreach((array)($files['name']??[]) as $i=>$name){fileUpload($root,$rel,['name'=>$name,'tmp_name'=>$files['tmp_name'][$i],'error'=>$files['error'][$i],'size'=>$files['size'][$i]]);$n++;}flash($n?"$n file".($n===1?'':'s').' uploaded.':'Choose a file first.',$back);}
   if($action==='file_save'){$new=trim((string)($_POST['new_name']??''));if($new!==''){$rel=ltrim($rel.'/'.cleanFileName($new),'/');$dir=(string)($_POST['path']??'');if(file_exists(filePath($root,$rel)[2]))throw new RuntimeException('A file with this name already exists.');}fileSave($root,$rel,(string)($_POST['content']??''));flash('Saved '.basename($rel).'.','/admin.php?'.http_build_query(['view'=>'files','root'=>$root,'path'=>$rel,'edit'=>1]));}
   if($action==='file_mkdir'){fileMkdir($root,$rel,(string)($_POST['name']??''));flash('Folder created.',$back);}
   if($action==='file_delete'){fileDelete($root,$rel);flash(basename($rel).' deleted.',$back);}
   if($action==='file_rename'){fileRename($root,$rel,(string)($_POST['to']??''));flash('Renamed.',$back);}
  }
  if($action==='analytics_settings'){
   run('INSERT INTO settings(key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value',['analytics_off',isset($_POST['analytics_off'])?'1':'0']);
   flash('Analytics settings saved.','/admin.php?view=analytics');
  }
  if($action==='geoip_update'){@set_time_limit(300);$n=geoipUpdate();flash('Country database updated ('.number_format($n).' IP ranges).','/admin.php?view=analytics');}
  if($action==='tracking'){
   $param=trim((string)($_POST['subid_param']??''));
   if($param!==''&&!preg_match('/^[A-Za-z0-9_]{1,30}$/',$param))throw new RuntimeException('The parameter name can only use letters, numbers and _ (e.g. subId1).');
   run('INSERT INTO settings(key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value',['subid_param',$param]);
   flash('Tracking settings saved.','/admin.php?view=clicks');
  }
  if($action==='password'){
   $user=query('SELECT * FROM admins WHERE id=?',[$_SESSION['admin']])[0];
   if(!password_verify((string)($_POST['current_password']??''),$user['password']))throw new RuntimeException('Current password is incorrect.');
   $password=(string)($_POST['new_password']??'');if(strlen($password)<12)throw new RuntimeException('Use at least 12 characters.');
   run('UPDATE admins SET password=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$user['id']]);session_regenerate_id(true);flash('Password updated.','/admin.php?view=users');
  }
  throw new RuntimeException('Unknown action.');
 }catch(RuntimeException $ex){$error=$ex->getMessage();}catch(Throwable $ex){error_log((string)$ex);$error='Unable to save. Please check the fields and try again.';}
}

$me=$logged?(query('SELECT * FROM admins WHERE id=?',[$_SESSION['admin']])[0]??null):null;
if($me&&$view==='clicks'&&isset($_GET['export'])){
 [$where,$params]=clickFilters();
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="clicks-'.date('Y-m-d').'.csv"');
 $out=fopen('php://output','w');fputcsv($out,['Time','Article','Destination','Link text','Placement','Clicked on page','Traffic source','Referrer','UTM source','UTM medium','UTM campaign','Landing page','Visitor ID','IP','Country','Device','Browser','OS','Bot','Admin','User agent']);
 $q=db()->prepare("SELECT k.*,l.url,r.title FROM clicks k JOIN links l ON l.id=k.link_id LEFT JOIN reviews r ON r.id=k.post_id WHERE $where ORDER BY k.id DESC");$q->execute($params);
 while($c=$q->fetch())fputcsv($out,[$c['created_at'],$c['title']??'',$c['url'],$c['anchor'],$c['placement'],$c['page'],$c['source'],$c['referrer'],$c['utm_source'],$c['utm_medium'],$c['utm_campaign'],$c['landing'],$c['visitor'],$c['ip'],$c['country'],$c['device'],$c['browser'],$c['os'],$c['is_bot']?'yes':'no',$c['is_admin']?'yes':'no',$c['user_agent']]);
 exit;
}
if($me&&$view==='analytics'&&isset($_GET['export'])){
 $from=preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($_GET['from']??''))?$_GET['from']:date('Y-m-d',strtotime('-29 days'));$to=preg_match('/^\d{4}-\d{2}-\d{2}$/',(string)($_GET['to']??''))?$_GET['to']:date('Y-m-d');
 header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="visits-'.$from.'-to-'.$to.'.csv"');
 $out=fopen('php://output','w');fputcsv($out,['Time','Page','Title','Source','Referrer','UTM source','UTM medium','UTM campaign','Country','Time zone','Language','Device','Browser','OS','Screen','Visitor','Visit','New visitor','Seconds','Scroll %','IP','Admin']);
 $q=adb()->prepare('SELECT * FROM hits WHERE day>=? AND day<=?'.(empty($_GET['admins'])?' AND is_admin=0':'').' ORDER BY id');$q->execute([$from,$to]);
 while($h=$q->fetch())fputcsv($out,[$h['ts'],$h['path'],$h['title'],$h['source'],$h['referrer'],$h['utm_source'],$h['utm_medium'],$h['utm_campaign'],$h['country'],$h['tz'],$h['lang'],$h['device'],$h['browser'],$h['os'],$h['screen'],$h['visitor'],$h['session'],$h['is_new']?'yes':'no',$h['seconds'],$h['scroll'],$h['ip'],$h['is_admin']?'yes':'no']);
 exit;
}
if($logged&&!$me){unset($_SESSION['admin']);$logged=false;}
$titles=['dashboard'=>'Dashboard','posts'=>'Posts','edit'=>'Edit Post','media'=>'Media Library','categories'=>'Categories','analytics'=>'Analytics','clicks'=>'Clicks','files'=>'File manager','search'=>'Search Console','advisor'=>'Content Advisor','authors'=>'Authors','appearance'=>'Appearance','seo'=>'SEO & Code','users'=>'Users','settings'=>'Settings'];
if(!isset($titles[$view]))$view='dashboard';
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($logged?$titles[$view]:'Log in') ?> ‹ <?= e(setting('site_name')) ?></title><link rel="icon" href="/assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="<?= e(asset('/assets/admin.css')) ?>"><script src="<?= e(asset('/assets/admin.js')) ?>" defer></script></head>
<body class="<?= $logged?'cms':'cms-login' ?>">
<?php if(!$logged): ?>
<aside class="login-art"><div><a class="login-logo" href="/"><img src="/assets/favicon.svg" alt=""><span><?= e(setting('site_name')) ?></span></a><p class="login-quote">Good choices start with great content.</p><p class="login-sub">Write, schedule and publish your reviews and guides from one calm workspace.</p></div><p class="login-foot">© <?= date('Y') ?> <?= e(setting('site_name')) ?></p></aside>
<main class="login-box">
 <h1 class="login-title">Welcome back</h1><p class="login-lead">Sign in to your editorial workspace.</p>
 <?php if($error): ?><p role="alert" class="notice notice-error"><?= e($error) ?></p><?php endif ?>
 <?php if(!query('SELECT id FROM admins LIMIT 1')): ?>
  <div class="box"><p>The CMS account has not been configured. Run <code>php scripts/create-admin.php</code> on the server to create your private account.</p></div>
 <?php else: ?>
  <form method="post" class="stack"><?= csrfField() ?><input type="hidden" name="action" value="login">
   <label>Email<input class="input" name="email" type="email" autocomplete="username" required></label>
   <label>Password<input class="input" name="password" type="password" autocomplete="current-password" required></label>
   <button class="button button-primary button-block button-lg">Sign in</button>
  </form>
 <?php endif ?>
 <p class="login-back"><a href="/">← Go to <?= e(setting('site_name')) ?></a></p>
</main>
<?php else:
 $now=now();
 $count=fn(string $where)=>(int)db()->query("SELECT COUNT(*) FROM reviews r WHERE $where")->fetchColumn();
 $counts=['all'=>$count("r.status!='trash'"),'published'=>$count(live()),'scheduled'=>$count("r.status='published' AND r.published_at>'$now'"),'draft'=>$count("r.status='draft'"),'trash'=>$count("r.status='trash'")];
 $nav=['dashboard'=>['Dashboard','home'],'posts'=>['Posts','file'],'media'=>['Media','image'],'categories'=>['Categories','folder'],'analytics'=>['Analytics','chart'],'clicks'=>['Clicks','send'],'search'=>['Search Console','search'],'advisor'=>['Content Advisor','trophy'],'authors'=>['Authors','users'],'appearance'=>['Appearance','image'],'seo'=>['SEO &amp; Code','code'],'files'=>['File manager','folder'],'users'=>['Users','users'],'settings'=>['Settings','gear']];
?>
<header class="cms-top">
 <button type="button" class="cms-menu" data-side-toggle aria-label="Toggle menu"><?= aicon('menu') ?></button>
 <a class="top-brand" href="/admin.php"><span class="brand-mark"><?= aicon('crown') ?></span><span><?= e(setting('site_name')) ?></span></a>
 <details class="new-menu"><summary class="button button-primary"><?= aicon('plus') ?> New <?= aicon('down','icon-sm') ?></summary>
  <div class="dropdown"><a href="/admin.php?view=edit"><?= aicon('file') ?> Post</a><a href="/admin.php?view=media"><?= aicon('image') ?> Media</a><a href="/admin.php?view=categories"><?= aicon('folder') ?> Category</a><a href="/admin.php?view=users"><?= aicon('users') ?> User</a></div></details>
 <form class="top-search" method="get" role="search"><input type="hidden" name="view" value="posts"><?= aicon('search') ?><input name="s" placeholder="Search posts, pages, media..." aria-label="Search posts" value="<?= e($view==='posts'?($_GET['s']??''):'') ?>"></form>
 <span class="cms-spacer"></span>
 <button type="button" class="icon-btn" data-theme-toggle aria-label="Toggle dark mode" title="Toggle dark mode"><?= aicon('sun') ?></button>
 <span class="top-divider"></span>
 <span class="cms-user">Howdy, <?= e($me['email']) ?></span>
 <a class="avatar-link" href="/admin.php?view=users" title="Your account"><span class="avatar"><?= aicon('user') ?></span><?= aicon('down','icon-sm') ?></a>
 <span class="top-divider"></span>
 <form method="post"><?= csrfField() ?><input type="hidden" name="action" value="logout"><button class="logout-btn"><?= aicon('logout') ?><span>Log Out</span></button></form>
</header>
<aside class="cms-side" id="cms-side"><nav aria-label="CMS navigation">
 <?php foreach($nav as $key=>[$label,$ic]): $active=$view===$key||($key==='posts'&&$view==='edit'); ?>
  <a class="<?= $active?'active':'' ?>" href="/admin.php?view=<?= $key ?>"><?= aicon($ic) ?><span><?= $label ?></span><?php if($key==='posts'&&$counts['draft']): ?><b class="badge"><?= $counts['draft'] ?></b><?php endif ?></a>
  <?php if($key==='posts'&&$active): ?><div class="sub"><a class="<?= $view==='posts'?'active':'' ?>" href="/admin.php?view=posts">All Posts</a><a class="<?= $view==='edit'&&empty($_GET['id'])?'active':'' ?>" href="/admin.php?view=edit">Add New</a></div><?php endif ?>
 <?php endforeach ?>
</nav></aside>
<div class="cms-scrim" data-side-toggle></div>
<main class="cms-main" id="main">
<?php if($error): ?><p role="alert" class="notice notice-error"><?= e($error) ?></p><?php endif ?>
<?php if(isset($_SESSION['flash'])): ?><p role="status" class="notice notice-success"><?= e($_SESSION['flash']) ?></p><?php unset($_SESSION['flash']);endif ?>

<?php if($view==='dashboard'): ?>
 <section class="panel"><?= pageHead('home','Dashboard',"Welcome back! Here's what's happening with your site.",'<span class="date-chip">'.aicon('calendar').' '.e(date('M j, Y')).'</span>') ?>
 <div class="dash">
  <section class="box dash-glance"><h2 class="box-title"><?= aicon('chart','icon title-icon') ?> At a Glance</h2><div class="glance">
   <?php foreach([['Published',$counts['published'],'posts&status=published','file','teal'],['Drafts',$counts['draft'],'posts&status=draft','file','blue'],['Scheduled',$counts['scheduled'],'posts&status=scheduled','clock','violet'],['Categories',(int)db()->query('SELECT COUNT(*) FROM categories')->fetchColumn(),'categories','folder','amber'],['Media files',count(mediaFiles()),'media','image','rose'],['In Trash',$counts['trash'],'posts&status=trash','trash','green']] as [$label,$n,$link,$ic,$tone]): ?>
    <a class="tile tile-<?= $tone ?>" href="/admin.php?view=<?= $link ?>"><span class="tile-icon"><?= aicon($ic) ?></span><span><b class="tile-num"><?= $n ?></b><span class="tile-label"><?= $label ?></span></span></a>
   <?php endforeach ?></div></section>
  <section class="box dash-draft"><h2 class="box-title box-title-line"><?= aicon('pen','icon title-icon') ?> Quick Draft</h2><form method="post" class="stack"><?= csrfField() ?><input type="hidden" name="action" value="quick_draft">
   <label>Title<input class="input" name="title" required maxlength="200"></label>
   <label>Content<textarea class="input" name="body" rows="4" placeholder="What's on your mind?" required></textarea></label>
   <div><button class="button button-primary"><?= aicon('send') ?> Save Draft</button></div></form></section>
  <section class="box dash-recent"><h2 class="box-title box-title-line"><?= aicon('file','icon title-icon') ?> Recently Published <a class="chev" href="/admin.php?view=posts&status=published" aria-label="All published posts"><?= aicon('right') ?></a></h2><ul class="activity">
   <?php foreach(query('SELECT r.id,r.title,r.published_at FROM reviews r WHERE '.live().' ORDER BY r.published_at DESC,r.id DESC LIMIT 6') as $r): ?>
    <li><span class="muted"><?= e(date('M j, g:i a',strtotime($r['published_at']))) ?></span><a href="/admin.php?view=edit&id=<?= $r['id'] ?>"><?= e($r['title']) ?></a></li>
   <?php endforeach ?></ul></section>
  <section class="box dash-drafts"><h2 class="box-title"><?= aicon('file','icon title-icon') ?> Your Drafts <a class="chev" href="/admin.php?view=posts&status=draft" aria-label="All drafts"><?= aicon('right') ?></a></h2><ul class="activity">
   <?php foreach($drafts=query("SELECT id,title,updated_at FROM reviews WHERE status='draft' ORDER BY updated_at DESC LIMIT 6") as $r): ?>
    <li><span class="muted"><?= e(date('M j',strtotime($r['updated_at']))) ?></span><a href="/admin.php?view=edit&id=<?= $r['id'] ?>"><?= e($r['title']) ?></a></li>
   <?php endforeach ?><?php if(!$drafts): ?><li class="muted">No drafts.</li><?php endif ?></ul></section>
 </div></section>
<?php elseif($view==='posts'):
 $status=(string)($_GET['status']??'all');if(!isset($counts[$status]))$status='all';
 $s=trim((string)($_GET['s']??''));$cat=(int)($_GET['cat']??0);$page=max(1,(int)($_GET['p']??1));
 $orderby=['date'=>'r.published_at','title'=>'r.title COLLATE NOCASE','score'=>'r.score','modified'=>'r.updated_at','category'=>'c.name'][(string)($_GET['orderby']??'')]??'r.published_at';
 $dir=($_GET['order']??'')==='asc'?'ASC':'DESC';
 $where=['all'=>"r.status!='trash'",'published'=>live(),'scheduled'=>"r.status='published' AND r.published_at>'$now'",'draft'=>"r.status='draft'",'trash'=>"r.status='trash'"][$status];$params=[];
 if($s!==''){$where.=' AND (r.title LIKE ? OR r.body LIKE ?)';$params[]="%$s%";$params[]="%$s%";}
 if($cat){$where.=' AND r.category_id=?';$params[]=$cat;}
 $total=(int)(query("SELECT COUNT(*) AS n FROM reviews r JOIN categories c ON c.id=r.category_id WHERE $where",$params)[0]['n']);$pages=max(1,(int)ceil($total/PER_PAGE));$page=min($page,$pages);
 $rows=query("SELECT r.*,c.name AS category FROM reviews r JOIN categories c ON c.id=r.category_id WHERE $where ORDER BY $orderby $dir,r.id DESC LIMIT ".PER_PAGE.' OFFSET '.(($page-1)*PER_PAGE),$params);
 $q=fn(array $over)=>'/admin.php?'.http_build_query(array_filter(array_merge(['view'=>'posts','status'=>$status==='all'?null:$status,'s'=>$s?:null,'cat'=>$cat?:null,'orderby'=>$_GET['orderby']??null,'order'=>$_GET['order']??null],$over),fn($v)=>$v!==null&&$v!==''));
 $self=$q(['p'=>$page>1?$page:null]);
 $sortLink=function(string $key,string $label)use($q,$dir){$cur=($_GET['orderby']??'date')===$key;$next=$cur&&$dir==='DESC'?'asc':'desc';return '<a class="sort'.($cur?' sorted':'').'" href="'.e($q(['orderby'=>$key,'order'=>$next,'p'=>null])).'">'.$label.'<svg class="sort-icon" viewBox="0 0 24 24" aria-hidden="true"><path class="up'.($cur&&$dir==='ASC'?' on':'').'" d="m8 10 4-4 4 4"/><path class="down'.($cur&&$dir==='DESC'?' on':'').'" d="m8 14 4 4 4-4"/></svg></a>';};
 $chip=fn(int $id)=>['teal','violet','green','rose','amber','blue'][$id%6];
?>
 <section class="panel">
 <?= pageHead('file','Posts','Manage and organize your content. Create, edit, and publish posts.','<a class="button button-primary button-lg" href="/admin.php?view=edit">'.aicon('plus').' Add New Post</a>') ?>

 <ul class="seg"><?php foreach(['all'=>'All','published'=>'Published','scheduled'=>'Scheduled','draft'=>'Drafts','trash'=>'Trash'] as $k=>$label): if($k!=='all'&&!$counts[$k])continue; ?><li><a class="<?= $status===$k?'current':'' ?>" href="<?= e($q(['status'=>$k==='all'?null:$k,'p'=>null])) ?>"><?php if($k!=='all'): ?><i class="dot dot-<?= $k ?>"></i><?php endif ?><?= $label ?> (<?= $counts[$k] ?>)</a></li><?php endforeach ?></ul>
 <form class="tablenav" method="get"><input type="hidden" name="view" value="posts"><?php if($status!=='all'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif ?>
  <label class="field-icon"><?= aicon('folder') ?><select class="input" name="cat" aria-label="Filter by category"><option value="">All Categories</option><?php foreach(categories() as $c): ?><option value="<?= $c['id'] ?>" <?= $cat===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach ?></select></label>
  <button class="button button-outline"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><path d="M3 5h18l-7 8v6l-4-2v-4z"/></svg> Filter</button><span class="cms-spacer"></span>
  <label class="field-icon field-search"><?= aicon('search') ?><input class="input" type="search" name="s" value="<?= e($s) ?>" aria-label="Search posts" placeholder="Search posts..."></label><button class="button button-primary">Search Posts</button>
 </form>
 <form method="post" id="bulk-form"><?= csrfField() ?><input type="hidden" name="action" value="bulk"><input type="hidden" name="return" value="<?= e($self) ?>">
 <div class="tablenav">
  <select class="input select-bulk" name="bulk_action" aria-label="Bulk actions"><option value="">Bulk actions</option>
   <?php if($status==='trash'): ?><option value="restore">Restore</option><option value="delete">Delete permanently</option><?php else: ?><option value="publish">Publish</option><option value="draft">Move to Draft</option><option value="trash">Move to Trash</option><?php endif ?>
  </select><button class="button button-outline" data-bulk-apply>Apply</button><span class="cms-spacer"></span><span class="muted"><?= $total ?> item<?= $total===1?'':'s' ?></span>
 </div>
 <div class="table-wrap"><table class="list-table"><thead><tr><th class="check"><input type="checkbox" data-check-all aria-label="Select all"></th><th class="th-title"><?= $sortLink('title','Title') ?></th><th><?= $sortLink('category','Category') ?></th><th><?= $sortLink('score','Score') ?></th><th title="SEO analyser · AI readiness">SEO · AI</th><th><?= $sortLink('date','Date') ?></th><th class="kebab-col"><span class="sr-only">Actions</span></th></tr></thead><tbody>
 <?php foreach($rows as $r): $state=postState($r); $view_url=reviewUrl($r).($state==='published'?'':'?preview=1'); ?>
  <tr><td class="check"><input type="checkbox" name="ids[]" value="<?= $r['id'] ?>" aria-label="Select <?= e($r['title']) ?>"></td>
   <td class="title-col"><div class="title-cell"><img class="thumb" src="<?= e($r['image']) ?>" alt="" loading="lazy"><div><a class="row-title" href="/admin.php?view=edit&id=<?= $r['id'] ?>"><?= e($r['title']) ?></a><?php if($state!=='published'): ?> <span class="pill pill-<?= $state ?>"><?= ucfirst($state) ?></span><?php endif ?><?php if($r['featured']): ?> <span class="tag">Featured</span><?php endif ?><?php if($r['demo']): ?> <span class="tag">Sample</span><?php endif ?></div></div></td>
   <td><a class="chip chip-<?= $chip((int)$r['category_id']) ?>" href="<?= e($q(['cat'=>$r['category_id'],'p'=>null])) ?>"><?= e($r['category']) ?></a></td>
   <td class="score-col"><?= $r['score']>0?number_format((float)$r['score'],1):'—' ?></td>
   <td class="nowrap"><?php [$sc,$ac]=seoAnalyse($r); foreach([seoScore($sc),seoScore($ac)] as $n): ?><span class="seo-score <?= $n>=75?'good':($n>=50?'ok':'bad') ?>"><?= $n ?></span><?php endforeach ?></td>
   <td class="date-col"><span class="date-state"><?= ['published'=>'Published','scheduled'=>'Goes live','draft'=>'Last modified','trash'=>'Trashed'][$state] ?></span><br><?= e(date('Y/m/d \a\t g:i a',strtotime($state==='draft'||$state==='trash'?$r['updated_at']:$r['published_at']))) ?></td>
   <td class="kebab-col"><details class="kebab"><summary aria-label="Actions for <?= e($r['title']) ?>"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="5" r="1.8" fill="currentColor"/><circle cx="12" cy="12" r="1.8" fill="currentColor"/><circle cx="12" cy="19" r="1.8" fill="currentColor"/></svg></summary><div class="dropdown dropdown-right">
    <?php if($state==='trash'): ?>
     <button form="row-<?= $r['id'] ?>-restore"><?= aicon('clock') ?> Restore</button><button class="danger" form="row-<?= $r['id'] ?>-delete"><?= aicon('trash') ?> Delete Permanently</button>
    <?php else: ?>
     <a href="/admin.php?view=edit&id=<?= $r['id'] ?>"><?= aicon('pen') ?> Edit</a><a href="<?= e($view_url) ?>" target="_blank"><?= aicon('right') ?> <?= $state==='published'?'View':'Preview' ?></a><button form="row-<?= $r['id'] ?>-dup"><?= aicon('file') ?> Duplicate</button><?php if($state==='published'&&pinterestToken()!==''): ?><button form="row-<?= $r['id'] ?>-pin"><?= aicon('send') ?> <?= pinnedAlready((int)$r['id'])?'Pin again':'Pin to Pinterest' ?></button><?php endif ?><button class="danger" form="row-<?= $r['id'] ?>-trash"><?= aicon('trash') ?> Move to Trash</button>
    <?php endif ?></div></details></td></tr>
 <?php endforeach ?>
 <?php if(!$rows): ?><tr><td colspan="7" class="muted empty">No posts found.</td></tr><?php endif ?>
 </tbody></table></div></form>
 <?php foreach($rows as $r): foreach(['restore'=>'post_status','delete'=>'post_status','trash'=>'post_status','dup'=>'duplicate','pin'=>'pin_post'] as $k=>$act): ?><form id="row-<?= $r['id'] ?>-<?= $k ?>" method="post" class="hidden" <?= $k==='delete'?'data-confirm="Delete this post permanently? This cannot be undone."':'' ?>><?= csrfField() ?><input type="hidden" name="action" value="<?= $act ?>"><input type="hidden" name="do" value="<?= $k ?>"><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="return" value="<?= e($self) ?>"></form><?php endforeach;endforeach ?>
 <div class="table-foot"><span class="muted"><?= $total?'Showing '.(($page-1)*PER_PAGE+1).' to '.min($total,$page*PER_PAGE).' of '.$total.' items':'' ?></span>
 <?php if($pages>1): $from=max(1,min($page-2,$pages-4));$to=min($pages,$from+4); ?><nav class="pagination" aria-label="Pages">
  <a class="<?= $page===1?'disabled':'' ?>" href="<?= e($q(['p'=>null])) ?>" aria-label="First page">«</a><a class="<?= $page===1?'disabled':'' ?>" href="<?= e($q(['p'=>max(1,$page-1)])) ?>" aria-label="Previous page">‹</a>
  <?php for($i=$from;$i<=$to;$i++): ?><a class="<?= $i===$page?'current':'' ?>" href="<?= e($q(['p'=>$i])) ?>" <?= $i===$page?'aria-current="page"':'' ?>><?= $i ?></a><?php endfor ?>
  <a class="<?= $page===$pages?'disabled':'' ?>" href="<?= e($q(['p'=>min($pages,$page+1)])) ?>" aria-label="Next page">›</a><a class="<?= $page===$pages?'disabled':'' ?>" href="<?= e($q(['p'=>$pages])) ?>" aria-label="Last page">»</a>
 </nav><?php endif ?></div>
 </section>

<?php elseif($view==='edit'):
 $id=(int)($_GET['id']??0);
 $r=$id?(query('SELECT * FROM reviews WHERE id=?',[$id])[0]??null):['id'=>0,'title'=>mb_substr(trim((string)($_GET['title']??'')),0,200),'slug'=>'','category_id'=>'','excerpt'=>'','body'=>'','image'=>'','score'=>'0','pros'=>'','cons'=>'','verdict'=>'','author'=>'Editorial team','status'=>'draft','featured'=>0,'demo'=>0,'meta_title'=>'','meta_description'=>'','brand'=>'','brand_about'=>'','cta_url'=>'','focus_keyword'=>mb_strtolower(mb_substr(trim((string)($_GET['title']??'')),0,100)),'seo_canonical'=>'','seo_robots'=>'','og_image'=>'','schema_type'=>'','tldr'=>'','takeaways'=>'','custom_schema'=>'','published_at'=>''];
 if($r&&$error&&($_POST['action']??'')==='save_post')$r=array_merge($r,array_intersect_key($_POST,$r),['featured'=>isset($_POST['featured']),'demo'=>isset($_POST['demo'])]);
 if(!$r): ?><p class="notice notice-error">Post not found.</p><?php else: $state=$id?postState($r):'new'; ?>
 <section class="panel"><?= pageHead('pen',$id?'Edit Post':'Add New Post',$id?'Update the content, settings and SEO of this post.':'Write something new. Save it as a draft or publish when ready.',$id?'<a class="button button-outline" href="/admin.php?view=posts">'.aicon('file').' All Posts</a><a class="button button-primary" href="/admin.php?view=edit">'.aicon('plus').' Add New</a>':'<a class="button button-outline" href="/admin.php?view=posts">'.aicon('file').' All Posts</a>') ?>
 <form method="post" enctype="multipart/form-data" class="editor" data-editor data-now="<?= e(substr(now(),0,16)) ?>"><?= csrfField() ?><input type="hidden" name="action" value="save_post"><input type="hidden" name="id" value="<?= e($r['id']) ?>">
  <div class="editor-main">
   <input class="input title-input" name="title" value="<?= e($r['title']) ?>" placeholder="Add title" required maxlength="200" aria-label="Title" data-title>
   <p class="permalink">Permalink: <span class="muted"><?= e($_SERVER['HTTP_HOST']??'') ?>/</span><input class="input input-sm" name="slug" value="<?= e($r['slug']) ?>" placeholder="auto-generated-from-title" aria-label="URL slug" data-slug>
    <?php if($id): ?><a class="button button-sm" href="<?= e(reviewUrl($r)) ?><?= $state==='published'?'':'?preview=1' ?>" target="_blank"><?= $state==='published'?'View Post':'Preview' ?></a><?php endif ?></p>
   <div class="box editor-box">
    <div class="toolbar" role="toolbar" aria-label="Formatting">
     <button type="button" data-md="h2" title="Heading">H2</button><button type="button" data-md="h3" title="Subheading">H3</button><button type="button" data-md="bold" title="Bold"><b>B</b></button><button type="button" data-md="link" title="Insert link">Link</button><button type="button" data-md="list" title="Bulleted list">• List</button><button type="button" data-md="image" title="Insert image">Image</button>
     <span class="cms-spacer"></span><div class="tabs"><button type="button" class="active" data-tab="write">Write</button><button type="button" data-tab="preview">Preview</button></div>
    </div>
    <textarea class="input body-input" name="body" rows="22" required aria-label="Content" data-body><?= e($r['body']) ?></textarea>
    <div class="prose-preview hidden" data-preview></div>
    <div class="editor-foot muted"><span data-wordcount>0 words</span><span>## heading · **bold** · [text](https://url) · - list · ![alt](/uploads/image.jpg)</span></div>
   </div>
   <section class="box"><h2 class="box-title"><?= aicon('file','icon title-icon') ?> Excerpt</h2><textarea class="input" name="excerpt" rows="3" maxlength="600"><?= e($r['excerpt']) ?></textarea><p class="hint">Short summary shown on cards and in search results. Leave empty to generate it from the content.</p></section>
   <details class="box" <?= $r['score']>0||$r['pros']!==''?'open':'' ?>><summary class="box-title"><?= aicon('chart','icon title-icon') ?> Review Details <span class="muted">(optional — for product reviews)</span></summary><div class="stack">
    <label>Score out of 10 <input class="input input-sm" name="score" type="number" min="0" max="10" step="0.1" value="<?= e($r['score']) ?>"></label><p class="hint">Leave at 0 for a regular article: no rating badge or verdict box is shown.</p>
    <label>Pros / Key takeaways (one per line)<textarea class="input" name="pros" rows="3"><?= e($r['pros']) ?></textarea></label>
    <label>Cons (one per line)<textarea class="input" name="cons" rows="3"><?= e($r['cons']) ?></textarea></label>
    <label>Final verdict<textarea class="input" name="verdict" rows="3"><?= e($r['verdict']) ?></textarea></label></div></details>
   <section class="box"><h2 class="box-title"><?= aicon('send','icon title-icon') ?> Brand &amp; Call to Action <span class="muted">(optional)</span></h2><div class="stack">
    <label>Brand name<input class="input" name="brand" value="<?= e($r['brand']) ?>" maxlength="80" placeholder="e.g. Temu"></label>
    <label>Brand link (affiliate URL)<input class="input" name="cta_url" value="<?= e($r['cta_url']) ?>" placeholder="https://…"></label>
    <label>About the brand<textarea class="input" name="brand_about" rows="2" maxlength="500"><?= e($r['brand_about']) ?></textarea></label>
    <p class="hint">Shows a "Shop on …" button and an "About …" box in the article sidebar. Leave empty to hide them.</p></div></section>
   <section class="box"><h2 class="box-title"><?= aicon('search','icon title-icon') ?> SEO</h2><div class="stack">
    <label>SEO title <span class="muted" data-count-for="meta_title"></span><input class="input" name="meta_title" value="<?= e($r['meta_title']) ?>" maxlength="200" placeholder="<?= e($r['title']?:'Defaults to the post title') ?>" data-count="60"></label>
    <label>Meta description <span class="muted" data-count-for="meta_description"></span><textarea class="input" name="meta_description" rows="2" maxlength="500" placeholder="Defaults to the excerpt" data-count="160"><?= e($r['meta_description']) ?></textarea></label>
    <div class="serp"><p class="serp-title" data-serp-title><?= e($r['meta_title']?:$r['title']?:'Post title') ?></p><p class="serp-url"><?= e($_SERVER['HTTP_HOST']??'') ?> › <?= e($r['slug']?:'post') ?></p><p class="serp-desc" data-serp-desc><?= e($r['meta_description']?:$r['excerpt']) ?></p></div>
    <label>Focus keyword <span class="muted">(the main search phrase this post should rank for)</span><input class="input" name="focus_keyword" value="<?= e($r['focus_keyword']) ?>" maxlength="100" placeholder="e.g. best sports hats" data-focus></label>
    <div class="seo-checks"><p class="lbl">SEO analysis <b class="seo-score" data-seo-score></b></p><ul class="check-list" data-seo-checks></ul></div></div></section>
   <section class="box"><h2 class="box-title"><?= aicon('chart','icon title-icon') ?> AI SEO <span class="muted">(AEO · GEO · LLMO — ChatGPT, Gemini, Perplexity, Google AI Overviews)</span></h2><div class="stack">
    <label>Quick answer / TL;DR <span class="muted" data-count-for="tldr"></span><textarea class="input" name="tldr" rows="3" maxlength="700" data-count="300" placeholder="Answer the main question of this post in 1–3 plain sentences. AI assistants quote this directly."><?= e($r['tldr']) ?></textarea></label>
    <label>Key takeaways <span class="muted">(one per line, 3–5 short facts)</span><textarea class="input" name="takeaways" rows="4" maxlength="2000" placeholder="Structured caps look sharper; relaxed caps suit casual days&#10;Match one colour from the hat in your outfit"><?= e($r['takeaways']) ?></textarea></label>
    <p class="hint">Shown as a "Quick answer" box at the top of the post, added to the schema (abstract + speakable) and to <code>/llms.txt</code>. Add a <code>## Frequently Asked Questions</code> section with <code>**Question?**</code> lines to get FAQ schema automatically.</p>
    <div class="seo-checks"><p class="lbl">AI readiness <b class="seo-score" data-ai-score></b></p><ul class="check-list" data-ai-checks></ul></div></div></section>
   <details class="box" <?= $r['seo_canonical'].$r['seo_robots'].$r['og_image'].$r['schema_type'].$r['custom_schema']!==''?'open':'' ?>><summary class="box-title"><?= aicon('gear','icon title-icon') ?> Advanced SEO</summary><div class="stack">
    <label>Schema type<select class="input" name="schema_type"><?php foreach(SCHEMA_TYPES as $k=>$label): ?><option value="<?= $k==='BlogPosting'?'':e($k) ?>" <?= ($r['schema_type']?:'BlogPosting')===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach ?></select></label>
    <p class="hint">"How-to guide" turns each <code>##</code> heading into a step. "Product review" needs a score and a brand name.</p>
    <label>Search engine visibility<select class="input" name="seo_robots"><?php foreach([''=>'Index & follow links (recommended)','noindex, follow'=>'Hide from search (noindex)','index, nofollow'=>'Index, but don\'t follow links','noindex, nofollow'=>'Hide and don\'t follow links'] as $k=>$label): ?><option value="<?= e($k) ?>" <?= $r['seo_robots']===$k?'selected':'' ?>><?= e($label) ?></option><?php endforeach ?></select></label>
    <label>Canonical URL <span class="muted">(only if this content first appeared elsewhere)</span><input class="input" name="seo_canonical" value="<?= e($r['seo_canonical']) ?>" placeholder="https://… (leave empty for this page)"></label>
    <label>Social share image <span class="muted">(1200×630 JPG/PNG recommended)</span><input class="input" name="og_image" value="<?= e($r['og_image']) ?>" placeholder="Defaults to the featured image"></label>
    <label>Custom schema (JSON-LD) <span class="muted">(optional, added to the page)</span><textarea class="input code-input" name="custom_schema" rows="4" spellcheck="false" placeholder='{"@type":"Product","name":"…","brand":{"@type":"Brand","name":"…"}}'><?= e($r['custom_schema']) ?></textarea></label>
   </div></details>
  </div>
  <aside class="editor-side">
   <section class="box"><h2 class="box-title"><?= aicon('send','icon title-icon') ?> Publish</h2>
    <p>Status: <b><?= ['new'=>'Not saved','published'=>'Published','scheduled'=>'Scheduled','draft'=>'Draft','trash'=>'In Trash'][$state] ?></b></p>
    <label>Publish date <input class="input input-sm" type="datetime-local" name="published_at" value="<?= e(substr((string)$r['published_at'],0,16)) ?>" data-pubdate></label>
    <p class="hint">Leave empty to publish now. A future date schedules the post.</p>
    <label class="check-row"><input type="checkbox" name="featured" <?= $r['featured']?'checked':'' ?>> Feature on homepage</label>
    <label class="check-row"><input type="checkbox" name="demo" <?= $r['demo']?'checked':'' ?>> Label as sample content</label>
    <div class="publish-actions">
     <?php if($id&&$state!=='draft'&&$state!=='new'): ?><button class="button" name="intent" value="draft">Switch to Draft</button><?php else: ?><button class="button" name="intent" value="draft">Save Draft</button><?php endif ?>
     <button class="button button-primary" name="intent" value="publish" data-publish-btn data-label="<?= $state==='published'||$state==='scheduled'?'Update':'Publish' ?>"><?= $state==='published'||$state==='scheduled'?'Update':'Publish' ?></button>
    </div>
    <?php if($id&&$state!=='trash'): ?><button class="link-danger" form="trash-post">Move to Trash</button><?php endif ?>
   </section>
   <section class="box"><h2 class="box-title"><?= aicon('folder','icon title-icon') ?> Category</h2><div class="cat-list"><?php foreach(categories() as $c): ?><label class="check-row"><input type="radio" name="category_id" value="<?= $c['id'] ?>" <?= (int)$r['category_id']===(int)$c['id']?'checked':'' ?> required> <?= e($c['name']) ?></label><?php endforeach ?></div><a class="hint" href="/admin.php?view=categories">+ Add New Category</a></section>
   <section class="box"><h2 class="box-title"><?= aicon('image','icon title-icon') ?> Featured Image</h2>
    <?php if($r['image']): ?><img class="feat-preview" src="<?= e($r['image']) ?>" alt="Current featured image" data-feat-preview><?php else: ?><img class="feat-preview hidden" alt="" data-feat-preview><?php endif ?>
    <label>Upload new<input class="input input-sm" type="file" name="image_upload" accept="image/jpeg,image/png,image/webp"></label>
    <details class="library"><summary>Choose from Media Library</summary><div class="library-grid"><?php foreach(libraryImages() as $img): ?><label><input type="radio" name="image_pick" value="<?= e($img) ?>" <?= $img===$r['image']?'checked':'' ?>><img src="<?= e($img) ?>" alt="" loading="lazy"></label><?php endforeach ?></div></details>
    <label>Or image URL<input class="input input-sm" name="image" value="<?= e($r['image']) ?>" placeholder="https://… or /uploads/…" data-image-url></label>
   </section>
   <section class="box"><h2 class="box-title"><?= aicon('user','icon title-icon') ?> Author</h2><input class="input input-sm" name="author" value="<?= e($r['author']) ?>" aria-label="Author" list="author-list"><datalist id="author-list"><?php foreach(query('SELECT name FROM authors ORDER BY name') as $a): ?><option value="<?= e($a['name']) ?>"><?php endforeach ?></datalist><p class="hint"><?= authorByName((string)$r['author'])?'Linked to the author profile.':'<a href="/admin.php?view=authors">Add an author profile</a> to show a photo and bio.' ?></p></section>
  </aside>
 </form>
 <?php if($id): ?><form id="trash-post" method="post" class="hidden"><?= csrfField() ?><input type="hidden" name="action" value="post_status"><input type="hidden" name="do" value="trash"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="return" value="/admin.php?view=posts"></form><?php endif ?>
 </section>
 <?php endif ?>

<?php elseif($view==='media'): $files=mediaFiles(); ?>
 <section class="panel"><?= pageHead('image','Media Library','Upload and manage the images used across your site.','<span class="muted">'.count($files).' file'.(count($files)===1?'':'s').'</span>') ?>
 <form method="post" enctype="multipart/form-data" class="box upload-box"><?= csrfField() ?><input type="hidden" name="action" value="upload_media">
  <p><b>Upload images</b> <span class="muted">JPG, PNG or WebP, up to 5 MB each.</span></p><input class="input" type="file" name="files[]" accept="image/jpeg,image/png,image/webp" multiple required><button class="button button-primary"><?= aicon('plus') ?> Upload</button></form>
 <div class="media-grid">
  <?php foreach($files as $f): $used=imageInUse($f['url']); ?>
   <figure class="media-item"><a href="<?= e($f['url']) ?>" target="_blank"><img src="<?= e($f['url']) ?>" alt="" loading="lazy"></a>
    <figcaption><input class="input input-sm" value="<?= e($f['url']) ?>" readonly aria-label="Image URL" data-copy><span class="muted"><?= round($f['size']/1024) ?> KB · <?= date('M j, Y',$f['time']) ?><?= $used?' · In use':'' ?></span>
     <?php if(!$used): ?><form method="post" data-confirm="Delete this image permanently?"><?= csrfField() ?><input type="hidden" name="action" value="delete_media"><input type="hidden" name="name" value="<?= e($f['name']) ?>"><button class="link-danger">Delete</button></form><?php endif ?></figcaption></figure>
  <?php endforeach ?>
  <?php if(!$files): ?><p class="muted">No uploads yet.</p><?php endif ?>
 </div>
 <p class="hint">Click an image URL to copy it, then paste it into a post as <code>![description](/uploads/…)</code>.</p></section>

<?php elseif($view==='categories'): ?>
 <section class="panel"><?= pageHead('folder','Categories','Organise posts into topics readers can browse.') ?>
 <div class="two-col">
  <form method="post" class="box stack"><?= csrfField() ?><input type="hidden" name="action" value="save_category"><h2 class="box-title"><?= aicon('plus','icon title-icon') ?> Add New Category</h2>
   <label>Name<input class="input" name="name" required maxlength="60" placeholder="e.g. Automotive"></label>
   <label>Icon<select class="input" name="icon"><?php foreach(['grid','tech','shopping','travel','gadgets','home'] as $i): ?><option><?= $i ?></option><?php endforeach ?></select></label>
   <div><button class="button button-primary"><?= aicon('plus') ?> Add New Category</button></div></form>
  <div class="table-wrap"><table class="list-table"><thead><tr><th>Name</th><th>Icon</th><th>Slug</th><th>Posts</th><th class="kebab-col"><span class="sr-only">Actions</span></th></tr></thead><tbody>
   <?php foreach(categories() as $c): ?><tr>
    <td><div class="title-cell"><span class="cat-icon chip-<?= ['teal','violet','green','rose','amber','blue'][(int)$c['id']%6] ?>"><?= aicon('folder') ?></span><form method="post" class="inline-form" id="cat-<?= $c['id'] ?>"><?= csrfField() ?><input type="hidden" name="action" value="save_category"><input type="hidden" name="id" value="<?= $c['id'] ?>"><input class="input input-sm" name="name" value="<?= e($c['name']) ?>" required aria-label="Category name"></form><details class="hub-intro"><summary>Hub introduction<?= trim((string)($c['intro']??''))!==''?' ✓':'' ?></summary><textarea class="input" name="intro" form="cat-<?= $c['id'] ?>" rows="5" maxlength="8000" placeholder="2–4 paragraphs shown at the top of /category/<?= e($c['slug']) ?>: what this topic covers, how to choose, where to start. Markdown allowed."><?= e($c['intro']??'') ?></textarea><span class="hint">Saved with the row’s Save button.</span></details></div></td>
    <td><select class="input input-sm" name="icon" form="cat-<?= $c['id'] ?>" aria-label="Icon"><?php foreach(['grid','tech','shopping','travel','gadgets','home'] as $i): ?><option <?= $c['icon']===$i?'selected':'' ?>><?= $i ?></option><?php endforeach ?></select></td>
    <td class="muted"><code><?= e($c['slug']) ?></code></td><td><a class="chip chip-<?= ['teal','violet','green','rose','amber','blue'][(int)$c['id']%6] ?>" href="/admin.php?view=posts&cat=<?= $c['id'] ?>"><?= (int)$c['total'] ?> post<?= (int)$c['total']===1?'':'s' ?></a></td>
    <td class="nowrap"><div class="row-buttons"><button class="button button-outline button-sm" form="cat-<?= $c['id'] ?>">Save</button><form method="post" class="inline-form" data-confirm="Delete this category?"><?= csrfField() ?><input type="hidden" name="action" value="delete_category"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="icon-danger" title="Delete category" aria-label="Delete <?= e($c['name']) ?>"><?= aicon('trash') ?></button></form></div></td></tr>
   <?php endforeach ?></tbody></table></div>
 </div></section>

<?php elseif($view==='advisor'): $tab=in_array($_GET['tab']??'',['doctor','ideas','history'],true)?$_GET['tab']:'doctor'; $ai=aiAvailable(); ?>
 <section class="panel"><?= pageHead('trophy','Content Advisor','What to write next, why pages are not ranking, and AI-prepared fixes you can apply with one click.','<div class="seg-tabs">'.implode('',array_map(fn($k,$l)=>'<a class="'.($tab===$k?'active':'').'" href="/admin.php?view=advisor&tab='.$k.'">'.$l.'</a>',['doctor','ideas','history'],['Content doctor','Keyword ideas','History'])).'</div>') ?>
 <?php if(!$ai): ?><p class="notice">AI fixes and drafts need the AI assistant: <a href="/admin.php?view=seo#ai">connect it in SEO &amp; Code</a>. The diagnosis and keyword ideas work without it.</p><?php endif ?>
 <?php if(!gscKey()): ?><p class="notice">Connect <a href="/admin.php?view=search">Search Console</a> to add ranking data (missing searches, page-2 pages, low click-through) to the diagnosis.</p><?php endif ?>
 <?php if($tab==='doctor'): $diag=array_filter(diagnosePosts(),fn($d)=>$d['issues']); $sugs=[];foreach(query('SELECT * FROM ai_suggestions') as $x)$sugs[$x['post_id']]=json_decode($x['data'],true); ?>
  <?php foreach($GLOBALS['advisorSite']??[] as [$code,$t,$detail]): ?><section class="box site-note"><h2 class="box-title"><?= aicon('search','icon title-icon') ?> <?= e($t) ?></h2><p><?= e($detail) ?></p><p><a class="button button-outline button-sm" href="/admin.php?view=search">Open Search Console report</a></p></section><?php endforeach ?>
  <?php $all=isset($_GET['all']);$ptitles=[];foreach(query("SELECT id,title FROM reviews r WHERE ".live()) as $t)$ptitles[$t["id"]]=$t["title"]; ?>
  <p class="muted"><?= count($diag) ?> published post<?= count($diag)===1?'':'s' ?> with something to improve, most important first.<?= !$all&&count($diag)>15?' Showing the top 15 · <a href="/admin.php?view=advisor&tab=doctor&all=1">show all</a>':'' ?></p>
  <?php foreach(array_slice($diag,0,$all?200:15) as $d): $p=$d['post'];$sg=$sugs[$p['id']]??null; ?>
  <section class="box doc-card" id="post-<?= $p['id'] ?>">
   <div class="doc-head"><div><a class="row-title" href="/admin.php?view=edit&id=<?= $p['id'] ?>"><?= e($p['title']) ?></a><br><span class="muted"><?= e($p['category']) ?> · SEO <?= $d['scores'][0] ?> · AI <?= $d['scores'][1] ?> · <a href="<?= e(reviewUrl($p)) ?>" target="_blank">view</a></span></div>
    <div class="row-buttons"><?php if($sg&&!empty($sg['pending'])): ?><span class="chip chip-amber">AI is working…</span><?php elseif(!$sg): ?><form method="post"><?= csrfField() ?><input type="hidden" name="action" value="ai_fixes"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="button button-primary button-sm" <?= $ai?'':'disabled' ?>><?= aicon('bulb') ?> Get AI fixes</button></form><?php endif ?></div></div>
   <ul class="check-list"><?php foreach($d['issues'] as $iss): [$sev,$code,$t,$detail]=$iss; ?><li class="<?= $sev>=3?'':'warn' ?>"><span><b><?= e($t) ?></b> — <?= e($detail) ?>
    <?php if($code==='cannibal'&&!empty($iss[4])): ?><form method="post" class="merge-form" data-confirm="Move this post to the Trash and redirect its URL (301) to the selected post?"><?= csrfField() ?><input type="hidden" name="action" value="merge_post"><input type="hidden" name="id" value="<?= $p['id'] ?>"><select class="input input-sm" name="into" aria-label="Merge into"><?php foreach($iss[4] as $pid): ?><option value="<?= (int)$pid ?>"><?= e($ptitles[$pid]??("#".$pid)) ?></option><?php endforeach ?></select><button class="button button-outline button-sm">Merge into this post</button></form><?php endif ?>
   </span></li><?php endforeach ?></ul>
   <?php if($sg&&empty($sg['pending'])): ?>
    <form method="post" class="fix-box"><?= csrfField() ?><input type="hidden" name="action" value="apply_fixes"><input type="hidden" name="id" value="<?= $p['id'] ?>">
     <p class="lbl"><?= aicon('bulb') ?> AI suggestion</p><p><?= e($sg['summary']) ?></p>
     <label class="check-row"><input type="checkbox" name="meta" checked> <span><b>SEO title:</b> <s class="muted"><?= e($p['meta_title']?:$p['title']) ?></s> → <?= e($sg['meta_title']) ?><br><b>Description:</b> <?= e($sg['meta_description']) ?></span></label>
     <label class="check-row"><input type="checkbox" name="tldr" checked> <span><b>Quick answer:</b> <?= e($sg['tldr']) ?></span></label>
     <?php foreach($sg['new_sections'] as $i=>$sec): ?><label class="check-row"><input type="checkbox" name="sections[]" value="<?= $i ?>" checked> <span><b>New section: <?= e($sec['heading']) ?></b><details><summary class="muted">Preview</summary><div class="prose fix-preview"><?= renderBody($sec['markdown']) ?></div></details></span></label><?php endforeach ?>
     <?php foreach($sg['faq'] as $i=>$f): ?><label class="check-row"><input type="checkbox" name="faq[]" value="<?= $i ?>" checked> <span><b>FAQ:</b> <?= e($f['q']) ?> <span class="muted"><?= e($f['a']) ?></span></span></label><?php endforeach ?>
     <div class="row-buttons"><button class="button button-primary"><?= aicon('check') ?> Apply selected</button><button class="button button-outline" form="discard-<?= $p['id'] ?>">Discard</button></div>
    </form><form id="discard-<?= $p['id'] ?>" method="post" class="hidden"><?= csrfField() ?><input type="hidden" name="action" value="discard_fixes"><input type="hidden" name="id" value="<?= $p['id'] ?>"></form>
   <?php endif ?>
  </section>
  <?php endforeach ?>
  <section class="box"><h2 class="box-title"><?= aicon('gear','icon title-icon') ?> Automation</h2>
   <form method="post" class="stack"><?= csrfField() ?><input type="hidden" name="action" value="advisor_settings">
    <label class="check-row"><input type="checkbox" name="advisor_weekly_fixes" <?= setting('advisor_weekly_fixes')==='1'?'checked':'' ?>> Every week, prepare AI fixes for the 3 posts with the biggest problems (you still approve them here)</label>
    <label class="check-row"><input type="checkbox" name="advisor_auto_apply" <?= setting('advisor_auto_apply')==='1'?'checked':'' ?>> <span><b>Fully automatic:</b> apply those weekly fixes without waiting for approval (new sections, FAQs, title, quick answer). A copy is saved first — check History each week and undo anything you don't like.</span></label>
    <label class="check-row"><input type="checkbox" name="advisor_auto_meta" <?= setting('advisor_auto_meta')==='1'?'checked':'' ?>> Automatically rewrite the SEO title &amp; description of pages with low click-through (max. once a month per page, undo in History)</label>
    <p class="hint">Runs from <code>scripts/content-advisor.php</code> (cron, hourly). Article text only changes automatically if “Fully automatic” is on.</p>
    <div><button class="button button-primary">Save</button></div></form></section>
 <?php elseif($tab==='ideas'): $status=in_array($_GET['status']??'',['new','covered','drafted','dismissed'],true)?$_GET['status']:'new';
  $ideas=query('SELECT k.*,c.name AS cat,r.title AS ptitle FROM keyword_ideas k LEFT JOIN categories c ON c.id=k.category_id LEFT JOIN reviews r ON r.id=k.post_id WHERE k.status'.($status==='new'?" IN ('new','queued')":'=?').' ORDER BY k.score DESC,k.impressions DESC LIMIT 150',$status==='new'?[]:[$status]); ?>
  <div class="doc-head"><div class="seg-tabs"><?php foreach(['new'=>'To write','covered'=>'Already covered','drafted'=>'Drafted','dismissed'=>'Hidden'] as $k=>$l): ?><a class="<?= $status===$k?'active':'' ?>" href="/admin.php?view=advisor&tab=ideas&status=<?= $k ?>"><?= $l ?></a><?php endforeach ?></div>
   <form method="post"><?= csrfField() ?><input type="hidden" name="action" value="refresh_ideas"><input type="hidden" name="tab" value="ideas"><button class="button button-outline"><?= aicon('clock') ?> Find new ideas</button></form></div>
  <p class="hint">Ideas come from Google's autocomplete (real searches) around your categories and focus keywords<?= gscKey()?', plus Search Console queries you already appear for':'' ?>. <b>Score</b> favours long, specific searches you can win quickly. Last checked: <?= setting('ideas_refreshed')?e(date('M j, g:i a',strtotime(setting('ideas_refreshed')))):'never' ?>.</p>
  <div class="table-wrap"><table class="list-table"><thead><tr><th>Keyword</th><th>Category</th><th>Score</th><th>Google</th><th class="kebab-col"></th></tr></thead><tbody>
  <?php foreach($ideas as $i): ?><tr><td><b><?= e($i['keyword']) ?></b><br><span class="muted"><?= e($i['source']) ?><?= $i['ptitle']?' · '.e($i['ptitle']):'' ?></span></td><td><?= e($i['cat']??'') ?></td>
   <td><span class="seo-score <?= $i['score']>=70?'good':($i['score']>=50?'ok':'bad') ?>"><?= (int)$i['score'] ?></span></td><td class="muted"><?= $i['impressions']?number_format($i['impressions']).' impr. · pos '.number_format($i['position'],1):'—' ?></td>
   <td class="nowrap"><div class="row-buttons">
    <?php if($i['status']==='queued'): ?><span class="chip chip-amber">Writing…</span>
    <?php elseif($i['post_id']): ?><a class="button button-outline button-sm" href="/admin.php?view=edit&id=<?= (int)$i['post_id'] ?>">Open post</a>
    <?php else: ?><form method="post" class="inline-form"><?= csrfField() ?><input type="hidden" name="action" value="idea_draft"><input type="hidden" name="tab" value="ideas"><input type="hidden" name="id" value="<?= $i['id'] ?>"><button class="button button-primary button-sm" <?= $ai?'':'disabled' ?> title="AI writes a full draft (not published)">AI draft</button></form><a class="button button-outline button-sm" href="/admin.php?view=edit&title=<?= rawurlencode(ucfirst($i['keyword'])) ?>">Write</a><?php endif ?>
    <?php if(in_array($i['status'],['new','dismissed'],true)): ?><form method="post" class="inline-form"><?= csrfField() ?><input type="hidden" name="action" value="idea_status"><input type="hidden" name="tab" value="ideas"><input type="hidden" name="id" value="<?= $i['id'] ?>"><input type="hidden" name="status" value="<?= $i['status']==='new'?'dismissed':'new' ?>"><button class="button button-outline button-sm"><?= $i['status']==='new'?'Hide':'Restore' ?></button></form><?php endif ?>
   </div></td></tr><?php endforeach ?>
  <?php if(!$ideas): ?><tr><td colspan="5" class="muted empty"><?= $status==='new'?'No ideas yet. Click “Find new ideas”.':'Nothing here.' ?></td></tr><?php endif ?>
  </tbody></table></div>
 <?php else: $revs=query('SELECT v.id,v.post_id,v.note,v.created_at,r.title FROM post_revisions v LEFT JOIN reviews r ON r.id=v.post_id ORDER BY v.id DESC LIMIT 60'); ?>
  <p class="hint">A copy of the article is saved before every AI change. Undo restores that copy (and saves the current version first, so undo can be undone).</p>
  <div class="table-wrap"><table class="list-table"><thead><tr><th>When</th><th>Article</th><th>Saved</th><th class="kebab-col"></th></tr></thead><tbody>
  <?php foreach($revs as $v): ?><tr><td class="nowrap"><?= e(date('M j, g:i a',strtotime($v['created_at']))) ?></td><td><a href="/admin.php?view=edit&id=<?= (int)$v['post_id'] ?>"><?= e($v['title']??'#'.$v['post_id']) ?></a></td><td class="muted"><?= e($v['note']) ?></td>
   <td><form method="post" data-confirm="Restore this version of the article?"><?= csrfField() ?><input type="hidden" name="action" value="undo_revision"><input type="hidden" name="id" value="<?= $v['id'] ?>"><button class="button button-outline button-sm">Restore</button></form></td></tr><?php endforeach ?>
  <?php if(!$revs): ?><tr><td colspan="4" class="muted empty">No AI changes yet.</td></tr><?php endif ?>
  </tbody></table></div>
 <?php endif ?>
 </section>
<?php elseif($view==='search'):
 $days=in_array((int)($_GET['days']??28),[7,28,90],true)?(int)($_GET['days']??28):28;
 $end=date('Y-m-d',strtotime('-2 days'));$start=date('Y-m-d',strtotime("$end -".($days-1).' days'));
 $pend=date('Y-m-d',strtotime("$start -1 day"));$pstart=date('Y-m-d',strtotime("$pend -".($days-1).' days'));
 $connected=gscKey()&&setting('gsc_property')!=='';$gscError=null;$refresh=isset($_GET['refresh']);
 if($connected){try{
  $tot=gscQuery([],$start,$end,1,$refresh)[0]??null;$prev=gscQuery([],$pstart,$pend,1,$refresh)[0]??null;
  $queries=gscQuery(['query'],$start,$end,1000,$refresh);$pages=gscQuery(['page'],$start,$end,500,$refresh);$qp=gscQuery(['query','page'],$start,$end,5000,$refresh);
 }catch(Throwable $ex){$gscError=$ex->getMessage();}}
 $fmtN=fn($n)=>$n>=1000?number_format($n/1000,1).'k':number_format($n);
 $delta=fn($a,$b,$inv=false)=>$b?(($d=($a-$b)/$b*100)>=0.5||$d<=-0.5?'<span class="delta '.((($d>0)!==$inv)?'up':'down').'">'.($d>0?'+':'').round($d).'%</span>':''):'';
 $editLink=function(string $url){$p=postForUrl($url);return $p?'<a href="/admin.php?view=edit&id='.$p['id'].'">'.e(mb_strimwidth($p['title'],0,60,'…')).'</a>':'<a href="'.e($url).'" target="_blank">'.e((string)parse_url($url,PHP_URL_PATH)).'</a>';}; ?>
 <section class="panel"><?= pageHead('search','Search Console','Your Google rankings: keywords, positions, clicks and where the quick wins are.',$connected?'<div class="seg-tabs">'.implode('',array_map(fn($d)=>'<a class="'.($d===$days?'active':'').'" href="/admin.php?view=search&days='.$d.'">'.$d.' days</a>',[7,28,90])).'</div><a class="button button-outline" href="/admin.php?view=search&days='.$days.'&refresh=1">'.aicon('clock').' Refresh</a>':'') ?>
 <?php if($gscError): ?><p class="notice notice-error"><?= e($gscError) ?></p><?php endif ?>
 <?php if($connected&&!$gscError): ?>
  <?php if($tot===null): ?><p class="notice">No Search Console data for <?= e($start) ?> – <?= e($end) ?> yet. New sites can take a few days to show data.</p><?php else: ?>
  <div class="glance glance-4"><?php foreach([['Clicks',$fmtN($tot['clicks']),$delta($tot['clicks'],$prev['clicks']??0),'teal','chart'],['Impressions',$fmtN($tot['impressions']),$delta($tot['impressions'],$prev['impressions']??0),'blue','search'],['Avg. CTR',round($tot['ctr']*100,1).'%',$delta($tot['ctr'],$prev['ctr']??0),'violet','check'],['Avg. position',number_format($tot['position'],1),$delta($tot['position'],$prev['position']??0,true),'amber','trophy']] as [$l,$v,$d,$tone,$ic]): ?><div class="tile tile-<?= $tone ?>"><span class="tile-icon"><?= aicon($ic) ?></span><span><b class="tile-num"><?= $v ?> <?= $d ?></b><span class="tile-label"><?= $l ?> · vs previous <?= $days ?> days</span></span></div><?php endforeach ?></div>
  <?php endif ?>
  <?php
   // Quick wins: page 1–2 positions with real demand — improving these pages moves traffic fastest.
   $opps=array_values(array_filter($qp,fn($r)=>$r['position']>=4.5&&$r['position']<=20&&$r['impressions']>=10));usort($opps,fn($a,$b)=>$b['impressions']<=>$a['impressions']);
   // Titles that underperform: good position, clicks well below the usual rate for that position.
   $ctrLow=array_values(array_filter($qp,fn($r)=>$r['position']<=10&&$r['impressions']>=30&&$r['ctr']<expectedCtr($r['position'])*0.6));usort($ctrLow,fn($a,$b)=>$b['impressions']<=>$a['impressions']);
   // Keywords nobody targets yet: queries with impressions whose page doesn't use them as focus keyword.
   $focus=array_map('mb_strtolower',array_column(query("SELECT focus_keyword FROM reviews WHERE focus_keyword!=''"),'focus_keyword'));
   $newTopics=array_values(array_filter($queries,fn($r)=>$r['position']>20&&$r['impressions']>=5&&!in_array(mb_strtolower($r['keys'][0]),$focus,true)));usort($newTopics,fn($a,$b)=>$b['impressions']<=>$a['impressions']);
   $table=function(string $title,string $hint,array $rows,array $cols){ ?>
    <section class="box gsc-box"><h2 class="box-title"><?= $title ?></h2><p class="hint"><?= $hint ?></p>
    <?php if(!$rows): ?><p class="muted">Nothing here for this period.</p><?php else: ?><div class="table-wrap"><table class="list-table gsc-table"><thead><tr><?php foreach($cols as $c=>$fn): ?><th><?= $c ?></th><?php endforeach ?></tr></thead><tbody>
    <?php foreach(array_slice($rows,0,25) as $r): ?><tr><?php foreach($cols as $fn): ?><td><?= $fn($r) ?></td><?php endforeach ?></tr><?php endforeach ?></tbody></table></div><?php endif ?></section>
   <?php };
   $pos=fn($r)=>'<b class="pos '.($r['position']<=3?'pos-top':($r['position']<=10?'pos-p1':'pos-p2')).'">'.number_format($r['position'],1).'</b>';
   $table(aicon('trophy','icon title-icon').' Quick wins: positions 5–20','These keywords already rank on page 1–2. Improve the page (add the keyword to a heading, expand the section, add FAQs, internal links) to move into the top 3.',$opps,['Keyword'=>fn($r)=>e($r['keys'][0]),'Page'=>fn($r)=>$editLink($r['keys'][1]),'Position'=>$pos,'Impressions'=>fn($r)=>number_format($r['impressions']),'Clicks'=>fn($r)=>number_format($r['clicks'])]);
   $table(aicon('pen','icon title-icon').' Low click-through: rewrite the title & description','Google shows these pages high up, but fewer people click than usual for that position. A clearer, more specific SEO title and meta description can double the clicks.',$ctrLow,['Keyword'=>fn($r)=>e($r['keys'][0]),'Page'=>fn($r)=>$editLink($r['keys'][1]),'Position'=>$pos,'CTR'=>fn($r)=>round($r['ctr']*100,1).'%','Expected'=>fn($r)=>'≈'.round(expectedCtr($r['position'])*100).'%']);
   $table(aicon('search','icon title-icon').' Top keywords','The searches that bring visitors now.',$queries,['Keyword'=>fn($r)=>e($r['keys'][0]),'Clicks'=>fn($r)=>number_format($r['clicks']),'Impressions'=>fn($r)=>number_format($r['impressions']),'CTR'=>fn($r)=>round($r['ctr']*100,1).'%','Position'=>$pos]);
   $table(aicon('file','icon title-icon').' Top pages','Which articles earn the most search traffic.',$pages,['Page'=>fn($r)=>$editLink($r['keys'][0]),'Clicks'=>fn($r)=>number_format($r['clicks']),'Impressions'=>fn($r)=>number_format($r['impressions']),'CTR'=>fn($r)=>round($r['ctr']*100,1).'%','Position'=>$pos]);
   $table(aicon('plus','icon title-icon').' New article ideas','Searches you appear for (beyond page 2) that no article targets as its focus keyword yet: good topics for new posts.',$newTopics,['Keyword'=>fn($r)=>e($r['keys'][0]),'Impressions'=>fn($r)=>number_format($r['impressions']),'Position'=>$pos,''=>fn($r)=>'<a class="button button-outline button-sm" href="/admin.php?view=edit&amp;title='.rawurlencode(ucfirst($r['keys'][0])).'">Write</a>']);
  ?>
 <?php endif ?>
 <details class="box" <?= $connected?'':'open' ?>><summary class="box-title"><?= aicon('gear','icon title-icon') ?> Connection <?= $connected?'<span class="chip chip-green">Connected'.(($k=gscKey())?' as '.e($k['client_email']):'').'</span>':'' ?></summary>
  <form method="post" class="stack"><?= csrfField() ?><input type="hidden" name="action" value="gsc_settings">
   <ol class="setup-steps"><li>In <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud</a>, create a project and enable the <b>Google Search Console API</b>.</li><li>Create a <b>service account</b> → Keys → Add key → JSON, and download it.</li><li>In <a href="https://search.google.com/search-console" target="_blank" rel="noopener">Search Console</a> → Settings → Users and permissions → Add user: the service account e-mail, permission <b>Restricted</b>.</li><li>Paste the property and the JSON key below.</li></ol>
   <label>Property<input class="input" name="gsc_property" value="<?= e(setting('gsc_property')) ?>" placeholder="sc-domain:besttop10things.com  or  https://www.besttop10things.com/"></label>
   <label>Service account JSON key <span class="muted"><?= gscKey()?'(saved — paste a new key only to replace it)':'' ?></span><textarea class="input code-input" name="gsc_key" rows="5" spellcheck="false" placeholder='{"type": "service_account", ...}'></textarea></label>
   <p class="hint">The key is stored outside the website folder and is never shown again. Access is read-only.</p>
   <div class="row-buttons"><button class="button button-primary"><?= aicon('send') ?> Save</button><?php if(gscKey()): ?><button class="button button-outline" name="gsc_disconnect" value="1">Disconnect</button><?php endif ?></div>
  </form></details>
 </section>
<?php elseif($view==='authors'): $aid=(int)($_GET['id']??0); $editA=$aid?(query('SELECT * FROM authors WHERE id=?',[$aid])[0]??null):null;
 $formA=$error&&($_POST['action']??'')==='save_author'?array_merge(['id'=>$aid,'avatar'=>''],array_intersect_key($_POST,array_flip(['name','slug','role','bio','avatar','expertise','links']))):($editA??['id'=>0,'name'=>'','slug'=>'','role'=>'','bio'=>'','avatar'=>'','expertise'=>'','links'=>'']);
 $acounts=[];foreach(query("SELECT lower(author) AS a,COUNT(*) AS n FROM reviews WHERE status!='trash' GROUP BY lower(author)") as $x)$acounts[$x["a"]]=(int)$x["n"]; ?>
 <section class="panel"><?= pageHead('users','Authors','Author profiles build trust with readers and Google (E-E-A-T). A post links to a profile when its Author field matches the name.') ?>
 <div class="two-col">
  <section class="box"><h2 class="box-title"><?= aicon($formA['id']?'pen':'plus','icon title-icon') ?> <?= $formA['id']?'Edit author':'Add author' ?></h2>
   <form method="post" enctype="multipart/form-data" class="stack"><?= csrfField() ?><input type="hidden" name="action" value="save_author"><input type="hidden" name="id" value="<?= (int)$formA['id'] ?>">
    <label>Name<input class="input" name="name" value="<?= e($formA['name']) ?>" required maxlength="80" placeholder="e.g. Naveen Prajapati"></label>
    <label>Role / title<input class="input" name="role" value="<?= e($formA['role']) ?>" maxlength="120" placeholder="e.g. Senior Tech Editor"></label>
    <label>Bio<textarea class="input" name="bio" rows="5" maxlength="3000" placeholder="Experience, what you test, why readers can trust you."><?= e($formA['bio']) ?></textarea></label>
    <label>Expertise <span class="muted">(comma separated)</span><input class="input" name="expertise" value="<?= e($formA['expertise']) ?>" maxlength="300" placeholder="Laptops, Travel gear, Web hosting"></label>
    <label>Profile links <span class="muted">(one https:// per line: LinkedIn, X, Instagram…)</span><textarea class="input" name="links" rows="3" maxlength="2000"><?= e($formA['links']) ?></textarea></label>
    <?php if($formA['avatar']!==''&&safeImage($formA['avatar'])): ?><img class="feat-preview author-preview" src="<?= e($formA['avatar']) ?>" alt=""><?php endif ?>
    <label>Photo<input class="input input-sm" type="file" name="avatar_upload" accept="image/jpeg,image/png,image/webp"></label>
    <label>Or photo URL<input class="input input-sm" name="avatar" value="<?= e($formA['avatar']) ?>" placeholder="/uploads/… or https://…"></label>
    <label>Profile URL<input class="input input-sm" name="slug" value="<?= e($formA['slug']) ?>" placeholder="auto from the name"></label>
    <div class="row-buttons"><button class="button button-primary"><?= aicon('send') ?> Save author</button><?php if($formA['id']): ?><a class="button button-outline" href="/admin.php?view=authors">Cancel</a><?php endif ?></div>
   </form>
  </section>
  <div class="table-wrap"><table class="list-table"><thead><tr><th>Author</th><th>Posts</th><th class="kebab-col"><span class="sr-only">Actions</span></th></tr></thead><tbody>
   <?php foreach(query('SELECT * FROM authors ORDER BY name') as $a): ?><tr><td><div class="title-cell"><?php if($a['avatar']!==''&&safeImage($a['avatar'])): ?><img class="thumb thumb-round" src="<?= e($a['avatar']) ?>" alt=""><?php else: ?><span class="avatar avatar-letter"><?= e(mb_strtoupper(mb_substr($a['name'],0,1))) ?></span><?php endif ?><div><a class="row-title" href="/admin.php?view=authors&id=<?= $a['id'] ?>"><?= e($a['name']) ?></a><br><span class="muted"><?= e($a['role']) ?></span></div></div></td>
    <td><?= $acounts[mb_strtolower($a["name"])]??0 ?></td>
    <td class="nowrap"><div class="row-buttons"><a class="button button-outline button-sm" href="<?= e(authorUrl($a)) ?>" target="_blank">View</a><form method="post" class="inline-form" data-confirm="Delete this author profile? Posts keep the name."><?= csrfField() ?><input type="hidden" name="action" value="delete_author"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="button button-outline button-sm">Delete</button></form></div></td></tr><?php endforeach ?>
   <?php if(!query('SELECT 1 FROM authors LIMIT 1')): ?><tr><td colspan="3" class="muted empty">No author profiles yet. Add the people who write and review for the site.</td></tr><?php endif ?>
   <?php $unlinked=array_filter(query("SELECT author,COUNT(*) AS n FROM reviews WHERE status!='trash' GROUP BY lower(author)"),fn($x)=>!authorByName((string)$x['author'])); if($unlinked): ?><tr><td colspan="3" class="muted">Names on posts without a profile: <?= e(implode(', ',array_map(fn($x)=>$x['author'].' ('.$x['n'].')',$unlinked))) ?></td></tr><?php endif ?>
  </tbody></table></div>
 </div>
 <section class="box" id="methodology"><h2 class="box-title"><?= aicon('file','icon title-icon') ?> “How we review” page <a class="muted" href="/how-we-review" target="_blank">/how-we-review</a></h2>
  <form method="post" class="stack"><?= csrfField() ?><input type="hidden" name="action" value="save_methodology">
   <textarea class="input code-input" name="methodology" rows="16"><?= e(trim(setting('methodology'))?:DEFAULT_METHODOLOGY) ?></textarea>
   <p class="hint">Markdown: <code>## heading</code>, <code>- list</code>, <code>**bold**</code>. Explain your research process, scoring scale and affiliate policy honestly.</p>
   <div><button class="button button-primary"><?= aicon('send') ?> Save page</button></div>
  </form></section>
 </section>
<?php elseif($view==='appearance'): $images=libraryImages();
 // After a failed save, show what was submitted rather than the stored menu.
 $menuRows=$error&&($_POST['action']??'')==='appearance'?array_map(fn($l,$u,$t)=>['label'=>(string)$l,'url'=>(string)$u,'type'=>(string)$t],(array)($_POST['menu_label']??[]),(array)($_POST['menu_url']??[]),(array)($_POST['menu_type']??[])):siteMenu(); ?>
 <section class="panel"><?= pageHead('image','Appearance','Logo, header menu and SEO settings for the public site.','<a class="button button-outline" href="/" target="_blank">'.aicon('right').' View site</a>') ?>
 <form method="post" enctype="multipart/form-data" class="appearance" data-appearance><?= csrfField() ?><input type="hidden" name="action" value="appearance">
  <div class="two-col">
   <div class="stack">
    <?php foreach(['logo'=>['Logo','Shown in the header, footer and as the browser tab icon. A square image works best.'],'og_image'=>['Social share image','Used when the site is shared on Facebook, X, WhatsApp and others (1200×630 recommended).']] as $k=>[$label,$hint]): $cur=setting($k); ?>
    <section class="box"><h2 class="box-title"><?= aicon('image','icon title-icon') ?> <?= $label ?></h2>
     <div class="logo-preview<?= $k==='og_image'?' wide':'' ?>"><?php if($cur!==''): ?><img src="<?= e($cur) ?>" alt="Current <?= strtolower($label) ?>" data-preview-<?= $k ?>><?php else: ?><span class="muted" data-preview-<?= $k ?>><?= $k==='logo'?'Default crown icon':'None set' ?></span><?php endif ?></div>
     <p class="hint"><?= $hint ?></p>
     <label>Upload new<input class="input input-sm" type="file" name="<?= $k ?>_upload" accept="image/jpeg,image/png,image/webp"></label>
     <details class="library"><summary>Choose from Media Library</summary><div class="library-grid"><?php foreach($images as $img): ?><label><input type="radio" name="<?= $k ?>_pick" value="<?= e($img) ?>" data-fill="<?= $k ?>"><img src="<?= e($img) ?>" alt="" loading="lazy"></label><?php endforeach ?></div></details>
     <label>Or image URL<input class="input input-sm" name="<?= $k ?>" value="<?= e($cur) ?>" placeholder="https://… or /uploads/…"></label>
     <?php if($cur!==''): ?><label class="check-row"><input type="checkbox" name="<?= $k ?>_remove" value="1"> Remove <?= strtolower($label) ?></label><?php endif ?>
    </section>
    <?php endforeach ?>
   </div>
   <div class="stack">
    <section class="box"><h2 class="box-title"><?= aicon('menu','icon title-icon') ?> Header Menu</h2>
     <p class="hint">Links can be site pages (start with <code>/</code>, e.g. <code>/top-10</code>) or full <code>https://</code> URLs. "Categories dropdown" shows every category under that item.</p>
     <div class="menu-rows" data-menu-rows>
      <?php foreach($menuRows as $item): ?>
      <div class="menu-row" data-menu-row><span class="drag" aria-hidden="true">⋮⋮</span>
       <input class="input" name="menu_label[]" value="<?= e($item['label']) ?>" placeholder="Label" aria-label="Menu label" maxlength="40">
       <input class="input" name="menu_url[]" value="<?= e($item['url']) ?>" placeholder="/reviews or https://…" aria-label="Menu link">
       <select class="input" name="menu_type[]" aria-label="Item type"><option value="link">Link</option><option value="categories" <?= ($item['type']??'')==='categories'?'selected':'' ?>>Categories dropdown</option></select>
       <span class="menu-btns"><button type="button" class="icon-danger" data-move="-1" aria-label="Move up" title="Move up">↑</button><button type="button" class="icon-danger" data-move="1" aria-label="Move down" title="Move down">↓</button><button type="button" class="icon-danger" data-remove aria-label="Remove item" title="Remove"><?= aicon('trash') ?></button></span>
      </div>
      <?php endforeach ?>
     </div>
     <div><button type="button" class="button button-outline" data-menu-add><?= aicon('plus') ?> Add menu item</button></div>
     <p class="hint">Quick links: <code>/reviews</code> · <code>/top-10</code> · <code>/categories</code> · <code>/compare</code> · <code>/about</code> · <code>/about#how</code> · <code>/category/tech</code></p>
    </section>
    <section class="box"><h2 class="box-title"><?= aicon('search','icon title-icon') ?> SEO</h2><div class="stack">
     <label><span class="lbl">Homepage title <span class="muted" data-count-for="seo_title"></span></span><input class="input" name="seo_title" value="<?= e(setting('seo_title')) ?>" maxlength="200" placeholder="<?= e(setting('site_name').' | Reviews, Comparisons & Buying Guides') ?>" data-count="60"></label>
     <p class="hint">Shown as the browser/Google title of the homepage. Keep it different from the description; leave empty to use the placeholder.</p>
     <p class="hint">The default meta description is the homepage description in <a href="/admin.php?view=settings">Settings</a>. Each post has its own SEO title and description in the editor.</p>
     <label><span class="lbl">Keywords <span class="muted">(comma separated)</span></span><textarea class="input" name="meta_keywords" rows="2" maxlength="500" placeholder="best products, reviews, top 10 lists, buying guides"><?= e(setting('meta_keywords')) ?></textarea></label>
     <p class="hint">Verification codes, Google Analytics, custom header/footer code and AI SEO settings are in <a href="/admin.php?view=seo">SEO &amp; Code</a>.</p>
    </div></section>
   </div>
  </div>
  <div class="save-bar"><button class="button button-primary button-lg"><?= aicon('send') ?> Save Appearance</button></div>
 </form>
 <template data-menu-template><div class="menu-row" data-menu-row><span class="drag" aria-hidden="true">⋮⋮</span><input class="input" name="menu_label[]" placeholder="Label" aria-label="Menu label" maxlength="40"><input class="input" name="menu_url[]" placeholder="/reviews or https://…" aria-label="Menu link"><select class="input" name="menu_type[]" aria-label="Item type"><option value="link">Link</option><option value="categories">Categories dropdown</option></select><span class="menu-btns"><button type="button" class="icon-danger" data-move="-1" aria-label="Move up" title="Move up">↑</button><button type="button" class="icon-danger" data-move="1" aria-label="Move down" title="Move down">↓</button><button type="button" class="icon-danger" data-remove aria-label="Remove item" title="Remove"><?= aicon('trash') ?></button></span></div></template>
 </section>

<?php elseif($view==='clicks'):
 [$where,$params,$f]=clickFilters();
 $base="FROM clicks k JOIN links l ON l.id=k.link_id LEFT JOIN reviews r ON r.id=k.post_id WHERE $where";
 $one=fn(string $sql)=>query($sql,$params)[0]??[];
 $tot=$one("SELECT COUNT(*) n,COUNT(DISTINCT NULLIF(k.visitor,'')) v,COUNT(DISTINCT k.post_id) p,COUNT(DISTINCT k.link_id) l,SUM(substr(k.created_at,1,10)='".date('Y-m-d')."') t $base");
 $byDay=[];foreach(query("SELECT substr(k.created_at,1,10) d,COUNT(*) n $base GROUP BY d",$params) as $row)$byDay[$row['d']]=(int)$row['n'];
 $days=[];for($d=strtotime($f['from']);$d<=strtotime($f['to'])&&count($days)<370;$d+=86400)$days[date('Y-m-d',$d)]=$byDay[date('Y-m-d',$d)]??0;
 $max=max(1,...array_values($days)?:[1]);
 $group=fn(string $col,int $limit=8)=>query("SELECT $col k,COUNT(*) n $base GROUP BY $col ORDER BY n DESC LIMIT $limit",$params);
 $topPosts=query("SELECT k.post_id k,COALESCE(r.title,'(no article)') label,COUNT(*) n $base GROUP BY k.post_id ORDER BY n DESC LIMIT 8",$params);
 $topLinks=query("SELECT k.link_id k,l.url label,COUNT(*) n $base GROUP BY k.link_id ORDER BY n DESC LIMIT 8",$params);
 $page=max(1,(int)($_GET['p']??1));$per=50;$pages=max(1,(int)ceil(((int)($tot['n']??0))/$per));$page=min($page,$pages);
 $rows=query("SELECT k.*,l.url,r.title,r.slug $base ORDER BY k.id DESC LIMIT $per OFFSET ".(($page-1)*$per),$params);
 $places=['body'=>'In article text','cta-toc'=>'Sidebar "Shop on" button','cta-about'=>'About brand box','cta-band'=>'Brand band'];
 $host=fn(string $u)=>preg_replace('/^www\./','',(string)parse_url($u,PHP_URL_HOST));
 $breakdowns=[['Traffic sources','source',$group('k.source')],['Devices','device',$group('k.device')],['Browsers',null,$group('k.browser')],['Operating systems',null,$group('k.os')],['Placement on page','placement',$group('k.placement')],['Countries','country',$group("NULLIF(k.country,'')")]];
?>
 <section class="panel"><?= pageHead('chart','Clicks','Every tracked outgoing link click: who clicked, from where, when and on which device.','<a class="button button-outline" href="'.e(clickUrl($f,['export'=>1])).'">'.aicon('file').' Export CSV</a>') ?>
 <form class="filters" method="get"><input type="hidden" name="view" value="clicks">
  <label>From<input class="input" type="date" name="from" value="<?= e($f['from']) ?>"></label>
  <label>To<input class="input" type="date" name="to" value="<?= e($f['to']) ?>"></label>
  <label>Article<select class="input" name="post"><option value="">All articles</option><?php foreach(query("SELECT DISTINCT r.id,r.title FROM clicks k JOIN reviews r ON r.id=k.post_id ORDER BY r.title") as $o): ?><option value="<?= $o['id'] ?>" <?= $f['post']===(int)$o['id']?'selected':'' ?>><?= e(mb_strimwidth($o['title'],0,60,'…')) ?></option><?php endforeach ?></select></label>
  <label>Link<select class="input" name="link"><option value="">All links</option><?php foreach(query("SELECT DISTINCT l.id,l.url FROM clicks k JOIN links l ON l.id=k.link_id ORDER BY l.url") as $o): ?><option value="<?= $o['id'] ?>" <?= $f['link']===(int)$o['id']?'selected':'' ?>><?= e($host($o['url']).' — '.mb_strimwidth((string)parse_url($o['url'],PHP_URL_PATH),0,40,'…')) ?></option><?php endforeach ?></select></label>
  <label>Source<select class="input" name="source"><option value="">All sources</option><?php foreach(query("SELECT DISTINCT source FROM clicks ORDER BY source") as $o): ?><option <?= $f['source']===$o['source']?'selected':'' ?>><?= e($o['source']) ?></option><?php endforeach ?></select></label>
  <label>Device<select class="input" name="device"><option value="">All devices</option><?php foreach(['Desktop','Mobile','Tablet','Bot'] as $o): ?><option <?= $f['device']===$o?'selected':'' ?>><?= $o ?></option><?php endforeach ?></select></label>
  <label>Placement<select class="input" name="placement"><option value="">Anywhere</option><?php foreach($places as $k=>$o): ?><option value="<?= $k ?>" <?= $f['placement']===$k?'selected':'' ?>><?= e($o) ?></option><?php endforeach ?></select></label>
  <label class="filters-wide">Search<input class="input" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="URL, link text, visitor ID or IP"></label>
  <label class="check-row"><input type="checkbox" name="bots" value="1" <?= $f['bots']?'checked':'' ?>> Include bots</label>
  <label class="check-row"><input type="checkbox" name="admins" value="1" <?= $f['admins']?'checked':'' ?>> Include admin clicks</label>
  <div class="filters-actions"><button class="button button-primary"><?= aicon('search') ?> Apply</button><a class="button button-outline" href="/admin.php?view=clicks">Reset</a></div>
 </form>
 <div class="glance glance-4">
  <?php foreach([['Clicks',(int)($tot['n']??0),'chart','teal'],['Unique visitors',(int)($tot['v']??0),'users','blue'],['Clicks today',(int)($tot['t']??0),'clock','violet'],['Articles clicked',(int)($tot['p']??0),'file','amber']] as [$label,$n,$ic,$tone]): ?>
   <div class="tile tile-<?= $tone ?>"><span class="tile-icon"><?= aicon($ic) ?></span><span><b class="tile-num"><?= number_format($n) ?></b><span class="tile-label"><?= $label ?></span></span></div>
  <?php endforeach ?>
 </div>
 <section class="box chart-box"><h2 class="box-title"><?= aicon('chart','icon title-icon') ?> Clicks per day <span class="muted"><?= e(date('M j',strtotime($f['from']))) ?> – <?= e(date('M j, Y',strtotime($f['to']))) ?></span></h2>
  <?php $n=count($days);$w=max(1,$n); ?>
  <svg class="bar-chart" viewBox="0 0 <?= $w*10 ?> 120" preserveAspectRatio="none" role="img" aria-label="Clicks per day">
   <?php $i=0;foreach($days as $d=>$c): $h=$c?max(2,round($c/$max*110)):0; ?><rect x="<?= $i*10+1 ?>" y="<?= 115-$h ?>" width="8" height="<?= $h ?>" rx="1.5"><title><?= e(date('D, M j',strtotime($d))) ?>: <?= $c ?> click<?= $c===1?'':'s' ?></title></rect><?php $i++;endforeach ?>
   <line x1="0" y1="115.5" x2="<?= $w*10 ?>" y2="115.5"/>
  </svg>
  <div class="chart-axis"><span><?= e(date('M j',strtotime($f['from']))) ?></span><span>max <?= $max ?>/day</span><span><?= e(date('M j',strtotime($f['to']))) ?></span></div>
 </section>
 <div class="breakdowns">
  <section class="box"><h2 class="box-title"><?= aicon('file','icon title-icon') ?> Top articles</h2><?php if(!$topPosts): ?><p class="muted">No clicks yet.</p><?php endif ?><ul class="bars"><?php foreach($topPosts as $row): ?><li><a href="<?= e(clickUrl($f,['post'=>(int)$row['k']])) ?>"><span><?= e($row['label']) ?></span><b><?= $row['n'] ?></b></a><i><em data-w="<?= round($row['n']/max(1,$tot['n'])*100) ?>"></em></i></li><?php endforeach ?></ul></section>
  <section class="box"><h2 class="box-title"><?= aicon('send','icon title-icon') ?> Top destinations</h2><?php if(!$topLinks): ?><p class="muted">No clicks yet.</p><?php endif ?><ul class="bars"><?php foreach($topLinks as $row): ?><li><a href="<?= e(clickUrl($f,['link'=>(int)$row['k']])) ?>" title="<?= e($row['label']) ?>"><span><b class="host"><?= e($host($row['label'])) ?></b> <?= e(mb_strimwidth((string)parse_url($row['label'],PHP_URL_PATH),0,40,'…')) ?></span><b><?= $row['n'] ?></b></a><i><em data-w="<?= round($row['n']/max(1,$tot['n'])*100) ?>"></em></i></li><?php endforeach ?></ul></section>
  <?php foreach($breakdowns as [$title,$param,$data]): ?>
  <section class="box"><h2 class="box-title"><?= $title ?></h2><?php if(!$data): ?><p class="muted">No data.</p><?php endif ?><ul class="bars"><?php foreach($data as $row): $label=$row['k']??'Unknown'; $label=$param==='placement'?($places[$label]??($label?:'Unknown')):$label; ?><li><?php if($param&&$row['k']!==null): ?><a href="<?= e(clickUrl($f,[$param=>$row['k']])) ?>"><?php else: ?><a><?php endif ?><span><?= e($label) ?></span><b><?= $row['n'] ?></b></a><i><em data-w="<?= round($row['n']/max(1,$tot['n'])*100) ?>"></em></i></li><?php endforeach ?></ul></section>
  <?php endforeach ?>
 </div>
 <h2 class="section-h"><?= aicon('users','icon title-icon') ?> Click log <span class="muted"><?= number_format((int)($tot['n']??0)) ?> clicks</span></h2>
 <div class="table-wrap"><table class="list-table click-table"><thead><tr><th>Time</th><th>Article &amp; link</th><th>Came from</th><th>Visitor</th><th>Device</th></tr></thead><tbody>
  <?php foreach($rows as $c): ?><tr>
   <td class="nowrap"><b><?= e(date('M j, Y',strtotime($c['created_at']))) ?></b><br><span class="muted"><?= e(date('g:i:s a',strtotime($c['created_at']))) ?></span></td>
   <td><?php if($c['title']): ?><a class="row-title" href="<?= e(clickUrl($f,['post'=>(int)$c['post_id']])) ?>"><?= e($c['title']) ?></a><br><?php endif ?><span class="muted">→ <a href="<?= e($c['url']) ?>" target="_blank" rel="noopener noreferrer" title="<?= e($c['url']) ?>"><?= e($host($c['url'])) ?></a><?= $c['anchor']!==''?' · “'.e($c['anchor']).'”':'' ?> · <?= e($places[$c['placement']]??$c['placement']) ?></span></td>
   <td><a href="<?= e(clickUrl($f,['source'=>$c['source']])) ?>"><span class="chip chip-teal"><?= e($c['source']?:'Direct') ?></span></a><?php if($c['utm_campaign']!==''): ?><br><span class="muted">Campaign: <?= e($c['utm_campaign']) ?><?= $c['utm_medium']!==''?' / '.e($c['utm_medium']):'' ?></span><?php endif ?><?php if($c['referrer']!==''): ?><br><span class="muted ellipsis" title="<?= e($c['referrer']) ?>"><?= e(mb_strimwidth($c['referrer'],0,48,'…')) ?></span><?php endif ?></td>
   <td><a href="<?= e(clickUrl($f,['q'=>$c['visitor']])) ?>" title="Show all clicks by this visitor"><code><?= e($c['visitor']!==''?substr($c['visitor'],0,8):'—') ?></code></a><br><span class="muted"><?= e($c['ip']) ?><?= $c['country']!==''?' · '.e($c['country']):'' ?></span><?= $c['is_admin']?' <span class="tag">Admin</span>':'' ?></td>
   <td><?= e($c['device']) ?><br><span class="muted"><?= e($c['browser']) ?> · <?= e($c['os']) ?></span></td>
  </tr><?php endforeach ?>
  <?php if(!$rows): ?><tr><td colspan="5" class="empty muted">No clicks match these filters yet. Links in articles are tracked automatically from now on.</td></tr><?php endif ?>
 </tbody></table></div>
 <?php if($pages>1): ?><nav class="pagination" aria-label="Pages"><?php for($i=max(1,$page-3);$i<=min($pages,$page+3);$i++): ?><a class="<?= $i===$page?'current':'' ?>" href="<?= e(clickUrl($f,['p'=>$i])) ?>"><?= $i ?></a><?php endfor ?></nav><?php endif ?>
 <form method="post" class="box stack narrow tracking-settings"><?= csrfField() ?><input type="hidden" name="action" value="tracking"><h2 class="box-title"><?= aicon('gear','icon title-icon') ?> Affiliate sub-ID <span class="muted">(optional)</span></h2>
  <p class="hint">Adds this click's ID to every outgoing link, so conversions in your affiliate dashboard can be matched to a click here. For Impact links (sjv.io, pxf.io, evyy.net…) use <code>subId1</code>. Leave empty to turn it off.</p>
  <label>Parameter name<input class="input" name="subid_param" value="<?= e(setting('subid_param')) ?>" placeholder="subId1" maxlength="30"></label>
  <div><button class="button button-primary">Save</button></div></form>
 </section>

<?php elseif($view==='analytics'):
 $f=['from'=>(string)($_GET['from']??''),'to'=>(string)($_GET['to']??''),'path'=>mb_substr((string)($_GET['path']??''),0,300),'source'=>mb_substr((string)($_GET['source']??''),0,100),'country'=>strtoupper(substr((string)($_GET['country']??''),0,2)),'device'=>(string)($_GET['device']??''),'visitor'=>preg_replace('/[^a-f0-9]/','',(string)($_GET['visitor']??'')),'admins'=>!empty($_GET['admins'])];
 foreach(['from','to'] as $k)if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$f[$k]))$f[$k]=$k==='from'?date('Y-m-d',strtotime('-29 days')):date('Y-m-d');
 $aurl=fn(array $o=[])=>'/admin.php?'.http_build_query(array_filter(['view'=>'analytics']+array_merge($f,$o),fn($v)=>$v!==null&&$v!==''&&$v!==false));
 $w=['h.day>=?','h.day<=?'];$p=[$f['from'],$f['to']];
 if(!$f['admins'])$w[]='h.is_admin=0';
 foreach(['path'=>'h.path','source'=>'h.source','country'=>'h.country','device'=>'h.device','visitor'=>'h.visitor'] as $k=>$col)if($f[$k]!==''){$w[]="$col=?";$p[]=$f[$k];}
 $where=implode(' AND ',$w);$A=fn(string $sql)=>aquery($sql,$p);
 $tot=$A("SELECT COUNT(*) pv,COUNT(DISTINCT visitor) v,COUNT(DISTINCT session) s,ROUND(AVG(NULLIF(seconds,0))) sec,SUM(is_new) nw FROM hits h WHERE $where")[0];
 $bounce=$A("SELECT ROUND(100.0*SUM(n=1)/MAX(COUNT(*),1)) b FROM (SELECT COUNT(*) n FROM hits h WHERE $where GROUP BY session)")[0]['b']??0;
 $live=aquery("SELECT COUNT(DISTINCT visitor) n FROM hits WHERE ts>=? AND is_admin=0",[date('Y-m-d\TH:i:s',time()-300)])[0]['n']??0;
 $byDay=[];foreach($A("SELECT day,COUNT(*) pv,COUNT(DISTINCT visitor) v FROM hits h WHERE $where GROUP BY day") as $r)$byDay[$r['day']]=$r;
 $days=[];for($d=strtotime($f['from']);$d<=strtotime($f['to'])&&count($days)<370;$d+=86400){$k=date('Y-m-d',$d);$days[$k]=[(int)($byDay[$k]['pv']??0),(int)($byDay[$k]['v']??0)];}
 $max=max(1,...array_map(fn($x)=>$x[0],array_values($days))?:[1]);
 $G=fn(string $col,int $n=10,string $extra='')=>$A("SELECT $col k,COUNT(*) n,COUNT(DISTINCT visitor) v FROM hits h WHERE $where $extra GROUP BY k HAVING k IS NOT NULL ORDER BY v DESC,n DESC LIMIT $n");
 $pages=$A("SELECT path,MAX(title) title,COUNT(*) n,COUNT(DISTINCT visitor) v,ROUND(AVG(NULLIF(seconds,0))) sec,ROUND(AVG(NULLIF(scroll,0))) sc FROM hits h WHERE $where GROUP BY path ORDER BY n DESC LIMIT 25");
 $ew=['e.day>=?','e.day<=?'];$ep=[$f['from'],$f['to']];if(!$f['admins'])$ew[]='e.is_admin=0';if($f['path']!==''){$ew[]='e.path=?';$ep[]=$f['path'];}if($f['visitor']!==''){$ew[]='e.visitor=?';$ep[]=$f['visitor'];}
 $clicks=aquery("SELECT kind,target,MAX(label) label,COUNT(*) n,COUNT(DISTINCT visitor) v FROM events e WHERE ".implode(' AND ',$ew)." GROUP BY kind,target ORDER BY n DESC LIMIT 25",$ep);
 $kinds=['affiliate'=>'Affiliate','outbound'=>'Other website','link'=>'Own page','button'=>'Button','download'=>'Download'];
 $pg=max(1,(int)($_GET['p']??1));$per=50;$npg=max(1,(int)ceil((int)$tot['pv']/$per));$pg=min($pg,$npg);
 $log=$A("SELECT * FROM hits h WHERE $where ORDER BY h.id DESC LIMIT $per OFFSET ".(($pg-1)*$per));
 $journey=$f['visitor']!==''?aquery("SELECT ts,path,'view' kind,'' label FROM hits WHERE visitor=? UNION ALL SELECT ts,target,kind,label FROM events WHERE visitor=? ORDER BY ts DESC LIMIT 200",[$f['visitor'],$f['visitor']]):[];
 $dur=fn($s)=>$s?($s>=60?floor($s/60).'m '.($s%60).'s':$s.'s'):'—';
 $boxes=[['Traffic sources','source',$G('h.source')],['Websites that sent visitors',null,$G("NULLIF(h.ref_host,'')")],['Countries','country',$G("NULLIF(h.country,'')")],['Time zones (region / city)',null,$G("NULLIF(h.tz,'')")],
  ['Entry pages (first page of a visit)','path',$G('h.path',10,'AND h.entry=1')],['Campaigns (utm)',null,$G("NULLIF(h.utm_campaign,'')")],['Devices','device',$G('h.device')],['Browsers',null,$G('h.browser')],['Operating systems',null,$G('h.os')],['Languages',null,$G("NULLIF(h.lang,'')")],['Screen sizes',null,$G("NULLIF(h.screen,'')")],['New vs returning',null,$A("SELECT CASE WHEN is_new=1 THEN 'New visitors' ELSE 'Returning' END k,COUNT(*) n,COUNT(DISTINCT visitor) v FROM hits h WHERE $where GROUP BY k ORDER BY v DESC")]];
 $vtot=max(1,(int)$tot['v']);
?>
 <section class="panel"><?= pageHead('chart','Analytics','Every visit to your website: who came, from where, which pages they read, how long, and what they clicked.','<a class="button button-outline" href="'.e($aurl(['export'=>1])).'">'.aicon('file').' Export CSV</a>') ?>
 <form class="filters" method="get"><input type="hidden" name="view" value="analytics">
  <label>From<input class="input" type="date" name="from" value="<?= e($f['from']) ?>"></label>
  <label>To<input class="input" type="date" name="to" value="<?= e($f['to']) ?>"></label>
  <label>Device<select class="input" name="device"><option value="">All devices</option><?php foreach(['Desktop','Mobile','Tablet'] as $o): ?><option <?= $f['device']===$o?'selected':'' ?>><?= $o ?></option><?php endforeach ?></select></label>
  <label>Page<input class="input" name="path" value="<?= e($f['path']) ?>" placeholder="/page-url"></label>
  <label>Source<input class="input" name="source" value="<?= e($f['source']) ?>" placeholder="Google, Pinterest…"></label>
  <label>Country<input class="input" name="country" value="<?= e($f['country']) ?>" placeholder="IN, US…" maxlength="2"></label>
  <label class="check-row"><input type="checkbox" name="admins" value="1" <?= $f['admins']?'checked':'' ?>> Include my own visits</label>
  <?php if($f['visitor']!==''): ?><input type="hidden" name="visitor" value="<?= e($f['visitor']) ?>"><?php endif ?>
  <div class="filters-actions"><button class="button button-primary"><?= aicon('search') ?> Apply</button><a class="button button-outline" href="/admin.php?view=analytics">Reset</a>
   <?php foreach(['Today'=>[0,0],'7 days'=>[6,0],'30 days'=>[29,0],'90 days'=>[89,0],'Year'=>[364,0]] as $l=>[$a]): ?><a class="button button-outline button-sm" href="<?= e($aurl(['from'=>date('Y-m-d',strtotime("-$a days")),'to'=>date('Y-m-d'),'p'=>null])) ?>"><?= $l ?></a><?php endforeach ?></div>
 </form>
 <?php if($f['visitor']!==''): ?><p class="notice">Showing one visitor <code><?= e(substr($f['visitor'],0,8)) ?></code>. <a href="<?= e($aurl(['visitor'=>null])) ?>">Show everyone</a></p><?php endif ?>
 <div class="glance glance-4 glance-6">
  <?php foreach([['Live now',(int)$live,'clock','teal','visitors in the last 5 min'],['Visitors',(int)$tot['v'],'users','blue','unique people'],['Visits',(int)$tot['s'],'send','violet','sessions'],['Page views',(int)$tot['pv'],'file','amber','pages opened'],['Avg. time on page',$dur((int)$tot['sec']),'clock','blue','reading time'],['Bounce rate',((int)$bounce).'%','chart','teal','left after 1 page']] as [$label,$n,$ic,$tone,$sub]): ?>
   <div class="tile tile-<?= $tone ?>" title="<?= e($sub) ?>"><span class="tile-icon"><?= aicon($ic) ?></span><span><b class="tile-num"><?= is_int($n)?number_format($n):e($n) ?></b><span class="tile-label"><?= $label ?></span></span></div>
  <?php endforeach ?>
 </div>
 <section class="box chart-box"><h2 class="box-title"><?= aicon('chart','icon title-icon') ?> Visitors and page views per day <span class="muted"><span class="key key-v"></span> visitors <span class="key key-pv"></span> page views</span></h2>
  <?php $n=max(1,count($days)); ?>
  <svg class="bar-chart" viewBox="0 0 <?= $n*10 ?> 120" preserveAspectRatio="none" role="img" aria-label="Visitors and page views per day">
   <?php $i=0;foreach($days as $d=>[$pv,$v]): $h=$pv?max(2,round($pv/$max*110)):0;$hv=$v?max(2,round($v/$max*110)):0; ?><g><title><?= e(date('D, M j',strtotime($d))) ?>: <?= $v ?> visitors, <?= $pv ?> page views</title><rect class="pv" x="<?= $i*10+1 ?>" y="<?= 115-$h ?>" width="8" height="<?= $h ?>" rx="1.5"/><rect x="<?= $i*10+2.5 ?>" y="<?= 115-$hv ?>" width="5" height="<?= $hv ?>" rx="1.5"/></g><?php $i++;endforeach ?>
   <line x1="0" y1="115.5" x2="<?= $n*10 ?>" y2="115.5"/>
  </svg>
  <div class="chart-axis"><span><?= e(date('M j',strtotime($f['from']))) ?></span><span>max <?= $max ?> views/day</span><span><?= e(date('M j',strtotime($f['to']))) ?></span></div>
 </section>
 <h2 class="section-h"><?= aicon('file','icon title-icon') ?> Pages</h2>
 <div class="table-wrap"><table class="list-table"><thead><tr><th>Page</th><th class="num">Views</th><th class="num">Visitors</th><th class="num">Avg. time</th><th class="num">Read depth</th></tr></thead><tbody>
  <?php foreach($pages as $r): ?><tr><td><a class="row-title" href="<?= e($aurl(['path'=>$r['path'],'p'=>null])) ?>"><?= e($r['title']!==''?mb_strimwidth($r['title'],0,80,'…'):$r['path']) ?></a><br><a class="muted" href="<?= e($r['path']) ?>" target="_blank" rel="noopener"><?= e($r['path']) ?></a></td><td class="num"><?= number_format((int)$r['n']) ?></td><td class="num"><?= number_format((int)$r['v']) ?></td><td class="num"><?= $dur((int)$r['sec']) ?></td><td class="num"><?= $r['sc']?(int)$r['sc'].'%':'—' ?></td></tr><?php endforeach ?>
  <?php if(!$pages): ?><tr><td colspan="5" class="empty muted">No visits recorded yet. Tracking starts as soon as this update is live; open your website in another browser to see yourself here (tick “Include my own visits” if you are logged in).</td></tr><?php endif ?>
 </tbody></table></div>
 <div class="breakdowns">
  <?php foreach($boxes as [$title,$param,$data]): ?>
  <section class="box"><h2 class="box-title"><?= $title ?></h2><?php if(!$data): ?><p class="muted">No data yet.</p><?php endif ?><ul class="bars"><?php foreach($data as $r): $label=$r['k']??'Unknown'; if($title==='Countries')$label=countryLabel((string)$r['k']); ?><li><?php if($param&&$r['k']!==null): ?><a href="<?= e($aurl([$param=>$r['k'],'p'=>null])) ?>"><?php else: ?><a><?php endif ?><span><?= e($label) ?></span><b title="<?= (int)$r['n'] ?> page views"><?= number_format((int)$r['v']) ?></b></a><i><em data-w="<?= round($r['v']/$vtot*100) ?>"></em></i></li><?php endforeach ?></ul></section>
  <?php endforeach ?>
 </div>
 <h2 class="section-h"><?= aicon('send','icon title-icon') ?> What people clicked</h2>
 <div class="table-wrap"><table class="list-table"><thead><tr><th>Link or button</th><th>Type</th><th class="num">Clicks</th><th class="num">People</th></tr></thead><tbody>
  <?php foreach($clicks as $r): ?><tr><td><b><?= e($r['label']!==''?mb_strimwidth($r['label'],0,70,'…'):'(no text)') ?></b><?php if($r['target']!==''): ?><br><span class="muted ellipsis" title="<?= e($r['target']) ?>"><?= e(mb_strimwidth($r['target'],0,80,'…')) ?></span><?php endif ?></td><td><span class="chip chip-teal"><?= e($kinds[$r['kind']]??$r['kind']) ?></span></td><td class="num"><?= number_format((int)$r['n']) ?></td><td class="num"><?= number_format((int)$r['v']) ?></td></tr><?php endforeach ?>
  <?php if(!$clicks): ?><tr><td colspan="4" class="empty muted">No clicks yet.</td></tr><?php endif ?>
 </tbody></table></div>
 <?php if($journey): ?>
 <h2 class="section-h"><?= aicon('user','icon title-icon') ?> This visitor's journey</h2>
 <div class="table-wrap"><table class="list-table"><thead><tr><th>Time</th><th>What</th></tr></thead><tbody>
  <?php foreach($journey as $j): ?><tr><td class="nowrap"><?= e(date('M j, g:i:s a',strtotime($j['ts']))) ?></td><td><?= $j['kind']==='view'?'Viewed <b>'.e($j['path']).'</b>':'Clicked <b>'.e($j['label']?:$j['path']).'</b> <span class="muted">('.e($kinds[$j['kind']]??$j['kind']).')</span>' ?></td></tr><?php endforeach ?>
 </tbody></table></div>
 <?php endif ?>
 <h2 class="section-h"><?= aicon('users','icon title-icon') ?> Visit log <span class="muted"><?= number_format((int)$tot['pv']) ?> page views</span></h2>
 <div class="table-wrap"><table class="list-table click-table"><thead><tr><th>Time</th><th>Page</th><th>Came from</th><th>Visitor &amp; location</th><th>Device</th><th>Read</th></tr></thead><tbody>
  <?php foreach($log as $h): ?><tr>
   <td class="nowrap"><b><?= e(date('M j, Y',strtotime($h['ts']))) ?></b><br><span class="muted"><?= e(date('g:i:s a',strtotime($h['ts']))) ?></span></td>
   <td><a class="row-title" href="<?= e($aurl(['path'=>$h['path'],'p'=>null])) ?>"><?= e(mb_strimwidth($h['title']?:$h['path'],0,60,'…')) ?></a><br><span class="muted"><?= e($h['path']) ?></span><?= $h['entry']?' <span class="tag">entry</span>':'' ?></td>
   <td><a href="<?= e($aurl(['source'=>$h['source'],'p'=>null])) ?>"><span class="chip chip-teal"><?= e($h['source']) ?></span></a><?php if($h['utm_campaign']!==''): ?><br><span class="muted">Campaign: <?= e($h['utm_campaign']) ?></span><?php endif ?><?php if($h['referrer']!==''): ?><br><span class="muted ellipsis" title="<?= e($h['referrer']) ?>"><?= e(mb_strimwidth($h['referrer'],0,48,'…')) ?></span><?php endif ?></td>
   <td><a href="<?= e($aurl(['visitor'=>$h['visitor'],'p'=>null])) ?>" title="Show this visitor's journey"><code><?= e(substr($h['visitor'],0,8)) ?></code></a><?= $h['is_new']?' <span class="tag">new</span>':'' ?><?= $h['is_admin']?' <span class="tag">you</span>':'' ?><br><span class="muted"><?= e(countryLabel($h['country'])) ?><?= $h['tz']!==''?' · '.e(str_replace('_',' ',$h['tz'])):'' ?><?= $h['ip']!==''?' · '.e($h['ip']):'' ?></span></td>
   <td><?= e($h['device']) ?><br><span class="muted"><?= e($h['browser']) ?> · <?= e($h['os']) ?><?= $h['screen']!==''?' · '.e($h['screen']):'' ?></span></td>
   <td class="nowrap"><?= $dur((int)$h['seconds']) ?><br><span class="muted"><?= (int)$h['scroll'] ?>% scrolled</span></td>
  </tr><?php endforeach ?>
  <?php if(!$log): ?><tr><td colspan="6" class="empty muted">No visits match these filters.</td></tr><?php endif ?>
 </tbody></table></div>
 <?php if($npg>1): ?><nav class="pagination" aria-label="Pages"><?php for($i=max(1,$pg-3);$i<=min($npg,$pg+3);$i++): ?><a class="<?= $i===$pg?'current':'' ?>" href="<?= e($aurl(['p'=>$i])) ?>"><?= $i ?></a><?php endfor ?></nav><?php endif ?>
 <div class="breakdowns">
 <form method="post" class="box stack"><?= csrfField() ?><input type="hidden" name="action" value="geoip_update"><h2 class="box-title"><?= aicon('gear','icon title-icon') ?> Country database</h2>
  <p class="hint"><?= is_file(GEOIP_DB)?'Last updated '.e(date('M j, Y',strtotime(setting('geoip_updated',date('c',filemtime(GEOIP_DB)))))).'.':'<b>Not downloaded yet</b> — countries show as “Unknown” until you click the button below (takes about a minute).' ?> It updates itself every month through the hourly cron job. <a href="https://db-ip.com" target="_blank" rel="noopener">IP Geolocation by DB-IP</a>.</p>
  <div><button class="button button-primary"><?= aicon('send') ?> Download / update now</button></div></form>
 <form method="post" class="box stack"><?= csrfField() ?><input type="hidden" name="action" value="analytics_settings"><h2 class="box-title"><?= aicon('gear','icon title-icon') ?> Settings</h2>
  <p class="hint">Visits are recorded by the site itself (no outside service). Bots are skipped; your own visits while logged in are marked and hidden by default. Data is kept for 13 months, IP addresses for 90 days. Google Analytics keeps running alongside.</p>
  <label class="check-row"><input type="checkbox" name="analytics_off" value="1" <?= setting('analytics_off')==='1'?'checked':'' ?>> Pause tracking</label>
  <div><button class="button button-primary">Save</button></div></form>
 </div>
 </section>

<?php elseif($view==='files'):
 $roots=fileRoots();$root=isset($roots[$_GET['root']??''])?$_GET['root']:'public';$rel=(string)($_GET['path']??'');
 try{[, , $abs,$rel]=filePath($root,$rel);$isFile=is_file($abs);if(!$isFile&&!is_dir($abs))throw new RuntimeException('Not found.');$fileErr='';}catch(RuntimeException $ex){$fileErr=$ex->getMessage();$rel='';[, , $abs]=filePath($root,'');$isFile=false;}
 $dirRel=$isFile?(dirname($rel)==='.'?'':dirname($rel)):$rel;$writable=$roots[$root][2];
 $furl=fn(array $o)=>'/admin.php?'.http_build_query(array_filter(['view'=>'files','root'=>$root,'path'=>$dirRel]+$o,fn($v)=>$v!==null&&$v!==''));
 $size=fn(int $b)=>$b>=1048576?round($b/1048576,1).' MB':($b>=1024?round($b/1024).' KB':$b.' B');
?>
 <section class="panel"><?= pageHead('folder','File manager','See your website files, upload new ones and edit text files.') ?>
 <nav class="tabs" aria-label="Folders"><?php foreach($roots as $k=>[$label]): ?><a class="<?= $k===$root?'active':'' ?>" href="/admin.php?view=files&amp;root=<?= $k ?>"><?= e($label) ?></a><?php endforeach ?></nav>
 <p class="hint"><?= match($root){
  'public'=>'<b>Safe from git pull.</b> Files here are yours: they are not part of the code, so updates never touch them. Each file is online at <code>/files/name</code> and also at <code>/name</code> (good for Google/Bing/Pinterest verification files).',
  'uploads'=>'<b>Safe from git pull.</b> Images from the media library and post editor.',
  default=>'<b>View only.</b> This is the website code from GitHub. Editing it here would be overwritten (or would block) the next <code>git pull</code>, so ask for code changes instead. API keys and databases are hidden.'} ?></p>
 <?php if($fileErr): ?><p class="notice notice-error"><?= e($fileErr) ?></p><?php endif ?>
 <nav class="crumbs" aria-label="Path"><a href="<?= e($furl(['path'=>null])) ?>"><?= e($roots[$root][0]) ?></a><?php $acc='';foreach(array_filter(explode('/',$rel),'strlen') as $part): $acc=ltrim("$acc/$part",'/'); ?> / <a href="<?= e($furl(['path'=>$acc])) ?>"><?= e($part) ?></a><?php endforeach ?></nav>
 <?php if($isFile): $ext=fileExt($rel);$url=fileUrl($root,$rel);$text=in_array($ext,TEXT_TYPES,true)||($root==='code'&&in_array($ext,['php','htaccess','lock','yml','yaml','sh','ini','conf',''],true)); ?>
  <section class="box"><h2 class="box-title"><?= aicon('file','icon title-icon') ?> <?= e(basename($rel)) ?> <span class="muted"><?= $size((int)filesize($abs)) ?> · changed <?= e(date('M j, Y g:i a',(int)filemtime($abs))) ?></span></h2>
   <?php if($url): ?><p><a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e(siteBase().$url) ?></a></p><?php endif ?>
   <?php if($text&&filesize($abs)<=2*1024*1024): ?>
    <form method="post" class="stack"><?= csrfField() ?><input type="hidden" name="action" value="file_save"><input type="hidden" name="root" value="<?= e($root) ?>"><input type="hidden" name="path" value="<?= e($rel) ?>">
     <textarea class="input code-input file-editor" name="content" rows="24" spellcheck="false" <?= $writable?'':'readonly' ?>><?= e((string)file_get_contents($abs)) ?></textarea>
     <?php if($writable): ?><div><button class="button button-primary"><?= aicon('send') ?> Save file</button></div><?php endif ?>
    </form>
   <?php elseif(str_starts_with((string)(FILE_TYPES[$ext]??''),'image/')&&$url): ?><img class="file-preview" src="<?= e($url) ?>" alt="">
   <?php else: ?><p class="muted">This file cannot be shown here<?= $url?', open it with the link above':'' ?>.</p><?php endif ?>
   <?php if($writable): ?><div class="row-actions-inline">
    <form method="post" class="inline-form"><?= csrfField() ?><input type="hidden" name="action" value="file_rename"><input type="hidden" name="root" value="<?= e($root) ?>"><input type="hidden" name="path" value="<?= e($rel) ?>"><input class="input input-sm" name="to" value="<?= e(basename($rel)) ?>" aria-label="New name"><button class="button button-outline button-sm">Rename</button></form>
    <form method="post" class="inline-form" data-confirm="Delete <?= e(basename($rel)) ?>? This cannot be undone."><?= csrfField() ?><input type="hidden" name="action" value="file_delete"><input type="hidden" name="root" value="<?= e($root) ?>"><input type="hidden" name="path" value="<?= e($rel) ?>"><button class="button button-danger button-sm"><?= aicon('trash') ?> Delete</button></form>
   </div><?php endif ?>
  </section>
 <?php else: $items=$fileErr?[]:fileList($root,$rel); ?>
  <?php if($writable): ?><div class="breakdowns">
   <form method="post" enctype="multipart/form-data" class="box stack"><?= csrfField() ?><input type="hidden" name="action" value="file_upload"><input type="hidden" name="root" value="<?= e($root) ?>"><input type="hidden" name="path" value="<?= e($rel) ?>">
    <h2 class="box-title"><?= aicon('plus','icon title-icon') ?> Upload files</h2><input class="input" type="file" name="files[]" multiple required>
    <p class="hint">Allowed: <?= e(implode(', ',array_keys(FILE_TYPES))) ?>. Max <?= e(ini_get('upload_max_filesize')) ?> per file.</p><div><button class="button button-primary">Upload</button></div></form>
   <div class="box stack"><h2 class="box-title"><?= aicon('pen','icon title-icon') ?> Create</h2>
    <form method="post" class="inline-form"><?= csrfField() ?><input type="hidden" name="action" value="file_save"><input type="hidden" name="root" value="<?= e($root) ?>"><input type="hidden" name="path" value="<?= e($rel) ?>"><input type="hidden" name="content" value=""><input class="input input-sm" name="new_name" placeholder="new-file.txt" required><button class="button button-outline button-sm">New text file</button></form>
    <form method="post" class="inline-form"><?= csrfField() ?><input type="hidden" name="action" value="file_mkdir"><input type="hidden" name="root" value="<?= e($root) ?>"><input type="hidden" name="path" value="<?= e($rel) ?>"><input class="input input-sm" name="name" placeholder="folder-name" required><button class="button button-outline button-sm">New folder</button></form>
   </div>
  </div><?php endif ?>
  <div class="table-wrap"><table class="list-table"><thead><tr><th>Name</th><th class="num">Size</th><th>Changed</th></tr></thead><tbody>
   <?php if($rel!==''): ?><tr><td colspan="3"><a href="<?= e($furl(['path'=>dirname($rel)==='.'?null:dirname($rel)])) ?>">← Up one folder</a></td></tr><?php endif ?>
   <?php foreach($items as $it): ?><tr><td><a class="row-title" href="<?= e($furl(['path'=>$it['rel']])) ?>"><?= aicon($it['dir']?'folder':'file','icon icon-sm') ?> <?= e($it['name']) ?><?= $it['dir']?'/':'' ?></a></td><td class="num"><?= $it['dir']?'—':$size((int)$it['size']) ?></td><td class="nowrap muted"><?= e(date('M j, Y g:i a',(int)$it['time'])) ?></td></tr><?php endforeach ?>
   <?php if(!$items): ?><tr><td colspan="3" class="empty muted">This folder is empty.</td></tr><?php endif ?>
  </tbody></table></div>
 <?php endif ?>
 </section>
<?php elseif($view==='users'): ?>
 <section class="panel"><?= pageHead('users','Users','People who can sign in to this workspace.') ?>
 <div class="two-col">
  <div class="stack">
   <form method="post" class="box stack"><?= csrfField() ?><input type="hidden" name="action" value="add_user"><h2 class="box-title"><?= aicon('users','icon title-icon') ?> Add New User</h2>
    <label>Email<input class="input" type="email" name="email" required autocomplete="off"></label>
    <label>Password<input class="input" type="password" name="password" minlength="12" required autocomplete="new-password"></label><p class="hint">At least 12 characters. Every user has full access to the CMS.</p>
    <div><button class="button button-primary"><?= aicon('plus') ?> Add New User</button></div></form>
   <form method="post" class="box stack"><?= csrfField() ?><input type="hidden" name="action" value="password"><h2 class="box-title"><?= aicon('gear','icon title-icon') ?> Change Your Password</h2>
    <label>Current password<input class="input" type="password" name="current_password" autocomplete="current-password" required></label>
    <label>New password<input class="input" type="password" name="new_password" autocomplete="new-password" minlength="12" required></label>
    <div><button class="button button-outline">Update Password</button></div></form>
  </div>
  <div class="table-wrap"><table class="list-table"><thead><tr><th>User</th><th>Role</th><th class="kebab-col"><span class="sr-only">Actions</span></th></tr></thead><tbody>
   <?php foreach(query('SELECT id,email FROM admins ORDER BY id') as $u): ?><tr><td><div class="title-cell"><span class="avatar avatar-letter"><?= e(strtoupper($u['email'][0])) ?></span><div><b class="user-name"><?= e(ucfirst(explode('@',$u['email'])[0])) ?></b><?= (int)$u['id']===(int)$me['id']?' <span class="tag">You</span>':'' ?><br><span class="muted"><?= e($u['email']) ?></span></div></div></td>
    <td><span class="chip chip-teal">Administrator</span></td>
    <td class="kebab-col"><?php if((int)$u['id']!==(int)$me['id']): ?><form method="post" data-confirm="Delete this user?"><?= csrfField() ?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="icon-danger" title="Delete user" aria-label="Delete <?= e($u['email']) ?>"><?= aicon('trash') ?></button></form><?php endif ?></td></tr><?php endforeach ?>
  </tbody></table></div>
 </div></section>

<?php elseif($view==='seo'): $f=fn($k,$d='')=>e($error&&($_POST['action']??'')==='seo_settings'?($_POST[$k]??''):setting($k,$d)); $base=siteBase(); ?>
 <section class="panel"><?= pageHead('code','SEO & Code','Search engine verification, analytics, AI search settings and custom code for every page.') ?>
 <form method="post" class="settings-form"><?= csrfField() ?><input type="hidden" name="action" value="seo_settings">
  <div class="grid-2">
   <section class="box"><h2 class="box-title"><?= aicon('check','icon title-icon') ?> Site verification</h2><div class="stack">
    <label>Google Search Console<input class="input" name="google_verification" value="<?= $f('google_verification') ?>" maxlength="200" placeholder="content value, or paste the whole meta tag"></label>
    <label>Bing Webmaster Tools <span class="muted">(also powers ChatGPT search)</span><input class="input" name="bing_verification" value="<?= $f('bing_verification') ?>" maxlength="200"></label>
    <label>Yandex<input class="input" name="yandex_verification" value="<?= $f('yandex_verification') ?>" maxlength="200"></label>
    <label>Pinterest<input class="input" name="pinterest_verification" value="<?= $f('pinterest_verification') ?>" maxlength="200"></label>
    <label>Google Analytics 4 measurement ID<input class="input" name="ga_id" value="<?= $f('ga_id','G-Z6E5E0V0Q3') ?>" maxlength="30" placeholder="G-XXXXXXXXXX"></label>
    <p class="hint">Analytics is not loaded while you are signed in to the admin, so your own visits are not counted.</p>
   </div></section>
   <section class="box"><h2 class="box-title"><?= aicon('chart','icon title-icon') ?> AI SEO (AEO · GEO · LLMO)</h2><div class="stack">
    <label class="check-row"><input type="checkbox" name="ai_search" value="allow" <?= setting('ai_search','allow')==='allow'?'checked':'' ?>> Allow AI search &amp; answer engines <span class="muted">(ChatGPT search, Perplexity, Claude, DuckAssist)</span></label>
    <label class="check-row"><input type="checkbox" name="ai_training" value="allow" <?= setting('ai_training','allow')==='allow'?'checked':'' ?>> Allow AI model crawlers <span class="muted">(GPTBot, Google-Extended/Gemini, ClaudeBot, Applebot, Meta)</span></label>
    <p class="hint">Keep both on to appear in AI answers. Google AI Overviews use the normal Googlebot.</p>
    <label>About the site, for AI assistants <span class="muted">(top of /llms.txt)</span><textarea class="input" name="llms_intro" rows="3" maxlength="1500" placeholder="Who you are, what you cover, how you test and choose products."><?= $f('llms_intro') ?></textarea></label>
    <p class="hint">AI files: <a href="/llms.txt" target="_blank">/llms.txt</a> · <a href="/llms-full.txt" target="_blank">/llms-full.txt</a> · <a href="/feed.xml" target="_blank">/feed.xml</a> · <a href="/sitemap.xml" target="_blank">/sitemap.xml</a> · <a href="/robots.txt" target="_blank">/robots.txt</a></p>
   </div></section>
   <section class="box"><h2 class="box-title"><?= aicon('users','icon title-icon') ?> Brand entity <span class="muted">(helps Google &amp; AI recognise the site)</span></h2><div class="stack">
    <label>Organisation description<textarea class="input" name="org_about" rows="3" maxlength="600" placeholder="Defaults to the site description"><?= $f('org_about') ?></textarea></label>
    <label>Contact email<input class="input" name="org_email" value="<?= $f('org_email') ?>" maxlength="200" placeholder="hello@besttop10things.com"></label>
    <label>Social profiles <span class="muted">(one https:// link per line)</span><textarea class="input" name="org_same_as" rows="4" maxlength="2000" placeholder="https://www.facebook.com/…&#10;https://www.instagram.com/…&#10;https://www.linkedin.com/company/…"><?= $f('org_same_as') ?></textarea></label>
   </div></section>
   <section class="box"><h2 class="box-title"><?= aicon('send','icon title-icon') ?> Fast indexing</h2><div class="stack">
    <p>Every publish or update pings <b>IndexNow</b> automatically (Bing, ChatGPT search, Yandex, Seznam, Naver).</p>
    <p class="hint">Key file: <a href="/<?= e(indexNowKey()) ?>.txt" target="_blank"><?= e($base) ?>/<?= e(indexNowKey()) ?>.txt</a></p>
    <button class="button button-outline" form="indexnow-all"><?= aicon('send') ?> Submit all URLs now</button>
    <p class="hint">For Google: submit <code><?= e($base) ?>/sitemap.xml</code> in Search Console → Sitemaps, and use URL Inspection → Request indexing for new posts.</p>
   </div></section>
  </div>
  <?php $aiStat=function(string $prov){[$st,$msg,$when]=array_pad(explode('|',setting('ai_status_'.$prov),3),3,'');
     if(!providerReady($prov))return '<b class="chip chip-amber">Not set up</b>';
     if($st==='ok')return '<b class="chip chip-green">✓ Working</b> <span class="muted">'.e($msg).' · tested '.e(date('M j, g:i a',strtotime($when))).'</span>';
     if($st==='error')return '<b class="chip chip-red">✗ Not working</b> <span class="muted">'.e($msg).'</span>';
     return '<b class="chip chip-amber">Key saved · not tested</b>';}; ?>
  <section class="box" id="ai"><h2 class="box-title"><?= aicon('bulb','icon title-icon') ?> AI assistant</h2><div class="stack">
   <div class="ai-summary"><span><b>Gemini:</b> <?= $aiStat('gemini') ?></span><span><b>Claude:</b> <?= $aiStat('claude') ?></span></div>
   <fieldset class="stack ai-fs"><legend>Google Gemini <span class="muted">(free tier)</span></legend>
    <div><?= $aiStat('gemini') ?></div>
    <label>Gemini API key <span class="muted">(aistudio.google.com › Get API key)</span><input class="input" type="password" name="gemini_key" form="ai-form" autocomplete="off" placeholder="<?= geminiKey()!==''?'•••••••• saved (paste a new key to replace)':'AIza… or AQ.…' ?>"></label>
    <label>Model <span class="muted">(optional)</span><input class="input" name="gemini_model" form="ai-form" value="<?= e(setting('gemini_model')) ?>" placeholder="gemini-flash-latest"></label>
    <div class="row-buttons"><?php if(geminiReady()): ?><button class="button button-outline button-sm" form="ai-test-gemini"><?= aicon('check') ?> Test Gemini</button><button class="button button-outline button-sm" form="ai-form" name="remove_gemini" value="1">Remove key</button><?php endif ?></div>
   </fieldset>
   <fieldset class="stack ai-fs"><legend>Claude <span class="muted">(Anthropic API, paid per use)</span></legend>
    <div><?= $aiStat('claude') ?></div>
    <?php if(!is_file(ROOT.'/vendor/autoload.php')): ?><p class="notice notice-error">Claude needs the SDK: on the server run <code>cd .besttop10-private &amp;&amp; composer install --no-dev</code></p><?php endif ?>
    <label>Anthropic API key <span class="muted">(console.anthropic.com › API keys; needs credit)</span><input class="input" type="password" name="anthropic_key" form="ai-form" autocomplete="off" placeholder="<?= aiKey()!==''?'•••••••• saved (paste a new key to replace)':'sk-ant-…' ?>"></label>
    <label>Workspace ID <span class="muted">(only if your key is not tied to a workspace; starts with wrkspc_)</span><input class="input" name="ai_workspace_id" form="ai-form" value="<?= e(setting('ai_workspace_id')) ?>" placeholder="wrkspc_…"></label>
    <div class="row-buttons"><?php if(claudeReady()): ?><button class="button button-outline button-sm" form="ai-test-claude"><?= aicon('check') ?> Test Claude</button><button class="button button-outline button-sm" form="ai-form" name="remove_claude" value="1">Remove key</button><?php endif ?></div>
   </fieldset>
   <fieldset class="stack ai-fs"><legend>Who does what</legend>
    <label>Main AI <span class="muted">(fixes, titles, keyword work)</span><select class="input" name="ai_provider" form="ai-form"><option value="gemini" <?= aiProvider()==='gemini'?'selected':'' ?>>Google Gemini</option><option value="claude" <?= aiProvider()==='claude'?'selected':'' ?>>Claude</option></select></label>
    <label>Article drafts<select class="input" name="ai_draft_provider" form="ai-form"><option value="">Same as main AI</option><option value="claude" <?= setting('ai_draft_provider')==='claude'?'selected':'' ?>>Claude (best writing)</option><option value="gemini" <?= setting('ai_draft_provider')==='gemini'?'selected':'' ?>>Google Gemini</option></select></label>
    <label class="check-row"><input type="checkbox" name="ai_fallback" form="ai-form" value="1" <?= setting('ai_fallback','1')==='1'?'checked':'' ?>> If one AI fails or hits its limit, use the other one automatically</label>
   </fieldset>
   <p class="hint">Keys are stored outside the website folder. Nothing is published without you unless you turn on “Fully automatic” in the Content Advisor.</p>
   <div class="row-buttons"><button class="button button-primary" form="ai-form"><?= aicon('send') ?> Save</button></div>
  </div></section>
  <section class="box" id="pinterest"><h2 class="box-title"><?= aicon('send','icon title-icon') ?> Pinterest auto-posting</h2><div class="stack">
   <?php $boards=json_decode(setting('pinterest_boards'),true)?:[]; if(pinterestToken()!==''): ?><p><b class="chip chip-green">Connected</b> <?= count($boards) ?> board<?= count($boards)===1?'':'s' ?><?php if(setting('pinterest_board')!==''&&isset($boards[setting('pinterest_board')])): ?> · pinning to <b><?= e($boards[setting('pinterest_board')]) ?></b><?php endif ?></p><?php endif ?>
   <label>Access token <span class="muted">(developers.pinterest.com → your app → generate token with boards:read, pins:read, pins:write)</span><input class="input" type="password" name="pinterest_token" form="pinterest-form" autocomplete="off" placeholder="<?= pinterestToken()!==''?'•••••••• saved (paste a new one to replace)':'pina_…' ?>"></label>
   <?php if($boards): ?><label>Board<select class="input" name="pinterest_board" form="pinterest-form"><?php foreach($boards as $id=>$name): ?><option value="<?= e($id) ?>" <?= setting('pinterest_board')===(string)$id?'selected':'' ?>><?= e($name) ?></option><?php endforeach ?></select></label><?php endif ?>
   <label class="check-row"><input type="checkbox" name="pinterest_auto" value="1" form="pinterest-form" <?= setting('pinterest_auto')==='1'?'checked':'' ?>> Pin automatically when a post is published</label>
   <p class="hint">Each post gets a 1000×1500 pin image with its title, linking back to the article. Scheduled posts are pinned by <code>scripts/social-cron.php</code> (run it hourly with cron). You can also pin any post from Posts › ⋯ › Pin to Pinterest.</p>
   <div class="row-buttons"><button class="button button-primary" form="pinterest-form"><?= aicon('send') ?> <?= pinterestToken()!==''?'Save &amp; refresh boards':'Connect Pinterest' ?></button><?php if(pinterestToken()!==''): ?><button class="button button-outline" form="pinterest-form" name="disconnect" value="1">Disconnect</button><?php endif ?></div>
   <?php $log=query("SELECT s.*,r.title FROM social_posts s LEFT JOIN reviews r ON r.id=s.post_id ORDER BY s.id DESC LIMIT 5"); if($log): ?><p class="lbl">Recent</p><ul class="check-list"><?php foreach($log as $l): ?><li class="<?= $l['status']==='ok'?'pass':'' ?>"><?= e(date('M j, g:i a',strtotime($l['created_at']))) ?> · <?= e($l['title']??'#'.$l['post_id']) ?><?= $l['status']==='ok'?'':' — '.e($l['message']) ?></li><?php endforeach ?></ul><?php endif ?>
  </div></section>
  <section class="box"><h2 class="box-title"><?= aicon('chart','icon title-icon') ?> Facebook, Instagram, X, LinkedIn</h2><div class="stack">
   <p>Connect your RSS feed once to an automation tool and every new post is shared automatically, with its 1200×630 image:</p>
   <p><input class="input" value="<?= e(siteBase()) ?>/feed.xml" readonly data-copy></p>
   <p class="hint"><b>Buffer</b> (Settings › RSS feeds), <b>Zapier</b> (RSS by Zapier → Facebook Pages / LinkedIn / X) or <b>IFTTT</b> (RSS feed → new item). Those platforms only allow automatic posting through approved apps like these.</p>
  </div></section>
  <section class="box"><h2 class="box-title"><?= aicon('code','icon title-icon') ?> Custom code</h2><div class="stack">
   <label>Header code <span class="muted">(inside &lt;head&gt; on every page: meta tags, pixels, tag managers)</span><textarea class="input code-input" name="code_head" rows="6" spellcheck="false"><?= $f('code_head') ?></textarea></label>
   <label>Body code <span class="muted">(right after &lt;body&gt;: e.g. Google Tag Manager noscript)</span><textarea class="input code-input" name="code_body" rows="4" spellcheck="false"><?= $f('code_body') ?></textarea></label>
   <label>Footer code <span class="muted">(before &lt;/body&gt;: chat widgets, extra scripts)</span><textarea class="input code-input" name="code_footer" rows="4" spellcheck="false"><?= $f('code_footer') ?></textarea></label>
   <label>Allowed script domains <span class="muted">(space or comma separated)</span><input class="input" name="code_domains" value="<?= $f('code_domains') ?>" maxlength="1000" placeholder="connect.facebook.net www.clarity.ms *.hotjar.com"></label>
   <label>ads.txt <span class="muted">(served at <a href="/ads.txt" target="_blank" rel="noopener">/ads.txt</a> — AdSense and other ad networks)</span><textarea class="input code-input" name="ads_txt" rows="4" spellcheck="false"><?= $f('ads_txt',ADS_TXT_DEFAULT) ?></textarea></label>
   <p class="hint">Everything in this section is saved in the site database, so a <code>git pull</code> never removes it.</p>
   <p class="hint">For security the site only runs scripts from its own files. Inline &lt;script&gt; code you paste here is allowed automatically; scripts or iframes loaded from another website need that website's domain listed above. Code runs on the public site only, not in the admin.</p>
  </div></section>
  <div class="save-bar"><button class="button button-primary button-lg"><?= aicon('send') ?> Save SEO settings</button></div>
 </form>
 <form id="ai-test-gemini" method="post" class="hidden"><?= csrfField() ?><input type="hidden" name="action" value="ai_test"><input type="hidden" name="provider" value="gemini"></form>
 <form id="ai-test-claude" method="post" class="hidden"><?= csrfField() ?><input type="hidden" name="action" value="ai_test"><input type="hidden" name="provider" value="claude"></form>
 <form id="ai-form" method="post" class="hidden"><?= csrfField() ?><input type="hidden" name="action" value="ai_key"></form>
 <form id="pinterest-form" method="post" class="hidden"><?= csrfField() ?><input type="hidden" name="action" value="pinterest"></form>
 <form id="indexnow-all" method="post" class="hidden"><?= csrfField() ?><input type="hidden" name="action" value="indexnow_all"></form>
 </section>
<?php elseif($view==='settings'): ?>
 <section class="panel"><?= pageHead('gear','Settings','Your site title, homepage headline and description.') ?>
 <form method="post" class="box stack narrow"><?= csrfField() ?><input type="hidden" name="action" value="settings"><h2 class="box-title"><?= aicon('home','icon title-icon') ?> General</h2>
  <?php foreach(['site_name'=>'Site Title','tagline'=>'Homepage headline','description'=>'Homepage description (also the default meta description)'] as $key=>$label): ?><label><?= $label ?><textarea class="input" name="<?= $key ?>" rows="2" required maxlength="500"><?= e(setting($key)) ?></textarea></label><?php endforeach ?>
  <div><button class="button button-primary"><?= aicon('send') ?> Save Changes</button></div></form>
 <?php $subs=query('SELECT email,created_at FROM subscribers ORDER BY id DESC'); ?>
 <section class="box narrow"><h2 class="box-title"><?= aicon('users','icon title-icon') ?> Newsletter Subscribers <span class="tag"><?= count($subs) ?></span></h2>
  <?php if($subs): ?><div class="table-wrap"><table class="list-table"><thead><tr><th>Email</th><th>Subscribed</th></tr></thead><tbody><?php foreach($subs as $sub): ?><tr><td><?= e($sub['email']) ?></td><td class="muted"><?= e(date('M j, Y',strtotime($sub['created_at']))) ?></td></tr><?php endforeach ?></tbody></table></div>
  <?php else: ?><p class="muted">No subscribers yet. People can sign up from the newsletter box at the bottom of every page.</p><?php endif ?></section>
 </section>
<?php endif ?>
</main>
<?php endif ?>
</body></html>

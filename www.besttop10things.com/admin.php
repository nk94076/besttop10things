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
 static $p=['home'=>'<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z" fill="currentColor" stroke="none"/>','file'=>'<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>','image'=>'<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>','folder'=>'<path d="M3 6a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>','users'=>'<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/>','gear'=>'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>','chart'=>'<path d="M4 20V10M10 20V4M16 20v-7M21 20H3"/>','pen'=>'<path d="M4 20h16M14.5 4.5l3 3L8 17H5v-3z"/>','clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>','trash'=>'<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6"/>','send'=>'<path d="M21 3 10 14M21 3l-7 18-4-7-7-4z"/>','calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>','right'=>'<path d="m9 6 6 6-6 6"/>','down'=>'<path d="m6 9 6 6 6-6"/>','plus'=>'<path d="M12 5v14M5 12h14"/>','search'=>'<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>','sun'=>'<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>','user'=>'<circle cx="12" cy="8" r="4" fill="currentColor" stroke="none"/><path d="M4 21a8 8 0 0 1 16 0z" fill="currentColor" stroke="none"/>','logout'=>'<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h11"/>','menu'=>'<path d="M3 6h18M3 12h18M3 18h18"/>','crown'=>'<path d="M3 8l4.5 4L12 5l4.5 7L21 8l-2 11H5z" fill="currentColor" stroke="none"/>'];
 return '<svg class="'.e($class).'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($p[$name]??$p['file']).'</svg>';
}
function pageHead(string $icon, string $title, string $sub, string $actions=''): string {
 return '<div class="page-head panel-head"><div class="head-with-icon"><span class="head-icon">'.aicon($icon).'</span><div><h1 class="page-title">'.e($title).'</h1><p class="page-sub">'.e($sub).'</p></div></div>'.($actions?'<div class="head-actions">'.$actions.'</div>':'').'</div>';
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
 if(query('SELECT id FROM reviews WHERE slug=? AND id!=?',[$slug,$id]))throw new RuntimeException('This permalink is already used. Choose another.');
 $date=(string)($in['published_at']??'');
 $publishedAt=preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/',$date)?$date.':00':now();
 $status=in_array($in['status']??'',['published','draft'],true)?$in['status']:'draft';
 $meta=[trim((string)($in['meta_title']??'')),trim((string)($in['meta_description']??''))];
 if(strlen($meta[0])>200||strlen($meta[1])>500)throw new RuntimeException('SEO title or description is too long.');
 $values=[$category,$title,$slug,trim((string)($in['excerpt']??''))?:autoExcerpt($body),$body,$image,$score,trim((string)($in['pros']??'')),trim((string)($in['cons']??'')),trim((string)($in['verdict']??'')),trim((string)($in['author']??''))?:'Editorial team',$status,empty($in['featured'])?0:1,empty($in['demo'])?0:1,$meta[0],$meta[1],$publishedAt,date('c')];
 $cols='category_id=?,title=?,slug=?,excerpt=?,body=?,image=?,score=?,pros=?,cons=?,verdict=?,author=?,status=?,featured=?,demo=?,meta_title=?,meta_description=?,published_at=?,updated_at=?';
 if($id){
  if(!query('SELECT id FROM reviews WHERE id=?',[$id]))throw new RuntimeException('Post not found.');
  run("UPDATE reviews SET $cols WHERE id=?",[...$values,$id]);return $id;
 }
 run('INSERT INTO reviews(category_id,title,slug,excerpt,body,image,score,pros,cons,verdict,author,status,featured,demo,meta_title,meta_description,published_at,updated_at,created_at) VALUES ('.implode(',',array_fill(0,19,'?')).')',[...$values,date('c')]);
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
   flash(['published'=>'Post published.','scheduled'=>'Post scheduled.','draft'=>'Draft saved.'][$state],'/admin.php?view=edit&id='.$id);
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
   $n=count($ids);$labels=['publish'=>'published','draft'=>'moved to drafts','trash'=>'moved to the Trash','restore'=>'restored from the Trash','delete'=>'permanently deleted'];
   flash("$n post".($n>1?'s':'')." {$labels[$do]}.",backTo('/admin.php?view=posts'));
  }
  if($action==='duplicate'){
   $r=query('SELECT * FROM reviews WHERE id=?',[(int)($_POST['id']??0)])[0]??null;if(!$r)throw new RuntimeException('Post not found.');
   $base=$r['slug'].'-copy';$slug=$base;for($i=2;query('SELECT id FROM reviews WHERE slug=?',[$slug]);$i++)$slug="$base-$i";
   $id=savePost(['title'=>$r['title'].' (copy)','slug'=>$slug,'status'=>'draft','featured'=>0,'published_at'=>'']+$r,0);
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
if($logged&&!$me){unset($_SESSION['admin']);$logged=false;}
$titles=['dashboard'=>'Dashboard','posts'=>'Posts','edit'=>'Edit Post','media'=>'Media Library','categories'=>'Categories','users'=>'Users','settings'=>'Settings'];
if(!isset($titles[$view]))$view='dashboard';
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($logged?$titles[$view]:'Log in') ?> ‹ <?= e(setting('site_name')) ?></title><link rel="icon" href="/assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/admin.css"><script src="/assets/admin.js" defer></script></head>
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
 $nav=['dashboard'=>['Dashboard','home'],'posts'=>['Posts','file'],'media'=>['Media','image'],'categories'=>['Categories','folder'],'users'=>['Users','users'],'settings'=>['Settings','gear']];
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
 <div class="table-wrap"><table class="list-table"><thead><tr><th class="check"><input type="checkbox" data-check-all aria-label="Select all"></th><th class="th-title"><?= $sortLink('title','Title') ?></th><th><?= $sortLink('category','Category') ?></th><th><?= $sortLink('score','Score') ?></th><th><?= $sortLink('date','Date') ?></th><th class="kebab-col"><span class="sr-only">Actions</span></th></tr></thead><tbody>
 <?php foreach($rows as $r): $state=postState($r); $view_url=reviewUrl($r).($state==='published'?'':'&preview=1'); ?>
  <tr><td class="check"><input type="checkbox" name="ids[]" value="<?= $r['id'] ?>" aria-label="Select <?= e($r['title']) ?>"></td>
   <td class="title-col"><div class="title-cell"><img class="thumb" src="<?= e($r['image']) ?>" alt="" loading="lazy"><div><a class="row-title" href="/admin.php?view=edit&id=<?= $r['id'] ?>"><?= e($r['title']) ?></a><?php if($state!=='published'): ?> <span class="pill pill-<?= $state ?>"><?= ucfirst($state) ?></span><?php endif ?><?php if($r['featured']): ?> <span class="tag">Featured</span><?php endif ?><?php if($r['demo']): ?> <span class="tag">Sample</span><?php endif ?></div></div></td>
   <td><a class="chip chip-<?= $chip((int)$r['category_id']) ?>" href="<?= e($q(['cat'=>$r['category_id'],'p'=>null])) ?>"><?= e($r['category']) ?></a></td>
   <td class="score-col"><?= $r['score']>0?number_format((float)$r['score'],1):'—' ?></td>
   <td class="date-col"><span class="date-state"><?= ['published'=>'Published','scheduled'=>'Goes live','draft'=>'Last modified','trash'=>'Trashed'][$state] ?></span><br><?= e(date('Y/m/d \a\t g:i a',strtotime($state==='draft'||$state==='trash'?$r['updated_at']:$r['published_at']))) ?></td>
   <td class="kebab-col"><details class="kebab"><summary aria-label="Actions for <?= e($r['title']) ?>"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="5" r="1.8" fill="currentColor"/><circle cx="12" cy="12" r="1.8" fill="currentColor"/><circle cx="12" cy="19" r="1.8" fill="currentColor"/></svg></summary><div class="dropdown dropdown-right">
    <?php if($state==='trash'): ?>
     <button form="row-<?= $r['id'] ?>-restore"><?= aicon('clock') ?> Restore</button><button class="danger" form="row-<?= $r['id'] ?>-delete"><?= aicon('trash') ?> Delete Permanently</button>
    <?php else: ?>
     <a href="/admin.php?view=edit&id=<?= $r['id'] ?>"><?= aicon('pen') ?> Edit</a><a href="<?= e($view_url) ?>" target="_blank"><?= aicon('right') ?> <?= $state==='published'?'View':'Preview' ?></a><button form="row-<?= $r['id'] ?>-dup"><?= aicon('file') ?> Duplicate</button><button class="danger" form="row-<?= $r['id'] ?>-trash"><?= aicon('trash') ?> Move to Trash</button>
    <?php endif ?></div></details></td></tr>
 <?php endforeach ?>
 <?php if(!$rows): ?><tr><td colspan="6" class="muted empty">No posts found.</td></tr><?php endif ?>
 </tbody></table></div></form>
 <?php foreach($rows as $r): foreach(['restore'=>'post_status','delete'=>'post_status','trash'=>'post_status','dup'=>'duplicate'] as $k=>$act): ?><form id="row-<?= $r['id'] ?>-<?= $k ?>" method="post" class="hidden" <?= $k==='delete'?'data-confirm="Delete this post permanently? This cannot be undone."':'' ?>><?= csrfField() ?><input type="hidden" name="action" value="<?= $act ?>"><input type="hidden" name="do" value="<?= $k ?>"><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="return" value="<?= e($self) ?>"></form><?php endforeach;endforeach ?>
 <div class="table-foot"><span class="muted"><?= $total?'Showing '.(($page-1)*PER_PAGE+1).' to '.min($total,$page*PER_PAGE).' of '.$total.' items':'' ?></span>
 <?php if($pages>1): $from=max(1,min($page-2,$pages-4));$to=min($pages,$from+4); ?><nav class="pagination" aria-label="Pages">
  <a class="<?= $page===1?'disabled':'' ?>" href="<?= e($q(['p'=>null])) ?>" aria-label="First page">«</a><a class="<?= $page===1?'disabled':'' ?>" href="<?= e($q(['p'=>max(1,$page-1)])) ?>" aria-label="Previous page">‹</a>
  <?php for($i=$from;$i<=$to;$i++): ?><a class="<?= $i===$page?'current':'' ?>" href="<?= e($q(['p'=>$i])) ?>" <?= $i===$page?'aria-current="page"':'' ?>><?= $i ?></a><?php endfor ?>
  <a class="<?= $page===$pages?'disabled':'' ?>" href="<?= e($q(['p'=>min($pages,$page+1)])) ?>" aria-label="Next page">›</a><a class="<?= $page===$pages?'disabled':'' ?>" href="<?= e($q(['p'=>$pages])) ?>" aria-label="Last page">»</a>
 </nav><?php endif ?></div>
 </section>

<?php elseif($view==='edit'):
 $id=(int)($_GET['id']??0);
 $r=$id?(query('SELECT * FROM reviews WHERE id=?',[$id])[0]??null):['id'=>0,'title'=>'','slug'=>'','category_id'=>'','excerpt'=>'','body'=>'','image'=>'','score'=>'0','pros'=>'','cons'=>'','verdict'=>'','author'=>'Editorial team','status'=>'draft','featured'=>0,'demo'=>0,'meta_title'=>'','meta_description'=>'','published_at'=>''];
 if($r&&$error&&($_POST['action']??'')==='save_post')$r=array_merge($r,array_intersect_key($_POST,$r),['featured'=>isset($_POST['featured']),'demo'=>isset($_POST['demo'])]);
 if(!$r): ?><p class="notice notice-error">Post not found.</p><?php else: $state=$id?postState($r):'new'; ?>
 <section class="panel"><?= pageHead('pen',$id?'Edit Post':'Add New Post',$id?'Update the content, settings and SEO of this post.':'Write something new. Save it as a draft or publish when ready.',$id?'<a class="button button-outline" href="/admin.php?view=posts">'.aicon('file').' All Posts</a><a class="button button-primary" href="/admin.php?view=edit">'.aicon('plus').' Add New</a>':'<a class="button button-outline" href="/admin.php?view=posts">'.aicon('file').' All Posts</a>') ?>
 <form method="post" enctype="multipart/form-data" class="editor" data-editor data-now="<?= e(substr(now(),0,16)) ?>"><?= csrfField() ?><input type="hidden" name="action" value="save_post"><input type="hidden" name="id" value="<?= e($r['id']) ?>">
  <div class="editor-main">
   <input class="input title-input" name="title" value="<?= e($r['title']) ?>" placeholder="Add title" required maxlength="200" aria-label="Title" data-title>
   <p class="permalink">Permalink: <span class="muted"><?= e($_SERVER['HTTP_HOST']??'') ?>/?page=review&amp;slug=</span><input class="input input-sm" name="slug" value="<?= e($r['slug']) ?>" placeholder="auto-generated-from-title" aria-label="URL slug" data-slug>
    <?php if($id): ?><a class="button button-sm" href="<?= e(reviewUrl($r)) ?><?= $state==='published'?'':'&preview=1' ?>" target="_blank"><?= $state==='published'?'View Post':'Preview' ?></a><?php endif ?></p>
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
    <label>Pros (one per line)<textarea class="input" name="pros" rows="3"><?= e($r['pros']) ?></textarea></label>
    <label>Cons (one per line)<textarea class="input" name="cons" rows="3"><?= e($r['cons']) ?></textarea></label>
    <label>Final verdict<textarea class="input" name="verdict" rows="3"><?= e($r['verdict']) ?></textarea></label></div></details>
   <section class="box"><h2 class="box-title"><?= aicon('search','icon title-icon') ?> SEO</h2><div class="stack">
    <label>SEO title <span class="muted" data-count-for="meta_title"></span><input class="input" name="meta_title" value="<?= e($r['meta_title']) ?>" maxlength="200" placeholder="<?= e($r['title']?:'Defaults to the post title') ?>" data-count="60"></label>
    <label>Meta description <span class="muted" data-count-for="meta_description"></span><textarea class="input" name="meta_description" rows="2" maxlength="500" placeholder="Defaults to the excerpt" data-count="160"><?= e($r['meta_description']) ?></textarea></label>
    <div class="serp"><p class="serp-title" data-serp-title><?= e($r['meta_title']?:$r['title']?:'Post title') ?></p><p class="serp-url"><?= e($_SERVER['HTTP_HOST']??'') ?> › <?= e($r['slug']?:'post') ?></p><p class="serp-desc" data-serp-desc><?= e($r['meta_description']?:$r['excerpt']) ?></p></div></div></section>
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
   <section class="box"><h2 class="box-title"><?= aicon('user','icon title-icon') ?> Author</h2><input class="input input-sm" name="author" value="<?= e($r['author']) ?>" aria-label="Author"></section>
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
    <td><div class="title-cell"><span class="cat-icon chip-<?= ['teal','violet','green','rose','amber','blue'][(int)$c['id']%6] ?>"><?= aicon('folder') ?></span><form method="post" class="inline-form" id="cat-<?= $c['id'] ?>"><?= csrfField() ?><input type="hidden" name="action" value="save_category"><input type="hidden" name="id" value="<?= $c['id'] ?>"><input class="input input-sm" name="name" value="<?= e($c['name']) ?>" required aria-label="Category name"></form></div></td>
    <td><select class="input input-sm" name="icon" form="cat-<?= $c['id'] ?>" aria-label="Icon"><?php foreach(['grid','tech','shopping','travel','gadgets','home'] as $i): ?><option <?= $c['icon']===$i?'selected':'' ?>><?= $i ?></option><?php endforeach ?></select></td>
    <td class="muted"><code><?= e($c['slug']) ?></code></td><td><a class="chip chip-<?= ['teal','violet','green','rose','amber','blue'][(int)$c['id']%6] ?>" href="/admin.php?view=posts&cat=<?= $c['id'] ?>"><?= (int)$c['total'] ?> post<?= (int)$c['total']===1?'':'s' ?></a></td>
    <td class="nowrap"><div class="row-buttons"><button class="button button-outline button-sm" form="cat-<?= $c['id'] ?>">Save</button><form method="post" class="inline-form" data-confirm="Delete this category?"><?= csrfField() ?><input type="hidden" name="action" value="delete_category"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="icon-danger" title="Delete category" aria-label="Delete <?= e($c['name']) ?>"><?= aicon('trash') ?></button></form></div></td></tr>
   <?php endforeach ?></tbody></table></div>
 </div></section>

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

<?php
require __DIR__.'/../.besttop10-private/app/bootstrap.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
const UPLOAD_DIR = __DIR__.'/uploads';
const PER_PAGE = 20;
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
<main class="login-box">
 <a class="login-logo" href="/"><img src="/assets/favicon.svg" alt=""><span><?= e(setting('site_name')) ?></span></a>
 <?php if($error): ?><p role="alert" class="notice notice-error"><?= e($error) ?></p><?php endif ?>
 <?php if(!query('SELECT id FROM admins LIMIT 1')): ?>
  <div class="box"><p>The CMS account has not been configured. Run <code>php scripts/create-admin.php</code> on the server to create your private account.</p></div>
 <?php else: ?>
  <form method="post" class="box stack"><?= csrfField() ?><input type="hidden" name="action" value="login">
   <label>Email<input class="input" name="email" type="email" autocomplete="username" required></label>
   <label>Password<input class="input" name="password" type="password" autocomplete="current-password" required></label>
   <button class="button button-primary button-block">Log In</button>
  </form>
 <?php endif ?>
 <p class="login-back"><a href="/">← Go to <?= e(setting('site_name')) ?></a></p>
</main>
<?php else:
 $now=now();
 $count=fn(string $where)=>(int)db()->query("SELECT COUNT(*) FROM reviews r WHERE $where")->fetchColumn();
 $counts=['all'=>$count("r.status!='trash'"),'published'=>$count(live()),'scheduled'=>$count("r.status='published' AND r.published_at>'$now'"),'draft'=>$count("r.status='draft'"),'trash'=>$count("r.status='trash'")];
 $nav=['dashboard'=>['Dashboard','home'],'posts'=>['Posts','book'],'media'=>['Media','grid'],'categories'=>['Categories','shopping'],'users'=>['Users','check'],'settings'=>['Settings','gadgets']];
?>
<header class="cms-bar">
 <button type="button" class="cms-menu" data-side-toggle aria-label="Toggle menu"><?= icon('menu') ?></button>
 <a class="cms-site" href="/" target="_blank"><?= icon('home','icon') ?> <?= e(setting('site_name')) ?></a>
 <a class="cms-new" href="/admin.php?view=edit">+ New</a>
 <span class="cms-spacer"></span>
 <span class="cms-user">Howdy, <?= e($me['email']) ?></span>
 <form method="post"><?= csrfField() ?><input type="hidden" name="action" value="logout"><button class="cms-logout">Log Out</button></form>
</header>
<aside class="cms-side" id="cms-side"><nav aria-label="CMS navigation">
 <?php foreach($nav as $key=>[$label,$ic]): $active=$view===$key||($key==='posts'&&$view==='edit'); ?>
  <a class="<?= $active?'active':'' ?>" href="/admin.php?view=<?= $key ?>"><?= icon($ic,'icon') ?><span><?= $label ?></span><?php if($key==='posts'&&$counts['draft']): ?><b class="badge"><?= $counts['draft'] ?></b><?php endif ?></a>
  <?php if($key==='posts'&&$active): ?><div class="sub"><a class="<?= $view==='posts'?'active':'' ?>" href="/admin.php?view=posts">All Posts</a><a class="<?= $view==='edit'&&empty($_GET['id'])?'active':'' ?>" href="/admin.php?view=edit">Add New</a></div><?php endif ?>
 <?php endforeach ?>
</nav></aside>
<main class="cms-main" id="main">
<?php if($error): ?><p role="alert" class="notice notice-error"><?= e($error) ?></p><?php endif ?>
<?php if(isset($_SESSION['flash'])): ?><p role="status" class="notice notice-success"><?= e($_SESSION['flash']) ?></p><?php unset($_SESSION['flash']);endif ?>

<?php if($view==='dashboard'): ?>
 <h1 class="page-title">Dashboard</h1>
 <div class="dash">
  <section class="box"><h2 class="box-title">At a Glance</h2><ul class="glance">
   <li><a href="/admin.php?view=posts&status=published"><b><?= $counts['published'] ?></b> Published</a></li>
   <li><a href="/admin.php?view=posts&status=draft"><b><?= $counts['draft'] ?></b> Drafts</a></li>
   <li><a href="/admin.php?view=posts&status=scheduled"><b><?= $counts['scheduled'] ?></b> Scheduled</a></li>
   <li><a href="/admin.php?view=categories"><b><?= (int)db()->query('SELECT COUNT(*) FROM categories')->fetchColumn() ?></b> Categories</a></li>
   <li><a href="/admin.php?view=media"><b><?= count(mediaFiles()) ?></b> Media files</a></li>
   <li><a href="/admin.php?view=posts&status=trash"><b><?= $counts['trash'] ?></b> In Trash</a></li>
  </ul></section>
  <section class="box"><h2 class="box-title">Quick Draft</h2><form method="post" class="stack"><?= csrfField() ?><input type="hidden" name="action" value="quick_draft">
   <label>Title<input class="input" name="title" required maxlength="200"></label>
   <label>Content<textarea class="input" name="body" rows="4" placeholder="What's on your mind?" required></textarea></label>
   <div><button class="button">Save Draft</button></div></form></section>
  <section class="box"><h2 class="box-title">Recently Published</h2><ul class="activity">
   <?php foreach(query('SELECT r.id,r.title,r.published_at FROM reviews r WHERE '.live().' ORDER BY r.published_at DESC,r.id DESC LIMIT 6') as $r): ?>
    <li><span class="muted"><?= e(date('M j, g:i a',strtotime($r['published_at']))) ?></span><a href="/admin.php?view=edit&id=<?= $r['id'] ?>"><?= e($r['title']) ?></a></li>
   <?php endforeach ?></ul></section>
  <section class="box"><h2 class="box-title">Your Drafts</h2><ul class="activity">
   <?php foreach($drafts=query("SELECT id,title,updated_at FROM reviews WHERE status='draft' ORDER BY updated_at DESC LIMIT 6") as $r): ?>
    <li><span class="muted"><?= e(date('M j',strtotime($r['updated_at']))) ?></span><a href="/admin.php?view=edit&id=<?= $r['id'] ?>"><?= e($r['title']) ?></a></li>
   <?php endforeach ?><?php if(!$drafts): ?><li class="muted">No drafts.</li><?php endif ?></ul></section>
 </div>

<?php elseif($view==='posts'):
 $status=(string)($_GET['status']??'all');if(!isset($counts[$status]))$status='all';
 $s=trim((string)($_GET['s']??''));$cat=(int)($_GET['cat']??0);$page=max(1,(int)($_GET['p']??1));
 $orderby=['date'=>'r.published_at','title'=>'r.title','score'=>'r.score','modified'=>'r.updated_at'][(string)($_GET['orderby']??'')]??'r.published_at';
 $dir=($_GET['order']??'')==='asc'?'ASC':'DESC';
 $where=['all'=>"r.status!='trash'",'published'=>live(),'scheduled'=>"r.status='published' AND r.published_at>'$now'",'draft'=>"r.status='draft'",'trash'=>"r.status='trash'"][$status];$params=[];
 if($s!==''){$where.=' AND (r.title LIKE ? OR r.body LIKE ?)';$params[]="%$s%";$params[]="%$s%";}
 if($cat){$where.=' AND r.category_id=?';$params[]=$cat;}
 $total=(int)(query("SELECT COUNT(*) AS n FROM reviews r WHERE $where",$params)[0]['n']);$pages=max(1,(int)ceil($total/PER_PAGE));$page=min($page,$pages);
 $rows=query("SELECT r.*,c.name AS category FROM reviews r JOIN categories c ON c.id=r.category_id WHERE $where ORDER BY $orderby $dir,r.id DESC LIMIT ".PER_PAGE.' OFFSET '.(($page-1)*PER_PAGE),$params);
 $q=fn(array $over)=>'/admin.php?'.http_build_query(array_filter(['view'=>'posts','status'=>$status==='all'?null:$status,'s'=>$s?:null,'cat'=>$cat?:null,'orderby'=>$_GET['orderby']??null,'order'=>$_GET['order']??null]+$over+[],fn($v)=>$v!==null&&$v!==''));
 $self=$q(['p'=>$page>1?$page:null]);
 $sortLink=function(string $key,string $label)use($q,$dir){$cur=($_GET['orderby']??'date')===$key;$next=$cur&&$dir==='DESC'?'asc':'desc';return '<a href="'.e($q(['orderby'=>$key,'order'=>$next,'p'=>null])).'">'.$label.($cur?($dir==='ASC'?' ▲':' ▼'):'').'</a>';};
?>
 <div class="page-head"><h1 class="page-title">Posts</h1><a class="button" href="/admin.php?view=edit">Add New Post</a></div>
 <ul class="subsubsub"><?php foreach(['all'=>'All','published'=>'Published','scheduled'=>'Scheduled','draft'=>'Drafts','trash'=>'Trash'] as $k=>$label): if($k!=='all'&&!$counts[$k])continue; ?><li><a class="<?= $status===$k?'current':'' ?>" href="<?= e($q(['status'=>$k==='all'?null:$k,'p'=>null])) ?>"><?= $label ?> <span class="muted">(<?= $counts[$k] ?>)</span></a></li><?php endforeach ?></ul>
 <form class="tablenav" method="get"><input type="hidden" name="view" value="posts"><?php if($status!=='all'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif ?>
  <select class="input input-sm" name="cat" aria-label="Filter by category"><option value="">All Categories</option><?php foreach(categories() as $c): ?><option value="<?= $c['id'] ?>" <?= $cat===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach ?></select>
  <button class="button button-sm">Filter</button><span class="cms-spacer"></span>
  <input class="input input-sm" type="search" name="s" value="<?= e($s) ?>" aria-label="Search posts" placeholder="Search posts"><button class="button button-sm">Search Posts</button>
 </form>
 <form method="post" id="bulk-form"><?= csrfField() ?><input type="hidden" name="action" value="bulk"><input type="hidden" name="return" value="<?= e($self) ?>">
 <div class="tablenav">
  <select class="input input-sm" name="bulk_action" aria-label="Bulk actions"><option value="">Bulk actions</option>
   <?php if($status==='trash'): ?><option value="restore">Restore</option><option value="delete">Delete permanently</option><?php else: ?><option value="publish">Publish</option><option value="draft">Move to Draft</option><option value="trash">Move to Trash</option><?php endif ?>
  </select><button class="button button-sm" data-bulk-apply>Apply</button><span class="cms-spacer"></span><span class="muted"><?= $total ?> item<?= $total===1?'':'s' ?></span>
 </div>
 <div class="table-wrap"><table class="list-table"><thead><tr><th class="check"><input type="checkbox" data-check-all aria-label="Select all"></th><th><?= $sortLink('title','Title') ?></th><th>Category</th><th><?= $sortLink('score','Score') ?></th><th><?= $sortLink('date','Date') ?></th></tr></thead><tbody>
 <?php foreach($rows as $r): $state=postState($r); ?>
  <tr><td class="check"><input type="checkbox" name="ids[]" value="<?= $r['id'] ?>" aria-label="Select <?= e($r['title']) ?>"></td>
   <td class="title-col"><a class="row-title" href="/admin.php?view=edit&id=<?= $r['id'] ?>"><?= e($r['title']) ?></a><?php if($state!=='published'&&$status==='all'): ?> <span class="state">— <?= ucfirst($state) ?></span><?php endif ?><?php if($r['featured']): ?> <span class="tag">Featured</span><?php endif ?><?php if($r['demo']): ?> <span class="tag">Sample</span><?php endif ?>
    <div class="row-actions">
     <?php if($state==='trash'): ?>
      <button form="row-<?= $r['id'] ?>-restore">Restore</button> | <button class="danger" form="row-<?= $r['id'] ?>-delete">Delete Permanently</button>
     <?php else: ?>
      <a href="/admin.php?view=edit&id=<?= $r['id'] ?>">Edit</a> | <button form="row-<?= $r['id'] ?>-dup">Duplicate</button> | <button class="danger" form="row-<?= $r['id'] ?>-trash">Trash</button> | <a href="<?= e(reviewUrl($r)) ?><?= $state==='published'?'':'&preview=1' ?>" target="_blank"><?= $state==='published'?'View':'Preview' ?></a>
     <?php endif ?>
    </div></td>
   <td><a href="<?= e($q(['cat'=>$r['category_id'],'p'=>null])) ?>"><?= e($r['category']) ?></a></td>
   <td><?= $r['score']>0?number_format((float)$r['score'],1):'—' ?></td>
   <td class="date-col"><?= ['published'=>'Published','scheduled'=>'Scheduled','draft'=>'Last Modified','trash'=>'Trashed'][$state] ?><br><span class="muted"><?= e(date('Y/m/d \a\t g:i a',strtotime($state==='draft'||$state==='trash'?$r['updated_at']:$r['published_at']))) ?></span></td></tr>
 <?php endforeach ?>
 <?php if(!$rows): ?><tr><td colspan="5" class="muted empty">No posts found.</td></tr><?php endif ?>
 </tbody></table></div></form>
 <?php foreach($rows as $r): foreach(['restore'=>'post_status','delete'=>'post_status','trash'=>'post_status','dup'=>'duplicate'] as $k=>$act): ?><form id="row-<?= $r['id'] ?>-<?= $k ?>" method="post" class="hidden" <?= $k==='delete'?'data-confirm="Delete this post permanently? This cannot be undone."':'' ?>><?= csrfField() ?><input type="hidden" name="action" value="<?= $act ?>"><input type="hidden" name="do" value="<?= $k ?>"><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="return" value="<?= e($self) ?>"></form><?php endforeach;endforeach ?>
 <?php if($pages>1): ?><nav class="pagination" aria-label="Pages"><?php for($i=1;$i<=$pages;$i++): ?><a class="<?= $i===$page?'current':'' ?>" href="<?= e($q(['p'=>$i])) ?>"><?= $i ?></a><?php endfor ?></nav><?php endif ?>

<?php elseif($view==='edit'):
 $id=(int)($_GET['id']??0);
 $r=$id?(query('SELECT * FROM reviews WHERE id=?',[$id])[0]??null):['id'=>0,'title'=>'','slug'=>'','category_id'=>'','excerpt'=>'','body'=>'','image'=>'','score'=>'0','pros'=>'','cons'=>'','verdict'=>'','author'=>'Editorial team','status'=>'draft','featured'=>0,'demo'=>0,'meta_title'=>'','meta_description'=>'','published_at'=>''];
 if($r&&$error&&($_POST['action']??'')==='save_post')$r=array_merge($r,array_intersect_key($_POST,$r),['featured'=>isset($_POST['featured']),'demo'=>isset($_POST['demo'])]);
 if(!$r): ?><p class="notice notice-error">Post not found.</p><?php else: $state=$id?postState($r):'new'; ?>
 <div class="page-head"><h1 class="page-title"><?= $id?'Edit Post':'Add New Post' ?></h1><?php if($id): ?><a class="button" href="/admin.php?view=edit">Add New</a><?php endif ?></div>
 <form method="post" enctype="multipart/form-data" class="editor" data-editor><?= csrfField() ?><input type="hidden" name="action" value="save_post"><input type="hidden" name="id" value="<?= e($r['id']) ?>">
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
   <section class="box"><h2 class="box-title">Excerpt</h2><textarea class="input" name="excerpt" rows="3" maxlength="600"><?= e($r['excerpt']) ?></textarea><p class="hint">Short summary shown on cards and in search results. Leave empty to generate it from the content.</p></section>
   <details class="box" <?= $r['score']>0||$r['pros']!==''?'open':'' ?>><summary class="box-title">Review Details <span class="muted">(optional — for product reviews)</span></summary><div class="stack">
    <label>Score out of 10 <input class="input input-sm" name="score" type="number" min="0" max="10" step="0.1" value="<?= e($r['score']) ?>"></label><p class="hint">Leave at 0 for a regular article: no rating badge or verdict box is shown.</p>
    <label>Pros (one per line)<textarea class="input" name="pros" rows="3"><?= e($r['pros']) ?></textarea></label>
    <label>Cons (one per line)<textarea class="input" name="cons" rows="3"><?= e($r['cons']) ?></textarea></label>
    <label>Final verdict<textarea class="input" name="verdict" rows="3"><?= e($r['verdict']) ?></textarea></label></div></details>
   <section class="box"><h2 class="box-title">SEO</h2><div class="stack">
    <label>SEO title <span class="muted" data-count-for="meta_title"></span><input class="input" name="meta_title" value="<?= e($r['meta_title']) ?>" maxlength="200" placeholder="<?= e($r['title']?:'Defaults to the post title') ?>" data-count="60"></label>
    <label>Meta description <span class="muted" data-count-for="meta_description"></span><textarea class="input" name="meta_description" rows="2" maxlength="500" placeholder="Defaults to the excerpt" data-count="160"><?= e($r['meta_description']) ?></textarea></label>
    <div class="serp"><p class="serp-title" data-serp-title><?= e($r['meta_title']?:$r['title']?:'Post title') ?></p><p class="serp-url"><?= e($_SERVER['HTTP_HOST']??'') ?> › <?= e($r['slug']?:'post') ?></p><p class="serp-desc" data-serp-desc><?= e($r['meta_description']?:$r['excerpt']) ?></p></div></div></section>
  </div>
  <aside class="editor-side">
   <section class="box"><h2 class="box-title">Publish</h2>
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
   <section class="box"><h2 class="box-title">Category</h2><div class="cat-list"><?php foreach(categories() as $c): ?><label class="check-row"><input type="radio" name="category_id" value="<?= $c['id'] ?>" <?= (int)$r['category_id']===(int)$c['id']?'checked':'' ?> required> <?= e($c['name']) ?></label><?php endforeach ?></div><a class="hint" href="/admin.php?view=categories">+ Add New Category</a></section>
   <section class="box"><h2 class="box-title">Featured Image</h2>
    <?php if($r['image']): ?><img class="feat-preview" src="<?= e($r['image']) ?>" alt="Current featured image" data-feat-preview><?php else: ?><img class="feat-preview hidden" alt="" data-feat-preview><?php endif ?>
    <label>Upload new<input class="input input-sm" type="file" name="image_upload" accept="image/jpeg,image/png,image/webp"></label>
    <details class="library"><summary>Choose from Media Library</summary><div class="library-grid"><?php foreach(libraryImages() as $img): ?><label><input type="radio" name="image_pick" value="<?= e($img) ?>" <?= $img===$r['image']?'checked':'' ?>><img src="<?= e($img) ?>" alt="" loading="lazy"></label><?php endforeach ?></div></details>
    <label>Or image URL<input class="input input-sm" name="image" value="<?= e($r['image']) ?>" placeholder="https://… or /uploads/…" data-image-url></label>
   </section>
   <section class="box"><h2 class="box-title">Author</h2><input class="input input-sm" name="author" value="<?= e($r['author']) ?>" aria-label="Author"></section>
  </aside>
 </form>
 <?php if($id): ?><form id="trash-post" method="post" class="hidden"><?= csrfField() ?><input type="hidden" name="action" value="post_status"><input type="hidden" name="do" value="trash"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="return" value="/admin.php?view=posts"></form><?php endif ?>
 <?php endif ?>

<?php elseif($view==='media'): $files=mediaFiles(); ?>
 <div class="page-head"><h1 class="page-title">Media Library</h1></div>
 <form method="post" enctype="multipart/form-data" class="box upload-box"><?= csrfField() ?><input type="hidden" name="action" value="upload_media">
  <p><b>Upload images</b> <span class="muted">JPG, PNG or WebP, up to 5 MB each.</span></p><input class="input" type="file" name="files[]" accept="image/jpeg,image/png,image/webp" multiple required><button class="button button-primary">Upload</button></form>
 <div class="media-grid">
  <?php foreach($files as $f): $used=imageInUse($f['url']); ?>
   <figure class="media-item"><a href="<?= e($f['url']) ?>" target="_blank"><img src="<?= e($f['url']) ?>" alt="" loading="lazy"></a>
    <figcaption><input class="input input-sm" value="<?= e($f['url']) ?>" readonly aria-label="Image URL" data-copy><span class="muted"><?= round($f['size']/1024) ?> KB · <?= date('M j, Y',$f['time']) ?><?= $used?' · In use':'' ?></span>
     <?php if(!$used): ?><form method="post" data-confirm="Delete this image permanently?"><?= csrfField() ?><input type="hidden" name="action" value="delete_media"><input type="hidden" name="name" value="<?= e($f['name']) ?>"><button class="link-danger">Delete</button></form><?php endif ?></figcaption></figure>
  <?php endforeach ?>
  <?php if(!$files): ?><p class="muted">No uploads yet.</p><?php endif ?>
 </div>
 <p class="hint">Click an image URL to copy it, then paste it into a post as <code>![description](/uploads/…)</code>.</p>

<?php elseif($view==='categories'): ?>
 <h1 class="page-title">Categories</h1>
 <div class="two-col">
  <form method="post" class="box stack"><?= csrfField() ?><input type="hidden" name="action" value="save_category"><h2 class="box-title">Add New Category</h2>
   <label>Name<input class="input" name="name" required maxlength="60" placeholder="e.g. Automotive"></label>
   <label>Icon<select class="input" name="icon"><?php foreach(['grid','tech','shopping','travel','gadgets','home'] as $i): ?><option><?= $i ?></option><?php endforeach ?></select></label>
   <div><button class="button button-primary">Add New Category</button></div></form>
  <div class="table-wrap"><table class="list-table"><thead><tr><th>Name</th><th>Icon</th><th>Slug</th><th>Count</th><th></th></tr></thead><tbody>
   <?php foreach(categories() as $c): ?><tr>
    <td><form method="post" class="inline-form" id="cat-<?= $c['id'] ?>"><?= csrfField() ?><input type="hidden" name="action" value="save_category"><input type="hidden" name="id" value="<?= $c['id'] ?>"><input class="input input-sm" name="name" value="<?= e($c['name']) ?>" required aria-label="Category name"></form></td>
    <td><select class="input input-sm" name="icon" form="cat-<?= $c['id'] ?>" aria-label="Icon"><?php foreach(['grid','tech','shopping','travel','gadgets','home'] as $i): ?><option <?= $c['icon']===$i?'selected':'' ?>><?= $i ?></option><?php endforeach ?></select></td>
    <td class="muted"><?= e($c['slug']) ?></td><td><a href="/admin.php?view=posts&cat=<?= $c['id'] ?>"><?= (int)$c['total'] ?></a></td>
    <td class="nowrap"><button class="button button-sm" form="cat-<?= $c['id'] ?>">Save</button> <form method="post" class="inline-form" data-confirm="Delete this category?"><?= csrfField() ?><input type="hidden" name="action" value="delete_category"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="link-danger">Delete</button></form></td></tr>
   <?php endforeach ?></tbody></table></div>
 </div>

<?php elseif($view==='users'): ?>
 <h1 class="page-title">Users</h1>
 <div class="two-col">
  <div class="stack">
   <form method="post" class="box stack"><?= csrfField() ?><input type="hidden" name="action" value="add_user"><h2 class="box-title">Add New User</h2>
    <label>Email<input class="input" type="email" name="email" required autocomplete="off"></label>
    <label>Password<input class="input" type="password" name="password" minlength="12" required autocomplete="new-password"></label><p class="hint">At least 12 characters. Every user has full access to the CMS.</p>
    <div><button class="button button-primary">Add New User</button></div></form>
   <form method="post" class="box stack"><?= csrfField() ?><input type="hidden" name="action" value="password"><h2 class="box-title">Change Your Password</h2>
    <label>Current password<input class="input" type="password" name="current_password" autocomplete="current-password" required></label>
    <label>New password<input class="input" type="password" name="new_password" autocomplete="new-password" minlength="12" required></label>
    <div><button class="button">Update Password</button></div></form>
  </div>
  <div class="table-wrap"><table class="list-table"><thead><tr><th>Email</th><th></th></tr></thead><tbody>
   <?php foreach(query('SELECT id,email FROM admins ORDER BY id') as $u): ?><tr><td><?= e($u['email']) ?><?= (int)$u['id']===(int)$me['id']?' <span class="tag">You</span>':'' ?></td>
    <td><?php if((int)$u['id']!==(int)$me['id']): ?><form method="post" data-confirm="Delete this user?"><?= csrfField() ?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="link-danger">Delete</button></form><?php endif ?></td></tr><?php endforeach ?>
  </tbody></table></div>
 </div>

<?php elseif($view==='settings'): ?>
 <h1 class="page-title">Settings</h1>
 <form method="post" class="box stack narrow"><?= csrfField() ?><input type="hidden" name="action" value="settings">
  <?php foreach(['site_name'=>'Site Title','tagline'=>'Homepage headline','description'=>'Homepage description (also the default meta description)'] as $key=>$label): ?><label><?= $label ?><textarea class="input" name="<?= $key ?>" rows="2" required maxlength="500"><?= e(setting($key)) ?></textarea></label><?php endforeach ?>
  <div><button class="button button-primary">Save Changes</button></div></form>
<?php endif ?>
</main>
<?php endif ?>
</body></html>

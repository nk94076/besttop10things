<?php
declare(strict_types=1);
const ROOT = __DIR__ . '/..';
date_default_timezone_set('Asia/Kolkata');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; img-src 'self' https:; style-src 'self'; script-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");
$dbPath = getenv('APP_DB') ?: ROOT . '/storage/site.sqlite';
$db = new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
$db->exec('CREATE TABLE IF NOT EXISTS categories (id INTEGER PRIMARY KEY, name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, icon TEXT NOT NULL DEFAULT "grid");
CREATE TABLE IF NOT EXISTS reviews (id INTEGER PRIMARY KEY, category_id INTEGER NOT NULL REFERENCES categories(id), title TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, excerpt TEXT NOT NULL, body TEXT NOT NULL, image TEXT NOT NULL, score REAL NOT NULL CHECK(score>=0 AND score<=10), pros TEXT NOT NULL, cons TEXT NOT NULL, verdict TEXT NOT NULL, author TEXT NOT NULL, status TEXT NOT NULL DEFAULT "draft", featured INTEGER NOT NULL DEFAULT 0, demo INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS admins (id INTEGER PRIMARY KEY, email TEXT NOT NULL UNIQUE, password TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS login_attempts (ip TEXT PRIMARY KEY, attempts INTEGER NOT NULL, last_at INTEGER NOT NULL);
CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS subscribers (id INTEGER PRIMARY KEY, email TEXT NOT NULL UNIQUE, created_at TEXT NOT NULL);');
$cols=array_column($db->query('PRAGMA table_info(reviews)')->fetchAll(),'name');
foreach(['meta_title'=>"TEXT NOT NULL DEFAULT ''",'meta_description'=>"TEXT NOT NULL DEFAULT ''",'published_at'=>'TEXT','brand'=>"TEXT NOT NULL DEFAULT ''",'brand_about'=>"TEXT NOT NULL DEFAULT ''",'cta_url'=>"TEXT NOT NULL DEFAULT ''"] as $col=>$def) if(!in_array($col,$cols,true)) $db->exec("ALTER TABLE reviews ADD COLUMN $col $def");
if(!in_array('published_at',$cols,true)) $db->exec('UPDATE reviews SET published_at=substr(created_at,1,19)');
if (!$db->query("SELECT COUNT(*) FROM settings WHERE key='initialized'")->fetchColumn()) require __DIR__ . '/seed.php';
function db(): PDO { global $db; return $db; }
function e(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function query(string $sql, array $params=[]): array { $q=db()->prepare($sql); $q->execute($params); return $q->fetchAll(); }
function run(string $sql, array $params=[]): void { $q=db()->prepare($sql); $q->execute($params); }
function setting(string $key, string $default=''): string { return query('SELECT value FROM settings WHERE key=?',[$key])[0]['value'] ?? $default; }
function slug(string $s): string { return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-') ?: 'review'; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrfField(): string { return '<input type="hidden" name="csrf" value="'.e(csrf()).'">'; }
function checkCsrf(): void { if (!hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Invalid request. Reload the page and try again.'); } }
function redirect(string $url): never { header('Location: '.$url, true, 303); exit; }
function now(): string { return date('Y-m-d\TH:i:s'); }
// SQL condition for posts visible on the public site: published and not scheduled for later.
function live(): string { return "r.status='published' AND (r.published_at IS NULL OR r.published_at<='".now()."')"; }
function categories(): array { return query('SELECT c.*, COUNT(r.id) AS total FROM categories c LEFT JOIN reviews r ON r.category_id=c.id AND '.live().' GROUP BY c.id ORDER BY c.id'); }
function reviewUrl(array $r): string { return '/?page=review&slug='.rawurlencode($r['slug']); }
function safeImage(string $url): bool { return (bool)preg_match('~^https://[^\s]+$~i', $url) || (bool)preg_match('~^/assets/[a-zA-Z0-9_./-]+\.(jpg|jpeg|png|webp|svg)$~', $url) || (bool)preg_match('~^/uploads/[a-f0-9]{32}\.(jpg|png|webp)$~', $url); }
function icon(string $name, string $class='h-5 w-5'): string {
 $paths=['search'=>'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/>','arrow'=>'<path d="M4 12h16m-6-6 6 6-6 6"/>','tech'=>'<rect x="4" y="3" width="16" height="13" rx="1"/><path d="M2 20h20M8 16v4m8-4v4"/>','shopping'=>'<path d="M4 7h16l1 14H3L4 7Zm4 0V5a4 4 0 0 1 8 0v2"/>','travel'=>'<path d="m3 10 7 2 5 9 2-1-2-8 6-6c2-3-1-5-3-3l-6 6-8-2-1 3Z"/>','gadgets'=>'<rect x="6" y="5" width="12" height="14" rx="3"/><path d="M9 5V1h6v4M9 19v4h6v-4m-6-6 2-3 2 1 2-3"/>','home'=>'<path d="m2 11 10-9 10 9M5 9v12h14V9M9 21v-8h6v8"/>','grid'=>'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>','check'=>'<path d="m5 12 4 4L19 6"/>','menu'=>'<path d="M3 6h18M3 12h18M3 18h18"/>','book'=>'<path d="M12 5c-4-3-8-2-10-1v16c3-2 7-2 10 0 3-2 7-2 10 0V4c-3-1-7-2-10 1Zm0 0v15"/>'];
 return '<svg class="'.e($class).'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['grid']).'</svg>';
}
// Front-end icon set (stroke icons, 24px grid)
function ficon(string $name, string $class='ic'): string {
 static $p=['laptop'=>'<rect x="4" y="4" width="16" height="11" rx="1.5"/><path d="M2 19h20"/>','cart'=>'<circle cx="9" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/><path d="M3 4h2l2.4 11h10.2L20 7H6.2"/>','plane'=>'<path d="M10.5 13.5 3 11l1.5-1.5 7 1 4-4.5c1-1 2.6-1.2 3.3-.5s.5 2.3-.5 3.3l-4.5 4 1 7L13.5 21 11 13.5"/>','watch'=>'<rect x="6" y="6" width="12" height="12" rx="3"/><path d="M9 6V2h6v4M9 18v4h6v-4M12 9v3l2 1"/>','home'=>'<path d="m3 11 9-8 9 8M5 9.5V20h14V9.5M10 20v-6h4v6"/>','bag'=>'<path d="M5 8h14l-1 13H6zM9 8V6a3 3 0 0 1 6 0v2"/>','leaf'=>'<path d="M5 19c0-8 5-14 15-14 0 10-6 15-14 15M5 19c3-4 6-7 10-9"/>','dumbbell'=>'<path d="M6 8v8M3 10v4M18 8v8M21 10v4M6 12h12"/>','grid'=>'<rect x="4" y="4" width="6" height="6" rx="1.2"/><rect x="14" y="4" width="6" height="6" rx="1.2"/><rect x="4" y="14" width="6" height="6" rx="1.2"/><rect x="14" y="14" width="6" height="6" rx="1.2"/>','fork'=>'<path d="M7 3v8M4 3v5a3 3 0 0 0 6 0V3M7 11v10M17 21V3c-2 1.5-3 4-3 8h3"/>','cap'=>'<path d="m2 9 10-5 10 5-10 5zM6 11v5c3 2.5 9 2.5 12 0v-5"/>','tag'=>'<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.3"/>','search'=>'<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>','arrow'=>'<path d="M5 12h14M13 6l6 6-6 6"/>','left'=>'<path d="m15 6-6 6 6 6"/>','right'=>'<path d="m9 6 6 6-6 6"/>','down'=>'<path d="m6 9 6 6 6-6"/>','sun'=>'<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>','bolt'=>'<path d="M13 2 4 14h7l-1 8 9-12h-7z" fill="currentColor" stroke="none"/>','star'=>'<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>','doc'=>'<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>','scale'=>'<path d="M12 3v18M7 21h10M5 7h14M5 7l-3 6a3 3 0 0 0 6 0zM19 7l-3 6a3 3 0 0 0 6 0z"/>','bulb'=>'<path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-4 10.5c.8.8 1 1.5 1 2.5h6c0-1 .2-1.7 1-2.5A6 6 0 0 0 12 3z"/>','mail'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>','dots'=>'<circle cx="5" cy="12" r="1.6" fill="currentColor"/><circle cx="12" cy="12" r="1.6" fill="currentColor"/><circle cx="19" cy="12" r="1.6" fill="currentColor"/>','menu'=>'<path d="M3 6h18M3 12h18M3 18h18"/>','crown'=>'<path d="M3 8l4.5 4L12 5l4.5 7L21 8l-2 11H5z" fill="currentColor" stroke="none"/>','check'=>'<path d="m5 12 4 4L19 6"/>','minus'=>'<path d="M6 12h12"/>','clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>','trophy'=>'<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0zM7 6H4v2a3 3 0 0 0 3 3M17 6h3v2a3 3 0 0 1-3 3"/>','link'=>'<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>','facebook'=>'<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v2H7v4h3v7h4v-7h3l1-4h-4V8z" fill="currentColor" stroke="none"/>','x'=>'<path d="M4 4l16 16M20 4 4 20" stroke-width="2.2"/>','pinterest'=>'<path d="M12 3a9 9 0 0 0-3.3 17.4c0-.8 0-1.8.2-2.6l1.3-5.4s-.3-.6-.3-1.6c0-1.5.9-2.6 2-2.6.9 0 1.4.7 1.4 1.5 0 .9-.6 2.3-.9 3.6-.3 1.1.5 2 1.6 2 1.9 0 3.2-2.4 3.2-5.3 0-2.2-1.5-3.8-4.1-3.8a4.7 4.7 0 0 0-4.9 4.7c0 .9.3 1.5.7 2 .2.2.2.3.1.5l-.2.8c0 .3-.2.3-.5.2-1.3-.5-1.9-2-1.9-3.6 0-2.7 2.3-5.9 6.8-5.9 3.6 0 6 2.6 6 5.4 0 3.7-2.1 6.5-5.1 6.5-1 0-2-.6-2.3-1.2l-.6 2.5c-.2.8-.7 1.7-1.1 2.3A9 9 0 1 0 12 3z" fill="currentColor" stroke="none"/>','linkedin'=>'<path d="M5 9h3v11H5zM6.5 4a1.8 1.8 0 1 1 0 3.6 1.8 1.8 0 0 1 0-3.6zM10 9h3v1.6c.5-.9 1.6-1.8 3.3-1.8 3.2 0 3.7 2.1 3.7 4.8V20h-3v-5.6c0-1.3 0-3-1.9-3s-2.1 1.4-2.1 2.9V20h-3z" fill="currentColor" stroke="none"/>','external'=>'<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>','listnum'=>'<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 9h8M8 12h8M8 15h5"/>','related'=>'<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M9 15l2-3 2 2 2-3"/>'];
 return '<svg class="'.e($class).'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($p[$name]??$p['tag']).'</svg>';
}
// Icon and colour tone for a category, by slug with a fallback on its stored icon.
function catStyle(array $c): array {
 $map=['tech'=>['laptop','teal'],'shopping'=>['cart','rose'],'travel'=>['plane','blue'],'gadgets'=>['watch','green'],'home-improvement'=>['home','amber'],'ecommerce'=>['bag','violet'],'beauty-care'=>['leaf','green'],'health-fitness'=>['dumbbell','violet'],'software-apps'=>['grid','blue'],'food-kitchen'=>['fork','rose'],'education'=>['cap','blue']];
 $fallback=['tech'=>'laptop','shopping'=>'cart','travel'=>'plane','gadgets'=>'watch','home'=>'home','grid'=>'grid'];
 return $map[$c['slug']??'']??[$fallback[$c['icon']??'']??'tag',['teal','rose','blue','green','amber','violet'][((int)($c['id']??0))%6]];
}
function readMinutes(string $body): int { return max(1,(int)round(str_word_count(strip_tags($body))/220)); }
function reviewCard(array $r): void { $tone=catStyle(['slug'=>$r['category_slug']??'','id'=>$r['category_id']??0])[1]; ?>
 <article class="card">
  <a class="card-media" href="<?= e(reviewUrl($r)) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e($r['image']) ?>" alt="" loading="lazy"><span class="badge badge-<?= $tone ?>"><?= e($r['category']) ?></span><?php if($r['score']>0): ?><span class="card-score"><?= ficon('star','ic ic-sm') ?> <?= number_format((float)$r['score'],1) ?></span><?php endif ?></a>
  <div class="card-body">
   <h3 class="card-title"><a href="<?= e(reviewUrl($r)) ?>"><?= e($r['title']) ?></a></h3>
   <p class="card-text"><?= e($r['excerpt']) ?></p>
   <p class="meta"><span class="avatar-sm"><?= e(strtoupper(substr($r['author'],0,1))) ?></span><?= e($r['author']) ?><i>•</i><?= e(date('M j, Y',strtotime($r['published_at']??$r['updated_at']))) ?><i>•</i><?= readMinutes($r['body']) ?> min read</p>
   <a class="link-arrow" href="<?= e(reviewUrl($r)) ?>"><?= $r['score']>0?'Read review':'Read article' ?> <?= ficon('arrow','ic ic-sm') ?></a>
  </div>
 </article>
<?php }

function inlineMd(string $s): string {
 $s=e($s);
 $s=preg_replace_callback('~\[([^\]]+)\]\((https://[^\s)]+)\)~',fn($m)=>'<a href="'.$m[2].'" target="_blank" rel="sponsored nofollow noopener">'.$m[1].'</a>',$s);
 return preg_replace('~\*\*(.+?)\*\*~','<strong>$1</strong>',$s);
}
function renderBody(string $body): string {
 $html='';
 foreach(preg_split('/\R\s*\R/',trim($body)) as $block){
  $lines=preg_split('/\R/',trim($block));
  if(preg_match('/^(#{2,4})\s+(.+)$/',$lines[0],$m)){$tag=strlen($m[1])===2?'h2':'h3';$plain=trim(str_replace('**','',preg_replace('~\[([^\]]+)\]\([^)]*\)~','$1',$m[2])));$html.='<'.$tag.($tag==='h2'?' id="'.e(slug($plain)).'"':'').'>'.inlineMd($m[2]).'</'.$tag.'>';array_shift($lines);if(!$lines)continue;}
  if(count($lines)===1&&preg_match('/^!\[([^\]]*)\]\(([^)\s]+)\)$/',$lines[0],$m)&&safeImage($m[2])){$html.='<figure><img src="'.e($m[2]).'" alt="'.e($m[1]).'" loading="lazy"></figure>';continue;}
  if(!array_filter($lines,fn($l)=>!preg_match('/^>\s?/',$l))){$html.='<blockquote>'.implode('<br>',array_map(fn($l)=>inlineMd(preg_replace('/^>\s?/','',$l)),$lines)).'</blockquote>';continue;}
  if(!array_filter($lines,fn($l)=>!preg_match('/^\s*[-*]\s+/',$l))){$html.='<ul>';foreach($lines as $l)$html.='<li>'.inlineMd(preg_replace('/^\s*[-*]\s+/','',$l)).'</li>';$html.='</ul>';continue;}
  $html.='<p>'.implode('<br>',array_map('inlineMd',$lines)).'</p>';
 }
 return $html;
}
// Header menu as configured in CMS → Appearance. Each item: label, url, type ("link" or "categories").
function siteMenu(): array {
 $menu=json_decode(setting('menu'),true);
 if(is_array($menu)&&$menu)return $menu;
 return [['label'=>'Reviews','url'=>'/?page=reviews','type'=>'categories'],['label'=>'Top 10 Lists','url'=>'/?page=top10','type'=>'link'],['label'=>'Categories','url'=>'/?page=categories','type'=>'link'],['label'=>'How We Review','url'=>'/?page=about#how','type'=>'link'],['label'=>'About','url'=>'/?page=about','type'=>'link']];
}
function safeMenuUrl(string $url): bool { return (bool)preg_match('~^(/(?!/)[^\s]*|https://[^\s]+)$~',$url); }

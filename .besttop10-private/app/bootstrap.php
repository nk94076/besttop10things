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
header("Content-Security-Policy: default-src 'self'; img-src 'self' https:; style-src 'self'; script-src 'self' https://www.googletagmanager.com; connect-src 'self' https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");
$dbPath = getenv('APP_DB') ?: ROOT . '/storage/site.sqlite';
$db = new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
$db->exec('CREATE TABLE IF NOT EXISTS categories (id INTEGER PRIMARY KEY, name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, icon TEXT NOT NULL DEFAULT "grid");
CREATE TABLE IF NOT EXISTS reviews (id INTEGER PRIMARY KEY, category_id INTEGER NOT NULL REFERENCES categories(id), title TEXT NOT NULL, slug TEXT NOT NULL UNIQUE, excerpt TEXT NOT NULL, body TEXT NOT NULL, image TEXT NOT NULL, score REAL NOT NULL CHECK(score>=0 AND score<=10), pros TEXT NOT NULL, cons TEXT NOT NULL, verdict TEXT NOT NULL, author TEXT NOT NULL, status TEXT NOT NULL DEFAULT "draft", featured INTEGER NOT NULL DEFAULT 0, demo INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS admins (id INTEGER PRIMARY KEY, email TEXT NOT NULL UNIQUE, password TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS login_attempts (ip TEXT PRIMARY KEY, attempts INTEGER NOT NULL, last_at INTEGER NOT NULL);
CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS subscribers (id INTEGER PRIMARY KEY, email TEXT NOT NULL UNIQUE, created_at TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS links (id INTEGER PRIMARY KEY, url TEXT NOT NULL UNIQUE, created_at TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS clicks (id INTEGER PRIMARY KEY, link_id INTEGER NOT NULL, post_id INTEGER, placement TEXT NOT NULL DEFAULT "", anchor TEXT NOT NULL DEFAULT "", page TEXT NOT NULL DEFAULT "", source TEXT NOT NULL DEFAULT "", referrer TEXT NOT NULL DEFAULT "", utm_source TEXT NOT NULL DEFAULT "", utm_medium TEXT NOT NULL DEFAULT "", utm_campaign TEXT NOT NULL DEFAULT "", landing TEXT NOT NULL DEFAULT "", visitor TEXT NOT NULL DEFAULT "", ip TEXT NOT NULL DEFAULT "", country TEXT NOT NULL DEFAULT "", device TEXT NOT NULL DEFAULT "", browser TEXT NOT NULL DEFAULT "", os TEXT NOT NULL DEFAULT "", user_agent TEXT NOT NULL DEFAULT "", is_bot INTEGER NOT NULL DEFAULT 0, is_admin INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL);
CREATE INDEX IF NOT EXISTS clicks_created ON clicks(created_at);
CREATE INDEX IF NOT EXISTS clicks_post ON clicks(post_id);
CREATE INDEX IF NOT EXISTS clicks_link ON clicks(link_id);');
$db->exec('CREATE TABLE IF NOT EXISTS redirects (from_path TEXT PRIMARY KEY, to_path TEXT NOT NULL, hits INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL)');
$db->exec('CREATE TABLE IF NOT EXISTS keyword_ideas (id INTEGER PRIMARY KEY, keyword TEXT NOT NULL UNIQUE, category_id INTEGER, source TEXT NOT NULL DEFAULT "", impressions INTEGER NOT NULL DEFAULT 0, position REAL NOT NULL DEFAULT 0, score INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT "new", post_id INTEGER, created_at TEXT NOT NULL)');
$db->exec('CREATE TABLE IF NOT EXISTS post_revisions (id INTEGER PRIMARY KEY, post_id INTEGER NOT NULL, title TEXT NOT NULL, meta_title TEXT NOT NULL DEFAULT "", meta_description TEXT NOT NULL DEFAULT "", tldr TEXT NOT NULL DEFAULT "", takeaways TEXT NOT NULL DEFAULT "", body TEXT NOT NULL, note TEXT NOT NULL DEFAULT "", created_at TEXT NOT NULL)');
$db->exec('CREATE TABLE IF NOT EXISTS ai_suggestions (post_id INTEGER PRIMARY KEY, data TEXT NOT NULL, created_at TEXT NOT NULL)');
$db->exec('CREATE TABLE IF NOT EXISTS social_posts (id INTEGER PRIMARY KEY, post_id INTEGER NOT NULL, network TEXT NOT NULL, status TEXT NOT NULL, remote_id TEXT NOT NULL DEFAULT "", message TEXT NOT NULL DEFAULT "", created_at TEXT NOT NULL)');
$db->exec('CREATE TABLE IF NOT EXISTS authors (id INTEGER PRIMARY KEY, name TEXT NOT NULL UNIQUE, slug TEXT NOT NULL UNIQUE, role TEXT NOT NULL DEFAULT "", bio TEXT NOT NULL DEFAULT "", avatar TEXT NOT NULL DEFAULT "", expertise TEXT NOT NULL DEFAULT "", links TEXT NOT NULL DEFAULT "", created_at TEXT NOT NULL)');
if(!in_array('intro',array_column($db->query('PRAGMA table_info(categories)')->fetchAll(),'name'),true))$db->exec("ALTER TABLE categories ADD COLUMN intro TEXT NOT NULL DEFAULT ''");
$cols=array_column($db->query('PRAGMA table_info(reviews)')->fetchAll(),'name');
foreach(['meta_title'=>"TEXT NOT NULL DEFAULT ''",'meta_description'=>"TEXT NOT NULL DEFAULT ''",'published_at'=>'TEXT','brand'=>"TEXT NOT NULL DEFAULT ''",'brand_about'=>"TEXT NOT NULL DEFAULT ''",'cta_url'=>"TEXT NOT NULL DEFAULT ''",'focus_keyword'=>"TEXT NOT NULL DEFAULT ''",'seo_canonical'=>"TEXT NOT NULL DEFAULT ''",'seo_robots'=>"TEXT NOT NULL DEFAULT ''",'og_image'=>"TEXT NOT NULL DEFAULT ''",'schema_type'=>"TEXT NOT NULL DEFAULT ''",'tldr'=>"TEXT NOT NULL DEFAULT ''",'takeaways'=>"TEXT NOT NULL DEFAULT ''",'custom_schema'=>"TEXT NOT NULL DEFAULT ''"] as $col=>$def) if(!in_array($col,$cols,true)) $db->exec("ALTER TABLE reviews ADD COLUMN $col $def");
if(!in_array('published_at',$cols,true)) $db->exec('UPDATE reviews SET published_at=substr(created_at,1,19)');
if (!$db->query("SELECT COUNT(*) FROM settings WHERE key='initialized'")->fetchColumn()) require __DIR__ . '/seed.php';
// One-time: Google Search Console ownership code (fills an empty field once; editable later in SEO & Code).
if(!$db->query("SELECT COUNT(*) FROM settings WHERE key='gsc_seeded'")->fetchColumn()){
 $db->exec("INSERT INTO settings(key,value) VALUES ('google_verification','fDeKGDOwF1hrnkonBq0riuFh5KjaZh2AnYTabK8vZRM') ON CONFLICT(key) DO UPDATE SET value=excluded.value WHERE settings.value=''");
 $db->exec("INSERT OR IGNORE INTO settings(key,value) VALUES ('gsc_seeded','1')");
}
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
function reviewUrl(array $r): string { return '/'.rawurlencode($r['slug']); }
// Clean public URLs. Post slugs may not use these names.
const RESERVED_SLUGS=['author','how-we-review','reviews','top-10','categories','category','compare','about','privacy','admin','admin-php','index-php','sitemap-xml','robots-txt','assets','uploads','search','feed'];
function pagePath(string $page): string { return ['methodology'=>'/how-we-review','home'=>'/','reviews'=>'/reviews','top10'=>'/top-10','categories'=>'/categories','compare'=>'/compare','about'=>'/about','privacy'=>'/privacy'][$page]??'/'; }
// Converts an old "/?page=…" link to its clean form; any other URL is returned unchanged.
function cleanUrl(string $url): string {
 if(!str_starts_with($url,'/?'))return $url;
 parse_str((string)parse_url($url,PHP_URL_QUERY),$q); $frag=(string)parse_url($url,PHP_URL_FRAGMENT);
 $page=(string)($q['page']??''); unset($q['page']);
 if($page==='')return $url;
 if($page==='review'&&!empty($q['slug'])){$path='/'.rawurlencode((string)$q['slug']);unset($q['slug']);}
 elseif($page==='reviews'&&!empty($q['category'])){$path='/category/'.rawurlencode((string)$q['category']);unset($q['category']);}
 else $path=pagePath($page);
 return $path.($q?'?'.http_build_query($q):'').($frag!==''?'#'.$frag:'');
}
function safeImage(string $url): bool { return (bool)preg_match('~^https://[^\s]+$~i', $url) || (bool)preg_match('~^/assets/[a-zA-Z0-9_./-]+\.(jpg|jpeg|png|webp|svg)$~', $url) || (bool)preg_match('~^/uploads/[a-f0-9]{32}\.(jpg|png|webp)$~', $url) || (bool)preg_match('~^/uploads/og/[a-z0-9-]+\.jpg$~', $url); }
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
// Homepage shows a section for every category with more than this many published posts.
const CAT_SECTION_MIN=4;
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
 $s=preg_replace_callback('~\[([^\]]+)\]\((https://[^\s)]+)\)~',fn($m)=>'<a href="'.e(trackUrl(html_entity_decode($m[2],ENT_QUOTES),'body',strip_tags(html_entity_decode($m[1],ENT_QUOTES)))).'" target="_blank" rel="sponsored nofollow noopener">'.$m[1].'</a>',$s);
 // Links to other pages on this site: normal followed links in the same tab (no click tracking).
 $s=preg_replace_callback('~\[([^\]]+)\]\((/[A-Za-z0-9._/#?=&;%-]*)\)~',fn($m)=>'<a href="'.$m[2].'">'.$m[1].'</a>',$s);
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
 if(is_array($menu)&&$menu)return array_map(fn($i)=>['url'=>cleanUrl((string)$i['url'])]+$i,$menu);
 return [['label'=>'Reviews','url'=>'/reviews','type'=>'categories'],['label'=>'Top 10 Lists','url'=>'/top-10','type'=>'link'],['label'=>'Categories','url'=>'/categories','type'=>'link'],['label'=>'How We Review','url'=>'/about#how','type'=>'link'],['label'=>'About','url'=>'/about','type'=>'link']];
}
function safeMenuUrl(string $url): bool { return (bool)preg_match('~^(/(?!/)[^\s]*|https://[^\s]+)$~',$url); }

// ---------- Click tracking ----------
// Post whose links are being rendered; trackUrl() records it on every outgoing link.
function trackPost(?int $id=null): int { static $post=0; if($id!==null)$post=$id; return $post; }
// Turns an external https URL into a /go/<id> tracking link. Only URLs stored in the
// links table can be redirected to, so /go/ cannot be used as an open redirect.
function trackUrl(string $url, string $placement, string $anchor=''): string {
 static $ids=[];
 if(!preg_match('~^https://\S+$~i',$url))return $url;
 if(!isset($ids[$url])){
  run('INSERT OR IGNORE INTO links(url,created_at) VALUES (?,?)',[$url,date('c')]);
  $ids[$url]=(int)query('SELECT id FROM links WHERE url=?',[$url])[0]['id'];
 }
 $q=['s'=>$placement];
 if(trackPost())$q['p']=trackPost();
 if($anchor!=='')$q['a']=mb_substr($anchor,0,80);
 return '/go/'.$ids[$url].'?'.http_build_query($q);
}
// Remembers each visitor (long-lived random id) and where their visit came from.
function captureVisit(): void {
 if(empty($_COOKIE['btv'])||!preg_match('/^[a-f0-9]{16}$/',$_COOKIE['btv'])){
  $_COOKIE['btv']=bin2hex(random_bytes(8));
  setcookie('btv',$_COOKIE['btv'],['expires'=>time()+63072000,'path'=>'/','httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off']);
 }
 if(!empty($_SESSION['visit']))return;
 $ref=(string)($_SERVER['HTTP_REFERER']??'');$host=strtolower((string)parse_url($ref,PHP_URL_HOST));$own=strtolower((string)($_SERVER['HTTP_HOST']??''));
 if($host===$own||$host==='www.'.$own||'www.'.$host===$own){$ref='';$host='';}
 $utm=[];foreach(['utm_source','utm_medium','utm_campaign'] as $k)$utm[$k]=mb_substr(trim((string)($_GET[$k]??'')),0,100);
 $_SESSION['visit']=['referrer'=>mb_substr($ref,0,500),'source'=>$utm['utm_source']?:trafficSource($host),'landing'=>mb_substr((string)($_SERVER['REQUEST_URI']??'/'),0,300)]+$utm;
}
// Friendly name for the site a visitor came from.
function trafficSource(string $host): string {
 if($host==='')return 'Direct';
 foreach(['google'=>'Google','bing'=>'Bing','yahoo'=>'Yahoo','duckduckgo'=>'DuckDuckGo','facebook'=>'Facebook','fb.'=>'Facebook','instagram'=>'Instagram','t.co'=>'X (Twitter)','twitter'=>'X (Twitter)','x.com'=>'X (Twitter)','pinterest'=>'Pinterest','linkedin'=>'LinkedIn','lnkd'=>'LinkedIn','reddit'=>'Reddit','youtube'=>'YouTube','whatsapp'=>'WhatsApp','telegram'=>'Telegram','chatgpt'=>'ChatGPT','perplexity'=>'Perplexity'] as $needle=>$name)if(str_contains($host,$needle))return $name;
 return preg_replace('/^www\./','',$host);
}
function parseAgent(string $ua): array {
 $bot=(bool)preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor|curl|wget|python|headless|lighthouse|httpclient|java\//i',$ua)||$ua==='';
 $device=preg_match('/ipad|tablet|kindle|silk|(android(?!.*mobile))/i',$ua)?'Tablet':(preg_match('/mobi|iphone|ipod|android|blackberry|opera mini|iemobile/i',$ua)?'Mobile':'Desktop');
 $browser=match(true){(bool)preg_match('/edg\//i',$ua)=>'Edge',(bool)preg_match('/opr\/|opera/i',$ua)=>'Opera',(bool)preg_match('/samsungbrowser/i',$ua)=>'Samsung Internet',(bool)preg_match('/firefox|fxios/i',$ua)=>'Firefox',(bool)preg_match('/chrome|crios/i',$ua)=>'Chrome',(bool)preg_match('/safari/i',$ua)=>'Safari',default=>'Other'};
 $os=match(true){(bool)preg_match('/windows/i',$ua)=>'Windows',(bool)preg_match('/iphone|ipad|ipod/i',$ua)=>'iOS',(bool)preg_match('/android/i',$ua)=>'Android',(bool)preg_match('/mac os x|macintosh/i',$ua)=>'macOS',(bool)preg_match('/cros/i',$ua)=>'ChromeOS',(bool)preg_match('/linux/i',$ua)=>'Linux',default=>'Other'};
 return ['device'=>$bot?'Bot':$device,'browser'=>$browser,'os'=>$os,'is_bot'=>$bot?1:0];
}
// Visitor IP; behind the local Varnish/nginx proxy the real address is in X-Forwarded-For.
function clientIp(): string {
 $ip=(string)($_SERVER['REMOTE_ADDR']??'');
 if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)){
  foreach(['HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP','HTTP_X_FORWARDED_FOR'] as $h){$c=trim(explode(',',(string)($_SERVER[$h]??''))[0]);if(filter_var($c,FILTER_VALIDATE_IP))return $c;}
 }
 return $ip;
}

// ---- SEO helpers ----
// Absolute site root, e.g. https://www.besttop10things.com (CLI scripts fall back to the live domain).
function siteBase(): string {
 $host=strtolower((string)($_SERVER['HTTP_HOST']??''));
 if(!preg_match('/^[a-z0-9.-]+(:\d+)?$/',$host))$host='www.besttop10things.com';
 return 'https://'.$host;
}
function absUrl(string $url): string { return str_starts_with($url,'/')?siteBase().$url:$url; }
// Rough language guess for imported articles (en/de/fr) so <html lang> and inLanguage are right.
function detectLang(string $text): string {
 $words=preg_split('/[^\p{L}]+/u',mb_strtolower(mb_substr(strip_tags($text),0,3000)),-1,PREG_SPLIT_NO_EMPTY);
 $sets=['de'=>['und','der','die','das','ist','nicht','mit','sich','auch','für','eine','werden','du','oder'],'fr'=>['les','des','est','pour','une','dans','vous','avec','sur','pas','qui','votre','sont','plus'],'en'=>['the','and','is','for','with','you','your','that','are','this','can','of','to','or']];
 $score=array_map(fn($set)=>count(array_intersect($words,$set)),$sets);
 arsort($score);return (string)array_key_first($score);
}
// Question/answer pairs from a "## Frequently Asked Questions" / "## FAQ" section (**Question** then answer lines).
function faqFrom(string $body): array {
 if(!preg_match('/^##\s+(?:FAQs?|Frequently Asked Questions|Häufig gestellte Fragen|Questions fréquentes)[^\n]*\n(.*?)(?=^##\s|\z)/imsu',$body,$m))return [];
 preg_match_all('/^\*\*(.+?)\*\*\s*\n((?:(?!\*\*).+\n?)+)/mu',$m[1],$qa,PREG_SET_ORDER);
 $out=[];foreach($qa as $x){$a=trim(preg_replace('/\[([^\]]+)\]\([^)]+\)/','$1',str_replace('**','',$x[2])));if($a!=='')$out[]=[trim($x[1]),$a];}
 return $out;
}
// IndexNow (Bing, Yandex, Seznam, Naver; used by ChatGPT search via Bing): a key file is served at /{key}.txt.
function indexNowKey(): string {
 $k=setting('indexnow_key');
 if(!preg_match('/^[a-f0-9]{32}$/',$k)){$k=bin2hex(random_bytes(16));run('INSERT OR REPLACE INTO settings(key,value) VALUES (?,?)',['indexnow_key',$k]);}
 return $k;
}
function indexNowPing(array $urls): bool {
 $urls=array_values(array_unique(array_map('absUrl',$urls)));
 if(!$urls||!function_exists('curl_init'))return false;
 $host=(string)parse_url(siteBase(),PHP_URL_HOST);$key=indexNowKey();
 $ch=curl_init('https://api.indexnow.org/indexnow');
 curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json; charset=utf-8'],CURLOPT_POSTFIELDS=>json_encode(['host'=>$host,'key'=>$key,'keyLocation'=>siteBase().'/'.$key.'.txt','urlList'=>array_slice($urls,0,10000)]),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>4,CURLOPT_CONNECTTIMEOUT=>3]);
 curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 return $code>=200&&$code<300;
}

// Schema.org types an editor may pick for a post.
const SCHEMA_TYPES=['BlogPosting'=>'Blog post (default)','Article'=>'Article','NewsArticle'=>'News article','HowTo'=>'How-to guide','Review'=>'Product review (uses the score)'];
// Custom header/body/footer code from the admin. Its <script> tags get a per-request nonce, so they run while
// the CSP still blocks any other inline script; external hosts must be listed in "Allowed script domains".
function cspNonce(): string { static $n=null; return $n??=base64_encode(random_bytes(16)); }
function customCode(string $key): string {
 $code=setting($key);if(trim($code)==='')return '';
 return preg_replace('/<script\b(?![^>]*\bnonce=)/i','<script nonce="'.cspNonce().'"',$code);
}
function codeDomains(): array {
 return array_values(array_filter(array_map(fn($d)=>strtolower(trim($d)),preg_split('/[\s,]+/',setting('code_domains'))),fn($d)=>preg_match('/^(\*\.)?[a-z0-9-]+(\.[a-z0-9-]+)+$/',$d)));
}
function sendCustomCodeCsp(): void {
 if(trim(setting('code_head').setting('code_body').setting('code_footer'))==='')return;
 $hosts=implode(' ',array_map(fn($d)=>'https://'.$d,codeDomains()));
 header("Content-Security-Policy: default-src 'self'; img-src 'self' https: data:; style-src 'self' $hosts; script-src 'self' 'nonce-".cspNonce()."' https://www.googletagmanager.com $hosts; connect-src 'self' https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com $hosts; frame-src $hosts https://www.googletagmanager.com; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");
}

// Same checks as the live analyser in the post editor (assets/admin.js). Returns [seoChecks, aiChecks], each a
// list of [state('pass'|'warn'|'fail'), message]; score = pass 1, warn ½, fail 0.
function seoAnalyse(array $p): array {
 $text=(string)$p['body'];$lower=mb_strtolower($text);
 $plain=preg_replace('/[#*>`_-]/u',' ',preg_replace('/!?\[([^\]]*)\]\([^)]*\)/u','$1',$text));
 $words=preg_split('/\s+/u',trim($plain),-1,PREG_SPLIT_NO_EMPTY);$wc=count($words);
 preg_match_all('/^##\s+(.+)$/mu',$text,$m);$h2s=array_map('mb_strtolower',$m[1]);
 $title=trim((string)(($p['meta_title']??'')!==''?$p['meta_title']:$p['title']));
 $desc=trim((string)(($p['meta_description']??'')!==''?$p['meta_description']:$p['excerpt']));
 $kw=mb_strtolower(trim((string)($p['focus_keyword']??'')));
 $has=fn($s)=>$kw!==''&&str_contains(mb_strtolower($s),$kw);
 $first=mb_strtolower(implode(' ',array_slice($words,0,120)));
 $kwCount=$kw!==''?substr_count($lower,$kw):0;
 $density=$wc?$kwCount*count(preg_split('/\s+/',$kw))/$wc*100:0;
 preg_match_all('/\]\(([^)]+)\)/',$text,$lm);$links=$lm[1];
 $seo=[];
 $seo[]=$kw!==''?['pass','Focus keyword set']:['fail','Add a focus keyword'];
 if($kw!==''){
  $seo[]=$has($title)?['pass','Keyword in SEO title']:['fail','Keyword not in SEO title'];
  $seo[]=$has($desc)?['pass','Keyword in meta description']:['fail','Keyword not in meta description'];
  $seo[]=str_contains((string)$p['slug'],trim(preg_replace('/[^a-z0-9]+/','-',$kw),'-')===''?'@':preg_replace('/[^a-z0-9]+/','-',$kw))?['pass','Keyword in URL']:['warn','Keyword not in URL'];
  $seo[]=str_contains($first,$kw)?['pass','Keyword in introduction']:['fail','Keyword not in introduction'];
  $seo[]=array_filter($h2s,fn($h)=>str_contains($h,$kw))?['pass','Keyword in a subheading']:['warn','Keyword not in a subheading'];
  $seo[]=$density>=0.5&&$density<=2.5?['pass',sprintf('Keyword density %.1f%%',$density)]:['warn',sprintf('Keyword density %.1f%% (0.5–2.5%%)',$density)];
 }
 $tl=mb_strlen($title);$dl=mb_strlen($desc);
 $seo[]=$tl>=30&&$tl<=60?['pass',"Title length $tl"]:['warn',"Title length $tl (30–60)"];
 $seo[]=$dl>=120&&$dl<=160?['pass',"Description length $dl"]:['warn',"Description length $dl (120–160)"];
 $seo[]=$wc>=600?['pass',"$wc words"]:['warn',"$wc words (600+)"];
 $seo[]=count($h2s)>=3?['pass',count($h2s).' subheadings']:['warn','Fewer than 3 subheadings'];
 $seo[]=array_filter($links,fn($u)=>str_starts_with($u,'/'))?['pass','Internal link']:['warn','No internal link'];
 $seo[]=array_filter($links,fn($u)=>str_starts_with($u,'https://'))?['pass','Outbound link']:['warn','No outbound link'];
 $tldr=trim((string)($p['tldr']??''));$tn=mb_strlen($tldr);
 $tk=count(array_filter(array_map('trim',explode("\n",(string)($p['takeaways']??'')))));
 $faq=0;
 if(preg_match('/^##\s+(?:faqs?|frequently asked questions).*$/imu',$text)){$parts=preg_split('/^##\s+(?:faqs?|frequently asked questions).*$/imu',$text);$sec=preg_split('/^##\s/mu',$parts[1]??'')[0];$faq=preg_match_all('/^\*\*.+\?\*\*\s*$/mu',$sec);}
 $ai=[];
 $ai[]=$tn>=40&&$tn<=320?['pass','Quick answer']:($tn?['warn',"Quick answer length $tn (40–320)"]:['fail','No quick answer']);
 $ai[]=$tk>=3?['pass',"$tk takeaways"]:['warn','Fewer than 3 takeaways'];
 $ai[]=$faq>=3?['pass',"FAQ ($faq)"]:['warn',"FAQ questions: $faq (3+)"];
 $ai[]=array_filter($h2s,fn($h)=>str_ends_with(trim($h),'?')||preg_match('/^(what|how|why|which|when|is|are|can|do|does|should)\b/u',$h))?['pass','Question heading']:['warn','No question-style heading'];
 $ai[]=preg_match('/^\s*(-|\d+\.)\s/mu',$text)?['pass','Has a list']:['warn','No list'];
 $ai[]=preg_match_all('/\b\d[\d.,%]*\b/u',$plain)>=3?['pass','Specific numbers']:['warn','Fewer than 3 numbers'];
 $ai[]=!$h2s||$wc/count($h2s)<=350?['pass','Short sections']:['warn','Sections too long ('.round($wc/count($h2s)).' words per heading)'];
 $ai[]=$kw!==''&&str_contains($first,$kw)&&preg_match('/[.!?]/u',implode(' ',array_slice($words,0,60)))?['pass','Direct answer up front']:['warn','No direct answer up front'];
 return [$seo,$ai];
}
function seoScore(array $checks): int { $n=0;foreach($checks as [$s])$n+=$s==='pass'?1:($s==='warn'?0.5:0);return (int)round($n/count($checks)*100); }

// Versioned asset URL: /assets/app.js?v=<content hash>. The URL changes whenever the file changes, so the
// long browser/CDN cache on /assets/ can never serve an outdated script or stylesheet to returning visitors.
function asset(string $path): string {
 static $cache=[];
 if(!isset($cache[$path])){$docroot=is_dir((string)($_SERVER['DOCUMENT_ROOT']??''))&&is_file($_SERVER['DOCUMENT_ROOT'].'/index.php')?$_SERVER['DOCUMENT_ROOT']:ROOT.'/../www.besttop10things.com';$file=$docroot.$path;$cache[$path]=$path.(is_file($file)?'?v='.substr(md5_file($file),0,10):'');}
 return $cache[$path];
}

// Homepage <title>: the CMS "Homepage title", unless it is empty or just repeats the meta description,
// in which case a clear branded default is used.
function homeTitle(): string {
 $t=trim(setting('seo_title'));$norm=fn($s)=>mb_strtolower(trim(preg_replace('/[\s[:punct:]]+/u',' ',$s)));
 if($t===''||$norm($t)===$norm(setting('description')))return setting('site_name').' | Reviews, Comparisons & Buying Guides';
 return $t;
}
// Popular shortcuts that always lead somewhere: the categories with the most live posts.
function popularLinks(int $n=4): array {
 $cats=array_filter(categories(),fn($c)=>(int)$c['total']>0);
 usort($cats,fn($a,$b)=>(int)$b['total']<=>(int)$a['total']);
 return array_map(fn($c)=>['label'=>$c['name'],'url'=>'/category/'.rawurlencode($c['slug'])],array_slice($cats,0,$n));
}
// A review is comparable/rankable only when it has a real score and at least pros or cons.
function rated(string $alias='r'): string { return "$alias.score>0 AND (trim($alias.pros)!='' OR trim($alias.cons)!='')"; }

require_once __DIR__.'/images.php';
require_once __DIR__.'/gsc.php';
require_once __DIR__.'/social.php';
require_once __DIR__.'/ai.php';
require_once __DIR__.'/advisor.php';

// ---- Authors (E-E-A-T) ----
// Posts store the author's display name; a matching profile adds a photo, bio and an author page.
function authorByName(string $name): ?array {
 static $map=null;
 if($map===null){$map=[];foreach(query('SELECT * FROM authors') as $a)$map[mb_strtolower($a['name'])]=$a;}
 return $map[mb_strtolower(trim($name))]??null;
}
function authorUrl(array $a): string { return '/author/'.rawurlencode($a['slug']); }
function authorLinks(array $a): array { return array_values(array_filter(array_map('trim',explode("\n",(string)$a['links'])),fn($u)=>preg_match('~^https://\S+$~',$u))); }
function personSchema(array $a): array {
 return array_filter(['@type'=>'Person','@id'=>siteBase().authorUrl($a).'#person','name'=>$a['name'],'url'=>siteBase().authorUrl($a),'jobTitle'=>$a['role']?:null,'description'=>$a['bio']?:null,
  'image'=>$a['avatar']!==''&&safeImage($a['avatar'])?absUrl($a['avatar']):null,'sameAs'=>authorLinks($a)?:null,
  'knowsAbout'=>array_values(array_filter(array_map('trim',explode(',',(string)$a['expertise']))))?:null,'worksFor'=>['@id'=>siteBase().'/#org']]);
}
const DEFAULT_METHODOLOGY = "Every guide and review on this site follows the same process, so you can see how we reach a recommendation.

## 1. Research
We start with the questions real buyers ask: what the product or service is for, who it suits and what the alternatives are. We read the official specifications, documentation and pricing pages, and note anything that is unclear or missing.

## 2. Compare
We look at the options side by side: features, ease of use, value, support and trade-offs. Where we give a score, it reflects how well a product does its job for the people it is meant for, not how many features it has.

## 3. Explain
We turn the details into practical advice: who should choose it, who should skip it, and what to check before buying.

## How scores work
Scores are out of 10 and only appear on reviews. Articles and buying guides are not scored or ranked.

- **9–10:** outstanding for its purpose, with very few trade-offs
- **7–8.9:** a strong choice with some limitations
- **5–6.9:** fine for some people, but with clear compromises
- **Below 5:** hard to recommend

## Independence and affiliate links
Some links are affiliate links: if you buy through them we may earn a commission at no extra cost to you. Commissions never change what we write, how we score a product or where it appears in a list. Brands cannot pay for a review or a better score.

## Updates and corrections
We review guides regularly and update them when prices, features or availability change. If you spot something out of date, please let us know and we will correct it.";

// ---- Automatic internal links ----
// Links the first mention of another live post's focus keyword (max $max links per article), preferring the
// same category and longer phrases. Never inside existing links, headings or the quick-answer box, and never
// to a post this article already links to.
function autoLinks(string $html, int $postId, int $categoryId, int $max=4): string {
 static $all=null;
 $all??=array_map(fn($c)=>$c+['href'=>'/'.$c['slug']],query("SELECT r.id,r.slug,r.category_id,lower(trim(r.focus_keyword)) AS kw FROM reviews r WHERE ".live()." AND length(trim(r.focus_keyword))>=6 AND instr(trim(r.focus_keyword),' ')>0"));
 $cands=array_filter($all,fn($c)=>(int)$c['id']!==$postId&&!str_contains($html,'href="'.$c['href'].'"'));
 usort($cands,fn($a,$b)=>[(int)($b['category_id']==$categoryId),mb_strlen($b['kw'])]<=>[(int)($a['category_id']==$categoryId),mb_strlen($a['kw'])]);
 $seen=[];$cands=array_values(array_filter($cands,function($c)use(&$seen){if(isset($seen[$c['kw']]))return false;return $seen[$c['kw']]=true;}));
 // The post's category hub: link the first mention of the category name (e.g. "travel").
 foreach(categories() as $cat)if((int)$cat['id']===$categoryId&&(int)$cat['total']>1&&mb_strlen($cat['name'])>=4&&!str_contains($html,'href="/category/'.$cat['slug'].'"'))$cands[]=['id'=>0,'kw'=>mb_strtolower(preg_replace('/\s*&\s*/u',' and ',$cat['name'])),'href'=>'/category/'.rawurlencode($cat['slug']),'category_id'=>$categoryId];
 if(!$cands)return $html;
 $parts=preg_split('/(<[^>]+>)/u',$html,-1,PREG_SPLIT_DELIM_CAPTURE);$skip=0;$done=0;
 foreach($parts as $i=>$part){
  if($part!==''&&$part[0]==='<'){
   if(preg_match('~^<(a|h[1-6]|figure|figcaption|code|pre|button)\b~i',$part))$skip++;
   elseif(preg_match('~^</(a|h[1-6]|figure|figcaption|code|pre|button)>~i',$part))$skip=max(0,$skip-1);
   continue;
  }
  if($skip||trim($part)==='')continue;
  foreach($cands as $k=>$c){
   $re='/(?<![\p{L}\p{N}])('.preg_quote($c['kw'],'/').')(?![\p{L}\p{N}])/iu';
   if(preg_match($re,$part)){$parts[$i]=$part=preg_replace($re,'<a href="'.e($c['href']).'">$1</a>',$part,1);unset($cands[$k]);if(++$done>=$max)break 2;break;}
  }
 }
 return implode('',$parts);
}

// ---- 301 redirects (merged posts, changed slugs) ----
function addRedirect(string $from, string $to): void {
 if($from===$to||$from==='/'||!str_starts_with($from,'/')||!str_starts_with($to,'/'))return;
 run('DELETE FROM redirects WHERE from_path=?',[$to]);                       // the target is live again
 run('UPDATE redirects SET to_path=? WHERE to_path=?',[$to,$from]);          // no redirect chains
 run('INSERT INTO redirects(from_path,to_path,created_at) VALUES (?,?,?) ON CONFLICT(from_path) DO UPDATE SET to_path=excluded.to_path',[$from,$to,date('c')]);
}
function redirectFor(string $path): ?string {
 $r=query('SELECT to_path FROM redirects WHERE from_path=?',[$path])[0]['to_path']??null;
 if($r!==null)run('UPDATE redirects SET hits=hits+1 WHERE from_path=?',[$path]);
 return $r;
}

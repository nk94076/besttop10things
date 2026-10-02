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
CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);');
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
function categories(): array { return query('SELECT c.*, COUNT(r.id) AS total FROM categories c LEFT JOIN reviews r ON r.category_id=c.id AND r.status="published" GROUP BY c.id ORDER BY c.id'); }
function reviewUrl(array $r): string { return '/?page=review&slug='.rawurlencode($r['slug']); }
function safeImage(string $url): bool { return (bool)preg_match('~^https://[^\s]+$~i', $url) || (bool)preg_match('~^/assets/[a-zA-Z0-9_./-]+\.(jpg|jpeg|png|webp|svg)$~', $url) || (bool)preg_match('~^/uploads/[a-f0-9]{32}\.(jpg|png|webp)$~', $url); }
function icon(string $name, string $class='h-5 w-5'): string {
 $paths=['search'=>'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/>','arrow'=>'<path d="M4 12h16m-6-6 6 6-6 6"/>','tech'=>'<rect x="4" y="3" width="16" height="13" rx="1"/><path d="M2 20h20M8 16v4m8-4v4"/>','shopping'=>'<path d="M4 7h16l1 14H3L4 7Zm4 0V5a4 4 0 0 1 8 0v2"/>','travel'=>'<path d="m3 10 7 2 5 9 2-1-2-8 6-6c2-3-1-5-3-3l-6 6-8-2-1 3Z"/>','gadgets'=>'<rect x="6" y="5" width="12" height="14" rx="3"/><path d="M9 5V1h6v4M9 19v4h6v-4m-6-6 2-3 2 1 2-3"/>','home'=>'<path d="m2 11 10-9 10 9M5 9v12h14V9M9 21v-8h6v8"/>','grid'=>'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>','check'=>'<path d="m5 12 4 4L19 6"/>','menu'=>'<path d="M3 6h18M3 12h18M3 18h18"/>','book'=>'<path d="M12 5c-4-3-8-2-10-1v16c3-2 7-2 10 0 3-2 7-2 10 0V4c-3-1-7-2-10 1Zm0 0v15"/>'];
 return '<svg class="'.e($class).'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['grid']).'</svg>';
}
function reviewCard(array $r): void { ?>
 <article class="group min-w-0"><a href="<?= e(reviewUrl($r)) ?>" class="block"><div class="relative overflow-hidden rounded-lg"><img class="card-photo" src="<?= e($r['image']) ?>" alt="<?= e($r['title']) ?>" loading="lazy"><span class="absolute bottom-3 left-3 rounded bg-white px-3 py-1.5 text-sm font-bold shadow-sm"><span class="text-amber-500">★</span> <?= number_format((float)$r['score'],1) ?><span class="font-normal text-muted"> / 10</span></span></div><div class="mt-5 flex items-center justify-between"><span class="eyebrow"><?= e($r['category']) ?></span><?php if($r['demo']): ?><span class="text-[10px] text-muted">Sample review</span><?php endif ?></div><h3 class="mt-2 text-2xl leading-tight group-hover:text-teal"><?= e($r['title']) ?></h3><p class="mt-3 text-sm leading-6 text-muted"><?= e($r['excerpt']) ?></p><span class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-teal">Read full review <?= icon('arrow','h-4 w-4') ?></span></a></article>
<?php }

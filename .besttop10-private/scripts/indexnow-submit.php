<?php
declare(strict_types=1);
// Usage: php scripts/indexnow-submit.php
// Sends every public URL (home, sections, categories, live posts) to IndexNow, so Bing, ChatGPT search,
// Yandex and others recrawl them quickly. Publishing in the admin already pings single posts automatically.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/bootstrap.php';
$urls=['/'];
foreach(['reviews','top10','categories','compare','about'] as $p)$urls[]=pagePath($p);
foreach(categories() as $c)if($c['total'])$urls[]='/category/'.rawurlencode($c['slug']);
foreach(query('SELECT r.slug FROM reviews r WHERE '.live()) as $r)$urls[]=reviewUrl($r);
echo 'Key file: '.siteBase().'/'.indexNowKey().".txt\n";
echo indexNowPing($urls)?count($urls)." URLs submitted to IndexNow.\n":"IndexNow did not accept the request (check that the key file above opens in a browser).\n";

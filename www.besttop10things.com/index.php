<?php
// Let PHP's built-in dev server serve real files (assets, uploads) directly.
if(PHP_SAPI==='cli-server'&&is_file(__DIR__.parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)))return false;
require __DIR__.'/../.besttop10-private/app/bootstrap.php';
require ROOT.'/app/layout.php';

// Routing: clean URLs, with 301 redirects from the old "/?page=…" links.
$path=rtrim(rawurldecode((string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)),'/')?:'/';
$routes=['/'=>'home','/index.php'=>'home','/reviews'=>'reviews','/top-10'=>'top10','/categories'=>'categories','/compare'=>'compare','/about'=>'about','/how-we-review'=>'methodology','/privacy'=>'privacy','/sitemap.xml'=>'sitemap','/robots.txt'=>'robots','/feed.xml'=>'feed','/llms.txt'=>'llms','/llms-full.txt'=>'llmsfull'];
if(($routes[$path]??'')==='home'&&isset($_GET['page'])&&$_SERVER['REQUEST_METHOD']==='GET'){
 $target=cleanUrl('/?'.(string)($_SERVER['QUERY_STRING']??''));
 if(!str_starts_with($target,'/?')){header('Location: '.$target,true,301);exit;}
}
// One URL per page: drop trailing slashes and /index.php (keeps the query string).
$rawPath=(string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
if(in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)&&($rawPath==='/index.php'||($rawPath!=='/'&&str_ends_with($rawPath,'/')))){
 $qs=(string)($_SERVER['QUERY_STRING']??'');header('Location: '.($rawPath==='/index.php'?'/':(rtrim($rawPath,'/')?:'/')).($qs!==''?'?'.$qs:''),true,301);exit;
}
// IndexNow ownership key file
if(preg_match('~^/([a-f0-9]{32})\.txt$~',$path,$m)&&hash_equals(indexNowKey(),$m[1])){header('Content-Type: text/plain; charset=utf-8');exit($m[1]);}
// Outgoing link click: log it, then send the visitor on to the stored destination.
if(preg_match('~^/go/(\d+)$~',$path,$m)){
 $link=query('SELECT * FROM links WHERE id=?',[(int)$m[1]])[0]??null;
 if(!$link){http_response_code(404);header('Content-Type: text/plain');exit('Link not found');}
 captureVisit();
 $visit=$_SESSION['visit']??[];
 $ua=mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,400);$agent=parseAgent($ua);
 $postId=(int)($_GET['p']??0);if($postId&&!query('SELECT id FROM reviews WHERE id=?',[$postId]))$postId=0;
 $page=(string)parse_url((string)($_SERVER['HTTP_REFERER']??''),PHP_URL_PATH);
 run('INSERT INTO clicks(link_id,post_id,placement,anchor,page,source,referrer,utm_source,utm_medium,utm_campaign,landing,visitor,ip,country,device,browser,os,user_agent,is_bot,is_admin,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[
  (int)$link['id'],$postId?:null,substr(preg_replace('/[^a-z0-9-]/','',(string)($_GET['s']??'')),0,30),mb_substr(trim((string)($_GET['a']??'')),0,80),mb_substr($page,0,300),
  (string)($visit['source']??'Direct'),(string)($visit['referrer']??''),(string)($visit['utm_source']??''),(string)($visit['utm_medium']??''),(string)($visit['utm_campaign']??''),(string)($visit['landing']??''),
  (string)($_COOKIE['btv']??''),clientIp(),strtoupper(substr(preg_replace('/[^A-Za-z]/','',(string)($_SERVER['HTTP_CF_IPCOUNTRY']??'')),0,2)),
  $agent['device'],$agent['browser'],$agent['os'],$ua,$agent['is_bot'],isset($_SESSION['admin'])?1:0,now()]);
 $dest=$link['url'];$param=setting('subid_param');
 if(preg_match('/^[A-Za-z0-9_]{1,30}$/',$param))$dest.=(str_contains($dest,'?')?'&':'?').$param.'=bt'.db()->lastInsertId();
 header('Cache-Control: no-store, private');header('X-Robots-Tag: noindex, nofollow');header('Location: '.$dest,true,302);exit;
}
if(isset($routes[$path]))$page=$routes[$path];
elseif(preg_match('~^/category/([a-z0-9-]+)$~',$path,$m)){$page='reviews';$_GET['category']=$m[1];}
elseif(preg_match('~^/author/([a-z0-9-]+)$~',$path,$m)){$page='author';$_GET['author']=$m[1];}
elseif(preg_match('~^/([a-z0-9-]+)$~',$path,$m)){$page='review';$_GET['slug']=$m[1];}
else $page='404';
if($page==='404'&&($to=redirectFor($path))){header('Location: '.$to,true,301);exit;}
if($page==='404')http_response_code(404);

if($page==='robots'){
 header('Content-Type: text/plain; charset=utf-8');
 $rules="Allow: /\nDisallow: /admin.php\nDisallow: /go/\nDisallow: /*?q=\n";
 $out="User-agent: *\n$rules\n";
 // Search and AI answer engines: each group can be switched off in Admin → SEO & Code.
 $groups=[['Googlebot','Bingbot','Applebot','DuckDuckBot'],
  setting('ai_search','allow')==='allow'?['OAI-SearchBot','ChatGPT-User','PerplexityBot','Perplexity-User','Claude-SearchBot','Claude-User','DuckAssistBot']:[],
  setting('ai_training','allow')==='allow'?['GPTBot','Google-Extended','ClaudeBot','Applebot-Extended','Meta-ExternalAgent','CCBot','Amazonbot']:[]];
 foreach(array_merge(...$groups) as $bot)$out.="User-agent: $bot\n$rules\n";
 if(setting('ai_search','allow')!=='allow')foreach(['OAI-SearchBot','ChatGPT-User','PerplexityBot','Perplexity-User','Claude-SearchBot','Claude-User','DuckAssistBot'] as $bot)$out.="User-agent: $bot\nDisallow: /\n\n";
 if(setting('ai_training','allow')!=='allow')foreach(['GPTBot','Google-Extended','ClaudeBot','Applebot-Extended','Meta-ExternalAgent','CCBot','Amazonbot'] as $bot)$out.="User-agent: $bot\nDisallow: /\n\n";
 echo $out."Sitemap: ".siteBase()."/sitemap.xml\n";exit;
}
if($page==='sitemap'){
 header('Content-Type: application/xml; charset=utf-8');
 $base=siteBase();
 $posts=query('SELECT r.slug,r.title,r.image,r.updated_at,r.published_at,c.slug AS cslug FROM reviews r JOIN categories c ON c.id=r.category_id WHERE '.live().' ORDER BY r.published_at DESC');
 $newest=$posts?substr(max(array_map(fn($r)=>max((string)$r['updated_at'],(string)$r['published_at']),$posts)),0,10):date('Y-m-d');
 $catMod=[];foreach($posts as $r)$catMod[$r['cslug']]=max($catMod[$r['cslug']]??'',substr(max((string)$r['updated_at'],(string)$r['published_at']),0,10));
 $urls=[['/',$newest,null]];
 foreach(['reviews','top10','categories','compare','about','methodology','privacy'] as $p)$urls[]=[pagePath($p),in_array($p,['reviews','top10','categories'],true)?$newest:null,null];
 foreach(categories() as $c)if($c['total'])$urls[]=['/category/'.rawurlencode($c['slug']),$catMod[$c['slug']]??null,null];
 foreach(query('SELECT a.slug FROM authors a WHERE EXISTS (SELECT 1 FROM reviews r WHERE lower(r.author)=lower(a.name) AND '.live().')') as $a)$urls[]=['/author/'.rawurlencode($a['slug']),null,null];
 foreach($posts as $r)$urls[]=[reviewUrl($r),substr(max((string)$r['updated_at'],(string)$r['published_at']),0,10),safeImage($r['image'])?[absUrl($r['image']),$r['title']]:null];
 echo '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';
 foreach($urls as [$u,$mod,$im])echo '<url><loc>'.e($base.$u).'</loc>'.($mod?'<lastmod>'.e($mod).'</lastmod>':'').($im?'<image:image><image:loc>'.e($im[0]).'</image:loc></image:image>':'').'</url>';
 echo '</urlset>';exit;
}
if($page==='feed'){
 header('Content-Type: application/rss+xml; charset=utf-8');
 $base=siteBase();$site=setting('site_name');
 $items=query('SELECT r.*,c.name AS category FROM reviews r JOIN categories c ON c.id=r.category_id WHERE '.live().' ORDER BY r.published_at DESC,r.id DESC LIMIT 30');
 echo '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/"><channel><title>'.e($site).'</title><link>'.e($base.'/').'</link><description>'.e(setting('description')).'</description><language>en</language><atom:link href="'.e($base.'/feed.xml').'" rel="self" type="application/rss+xml"/>';
 foreach($items as $r){$u=$base.reviewUrl($r);echo '<item><title>'.e($r['title']).'</title><link>'.e($u).'</link><guid isPermaLink="true">'.e($u).'</guid><pubDate>'.e(date(DATE_RSS,strtotime($r['published_at']??$r['created_at']))).'</pubDate><category>'.e($r['category']).'</category><description>'.e($r['excerpt']).'</description>'.(($im=shareImage($r,'social'))?'<enclosure url="'.e($base.$im).'" length="'.(int)@filesize(publicDir().$im).'" type="image/jpeg"/><media:content url="'.e($base.$im).'" medium="image" type="image/jpeg" width="1200" height="630"/>':'').'</item>';}
 echo '</channel></rss>';exit;
}
// llms.txt: a plain-text map of the site for AI assistants (llmstxt.org); llms-full.txt adds the article text.
if($page==='llms'||$page==='llmsfull'){
 header('Content-Type: text/plain; charset=utf-8');header('X-Robots-Tag: noindex');
 $base=siteBase();$full=$page==='llmsfull';
 $rows=query('SELECT r.*,c.name AS category FROM reviews r JOIN categories c ON c.id=r.category_id WHERE '.live().' ORDER BY c.name,r.published_at DESC');
 echo '# '.setting('site_name')."\n\n> ".setting('description')."\n\n".(trim(setting('llms_intro'))?:'Independent reviews, buying guides and top 10 lists. Each article is written and edited by our team; some links are affiliate links.')."\n\n## Sections\n\n- [All reviews]($base/reviews)\n- [Top 10 lists]($base/top-10)\n- [Categories]($base/categories)\n- [About]($base/about)\n";
 if(!$full)echo "- [Full article text]($base/llms-full.txt)\n";
 $group=null;
 foreach($rows as $r){
  if($full){echo "\n---\n\n# ".$r['title']."\n\nURL: $base".reviewUrl($r)."\nCategory: ".$r['category']."\nPublished: ".substr((string)($r['published_at']??$r['created_at']),0,10).(trim($r['tldr'])!==''?"\n\nSummary: ".trim($r['tldr']):'').(trim($r['takeaways'])!==''?"\n\nKey takeaways:\n".implode("\n",array_map(fn($t)=>'- '.ltrim(trim($t),'-• '),array_filter(explode("\n",$r['takeaways']),'trim'))):'')."\n\n".trim(preg_replace('/\[([^\]]+)\]\([^)]+\)/','$1',$r['body']))."\n";continue;}
  if($group!==$r['category']){$group=$r['category'];echo "\n## $group\n\n";}
  echo '- ['.$r['title']."]($base".reviewUrl($r).'): '.trim(preg_replace('/\s+/',' ',trim($r['tldr'])!==''?$r['tldr']:$r['excerpt']))."\n";
 }
 exit;
}

if($_SERVER['REQUEST_METHOD']==='GET')captureVisit();

// Newsletter sign-up
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='subscribe'){
 checkCsrf();
 $email=strtolower(trim((string)($_POST['email']??'')));
 if(filter_var($email,FILTER_VALIDATE_EMAIL)&&strlen($email)<=200)run('INSERT OR IGNORE INTO subscribers(email,created_at) VALUES (?,?)',[$email,date('c')]);
 redirect('/?subscribed=1#newsletter');
}

$join='SELECT r.*,c.name AS category,c.slug AS category_slug FROM reviews r JOIN categories c ON c.id=r.category_id ';
$cats=categories();
$shownCats=array_values(array_filter($cats,fn($c)=>(int)$c['total']>0)); // public lists only show categories with live posts
if ($page==='review') {
 $review=query($join.'WHERE r.slug=? AND '.(isset($_SESSION['admin'],$_GET['preview'])?"r.status!='trash'":live()),[(string)($_GET['slug']??'')])[0]??null;
 if(!$review&&($to=redirectFor($path))){header('Location: '.$to,true,301);exit;}
 if(!$review) { http_response_code(404); $page='404'; }
}
if($page==='author'){$authorRow=query('SELECT * FROM authors WHERE slug=?',[(string)($_GET['author']??'')])[0]??null;if(!$authorRow){http_response_code(404);$page='404';}}
$valid=['author','methodology','home','review','reviews','top10','categories','about','privacy','compare','404'];
if(!in_array($page,$valid,true)){http_response_code(404);$page='404';}
$titles=['methodology'=>'How We Review','author'=>'','home'=>'','reviews'=>'All Reviews & Buying Guides','top10'=>'Top 10 Lists: Our Highest-Rated Picks','categories'=>'Browse All Categories','about'=>'About Us','privacy'=>'Privacy Policy','compare'=>'Compare Top-Rated Picks Side by Side','404'=>'Page not found'];
$descs=['methodology'=>'How '.setting('site_name').' researches, compares and scores products: our review process, scoring scale and editorial independence.','reviews'=>'Browse every review and buying guide on '.setting('site_name').': honest trade-offs, practical tips and clear recommendations across tech, travel, shopping, lifestyle and more.','top10'=>'Our rated reviews ranked by review score, plus the latest buying guides, listed separately and unranked.','categories'=>'Explore reviews and buying guides by category, from tech and travel to fashion, home, education and more.','compare'=>'Compare rated reviews in the same category side by side: scores, pros and cons at a glance.','about'=>'Who we are, how we research and write our reviews, and how we keep our recommendations independent.','privacy'=>'How '.setting('site_name').' collects, uses and protects information, including cookies and outbound link tracking.','404'=>'The page you were looking for could not be found.'];
$base=siteBase();$crumb=fn(array $items)=>['@type'=>'BreadcrumbList','itemListElement'=>array_map(fn($i,$x)=>['@type'=>'ListItem','position'=>$i+1,'name'=>$x[0],'item'=>$base.$x[1]],array_keys($items),$items)];
$seo=[];$pageTitle=$titles[$page]??'';$pageDesc=$descs[$page]??'';
if($page==='review'){
 $r0=$review;$url=$base.reviewUrl($r0);$lang=detectLang($r0['title'].' '.$r0['body']);
 $pub=date('c',strtotime($r0['published_at']??$r0['created_at']));$mod=date('c',max(strtotime($r0['updated_at']),strtotime($r0['published_at']??$r0['created_at'])));
 $pageTitle=$r0['meta_title']?:$r0['title'];$pageDesc=$r0['meta_description']?:$r0['excerpt'];
 $stype=array_key_exists($r0['schema_type']??'',SCHEMA_TYPES)?$r0['schema_type']:'BlogPosting';if($stype==='Review'&&!($r0['score']>0&&($r0['brand']??'')!==''))$stype='BlogPosting';
 $article=['@type'=>$stype,'@id'=>$url.'#article','mainEntityOfPage'=>$url,'url'=>$url,'headline'=>mb_substr($r0['title'],0,110),'description'=>$pageDesc,'datePublished'=>$pub,'dateModified'=>$mod,'inLanguage'=>$lang,'articleSection'=>$r0['category'],'wordCount'=>str_word_count(strip_tags($r0['body'])),'author'=>($au=authorByName((string)$r0['author']))?personSchema($au):['@type'=>'Organization','name'=>$r0['author']?:setting('site_name'),'url'=>$base.'/about'],'publisher'=>['@id'=>$base.'/#org'],'isPartOf'=>['@id'=>$base.'/#website']];
 if(safeImage($r0['image']))$article['image']=['@type'=>'ImageObject','url'=>absUrl($r0['image'])];
 if(trim($r0['tldr']??'')!==''){$article['abstract']=trim($r0['tldr']);$article['speakable']=['@type'=>'SpeakableSpecification','cssSelector'=>['.quick-answer','.post-title']];}
 if(($r0['focus_keyword']??'')!=='')$article['keywords']=$r0['focus_keyword'];
 if($stype==='Review'){unset($article['articleSection'],$article['wordCount']);$article['itemReviewed']=['@type'=>'Organization','name'=>$r0['brand']];$article['reviewRating']=['@type'=>'Rating','ratingValue'=>(float)$r0['score'],'bestRating'=>10,'worstRating'=>0];if(!authorByName((string)$r0['author']))$article['author']=['@type'=>'Person','name'=>$r0['author']?:setting('site_name')];}
 if($stype==='HowTo'){preg_match_all('/^##\s+(.+)$/m',$r0['body'],$hs);$article=['@type'=>'HowTo','@id'=>$url.'#howto','name'=>$r0['title'],'description'=>$pageDesc,'inLanguage'=>$lang,'image'=>$article['image']??null,'step'=>array_map(fn($i,$h)=>['@type'=>'HowToStep','position'=>$i+1,'name'=>trim($h,' #*'),'url'=>$url.'#'.slug($h)],array_keys($hs[1]),$hs[1])];$article=array_filter($article);}
 elseif(($r0['brand']??'')!=='')$article['mentions']=['@type'=>'Brand','name'=>$r0['brand']];
 $seo=['type'=>'article','image'=>$r0['image'],'published'=>$pub,'modified'=>$mod,'section'=>$r0['category'],'lang'=>$lang,'jsonld'=>[$article,$crumb([['Home','/'],[$r0['category'],'/category/'.rawurlencode($r0['category_slug'])],[$r0['title'],reviewUrl($r0)]])]];
 if($faq=faqFrom($r0['body']))$seo['jsonld'][]=['@type'=>'FAQPage','@id'=>$url.'#faq','mainEntity'=>array_map(fn($x)=>['@type'=>'Question','name'=>$x[0],'acceptedAnswer'=>['@type'=>'Answer','text'=>$x[1]]],$faq)];
 if($custom=json_decode((string)($r0['custom_schema']??''),true))foreach(isset($custom['@type'])?[$custom]:(array)($custom['@graph']??$custom) as $node)if(is_array($node)&&isset($node['@type'])){unset($node['@context']);$seo['jsonld'][]=$node;}
 // Share image: the editor's override, else the generated 1200×630 title card, else the featured image.
 if(($r0['og_image']??'')!==''&&safeImage($r0['og_image']))$seo['image']=$r0['og_image'];
 elseif($og=shareImage($r0,'social'))$seo['image']=$og;
 if(($r0['seo_canonical']??'')!=='')$seo['canonical_abs']=$r0['seo_canonical'];
 if(in_array($r0['seo_robots']??'',['noindex, follow','index, nofollow','noindex, nofollow'],true))$seo['robots']=$r0['seo_robots'];
 if(isset($_GET['preview']))$seo['robots']='noindex, nofollow';
}elseif($page==='reviews'||$page==='top10'){
 $catSlug=(string)($_GET['category']??'');$curCat=null;foreach($cats as $c)if($c['slug']===$catSlug)$curCat=$c;
 if($curCat){
  $pageTitle=$page==='top10'?'Top 10 '.$curCat['name'].' Picks':$curCat['name'].' Reviews & Buying Guides';
  $pageDesc=$page==='top10'?'Rated '.$curCat['name'].' reviews ranked by review score, plus the latest '.$curCat['name'].' guides.':'The latest '.$curCat['name'].' reviews, buying guides and tips from '.setting('site_name').': '.$curCat['total'].' article'.($curCat['total']==1?'':'s').' with honest pros, cons and recommendations.';
 }
 $listed=query($join.'WHERE '.live().($page==='top10'?' AND '.rated():'').($curCat?' AND c.slug=?':'').' ORDER BY '.($page==='top10'?'r.score DESC,':'').'r.published_at DESC LIMIT '.($page==='top10'?10:20),$curCat?[$catSlug]:[]);
 $selfPath=$curCat&&$page==='reviews'?'/category/'.rawurlencode($catSlug):pagePath($page);
 $seo['jsonld']=[['@type'=>'CollectionPage','@id'=>$base.$selfPath.'#page','url'=>$base.$selfPath,'name'=>$pageTitle,'description'=>$pageDesc,'isPartOf'=>['@id'=>$base.'/#website'],'mainEntity'=>['@type'=>'ItemList','itemListOrder'=>$page==='top10'?'https://schema.org/ItemListOrderDescending':'https://schema.org/ItemListUnordered','numberOfItems'=>count($listed),'itemListElement'=>array_map(fn($i,$x)=>['@type'=>'ListItem','position'=>$i+1,'url'=>$base.reviewUrl($x),'name'=>$x['title']],array_keys($listed),$listed)]],$crumb(array_merge([['Home','/']],$curCat?[[$page==='top10'?'Top 10 Lists':'Reviews',pagePath($page)],[$curCat['name'],$selfPath]]:[[$titles[$page],pagePath($page)]]))];
 if($page==='top10'&&$curCat)$seo['canonical']=pagePath('top10').'?category='.rawurlencode($catSlug);
 if(trim((string)($_GET['q']??''))!==''||isset($_GET['sort'])||($catSlug!==''&&(!$curCat||!(int)$curCat['total'])))$seo['robots']='noindex, follow';
}elseif($page==='404')$seo['robots']='noindex, follow';
elseif($page==='home')$seo['jsonld']=[['@type'=>'WebPage','@id'=>$base.'/#webpage','url'=>$base.'/','name'=>homeTitle(),'description'=>setting('description'),'isPartOf'=>['@id'=>$base.'/#website'],'about'=>['@id'=>$base.'/#org']]];
elseif($page==='author'){
 $pageTitle=$authorRow['name'].($authorRow['role']!==''?', '.$authorRow['role']:'');$pageDesc=$authorRow['bio']!==''?mb_strimwidth($authorRow['bio'],0,300,'…'):'Articles and reviews by '.$authorRow['name'].' on '.setting('site_name').'.';
 $seo['type']='profile';if($authorRow['avatar']!==''&&safeImage($authorRow['avatar']))$seo['image']=$authorRow['avatar'];
 $seo['jsonld']=[['@type'=>'ProfilePage','@id'=>$base.authorUrl($authorRow),'url'=>$base.authorUrl($authorRow),'name'=>$pageTitle,'mainEntity'=>personSchema($authorRow),'isPartOf'=>['@id'=>$base.'/#website']],$crumb([['Home','/'],['Authors','/about'],[$authorRow['name'],authorUrl($authorRow)]])];
}elseif($page==='methodology')$seo['jsonld']=[['@type'=>'WebPage','@id'=>$base.'/how-we-review','url'=>$base.'/how-we-review','name'=>'How We Review','description'=>$pageDesc,'publisher'=>['@id'=>$base.'/#org'],'isPartOf'=>['@id'=>$base.'/#website']],$crumb([['Home','/'],['How We Review','/how-we-review']])];
elseif($page==='about')$seo['jsonld']=[['@type'=>'AboutPage','url'=>$base.'/about','name'=>'About '.setting('site_name'),'about'=>['@id'=>$base.'/#org']]];
headerView($pageTitle,$pageDesc,$page==='review'?'reviews':$page,$seo);
// Splits the homepage headline so its last two words can be highlighted.
$headline=function(string $text): string { $w=preg_split('/\s+/',trim($text)); if(count($w)<4)return e($text); $tail=array_splice($w,-2); return e(implode(' ',$w)).' <span class="hl">'.e(implode(' ',$tail)).'</span>'; };
?>
<main id="main">
<?php if($page==='home'):
 $slides=query($join.'WHERE '.live().' ORDER BY r.featured DESC,r.published_at DESC,r.id DESC LIMIT 4');
 $latest=query($join.'WHERE '.live().' ORDER BY r.published_at DESC,r.id DESC LIMIT 3');
?>
<section class="hero"><div class="wrap hero-grid">
 <div class="hero-copy">
  <p class="pill-eyebrow"><?= ficon('bolt','ic ic-xs') ?> Less guesswork. Better choices.</p>
  <h1 class="hero-title"><?= $headline(setting('tagline')) ?></h1>
  <p class="hero-sub"><?= e(setting('description')) ?></p>
  <form action="/reviews" class="hero-search" role="search"><?= ficon('search') ?><label for="hero-q" class="sr-only">Search reviews</label><input id="hero-q" name="q" placeholder="What are you looking for?"><button class="btn btn-primary" type="submit">Search <?= ficon('arrow','ic ic-sm') ?></button></form>
  <?php if($pop=popularLinks()): ?><div class="popular"><span>Popular:</span><?php foreach($pop as $l): ?><a href="<?= e($l['url']) ?>"><?= e($l['label']) ?></a><?php endforeach ?></div><?php endif ?>
 </div>
 <div class="hero-media" data-slider>
  <span class="hero-tag"><?= ficon('star','ic ic-sm') ?> Thoughtfully Compared</span>
  <div class="slide is-active"><img src="/assets/hero.jpg" alt="Headphones on a warm neutral background" fetchpriority="high"><div class="slide-card"><p class="eyebrow">Featured review</p><h2>Find your next favourite.</h2><p>Expert reviews, real research, honest opinions.</p><a class="link-arrow" href="/reviews">Explore reviews <?= ficon('arrow','ic ic-sm') ?></a></div></div>
  <?php foreach($slides as $s): ?><div class="slide" hidden><img src="<?= e($s['image']) ?>" alt="" loading="lazy"><div class="slide-card"><p class="eyebrow"><?= e($s['category']) ?></p><h2><?= e($s['title']) ?></h2><p><?= e(mb_strimwidth($s['excerpt'],0,90,'…')) ?></p><a class="link-arrow" href="<?= e(reviewUrl($s)) ?>">Read <?= $s['score']>0?'review':'article' ?> <?= ficon('arrow','ic ic-sm') ?></a></div></div><?php endforeach ?>
  <div class="slider-ui"><div class="dots"><?php for($i=0;$i<=count($slides);$i++): ?><button type="button" class="<?= $i?'':'is-active' ?>" data-slide="<?= $i ?>" aria-label="Show slide <?= $i+1 ?>"></button><?php endfor ?></div><button type="button" class="round-btn" data-prev aria-label="Previous slide"><?= ficon('left','ic ic-sm') ?></button><button type="button" class="round-btn" data-next aria-label="Next slide"><?= ficon('right','ic ic-sm') ?></button></div>
 </div>
</div></section>

<section class="wrap"><nav class="cat-strip" aria-label="Browse categories">
 <?php foreach(array_slice($shownCats,0,9) as $c): [$ic,$tone]=catStyle($c); ?><a href="/category/<?= e($c['slug']) ?>"><span class="cat-dot tone-<?= $tone ?>"><?= ficon($ic) ?></span><?= e($c['name']) ?></a><?php endforeach ?>
 <a href="/categories"><span class="cat-dot tone-slate"><?= ficon('dots') ?></span>More</a>
</nav></section>

<section class="wrap section">
 <div class="section-head"><div><p class="eyebrow">The latest word</p><h2 class="section-title">Reviews worth your time</h2><p class="section-sub">Honest reviews, practical advice, and top 10 lists to help you choose better.</p></div><a class="btn btn-outline" href="/reviews">See all reviews <?= ficon('arrow','ic ic-sm') ?></a></div>
 <div class="card-grid"><?php foreach($latest as $r) reviewCard($r); ?></div>
</section>

<?php // One section per category with more than CAT_SECTION_MIN posts; layouts rotate so each section looks different.
 $layouts=['feature','grid','spotlight','list'];
 $sectionCats=array_values(array_filter($cats,fn($c)=>(int)$c['total']>CAT_SECTION_MIN));
 foreach($sectionCats as $n=>$c):
  [$ic,$tone]=catStyle($c); $layout=$layouts[$n%count($layouts)];
  $posts=query($join.'WHERE '.live().' AND r.category_id=? ORDER BY r.featured DESC,r.published_at DESC,r.id DESC LIMIT '.($layout==='list'?6:5),[$c['id']]);
  $lead=$posts[0]; $rest=array_slice($posts,1);
?>
<section class="wrap section cat-section cat-<?= $layout ?> tone-band-<?= $tone ?>">
 <div class="section-head"><div class="cat-head"><span class="cat-dot cat-dot-lg tone-<?= $tone ?>"><?= ficon($ic) ?></span><div><p class="eyebrow">Best of <?= e($c['name']) ?></p><h2 class="section-title"><?= e($c['name']) ?></h2></div></div><a class="btn btn-outline" href="/category/<?= e($c['slug']) ?>">View all <?= (int)$c['total'] ?> <?= ficon('arrow','ic ic-sm') ?></a></div>
 <?php if($layout==='feature'): ?>
  <div class="feat-grid">
   <a class="feat-main" href="<?= e(reviewUrl($lead)) ?>"><img src="<?= e($lead['image']) ?>" alt="" loading="lazy"><div class="feat-body"><span class="badge badge-<?= $tone ?>"><?= e($c['name']) ?></span><h3><?= e($lead['title']) ?></h3><p><?= e($lead['excerpt']) ?></p><span class="link-arrow">Read article <?= ficon('arrow','ic ic-sm') ?></span></div></a>
   <ol class="feat-list"><?php foreach($rest as $i=>$r): ?><li><a href="<?= e(reviewUrl($r)) ?>"><span class="feat-num"><?= str_pad((string)($i+2),2,'0',STR_PAD_LEFT) ?></span><span><b><?= e($r['title']) ?></b><small><?= readMinutes($r['body']) ?> min read</small></span><img src="<?= e($r['image']) ?>" alt="" loading="lazy"></a></li><?php endforeach ?></ol>
  </div>
 <?php elseif($layout==='grid'): ?>
  <div class="card-grid card-grid-4"><?php foreach(array_slice($posts,0,4) as $r) reviewCard($r); ?></div>
 <?php elseif($layout==='spotlight'): ?>
  <div class="spot-grid">
   <a class="spot-main" href="<?= e(reviewUrl($lead)) ?>"><img src="<?= e($lead['image']) ?>" alt="" loading="lazy"><div class="spot-overlay"><span class="badge badge-<?= $tone ?>">Editor's pick</span><h3><?= e($lead['title']) ?></h3><span class="meta"><?= readMinutes($lead['body']) ?> min read</span></div></a>
   <div class="spot-side"><?php foreach($rest as $r): ?><a class="spot-item" href="<?= e(reviewUrl($r)) ?>"><img src="<?= e($r['image']) ?>" alt="" loading="lazy"><b><?= e($r['title']) ?></b></a><?php endforeach ?></div>
  </div>
 <?php else: ?>
  <div class="row-list"><?php foreach($posts as $r): ?><a class="row-item" href="<?= e(reviewUrl($r)) ?>"><img src="<?= e($r['image']) ?>" alt="" loading="lazy"><span><b><?= e($r['title']) ?></b><small><?= e(mb_strimwidth($r['excerpt'],0,110,'…')) ?></small><i class="meta"><?= e(date('M j, Y',strtotime($r['published_at']??$r['created_at']))) ?> • <?= readMinutes($r['body']) ?> min read</i></span></a><?php endforeach ?></div>
 <?php endif ?>
</section>
<?php endforeach ?>

<section class="wrap section">
 <div class="section-head"><div><p class="eyebrow">Something for every day</p><h2 class="section-title">Explore your interests</h2><p class="section-sub">Browse our top categories and find reviews tailored to your needs.</p></div><a class="btn btn-outline" href="/categories">View all categories <?= ficon('arrow','ic ic-sm') ?></a></div>
 <div class="interest-grid"><?php foreach($shownCats as $c): [$ic,$tone]=catStyle($c); ?><a class="interest" href="/category/<?= e($c['slug']) ?>"><span class="cat-dot cat-dot-lg tone-<?= $tone ?>"><?= ficon($ic) ?></span><span><b><?= e($c['name']) ?></b><small><?= (int)$c['total'] ?> review<?= (int)$c['total']===1?'':'s' ?></small></span><?= ficon('right','ic ic-sm chev') ?></a><?php endforeach ?></div>
</section>

<section class="wrap section"><div class="why">
 <div class="why-intro"><p class="eyebrow">Why choose us?</p><h2 class="section-title">Clarity comes first.</h2><p>A considered approach to every review.<br>We research, compare and explain so you can choose with confidence.</p><a class="btn btn-outline" href="/about#how">How we review <?= ficon('arrow','ic ic-sm') ?></a></div>
 <?php foreach([['doc','01','Research','Understand the features, the context and the choices.'],['scale','02','Compare','Look at the strengths and the trade-offs side by side.'],['bulb','03','Explain','Turn the details into practical, readable advice.']] as [$ic,$n,$t,$d]): ?><div class="why-step"><span class="cat-dot cat-dot-lg tone-teal"><?= ficon($ic) ?></span><h3><small><?= $n ?></small> <?= $t ?></h3><p><?= $d ?></p></div><?php endforeach ?>
</div></section>

<?php elseif($page==='reviews'||$page==='top10'):
 $q=trim((string)($_GET['q']??'')); $cat=(string)($_GET['category']??''); $sort=(string)($_GET['sort']??'latest');
 $where='WHERE '.live(); $params=[];
 if($q!==''){$where.=' AND (r.title LIKE ? OR r.excerpt LIKE ?)';$params[]='%'.$q.'%';$params[]='%'.$q.'%';}
 if($cat!==''){$where.=' AND c.slug=?';$params[]=$cat;}
 $order=$page==='top10'||$sort==='score'?'r.score DESC,r.published_at DESC,r.id DESC':($sort==='az'?'r.title COLLATE NOCASE':'r.published_at DESC,r.id DESC');
 if($page==='top10'){
  // Only reviews with a real score (and pros/cons) are ranked; editorial guides are listed separately, unranked.
  $results=query($join.$where.' AND '.rated().' ORDER BY r.score DESC,r.published_at DESC,r.id DESC LIMIT 10',$params);
  $guides=query($join.$where.' AND NOT ('.rated().') ORDER BY r.published_at DESC,r.id DESC LIMIT 9',$params);
 }else $results=query($join.$where.' ORDER BY '.$order,$params);
 $current=null;foreach($cats as $c)if($c['slug']===$cat)$current=$c;
 $listUrl=function(string $catSlug) use($page,$q): string {
  $params=$q!==''?['q'=>$q]:[];
  if($catSlug==='')return pagePath($page).($params?'?'.http_build_query($params):'');
  if($page==='reviews')return '/category/'.rawurlencode($catSlug).($params?'?'.http_build_query($params):'');
  return pagePath($page).'?'.http_build_query(['category'=>$catSlug]+$params);
 };
?>
<section class="page-hero"><div class="wrap">
 <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Home</a><?= ficon('right','ic ic-xs') ?><?php if($current): ?><a href="<?= pagePath($page) ?>"><?= $page==='top10'?'Top 10 Lists':'Reviews' ?></a><?= ficon('right','ic ic-xs') ?><span><?= e($current['name']) ?></span><?php else: ?><span><?= $page==='top10'?'Top 10 Lists':'Reviews' ?></span><?php endif ?></nav>
 <p class="eyebrow"><?= $page==='top10'?'The shortlist':'Find your next favourite' ?></p>
 <h1 class="page-title"><?= e($current?($page==='top10'?'Top 10 in '.$current['name']:$current['name'].' Guides & Reviews'):($page==='top10'?'The top 10 edit':($q!==''?'Results for “'.$q.'”':'Explore our reviews'))) ?></h1>
 <p class="section-sub"><?= $page==='top10'?'Rated reviews ranked by their review score, plus our latest buying guides listed separately.':'Useful details, honest trade-offs and clear comparisons, all in one place.' ?></p>
 <form class="filter-bar" action="<?= pagePath($page) ?>">
  <label class="field-ic"><?= ficon('search') ?><input name="q" value="<?= e($q) ?>" placeholder="Search products, ideas and more" aria-label="Search"></label>
  <label class="field-ic"><?= ficon('grid') ?><select name="category" aria-label="Category"><option value="">All categories</option><?php foreach($shownCats as $c): ?><option value="<?= e($c['slug']) ?>" <?= $cat===$c['slug']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach ?></select></label>
  <?php if($page==='reviews'): ?><label class="field-ic"><?= ficon('clock') ?><select name="sort" aria-label="Sort by"><option value="latest">Latest</option><option value="score" <?= $sort==='score'?'selected':'' ?>>Highest rated</option><option value="az" <?= $sort==='az'?'selected':'' ?>>A–Z</option></select></label><?php endif ?>
  <button class="btn btn-primary">Apply</button>
 </form>
 <div class="chip-row"><a class="<?= $cat===''?'is-active':'' ?>" href="<?= e($listUrl('')) ?>">All</a><?php foreach($shownCats as $c): ?><a class="<?= $cat===$c['slug']?'is-active':'' ?>" href="<?= e($listUrl($c['slug'])) ?>"><?= e($c['name']) ?></a><?php endforeach ?></div>
</div></section>
<section class="wrap section section-tight">
 <?php if($page==='top10'): ?>
 <div class="section-head"><div><h2 class="section-title">Highest-rated reviews</h2><p class="section-sub">Ranked by our review score out of 10. Only reviews that have a score and listed pros or cons are ranked.</p></div></div>
 <?php if(!$results): ?><div class="empty"><span class="cat-dot cat-dot-lg tone-teal"><?= ficon('trophy') ?></span><h2>No rated reviews yet</h2><p>There are no scored reviews<?= $current?' in '.e($current['name']):'' ?> to rank yet. Browse the guides below or all reviews instead.</p><a class="btn btn-primary" href="/reviews">Browse reviews</a></div>
 <?php else: ?><ol class="rank-list"><?php foreach($results as $i=>$r): ?><li class="rank"><span class="rank-num"><?= $i+1 ?></span><a class="rank-img" href="<?= e(reviewUrl($r)) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e($r['image']) ?>" alt="" loading="lazy"></a><div class="rank-body"><span class="badge badge-<?= catStyle(['slug'=>$r['category_slug'],'id'=>$r['category_id']])[1] ?>"><?= e($r['category']) ?></span><h2><a href="<?= e(reviewUrl($r)) ?>"><?= e($r['title']) ?></a></h2><p><?= e($r['excerpt']) ?></p></div><div class="rank-side"><?php if($r['score']>0): ?><span class="score-big"><?= number_format((float)$r['score'],1) ?><small>/10</small></span><?php endif ?><a class="btn btn-outline" href="<?= e(reviewUrl($r)) ?>">Read <?= ficon('arrow','ic ic-sm') ?></a></div></li><?php endforeach ?></ol><?php endif ?>
 <?php if($guides): ?><div class="section-head guides-head"><div><h2 class="section-title">Latest buying guides</h2><p class="section-sub">Editorial guides and articles. These are not rated or ranked.</p></div></div><div class="card-grid"><?php foreach($guides as $r) reviewCard($r); ?></div><?php endif ?>
 <?php else: ?>
 <?php if($page==='reviews'&&$current&&$q===''&&$results): // Category hub: intro, the main guides to start with, then everything else
  $pillars=query($join.'WHERE '.live().' AND c.slug=? ORDER BY r.featured DESC,length(r.body) DESC LIMIT 3',[$current['slug']]);$pids=array_column($pillars,'id');
  $resultsTotal=count($results);if(count($results)>4)$results=array_values(array_filter($results,fn($r)=>!in_array($r['id'],$pids)));else $pillars=[]; ?>
 <div class="hub-intro-box prose"><?= trim((string)($current['intro']??''))!==''?renderBody($current['intro']):'<p>Everything we have published about <strong>'.e($current['name']).'</strong> in one place: '.(int)$current['total'].' guides and reviews with practical advice, honest trade-offs and clear recommendations. New to the topic? Start with the essential guides below.</p>' ?></div>
 <?php if($pillars): ?><div class="section-head"><div><p class="eyebrow">Start here</p><h2 class="section-title">Essential <?= e($current['name']) ?> guides</h2></div></div><div class="card-grid hub-pillars"><?php foreach($pillars as $r) reviewCard($r); ?></div>
 <div class="section-head guides-head"><div><h2 class="section-title">All <?= e($current['name']) ?> articles</h2></div></div><?php endif ?>
 <?php endif ?>
 <div class="result-bar"><span><?= $n=$resultsTotal??count($results) ?> <?= $n===1?'result':'results' ?></span><a class="link-arrow" href="/compare<?= $cat!==''?'?category='.e(rawurlencode($cat)):'' ?>">Compare reviews <?= ficon('arrow','ic ic-sm') ?></a></div>
 <?php if(!$results): ?><div class="empty"><span class="cat-dot cat-dot-lg tone-teal"><?= ficon('search') ?></span><h2>No reviews found</h2><p>Try another keyword or explore a different category.</p><a class="btn btn-primary" href="<?= pagePath($page) ?>">Clear filters</a></div>
 <?php else: ?><div class="card-grid"><?php foreach($results as $r) reviewCard($r); ?></div><?php endif ?><?php endif ?>
</section>

<?php elseif($page==='review'): $r=$review; $tone=catStyle(['slug'=>$r['category_slug'],'id'=>$r['category_id']])[1];
 preg_match_all('/^##\s+(.+)$/m',$r['body'],$m); $toc=array_map(fn($h)=>trim(str_replace('**','',preg_replace('~\[([^\]]+)\]\([^)]*\)~','$1',$h))),$m[1]);
 $shareUrl='https://'.($_SERVER['HTTP_HOST']??'besttop10things.com').reviewUrl($r);
 $share=['facebook'=>['Facebook','https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($shareUrl)],'x'=>['X','https://twitter.com/intent/tweet?url='.rawurlencode($shareUrl).'&text='.rawurlencode($r['title'])],'pinterest'=>['Pinterest','https://pinterest.com/pin/create/button/?url='.rawurlencode($shareUrl).'&media='.rawurlencode(absUrl(shareImage($r,'pin')??$r['image'])).'&description='.rawurlencode($r['title'])],'linkedin'=>['LinkedIn','https://www.linkedin.com/sharing/share-offsite/?url='.rawurlencode($shareUrl)]];
 $hasCta=$r['brand']!==''&&$r['cta_url']!=='';
 $takeaways=$r['score']>0?[]:array_values(array_filter(array_map('trim',explode("\n",$r['pros']))));
 $related=query($join.'WHERE '.live().' AND r.id!=? AND r.category_id=? ORDER BY r.published_at DESC,r.id DESC LIMIT 3',[$r['id'],$r['category_id']]);
 $more=query($join.'WHERE '.live().' AND r.id!=? ORDER BY (r.category_id=?) DESC,r.published_at DESC LIMIT 3',[$r['id'],$r['category_id']]);
?>
<div class="read-progress" aria-hidden="true"><span data-progress-bar></span></div>
<article class="wrap post">
 <div class="post-top"><nav class="crumbs" aria-label="Breadcrumb"><a href="/">Home</a><?= ficon('right','ic ic-xs') ?><a href="/category/<?= e($r['category_slug']) ?>"><?= e($r['category']) ?></a><?= ficon('right','ic ic-xs') ?><span><?= e($r['title']) ?></span></nav>
  <div class="progress-inline" aria-hidden="true"><span>Reading progress</span><i><b data-progress-bar></b></i><span data-progress-text>0%</span></div></div>
 <div class="post-grid">
  <div class="post-main">
   <span class="badge badge-<?= $tone ?>"><?= e($r['category']) ?></span>
   <h1 class="post-title"><?= e($r['title']) ?></h1>
   <p class="post-dek"><?= e($r['excerpt']) ?></p>
   <div class="byline">
    <?php $au=authorByName((string)$r['author']); if($au&&$au['avatar']!==''&&safeImage($au['avatar'])): ?><img class="avatar-lg avatar-img" src="<?= e($au['avatar']) ?>" alt="" width="48" height="48"><?php else: ?><span class="avatar-lg"><?= e(strtoupper(substr($r['author'],0,1))) ?></span><?php endif ?>
    <div><b><?php if($au): ?><a href="<?= e(authorUrl($au)) ?>" rel="author"><?= e($au['name']) ?></a><?php else: ?><?= e($r['author']) ?><?php endif ?></b><span class="meta"><?php if($au&&$au['role']!==''): ?><?= e($au['role']) ?><i>•</i><?php endif ?><?= e(date('M j, Y',strtotime($r['published_at']??$r['created_at']))) ?><i>•</i><?= readMinutes($r['body']) ?> min read</span></div>
    <div class="share"><button type="button" class="share-btn" data-copy-link="<?= e($shareUrl) ?>" aria-label="Copy link" title="Copy link"><?= ficon('link','ic ic-sm') ?></button><?php foreach($share as $k=>[$label,$url]): ?><a class="share-btn share-<?= $k ?>" href="<?= e($url) ?>" target="_blank" rel="noopener" aria-label="Share on <?= $label ?>" title="Share on <?= $label ?>"><?= ficon($k,'ic ic-sm') ?></a><?php endforeach ?></div>
   </div>
   <img class="post-img" src="<?= e($r['image']) ?>" alt="<?= e($r['title']) ?>">
   <?php if($r['demo']): ?><p class="note note-amber">This is a sample review. Images, ratings and observations demonstrate the website and do not represent a verified product test.</p><?php endif ?>
   <?php $tk=array_values(array_filter(array_map('trim',explode("\n",$r['takeaways']??'')))); if(trim($r['tldr']??'')!==''||$tk): ?><aside class="quick-answer" aria-label="Quick answer"><p class="qa-label"><?= ficon('check','ic ic-sm') ?> Quick answer</p><?php if(trim($r['tldr'])!==''): ?><p class="qa-text"><?= e(trim($r['tldr'])) ?></p><?php endif ?><?php if($tk): ?><p class="qa-sub">Key takeaways</p><ul class="qa-list"><?php foreach($tk as $t): ?><li><?= e(ltrim($t,'-• ')) ?></li><?php endforeach ?></ul><?php endif ?></aside><?php endif ?>
   <div class="prose post-body"><?php trackPost((int)$r['id']); ?><?= autoLinks(renderBody($r['body']),(int)$r['id'],(int)$r['category_id']) ?></div>
   <?php if($hasCta): ?><div class="cta-band"><div><p class="eyebrow">Ready to explore?</p><h2><?= e($r['brand']) ?></h2><?php if($r['brand_about']!==''): ?><p><?= e($r['brand_about']) ?></p><?php endif ?></div><a class="btn btn-primary" href="<?= e(trackUrl($r['cta_url'],'cta-band',$r['brand'])) ?>" target="_blank" rel="sponsored nofollow noopener">Visit <?= e($r['brand']) ?> <?= ficon('external','ic ic-sm') ?></a></div><?php endif ?>
   <p class="disclose-line"><?= ficon('doc','ic ic-sm') ?> This article may contain affiliate links. We may earn a commission if you buy through them, at no extra cost to you.</p>
  </div>
  <aside class="post-side">
   <?php if($r['score']>0): ?><div class="side-card verdict"><p class="side-title"><span class="side-ic"><?= ficon('star','ic ic-sm') ?></span>Our verdict</p><p class="score-big"><?= number_format((float)$r['score'],1) ?><small>/10</small></p><p class="side-text"><?= e($r['verdict']) ?></p>
    <?php if(trim($r['pros'])!==''): ?><h3>What we like</h3><ul class="pc pc-pro"><?php foreach(array_filter(explode("\n",$r['pros'])) as $p): ?><li><?= ficon('check','ic ic-sm') ?><?= e($p) ?></li><?php endforeach ?></ul><?php endif ?>
    <?php if(trim($r['cons'])!==''): ?><h3>What could be better</h3><ul class="pc pc-con"><?php foreach(array_filter(explode("\n",$r['cons'])) as $p): ?><li><?= ficon('minus','ic ic-sm') ?><?= e($p) ?></li><?php endforeach ?></ul><?php endif ?>
    <a class="btn btn-outline btn-block" href="/compare?category=<?= e($r['category_slug']) ?>">Compare options <?= ficon('arrow','ic ic-sm') ?></a></div><?php endif ?>
   <?php if(count($toc)>1||$hasCta): ?><nav class="side-card toc" aria-label="In this article"><p class="side-title"><span class="side-ic"><?= ficon('listnum','ic ic-sm') ?></span>In this article</p>
    <?php if(count($toc)>1): ?><ol><?php foreach($toc as $h): ?><li><a href="#<?= e(slug($h)) ?>" data-toc-link><?= e($h) ?></a></li><?php endforeach ?></ol><?php endif ?>
    <?php if($hasCta): ?><a class="btn btn-primary btn-block" href="<?= e(trackUrl($r['cta_url'],'cta-toc',$r['brand'])) ?>" target="_blank" rel="sponsored nofollow noopener">Shop on <?= e($r['brand']) ?> <?= ficon('arrow','ic ic-sm') ?></a><?php endif ?></nav><?php endif ?>
   <?php if($takeaways): ?><div class="side-card"><p class="side-title"><span class="side-ic"><?= ficon('bulb','ic ic-sm') ?></span>Key Takeaways</p><ul class="pc pc-pro"><?php foreach($takeaways as $t): ?><li><?= ficon('check','ic ic-sm') ?><?= e($t) ?></li><?php endforeach ?></ul></div><?php endif ?>
   <?php if($hasCta): ?><div class="side-card brand-card"><div class="brand-row"><span class="brand-logo tone-<?= $tone ?>"><?= e(mb_strtoupper(mb_substr($r['brand'],0,1))) ?></span><div><p class="side-title">About <?= e($r['brand']) ?></p><?php if($r['brand_about']!==''): ?><p class="side-text"><?= e($r['brand_about']) ?></p><?php endif ?></div></div><a class="btn btn-primary btn-block" href="<?= e(trackUrl($r['cta_url'],'cta-about',$r['brand'])) ?>" target="_blank" rel="sponsored nofollow noopener">Visit <?= e($r['brand']) ?> <?= ficon('external','ic ic-sm') ?></a></div><?php endif ?>
   <?php if($related): ?><div class="side-card"><p class="side-title"><span class="side-ic"><?= ficon('related','ic ic-sm') ?></span>Related in <?= e($r['category']) ?></p><ul class="mini-list"><?php foreach($related as $x): ?><li><a href="<?= e(reviewUrl($x)) ?>"><img src="<?= e($x['image']) ?>" alt="" loading="lazy"><span><b><?= e($x['title']) ?></b><small><?= e(date('M j, Y',strtotime($x['published_at']??$x['created_at']))) ?> • <?= readMinutes($x['body']) ?> min read</small></span></a></li><?php endforeach ?></ul></div><?php endif ?>
  </aside>
 </div>
</article>
<section class="wrap section"><div class="section-head"><h2 class="section-title">Keep exploring</h2><a class="link-arrow" href="/reviews">View all articles <?= ficon('arrow','ic ic-sm') ?></a></div><div class="card-grid"><?php foreach($more as $x)reviewCard($x); ?></div></section>

<?php elseif($page==='compare'):
 // Comparable = live, scored, with pros or cons; compared only within one category (needs at least 2).
 $eligible=[];foreach(query('SELECT c.slug,c.name,COUNT(*) AS n FROM reviews r JOIN categories c ON c.id=r.category_id WHERE '.live().' AND '.rated().' GROUP BY c.id HAVING n>=2 ORDER BY n DESC,c.name') as $x)$eligible[$x['slug']]=$x;
 $cat=(string)($_GET['category']??'');
 if(!isset($eligible[$cat]))$cat=$cat===''&&$eligible?(string)array_key_first($eligible):($cat===''?'':$cat);
 $comp=isset($eligible[$cat])?query($join.'WHERE '.live().' AND '.rated().' AND c.slug=? ORDER BY r.score DESC,r.published_at DESC LIMIT 3',[$cat]):[];
?>
<section class="page-hero"><div class="wrap">
 <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Home</a><?= ficon('right','ic ic-xs') ?><span>Compare</span></nav>
 <p class="eyebrow">Side by side</p><h1 class="page-title">A clearer comparison</h1><p class="section-sub">Compare up to three of the highest-rated reviews within the same category. Only reviews with a score and listed pros or cons are included.</p>
 <?php if($eligible): ?><form class="filter-bar" action="/compare"><label class="field-ic"><?= ficon('grid') ?><select name="category" aria-label="Category"><?php foreach($eligible as $x): ?><option value="<?= e($x['slug']) ?>" <?= $cat===$x['slug']?'selected':'' ?>><?= e($x['name']) ?> (<?= (int)$x['n'] ?>)</option><?php endforeach ?></select></label><button class="btn btn-primary">Compare</button></form><?php endif ?>
</div></section>
<section class="wrap section section-tight">
 <?php if(count($comp)<2): ?><div class="empty"><span class="cat-dot cat-dot-lg tone-teal"><?= ficon('scale') ?></span><h2>Not enough reviews to compare<?= $cat!==''&&!isset($eligible[$cat])?' in this category':'' ?></h2><p>A comparison needs at least two rated reviews in the same category. <?= $eligible?'Choose a category above, or browse':'Browse' ?> our reviews and buying guides instead.</p><a class="btn btn-primary" href="/reviews">Browse reviews</a> <a class="btn btn-outline" href="/top-10">See Top 10</a></div>
 <?php else: ?><div class="compare-grid"><?php foreach($comp as $i=>$r): ?><article class="compare-card<?= $i===0?' is-top':'' ?>"><?php if($i===0): ?><span class="top-pick"><?= ficon('trophy','ic ic-sm') ?> Top pick</span><?php endif ?><img src="<?= e($r['image']) ?>" alt="" loading="lazy"><div class="compare-body"><span class="badge badge-<?= catStyle(['slug'=>$r['category_slug'],'id'=>$r['category_id']])[1] ?>"><?= e($r['category']) ?></span><h2><?= e($r['title']) ?></h2><p class="score-big"><?= number_format((float)$r['score'],1) ?><small>/10</small></p><h3>Pros</h3><?php $pl=array_filter(array_map('trim',explode("\n",$r['pros']))); if($pl): ?><ul class="pc pc-pro"><?php foreach($pl as $p): ?><li><?= ficon('check','ic ic-sm') ?><?= e($p) ?></li><?php endforeach ?></ul><?php else: ?><p class="muted">Not provided</p><?php endif ?><h3>Cons</h3><?php $cl=array_filter(array_map('trim',explode("\n",$r['cons']))); if($cl): ?><ul class="pc pc-con"><?php foreach($cl as $p): ?><li><?= ficon('minus','ic ic-sm') ?><?= e($p) ?></li><?php endforeach ?></ul><?php else: ?><p class="muted">Not provided</p><?php endif ?><a class="btn btn-primary btn-block" href="<?= e(reviewUrl($r)) ?>">Read full review</a></div></article><?php endforeach ?></div><?php endif ?>
</section>

<?php elseif($page==='categories'): ?>
<section class="page-hero"><div class="wrap">
 <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Home</a><?= ficon('right','ic ic-xs') ?><span>Categories</span></nav>
 <p class="eyebrow">Discover something useful</p><h1 class="page-title">Everyday interests. Explored.</h1><p class="section-sub">Find reviews and ideas in the categories that matter to you.</p>
</div></section>
<section class="wrap section section-tight"><div class="cat-cards"><?php foreach($shownCats as $c): [$ic,$tone]=catStyle($c); ?><a class="cat-card" href="/category/<?= e($c['slug']) ?>"><span class="cat-dot cat-dot-xl tone-<?= $tone ?>"><?= ficon($ic) ?></span><h2><?= e($c['name']) ?></h2><p><?= (int)$c['total'] ?> review<?= (int)$c['total']===1?'':'s' ?></p><span class="link-arrow">Explore <?= ficon('arrow','ic ic-sm') ?></span></a><?php endforeach ?></div></section>

<?php elseif($page==='methodology'): ?>
<section class="page-hero"><div class="wrap narrow-wrap">
 <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Home</a><?= ficon('right','ic ic-xs') ?><span>How We Review</span></nav>
 <p class="eyebrow">Our methodology</p><h1 class="page-title">How we review</h1><p class="section-sub">Research, compare, explain: the process behind every guide and score.</p>
</div></section>
<section class="wrap narrow-wrap section section-tight"><div class="prose post-body"><?= renderBody(trim(setting('methodology'))?:DEFAULT_METHODOLOGY) ?></div>
 <?php $team=query('SELECT * FROM authors ORDER BY id'); if($team): ?><h2 class="section-title team-title">The team</h2><div class="team-grid"><?php foreach($team as $a): ?><a class="team-card" href="<?= e(authorUrl($a)) ?>"><?php if($a['avatar']!==''&&safeImage($a['avatar'])): ?><img src="<?= e($a['avatar']) ?>" alt="" loading="lazy"><?php else: ?><span class="avatar-lg"><?= e(mb_strtoupper(mb_substr($a['name'],0,1))) ?></span><?php endif ?><span><b><?= e($a['name']) ?></b><small><?= e($a['role']) ?></small></span></a><?php endforeach ?></div><?php endif ?>
</section>

<?php elseif($page==='author'): $a=$authorRow; $posts=query($join.'WHERE '.live().' AND lower(r.author)=lower(?) ORDER BY r.published_at DESC',[$a['name']]); ?>
<section class="page-hero"><div class="wrap narrow-wrap">
 <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Home</a><?= ficon('right','ic ic-xs') ?><a href="/how-we-review">Our team</a><?= ficon('right','ic ic-xs') ?><span><?= e($a['name']) ?></span></nav>
 <div class="author-head"><?php if($a['avatar']!==''&&safeImage($a['avatar'])): ?><img class="author-photo" src="<?= e($a['avatar']) ?>" alt="<?= e($a['name']) ?>"><?php else: ?><span class="author-photo avatar-lg"><?= e(mb_strtoupper(mb_substr($a['name'],0,1))) ?></span><?php endif ?>
  <div><p class="eyebrow"><?= e($a['role']?:'Author') ?></p><h1 class="page-title"><?= e($a['name']) ?></h1>
  <?php if($a['expertise']!==''): ?><p class="author-tags"><?php foreach(array_filter(array_map('trim',explode(',',$a['expertise']))) as $t): ?><span class="chip-tag"><?= e($t) ?></span><?php endforeach ?></p><?php endif ?></div></div>
 <?php if($a['bio']!==''): ?><div class="prose author-bio"><?php foreach(preg_split('/\n\s*\n/',trim($a['bio'])) as $para): ?><p><?= nl2br(e($para)) ?></p><?php endforeach ?></div><?php endif ?>
 <?php if($links=authorLinks($a)): ?><p class="author-links"><?php foreach($links as $u): ?><a href="<?= e($u) ?>" target="_blank" rel="noopener me"><?= e(preg_replace('/^www\./','',(string)parse_url($u,PHP_URL_HOST))) ?></a><?php endforeach ?></p><?php endif ?>
</div></section>
<section class="wrap section section-tight"><h2 class="section-title"><?= count($posts) ?> article<?= count($posts)===1?'':'s' ?> by <?= e($a['name']) ?></h2><?php if($posts): ?><div class="card-grid"><?php foreach($posts as $r) reviewCard($r); ?></div><?php else: ?><p class="muted">No published articles yet.</p><?php endif ?></section>

<?php elseif($page==='about'||$page==='privacy'): ?>
<section class="page-hero"><div class="wrap narrow-wrap">
 <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Home</a><?= ficon('right','ic ic-xs') ?><span><?= $page==='about'?'About':'Privacy' ?></span></nav>
 <p class="eyebrow"><?= $page==='about'?'Behind the reviews':'Your information' ?></p><h1 class="page-title"><?= $page==='about'?'Clarity comes first.':'Privacy' ?></h1>
</div></section>
<section class="wrap narrow-wrap section section-tight"><div class="prose">
 <?php if($page==='about'): ?>
  <p><?= e(setting('site_name')) ?> is a place to explore product and service reviews, buying guides and top 10 lists across the things that make up everyday life.</p>
  <h2 id="how">How we review</h2>
  <p><a href="/how-we-review">Read our full review methodology and scoring scale →</a></p>
  <div class="how-steps"><?php foreach([['doc','Research','Understand the features, the context and the choices.'],['scale','Compare','Look at the strengths and the trade-offs side by side.'],['bulb','Explain','Turn the details into practical, readable advice.']] as $i=>[$ic,$t,$d]): ?><div class="how-step"><span class="cat-dot cat-dot-lg tone-teal"><?= ficon($ic) ?></span><h3><small>0<?= $i+1 ?></small> <?= $t ?></h3><p><?= $d ?></p></div><?php endforeach ?></div>
  <p>Our editorial framework focuses on usefulness, ease of use, features and value. Reviews can include a score, strengths, limitations and a clear verdict, so readers can see the reasoning behind a recommendation.</p>
  <h2>Affiliate links</h2>
  <p>Some articles contain affiliate links. If you buy through them we may earn a commission, at no extra cost to you. This never changes how a product is described or scored.</p>
  <h2>Sample content</h2>
  <p>Content labelled as a sample is illustrative. Its scores and observations are not claims of hands-on testing.</p>
 <?php else: ?>
  <p>This website stores essential session cookies for secure CMS login and form protection. Public browsing does not require an account.</p>
  <p>If you subscribe to our newsletter, we store your email address so we can send you updates. You can ask us to remove it at any time.</p>
  <p>When you click a link to another website, such as a shop, we record the click so we can understand which articles are useful. This includes the time, the article and link, the page you came from, your IP address, approximate country if available, device and browser type, and a random visitor ID kept in a cookie. We do not record your name or email with clicks.</p>
  <p>CMS account information and editorial content are stored in the site's database. Failed login attempts are temporarily recorded to limit repeated attempts. The site does not include analytics or advertising trackers by default.</p>
  <p>Images configured by editors may load from third-party HTTPS hosts. Those hosts receive the network information needed to serve an image. Website server logs may also record requests.</p>
 <?php endif ?>
</div></section>

<?php else: ?>
<section class="wrap section notfound"><span class="cat-dot cat-dot-xl tone-teal"><?= ficon('search') ?></span><p class="eyebrow">404</p><h1 class="page-title">This page took a different path.</h1><p class="section-sub">The page you are looking for doesn't exist or has moved.</p><div class="notfound-actions"><a class="btn btn-primary" href="/">Back to home</a><a class="btn btn-outline" href="/reviews">Browse reviews</a></div></section>
<?php endif ?>
</main>
<?php footerView(); ?>

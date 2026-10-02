<?php
// Let PHP's built-in dev server serve real files (assets, uploads) directly.
if(PHP_SAPI==='cli-server'&&is_file(__DIR__.parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)))return false;
require __DIR__.'/../.besttop10-private/app/bootstrap.php';
require ROOT.'/app/layout.php';

// Routing: clean URLs, with 301 redirects from the old "/?page=…" links.
$path=rtrim(rawurldecode((string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)),'/')?:'/';
$routes=['/'=>'home','/index.php'=>'home','/reviews'=>'reviews','/top-10'=>'top10','/categories'=>'categories','/compare'=>'compare','/about'=>'about','/privacy'=>'privacy','/sitemap.xml'=>'sitemap','/robots.txt'=>'robots'];
if(($routes[$path]??'')==='home'&&isset($_GET['page'])&&$_SERVER['REQUEST_METHOD']==='GET'){
 $target=cleanUrl('/?'.(string)($_SERVER['QUERY_STRING']??''));
 if(!str_starts_with($target,'/?')){header('Location: '.$target,true,301);exit;}
}
if(isset($routes[$path]))$page=$routes[$path];
elseif(preg_match('~^/category/([a-z0-9-]+)$~',$path,$m)){$page='reviews';$_GET['category']=$m[1];}
elseif(preg_match('~^/([a-z0-9-]+)$~',$path,$m)){$page='review';$_GET['slug']=$m[1];}
else $page='404';
if($page==='404')http_response_code(404);

if($page==='robots'){
 header('Content-Type: text/plain; charset=utf-8');
 echo "User-agent: *\nDisallow: /admin.php\n\nSitemap: https://".($_SERVER['HTTP_HOST']??'')."/sitemap.xml\n";exit;
}
if($page==='sitemap'){
 header('Content-Type: application/xml; charset=utf-8');
 $base='https://'.($_SERVER['HTTP_HOST']??'');
 $urls=[['/',date('Y-m-d')]];
 foreach(['reviews','top10','categories','compare','about','privacy'] as $p)$urls[]=[pagePath($p),null];
 foreach(categories() as $c)if($c['total'])$urls[]=['/category/'.rawurlencode($c['slug']),null];
 foreach(query('SELECT r.slug,r.updated_at FROM reviews r WHERE '.live().' ORDER BY r.published_at DESC') as $r)$urls[]=[reviewUrl($r),substr($r['updated_at'],0,10)];
 echo '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
 foreach($urls as [$u,$mod])echo '<url><loc>'.e($base.$u).'</loc>'.($mod?'<lastmod>'.e($mod).'</lastmod>':'').'</url>';
 echo '</urlset>';exit;
}

// Newsletter sign-up
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='subscribe'){
 checkCsrf();
 $email=strtolower(trim((string)($_POST['email']??'')));
 if(filter_var($email,FILTER_VALIDATE_EMAIL)&&strlen($email)<=200)run('INSERT OR IGNORE INTO subscribers(email,created_at) VALUES (?,?)',[$email,date('c')]);
 redirect('/?subscribed=1#newsletter');
}

$join='SELECT r.*,c.name AS category,c.slug AS category_slug FROM reviews r JOIN categories c ON c.id=r.category_id ';
$cats=categories();
if ($page==='review') {
 $review=query($join.'WHERE r.slug=? AND '.(isset($_SESSION['admin'],$_GET['preview'])?"r.status!='trash'":live()),[(string)($_GET['slug']??'')])[0]??null;
 if(!$review) { http_response_code(404); $page='404'; }
}
$valid=['home','review','reviews','top10','categories','about','privacy','compare','404'];
if(!in_array($page,$valid,true)){http_response_code(404);$page='404';}
$titles=['home'=>'','reviews'=>'Reviews','top10'=>'Top 10 Lists','categories'=>'Categories','about'=>'About','privacy'=>'Privacy','compare'=>'Compare','404'=>'Page not found'];
headerView($page==='review'?($review['meta_title']?:$review['title']):$titles[$page],$page==='review'?($review['meta_description']?:$review['excerpt']):'',$page==='review'?'reviews':$page);
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
  <div class="popular"><span>Popular:</span><a href="/reviews?q=headphones">Headphones</a><a href="/category/travel">Travel essentials</a><a href="/category/home-improvement">Home upgrades</a><a href="/category/tech">Tech</a></div>
 </div>
 <div class="hero-media" data-slider>
  <span class="hero-tag"><?= ficon('star','ic ic-sm') ?> Thoughtfully Compared</span>
  <div class="slide is-active"><img src="/assets/hero.jpg" alt="Headphones on a warm neutral background" fetchpriority="high"><div class="slide-card"><p class="eyebrow">Featured review</p><h2>Find your next favourite.</h2><p>Expert reviews, real research, honest opinions.</p><a class="link-arrow" href="/reviews">Explore reviews <?= ficon('arrow','ic ic-sm') ?></a></div></div>
  <?php foreach($slides as $s): ?><div class="slide" hidden><img src="<?= e($s['image']) ?>" alt="" loading="lazy"><div class="slide-card"><p class="eyebrow"><?= e($s['category']) ?></p><h2><?= e($s['title']) ?></h2><p><?= e(mb_strimwidth($s['excerpt'],0,90,'…')) ?></p><a class="link-arrow" href="<?= e(reviewUrl($s)) ?>">Read <?= $s['score']>0?'review':'article' ?> <?= ficon('arrow','ic ic-sm') ?></a></div></div><?php endforeach ?>
  <div class="slider-ui"><div class="dots"><?php for($i=0;$i<=count($slides);$i++): ?><button type="button" class="<?= $i?'':'is-active' ?>" data-slide="<?= $i ?>" aria-label="Show slide <?= $i+1 ?>"></button><?php endfor ?></div><button type="button" class="round-btn" data-prev aria-label="Previous slide"><?= ficon('left','ic ic-sm') ?></button><button type="button" class="round-btn" data-next aria-label="Next slide"><?= ficon('right','ic ic-sm') ?></button></div>
 </div>
</div></section>

<section class="wrap"><nav class="cat-strip" aria-label="Browse categories">
 <?php foreach(array_slice($cats,0,9) as $c): [$ic,$tone]=catStyle($c); ?><a href="/category/<?= e($c['slug']) ?>"><span class="cat-dot tone-<?= $tone ?>"><?= ficon($ic) ?></span><?= e($c['name']) ?></a><?php endforeach ?>
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
 <div class="interest-grid"><?php foreach($cats as $c): [$ic,$tone]=catStyle($c); ?><a class="interest" href="/category/<?= e($c['slug']) ?>"><span class="cat-dot cat-dot-lg tone-<?= $tone ?>"><?= ficon($ic) ?></span><span><b><?= e($c['name']) ?></b><small><?= (int)$c['total'] ?> review<?= (int)$c['total']===1?'':'s' ?></small></span><?= ficon('right','ic ic-sm chev') ?></a><?php endforeach ?></div>
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
 $results=query($join.$where.' ORDER BY '.$order.($page==='top10'?' LIMIT 10':''),$params);
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
 <h1 class="page-title"><?= e($current?($page==='top10'?'Top 10 in '.$current['name']:$current['name']):($page==='top10'?'The top 10 edit':($q!==''?'Results for “'.$q.'”':'Explore our reviews'))) ?></h1>
 <p class="section-sub"><?= $page==='top10'?'Our ten highest-rated and most recent picks, ranked.':'Useful details, honest trade-offs and clear comparisons, all in one place.' ?></p>
 <form class="filter-bar" action="<?= pagePath($page) ?>">
  <label class="field-ic"><?= ficon('search') ?><input name="q" value="<?= e($q) ?>" placeholder="Search products, ideas and more" aria-label="Search"></label>
  <label class="field-ic"><?= ficon('grid') ?><select name="category" aria-label="Category"><option value="">All categories</option><?php foreach($cats as $c): ?><option value="<?= e($c['slug']) ?>" <?= $cat===$c['slug']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach ?></select></label>
  <?php if($page==='reviews'): ?><label class="field-ic"><?= ficon('clock') ?><select name="sort" aria-label="Sort by"><option value="latest">Latest</option><option value="score" <?= $sort==='score'?'selected':'' ?>>Highest rated</option><option value="az" <?= $sort==='az'?'selected':'' ?>>A–Z</option></select></label><?php endif ?>
  <button class="btn btn-primary">Apply</button>
 </form>
 <div class="chip-row"><a class="<?= $cat===''?'is-active':'' ?>" href="<?= e($listUrl('')) ?>">All</a><?php foreach($cats as $c): ?><a class="<?= $cat===$c['slug']?'is-active':'' ?>" href="<?= e($listUrl($c['slug'])) ?>"><?= e($c['name']) ?></a><?php endforeach ?></div>
</div></section>
<section class="wrap section section-tight">
 <div class="result-bar"><span><?= count($results) ?> <?= count($results)===1?'result':'results' ?></span><a class="link-arrow" href="/compare?category=<?= e($cat) ?>">Compare reviews <?= ficon('arrow','ic ic-sm') ?></a></div>
 <?php if(!$results): ?><div class="empty"><span class="cat-dot cat-dot-lg tone-teal"><?= ficon('search') ?></span><h2>No reviews found</h2><p>Try another keyword or explore a different category.</p><a class="btn btn-primary" href="<?= pagePath($page) ?>">Clear filters</a></div>
 <?php elseif($page==='top10'): ?><ol class="rank-list"><?php foreach($results as $i=>$r): ?><li class="rank"><span class="rank-num"><?= $i+1 ?></span><a class="rank-img" href="<?= e(reviewUrl($r)) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e($r['image']) ?>" alt="" loading="lazy"></a><div class="rank-body"><span class="badge badge-<?= catStyle(['slug'=>$r['category_slug'],'id'=>$r['category_id']])[1] ?>"><?= e($r['category']) ?></span><h2><a href="<?= e(reviewUrl($r)) ?>"><?= e($r['title']) ?></a></h2><p><?= e($r['excerpt']) ?></p></div><div class="rank-side"><?php if($r['score']>0): ?><span class="score-big"><?= number_format((float)$r['score'],1) ?><small>/10</small></span><?php endif ?><a class="btn btn-outline" href="<?= e(reviewUrl($r)) ?>">Read <?= ficon('arrow','ic ic-sm') ?></a></div></li><?php endforeach ?></ol>
 <?php else: ?><div class="card-grid"><?php foreach($results as $r) reviewCard($r); ?></div><?php endif ?>
</section>

<?php elseif($page==='review'): $r=$review; $tone=catStyle(['slug'=>$r['category_slug'],'id'=>$r['category_id']])[1];
 preg_match_all('/^##\s+(.+)$/m',$r['body'],$m); $toc=array_map(fn($h)=>trim(str_replace('**','',preg_replace('~\[([^\]]+)\]\([^)]*\)~','$1',$h))),$m[1]);
 $shareUrl='https://'.($_SERVER['HTTP_HOST']??'besttop10things.com').reviewUrl($r);
 $share=['facebook'=>['Facebook','https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($shareUrl)],'x'=>['X','https://twitter.com/intent/tweet?url='.rawurlencode($shareUrl).'&text='.rawurlencode($r['title'])],'pinterest'=>['Pinterest','https://pinterest.com/pin/create/button/?url='.rawurlencode($shareUrl).'&description='.rawurlencode($r['title'])],'linkedin'=>['LinkedIn','https://www.linkedin.com/sharing/share-offsite/?url='.rawurlencode($shareUrl)]];
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
    <span class="avatar-lg"><?= e(strtoupper(substr($r['author'],0,1))) ?></span>
    <div><b><?= e($r['author']) ?></b><span class="meta"><?= e(date('M j, Y',strtotime($r['published_at']??$r['created_at']))) ?><i>•</i><?= readMinutes($r['body']) ?> min read<?php if(substr($r['updated_at'],0,10)!==substr((string)$r['published_at'],0,10)): ?><i>•</i>Updated <?= e(date('M j, Y',strtotime($r['updated_at']))) ?><?php endif ?></span></div>
    <div class="share"><button type="button" class="share-btn" data-copy-link="<?= e($shareUrl) ?>" aria-label="Copy link" title="Copy link"><?= ficon('link','ic ic-sm') ?></button><?php foreach($share as $k=>[$label,$url]): ?><a class="share-btn share-<?= $k ?>" href="<?= e($url) ?>" target="_blank" rel="noopener" aria-label="Share on <?= $label ?>" title="Share on <?= $label ?>"><?= ficon($k,'ic ic-sm') ?></a><?php endforeach ?></div>
   </div>
   <img class="post-img" src="<?= e($r['image']) ?>" alt="<?= e($r['title']) ?>">
   <?php if($r['demo']): ?><p class="note note-amber">This is a sample review. Images, ratings and observations demonstrate the website and do not represent a verified product test.</p><?php endif ?>
   <div class="prose post-body"><?= renderBody($r['body']) ?></div>
   <?php if($hasCta): ?><div class="cta-band"><div><p class="eyebrow">Ready to explore?</p><h2><?= e($r['brand']) ?></h2><?php if($r['brand_about']!==''): ?><p><?= e($r['brand_about']) ?></p><?php endif ?></div><a class="btn btn-primary" href="<?= e($r['cta_url']) ?>" target="_blank" rel="sponsored nofollow noopener">Visit <?= e($r['brand']) ?> <?= ficon('external','ic ic-sm') ?></a></div><?php endif ?>
   <p class="disclose-line"><?= ficon('doc','ic ic-sm') ?> This article may contain affiliate links. We may earn a commission if you buy through them, at no extra cost to you.</p>
  </div>
  <aside class="post-side">
   <?php if($r['score']>0): ?><div class="side-card verdict"><p class="side-title"><span class="side-ic"><?= ficon('star','ic ic-sm') ?></span>Our verdict</p><p class="score-big"><?= number_format((float)$r['score'],1) ?><small>/10</small></p><p class="side-text"><?= e($r['verdict']) ?></p>
    <?php if(trim($r['pros'])!==''): ?><h3>What we like</h3><ul class="pc pc-pro"><?php foreach(array_filter(explode("\n",$r['pros'])) as $p): ?><li><?= ficon('check','ic ic-sm') ?><?= e($p) ?></li><?php endforeach ?></ul><?php endif ?>
    <?php if(trim($r['cons'])!==''): ?><h3>What could be better</h3><ul class="pc pc-con"><?php foreach(array_filter(explode("\n",$r['cons'])) as $p): ?><li><?= ficon('minus','ic ic-sm') ?><?= e($p) ?></li><?php endforeach ?></ul><?php endif ?>
    <a class="btn btn-outline btn-block" href="/compare?category=<?= e($r['category_slug']) ?>">Compare options <?= ficon('arrow','ic ic-sm') ?></a></div><?php endif ?>
   <?php if(count($toc)>1||$hasCta): ?><nav class="side-card toc" aria-label="In this article"><p class="side-title"><span class="side-ic"><?= ficon('listnum','ic ic-sm') ?></span>In this article</p>
    <?php if(count($toc)>1): ?><ol><?php foreach($toc as $h): ?><li><a href="#<?= e(slug($h)) ?>" data-toc-link><?= e($h) ?></a></li><?php endforeach ?></ol><?php endif ?>
    <?php if($hasCta): ?><a class="btn btn-primary btn-block" href="<?= e($r['cta_url']) ?>" target="_blank" rel="sponsored nofollow noopener">Shop on <?= e($r['brand']) ?> <?= ficon('arrow','ic ic-sm') ?></a><?php endif ?></nav><?php endif ?>
   <?php if($takeaways): ?><div class="side-card"><p class="side-title"><span class="side-ic"><?= ficon('bulb','ic ic-sm') ?></span>Key Takeaways</p><ul class="pc pc-pro"><?php foreach($takeaways as $t): ?><li><?= ficon('check','ic ic-sm') ?><?= e($t) ?></li><?php endforeach ?></ul></div><?php endif ?>
   <?php if($hasCta): ?><div class="side-card brand-card"><div class="brand-row"><span class="brand-logo tone-<?= $tone ?>"><?= e(mb_strtoupper(mb_substr($r['brand'],0,1))) ?></span><div><p class="side-title">About <?= e($r['brand']) ?></p><?php if($r['brand_about']!==''): ?><p class="side-text"><?= e($r['brand_about']) ?></p><?php endif ?></div></div><a class="btn btn-primary btn-block" href="<?= e($r['cta_url']) ?>" target="_blank" rel="sponsored nofollow noopener">Visit <?= e($r['brand']) ?> <?= ficon('external','ic ic-sm') ?></a></div><?php endif ?>
   <?php if($related): ?><div class="side-card"><p class="side-title"><span class="side-ic"><?= ficon('related','ic ic-sm') ?></span>Related in <?= e($r['category']) ?></p><ul class="mini-list"><?php foreach($related as $x): ?><li><a href="<?= e(reviewUrl($x)) ?>"><img src="<?= e($x['image']) ?>" alt="" loading="lazy"><span><b><?= e($x['title']) ?></b><small><?= e(date('M j, Y',strtotime($x['published_at']??$x['created_at']))) ?> • <?= readMinutes($x['body']) ?> min read</small></span></a></li><?php endforeach ?></ul></div><?php endif ?>
  </aside>
 </div>
</article>
<section class="wrap section"><div class="section-head"><h2 class="section-title">Keep exploring</h2><a class="link-arrow" href="/reviews">View all articles <?= ficon('arrow','ic ic-sm') ?></a></div><div class="card-grid"><?php foreach($more as $x)reviewCard($x); ?></div></section>

<?php elseif($page==='compare'):
 $cat=(string)($_GET['category']??'');$comp=query($join.'WHERE '.live().' AND r.score>0'.($cat!==''?' AND c.slug=?':'').' ORDER BY r.score DESC LIMIT 3',$cat!==''?[$cat]:[]);
?>
<section class="page-hero"><div class="wrap">
 <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Home</a><?= ficon('right','ic ic-xs') ?><span>Compare</span></nav>
 <p class="eyebrow">Side by side</p><h1 class="page-title">A clearer comparison</h1><p class="section-sub">Compare the three highest-rated reviews in a category.</p>
 <form class="filter-bar" action="/compare"><label class="field-ic"><?= ficon('grid') ?><select name="category" aria-label="Category"><option value="">All categories</option><?php foreach($cats as $c): ?><option value="<?= e($c['slug']) ?>" <?= $cat===$c['slug']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach ?></select></label><button class="btn btn-primary">Compare</button></form>
</div></section>
<section class="wrap section section-tight">
 <?php if(!$comp): ?><div class="empty"><span class="cat-dot cat-dot-lg tone-teal"><?= ficon('scale') ?></span><h2>Nothing to compare yet</h2><p>There are no scored reviews in this category yet. Try another category or browse all reviews.</p><a class="btn btn-primary" href="/reviews<?= $cat!==''?'&category='.e($cat):'' ?>">Browse reviews</a></div>
 <?php else: ?><div class="compare-grid"><?php foreach($comp as $i=>$r): ?><article class="compare-card<?= $i===0?' is-top':'' ?>"><?php if($i===0): ?><span class="top-pick"><?= ficon('trophy','ic ic-sm') ?> Top pick</span><?php endif ?><img src="<?= e($r['image']) ?>" alt="" loading="lazy"><div class="compare-body"><span class="badge badge-<?= catStyle(['slug'=>$r['category_slug'],'id'=>$r['category_id']])[1] ?>"><?= e($r['category']) ?></span><h2><?= e($r['title']) ?></h2><p class="score-big"><?= number_format((float)$r['score'],1) ?><small>/10</small></p><h3>Pros</h3><ul class="pc pc-pro"><?php foreach(array_filter(explode("\n",$r['pros'])) as $p): ?><li><?= ficon('check','ic ic-sm') ?><?= e($p) ?></li><?php endforeach ?></ul><h3>Cons</h3><ul class="pc pc-con"><?php foreach(array_filter(explode("\n",$r['cons'])) as $p): ?><li><?= ficon('minus','ic ic-sm') ?><?= e($p) ?></li><?php endforeach ?></ul><a class="btn btn-primary btn-block" href="<?= e(reviewUrl($r)) ?>">Read full review</a></div></article><?php endforeach ?></div><?php endif ?>
</section>

<?php elseif($page==='categories'): ?>
<section class="page-hero"><div class="wrap">
 <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Home</a><?= ficon('right','ic ic-xs') ?><span>Categories</span></nav>
 <p class="eyebrow">Discover something useful</p><h1 class="page-title">Everyday interests. Explored.</h1><p class="section-sub">Find reviews and ideas in the categories that matter to you.</p>
</div></section>
<section class="wrap section section-tight"><div class="cat-cards"><?php foreach($cats as $c): [$ic,$tone]=catStyle($c); ?><a class="cat-card" href="/category/<?= e($c['slug']) ?>"><span class="cat-dot cat-dot-xl tone-<?= $tone ?>"><?= ficon($ic) ?></span><h2><?= e($c['name']) ?></h2><p><?= (int)$c['total'] ?> review<?= (int)$c['total']===1?'':'s' ?></p><span class="link-arrow">Explore <?= ficon('arrow','ic ic-sm') ?></span></a><?php endforeach ?></div></section>

<?php elseif($page==='about'||$page==='privacy'): ?>
<section class="page-hero"><div class="wrap narrow-wrap">
 <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Home</a><?= ficon('right','ic ic-xs') ?><span><?= $page==='about'?'About':'Privacy' ?></span></nav>
 <p class="eyebrow"><?= $page==='about'?'Behind the reviews':'Your information' ?></p><h1 class="page-title"><?= $page==='about'?'Clarity comes first.':'Privacy' ?></h1>
</div></section>
<section class="wrap narrow-wrap section section-tight"><div class="prose">
 <?php if($page==='about'): ?>
  <p><?= e(setting('site_name')) ?> is a place to explore product and service reviews, buying guides and top 10 lists across the things that make up everyday life.</p>
  <h2 id="how">How we review</h2>
  <div class="how-steps"><?php foreach([['doc','Research','Understand the features, the context and the choices.'],['scale','Compare','Look at the strengths and the trade-offs side by side.'],['bulb','Explain','Turn the details into practical, readable advice.']] as $i=>[$ic,$t,$d]): ?><div class="how-step"><span class="cat-dot cat-dot-lg tone-teal"><?= ficon($ic) ?></span><h3><small>0<?= $i+1 ?></small> <?= $t ?></h3><p><?= $d ?></p></div><?php endforeach ?></div>
  <p>Our editorial framework focuses on usefulness, ease of use, features and value. Reviews can include a score, strengths, limitations and a clear verdict, so readers can see the reasoning behind a recommendation.</p>
  <h2>Affiliate links</h2>
  <p>Some articles contain affiliate links. If you buy through them we may earn a commission, at no extra cost to you. This never changes how a product is described or scored.</p>
  <h2>Sample content</h2>
  <p>Content labelled as a sample is illustrative. Its scores and observations are not claims of hands-on testing.</p>
 <?php else: ?>
  <p>This website stores essential session cookies for secure CMS login and form protection. Public browsing does not require an account.</p>
  <p>If you subscribe to our newsletter, we store your email address so we can send you updates. You can ask us to remove it at any time.</p>
  <p>CMS account information and editorial content are stored in the site's database. Failed login attempts are temporarily recorded to limit repeated attempts. The site does not include analytics or advertising trackers by default.</p>
  <p>Images configured by editors may load from third-party HTTPS hosts. Those hosts receive the network information needed to serve an image. Website server logs may also record requests.</p>
 <?php endif ?>
</div></section>

<?php else: ?>
<section class="wrap section notfound"><span class="cat-dot cat-dot-xl tone-teal"><?= ficon('search') ?></span><p class="eyebrow">404</p><h1 class="page-title">This page took a different path.</h1><p class="section-sub">The page you are looking for doesn't exist or has moved.</p><div class="notfound-actions"><a class="btn btn-primary" href="/">Back to home</a><a class="btn btn-outline" href="/reviews">Browse reviews</a></div></section>
<?php endif ?>
</main>
<?php footerView(); ?>

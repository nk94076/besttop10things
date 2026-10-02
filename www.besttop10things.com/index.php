<?php
require __DIR__.'/../.besttop10-private/app/bootstrap.php';
require ROOT.'/app/layout.php';
$page=(string)($_GET['page']??'home');
$requestPath=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
if(!in_array($requestPath,['/','/index.php'],true)){http_response_code(404);$page='404';}

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
  <form action="/" class="hero-search" role="search"><input type="hidden" name="page" value="reviews"><?= ficon('search') ?><label for="hero-q" class="sr-only">Search reviews</label><input id="hero-q" name="q" placeholder="What are you looking for?"><button class="btn btn-primary" type="submit">Search <?= ficon('arrow','ic ic-sm') ?></button></form>
  <div class="popular"><span>Popular:</span><a href="/?page=reviews&q=headphones">Headphones</a><a href="/?page=reviews&category=travel">Travel essentials</a><a href="/?page=reviews&category=home-improvement">Home upgrades</a><a href="/?page=reviews&category=tech">Tech</a></div>
 </div>
 <div class="hero-media" data-slider>
  <span class="hero-tag"><?= ficon('star','ic ic-sm') ?> Thoughtfully Compared</span>
  <div class="slide is-active"><img src="/assets/hero.jpg" alt="Headphones on a warm neutral background" fetchpriority="high"><div class="slide-card"><p class="eyebrow">Featured review</p><h2>Find your next favourite.</h2><p>Expert reviews, real research, honest opinions.</p><a class="link-arrow" href="/?page=reviews">Explore reviews <?= ficon('arrow','ic ic-sm') ?></a></div></div>
  <?php foreach($slides as $s): ?><div class="slide" hidden><img src="<?= e($s['image']) ?>" alt="" loading="lazy"><div class="slide-card"><p class="eyebrow"><?= e($s['category']) ?></p><h2><?= e($s['title']) ?></h2><p><?= e(mb_strimwidth($s['excerpt'],0,90,'…')) ?></p><a class="link-arrow" href="<?= e(reviewUrl($s)) ?>">Read <?= $s['score']>0?'review':'article' ?> <?= ficon('arrow','ic ic-sm') ?></a></div></div><?php endforeach ?>
  <div class="slider-ui"><div class="dots"><?php for($i=0;$i<=count($slides);$i++): ?><button type="button" class="<?= $i?'':'is-active' ?>" data-slide="<?= $i ?>" aria-label="Show slide <?= $i+1 ?>"></button><?php endfor ?></div><button type="button" class="round-btn" data-prev aria-label="Previous slide"><?= ficon('left','ic ic-sm') ?></button><button type="button" class="round-btn" data-next aria-label="Next slide"><?= ficon('right','ic ic-sm') ?></button></div>
 </div>
</div></section>

<section class="wrap"><nav class="cat-strip" aria-label="Browse categories">
 <?php foreach(array_slice($cats,0,9) as $c): [$ic,$tone]=catStyle($c); ?><a href="/?page=reviews&category=<?= e($c['slug']) ?>"><span class="cat-dot tone-<?= $tone ?>"><?= ficon($ic) ?></span><?= e($c['name']) ?></a><?php endforeach ?>
 <a href="/?page=categories"><span class="cat-dot tone-slate"><?= ficon('dots') ?></span>More</a>
</nav></section>

<section class="wrap section">
 <div class="section-head"><div><p class="eyebrow">The latest word</p><h2 class="section-title">Reviews worth your time</h2><p class="section-sub">Honest reviews, practical advice, and top 10 lists to help you choose better.</p></div><a class="btn btn-outline" href="/?page=reviews">See all reviews <?= ficon('arrow','ic ic-sm') ?></a></div>
 <div class="card-grid"><?php foreach($latest as $r) reviewCard($r); ?></div>
</section>

<section class="wrap section">
 <div class="section-head"><div><p class="eyebrow">Something for every day</p><h2 class="section-title">Explore your interests</h2><p class="section-sub">Browse our top categories and find reviews tailored to your needs.</p></div><a class="btn btn-outline" href="/?page=categories">View all categories <?= ficon('arrow','ic ic-sm') ?></a></div>
 <div class="interest-grid"><?php foreach($cats as $c): [$ic,$tone]=catStyle($c); ?><a class="interest" href="/?page=reviews&category=<?= e($c['slug']) ?>"><span class="cat-dot cat-dot-lg tone-<?= $tone ?>"><?= ficon($ic) ?></span><span><b><?= e($c['name']) ?></b><small><?= (int)$c['total'] ?> review<?= (int)$c['total']===1?'':'s' ?></small></span><?= ficon('right','ic ic-sm chev') ?></a><?php endforeach ?></div>
</section>

<section class="wrap section"><div class="why">
 <div class="why-intro"><p class="eyebrow">Why choose us?</p><h2 class="section-title">Clarity comes first.</h2><p>A considered approach to every review.<br>We research, compare and explain so you can choose with confidence.</p><a class="btn btn-outline" href="/?page=about#how">How we review <?= ficon('arrow','ic ic-sm') ?></a></div>
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
?>
<section class="page-hero"><div class="wrap">
 <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Home</a><?= ficon('right','ic ic-xs') ?><?php if($current): ?><a href="/?page=<?= $page ?>"><?= $page==='top10'?'Top 10 Lists':'Reviews' ?></a><?= ficon('right','ic ic-xs') ?><span><?= e($current['name']) ?></span><?php else: ?><span><?= $page==='top10'?'Top 10 Lists':'Reviews' ?></span><?php endif ?></nav>
 <p class="eyebrow"><?= $page==='top10'?'The shortlist':'Find your next favourite' ?></p>
 <h1 class="page-title"><?= e($current?($page==='top10'?'Top 10 in '.$current['name']:$current['name']):($page==='top10'?'The top 10 edit':($q!==''?'Results for “'.$q.'”':'Explore our reviews'))) ?></h1>
 <p class="section-sub"><?= $page==='top10'?'Our ten highest-rated and most recent picks, ranked.':'Useful details, honest trade-offs and clear comparisons, all in one place.' ?></p>
 <form class="filter-bar" action="/"><input type="hidden" name="page" value="<?= e($page) ?>">
  <label class="field-ic"><?= ficon('search') ?><input name="q" value="<?= e($q) ?>" placeholder="Search products, ideas and more" aria-label="Search"></label>
  <label class="field-ic"><?= ficon('grid') ?><select name="category" aria-label="Category"><option value="">All categories</option><?php foreach($cats as $c): ?><option value="<?= e($c['slug']) ?>" <?= $cat===$c['slug']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach ?></select></label>
  <?php if($page==='reviews'): ?><label class="field-ic"><?= ficon('clock') ?><select name="sort" aria-label="Sort by"><option value="latest">Latest</option><option value="score" <?= $sort==='score'?'selected':'' ?>>Highest rated</option><option value="az" <?= $sort==='az'?'selected':'' ?>>A–Z</option></select></label><?php endif ?>
  <button class="btn btn-primary">Apply</button>
 </form>
 <div class="chip-row"><a class="<?= $cat===''?'is-active':'' ?>" href="/?page=<?= $page ?><?= $q!==''?'&q='.rawurlencode($q):'' ?>">All</a><?php foreach($cats as $c): ?><a class="<?= $cat===$c['slug']?'is-active':'' ?>" href="/?page=<?= $page ?>&category=<?= e($c['slug']) ?><?= $q!==''?'&q='.rawurlencode($q):'' ?>"><?= e($c['name']) ?></a><?php endforeach ?></div>
</div></section>
<section class="wrap section section-tight">
 <div class="result-bar"><span><?= count($results) ?> <?= count($results)===1?'result':'results' ?></span><a class="link-arrow" href="/?page=compare&category=<?= e($cat) ?>">Compare reviews <?= ficon('arrow','ic ic-sm') ?></a></div>
 <?php if(!$results): ?><div class="empty"><span class="cat-dot cat-dot-lg tone-teal"><?= ficon('search') ?></span><h2>No reviews found</h2><p>Try another keyword or explore a different category.</p><a class="btn btn-primary" href="/?page=<?= $page ?>">Clear filters</a></div>
 <?php elseif($page==='top10'): ?><ol class="rank-list"><?php foreach($results as $i=>$r): ?><li class="rank"><span class="rank-num"><?= $i+1 ?></span><a class="rank-img" href="<?= e(reviewUrl($r)) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e($r['image']) ?>" alt="" loading="lazy"></a><div class="rank-body"><span class="badge badge-<?= catStyle(['slug'=>$r['category_slug'],'id'=>$r['category_id']])[1] ?>"><?= e($r['category']) ?></span><h2><a href="<?= e(reviewUrl($r)) ?>"><?= e($r['title']) ?></a></h2><p><?= e($r['excerpt']) ?></p></div><div class="rank-side"><?php if($r['score']>0): ?><span class="score-big"><?= number_format((float)$r['score'],1) ?><small>/10</small></span><?php endif ?><a class="btn btn-outline" href="<?= e(reviewUrl($r)) ?>">Read <?= ficon('arrow','ic ic-sm') ?></a></div></li><?php endforeach ?></ol>
 <?php else: ?><div class="card-grid"><?php foreach($results as $r) reviewCard($r); ?></div><?php endif ?>
</section>

<?php elseif($page==='review'): $r=$review; $tone=catStyle(['slug'=>$r['category_slug'],'id'=>$r['category_id']])[1];
 preg_match_all('/^##\s+(.+)$/m',$r['body'],$m); $toc=array_map(fn($h)=>trim(str_replace('**','',preg_replace('~\[([^\]]+)\]\([^)]*\)~','$1',$h))),$m[1]);
?>
<article>
 <header class="article-hero"><div class="wrap article-head">
  <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Home</a><?= ficon('right','ic ic-xs') ?><a href="/?page=reviews&category=<?= e($r['category_slug']) ?>"><?= e($r['category']) ?></a><?= ficon('right','ic ic-xs') ?><span><?= $r['score']>0?'Review':'Article' ?></span></nav>
  <span class="badge badge-<?= $tone ?>"><?= e($r['category']) ?></span>
  <h1 class="article-title"><?= e($r['title']) ?></h1>
  <p class="article-dek"><?= e($r['excerpt']) ?></p>
  <p class="meta meta-lg"><span class="avatar-sm"><?= e(strtoupper(substr($r['author'],0,1))) ?></span><?= e($r['author']) ?><i>•</i>Updated <?= e(date('M j, Y',strtotime($r['updated_at']))) ?><i>•</i><?= readMinutes($r['body']) ?> min read</p>
 </div></header>
 <div class="wrap article-grid">
  <div class="article-main">
   <img class="article-img" src="<?= e($r['image']) ?>" alt="<?= e($r['title']) ?>">
   <?php if($r['demo']): ?><p class="note note-amber">This is a sample review. Images, ratings and observations demonstrate the website and do not represent a verified product test.</p><?php endif ?>
   <div class="prose"><?= renderBody($r['body']) ?></div>
  </div>
  <aside class="article-side">
   <?php if($r['score']>0): ?><div class="side-card verdict"><p class="eyebrow">Our verdict</p><p class="score-big"><?= number_format((float)$r['score'],1) ?><small>/10</small></p><p class="side-text"><?= e($r['verdict']) ?></p>
    <?php if(trim($r['pros'])!==''): ?><h3>What we like</h3><ul class="pc pc-pro"><?php foreach(array_filter(explode("\n",$r['pros'])) as $p): ?><li><?= ficon('check','ic ic-sm') ?><?= e($p) ?></li><?php endforeach ?></ul><?php endif ?>
    <?php if(trim($r['cons'])!==''): ?><h3>What could be better</h3><ul class="pc pc-con"><?php foreach(array_filter(explode("\n",$r['cons'])) as $p): ?><li><?= ficon('minus','ic ic-sm') ?><?= e($p) ?></li><?php endforeach ?></ul><?php endif ?>
    <a class="btn btn-primary btn-block" href="/?page=compare&category=<?= e($r['category_slug']) ?>">Compare options <?= ficon('arrow','ic ic-sm') ?></a></div>
   <?php endif ?>
   <?php if(count($toc)>1): ?><nav class="side-card toc" aria-label="In this article"><p class="eyebrow">In this article</p><ol><?php foreach($toc as $h): ?><li><a href="#<?= e(slug($h)) ?>"><?= e($h) ?></a></li><?php endforeach ?></ol></nav><?php endif ?>
   <div class="side-card disclosure"><p class="eyebrow">Disclosure</p><p class="side-text">This article may contain affiliate links. We may earn a commission if you make a purchase through them, at no extra cost to you.</p><a class="link-arrow" href="/?page=reviews&category=<?= e($r['category_slug']) ?>">More in <?= e($r['category']) ?> <?= ficon('arrow','ic ic-sm') ?></a></div>
  </aside>
 </div>
 <section class="wrap section"><div class="section-head"><div><p class="eyebrow">Keep exploring</p><h2 class="section-title">You might also like</h2></div></div><div class="card-grid"><?php foreach(query($join.'WHERE '.live().' AND r.id!=? ORDER BY (r.category_id=?) DESC,r.published_at DESC LIMIT 3',[$r['id'],$r['category_id']]) as $related)reviewCard($related); ?></div></section>
</article>

<?php elseif($page==='compare'):
 $cat=(string)($_GET['category']??'');$comp=query($join.'WHERE '.live().' AND r.score>0'.($cat!==''?' AND c.slug=?':'').' ORDER BY r.score DESC LIMIT 3',$cat!==''?[$cat]:[]);
?>
<section class="page-hero"><div class="wrap">
 <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Home</a><?= ficon('right','ic ic-xs') ?><span>Compare</span></nav>
 <p class="eyebrow">Side by side</p><h1 class="page-title">A clearer comparison</h1><p class="section-sub">Compare the three highest-rated reviews in a category.</p>
 <form class="filter-bar"><input type="hidden" name="page" value="compare"><label class="field-ic"><?= ficon('grid') ?><select name="category" aria-label="Category"><option value="">All categories</option><?php foreach($cats as $c): ?><option value="<?= e($c['slug']) ?>" <?= $cat===$c['slug']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach ?></select></label><button class="btn btn-primary">Compare</button></form>
</div></section>
<section class="wrap section section-tight">
 <?php if(!$comp): ?><div class="empty"><span class="cat-dot cat-dot-lg tone-teal"><?= ficon('scale') ?></span><h2>Nothing to compare yet</h2><p>There are no scored reviews in this category yet. Try another category or browse all reviews.</p><a class="btn btn-primary" href="/?page=reviews<?= $cat!==''?'&category='.e($cat):'' ?>">Browse reviews</a></div>
 <?php else: ?><div class="compare-grid"><?php foreach($comp as $i=>$r): ?><article class="compare-card<?= $i===0?' is-top':'' ?>"><?php if($i===0): ?><span class="top-pick"><?= ficon('trophy','ic ic-sm') ?> Top pick</span><?php endif ?><img src="<?= e($r['image']) ?>" alt="" loading="lazy"><div class="compare-body"><span class="badge badge-<?= catStyle(['slug'=>$r['category_slug'],'id'=>$r['category_id']])[1] ?>"><?= e($r['category']) ?></span><h2><?= e($r['title']) ?></h2><p class="score-big"><?= number_format((float)$r['score'],1) ?><small>/10</small></p><h3>Pros</h3><ul class="pc pc-pro"><?php foreach(array_filter(explode("\n",$r['pros'])) as $p): ?><li><?= ficon('check','ic ic-sm') ?><?= e($p) ?></li><?php endforeach ?></ul><h3>Cons</h3><ul class="pc pc-con"><?php foreach(array_filter(explode("\n",$r['cons'])) as $p): ?><li><?= ficon('minus','ic ic-sm') ?><?= e($p) ?></li><?php endforeach ?></ul><a class="btn btn-primary btn-block" href="<?= e(reviewUrl($r)) ?>">Read full review</a></div></article><?php endforeach ?></div><?php endif ?>
</section>

<?php elseif($page==='categories'): ?>
<section class="page-hero"><div class="wrap">
 <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Home</a><?= ficon('right','ic ic-xs') ?><span>Categories</span></nav>
 <p class="eyebrow">Discover something useful</p><h1 class="page-title">Everyday interests. Explored.</h1><p class="section-sub">Find reviews and ideas in the categories that matter to you.</p>
</div></section>
<section class="wrap section section-tight"><div class="cat-cards"><?php foreach($cats as $c): [$ic,$tone]=catStyle($c); ?><a class="cat-card" href="/?page=reviews&category=<?= e($c['slug']) ?>"><span class="cat-dot cat-dot-xl tone-<?= $tone ?>"><?= ficon($ic) ?></span><h2><?= e($c['name']) ?></h2><p><?= (int)$c['total'] ?> review<?= (int)$c['total']===1?'':'s' ?></p><span class="link-arrow">Explore <?= ficon('arrow','ic ic-sm') ?></span></a><?php endforeach ?></div></section>

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
<section class="wrap section notfound"><span class="cat-dot cat-dot-xl tone-teal"><?= ficon('search') ?></span><p class="eyebrow">404</p><h1 class="page-title">This page took a different path.</h1><p class="section-sub">The page you are looking for doesn't exist or has moved.</p><div class="notfound-actions"><a class="btn btn-primary" href="/">Back to home</a><a class="btn btn-outline" href="/?page=reviews">Browse reviews</a></div></section>
<?php endif ?>
</main>
<?php footerView(); ?>

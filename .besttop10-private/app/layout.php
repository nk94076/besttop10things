<?php
// With an uploaded logo only the image is shown (it already contains the name); otherwise the crown mark and site name.
function siteLogo(string $class='logo'): string {
 $logo=setting('logo');
 if($logo!==''&&safeImage($logo))return '<a href="/" class="'.e($class).' has-logo"><img class="logo-img" src="'.e($logo).'" alt="'.e(setting('site_name')).'"></a>';
 return '<a href="/" class="'.e($class).'"><span class="logo-mark">'.ficon('crown').'</span><span>'.e(setting('site_name')).'</span></a>';
}
function headerView(string $title='', string $description='', string $active='', array $seo=[]): void {
 $cats=categories();
 $site=setting('site_name');
 $metaTitle=$title!==''?$title.' | '.$site:(setting('seo_title')?:$site);
 $metaDesc=mb_strimwidth(trim(preg_replace('/\s+/',' ',$description?:setting('description'))),0,300,'…');
 $base=siteBase();
 $path=rtrim((string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH),'/')?:'/';
 $canonical=$base.($seo['canonical']??$path);
 $img=(string)($seo['image']??'');if($img===''||!safeImage($img))$img=setting('og_image');
 $img=$img!==''&&safeImage($img)?absUrl($img):'';
 $logo=setting('logo');$logo=absUrl($logo!==''&&safeImage($logo)?$logo:'/assets/favicon.svg');
 $lang=$seo['lang']??'en';
 $robots=$seo['robots']??'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
 // Structured data: organisation + website on every page, plus page-specific nodes.
 $graph=[
  ['@type'=>'Organization','@id'=>$base.'/#org','name'=>$site,'url'=>$base.'/','logo'=>['@type'=>'ImageObject','url'=>$logo],'description'=>setting('description')],
  ['@type'=>'WebSite','@id'=>$base.'/#website','url'=>$base.'/','name'=>$site,'description'=>setting('description'),'publisher'=>['@id'=>$base.'/#org'],'inLanguage'=>'en','potentialAction'=>['@type'=>'SearchAction','target'=>['@type'=>'EntryPoint','urlTemplate'=>$base.'/reviews?q={search_term_string}'],'query-input'=>'required name=search_term_string']],
 ];
 foreach($seo['jsonld']??[] as $node)$graph[]=$node;
 $ld=json_encode(['@context'=>'https://schema.org','@graph'=>$graph],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP); ?>
<!doctype html><html lang="<?= e($lang) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($metaTitle) ?></title><meta name="description" content="<?= e($metaDesc) ?>"><meta name="robots" content="<?= e($robots) ?>"><?php if(setting('meta_keywords')!==''): ?><meta name="keywords" content="<?= e(setting('meta_keywords')) ?>"><?php endif ?><?php if(setting('google_verification')!==''): ?><meta name="google-site-verification" content="<?= e(setting('google_verification')) ?>"><?php endif ?><?php if(setting('bing_verification')!==''): ?><meta name="msvalidate.01" content="<?= e(setting('bing_verification')) ?>"><?php endif ?>
<link rel="canonical" href="<?= e($canonical) ?>"><meta property="og:type" content="<?= e($seo['type']??'website') ?>"><meta property="og:site_name" content="<?= e($site) ?>"><meta property="og:locale" content="<?= e(['de'=>'de_DE','fr'=>'fr_FR'][$lang]??'en_US') ?>"><meta property="og:url" content="<?= e($canonical) ?>"><meta property="og:title" content="<?= e($title!==''?$title:$metaTitle) ?>"><meta property="og:description" content="<?= e($metaDesc) ?>"><?php if($img!==''): ?><meta property="og:image" content="<?= e($img) ?>"><meta property="og:image:alt" content="<?= e($title!==''?$title:$site) ?>"><meta name="twitter:image" content="<?= e($img) ?>"><?php endif ?><meta name="twitter:card" content="<?= $img!==''?'summary_large_image':'summary' ?>"><meta name="twitter:title" content="<?= e($title!==''?$title:$metaTitle) ?>"><meta name="twitter:description" content="<?= e($metaDesc) ?>">
<?php if(!empty($seo['published'])): ?><meta property="article:published_time" content="<?= e($seo['published']) ?>"><meta property="article:modified_time" content="<?= e($seo['modified']??$seo['published']) ?>"><?php if(!empty($seo['section'])): ?><meta property="article:section" content="<?= e($seo['section']) ?>"><?php endif ?><?php endif ?>
<link rel="alternate" type="application/rss+xml" title="<?= e($site) ?>" href="/feed.xml"><link rel="icon" href="<?= e(setting('logo')!==''&&safeImage(setting('logo'))?setting('logo'):'/assets/favicon.svg') ?>"><link rel="stylesheet" href="/assets/site.css"><script src="/assets/app.js" defer></script>
<?php $ga=setting('ga_id','G-Z6E5E0V0Q3'); if(preg_match('/^G-[A-Z0-9]{4,20}$/',$ga)&&!isset($_SESSION['admin'])): ?><script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($ga) ?>"></script><script src="/assets/ga.js" data-ga="<?= e($ga) ?>"></script><?php endif ?>
<script type="application/ld+json"><?= $ld ?></script></head><body>
<a class="skip" href="#main">Skip to content</a>
<header class="site-header"><div class="wrap header-row">
 <?= siteLogo() ?>
 <nav class="main-nav" aria-label="Main navigation">
  <?php foreach(siteMenu() as $item): $isActive=$active!==''&&$active!=='home'&&parse_url($item['url'],PHP_URL_PATH)===pagePath($active)&&!str_contains($item['url'],'#'); ?>
   <?php if(($item['type']??'link')==='categories'): ?><div class="nav-drop"><a href="<?= e($item['url']) ?>" class="<?= $isActive?'active':'' ?>"><?= e($item['label']) ?> <?= ficon('down','ic ic-xs') ?></a>
    <div class="drop-panel"><?php foreach($cats as $c): [$ic,$tone]=catStyle($c); ?><a href="/category/<?= e($c['slug']) ?>"><span class="cat-dot tone-<?= $tone ?>"><?= ficon($ic,'ic ic-sm') ?></span><?= e($c['name']) ?></a><?php endforeach ?><a class="drop-all" href="<?= e($item['url']) ?>">View all <?= ficon('arrow','ic ic-sm') ?></a></div></div>
   <?php else: ?><a href="<?= e($item['url']) ?>" class="<?= $isActive?'active':'' ?>"<?= str_starts_with($item['url'],'https://')?' target="_blank" rel="noopener"':'' ?>><?= e($item['label']) ?></a><?php endif ?>
  <?php endforeach ?>
 </nav>
 <div class="header-actions">
  <button type="button" class="icon-btn search-btn" data-search-open aria-label="Search" title="Search"><?= ficon('search') ?></button>
  <button type="button" class="icon-btn" data-theme-toggle aria-label="Toggle dark mode" title="Toggle dark mode"><?= ficon('sun') ?></button>
  <button type="button" class="icon-btn menu-btn" data-menu-toggle aria-expanded="false" aria-controls="mobile-menu" aria-label="Open menu"><?= ficon('menu') ?></button>
 </div>
</div>
<nav id="mobile-menu" class="mobile-menu" hidden aria-label="Mobile navigation"><div class="wrap">
 <?php foreach(siteMenu() as $item): ?><a href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a><?php endforeach ?>
</div></nav></header>
<div class="search-overlay" data-search-overlay hidden role="dialog" aria-modal="true" aria-label="Search">
 <div class="search-box">
  <button type="button" class="icon-btn search-close" data-search-close aria-label="Close search"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
  <p class="eyebrow">Search</p>
  <form class="search-big" action="/reviews" role="search"><?= ficon('search') ?><input name="q" placeholder="What are you looking for?" aria-label="Search reviews" data-search-input autocomplete="off"><button class="btn btn-primary">Search <?= ficon('arrow','ic ic-sm') ?></button></form>
  <p class="search-label">Popular searches</p>
  <div class="popular"><a href="/reviews?q=headphones">Headphones</a><a href="/reviews?q=laptop">Laptops</a><a href="/reviews?q=travel">Travel</a><a href="/reviews?q=shopping">Online shopping</a><a href="/top-10">Top 10 lists</a></div>
  <p class="search-label">Browse categories</p>
  <div class="search-cats"><?php foreach($cats as $c): [$ic,$tone]=catStyle($c); ?><a href="/category/<?= e($c['slug']) ?>"><span class="cat-dot tone-<?= $tone ?>"><?= ficon($ic,'ic ic-sm') ?></span><?= e($c['name']) ?></a><?php endforeach ?></div>
 </div>
</div>
<?php }

function footerView(): void { ?>
<section class="wrap" id="newsletter"><div class="newsletter">
 <span class="nl-icon"><?= ficon('mail') ?></span>
 <div class="nl-copy"><p class="eyebrow eyebrow-light">Stay updated</p><h2>Get the latest reviews and top 10 lists</h2><p>Subscribe to get new articles, buying guides and recommendations straight to your inbox.</p></div>
 <?php if(isset($_GET['subscribed'])): ?><p class="nl-done" role="status"><?= ficon('check') ?> Thanks! You're subscribed.</p>
 <?php else: ?><form class="nl-form" method="post" action="/"><?= csrfField() ?><input type="hidden" name="action" value="subscribe"><?= ficon('mail','ic nl-field-ic') ?><input type="email" name="email" required maxlength="200" placeholder="Your email address" aria-label="Your email address"><button class="btn btn-primary">Subscribe <?= ficon('arrow','ic ic-sm') ?></button></form><?php endif ?>
</div></section>
<footer class="site-footer"><div class="wrap footer-row">
 <div><?= siteLogo('logo logo-sm') ?><p class="footer-tag">A little more clarity. A better everyday choice.<br>Reviews and guides for the way you live.</p></div>
 <nav class="footer-links" aria-label="Footer"><a href="/categories">Explore categories</a><a href="/about#how">How we review</a><a href="/about">About</a><a href="/privacy">Privacy</a></nav>
</div><div class="wrap footer-bottom"><span>© <?= date('Y') ?> <?= e(setting('site_name')) ?>. All rights reserved.</span></div></footer>
</body></html>
<?php }

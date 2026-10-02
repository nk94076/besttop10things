<?php
// With an uploaded logo only the image is shown (it already contains the name); otherwise the crown mark and site name.
function siteLogo(string $class='logo'): string {
 $logo=setting('logo');
 if($logo!==''&&safeImage($logo))return '<a href="/" class="'.e($class).' has-logo"><img class="logo-img" src="'.e($logo).'" alt="'.e(setting('site_name')).'"></a>';
 return '<a href="/" class="'.e($class).'"><span class="logo-mark">'.ficon('crown').'</span><span>'.e(setting('site_name')).'</span></a>';
}
function headerView(string $title='', string $description='', string $active=''): void {
 $cats=categories();
 $metaTitle=$title!==''?$title.' | '.setting('site_name'):(setting('seo_title')?:setting('site_name'));
 $metaDesc=$description?:setting('description');
 $og=setting('og_image'); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($metaTitle) ?></title><meta name="description" content="<?= e($metaDesc) ?>"><?php if(setting('meta_keywords')!==''): ?><meta name="keywords" content="<?= e(setting('meta_keywords')) ?>"><?php endif ?><?php if(setting('google_verification')!==''): ?><meta name="google-site-verification" content="<?= e(setting('google_verification')) ?>"><?php endif ?><meta property="og:type" content="website"><meta property="og:site_name" content="<?= e(setting('site_name')) ?>"><meta property="og:title" content="<?= e($metaTitle) ?>"><meta property="og:description" content="<?= e($metaDesc) ?>"><?php if($og!==''&&safeImage($og)): ?><meta property="og:image" content="<?= e(str_starts_with($og,'/')?'https://'.($_SERVER['HTTP_HOST']??'').$og:$og) ?>"><meta name="twitter:card" content="summary_large_image"><?php endif ?><link rel="canonical" href="<?= e('https://'.($_SERVER['HTTP_HOST']??'').(rtrim((string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH),'/')?:'/')) ?>"><link rel="icon" href="<?= e(setting('logo')!==''&&safeImage(setting('logo'))?setting('logo'):'/assets/favicon.svg') ?>"><link rel="stylesheet" href="/assets/site.css"><script src="/assets/app.js" defer></script></head><body>
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

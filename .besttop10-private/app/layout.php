<?php
function headerView(string $title='', string $description='', string $active=''): void {
 $cats=categories();
 $nav=['reviews'=>'Reviews','top10'=>'Top 10 Lists','categories'=>'Categories','how'=>'How We Review','about'=>'About']; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e(($title ? $title.' | ' : '').setting('site_name')) ?></title><meta name="description" content="<?= e($description ?: setting('description')) ?>"><meta property="og:title" content="<?= e($title ?: setting('site_name')) ?>"><meta property="og:description" content="<?= e($description ?: setting('description')) ?>"><link rel="icon" href="/assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/site.css"><script src="/assets/app.js" defer></script></head><body>
<a class="skip" href="#main">Skip to content</a>
<header class="site-header"><div class="wrap header-row">
 <a href="/" class="logo"><span class="logo-mark"><?= ficon('crown') ?></span><span><?= e(setting('site_name')) ?></span></a>
 <nav class="main-nav" aria-label="Main navigation">
  <div class="nav-drop"><a href="/?page=reviews" class="<?= $active==='reviews'?'active':'' ?>">Reviews <?= ficon('down','ic ic-xs') ?></a>
   <div class="drop-panel"><?php foreach($cats as $c): [$ic,$tone]=catStyle($c); ?><a href="/?page=reviews&category=<?= e($c['slug']) ?>"><span class="cat-dot tone-<?= $tone ?>"><?= ficon($ic,'ic ic-sm') ?></span><?= e($c['name']) ?></a><?php endforeach ?><a class="drop-all" href="/?page=reviews">All reviews <?= ficon('arrow','ic ic-sm') ?></a></div></div>
  <a href="/?page=top10" class="<?= $active==='top10'?'active':'' ?>">Top 10 Lists</a>
  <a href="/?page=categories" class="<?= $active==='categories'?'active':'' ?>">Categories</a>
  <a href="/?page=about#how" class="<?= $active==='how'?'active':'' ?>">How We Review</a>
  <a href="/?page=about" class="<?= $active==='about'?'active':'' ?>">About</a>
 </nav>
 <form class="header-search" action="/" role="search"><input type="hidden" name="page" value="reviews"><?= ficon('search') ?><input name="q" placeholder="Search reviews, products..." aria-label="Search reviews"></form>
 <button type="button" class="icon-btn" data-theme-toggle aria-label="Toggle dark mode" title="Toggle dark mode"><?= ficon('sun') ?></button>
 <button type="button" class="icon-btn menu-btn" data-menu-toggle aria-expanded="false" aria-controls="mobile-menu" aria-label="Open menu"><?= ficon('menu') ?></button>
</div>
<nav id="mobile-menu" class="mobile-menu" hidden aria-label="Mobile navigation"><div class="wrap">
 <form class="mobile-search" action="/" role="search"><input type="hidden" name="page" value="reviews"><?= ficon('search') ?><input name="q" placeholder="Search reviews, products..." aria-label="Search reviews"></form>
 <?php foreach(['reviews'=>'/?page=reviews','top10'=>'/?page=top10','categories'=>'/?page=categories','how'=>'/?page=about#how','about'=>'/?page=about'] as $k=>$href): ?><a href="<?= $href ?>"><?= $nav[$k] ?></a><?php endforeach ?>
</div></nav></header>
<?php }

function footerView(): void { ?>
<section class="wrap" id="newsletter"><div class="newsletter">
 <span class="nl-icon"><?= ficon('mail') ?></span>
 <div class="nl-copy"><p class="eyebrow eyebrow-light">Stay updated</p><h2>Get the latest reviews and top 10 lists</h2><p>Subscribe to get new articles, buying guides and recommendations straight to your inbox.</p></div>
 <?php if(isset($_GET['subscribed'])): ?><p class="nl-done" role="status"><?= ficon('check') ?> Thanks! You're subscribed.</p>
 <?php else: ?><form class="nl-form" method="post" action="/"><?= csrfField() ?><input type="hidden" name="action" value="subscribe"><?= ficon('mail','ic nl-field-ic') ?><input type="email" name="email" required maxlength="200" placeholder="Your email address" aria-label="Your email address"><button class="btn btn-primary">Subscribe <?= ficon('arrow','ic ic-sm') ?></button></form><?php endif ?>
</div></section>
<footer class="site-footer"><div class="wrap footer-row">
 <div><a href="/" class="logo logo-sm"><span class="logo-mark"><?= ficon('crown') ?></span><span><?= e(setting('site_name')) ?></span></a><p class="footer-tag">A little more clarity. A better everyday choice.<br>Reviews and guides for the way you live.</p></div>
 <nav class="footer-links" aria-label="Footer"><a href="/?page=categories">Explore categories</a><a href="/?page=about#how">How we review</a><a href="/?page=about">About</a><a href="/?page=privacy">Privacy</a><a href="/admin.php">Editorial CMS</a></nav>
</div><div class="wrap footer-bottom"><span>© <?= date('Y') ?> <?= e(setting('site_name')) ?>. All rights reserved.</span><span>Some links are affiliate links. Scores are illustrative.</span></div></footer>
</body></html>
<?php }

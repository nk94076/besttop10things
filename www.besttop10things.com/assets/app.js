// Theme: remember light/dark per browser
const root = document.documentElement;
try { if (localStorage.getItem('site-theme') === 'dark') root.dataset.theme = 'dark'; } catch {}
document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => {
  const dark = root.dataset.theme !== 'dark';
  if (dark) root.dataset.theme = 'dark'; else delete root.dataset.theme;
  try { localStorage.setItem('site-theme', dark ? 'dark' : 'light'); } catch {}
});

// Mobile menu
const toggle = document.querySelector('[data-menu-toggle]');
toggle?.addEventListener('click', () => {
  const expanded = toggle.getAttribute('aria-expanded') === 'true';
  toggle.setAttribute('aria-expanded', String(!expanded));
  document.getElementById('mobile-menu').hidden = expanded;
});

document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => {
  if (!window.confirm(form.dataset.confirm)) event.preventDefault();
}));

// Hero slider
const slider = document.querySelector('[data-slider]');
if (slider) {
  const slides = [...slider.querySelectorAll('.slide')];
  const dots = [...slider.querySelectorAll('[data-slide]')];
  let current = 0, timer;
  const show = index => {
    current = (index + slides.length) % slides.length;
    slides.forEach((slide, i) => { slide.hidden = i !== current; slide.classList.toggle('is-active', i === current); });
    dots.forEach((dot, i) => dot.classList.toggle('is-active', i === current));
  };
  const restart = () => {
    clearInterval(timer);
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) timer = setInterval(() => show(current + 1), 6000);
  };
  slider.querySelector('[data-prev]')?.addEventListener('click', () => { show(current - 1); restart(); });
  slider.querySelector('[data-next]')?.addEventListener('click', () => { show(current + 1); restart(); });
  dots.forEach(dot => dot.addEventListener('click', () => { show(Number(dot.dataset.slide)); restart(); }));
  slider.addEventListener('mouseenter', () => clearInterval(timer));
  slider.addEventListener('mouseleave', restart);
  if (slides.length > 1) restart();
}

// Article: reading progress, copy link and active table-of-contents entry
const body = document.querySelector('.post-body');
if (body) {
  const bars = document.querySelectorAll('[data-progress-bar]');
  const label = document.querySelector('[data-progress-text]');
  const update = () => {
    const rect = body.getBoundingClientRect();
    const total = rect.height - window.innerHeight * 0.6;
    const pct = Math.round(Math.min(1, Math.max(0, -rect.top / Math.max(total, 1))) * 100);
    bars.forEach(bar => { bar.style.width = pct + '%'; });
    if (label) label.textContent = pct + '%';
  };
  window.addEventListener('scroll', update, { passive: true });
  update();

  const links = [...document.querySelectorAll('[data-toc-link]')];
  if (links.length && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        links.forEach(link => link.classList.toggle('is-active', link.getAttribute('href') === '#' + entry.target.id));
      });
    }, { rootMargin: '-90px 0px -70% 0px' });
    body.querySelectorAll('h2[id]').forEach(h => observer.observe(h));
  }
}
document.querySelectorAll('[data-copy-link]').forEach(button => button.addEventListener('click', async () => {
  try { await navigator.clipboard.writeText(button.dataset.copyLink); } catch { return; }
  button.classList.add('copied');
  setTimeout(() => button.classList.remove('copied'), 1500);
}));

// Full-screen search: open from the header button or with "/", close with Esc or the backdrop
const overlay = document.querySelector('[data-search-overlay]');
if (overlay) {
  const input = overlay.querySelector('[data-search-input]');
  let lastFocus = null;
  const open = () => {
    lastFocus = document.activeElement;
    overlay.hidden = false;
    document.body.classList.add('no-scroll');
    input.focus();
  };
  const close = () => {
    overlay.hidden = true;
    document.body.classList.remove('no-scroll');
    lastFocus?.focus();
  };
  document.querySelectorAll('[data-search-open]').forEach(button => button.addEventListener('click', open));
  overlay.querySelector('[data-search-close]').addEventListener('click', close);
  overlay.addEventListener('click', event => { if (event.target === overlay) close(); });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !overlay.hidden) close();
    if (event.key === '/' && overlay.hidden && !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) { event.preventDefault(); open(); }
  });
}

// Site analytics (first-party, see Admin › Analytics): page view, clicks, time on page and scroll depth.
(() => {
  if (!navigator.sendBeacon || /bot|crawl|spider|headless/i.test(navigator.userAgent)) return;
  const rnd = n => [...crypto.getRandomValues(new Uint8Array(n))].map(b => b.toString(16).padStart(2, '0')).join('');
  const pv = rnd(12);
  let s = ''; try { s = sessionStorage.getItem('bts') || rnd(8); sessionStorage.setItem('bts', s); } catch { s = rnd(8); }
  const send = data => navigator.sendBeacon('/t', JSON.stringify({ pv, ...data }));
  send({ k: 'pv', s, u: location.href, r: document.referrer, t: document.title, w: screen.width + 'x' + screen.height,
    l: navigator.language || '', z: (Intl.DateTimeFormat().resolvedOptions().timeZone || '') });
  let maxScroll = 0, visibleMs = 0, shownAt = document.visibilityState === 'visible' ? Date.now() : 0;
  addEventListener('scroll', () => {
    const h = document.documentElement.scrollHeight - innerHeight;
    maxScroll = Math.max(maxScroll, h > 0 ? Math.round(scrollY / h * 100) : 100);
  }, { passive: true });
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
      if (shownAt) visibleMs += Date.now() - shownAt;
      shownAt = 0;
      send({ k: 'end', sec: Math.round(visibleMs / 1000), sc: maxScroll });
    } else shownAt = Date.now();
  });
  document.addEventListener('click', event => {
    const el = event.target.closest('a[href], button');
    if (!el || el.closest('form[action="/admin.php"]')) return;
    const href = el.tagName === 'A' ? el.href : '';
    const kind = el.tagName === 'BUTTON' ? 'button' : /\/go\/\d+/.test(href) ? 'affiliate'
      : el.hasAttribute('download') || /\.(pdf|zip|csv|docx?|xlsx?)(\?|$)/i.test(href) ? 'download'
      : href && new URL(href, location.href).host !== location.host ? 'outbound' : 'link';
    const label = (el.getAttribute('aria-label') || el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 120);
    send({ k: 'click', c: kind, h: href.slice(0, 500), x: label });
  }, { capture: true });
})();

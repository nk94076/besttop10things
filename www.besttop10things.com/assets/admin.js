// Theme: remember light/dark per browser
const root = document.documentElement;
try { if (localStorage.getItem('cms-theme') === 'dark') root.dataset.theme = 'dark'; } catch {}
document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => {
  const dark = root.dataset.theme !== 'dark';
  if (dark) root.dataset.theme = 'dark'; else delete root.dataset.theme;
  try { localStorage.setItem('cms-theme', dark ? 'dark' : 'light'); } catch {}
});

// Close the "+ New" menu when clicking elsewhere
document.addEventListener('click', event => {
  document.querySelectorAll('details.new-menu[open], details.kebab[open]').forEach(menu => { if (!menu.contains(event.target)) menu.removeAttribute('open'); });
});

document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => {
  if (!window.confirm(form.dataset.confirm)) event.preventDefault();
}));

document.querySelectorAll('[data-side-toggle]').forEach(el => el.addEventListener('click', () => {
  const open = document.getElementById('cms-side').classList.toggle('open');
  document.querySelector('.cms-scrim')?.classList.toggle('open', open);
}));

// Posts list: select all + bulk action guard
document.querySelector('[data-check-all]')?.addEventListener('change', event => {
  document.querySelectorAll('input[name="ids[]"]').forEach(box => { box.checked = event.target.checked; });
});
document.querySelector('[data-bulk-apply]')?.addEventListener('click', event => {
  const form = event.target.form;
  if (form.bulk_action.value === 'delete' && !window.confirm('Delete the selected posts permanently?')) event.preventDefault();
});

// Media: click to copy URL
document.querySelectorAll('[data-copy]').forEach(input => input.addEventListener('click', () => {
  input.select();
  navigator.clipboard?.writeText(input.value);
}));

const editor = document.querySelector('[data-editor]');
if (editor) {
  const body = editor.querySelector('[data-body]');
  const preview = editor.querySelector('[data-preview]');
  const words = editor.querySelector('[data-wordcount]');
  let dirty = false;

  const countWords = () => {
    const n = body.value.trim() ? body.value.trim().split(/\s+/).length : 0;
    words.textContent = `${n} word${n === 1 ? '' : 's'} · ~${Math.max(1, Math.round(n / 220))} min read`;
  };
  countWords();

  // Wrap or prefix the current selection with markdown
  const insert = (before, after = '', placeholder = '') => {
    const start = body.selectionStart, end = body.selectionEnd;
    const selected = body.value.slice(start, end) || placeholder;
    body.setRangeText(before + selected + after, start, end, 'end');
    body.focus();
    body.dispatchEvent(new Event('input'));
  };
  const linePrefix = prefix => {
    const start = body.value.lastIndexOf('\n', body.selectionStart - 1) + 1;
    const end = body.selectionEnd;
    const lines = body.value.slice(start, end).split('\n').map(l => prefix + l.replace(/^(#{2,4} |- )/, ''));
    body.setRangeText(lines.join('\n'), start, end, 'end');
    body.focus();
    body.dispatchEvent(new Event('input'));
  };
  editor.querySelectorAll('[data-md]').forEach(button => button.addEventListener('click', () => {
    const kind = button.dataset.md;
    if (kind === 'bold') insert('**', '**', 'bold text');
    if (kind === 'h2') linePrefix('## ');
    if (kind === 'h3') linePrefix('### ');
    if (kind === 'list') linePrefix('- ');
    if (kind === 'link') {
      const url = window.prompt('Link URL (https://…)', 'https://');
      if (url && url !== 'https://') insert('[', `](${url})`, 'link text');
    }
    if (kind === 'image') {
      const url = window.prompt('Image URL (copy it from the Media Library)', '/uploads/');
      if (url && url !== '/uploads/') insert('\n\n![', `](${url})\n\n`, 'image description');
    }
  }));

  // Write / Preview tabs
  editor.querySelectorAll('[data-tab]').forEach(tab => tab.addEventListener('click', async () => {
    editor.querySelectorAll('[data-tab]').forEach(t => t.classList.toggle('active', t === tab));
    const showPreview = tab.dataset.tab === 'preview';
    body.classList.toggle('hidden', showPreview);
    preview.classList.toggle('hidden', !showPreview);
    if (!showPreview) return;
    preview.textContent = 'Loading preview…';
    const data = new FormData();
    data.append('action', 'preview');
    data.append('csrf', editor.csrf.value);
    data.append('body', body.value);
    try {
      const response = await fetch('/admin.php', { method: 'POST', body: data, credentials: 'same-origin' });
      preview.innerHTML = response.ok ? await response.text() : '<p>Preview unavailable. Your session may have expired.</p>';
    } catch {
      preview.textContent = 'Preview unavailable.';
    }
  }));

  // Slug from title until the slug is edited by hand
  const title = editor.querySelector('[data-title]');
  const slug = editor.querySelector('[data-slug]');
  let slugTouched = slug.value !== '';
  slug.addEventListener('input', () => { slugTouched = true; });
  title.addEventListener('input', () => {
    if (!slugTouched) slug.value = title.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  });

  // SEO counters and snippet preview
  const serpTitle = editor.querySelector('[data-serp-title]');
  const serpDesc = editor.querySelector('[data-serp-desc]');
  const updateSeo = () => {
    editor.querySelectorAll('[data-count]').forEach(field => {
      const label = editor.querySelector(`[data-count-for="${field.name}"]`);
      const n = field.value.length, max = Number(field.dataset.count);
      label.textContent = `${n}/${max}`;
      label.classList.toggle('over', n > max);
    });
    serpTitle.textContent = editor.meta_title.value || title.value || 'Post title';
    serpDesc.textContent = editor.meta_description.value || editor.excerpt.value || '';
  };
  ['input', 'change'].forEach(type => editor.addEventListener(type, updateSeo));
  updateSeo();

  // Live SEO and AI-readiness checks (Yoast/Rank Math style)
  const renderChecks = (list, scoreEl, checks) => {
    list.replaceChildren(...checks.map(([state, text]) => {
      const li = document.createElement('li');
      li.className = state; li.textContent = text; return li;
    }));
    const pts = checks.reduce((n, [state]) => n + (state === 'pass' ? 1 : state === 'warn' ? 0.5 : 0), 0);
    const pct = Math.round(pts / checks.length * 100);
    scoreEl.textContent = `${pct}/100`;
    scoreEl.className = 'seo-score ' + (pct >= 75 ? 'good' : pct >= 50 ? 'ok' : 'bad');
  };
  const analyse = () => {
    const text = body.value, lower = text.toLowerCase();
    const plain = text.replace(/!?\[([^\]]*)\]\([^)]*\)/g, '$1').replace(/[#*>`_-]/g, ' ');
    const words = plain.trim() ? plain.trim().split(/\s+/) : [];
    const h2s = [...text.matchAll(/^##\s+(.+)$/gm)].map(m => m[1].toLowerCase());
    const seoTitle = (editor.meta_title.value || title.value).trim();
    const desc = (editor.meta_description.value || editor.excerpt.value).trim();
    const kw = editor.focus_keyword.value.trim().toLowerCase();
    const has = s => kw !== '' && s.toLowerCase().includes(kw);
    const firstPara = words.slice(0, 120).join(' ').toLowerCase();
    const kwCount = kw ? lower.split(kw).length - 1 : 0;
    const density = words.length ? kwCount * kw.split(/\s+/).length / words.length * 100 : 0;
    const links = [...text.matchAll(/\]\(([^)]+)\)/g)].map(m => m[1]);
    const seo = [];
    seo.push(kw ? ['pass', `Focus keyword set: "${kw}"`] : ['fail', 'Add a focus keyword.']);
    if (kw) {
      seo.push(has(seoTitle) ? ['pass', 'Keyword appears in the SEO title.'] : ['fail', 'Use the keyword in the SEO title.']);
      seo.push(has(desc) ? ['pass', 'Keyword appears in the meta description.'] : ['fail', 'Use the keyword in the meta description.']);
      seo.push(slug.value.includes(kw.replace(/[^a-z0-9]+/g, '-')) ? ['pass', 'Keyword appears in the URL.'] : ['warn', 'Consider putting the keyword in the permalink.']);
      seo.push(firstPara.includes(kw) ? ['pass', 'Keyword appears in the introduction.'] : ['fail', 'Use the keyword in the first paragraph.']);
      seo.push(h2s.some(h => h.includes(kw)) ? ['pass', 'Keyword appears in a subheading.'] : ['warn', 'Use the keyword in at least one ## subheading.']);
      seo.push(density >= 0.5 && density <= 2.5 ? ['pass', `Keyword density ${density.toFixed(1)}% (good).`] : ['warn', `Keyword density ${density.toFixed(1)}% (aim for 0.5–2.5%).`]);
    }
    seo.push(seoTitle.length >= 30 && seoTitle.length <= 60 ? ['pass', `SEO title length ${seoTitle.length} (good).`] : ['warn', `SEO title is ${seoTitle.length} characters (aim for 30–60).`]);
    seo.push(desc.length >= 120 && desc.length <= 160 ? ['pass', `Meta description length ${desc.length} (good).`] : ['warn', `Meta description is ${desc.length} characters (aim for 120–160).`]);
    seo.push(words.length >= 600 ? ['pass', `${words.length} words.`] : ['warn', `${words.length} words; 600+ usually ranks better.`]);
    seo.push(h2s.length >= 3 ? ['pass', `${h2s.length} subheadings.`] : ['warn', 'Break the article into at least 3 ## sections.']);
    seo.push(links.some(u => u.startsWith('/')) ? ['pass', 'Has internal links.'] : ['warn', 'Link to at least one other article on this site (/slug).']);
    seo.push(links.some(u => u.startsWith('https://')) ? ['pass', 'Has outbound links.'] : ['warn', 'Add a link to a useful outside source.']);
    renderChecks(editor.querySelector('[data-seo-checks]'), editor.querySelector('[data-seo-score]'), seo);

    const tldr = editor.tldr.value.trim();
    const takeaways = editor.takeaways.value.split('\n').filter(l => l.trim()).length;
    const faq = /^##\s+(faqs?|frequently asked questions)/im.test(text) ? (text.split(/^##\s+(?:faqs?|frequently asked questions).*$/im)[1] || '').split(/^##\s/m)[0].match(/^\*\*.+\?\*\*\s*$/gm) || [] : [];
    const ai = [];
    ai.push(tldr.length >= 40 && tldr.length <= 320 ? ['pass', 'Quick answer present: AI tools can quote it directly.'] : tldr ? ['warn', 'Keep the quick answer to 1–3 sentences (40–320 characters).'] : ['fail', 'Add a quick answer / TL;DR.']);
    ai.push(takeaways >= 3 ? ['pass', `${takeaways} key takeaways.`] : ['warn', 'Add 3–5 key takeaways.']);
    ai.push(faq.length >= 3 ? ['pass', `FAQ section with ${faq.length} questions (FAQ schema added).`] : ['warn', 'Add a "## Frequently Asked Questions" section with 3+ **Question?** lines.']);
    ai.push(h2s.some(h => h.trim().endsWith('?') || /^(what|how|why|which|when|is|are|can|do|does|should)\b/.test(h)) ? ['pass', 'Uses question-style headings people (and AI) search for.'] : ['warn', 'Phrase some headings as questions ("How do I…?").']);
    ai.push(/^\s*(-|\d+\.)\s/m.test(text) ? ['pass', 'Uses lists that are easy to extract.'] : ['warn', 'Add a bulleted or numbered list.']);
    ai.push((plain.match(/\b\d[\d.,%]*\b/g) || []).length >= 3 ? ['pass', 'Includes specific numbers or facts.'] : ['warn', 'Add concrete numbers, prices, sizes or dates; AI answers favour specifics.']);
    ai.push(!h2s.length || words.length / h2s.length <= 350 ? ['pass', 'Sections are short and focused.'] : ['warn', 'Sections are long; add more ## subheadings (≈ every 300 words).']);
    ai.push(kw && firstPara.includes(kw) && words.length && /[.!?]/.test(words.slice(0, 60).join(' ')) ? ['pass', 'Opens with a direct answer.'] : ['warn', 'Answer the main question in the first 2–3 sentences.']);
    renderChecks(editor.querySelector('[data-ai-checks]'), editor.querySelector('[data-ai-score]'), ai);
  };
  let analyseTimer;
  ['input', 'change'].forEach(type => editor.addEventListener(type, () => { clearTimeout(analyseTimer); analyseTimer = setTimeout(analyse, 250); }));
  analyse();

  // "Schedule" label for future dates
  const pubdate = editor.querySelector('[data-pubdate]');
  const publishBtn = editor.querySelector('[data-publish-btn]');
  const updatePublish = () => {
    // Compare against server time (site timezone), not the browser clock
    const future = pubdate.value && pubdate.value > editor.dataset.now;
    publishBtn.textContent = future ? 'Schedule' : publishBtn.dataset.label;
  };
  pubdate.addEventListener('input', updatePublish);
  updatePublish();

  // Featured image preview
  const featPreview = editor.querySelector('[data-feat-preview]');
  const imageUrl = editor.querySelector('[data-image-url]');
  const showImage = src => { featPreview.src = src; featPreview.classList.remove('hidden'); };
  editor.querySelectorAll('input[name="image_pick"]').forEach(radio => radio.addEventListener('change', () => {
    imageUrl.value = radio.value;
    showImage(radio.value);
  }));
  editor.image_upload.addEventListener('change', () => {
    const file = editor.image_upload.files[0];
    if (file) showImage(URL.createObjectURL(file));
  });

  // Warn before leaving with unsaved changes
  editor.addEventListener('input', () => { dirty = true; countWords(); });
  editor.addEventListener('submit', () => { dirty = false; });
  window.addEventListener('beforeunload', event => { if (dirty) event.preventDefault(); });
}

// Appearance: header menu rows, library picks and SEO title counter
const appearance = document.querySelector('[data-appearance]');
if (appearance) {
  const rows = appearance.querySelector('[data-menu-rows]');
  const template = document.querySelector('[data-menu-template]');
  appearance.querySelector('[data-menu-add]').addEventListener('click', () => {
    rows.append(template.content.cloneNode(true));
    rows.lastElementChild.querySelector('input').focus();
  });
  rows.addEventListener('click', event => {
    const button = event.target.closest('button');
    if (!button) return;
    const row = button.closest('[data-menu-row]');
    if (button.hasAttribute('data-remove')) row.remove();
    if (button.dataset.move === '-1' && row.previousElementSibling) row.previousElementSibling.before(row);
    if (button.dataset.move === '1' && row.nextElementSibling) row.nextElementSibling.after(row);
  });
  appearance.querySelectorAll('[data-fill]').forEach(radio => radio.addEventListener('change', () => {
    appearance.querySelector(`input[name="${radio.dataset.fill}"]`).value = radio.value;
  }));
  const seo = appearance.querySelector('[data-count]');
  const label = appearance.querySelector(`[data-count-for="${seo.name}"]`);
  const count = () => { label.textContent = `${seo.value.length}/${seo.dataset.count}`; label.classList.toggle('over', seo.value.length > Number(seo.dataset.count)); };
  seo.addEventListener('input', count);
  count();
}

// Clicks: size the breakdown bars (inline styles are blocked by the CSP, CSSOM is not)
document.querySelectorAll('[data-w]').forEach(bar => { bar.style.width = Math.max(2, Number(bar.dataset.w)) + '%'; });

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

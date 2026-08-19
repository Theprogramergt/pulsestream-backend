(() => {
  const cfg = window.PS_CONFIG || {};
  const maxHistory = Number(cfg.maxHistory || 15);

  const el = {
    burger: document.getElementById('burger'),
    drawer: document.getElementById('drawer'),
    urlInput: document.getElementById('urlInput'),
    clearBtn: document.getElementById('clearBtn'),
    pasteBtn: document.getElementById('pasteBtn'),
    fetchBtn: document.getElementById('fetchBtn'),
    platformBadge: document.getElementById('platformBadge'),
    progress: document.getElementById('progress'),
    progressBar: document.getElementById('progressBar'),
    progressPct: document.getElementById('progressPct'),
    progressPhase: document.getElementById('progressPhase'),
    alert: document.getElementById('alert'),
    result: document.getElementById('result'),
    thumb: document.getElementById('thumb'),
    previewToggle: document.getElementById('previewToggle'),
    embedWrap: document.getElementById('embedWrap'),
    embed: document.getElementById('embed'),
    resultPlatform: document.getElementById('resultPlatform'),
    rTitle: document.getElementById('rTitle'),
    rAuthor: document.getElementById('rAuthor'),
    rDuration: document.getElementById('rDuration'),
    rViews: document.getElementById('rViews'),
    downloadBtn: document.getElementById('downloadBtn'),
    directLink: document.getElementById('directLink'),
    copyBtn: document.getElementById('copyBtn'),
    longNote: document.getElementById('longNote'),
    historyList: document.getElementById('historyList'),
    clearHistory: document.getElementById('clearHistory'),
    statChip: document.getElementById('statChip'),
  };

  const state = {
    media: null,
    format: 'mp4',
    quality: 'max',
    loading: false,
  };

  function normalizeUserUrl(value) {
    return String(value || '').replace(/\s+/g, ' ').trim();
  }

  function isHttpUrl(value) {
    try {
      const u = new URL(value);
      return u.protocol === 'http:' || u.protocol === 'https:';
    } catch {
      return false;
    }
  }

  function apiEndpoint(path) {
    try {
      return new URL(path, window.location.href).toString();
    } catch {
      return path;
    }
  }

  function showAlert(message, isError = true) {
    if (!el.alert) return;
    el.alert.hidden = false;
    el.alert.textContent = message;
    el.alert.style.borderColor = isError ? 'rgba(255,107,107,.45)' : 'rgba(125,255,212,.45)';
  }

  function clearAlert() {
    if (!el.alert) return;
    el.alert.hidden = true;
    el.alert.textContent = '';
  }

  function setProgress(active, pct = 0, phase = 'PROCESSING...') {
    if (!el.progress) return;
    el.progress.hidden = !active;
    if (!active) return;
    el.progressBar.style.width = `${Math.max(0, Math.min(100, pct))}%`;
    el.progressPct.textContent = `${Math.round(pct)}%`;
    el.progressPhase.textContent = phase;
  }

  function detectPlatform(url) {
    const x = String(url || '').toLowerCase();
    if (/youtube\.com|youtu\.be/.test(x)) return 'youtube';
    if (/tiktok\.com/.test(x)) return 'tiktok';
    if (/instagram\.com/.test(x)) return 'instagram';
    return null;
  }

  function setPlatformBadge(url) {
    const platform = detectPlatform(url);
    if (!platform) {
      el.platformBadge.hidden = true;
      el.platformBadge.textContent = '';
      el.platformBadge.removeAttribute('data-p');
      return;
    }
    el.platformBadge.hidden = false;
    el.platformBadge.textContent = platform.toUpperCase();
    el.platformBadge.setAttribute('data-p', platform);
  }

  function fmtNumber(v) {
    if (v == null || Number.isNaN(Number(v))) return '—';
    return Number(v).toLocaleString();
  }

  function q(params) {
    return new URLSearchParams(params).toString();
  }

  function currentDownloadUrl() {
    if (!state.media || !state.media.webpage_url) return '#';
    return `${apiEndpoint('api/download.php')}?${q({
      url: state.media.webpage_url,
      format: state.format,
      quality: state.quality,
    })}`;
  }

  function syncActionLinks() {
    const href = currentDownloadUrl();
    el.directLink.href = href;
    el.downloadBtn.disabled = !state.media;
  }

  function renderResult(data) {
    state.media = data;
    el.result.hidden = false;
    el.thumb.src = data.thumbnail || '';
    el.rTitle.textContent = data.title || 'Untitled media';
    el.rAuthor.textContent = data.author || 'Unknown';
    el.rDuration.textContent = data.duration_text || '—';
    el.rViews.textContent = fmtNumber(data.views);
    el.resultPlatform.textContent = String(data.platform || '').toUpperCase();

    if (data.embed_url) {
      el.embed.src = data.embed_url;
      el.previewToggle.hidden = false;
    } else {
      el.previewToggle.hidden = true;
      el.embedWrap.hidden = true;
      el.embed.src = '';
    }

    el.longNote.hidden = !data.is_long;
    syncActionLinks();
    saveHistory(data);
    renderHistory();
  }

  function historyRead() {
    try {
      const raw = localStorage.getItem('ps_history') || '[]';
      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  }

  function historyWrite(items) {
    localStorage.setItem('ps_history', JSON.stringify(items.slice(0, maxHistory)));
  }

  function saveHistory(item) {
    const items = historyRead().filter((x) => x && x.webpage_url !== item.webpage_url);
    items.unshift({
      title: item.title || 'Untitled media',
      author: item.author || 'Unknown',
      platform: item.platform || 'unknown',
      duration_text: item.duration_text || '—',
      webpage_url: item.webpage_url || '',
      thumbnail: item.thumbnail || '',
    });
    historyWrite(items);
  }

  function renderHistory() {
    if (!el.historyList) return;
    const items = historyRead();
    if (!items.length) {
      el.historyList.innerHTML = '<p class="muted empty">No downloads yet — your history will appear here.</p>';
      return;
    }

    el.historyList.innerHTML = items.map((x) => {
      const safeTitle = escapeHtml(x.title);
      const safeAuthor = escapeHtml(x.author);
      const safeDuration = escapeHtml(x.duration_text);
      const safePlatform = escapeHtml(String(x.platform || '').toUpperCase());
      const safeUrl = escapeAttr(x.webpage_url);
      const safeThumb = escapeAttr(x.thumbnail || '');
      return `
        <article class="history-item glass card">
          <img src="${safeThumb}" alt="thumbnail" loading="lazy">
          <div>
            <div class="mono-badge">${safePlatform}</div>
            <h3>${safeTitle}</h3>
            <p>${safeAuthor} • ${safeDuration}</p>
          </div>
          <button class="btn btn-ghost btn-sm" type="button" data-history-url="${safeUrl}">Use</button>
        </article>
      `;
    }).join('');
  }

  function escapeHtml(v) {
    return String(v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function escapeAttr(v) {
    return escapeHtml(v);
  }

  function setFormatQualityHints() {
    const isMp3 = state.format === 'mp3';
    document.querySelectorAll('.q-hint').forEach((node) => {
      if (!node) return;
      const p = node.parentElement?.dataset?.quality;
      if (!p) return;
      const text = isMp3
        ? (p === 'max' ? '320 kbps' : (p === 'standard' ? '192 kbps' : '128 kbps'))
        : (p === 'max' ? '1080p HD' : (p === 'standard' ? '480p' : '360p'));
      node.textContent = text;
    });
  }

  async function fetchInfo() {
    const url = normalizeUserUrl(el.urlInput.value);
    clearAlert();
    if (!url) {
      showAlert('Paste a media URL first.');
      return;
    }
    if (!isHttpUrl(url)) {
      showAlert('Use a full URL that starts with http:// or https://');
      return;
    }

    el.urlInput.value = url;

    state.loading = true;
    el.fetchBtn.disabled = true;
    setProgress(true, 12, 'CONTACTING EXTRACTOR...');

    try {
      setProgress(true, 35, 'FETCHING MEDIA METADATA...');
      const res = await fetch(apiEndpoint('api/info.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ url }),
      });
      const json = await res.json();
      if (!res.ok || !json.ok || !json.data) {
        throw new Error(json.error || 'Could not extract this media.');
      }

      setProgress(true, 86, 'PREPARING RESULT...');
      renderResult(json.data);
      showAlert('Media loaded. Choose format and quality, then download.', false);
      setProgress(true, 100, 'READY');
      window.setTimeout(() => setProgress(false), 450);
    } catch (err) {
      showAlert(err.message || 'Unknown extraction error.');
      setProgress(false);
    } finally {
      state.loading = false;
      el.fetchBtn.disabled = false;
    }
  }

  async function updateStatChip() {
    if (!el.statChip) return;
    try {
      const res = await fetch(apiEndpoint('api/stats.php'), { cache: 'no-store' });
      const json = await res.json();
      const count = Number(json?.data?.unique_visitors || 0);
      el.statChip.textContent = `${count.toLocaleString()} USERS TRIED`;
    } catch {
      el.statChip.textContent = 'LIVE NOW';
    }
  }

  function bind() {
    el.burger?.addEventListener('click', () => {
      const open = el.drawer.hidden;
      el.drawer.hidden = !open;
      el.burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    el.urlInput?.addEventListener('input', () => {
      const hasText = el.urlInput.value.trim().length > 0;
      el.clearBtn.hidden = !hasText;
      setPlatformBadge(el.urlInput.value);
    });

    el.clearBtn?.addEventListener('click', () => {
      el.urlInput.value = '';
      el.clearBtn.hidden = true;
      setPlatformBadge('');
      el.urlInput.focus();
    });

    el.pasteBtn?.addEventListener('click', async () => {
      try {
        const txt = await navigator.clipboard.readText();
        if (txt) {
          el.urlInput.value = txt.trim();
          el.clearBtn.hidden = !el.urlInput.value;
          setPlatformBadge(el.urlInput.value);
        }
      } catch {
        showAlert('Clipboard read blocked by browser permissions.');
      }
    });

    document.querySelectorAll('.sample').forEach((btn) => {
      btn.addEventListener('click', () => {
        const url = btn.getAttribute('data-url') || '';
        el.urlInput.value = url;
        el.clearBtn.hidden = !url;
        setPlatformBadge(url);
        fetchInfo();
      });
    });

    el.fetchBtn?.addEventListener('click', fetchInfo);
    el.urlInput?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') fetchInfo();
    });

    document.querySelectorAll('.seg-btn[data-format]').forEach((btn) => {
      btn.addEventListener('click', () => {
        state.format = btn.getAttribute('data-format') || 'mp4';
        document.querySelectorAll('.seg-btn[data-format]').forEach((b) => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        setFormatQualityHints();
        syncActionLinks();
      });
    });

    document.querySelectorAll('.seg-btn[data-quality]').forEach((btn) => {
      btn.addEventListener('click', () => {
        state.quality = btn.getAttribute('data-quality') || 'max';
        document.querySelectorAll('.seg-btn[data-quality]').forEach((b) => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        syncActionLinks();
      });
    });

    el.previewToggle?.addEventListener('click', () => {
      const opening = el.embedWrap.hidden;
      el.embedWrap.hidden = !opening;
      el.previewToggle.textContent = opening ? 'Hide player' : 'Preview player';
    });

    el.downloadBtn?.addEventListener('click', () => {
      if (!state.media) return;
      window.location.href = currentDownloadUrl();
    });

    el.directLink?.addEventListener('click', (e) => {
      if (!state.media) e.preventDefault();
    });

    el.copyBtn?.addEventListener('click', async () => {
      if (!state.media) return;
      try {
        await navigator.clipboard.writeText(currentDownloadUrl());
        showAlert('Direct download link copied.', false);
      } catch {
        showAlert('Clipboard write blocked by browser permissions.');
      }
    });

    el.clearHistory?.addEventListener('click', () => {
      localStorage.removeItem('ps_history');
      renderHistory();
    });

    el.historyList?.addEventListener('click', (e) => {
      const target = e.target;
      if (!(target instanceof HTMLElement)) return;
      const url = target.getAttribute('data-history-url');
      if (!url) return;
      el.urlInput.value = url;
      setPlatformBadge(url);
      el.clearBtn.hidden = !url;
      fetchInfo();
    });

    document.querySelectorAll('.reveal').forEach((node, idx) => {
      node.style.animationDelay = `${Math.min(idx * 90, 520)}ms`;
      node.classList.add('is-in');
    });
  }

  bind();
  renderHistory();
  setFormatQualityHints();
  syncActionLinks();
  updateStatChip();
})();

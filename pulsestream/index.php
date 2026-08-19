<?php
require_once __DIR__ . '/includes/db.php';
session_start();
ps_track_visit('/');
$longMs = LONG_JOB_TIMEOUT_MS;
$isAdmin = isset($_SESSION['ps_admin']) && $_SESSION['ps_admin'] === true;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PULSESTREAM — YouTube, TikTok &amp; Instagram Media Extractor</title>
<meta name="description" content="Extract MP4 video or 320 kbps MP3 audio from YouTube (4h+ podcasts), TikTok without watermark, and Instagram Reels & posts.">
<meta property="og:title" content="PULSESTREAM — High-Speed Media Extractor">
<meta property="og:description" content="MP4 up to 1080p or studio-master 320 kbps MP3. YouTube, TikTok (no watermark) and Instagram.">
<meta property="og:type" content="website">
<meta name="twitter:card" content="summary_large_image">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="grid-bg">
<div class="glow glow-a"></div>
<div class="glow glow-b"></div>
<div class="glow glow-c"></div>

<header class="nav">
  <div class="shell nav-inner">
    <a class="brand" href="index.php">
      <span class="brand-mark"><span class="brand-dot"></span></span>
      <span class="brand-text metallic">PULSE<span class="brand-thin">STREAM</span></span>
    </a>
    <nav class="nav-links" aria-label="Primary">
      <a href="#extract">Extract</a>
      <a href="#platforms">Platforms</a>
      <a href="#history">History</a>
      <a href="#legal">Legal</a>
      <?php if ($isAdmin): ?><a class="pill pill-accent" href="admin.php">Analytics</a><?php endif; ?>
    </nav>
    <button class="burger" id="burger" aria-label="Open menu" aria-expanded="false" aria-controls="drawer">
      <span></span><span></span><span></span>
    </button>
  </div>
  <div class="drawer" id="drawer" hidden>
    <a href="#extract">Extract</a>
    <a href="#platforms">Platforms</a>
    <a href="#history">History</a>
    <a href="#legal">Legal</a>
    <?php if ($isAdmin): ?><a href="admin.php">Analytics</a><?php endif; ?>
  </div>
</header>

<main class="shell">
  <!-- HERO -->
  <section class="hero reveal" id="extract">
    <h1 class="hero-title metallic">Rip audio &amp; video<br>at <span class="accent-text">pulse speed</span></h1>
    <p class="hero-sub">
      YouTube long-form (4h+ podcasts &amp; audiobooks), TikTok without watermark, Instagram Reels
      &amp; posts. MP4 up to 1080p or studio-master 320&nbsp;kbps MP3.
    </p>
    <div class="hero-chips">
      <span class="chip">4h+ SAFE STREAMING</span>
      <span class="chip">NO WATERMARK</span>
      <span class="chip">320 KBPS MASTER</span>
      <span class="chip" id="statChip">— USERS TRIED</span>
    </div>
  </section>

  <!-- EXTRACTOR CARD -->
  <section class="glass card card-hero reveal" aria-label="Media extractor">
    <div class="url-row">
      <div class="url-field">
        <svg class="url-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>
        <input id="urlInput" type="url" inputmode="url" autocomplete="off" spellcheck="false"
               placeholder="Paste a YouTube, TikTok or Instagram link…" aria-label="Media URL">
        <span class="platform-badge" id="platformBadge" hidden></span>
        <button class="icon-btn" id="clearBtn" type="button" title="Clear" hidden>&times;</button>
      </div>
      <button class="btn btn-ghost" id="pasteBtn" type="button">Paste</button>
      <button class="btn btn-primary" id="fetchBtn" type="button">
        <span class="btn-label">Fetch media</span>
      </button>
    </div>

    <div class="samples">
      <span class="mono-badge">QUICK TEST</span>
      <button class="pill sample" data-url="https://www.youtube.com/watch?v=dQw4w9WgXcQ">YouTube video</button>
      <button class="pill sample" data-url="https://www.tiktok.com/@tiktok/video/7106594312292453675">TikTok clip</button>
      <button class="pill sample" data-url="https://www.instagram.com/reel/CtjoC2BNsB2/">Instagram reel</button>
    </div>

    <div class="progress" id="progress" hidden>
      <div class="progress-head">
        <span class="mono-badge" id="progressPhase">EXTRACTING METADATA…</span>
        <span class="mono-badge" id="progressPct">0%</span>
      </div>
      <div class="progress-track"><div class="progress-bar" id="progressBar"></div></div>
    </div>

    <div class="alert" id="alert" hidden></div>

    <!-- RESULT -->
    <div class="result" id="result" hidden>
      <div class="result-grid">
        <div class="media-box">
          <img id="thumb" alt="Media thumbnail" loading="lazy">
          <button class="btn btn-ghost btn-sm preview-toggle" id="previewToggle" type="button">Preview player</button>
          <div class="embed-wrap" id="embedWrap" hidden><iframe id="embed" title="Media preview" allowfullscreen loading="lazy"></iframe></div>
        </div>
        <div class="meta">
          <span class="platform-badge" id="resultPlatform"></span>
          <h2 class="result-title" id="rTitle">—</h2>
          <div class="meta-rows">
            <div><span class="mono-badge">CHANNEL</span><span id="rAuthor">—</span></div>
            <div><span class="mono-badge">DURATION</span><span id="rDuration">—</span></div>
            <div><span class="mono-badge">VIEWS</span><span id="rViews">—</span></div>
          </div>

          <div class="control-block">
            <span class="mono-badge">FORMAT</span>
            <div class="seg" role="group" aria-label="Format">
              <button class="seg-btn is-active" data-format="mp4" type="button">MP4 Video<small>audio + video synced</small></button>
              <button class="seg-btn" data-format="mp3" type="button">MP3 Audio<small>studio master 320k</small></button>
            </div>
          </div>

          <div class="control-block">
            <span class="mono-badge">QUALITY</span>
            <div class="seg seg-3" role="group" aria-label="Quality">
              <button class="seg-btn is-active" data-quality="max" type="button">Maximum<small class="q-hint">1080p HD</small></button>
              <button class="seg-btn" data-quality="standard" type="button">Standard<small class="q-hint">480p</small></button>
              <button class="seg-btn" data-quality="compact" type="button">Compact<small class="q-hint">360p</small></button>
            </div>
          </div>

          <div class="actions">
            <button class="btn btn-primary btn-glow" id="downloadBtn" type="button">Download now</button>
            <a class="btn btn-ghost" id="directLink" href="#" target="_blank" rel="noopener">Direct URL</a>
            <button class="btn btn-ghost" id="copyBtn" type="button">Copy link</button>
          </div>
          <p class="fineprint" id="longNote" hidden>Long-form source detected — extended <?= (int) round($longMs / 3600000) ?>&nbsp;hour transfer window active, buffered streaming enabled.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- PLATFORMS -->
  <section class="section reveal" id="platforms">
    <h2 class="section-title metallic">Platform capabilities</h2>
    <p class="section-sub">Every source is handled by a dedicated extraction profile.</p>
    <div class="feature-grid">
      <article class="glass card feature" data-accent="youtube">
        <span class="tag tag-youtube">YOUTUBE</span>
        <h3>Long-form &amp; podcasts</h3>
        <ul>
          <li>4h+ videos, lectures and audiobooks without cutoff</li>
          <li>320 kbps MP3 extraction for podcast archiving</li>
          <li>1080p HD MP4 with perfectly synced audio</li>
          <li>Buffered chunk streaming, 4-hour transfer window</li>
        </ul>
      </article>
      <article class="glass card feature" data-accent="tiktok">
        <span class="tag tag-tiktok">TIKTOK</span>
        <h3>Clean HD, no watermark</h3>
        <ul>
          <li>Watermark-free source rendition</li>
          <li>Original vertical HD resolution kept intact</li>
          <li>Isolate the sound as MP3 in one tap</li>
          <li>Instant metadata: author, views, duration</li>
        </ul>
      </article>
      <article class="glass card feature" data-accent="instagram">
        <span class="tag tag-instagram">INSTAGRAM</span>
        <h3>Reels &amp; feed posts</h3>
        <ul>
          <li>Reels, video posts and carousels</li>
          <li>Audio-only export for reel sounds</li>
          <li>High-resolution cover thumbnail</li>
          <li>Public content only, fully compliant</li>
        </ul>
      </article>
    </div>
  </section>

  <!-- HISTORY -->
  <section class="section reveal" id="history">
    <div class="history-head">
      <div>
        <h2 class="section-title metallic">Local history</h2>
        <p class="section-sub">Your last <?= (int) MAX_HISTORY ?> extractions, stored only in this browser.</p>
      </div>
      <button class="btn btn-ghost btn-sm" id="clearHistory" type="button">Clear history</button>
    </div>
    <div class="history-list" id="historyList"><p class="muted empty">No downloads yet — your history will appear here.</p></div>
  </section>

  <!-- LEGAL -->
  <section class="section reveal" id="legal">
    <div class="glass card legal">
      <h2 class="card-title">Authorized use only</h2>
      <p>
        PULSESTREAM is provided strictly for downloading publicly available media that you own, that is
        released under a permissive licence, or that you have explicit authorization to archive. Use it for
        personal offline archiving, accessibility, research and backup of your own uploads.
      </p>
      <p>
        You are solely responsible for complying with copyright law and with the terms of service of each
        source platform. Redistribution, re-uploading or commercial exploitation of third-party content
        without permission is prohibited. No media is stored on this server — streams are relayed directly
        to your device.
      </p>
    </div>
  </section>
</main>

<footer class="footer">
  <div class="shell footer-inner">
    <span class="brand-text metallic">PULSESTREAM</span>
    <span class="footer-copy"><span class="footer-copyright">©</span> <span class="footer-name">Elias Bcheraui</span> <span class="footer-year"><?= date('Y') ?></span></span>
  </div>
</footer>

<script>window.PS_CONFIG = { longTimeoutMs: <?= (int) $longMs ?>, maxHistory: <?= (int) MAX_HISTORY ?> };</script>
<script src="assets/js/app.js?v=20260819-4" defer></script>
</body>
</html>

# PULSESTREAM — installation on XAMPP

Media extractor for YouTube (4h+ podcasts), TikTok (no watermark) and Instagram (Reels & posts).
Stack: HTML + CSS + vanilla JS front-end, PHP 8 back-end, MySQL/MariaDB analytics.

---

## 1. Copy the files

Unzip so you end up with:

```
C:\xampp\htdocs\pulsestream\
```

Start **Apache** and **MySQL** in the XAMPP Control Panel.

## 2. Create the database

1. Open http://localhost/phpmyadmin
2. Go to the **Import** tab
3. Choose `pulsestream/sql/pulsestream.sql` and press **Go**

This creates the `pulsestream` database with:

| Object | Purpose |
| --- | --- |
| `visits` | every page view + anonymous visitor id, device, referrer |
| `download_attempts` | every fetch / download / copy-link attempt with platform, format, quality, status |
| `daily_stats` | fast daily rollup (visits, fetches, downloads) |
| `v_traffic_overview` | one-row totals |
| `v_platform_breakdown` | per-platform usage |
| `v_last_30_days` | last 30 days trend |

If your MySQL root user has a password, set it in `config.php` (`DB_PASS`).

## 3. Install the extraction binaries

Download and drop these two files into `pulsestream/bin/`:

* `yt-dlp.exe` — https://github.com/yt-dlp/yt-dlp/releases (file `yt-dlp.exe`)
* `ffmpeg.exe` — https://www.gyan.dev/ffmpeg/builds/ (needed for MP3 conversion and MP4 muxing)

On Linux/macOS use the binaries named `yt-dlp` and `ffmpeg` (no `.exe`) and `chmod +x` them.

> Without these two binaries the UI loads but fetching returns
> "yt-dlp is not installed".

## InfinityFree setup (no shell_exec)

InfinityFree blocks command execution functions, so run extraction on a separate backend host (VPS, Render, Railway, your own server) and keep this project as the frontend + analytics site.

1. Deploy a second copy of PULSESTREAM on a host that allows `shell_exec` / `proc_open`, with `yt-dlp` + `ffmpeg` installed.
2. In this InfinityFree copy, edit `config.php` and set:

```php
define('REMOTE_EXTRACTOR_BASE_URL', 'https://your-backend.example.com/pulsestream');
define('REMOTE_INFO_PATH', '/api/info.php');
define('REMOTE_DOWNLOAD_PATH', '/api/download.php');
```

3. Leave `REMOTE_SHARED_TOKEN` empty unless your remote backend is configured to validate a header token.

When `REMOTE_EXTRACTOR_BASE_URL` is set, this InfinityFree app will:

* send metadata fetches (`/api/info.php`) to your remote backend
* redirect downloads (`/api/download.php`) to your remote backend
* keep local visit and attempt analytics in your InfinityFree MySQL database

## 4. Recommended PHP settings (long 4h+ videos)

In `C:\xampp\php\php.ini`:

```ini
max_execution_time = 0
memory_limit = 512M
output_buffering = Off
zlib.output_compression = Off
```

Restart Apache afterwards. The download endpoint also sets these at runtime and
streams in 512 KB chunks, so multi-hour podcasts and audiobooks are not cut off.

## 5. Open the app

* App: http://localhost/pulsestream/
* Analytics dashboard: http://localhost/pulsestream/admin.php

---

## Handy phpMyAdmin queries

```sql
-- How many people tried the website (unique)
SELECT COUNT(DISTINCT visitor_id) AS people_who_tried FROM visits;

-- Total page views
SELECT COUNT(*) FROM visits;

-- Tried today
SELECT COUNT(DISTINCT visitor_id) FROM visits WHERE DATE(created_at) = CURDATE();

-- Overview in one row
SELECT * FROM v_traffic_overview;

-- Which platform is used most
SELECT * FROM v_platform_breakdown;

-- Last 30 days trend
SELECT * FROM v_last_30_days;

-- Most requested media
SELECT title, platform, COUNT(*) c FROM download_attempts
GROUP BY title, platform ORDER BY c DESC LIMIT 20;

-- Mobile vs tablet vs desktop
SELECT device_type, COUNT(*) FROM visits GROUP BY device_type;

-- Failures to debug
SELECT created_at, platform, url, error_message FROM download_attempts
WHERE status='error' ORDER BY id DESC LIMIT 50;
```

## Files

```
index.php              main UI (hero, extractor, platforms, history, legal)
admin.php              analytics dashboard (delete or protect before going public)
config.php             DB credentials, binary paths, 4h timeout, chunk size
includes/db.php        PDO connection + visit/attempt tracking helpers
includes/extractor.php yt-dlp wrapper, metadata parsing, format selectors
api/info.php           POST { url } -> title, author, views, duration, thumbnail, embed
api/download.php       GET  url/format/quality -> streamed MP4 or MP3
api/track.php          analytics beacon
api/stats.php          public counters for the hero strip
assets/css/style.css   pitch-black cyber-tech design system
assets/js/app.js       UI logic, progress bar, localStorage history (15 items)
sql/pulsestream.sql    database schema + views + example queries
bin/                   put yt-dlp + ffmpeg here
downloads/             temp/log folder (must be writable)
```

## Legal

Use only for media you own or are authorized to archive. See the compliance
section at the bottom of the app.

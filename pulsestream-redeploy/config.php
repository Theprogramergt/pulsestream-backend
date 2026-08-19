<?php
/**
 * PULSESTREAM - configuration
 * Edit these values to match your XAMPP setup.
 */

// ---- Database (InfinityFree) ----
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'if0_42688073_pulsestream');
define('DB_USER', 'if0_42688073');
define('DB_PASS', 'B1gM2yHwiE9UxvL');

// ---- Binaries -------------------------------------------------------------
// Put yt-dlp.exe and ffmpeg.exe inside the /bin folder, or set absolute paths.
define('YTDLP_BIN', __DIR__ . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . (stripos(PHP_OS, 'WIN') === 0 ? 'yt-dlp.exe' : 'yt-dlp'));
define('FFMPEG_BIN', __DIR__ . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . (stripos(PHP_OS, 'WIN') === 0 ? 'ffmpeg.exe' : 'ffmpeg'));

// ---- Remote extractor backend (for shared hosts like InfinityFree) --------
// Leave empty to use local yt-dlp/ffmpeg execution.
// Example: https://your-vps.example.com/pulsestream
define('REMOTE_EXTRACTOR_BASE_URL', '');
define('REMOTE_INFO_PATH', '/api/info.php');
define('REMOTE_DOWNLOAD_PATH', '/api/download.php');

// Optional shared token sent to the remote backend on info requests.
// Keep empty if your backend does not require auth.
define('REMOTE_SHARED_TOKEN', '');
define('REMOTE_SHARED_TOKEN_HEADER', 'X-PulseStream-Token');

// ---- Long video support (4h+ podcasts / audiobooks) ----------------------
define('LONG_JOB_TIMEOUT_MS', 14400000);      // 4 hours in ms (used by JS + PHP)
define('STREAM_HIGH_WATER_MARK', 1024 * 512); // 512 KB chunks while streaming

// ---- Misc ---------------------------------------------------------------
define('TMP_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'downloads');
define('APP_NAME', 'PULSESTREAM');
define('MAX_HISTORY', 15);

// ---- Admin Authentication -------------------------------------------------
define('ADMIN_PASSWORD', 'herewegoELIAS()20'); // CHANGE THIS TO YOUR SECRET PASSWORD

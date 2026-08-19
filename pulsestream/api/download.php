<?php
/**
 * GET /api/download.php?url=...&format=mp4|mp3&quality=max|standard|compact
 *
 * Streams the media straight to the browser with a 512 KB high-water-mark
 * buffer and no execution time limit, so 4h+ YouTube podcasts and
 * audiobooks are never cut off.
 */
require_once __DIR__ . '/../includes/remote_backend.php';
require_once __DIR__ . '/../includes/extractor.php';

@ini_set('max_execution_time', '0');
@ini_set('memory_limit', '512M');
@ini_set('zlib.output_compression', '0');
@set_time_limit(0);
ignore_user_abort(false);

$url     = trim((string) ($_GET['url'] ?? ''));
$format  = ($_GET['format'] ?? 'mp4') === 'mp3' ? 'mp3' : 'mp4';
$quality = in_array($_GET['quality'] ?? '', ['max', 'standard', 'compact'], true) ? $_GET['quality'] : 'max';

if (!ps_valid_url($url) || ps_detect_platform($url) === 'unknown') {
    ps_json(['ok' => false, 'error' => 'Invalid or unsupported media link.'], 422);
}

if (ps_remote_enabled()) {
    $platform = ps_detect_platform($url);
    ps_track_attempt([
        'url' => $url,
        'action' => 'download',
        'status' => 'success',
        'platform' => $platform,
        'format' => $format,
        'quality' => $quality,
    ]);

    $remoteUrl = ps_remote_url((string) REMOTE_DOWNLOAD_PATH, [
        'url' => $url,
        'format' => $format,
        'quality' => $quality,
    ]);
    header('Location: ' . $remoteUrl, true, 302);
    exit;
}

if (!ps_ytdlp_available()) {
    ps_json(['ok' => false, 'error' => 'yt-dlp missing in /bin.'], 500);
}

$platform = ps_detect_platform($url);
$sel      = ps_format_selector($format, $quality);

// Title for a nice filename (best effort, never fatal).
$title = 'pulsestream-media';
$duration = null;
try {
    $info = ps_extract_info($url);
    $title = $info['title'];
    $duration = $info['duration'];
} catch (Throwable $e) {
}

$filename = ps_safe_name($title, $sel['ext']);

ps_track_attempt([
    'url' => $url, 'action' => 'download', 'status' => 'success', 'platform' => $platform,
    'title' => $title, 'format' => $format, 'quality' => $quality, 'duration' => $duration,
]);

// yt-dlp writes the finished stream to stdout ("-o -").
$args = array_merge(
    ['--no-warnings', '--no-playlist', '--retries', '10', '--socket-timeout', '60'],
    $sel['args'],
    ['-o', '-', $url]
);
$cmd = ps_ytdlp_cmd($args);

while (ob_get_level() > 0) ob_end_clean();

header('Content-Type: ' . $sel['mime']);
header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('X-Accel-Buffering: no');
header('Cache-Control: no-store');
header('X-PulseStream-Quality: ' . $sel['label']);

$descriptors = [1 => ['pipe', 'w'], 2 => ['file', TMP_DIR . DIRECTORY_SEPARATOR . 'extractor.log', 'a']];
$proc = proc_open($cmd, $descriptors, $pipes);
if (!is_resource($proc)) {
    http_response_code(500);
    echo 'Could not start the extractor process.';
    exit;
}

stream_set_blocking($pipes[1], true);
// 512 KB high-water-mark chunks -> steady throughput on multi-hour files.
while (!feof($pipes[1])) {
    $chunk = fread($pipes[1], STREAM_HIGH_WATER_MARK);
    if ($chunk === false || $chunk === '') break;
    echo $chunk;
    flush();
    if (connection_aborted()) break;
}
fclose($pipes[1]);
proc_close($proc);

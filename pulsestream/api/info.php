<?php
/**
 * POST /api/info.php  { url }
 * Returns media metadata + records the attempt in MySQL.
 */
require_once __DIR__ . '/../includes/remote_backend.php';
require_once __DIR__ . '/../includes/extractor.php';

@ini_set('max_execution_time', '0');
@set_time_limit(0);

$body = json_decode(file_get_contents('php://input') ?: '', true) ?: $_POST;
$url  = trim((string) ($body['url'] ?? ''));

if (!ps_valid_url($url)) {
    ps_track_attempt(['url' => $url, 'action' => 'fetch', 'status' => 'error', 'error' => 'invalid url', 'platform' => ps_detect_platform($url)]);
    ps_json(['ok' => false, 'error' => 'Please paste a valid http(s) media link.'], 422);
}

$platform = ps_detect_platform($url);
if ($platform === 'unknown') {
    ps_track_attempt(['url' => $url, 'action' => 'fetch', 'status' => 'error', 'error' => 'unsupported platform', 'platform' => 'unknown']);
    ps_json(['ok' => false, 'error' => 'Unsupported source. Use YouTube, TikTok or Instagram.'], 422);
}

if (ps_remote_enabled()) {
    try {
        $remote = ps_remote_info_request($url);
        $ok = !empty($remote['json']['ok']);
        $status = $ok ? 'success' : 'error';

        ps_track_attempt([
            'url' => $url,
            'action' => 'fetch',
            'status' => $status,
            'platform' => $platform,
            'title' => (string) ($remote['json']['data']['title'] ?? ''),
            'author' => (string) ($remote['json']['data']['author'] ?? ''),
            'duration' => isset($remote['json']['data']['duration']) ? (int) $remote['json']['data']['duration'] : null,
            'error' => (string) ($remote['json']['error'] ?? ''),
        ]);

        ps_json($remote['json'], $ok ? 200 : (($remote['code'] >= 400) ? $remote['code'] : 502));
    } catch (Throwable $e) {
        ps_track_attempt([
            'url' => $url,
            'action' => 'fetch',
            'status' => 'error',
            'platform' => $platform,
            'error' => $e->getMessage(),
        ]);
        ps_json(['ok' => false, 'error' => 'Remote extractor error: ' . $e->getMessage()], 502);
    }
}

if (!ps_ytdlp_available()) {
    ps_json(['ok' => false, 'error' => 'yt-dlp is not installed. Drop yt-dlp.exe (and ffmpeg.exe) into the /bin folder.'], 500);
}

try {
    $info = ps_extract_info($url);
    ps_track_attempt([
        'url' => $url, 'action' => 'fetch', 'status' => 'success', 'platform' => $platform,
        'title' => $info['title'], 'author' => $info['author'], 'duration' => $info['duration'],
    ]);
    ps_json(['ok' => true, 'data' => $info]);
} catch (Throwable $e) {
    ps_track_attempt(['url' => $url, 'action' => 'fetch', 'status' => 'error', 'platform' => $platform, 'error' => $e->getMessage()]);
    ps_json(['ok' => false, 'error' => $e->getMessage()], 502);
}

<?php
/**
 * POST /api/track.php { event: "visit"|"attempt", ... }
 * Lightweight analytics beacon used by the front-end.
 */
require_once __DIR__ . '/../includes/db.php';

$body  = json_decode(file_get_contents('php://input') ?: '', true) ?: $_POST;
$event = $body['event'] ?? 'visit';

if ($event === 'attempt') {
    ps_track_attempt([
        'url'      => (string) ($body['url'] ?? ''),
        'platform' => ps_detect_platform((string) ($body['url'] ?? '')),
        'action'   => in_array($body['action'] ?? '', ['fetch', 'download', 'copy_link', 'history_redownload'], true) ? $body['action'] : 'fetch',
        'title'    => (string) ($body['title'] ?? ''),
        'author'   => (string) ($body['author'] ?? ''),
        'format'   => in_array($body['format'] ?? '', ['mp3', 'mp4'], true) ? $body['format'] : null,
        'quality'  => in_array($body['quality'] ?? '', ['max', 'standard', 'compact'], true) ? $body['quality'] : null,
        'status'   => in_array($body['status'] ?? '', ['success', 'error'], true) ? $body['status'] : 'success',
        'error'    => (string) ($body['error'] ?? ''),
        'duration' => isset($body['duration']) ? (int) $body['duration'] : null,
    ]);
} else {
    ps_track_visit((string) ($body['page'] ?? '/'));
}

ps_json(['ok' => true]);

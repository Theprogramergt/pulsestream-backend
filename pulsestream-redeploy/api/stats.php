<?php
/**
 * GET /api/stats.php  -> aggregate counters shown in the hero strip.
 */
require_once __DIR__ . '/../includes/db.php';

$pdo = ps_db();
if (!$pdo) ps_json(['ok' => true, 'data' => ['visits' => 0, 'unique_visitors' => 0, 'attempts' => 0, 'downloads' => 0, 'db' => false]]);

try {
    $one = fn(string $sql) => (int) $pdo->query($sql)->fetchColumn();
    ps_json(['ok' => true, 'data' => [
        'visits'          => $one('SELECT COUNT(*) FROM visits'),
        'unique_visitors' => $one('SELECT COUNT(DISTINCT visitor_id) FROM visits'),
        'attempts'        => $one('SELECT COUNT(*) FROM download_attempts'),
        'downloads'       => $one("SELECT COUNT(*) FROM download_attempts WHERE action='download'"),
        'today'           => $one('SELECT COALESCE(visits,0) FROM daily_stats WHERE stat_date = CURDATE()'),
        'db'              => true,
    ]]);
} catch (Throwable $e) {
    ps_json(['ok' => false, 'error' => 'stats unavailable'], 500);
}

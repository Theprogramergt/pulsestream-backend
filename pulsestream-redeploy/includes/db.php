<?php
require_once __DIR__ . '/../config.php';

function ps_db(): ?PDO
{
    static $pdo = null;
    static $tried = false;
    if ($tried) return $pdo;
    $tried = true;
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $e) {
        // The site keeps working even if MySQL is off; analytics are just skipped.
        error_log('[pulsestream] DB unavailable: ' . $e->getMessage());
        $pdo = null;
    }
    return $pdo;
}

function ps_client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

/** Stable anonymous visitor id (hash, no raw IP stored twice). */
function ps_visitor_id(): string
{
    if (empty($_COOKIE['ps_vid'])) {
        $vid = bin2hex(random_bytes(16));
        setcookie('ps_vid', $vid, [
            'expires' => time() + 60 * 60 * 24 * 365,
            'path' => '/',
            'samesite' => 'Lax',
        ]);
        $_COOKIE['ps_vid'] = $vid;
    }
    return $_COOKIE['ps_vid'];
}

function ps_detect_platform(string $url): string
{
    $u = strtolower($url);
    if (preg_match('~(youtube\.com|youtu\.be)~', $u)) return 'youtube';
    if (preg_match('~tiktok\.com~', $u)) return 'tiktok';
    if (preg_match('~instagram\.com~', $u)) return 'instagram';
    return 'unknown';
}

/** Records a visit / page view. */
function ps_track_visit(string $page = '/'): void
{
    $pdo = ps_db();
    if (!$pdo) return;
    try {
        $pdo->prepare('INSERT INTO visits (visitor_id, ip_address, user_agent, referrer, page, device_type)
                       VALUES (?,?,?,?,?,?)')
            ->execute([
                ps_visitor_id(),
                ps_client_ip(),
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500),
                substr($page, 0, 190),
                ps_device_type(),
            ]);
        $pdo->prepare("INSERT INTO daily_stats (stat_date, visits) VALUES (CURDATE(), 1)
                       ON DUPLICATE KEY UPDATE visits = visits + 1")->execute();
    } catch (Throwable $e) {
        error_log('[pulsestream] track_visit: ' . $e->getMessage());
    }
}

function ps_device_type(): string
{
    $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (preg_match('~ipad|tablet~', $ua)) return 'tablet';
    if (preg_match('~mobi|android|iphone~', $ua)) return 'mobile';
    return 'desktop';
}

/**
 * Records an attempt (fetch / download). Returns the inserted row id.
 */
function ps_track_attempt(array $d): ?int
{
    $pdo = ps_db();
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare('INSERT INTO download_attempts
            (visitor_id, ip_address, platform, action, url, title, author, format, quality, status, error_message, duration_seconds)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            ps_visitor_id(),
            ps_client_ip(),
            $d['platform'] ?? 'unknown',
            $d['action'] ?? 'fetch',
            substr($d['url'] ?? '', 0, 1000),
            substr($d['title'] ?? '', 0, 500),
            substr($d['author'] ?? '', 0, 255),
            $d['format'] ?? null,
            $d['quality'] ?? null,
            $d['status'] ?? 'success',
            substr($d['error'] ?? '', 0, 500),
            isset($d['duration']) ? (int) $d['duration'] : null,
        ]);
        $col = (($d['action'] ?? 'fetch') === 'download') ? 'downloads' : 'fetches';
        $pdo->prepare("INSERT INTO daily_stats (stat_date, $col) VALUES (CURDATE(), 1)
                       ON DUPLICATE KEY UPDATE $col = $col + 1")->execute();
        return (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        error_log('[pulsestream] track_attempt: ' . $e->getMessage());
        return null;
    }
}

function ps_json($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function ps_valid_url(?string $url): bool
{
    if (!$url || strlen($url) > 1000) return false;
    if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
    return (bool) preg_match('~^https?://~i', $url);
}

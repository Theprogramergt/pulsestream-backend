<?php
require_once __DIR__ . '/db.php';

function ps_remote_enabled(): bool
{
    return defined('REMOTE_EXTRACTOR_BASE_URL') && trim((string) REMOTE_EXTRACTOR_BASE_URL) !== '';
}

function ps_remote_url(string $path, array $query = []): string
{
    $base = rtrim((string) REMOTE_EXTRACTOR_BASE_URL, '/');
    $url = $base . '/' . ltrim($path, '/');
    if (!empty($query)) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
    return $url;
}

function ps_remote_info_request(string $url): array
{
    $endpoint = ps_remote_url((string) REMOTE_INFO_PATH);
    $payload = json_encode(['url' => $url], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        throw new RuntimeException('Could not prepare remote extractor request.');
    }

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    $token = trim((string) REMOTE_SHARED_TOKEN);
    if ($token !== '') {
        $headers[] = REMOTE_SHARED_TOKEN_HEADER . ': ' . $token;
    }

    $raw = null;
    $code = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch) ?: 'Unknown cURL error';
            curl_close($ch);
            throw new RuntimeException('Remote extractor request failed: ' . $err);
        }
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $payload,
                'timeout' => 90,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($endpoint, false, $ctx);
        if ($raw === false) {
            throw new RuntimeException('Remote extractor request failed and cURL is unavailable.');
        }

        if (!empty($http_response_header[0]) && preg_match('~\s(\d{3})\s~', $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
    }

    $json = json_decode((string) $raw, true);
    if (!is_array($json)) {
        throw new RuntimeException('Remote extractor returned an invalid JSON response.');
    }

    return ['code' => $code, 'json' => $json];
}

<?php
require_once __DIR__ . '/db.php';

/** Builds a shell-safe yt-dlp command. */
function ps_ytdlp_cmd(array $args): string
{
    $parts = [escapeshellarg(YTDLP_BIN)];
    if (is_file(FFMPEG_BIN)) {
        $parts[] = '--ffmpeg-location';
        $parts[] = escapeshellarg(dirname(FFMPEG_BIN));
    }
    foreach ($args as $a) {
        $parts[] = escapeshellarg($a);
    }
    return implode(' ', $parts);
}

function ps_ytdlp_available(): bool
{
    return is_file(YTDLP_BIN) || ps_which(basename(YTDLP_BIN)) !== null;
}

function ps_which(string $bin): ?string
{
    $cmd = stripos(PHP_OS, 'WIN') === 0 ? "where $bin" : "command -v $bin";
    $out = @shell_exec($cmd . ' 2>&1');
    if (!$out) return null;
    $line = trim(explode("\n", trim($out))[0]);
    return $line !== '' && stripos($line, 'not found') === false ? $line : null;
}

/**
 * Extracts metadata using yt-dlp -J. Works for YouTube (any length),
 * TikTok (no watermark) and Instagram Reels / posts.
 */
function ps_extract_info(string $url): array
{
    @set_time_limit(0);
    $cmd = ps_ytdlp_cmd([
        '-J',
        '--no-warnings',
        '--no-playlist',
        '--socket-timeout', '30',
        '--retries', '5',
        $url,
    ]) . ' 2>' . (stripos(PHP_OS, 'WIN') === 0 ? 'NUL' : '/dev/null');

    $raw = @shell_exec($cmd);
    if (!$raw) {
        throw new RuntimeException('Extractor returned no data. Check that yt-dlp exists in /bin and that the URL is public.');
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        throw new RuntimeException('Could not parse media metadata for this link.');
    }
    if (isset($json['entries'][0])) $json = $json['entries'][0];

    $thumb = $json['thumbnail'] ?? null;
    if (!$thumb && !empty($json['thumbnails'])) {
        $t = end($json['thumbnails']);
        $thumb = $t['url'] ?? null;
    }

    return [
        'id'          => $json['id'] ?? null,
        'platform'    => ps_detect_platform($url),
        'title'       => $json['title'] ?? 'Untitled media',
        'author'      => $json['uploader'] ?? ($json['channel'] ?? ($json['uploader_id'] ?? 'Unknown')),
        'author_url'  => $json['uploader_url'] ?? null,
        'duration'    => isset($json['duration']) ? (int) $json['duration'] : 0,
        'duration_text' => ps_hms(isset($json['duration']) ? (int) $json['duration'] : 0),
        'views'       => isset($json['view_count']) ? (int) $json['view_count'] : null,
        'likes'       => isset($json['like_count']) ? (int) $json['like_count'] : null,
        'thumbnail'   => $thumb,
        'webpage_url' => $json['webpage_url'] ?? $url,
        'embed_url'   => ps_embed_url($url, $json),
        'is_long'     => (int) (($json['duration'] ?? 0) >= 3600),
        'description' => mb_substr((string) ($json['description'] ?? ''), 0, 400),
    ];
}

function ps_embed_url(string $url, array $json): ?string
{
    $p = ps_detect_platform($url);
    $id = $json['id'] ?? null;
    if ($p === 'youtube' && $id) return 'https://www.youtube.com/embed/' . rawurlencode($id);
    if ($p === 'tiktok' && $id)  return 'https://www.tiktok.com/embed/v2/' . rawurlencode($id);
    if ($p === 'instagram') {
        $clean = preg_replace('~\?.*$~', '', $url);
        return rtrim($clean, '/') . '/embed';
    }
    return null;
}

function ps_hms(int $s): string
{
    if ($s <= 0) return '--:--';
    $h = intdiv($s, 3600);
    $m = intdiv($s % 3600, 60);
    $x = $s % 60;
    return $h > 0
        ? sprintf('%d:%02d:%02d', $h, $m, $x)
        : sprintf('%d:%02d', $m, $x);
}

/** Maps the UI format+quality choice to a yt-dlp selector. */
function ps_format_selector(string $format, string $quality): array
{
    if ($format === 'mp3') {
        $kbps = ['max' => '320', 'standard' => '192', 'compact' => '128'][$quality] ?? '320';
        return [
            'args' => ['-f', 'bestaudio/best', '-x', '--audio-format', 'mp3', '--audio-quality', $kbps . 'K'],
            'ext'  => 'mp3',
            'mime' => 'audio/mpeg',
            'label' => $kbps . ' kbps MP3',
        ];
    }
    $h = ['max' => 1080, 'standard' => 480, 'compact' => 360][$quality] ?? 1080;
    return [
        'args' => ['-f', "bestvideo[height<=$h]+bestaudio/best[height<=$h]/best", '--merge-output-format', 'mp4'],
        'ext'  => 'mp4',
        'mime' => 'video/mp4',
        'label' => $h . 'p MP4',
    ];
}

function ps_safe_name(string $title, string $ext): string
{
    $name = preg_replace('~[^\pL\pN \-\_\.\(\)]+~u', '', $title);
    $name = trim(preg_replace('~\s+~', ' ', (string) $name));
    if ($name === '') $name = 'pulsestream-media';
    return mb_substr($name, 0, 120) . '.' . $ext;
}

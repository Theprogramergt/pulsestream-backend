import os
import re
import subprocess
import shutil
from flask import Flask, request, jsonify, Response, stream_with_context

import yt_dlp

app = Flask(__name__)

# Optional shared token check (matches REMOTE_SHARED_TOKEN in config.php)
SHARED_TOKEN = os.environ.get("PS_SHARED_TOKEN", "")
SHARED_TOKEN_HEADER = "X-PulseStream-Token"

LONG_THRESHOLD_SECONDS = 60 * 60  # 1 hour+ counts as "long" for the UI note


def check_token():
    if not SHARED_TOKEN:
        return True
    return request.headers.get(SHARED_TOKEN_HEADER, "") == SHARED_TOKEN


def detect_platform(url: str) -> str:
    u = url.lower()
    if "youtube.com" in u or "youtu.be" in u:
        return "youtube"
    if "tiktok.com" in u:
        return "tiktok"
    if "instagram.com" in u:
        return "instagram"
    return "unknown"


def fmt_duration(seconds):
    if not seconds:
        return "—"
    seconds = int(seconds)
    h, rem = divmod(seconds, 3600)
    m, s = divmod(rem, 60)
    if h:
        return f"{h}:{m:02d}:{s:02d}"
    return f"{m}:{s:02d}"


@app.route("/api/info.php", methods=["POST"])
def info():
    if not check_token():
        return jsonify(ok=False, error="Unauthorized"), 401

    body = request.get_json(silent=True) or {}
    url = (body.get("url") or "").strip()

    if not url or not re.match(r"^https?://", url, re.I):
        return jsonify(ok=False, error="Please paste a valid http(s) media link."), 422

    platform = detect_platform(url)
    if platform == "unknown":
        return jsonify(ok=False, error="Unsupported source. Use YouTube, TikTok or Instagram."), 422

    ydl_opts = {
        "quiet": True,
        "no_warnings": True,
        "noplaylist": True,
        "skip_download": True,
    }

    try:
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            info_dict = ydl.extract_info(url, download=False)
    except Exception as e:
        return jsonify(ok=False, error=f"Could not extract this media: {e}"), 502

    duration = info_dict.get("duration") or 0
    data = {
        "title": info_dict.get("title") or "Untitled media",
        "author": info_dict.get("uploader") or info_dict.get("channel") or "Unknown",
        "duration": int(duration),
        "duration_text": fmt_duration(duration),
        "views": info_dict.get("view_count"),
        "thumbnail": info_dict.get("thumbnail"),
        "embed_url": info_dict.get("webpage_url"),
        "webpage_url": info_dict.get("webpage_url") or url,
        "platform": platform,
        "is_long": duration >= LONG_THRESHOLD_SECONDS,
    }

    return jsonify(ok=True, data=data)


def format_selector(fmt: str, quality: str):
    height_map = {"max": "1080", "standard": "480", "compact": "360"}
    height = height_map.get(quality, "1080")

    if fmt == "mp3":
        return {
            "args": ["-x", "--audio-format", "mp3", "--audio-quality", "0"],
            "ext": "mp3",
            "mime": "audio/mpeg",
        }
    return {
        "args": ["-f", f"bestvideo[height<={height}]+bestaudio/best[height<={height}]", "--merge-output-format", "mp4"],
        "ext": "mp4",
        "mime": "video/mp4",
    }


def safe_filename(title: str, ext: str) -> str:
    cleaned = re.sub(r"[^\w\s\-]", "", title or "pulsestream-media").strip()
    cleaned = re.sub(r"\s+", "-", cleaned)[:80] or "pulsestream-media"
    return f"{cleaned}.{ext}"


@app.route("/api/download.php", methods=["GET"])
def download():
    if not check_token():
        return jsonify(ok=False, error="Unauthorized"), 401

    url = (request.args.get("url") or "").strip()
    fmt = request.args.get("format", "mp4")
    fmt = "mp3" if fmt == "mp3" else "mp4"
    quality = request.args.get("quality", "max")
    if quality not in ("max", "standard", "compact"):
        quality = "max"

    if not url or not re.match(r"^https?://", url, re.I) or detect_platform(url) == "unknown":
        return jsonify(ok=False, error="Invalid or unsupported media link."), 422

    sel = format_selector(fmt, quality)

    title = "pulsestream-media"
    try:
        with yt_dlp.YoutubeDL({"quiet": True, "no_warnings": True, "skip_download": True}) as ydl:
            probe = ydl.extract_info(url, download=False)
            title = probe.get("title") or title
    except Exception:
        pass

    filename = safe_filename(title, sel["ext"])

    ytdlp_bin = shutil.which("yt-dlp") or "yt-dlp"
    cmd = [ytdlp_bin, "--no-warnings", "--no-playlist", "--retries", "10",
           "--socket-timeout", "60", *sel["args"], "-o", "-", url]

    proc = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.DEVNULL)

    def generate():
        try:
            while True:
                chunk = proc.stdout.read(512 * 1024)
                if not chunk:
                    break
                yield chunk
        finally:
            proc.stdout.close()
            proc.wait()

    headers = {
        "Content-Disposition": f'attachment; filename="{filename}"',
        "Cache-Control": "no-store",
        "X-Accel-Buffering": "no",
    }
    return Response(stream_with_context(generate()), mimetype=sel["mime"], headers=headers)


@app.route("/healthz")
def healthz():
    return jsonify(ok=True)


if __name__ == "__main__":
    port = int(os.environ.get("PORT", 8080))
    app.run(host="0.0.0.0", port=port)

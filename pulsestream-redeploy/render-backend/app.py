import os
import re
import shutil
import subprocess
import tempfile
import uuid

from flask import Flask, request, jsonify, Response, stream_with_context

import yt_dlp


app = Flask(__name__)


# ============================================================
# Optional shared token
# ============================================================

SHARED_TOKEN = os.environ.get("PS_SHARED_TOKEN", "")
SHARED_TOKEN_HEADER = "X-PulseStream-Token"


# ============================================================
# Constants
# ============================================================

LONG_THRESHOLD_SECONDS = 60 * 60  # 1 hour

TEMP_DIR = "/tmp/pulsestream"

os.makedirs(TEMP_DIR, exist_ok=True)


# ============================================================
# Authentication
# ============================================================

def check_token():
    """
    If PS_SHARED_TOKEN is empty, authentication is disabled.
    If PS_SHARED_TOKEN is configured on Render, the PHP frontend
    must send the matching X-PulseStream-Token header.
    """

    if not SHARED_TOKEN:
        return True

    return request.headers.get(
        SHARED_TOKEN_HEADER,
        ""
    ) == SHARED_TOKEN


# ============================================================
# Platform detection
# ============================================================

def detect_platform(url: str) -> str:
    u = url.lower()

    if "youtube.com" in u or "youtu.be" in u:
        return "youtube"

    if "tiktok.com" in u:
        return "tiktok"

    if "instagram.com" in u:
        return "instagram"

    return "unknown"


# ============================================================
# Duration formatting
# ============================================================

def fmt_duration(seconds):

    if not seconds:
        return "—"

    seconds = int(seconds)

    hours, remainder = divmod(seconds, 3600)
    minutes, secs = divmod(remainder, 60)

    if hours:
        return f"{hours}:{minutes:02d}:{secs:02d}"

    return f"{minutes}:{secs:02d}"


# ============================================================
# Filename
# ============================================================

def safe_filename(title: str, ext: str) -> str:

    cleaned = re.sub(
        r"[^\w\s\-]",
        "",
        title or "pulsestream-media"
    )

    cleaned = re.sub(
        r"\s+",
        "-",
        cleaned
    ).strip()

    cleaned = cleaned[:80]

    if not cleaned:
        cleaned = "pulsestream-media"

    return f"{cleaned}.{ext}"


# ============================================================
# Health check
# ============================================================

@app.route("/healthz", methods=["GET"])
def healthz():

    return jsonify(ok=True)


# ============================================================
# INFO ENDPOINT
# ============================================================

@app.route("/api/info.php", methods=["POST"])
def info():

    if not check_token():
        return jsonify(
            ok=False,
            error="Unauthorized"
        ), 401

    body = request.get_json(
        silent=True
    ) or {}

    url = (
        body.get("url") or ""
    ).strip()

    # --------------------------------------------------------
    # Validate URL
    # --------------------------------------------------------

    if not url or not re.match(
        r"^https?://",
        url,
        re.I
    ):

        return jsonify(
            ok=False,
            error="Please paste a valid http(s) media link."
        ), 422

    # --------------------------------------------------------
    # Detect platform
    # --------------------------------------------------------

    platform = detect_platform(url)

    if platform == "unknown":

        return jsonify(
            ok=False,
            error="Unsupported source. Use YouTube, TikTok or Instagram."
        ), 422

    # --------------------------------------------------------
    # yt-dlp options
    # --------------------------------------------------------

    ydl_opts = {
        "quiet": True,
        "no_warnings": True,
        "noplaylist": True,
        "skip_download": True,
    }

    # --------------------------------------------------------
    # Extract information
    # --------------------------------------------------------

    try:

        with yt_dlp.YoutubeDL(
            ydl_opts
        ) as ydl:

            info_dict = ydl.extract_info(
                url,
                download=False
            )

    except Exception as e:

        return jsonify(
            ok=False,
            error=f"Could not extract this media: {e}"
        ), 502

    # --------------------------------------------------------
    # Metadata
    # --------------------------------------------------------

    duration = info_dict.get(
        "duration"
    ) or 0

    data = {

        "title":
            info_dict.get("title")
            or "Untitled media",

        "author":
            info_dict.get("uploader")
            or info_dict.get("channel")
            or "Unknown",

        "duration":
            int(duration),

        "duration_text":
            fmt_duration(duration),

        "views":
            info_dict.get("view_count"),

        "thumbnail":
            info_dict.get("thumbnail"),

        "embed_url":
            info_dict.get("webpage_url"),

        "webpage_url":
            info_dict.get("webpage_url")
            or url,

        "platform":
            platform,

        "is_long":
            duration >= LONG_THRESHOLD_SECONDS,
    }

    return jsonify(
        ok=True,
        data=data
    )


# ============================================================
# Format selector
# ============================================================

def format_selector(fmt: str, quality: str):

    height_map = {

        "max": "1080",

        "standard": "480",

        "compact": "360",
    }

    height = height_map.get(
        quality,
        "1080"
    )

    # --------------------------------------------------------
    # MP3
    # --------------------------------------------------------

    if fmt == "mp3":

        return {

            "args": [
                "-x",
                "--audio-format",
                "mp3",
                "--audio-quality",
                "0",
            ],

            "ext": "mp3",

            "mime": "audio/mpeg",

            "label": "MP3",
        }

    # --------------------------------------------------------
    # MP4
    # --------------------------------------------------------

    return {

        "args": [
            "-f",
            (
                f"bestvideo[height<={height}]"
                f"+bestaudio/"
                f"best[height<={height}]"
            ),

            "--merge-output-format",
            "mp4",
        ],

        "ext": "mp4",

        "mime": "video/mp4",

        "label": f"MP4 {height}p",
    }


# ============================================================
# DOWNLOAD ENDPOINT
# ============================================================

@app.route("/api/download.php", methods=["GET"])
def download():

    # --------------------------------------------------------
    # Authentication
    # --------------------------------------------------------

    if not check_token():

        return jsonify(
            ok=False,
            error="Unauthorized"
        ), 401

    # --------------------------------------------------------
    # Parameters
    # --------------------------------------------------------

    url = (
        request.args.get("url") or ""
    ).strip()

    fmt = request.args.get(
        "format",
        "mp4"
    )

    if fmt != "mp3":
        fmt = "mp4"

    quality = request.args.get(
        "quality",
        "max"
    )

    if quality not in (
        "max",
        "standard",
        "compact"
    ):

        quality = "max"

    # --------------------------------------------------------
    # Validate URL
    # --------------------------------------------------------

    if (
        not url
        or not re.match(
            r"^https?://",
            url,
            re.I
        )
        or detect_platform(url) == "unknown"
    ):

        return jsonify(
            ok=False,
            error="Invalid or unsupported media link."
        ), 422

    # --------------------------------------------------------
    # Select format
    # --------------------------------------------------------

    sel = format_selector(
        fmt,
        quality
    )

    # --------------------------------------------------------
    # Find yt-dlp
    # --------------------------------------------------------

    ytdlp_bin = (
        shutil.which("yt-dlp")
        or "yt-dlp"
    )

    # --------------------------------------------------------
    # Get title
    # --------------------------------------------------------

    title = "pulsestream-media"

    try:

        with yt_dlp.YoutubeDL({

            "quiet": True,

            "no_warnings": True,

            "skip_download": True,

        }) as ydl:

            probe = ydl.extract_info(
                url,
                download=False
            )

            title = (
                probe.get("title")
                or title
            )

    except Exception:

        pass

    # --------------------------------------------------------
    # Filename
    # --------------------------------------------------------

    filename = safe_filename(
        title,
        sel["ext"]
    )

    # --------------------------------------------------------
    # Unique temporary directory
    # --------------------------------------------------------

    job_id = uuid.uuid4().hex

    job_dir = os.path.join(
        TEMP_DIR,
        job_id
    )

    os.makedirs(
        job_dir,
        exist_ok=True
    )

    output_template = os.path.join(
        job_dir,
        "media.%(ext)s"
    )

    # --------------------------------------------------------
    # Build yt-dlp command
    # --------------------------------------------------------

    cmd = [

        ytdlp_bin,

        "--no-warnings",

        "--no-playlist",

        "--retries",
        "10",

        "--socket-timeout",
        "60",
    ]

    # --------------------------------------------------------
    # MP3
    # --------------------------------------------------------

    if fmt == "mp3":

        cmd.extend([

            "-x",

            "--audio-format",
            "mp3",

            "--audio-quality",
            "0",

        ])

    # --------------------------------------------------------
    # MP4
    # --------------------------------------------------------

    else:

        height_map = {

            "max": "1080",

            "standard": "480",

            "compact": "360",
        }

        height = height_map.get(
            quality,
            "1080"
        )

        cmd.extend([

            "-f",

            (
                f"bestvideo[height<={height}]"
                f"+bestaudio/"
                f"best[height<={height}]"
            ),

            "--merge-output-format",
            "mp4",
        ])

    # --------------------------------------------------------
    # Output file
    # --------------------------------------------------------

    cmd.extend([

        "-o",
        output_template,

        url,
    ])

    # --------------------------------------------------------
    # Run yt-dlp
    # --------------------------------------------------------

    try:

        result = subprocess.run(

            cmd,

            stdout=subprocess.PIPE,

            stderr=subprocess.PIPE,

            timeout=600,

            check=False,
        )

    except subprocess.TimeoutExpired:

        shutil.rmtree(
            job_dir,
            ignore_errors=True
        )

        return jsonify(

            ok=False,

            error="Download timed out."

        ), 504

    except Exception as e:

        shutil.rmtree(
            job_dir,
            ignore_errors=True
        )

        return jsonify(

            ok=False,

            error=f"Could not start yt-dlp: {e}"

        ), 500

    # --------------------------------------------------------
    # Check yt-dlp result
    # --------------------------------------------------------

    if result.returncode != 0:

        error_message = result.stderr.decode(
            "utf-8",
            errors="replace"
        )

        shutil.rmtree(
            job_dir,
            ignore_errors=True
        )

        return jsonify(

            ok=False,

            error=(
                "yt-dlp failed: "
                + error_message[-3000:]
            )

        ), 502

    # --------------------------------------------------------
    # Find output file
    # --------------------------------------------------------

    output_file = None

    try:

        for name in os.listdir(job_dir):

            full_path = os.path.join(
                job_dir,
                name
            )

            if os.path.isfile(full_path):

                output_file = full_path

                break

    except Exception as e:

        shutil.rmtree(
            job_dir,
            ignore_errors=True
        )

        return jsonify(

            ok=False,

            error=f"Could not find output file: {e}"

        ), 500

    # --------------------------------------------------------
    # Make sure file exists
    # --------------------------------------------------------

    if not output_file or not os.path.exists(
        output_file
    ):

        shutil.rmtree(
            job_dir,
            ignore_errors=True
        )

        return jsonify(

            ok=False,

            error=(
                "Download completed "
                "but no output file was created."
            )

        ), 500

    # --------------------------------------------------------
    # Check file size
    # --------------------------------------------------------

    file_size = os.path.getsize(
        output_file
    )

    if file_size <= 0:

        shutil.rmtree(
            job_dir,
            ignore_errors=True
        )

        return jsonify(

            ok=False,

            error="Downloaded file is empty."

        ), 500

    # --------------------------------------------------------
    # Stream completed file
    # --------------------------------------------------------

    def generate_file():

        try:

            with open(
                output_file,
                "rb"
            ) as file:

                while True:

                    chunk = file.read(
                        512 * 1024
                    )

                    if not chunk:
                        break

                    yield chunk

        finally:

            shutil.rmtree(
                job_dir,
                ignore_errors=True
            )

    # --------------------------------------------------------
    # Response
    # --------------------------------------------------------

    response = Response(

        stream_with_context(
            generate_file()
        ),

        mimetype=sel["mime"],
    )

    response.headers[
        "Content-Disposition"
    ] = (
        f'attachment; filename="{filename}"'
    )

    response.headers[
        "Content-Length"
    ] = str(file_size)

    response.headers[
        "Cache-Control"
    ] = "no-store"

    response.headers[
        "X-Accel-Buffering"
    ] = "no"

    response.headers[
        "X-PulseStream-Quality"
    ] = sel["label"]

    return response


# ============================================================
# Local development
# ============================================================

if __name__ == "__main__":

    port = int(
        os.environ.get(
            "PORT",
            8080
        )
    )

    app.run(
        host="0.0.0.0",
        port=port
    )

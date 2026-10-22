import os
import re
import stat
import time
import logging
import threading
import traceback
import json
import paramiko
from dotenv import load_dotenv
from fastapi import FastAPI, HTTPException, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import StreamingResponse
from pathlib import Path, PurePosixPath
from datetime import datetime

BASE = Path(__file__).resolve().parent
load_dotenv(BASE / ".env", override=True)

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
log = logging.getLogger("audios_backend")

def reload_env():
    """Recarrega o .env dinamicamente para apanhar mudanças feitas via interface gráfica."""
    load_dotenv(BASE / ".env", override=True)

# ── Configuração do servidor remoto ──
REMOTE_HOST = os.getenv("AUDIOS_HOST", "192.168.4.250")
REMOTE_PORT = int(os.getenv("AUDIOS_PORT", "22"))
REMOTE_USER = os.getenv("AUDIOS_USER", "root")
REMOTE_PASS = os.getenv("AUDIOS_PASS", "Support*2008#")
REMOTE_KEY  = os.getenv("AUDIOS_KEY", "")
REMOTE_DIR  = os.getenv("AUDIO_DIR") or os.getenv("AUDIOS_DIR", "/var/spool/asterisk/monitor")

CACHE_TTL = int(os.getenv("CACHE_TTL", "30"))
CHUNK     = 64 * 1024
FORMATS = {
    ".mp3":  ("audio/mpeg",      "MP3",  True),
    ".wav":  ("audio/wav",       "WAV",  True),
    ".m4a":  ("audio/mp4",       "M4A",  True),
    ".ogg":  ("audio/ogg",       "OGG",  True),
    ".flac": ("audio/flac",      "FLAC", True),
    ".aac":  ("audio/aac",       "AAC",  True),
    ".wma":  ("audio/x-ms-wma",  "WMA",  False),
    ".vox":  ("audio/wav",       "VOX",  False),
    ".amr":  ("audio/amr",       "AMR",  False),
}

app = FastAPI(title="QA — Áudios do Servidor", version="2.1")
app.add_middleware(CORSMiddleware, allow_origins=["*"], allow_methods=["*"], allow_headers=["*"])

_cache = {"ts": 0.0, "files": []}
_lock  = threading.Lock()
LAST_ERROR = {"ts": None, "msg": None}

def _connect() -> paramiko.SSHClient:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    kw = dict(hostname=REMOTE_HOST, port=REMOTE_PORT, username=REMOTE_USER,
              timeout=15, allow_agent=False, look_for_keys=False,
              banner_timeout=15, auth_timeout=15)
    if REMOTE_KEY: kw["key_filename"] = REMOTE_KEY
    else:          kw["password"]     = REMOTE_PASS
    ssh.connect(**kw)
    return ssh

def _remote_dir(ssh) -> str:
    if REMOTE_DIR.startswith("~"):
        _, out, _ = ssh.exec_command('eval echo %s' % REMOTE_DIR)
        resolved = out.read().decode().strip()
        return resolved if resolved else REMOTE_DIR
    return REMOTE_DIR

def _safe_name(name: str) -> bool:
    return (bool(name) and name not in (".", "..") and "/" not in name and "\"" not in name and "\x00" not in name)

def _human(size: float) -> str:
    for unit in ("B", "KB", "MB", "GB", "TB"):
        if size < 1024 or unit == "TB":
            return ("%d B" % size) if unit == "B" else ("%.1f %s" % (size, unit))
        size /= 1024
    return "%d B" % size

def list_files(force: bool = False):
    reload_env()
    global REMOTE_DIR
    REMOTE_DIR = os.getenv("AUDIO_DIR") or os.getenv("AUDIOS_DIR", "/var/spool/asterisk/monitor")
    
    now = time.time()
    with _lock:
        if not force and _cache["files"] and (now - _cache["ts"]) < CACHE_TTL:
            return _cache["files"], True
    
    ssh = _connect()
    try:
        sftp = ssh.open_sftp()
        rdir = _remote_dir(ssh)
        files = []
        for attr in sftp.listdir_attr(rdir):
            if attr.st_mode and stat.S_ISDIR(attr.st_mode):
                continue
            ext = PurePosixPath(attr.filename).suffix.lower()
            if ext not in FORMATS:
                continue
            mime, tipo, playable = FORMATS[ext]
            mtime = attr.st_mtime or 0
            files.append({
                "name":        attr.filename,
                "size":        attr.st_size or 0,
                "size_human":  _human(attr.st_size or 0),
                "modified":    datetime.fromtimestamp(mtime).strftime("%Y-%m-%d %H:%M:%S"),
                "modified_ts": mtime,
                "ext":         ext.lstrip("."),
                "type":        tipo,
                "mime":        mime,
                "playable":    playable,
            })
        files.sort(key=lambda f: f["modified_ts"], reverse=True)
        with _lock:
            _cache["ts"], _cache["files"] = time.time(), files
        LAST_ERROR["msg"] = None
        return files, False
    finally:
        ssh.close()

def _find(filename: str) -> dict:
    if not _safe_name(filename):
        raise HTTPException(400, "Nome de ficheiro inválido.")
    for f in list_files()[0]:
        if f["name"] == filename:
            return f
    raise HTTPException(404, "Ficheiro não encontrado em %s." % REMOTE_DIR)

def _serve(filename: str, request: Request, attachment: bool):
    meta = _find(filename)
    ssh  = _connect()
    sftp = ssh.open_sftp()
    path = _remote_dir(ssh) + "/" + filename
    try:
        size = sftp.stat(path).st_size
    except Exception:
        sftp.close(); ssh.close()
        raise HTTPException(404, "O ficheiro deixou de existir no servidor.")
    
    start, end, status = 0, size - 1, 200
    rng = request.headers.get("range") if not attachment else None
    if rng:
        m = re.match(r"bytes=(\d*)-(\d*)", rng)
        if m:
            s, e = m.group(1), m.group(2)
            if s:   start, end = int(s), int(e) if e else size - 1
            elif e: start, end = size - int(e), size - 1
            start = max(0, min(start, size - 1))
            end   = max(start, min(end, size - 1))
            status = 206
            
    handle = sftp.open(path, "rb")
    handle.prefetch()
    handle.seek(start)
    length = end - start + 1
    
    def generate():
        left = length
        try:
            while left > 0:
                data = handle.read(min(CHUNK, left))
                if not data:
                    break
                left -= len(data)
                yield data
        finally:
            handle.close(); sftp.close(); ssh.close()
            
    headers = {
        "Accept-Ranges":  "bytes",
        "Content-Length": str(length),
        "Content-Disposition": '%s; filename="%s"' % ("attachment" if attachment else "inline", filename),
    }
    if status == 206:
        headers["Content-Range"] = "bytes %d-%d/%d" % (start, end, size)
        
    return StreamingResponse(generate(), status_code=status, media_type=meta["mime"], headers=headers)

@app.get("/")
@app.get("/health")
def health():
    return {
        "status":   "ok",
        "service":  "QA — Áudios do Servidor",
        "sftp":     "%s@%s:%d:%s" % (REMOTE_USER, REMOTE_HOST, REMOTE_PORT, REMOTE_DIR),
        "last_error": LAST_ERROR["msg"],
        "endpoints": ["/audios/list", "/audios/stream/{f}", "/audios/download/{f}"],
    }

@app.get("/audios/list")
def audios_list(force: bool = False):
    try:
        files, cached = list_files(force)
    except HTTPException:
        raise
    except Exception as exc:
        LAST_ERROR["ts"], LAST_ERROR["msg"] = time.time(), str(exc)
        log.error("SFTP falhou: %s\n%s", exc, traceback.format_exc())
        raise HTTPException(502, "Falha ao ligar a %s:%d — %s" % (REMOTE_HOST, REMOTE_PORT, exc))
    return {
        "source":  "%s@%s:%d:%s" % (REMOTE_USER, REMOTE_HOST, REMOTE_PORT, REMOTE_DIR),
        "total":  len(files),
        "cached": cached,
        "files":  files,
    }

@app.get("/audios/stream/{filename}")
def audios_stream(filename: str, request: Request):
    return _serve(filename, request, attachment=False)

@app.get("/audios/download/{filename}")
def audios_download(filename: str, request: Request):
    return _serve(filename, request, attachment=True)

@app.get("/progress")
def progress():
    default = {"active": False, "file": "", "stages": {"download": 0, "conversao": 0, "transcricao": 0, "upload": 0}}
    p = BASE / "storage" / "progress.json"
    if not p.exists():
        return default
    try:
        data = json.loads(p.read_text())
        if data.get("active") and time.time() - data.get("updated", 0) > 120:
            return default
        return data
    except Exception:
        return default

@app.get("/activity")
def get_activity():
    p = BASE / "storage" / "activity_history.json"
    if not p.exists():
        return []
    try:
        return json.loads(p.read_text())
    except Exception:
        return []

if __name__ == "__main__":
    import uvicorn
    log.info("Backend pronto → SFTP %s@%s:%d %s", REMOTE_USER, REMOTE_HOST, REMOTE_PORT, REMOTE_DIR)
    uvicorn.run(app, host="0.0.0.0", port=8000)
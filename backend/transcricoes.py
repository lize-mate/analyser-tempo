# ══════════════════════════════════════════════════════════════════
#  FICHEIRO: backend/transcricoes.py
#  Router FastAPI para listar/ver/baixar .txt de ~/text_files
# ══════════════════════════════════════════════════════════════════
import os
import re
import stat
import time
import threading
from datetime import datetime
from pathlib import Path, PurePosixPath
import paramiko
from dotenv import load_dotenv
from fastapi import APIRouter, HTTPException
from fastapi.responses import PlainTextResponse, StreamingResponse

BASE = Path(__file__).resolve().parent
load_dotenv(BASE / ".env", override=True)

REMOTE_HOST = os.getenv("AUDIOS_HOST", "192.168.4.55")
REMOTE_PORT = int(os.getenv("AUDIOS_PORT", "22"))
REMOTE_USER = os.getenv("AUDIOS_USER", "hjit")
REMOTE_PASS = os.getenv("AUDIOS_PASS", "")
REMOTE_KEY  = os.getenv("AUDIOS_KEY", "")
TEXT_DIR    = os.getenv("TEXT_DIR", "~/text_files")

CACHE_TTL = int(os.getenv("CACHE_TTL", "30"))
CHUNK     = 64 * 1024

router = APIRouter(tags=["transcricoes"])
_cache = {"ts": 0.0, "files": []}
_lock  = threading.Lock()

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
    if TEXT_DIR.startswith("~"):
        _, out, _ = ssh.exec_command('eval echo %s' % TEXT_DIR)
        return out.read().decode().strip() or TEXT_DIR
    return TEXT_DIR

def _safe_name(name: str) -> bool:
    return (
        bool(name)
        and name not in (".", "..")
        and "/" not in name
        and "\\" not in name
        and "\x00" not in name
    )

def _human(size: float) -> str:
    for unit in ("B", "KB", "MB", "GB", "TB"):
        if size < 1024 or unit == "TB":
            return ("%d B" % size) if unit == "B" else ("%.1f %s" % (size, unit))
        size /= 1024
    return "%d B" % size

def list_files(force: bool = False):
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
            if not attr.filename.lower().endswith(".txt"):
                continue
            mtime = attr.st_mtime or 0
            files.append({
                "name":        attr.filename,
                "audio":       PurePosixPath(attr.filename).stem,
                "size":        attr.st_size or 0,
                "size_human":  _human(attr.st_size or 0),
                "modified":    datetime.fromtimestamp(mtime).strftime("%Y-%m-%d %H:%M:%S"),
                "modified_ts": mtime,
            })
        files.sort(key=lambda f: f["modified_ts"], reverse=True)
        with _lock:
            _cache["ts"], _cache["files"] = time.time(), files
        return files, False
    finally:
        ssh.close()

@router.get("/transcricoes/list")
def transcricoes_list(force: bool = False):
    try:
        files, cached = list_files(force)
    except Exception as exc:
        raise HTTPException(502, "Falha ao ligar a %s:%d — %s" % (REMOTE_HOST, REMOTE_PORT, exc))
    return {
        "source": "%s@%s:%d:%s" % (REMOTE_USER, REMOTE_HOST, REMOTE_PORT, TEXT_DIR),
        "total":  len(files),
        "cached": cached,
        "files":  files,
    }

@router.get("/transcricoes/view/{filename}")
def transcricoes_view(filename: str):
    if not _safe_name(filename):
        raise HTTPException(400, "Nome inválido.")
    ssh = _connect()
    try:
        sftp = ssh.open_sftp()
        path = _remote_dir(ssh) + "/" + filename
        f = sftp.open(path, "rb")
        content = f.read()
        f.close()
        return PlainTextResponse(content.decode("utf-8", errors="replace"))
    except Exception:
        raise HTTPException(404, "Transcrição não encontrada no servidor.")
    finally:
        ssh.close()

@router.get("/transcricoes/download/{filename}")
def transcricoes_download(filename: str):
    if not _safe_name(filename):
        raise HTTPException(400, "Nome inválido.")
    ssh = _connect()
    sftp = ssh.open_sftp()
    path = _remote_dir(ssh) + "/" + filename
    try:
        size = sftp.stat(path).st_size
    except Exception:
        sftp.close(); ssh.close()
        raise HTTPException(404, "Transcrição não encontrada no servidor.")
    handle = sftp.open(path, "rb")
    def generate():
        try:
            while True:
                data = handle.read(CHUNK)
                if not data:
                    break
                yield data
        finally:
            handle.close(); sftp.close(); ssh.close()
    return StreamingResponse(
        generate(),
        media_type="text/plain; charset=utf-8",
        headers={"Content-Length": str(size),
                 "Content-Disposition": 'attachment; filename="%s"' % filename},
    )
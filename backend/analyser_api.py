# ═══════════════════════════════════════════════════════════════════
# QA ANALYSER API v6.2 — Servidor Fedora (Groq Free Tier Optimized)
# FastAPI :8001 · Groq IA · ReportLab PDF · SFTP · Worker Queue
# ═══════════════════════════════════════════════════════════════════
import os, re, stat, time, json, logging, threading, asyncio
from queue import Queue, Empty
from datetime import datetime, timedelta
from pathlib import Path, PurePosixPath
import paramiko, httpx
from dotenv import load_dotenv
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import PlainTextResponse, StreamingResponse

app = FastAPI(title="QA Analyser API", version="6.2")
app.add_middleware(CORSMiddleware, allow_origins=["*"], allow_methods=["*"], allow_headers=["*"])

BASE = Path(__file__).resolve().parent
load_dotenv(BASE / ".env", override=True)
logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
log = logging.getLogger("analyser")

# ── CONFIGURAÇÃO DE SERVIDORES ──
AUDIO_HOST = os.getenv("AUDIOS_HOST", "192.168.4.250").strip()
AUDIO_PORT = int(os.getenv("AUDIOS_PORT", "22").strip() or "22")
AUDIO_USER = os.getenv("AUDIOS_USER", "root").strip()
AUDIO_PASS = os.getenv("AUDIOS_PASS", "Support*2008#").strip()
AUDIO_DIR  = os.getenv("AUDIO_DIR", "/var/spool/asterisk/monitor/2026/10/15").strip()

TEXT_HOST  = os.getenv("TEXT_HOST", "192.168.4.55").strip()
TEXT_PORT  = int(os.getenv("TEXT_PORT", "22").strip() or "22")
TEXT_USER  = os.getenv("TEXT_USER", "hjit").strip()
TEXT_PASS  = os.getenv("TEXT_PASS", "*Support2026#").strip()
TEXT_DIR   = os.getenv("TEXT_DIR", "~/transcricoes").strip()

PDF_HOST   = os.getenv("PDF_HOST", "192.168.4.55").strip()
PDF_PORT   = int(os.getenv("PDF_PORT", "22").strip() or "22")
PDF_USER   = os.getenv("PDF_USER", "hjit").strip()
PDF_PASS   = os.getenv("PDF_PASS", "*Support2026#").strip()
PDF_DIR    = os.getenv("PDF_DIR", "/home/hjit/pdf_files").strip()

# ── GROQ AI (OTIMIZADO PARA FREE TIER) ──
GROQ_API_KEY     = os.getenv("GROQ_API_KEY", "").strip()
GROQ_MODEL_REQ   = os.getenv("GROQ_MODEL", "llama3-8b-8192").strip() # ✅ Usa o modelo do .env
GROQ_TIMEOUT     = int(os.getenv("GROQ_TIMEOUT", "180"))
GROQ_URL         = "https://api.groq.com/openai/v1/chat/completions"

MIN_TRANSCRIPT_CHARS = int(os.getenv("MIN_TRANSCRIPT_CHARS", "80"))
MAX_TRANSCRIPT_CHARS = int(os.getenv("MAX_TRANSCRIPT_CHARS", "8000"))
MAX_RETRY_ATTEMPTS   = int(os.getenv("MAX_RETRY_ATTEMPTS", "3"))
SYNC_INTERVAL        = int(os.getenv("SYNC_INTERVAL", "120")) # Aumentado para poupar tokens

CHUNK = 64 * 1024
STORAGE       = BASE / "storage"
ANALYSES_FILE = STORAGE / "analyses.json"
ANALYSES_DIR  = STORAGE / "reports"
PDF_LOCAL_DIR = STORAGE / "pdfs"

for d in [STORAGE, ANALYSES_DIR, PDF_LOCAL_DIR]: d.mkdir(parents=True, exist_ok=True)

_lock = threading.Lock()
_a_lock = threading.Lock()
_analysis_queue = Queue()
_running = set()
_STATE = {"resolved_model": None, "model_detection_done": False, "total_analysed": 0, "total_failed": 0}

# ✅ Lista atualizada com modelos ativos no tier gratuito da Groq
MODEL_CANDIDATES = [
    GROQ_MODEL_REQ, 
    "llama3-8b-8192", 
    "llama-3.1-8b-versatile", 
    "gemma2-9b-it"
]

DEFAULT_WEIGHTS = {
    "opening_and_compliance": 0.15, 
    "clarity_and_accuracy": 0.20, 
    "professionalism_and_communication": 0.20, 
    "empathy_and_active_listening": 0.25, 
    "resolution_and_problem_solving": 0.20
}

#  HELPERS SFTP ──
def _connect_text():
    ssh = paramiko.SSHClient(); ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    kw = dict(hostname=TEXT_HOST, port=TEXT_PORT, username=TEXT_USER, timeout=15, allow_agent=False, look_for_keys=False)
    if TEXT_KEY := os.getenv("TEXT_KEY"): kw["key_filename"] = TEXT_KEY
    else: kw["password"] = TEXT_PASS
    ssh.connect(**{k:v for k,v in kw.items() if v is not None}); return ssh

def _remote_dir(ssh, base_dir: str, user: str) -> str:
    if base_dir.startswith("~"):
        try:
            _, out, _ = ssh.exec_command('eval echo %s' % base_dir); resolved = out.read().decode().strip()
            if resolved and not resolved.startswith("~"): return resolved
        except: pass
        return f"/home/{user}/{base_dir[2:]}" if base_dir.startswith("~/") else base_dir
    return base_dir

def _remote_text_dir(ssh): return _remote_dir(ssh, TEXT_DIR, TEXT_USER)
def _remote_pdf_dir(ssh): return _remote_dir(ssh, PDF_DIR, PDF_USER)

def list_text_files():
    ssh = _connect_text()
    try:
        sftp = ssh.open_sftp(); rdir = _remote_text_dir(ssh); files = []
        for attr in sftp.listdir_attr(rdir):
            if stat.S_ISDIR(attr.st_mode  or 0) or not attr.filename.lower().endswith(".txt"): continue
            files.append({"name": attr.filename, "audio": PurePosixPath(attr.filename).stem, "size": attr.st_size or 0, "modified": datetime.fromtimestamp(attr.st_mtime or 0).strftime("%Y-%m-%d %H:%M:%S"), "modified_ts": attr.st_mtime or 0})
        sftp.close(); return sorted(files, key=lambda f: f["modified_ts"], reverse=True)
    finally: ssh.close()

def list_pdf_files():
    ssh = _connect_text()
    try:
        sftp = ssh.open_sftp(); rdir =  remote_pdf_dir(ssh); files = []
        try: sftp.stat(rdir)
        except IOError:
            try: sftp.mkdir(rdir)
            except: pass
        for attr in sftp.listdir_attr(rdir):
            if stat.S_ISDIR(attr.st_mode or 0) or not attr.filename.lower().endswith(".pdf"): continue
            stem = PurePosixPath(attr.filename).stem
            m = re.match(r"^(.+?) relatorio \d{8} \d{6}$", stem)
            original_stem = m.group(1) if m else stem
            files.append({"name": attr.filename, "audio": original_stem, "text_filename": original_stem, "size": attr.st_size or 0, "modified": datetime.fromtimestamp(attr.st_mtime or 0).strftime("%Y-%m-%d %H:%M:%S"), "modified_ts": attr.st_mtime or 0, "view": f"/pdfs/view/{attr.filename}", "download": f"/pdfs/download/{attr.filename}"})
        sftp.close(); return sorted(files, key=lambda f: f["modified_ts"], reverse=True)
    finally: ssh.close()

def load_analyses() -> dict:
    if not ANALYSES_FILE.exists(): return {}
    try: return json.loads(ANALYSES_FILE.read_text())
    except: return {}

def save_analyses(data: dict):
    tmp = ANALYSES_FILE.with_suffix(".tmp"); tmp.write_text(json.dumps(data, ensure_ascii=False, indent=2)); os.replace(tmp, ANALYSES_FILE)

# ── GROQ AI ──
async def _call_groq(prompt: str) -> str:
    if not GROQ_API_KEY or not _STATE["resolved_model"]: raise RuntimeError("Groq não configurado")
    headers = {"Authorization": f"Bearer {GROQ_API_KEY}", "Content-Type": "application/json"}
    payload = {"model": _STATE["resolved_model"], "messages": [{"role": "system", "content": "Analista QA. Responda APENAS em JSON válido."}, {"role": "user", "content": prompt}], "temperature": 0.3, "max_tokens": 800} # ✅ CRÍTICO: Reduzido de 4000 para 800
    
    async with httpx.AsyncClient(timeout=GROQ_TIMEOUT) as client:
        for attempt in range(3):
            try: r = await client.post(GROQ_URL, headers=headers, json=payload)
            except Exception as e: await asyncio.sleep(15 * (attempt + 1)); continue
            if r.status_code == 200: return r.json()["choices"][0]["message"]["content"]
            if r.status_code in (413, 429) and attempt < 2: await asyncio.sleep(30 * (attempt + 1)); continue
            raise RuntimeError(f"Groq HTTP {r.status_code}: {r.text[:300]}")
        raise RuntimeError("Falha após 3 tentativas")

def analyze_file(filename: str):
    if filename in _running: return
    _running.add(filename)
    try:
        ssh = _connect_text()
        try:
            sftp = ssh.open_sftp(); path = _remote_text_dir(ssh) + "/" + filename
            text = sftp.open(path, "rb").read().decode("utf-8", errors="replace"); sftp.close()
        finally: ssh.close()
        
        if len(text.strip()) < MIN_TRANSCRIPT_CHARS: raise ValueError("Transcrição curta demais")
        raw_response = asyncio.run(_call_groq(text[:MAX_TRANSCRIPT_CHARS]))
        
        # Extrair JSON básico (fallback simples para manter o código conciso)
        start, end = raw_response.find("{"), raw_response.rfind("}")
        json_block = json.loads(raw_response[start:end+1]) if start != -1 and end != -1 else {"overall_score": {"final_percentage": 70}}
        pct = float(json_block.get("overall_score", {}).get("final_percentage", 70))
        
        result = {"estado": "analisado", "analyzed_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S"), "model": _STATE["resolved_model"], "final_percentage": pct, "classification": "Bom" if pct >= 80 else ("Médio" if pct >= 60 else "Necessita Melhoria"), "json": json_block}
        report_file = ANALYSES_DIR / (PurePosixPath(filename).stem + ".json"); report_file.write_text(json.dumps(result, ensure_ascii=False, indent=2))
        
        with _a_lock:
            all_a = load_analyses(); all_a[filename] = result; save_analyses(all_a)
        _STATE["total_analysed"] += 1; log.info("✓ Concluída: %s → %.1f%%", filename, pct)
    except Exception as e:
        log.error("✗ Falhou (%s): %s", filename, e)
        with _a_lock:
            all_a = load_analyses(); all_a[filename] = {"estado": "falhou", "motivo": str(e), "analyzed_at": datetime.now().strftime("%Y-%m-%d %H:%M:%S")}; save_analyses(all_a)
        _STATE["total_failed"] += 1
    finally: _running.discard(filename)

def _analysis_worker():
    while True:
        try: name = _analysis_queue.get(timeout=1)
        except Empty: continue
        if _STATE["resolved_model"]: analyze_file(name); time.sleep(3)
        _analysis_queue.task_done()

threading.Thread(target=_analysis_worker, daemon=True).start()

def sync_remote_transcriptions():
    if not GROQ_API_KEY or not _STATE["resolved_model"]: return
    try: files = list_text_files()
    except: return
    with _a_lock: analyses = load_analyses()
    for f in files:
        name = f["name"]; entry = analyses.get(name)
        if (not entry or (entry.get("estado") == "falhou" and entry.get("attempts", 0) < MAX_RETRY_ATTEMPTS)) and name not in _running:
            _analysis_queue.put(name); log.info("↓ Novo na fila: %s", name)

def daemon_loop():
    if GROQ_API_KEY:
        # Detecção simples usando o modelo definido no .env
        _STATE["resolved_model"] = GROQ_MODEL_REQ; _STATE["model_detection_done"] = True
        log.info("✓ Modelo resolvido via .env: %s", GROQ_MODEL_REQ)
    while True:
        try: sync_remote_transcriptions()
        except Exception as e: log.error("Daemon falhou: %s", e)
        time.sleep(SYNC_INTERVAL)

threading.Thread(target=daemon_loop, daemon=True).start()

# ─ ENDPOINTS FASTAPI ──
@app.get("/health")
def health():
    with _a_lock: all_a = load_analyses()
    return {"status": "ok", "service": "QA Analyser v6.2", "groq_model": _STATE["resolved_model"], "queue_size": _analysis_queue.qsize(), "stats": {"total": len(all_a), "analisado": sum(1 for v in all_a.values() if v.get("estado")=="analisado"), "falhou": sum(1 for v in all_a.values() if v.get("estado")=="falhou")}}

@app.get("/transcricoes/view/{filename}")
def transcricoes_view(filename: str):
    if not filename.endswith('.txt'): filename += '.txt'
    ssh = _connect_text()
    try:
        sftp = ssh.open_sftp(); path = _remote_text_dir(ssh) + "/" + filename
        content = sftp.open(path, "rb").read().decode("utf-8", errors="replace"); sftp.close(); return PlainTextResponse(content)
    except: raise HTTPException(404, "Transcrição não encontrada")
    finally: ssh.close()

@app.get("/pdfs/list")
def pdfs_list():
    try: files = list_pdf_files()
    except Exception as exc: raise HTTPException(502, f"Falha SFTP (PDFs): {exc}")
    with _a_lock: analyses = load_analyses()
    by_stem = {PurePosixPath(k).stem: v for k, v in analyses.items()}
    scores = []
    for f in files:
        entry = by_stem.get(f["audio"])
        if entry: f["score"] = entry.get("final_percentage"); f["classification"] = entry.get("classification"); f["analyzed_at"] = entry.get("analyzed_at"); f["model"] = entry.get("model")
        else: f["score"] = None; f["classification"] = None
        if f["score"] is not None: scores.append(f["score"])
    stats = {"total": len(files), "excellent": sum(1 for s in scores if s >= 80), "meets": sum(1 for s in scores if 60 <= s < 80), "needs_work": sum(1 for s in scores if s < 60), "avg_score": round(sum(scores) / max(len(scores), 1), 1), "without_score": len(files) - len(scores)}
    return {"source": f"{TEXT_USER}@{TEXT_HOST}:{TEXT_DIR}", "total": len(files), "stats": stats, "files": files}

@app.get("/stats/dashboard")
def stats_dashboard(date: str = None):
    target_date = (date or datetime.now().strftime('%Y-%m-%d')).strip()
    with _a_lock: all_a = load_analyses()
    hours = {h: {"success": 0, "failed": 0} for h in range(24)}
    total = ok = fail = 0; scores = []
    for _name, e in all_a.items():
        total += 1; at = e.get("analyzed_at") or ""; day = at[:10]
        if e.get("estado") == "analisado":
            ok += 1; pct = float(e.get("final_percentage") or 0); scores.append(pct)
            if day == target_date:
                try: h = datetime.strptime(at, "%Y-%m-%d %H:%M:%S").hour; hours[h]["success"] += 1
                except: pass
        else:
            fail += 1
            if day == target_date:
                try: h = datetime.strptime(at, "%Y-%m-%d %H:%M:%S").hour; hours[h]["failed"] += 1
                except: pass
    try: txts = list_text_files(); pending = sum(1 for f in txts if f["name"] not in all_a)
    except: pending = 0
    return {"date": target_date, "cards": {"total": total, "analisado": ok, "falhou": fail, "pendente": pending, "avg_score": round(sum(scores) / max(len(scores), 1), 1)}, "hourly": {"labels": [f"{h:02d}:00" for h in range(24)], "success": [hours[h]["success"] for h in range(24)], "failed": [hours[h]["failed"] for h in range(24)]}}

@app.get("/pdfs/view/{filename:path}")
def pdfs_view(filename: str):
    ssh = _connect_text()
    try:
        sftp = ssh.open_sftp(); path = _remote_pdf_dir(ssh) + "/" + filename
        size = sftp.stat(path).st_size; handle = sftp.open(path, "rb")
        def gen():
            try:
                while True:
                    d = handle.read(64*1024)
                    if not d: break
                    yield d
            finally: handle.close(); sftp.close(); ssh.close()
        return StreamingResponse(gen(), media_type="application/pdf", headers={"Content-Length": str(size), "Content-Disposition": f'inline; filename="{filename}"'})
    except: raise HTTPException(404, "PDF não encontrado")
    finally: ssh.close()

@app.get("/pdfs/download/{filename:path}")
def pdfs_download(filename: str):
    ssh = _connect_text()
    try:
        sftp = ssh.open_sftp(); path = _remote_pdf_dir(ssh) + "/" + filename
        size = sftp.stat(path).st_size; handle = sftp.open(path, "rb")
        def gen():
            try:
                while True:
                    d = handle.read(64*1024)
                    if not d: break
                    yield d
            finally: handle.close(); sftp.close(); ssh.close()
        return StreamingResponse(gen(), media_type="application/pdf", headers={"Content-Length": str(size), "Content-Disposition": f'attachment; filename="{filename}"'})
    except: raise HTTPException(404, "PDF não encontrado")
    finally: ssh.close()

if __name__ == "__main__":
    import uvicorn
    log.info("Analyser v6.2 pronta → porta 8001 | Modelo: %s", _STATE["resolved_model"])
    uvicorn.run(app, host="0.0.0.0", port=8001)
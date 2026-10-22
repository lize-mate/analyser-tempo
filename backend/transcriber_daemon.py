import os
import re
import stat
import time
import json
import shutil
import logging
import threading
import subprocess
import datetime
from pathlib import Path
from queue import Queue
import paramiko
from dotenv import load_dotenv
from watchdog.observers import Observer
from watchdog.events import FileSystemEventHandler

BASE = Path(__file__).resolve().parent
load_dotenv(BASE / ".env", override=True)

def reload_env():
    """Recarrega o .env dinamicamente para apanhar mudanças feitas via interface gráfica."""
    load_dotenv(BASE / ".env", override=True)

# ── CONFIGURAÇÃO — SERVIDOR PBX ASTERISK (fonte dos áudios) ──
REMOTE_HOST = os.getenv("AUDIOS_HOST", "192.168.4.250").strip()
REMOTE_PORT = int(os.getenv("AUDIOS_PORT", "22").strip())
REMOTE_USER = os.getenv("AUDIOS_USER", "root").strip()
REMOTE_PASS = os.getenv("AUDIOS_PASS", "Support*2008#").strip()
REMOTE_KEY  = os.getenv("AUDIOS_KEY", "").strip()

# ── SERVIDOR ANTIGO (destino das transcrições) ──
TEXT_HOST = os.getenv("TEXT_HOST", "192.168.4.55").strip()
TEXT_PORT = int(os.getenv("TEXT_PORT", "22").strip())
TEXT_USER = os.getenv("TEXT_USER", "hjit").strip()
TEXT_PASS = os.getenv("TEXT_PASS", "*Support2026#").strip()
TEXT_KEY  = os.getenv("TEXT_KEY", "").strip()

STORAGE        = BASE / "storage"
INCOMING       = STORAGE / "audio_incoming"
PROCESSED      = STORAGE / "audio_processed"
TRANSCRIPTIONS = STORAGE / "transcriptions"
FAILED         = STORAGE / "audio_failed"
INVALID_AUDIO  = STORAGE / "audio_invalid"
TMP_WAV        = STORAGE / "tmp_wav"

for _d in (INCOMING, PROCESSED, TRANSCRIPTIONS, FAILED, INVALID_AUDIO, TMP_WAV):
    _d.mkdir(parents=True, exist_ok=True)

WHISPER_MODEL = os.getenv("WHISPER_MODEL", "small").strip().replace(".pt", "")
DIARIZE       = os.getenv("DIARIZE", "false").strip().lower() == "true"
LANGUAGE      = os.getenv("LANGUAGE", "pt").strip()
SYNC_INTERVAL = int(os.getenv("SYNC_INTERVAL", "30").strip())
VOX_RATE      = int(os.getenv("VOX_RATE", "8000").strip())
AUDIO_CLEAN     = os.getenv("AUDIO_CLEAN", "true").strip().lower() == "true"
USE_NOISEREDUCE = os.getenv("USE_NOISEREDUCE", "false").strip().lower() == "true"
VALID_EXT = {".wav", ".mp3", ".m4a", ".ogg", ".flac", ".aac", ".vox"}
MIN_DURATION_SEC = 120

INITIAL_PROMPT = (
    "Howard Johnson Call Centre. Atendimento telefónico em português europeu. "
    "Agente e cliente. Bom dia, boa tarde, boa noite. Em que posso ajudar? "
    "Perfeitamente, com certeza, obrigado. Reserva, check-in, check-out, "
    "cancelamento, pagamento, cartão, multibanco, MB Way, factura, NIF."
)

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
log = logging.getLogger("transcriber")

# ── GESTÃO DE PROGRESSO E HISTÓRICO ──
queue = Queue()
BUSY  = set()
PROGRESS_FILE = STORAGE / "progress.json"
HISTORY_FILE  = STORAGE / "activity_history.json"

def set_progress(active: bool, file: str = "", **stages):
    vals = {"download": 0, "conversao": 0, "transcricao": 0, "upload": 0}
    vals.update(stages)
    data = {"active": active, "file": file, "updated": time.time(), "stages": vals}
    tmp = PROGRESS_FILE.with_suffix(".tmp")
    tmp.write_text(json.dumps(data))
    os.replace(tmp, PROGRESS_FILE)

def log_activity(message: str, level: str = "info"):
    history = []
    if HISTORY_FILE.exists():
        try: 
            history = json.loads(HISTORY_FILE.read_text())
        except Exception: 
            pass
    history.insert(0, {
        "time": time.strftime("%H:%M:%S"), 
        "date": time.strftime("%Y-%m-%d"),
        "msg": message, 
        "level": level
    })
    HISTORY_FILE.write_text(json.dumps(history[:20]))

# ── CONEXÕES SFTP ──
def connect_audio():
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    kw = dict(hostname=REMOTE_HOST, port=REMOTE_PORT, username=REMOTE_USER,
              timeout=15, allow_agent=False, look_for_keys=False,
              banner_timeout=15, auth_timeout=15)
    if REMOTE_KEY: kw["key_filename"] = REMOTE_KEY
    else:          kw["password"]     = REMOTE_PASS
    ssh.connect(**kw)
    return ssh

def connect_text():
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    kw = dict(hostname=TEXT_HOST, port=TEXT_PORT, username=TEXT_USER,
              timeout=15, allow_agent=False, look_for_keys=False,
              banner_timeout=15, auth_timeout=15)
    if TEXT_KEY: kw["key_filename"] = TEXT_KEY
    else:        kw["password"]     = TEXT_PASS
    ssh.connect(**kw)
    return ssh

def resolve_dir(ssh, path, user=None):
    path = (path or "").strip()
    if path.startswith("~"):
        try:
            _, out, _ = ssh.exec_command('eval echo %s' % path)
            resolved = out.read().decode().strip()
            if resolved and not resolved.startswith("~"): return resolved
        except Exception: pass
        resto = path[2:] if path.startswith("~/") else path[1:]
        target_user = user or REMOTE_USER
        return "/home/%s/%s" % (target_user, resto)
    return path

# ── PROCESSAMENTO DE ÁUDIO ──
def vox_to_wav(vox_path: Path, wav_path: Path) -> bool:
    try:
        import audioop, wave
        raw = vox_path.read_bytes()
        if not raw: raise ValueError("VOX vazio")
        pcm, _ = audioop.adpcm2lin(raw, 2, None)
        pcm = audioop.mul(pcm, 2, 1.5)
        with wave.open(str(wav_path), "wb") as w:
            w.setnchannels(1); w.setsampwidth(2); w.setframerate(VOX_RATE)
            w.writeframes(pcm)
        return True
    except Exception as e:
        log.error("   VOX→WAV falhou: %s", e)
        return False

def get_duration_seconds(file_path: Path) -> float:
    try:
        out = subprocess.run(
            ["ffprobe", "-v", "error", "-show_entries", "format=duration",
             "-of", "default=noprint_wrappers=1:nokey=1", str(file_path)],
            capture_output=True, text=True, timeout=10
        )
        return float(out.stdout.strip() or 0)
    except Exception:
        return 0.0

def log_invalid_audio(filename: str, duration: float):
    today = datetime.datetime.now().strftime("%Y-%m-%d")
    report_file = STORAGE / f"relatorio_invalidos_{today}.txt"
    timestamp = datetime.datetime.now().strftime("%H:%M:%S")
    duration_str = f"{duration/60:.2f} min ({duration:.1f}s)"
    line = f"[{timestamp}] {filename} - Duração: {duration_str}\n"
    try:
        if not report_file.exists():
            with open(report_file, "w", encoding="utf-8") as f:
                f.write(f"=== RELATÓRIO DIÁRIO DE ÁUDIOS INVÁLIDOS ( < {MIN_DURATION_SEC}s) ===\n")
                f.write(f"Data: {today}\n")
                f.write("-" * 65 + "\n")
        with open(report_file, "a", encoding="utf-8") as f:
            f.write(line)
        log.info("  📝 Adicionado ao relatório diário: %s", report_file.name)
    except Exception as e:
        log.error("Falha ao escrever no relatório de inválidos: %s", e)

def clean_audio(src: Path, dst: Path) -> bool:
    if not AUDIO_CLEAN:
        shutil.copy(str(src), str(dst))
        return True
    try:
        out = subprocess.run(["ffprobe", "-v", "error", "-show_entries", "format=duration",
                              "-of", "default=noprint_wrappers=1:nokey=1", str(src)],
                             capture_output=True, text=True, timeout=30)
        dur0 = float(out.stdout.strip() or 0)
        p = subprocess.run(["ffmpeg", "-hide_banner", "-i", str(src), "-af", "volumedetect", "-f", "null", "-"],
                           capture_output=True, text=True, timeout=120)
        mean = re.search(r"mean_volume:\s*(-?[\d.]+)\s*dB", p.stderr)
        vol0_mean = float(mean.group(1)) if mean else -91.0
        log.info("  🧹 Limpeza IN : %.1fs | mean %.1f dB", dur0, vol0_mean)
    except Exception:
        dur0, vol0_mean = 0, -91.0
    
    filters = ["highpass=f=200", "lowpass=f=3800", "loudnorm=I=-16:TP=-1.5:LRA=11", "aresample=16000"]
    try:
        subprocess.run(
            ["ffmpeg", "-y", "-hide_banner", "-loglevel", "error",
             "-i", str(src), "-af", ",".join(filters),
             "-ac", "1", "-ar", "16000", "-sample_fmt", "s16", str(dst)],
            check=True, capture_output=True, timeout=600
        )
    except Exception as e:
        log.warning("  ffmpeg preprocess falhou (%s) — uso do original", e)
        shutil.copy(str(src), str(dst))
        return True
    
    if USE_NOISEREDUCE:
        log.info("  ⏭️ noisereduce ignorado (usando apenas limpeza FFmpeg)")
    return True

# ── TRANSCRIÇÃO E DIARIZAÇÃO ──
USE_FASTER = True
try:
    from faster_whisper import WhisperModel
    log.info("A carregar faster-whisper '%s' (int8, CPU)...", WHISPER_MODEL)
    MODEL = WhisperModel(WHISPER_MODEL, device="cpu", compute_type="int8")
    log.info("✓ faster-whisper pronto.")
except Exception as e:
    USE_FASTER = False
    log.warning("⚠ faster-whisper indisponível (%s) — a usar openai-whisper", e)
    import whisper
    log.info("A carregar openai-whisper '%s'...", WHISPER_MODEL)
    MODEL = whisper.load_model(WHISPER_MODEL)
    log.info("✓ openai-whisper pronto.")

def transcribe(wav: Path):
    if USE_FASTER:
        segments, _info = MODEL.transcribe(
            str(wav), language=LANGUAGE, beam_size=5, best_of=5, temperature=0.0,
            condition_on_previous_text=False, repetition_penalty=1.2, no_repeat_ngram_size=3,
            vad_filter=True, vad_parameters=dict(min_silence_duration_ms=250, speech_pad_ms=200),
            no_speech_threshold=0.55, compression_ratio_threshold=2.2, initial_prompt=INITIAL_PROMPT,
        )
        return [{"start": s.start, "end": s.end, "text": (s.text or "").strip()} for s in segments]
    else:
        res = MODEL.transcribe(
            str(wav), language=LANGUAGE, fp16=False, condition_on_previous_text=False, temperature=0.0,
            no_speech_threshold=0.55, compression_ratio_threshold=2.2, initial_prompt=INITIAL_PROMPT
        )
        return [{"start": s["start"], "end": s["end"], "text": (s["text"] or "").strip()} for s in res["segments"]]

def _norm(t: str) -> str:
    return re.sub(r"\s+", " ", t).strip().lower().rstrip("…,;!?")

def dedup_segments(segs):
    out, prev = [], None
    for s in segs:
        n = _norm(s["text"])
        if not n or n == prev: continue
        toks = n.split()
        if len(toks) >= 6:
            h = len(toks) // 2
            if toks[:h] == toks[h:2*h]: continue
        if len(toks) >= 4 and len(set(toks)) == 1: continue
        out.append(s); prev = n
    return out

def diarize(wav: Path, segs):
    if not DIARIZE or len(segs) < 2:
        return ["Agente"] * len(segs)
    try:
        import torch
        import soundfile as sf
        import numpy as np
        from sklearn.cluster import AgglomerativeClustering
        try:
            from speechbrain.inference.speaker import EncoderClassifier
        except Exception:
            from speechbrain.pretrained import EncoderClassifier
        log.info("  A carregar modelo de diarização (ECAPA-TDNN)...")
        ecapa = EncoderClassifier.from_hparams(
            source="speechbrain/spkrec-ecapa-voxceleb", savedir=str(STORAGE / "ecapa"), run_opts={"device": "cpu"}
        )
        audio, sr = sf.read(str(wav))
        if audio.ndim > 1: audio = audio.mean(axis=1)
        embs, valid = [], []
        for i, s in enumerate(segs):
            a, b = int(s["start"] * sr), int(s["end"] * sr)
            clip = audio[a:b]
            if len(clip) < int(sr * 0.75): continue
            with torch.no_grad():
                e = ecapa.encode_batch(torch.tensor(clip, dtype=torch.float32).unsqueeze(0))
                embs.append(e.squeeze().numpy())
                valid.append(i)
        if len(embs) < 2:
            return ["Agente"] * len(segs)
        X = np.stack(embs)
        labels = AgglomerativeClustering(n_clusters=2, metric="cosine", linkage="average").fit_predict(X)
        lab_of = {vi: labels[k] for k, vi in enumerate(valid)}
        first = lab_of.get(0, 0)
        names, last = [], "Agente"
        for i in range(len(segs)):
            lab = lab_of.get(i)
            if lab is None: names.append(last)
            else:
                last = "Agente" if lab == first else "Cliente"
                names.append(last)
        log.info("  ✓ Diarização concluída")
        return names
    except Exception as e:
        log.warning("⚠ Diarização falhou (%s) — a usar mono-locutor", e)
        return ["Agente"] * len(segs)

def merge_turns(segs, speakers):
    turns = []
    for s, sp in zip(segs, speakers):
        if turns and turns[-1][0] == sp:
            turns[-1][1] += " " + s["text"]
        else:
            turns.append([sp, s["text"]])
    return turns

def format_transcription_visual(turns):
    lines = []
    for speaker, text in turns:
        if speaker == "Agente":
            lines.append(f"[Agente] {text}")
        else:
            lines.append(f"- {text}")
    return "\n".join(lines)

# ── LÓGICA DE DIRETÓRIO E SINCRONIZAÇÃO ──
def get_active_audio_dir():
    """Retorna APENAS o diretório do dia atual, lendo dinamicamente do .env."""
    reload_env()
    raw_dir = os.getenv("AUDIO_DIR") or os.getenv("AUDIOS_DIR") or os.getenv("AUDIO_BASE_DIR", "/var/spool/asterisk/monitor").strip()
    match = re.search(r'(.+?)(?:/\d{2,4}/\d{1,2}/\d{1,2})/?$', raw_dir)
    base_dir = match.group(1) if match else raw_dir
    now = datetime.datetime.now()
    return os.path.join(base_dir, now.strftime("%Y/%m/%d"))

def sync_remote_audios():
    reload_env()
    ssh = connect_audio()
    sftp = ssh.open_sftp()
    rdir_base = get_active_audio_dir()
    rdir = resolve_dir(ssh, rdir_base, REMOTE_USER)
    try:
        attrs = sftp.listdir_attr(rdir)
    except (FileNotFoundError, IOError):
        log.info("  ⏳ Pasta de hoje ainda não criada pelo PBX: %s — a aguardar próximo ciclo...", rdir)
        sftp.close()
        ssh.close()
        return
    
    for attr in attrs:
        if attr.st_mode and stat.S_ISDIR(attr.st_mode): continue
        ext = Path(attr.filename).suffix.lower()
        if ext not in VALID_EXT: continue
        stem = Path(attr.filename).stem
        local = INCOMING / attr.filename
        done_a = PROCESSED / attr.filename
        done_t = TRANSCRIPTIONS / (stem + ".txt")
        failed = FAILED / attr.filename
        invalid = INVALID_AUDIO / attr.filename
        
        if local.exists() or done_a.exists() or done_t.exists() or failed.exists() or invalid.exists(): 
            continue
            
        log.info("↓ A descarregar: %s (de %s)", attr.filename, rdir)
        log_activity(f"A descarregar: {attr.filename}", "info")
        set_progress(True, attr.filename, download=40)
        sftp.get(f"{rdir}/{attr.filename}", str(local))
        set_progress(True, attr.filename, download=100)
        log_activity(f"Download concluído: {attr.filename}", "success")
    sftp.close()
    ssh.close()

def wait_stable(p, tries=20):
    last = -1
    for _ in range(tries):
        try: sz = p.stat().st_size
        except OSError: return False
        if sz == last and sz > 0: return True
        last = sz; time.sleep(1)
    return False

def process_audio(p: Path):
    name = p.name
    stem = p.stem
    txt_path = TRANSCRIPTIONS / (stem + ".txt")
    
    if txt_path.exists():
        log.info("⏭️ Já transcrito anteriormente, a ignorar definitivamente: %s", name)
        if p.exists():
            try: shutil.move(str(p), str(PROCESSED / name))
            except Exception: p.rename(p.with_suffix(p.suffix + ".done"))
        return

    log.info("▶ A processar: %s", name)
    log_activity(f"A iniciar processamento: {name}", "info")
    raw_wav = TMP_WAV / (stem + ".raw.wav")
    pp_wav  = TMP_WAV / (stem + ".16k.wav")
    
    try:
        set_progress(True, name, download=100, conversao=40)
        if p.suffix.lower() == ".vox":
            if not vox_to_wav(p, raw_wav): 
                raise ValueError("Falha na conversão VOX")
        else:
            shutil.copy(str(p), str(raw_wav))
            
        duration = get_duration_seconds(raw_wav)
        if duration < MIN_DURATION_SEC:
            log.warning("⚠ Áudio inválido (duração %.1fs < %ds): %s", duration, MIN_DURATION_SEC, name)
            log_invalid_audio(name, duration)
            dest_invalid = INVALID_AUDIO / name
            if p.exists():
                try: shutil.move(str(p), str(dest_invalid))
                except Exception: p.rename(p.with_suffix(p.suffix + ".invalid"))
            log.info("  ↪ Movido/bloqueado como inválido ")
            set_progress(False)
            return
            
        if not clean_audio(raw_wav, pp_wav):
            raise ValueError("Limpeza falhou ou áudio sem voz audível")
        if not pp_wav.exists() or pp_wav.stat().st_size < 1024:
            raise ValueError("WAV limpo vazio")
            
        set_progress(True, name, download=100, conversao=100, transcricao=40)
        segs = dedup_segments(transcribe(pp_wav))
        if not segs:
            raise ValueError("Sem fala detectada (após VAD/dedup)")
            
        speakers = diarize(pp_wav, segs)
        turns = merge_turns(segs, speakers)
        final_text = format_transcription_visual(turns)
        log.info("  ✓ %d segmentos → %d turnos", len(segs), len(turns))
        txt_path.write_text(final_text, encoding="utf-8")
        log.info("  ✓ Transcrição: %d caracteres", len(final_text))
        log_activity(f"Transcrição gerada: {name}", "success")
        set_progress(True, name, download=100, conversao=100, transcricao=100, upload=40)
        
        # Recarrega o .env para garantir que temos o TEXT_DIR mais recente da interface gráfica
        reload_env()
        ssh = connect_text()
        sftp = ssh.open_sftp()
        tdir = resolve_dir(ssh, os.getenv("TEXT_DIR", "~/transcricoes"), TEXT_USER)
        try: sftp.stat(tdir)
        except IOError: sftp.mkdir(tdir)
        sftp.put(str(txt_path), f"{tdir}/{txt_path.name}")
        sftp.close()
        ssh.close()
        
        log.info("  ✓ .txt enviado para %s@%s:%s", TEXT_USER, TEXT_HOST, os.getenv("TEXT_DIR", "~/transcricoes"))
        log_activity(f"Upload concluído: {name}", "success")
        set_progress(True, name, download=100, conversao=100, transcricao=100, upload=100)
        time.sleep(1.5)
        
        dest = PROCESSED / name
        if dest.exists():
            dest = PROCESSED / ("%d_%s" % (int(time.time()), name))
        shutil.move(str(p), str(dest))
        log.info("✓ Concluído: %s", name)
        
    except Exception as e:
        log.error("✗ FALHA CRÍTICA (SEM RETRY) (%s): %s", name, e)
        log_activity(f"Falha crítica definitiva: {name} - {str(e)}", "error")
        dest_fail = FAILED / name
        if p.exists():
            try: shutil.move(str(p), str(dest_fail))
            except Exception: p.rename(p.with_suffix(p.suffix + ".failed"))
        log.warning("  ↪ Movido/bloqueado como falha definitiva")
    finally:
        for t in (raw_wav, pp_wav):
            if t.exists():
                try: t.unlink()
                except Exception: pass
        set_progress(False)

# ── WORKER E OBSERVER ──
def worker():
    while True:
        p = queue.get()
        try:
            if p.name not in BUSY and wait_stable(p):
                BUSY.add(p.name)
                process_audio(p)
        except Exception:
            log.exception("Erro no worker (%s)", p.name)
        finally:
            BUSY.discard(p.name)
            queue.task_done()

class Handler(FileSystemEventHandler):
    def on_created(self, ev):
        if ev.is_directory: return
        p = Path(ev.src_path)
        if p.suffix.lower() in VALID_EXT: 
            queue.put(p)

def sweep():
    for p in INCOMING.iterdir():
        if p.suffix.lower() in VALID_EXT and not (TRANSCRIPTIONS / (p.stem + ".txt")).exists():
            queue.put(p)

def main():
    today_str = datetime.datetime.now().strftime("%d/%m/%Y")
    log.info("=" * 60)
    log.info("  Transcriber v5.0 (FOCO DIÁRIO + NO-RETRY) ")
    log.info("  Data alvo atual: %s", today_str)
    log.info("  Duração mínima: %d segundos | Inválidos em: %s", MIN_DURATION_SEC, INVALID_AUDIO.name)
    log.info("  Áudios (PBX): %s@%s:%d:%s (UMA pasta por vez)", REMOTE_USER, REMOTE_HOST, REMOTE_PORT, os.getenv("AUDIO_DIR") or os.getenv("AUDIOS_DIR", "/var/spool/asterisk/monitor"))
    log.info("  Txts (Servidor): %s@%s:%d:%s", TEXT_USER, TEXT_HOST, TEXT_PORT, os.getenv("TEXT_DIR", "~/transcricoes"))
    log.info("  POLÍTICA: Muda de pasta apenas à meia-noite. Zero retries. ")
    log.info("=" * 60)
    
    # Agora log_activity está devidamente definida antes desta chamada
    log_activity(f"Transcriber v5.0 iniciado (Foco: {today_str})", "info")
    
    threading.Thread(target=worker, daemon=True).start()
    obs = Observer()
    obs.schedule(Handler(), str(INCOMING), recursive=False)
    obs.start()
    
    while True:
        try:
            sync_remote_audios()
            sweep()
        except Exception as e:
            log.error("Sync falhou: %s", e)
        time.sleep(SYNC_INTERVAL)

if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        log.info("A parar.")
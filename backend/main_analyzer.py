python @app.get("/transcricoes/status")
def transcricoes_status():
    """
    Devolve o estado de cada áudio:
    - 'analisado': transcrição existe em ~/text_files (remoto)
    - 'pendente': áudio em ~/Recordings mas sem transcrição ainda
    - 'processando': áudio a ser transcrito agora (progress.json ativo)
    - 'falhou': áudio em storage/audio_failed/ local
    """
    import json as _json
    
    # 1. Transcrições bem-sucedidas (remotas)
    try:
        ssh = _connect()
        sftp = ssh.open_sftp()
        remote_text_dir = _remote_dir(ssh).replace("Recordings", "text_files")
        successful = set()
        try:
            for attr in sftp.listdir_attr(remote_text_dir):
                if attr.filename.lower().endswith(".txt"):
                    successful.add(PurePosixPath(attr.filename).stem)
        except Exception:
            pass
        sftp.close()
        ssh.close()
    except Exception:
        successful = set()
    
    # 2. Áudios que falharam (locais)
    failed_dir = BASE / "storage" / "audio_failed"
    failed = {}
    if failed_dir.exists():
        for p in failed_dir.iterdir():
            if p.is_file() and p.suffix.lower() in FORMATS:
                failed[p.stem] = "Falha na transcrição (áudio sem voz ou formato inválido)"
    
    # 3. Áudio em processamento agora
    processing = None
    progress_file = BASE / "storage" / "progress.json"
    if progress_file.exists():
        try:
            data = _json.loads(progress_file.read_text())
            if data.get("active") and time.time() - data.get("updated", 0) < 120:
                processing = PurePosixPath(data.get("file", "")).stem
        except Exception:
            pass
    
    # 4. Áudios pendentes (em ~/Recordings mas sem transcrição)
    try:
        files, _ = list_files(force=False)
        pending = []
        for f in files:
            stem = PurePosixPath(f["name"]).stem
            if stem not in successful and stem not in failed and stem != processing:
                pending.append(stem)
    except Exception:
        pending = []
    
    return {
        "analisado": list(successful),
        "pendente": pending,
        "processando": processing,
        "falhou": failed  # dict: {stem: motivo}
    }
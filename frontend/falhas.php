<?php
require_once 'includes/config.php';
require_once 'includes/api.php';
requireLogin();
$pageTitle     = 'Falhas de Análise';
$activeSection = 'falhas';

function fl_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ══ CARREGAR ANÁLISES (para obter estado e motivo) ═══ */
$analyses = [];
$analysesFile = __DIR__ . '/../backend/storage/analyses.json';
if (file_exists($analysesFile)) {
    $analyses = json_decode(file_get_contents($analysesFile), true) ?: [];
}

/* ═══ LISTAR TRANSCRIÇÕES FALHADAS ═══ */
$transDir = __DIR__ . '/../backend/storage/transcriptions';
$transFalhas = [];
if (is_dir($transDir)) {
    foreach (scandir($transDir) as $file) {
        if (!preg_match('/\.txt$/i', $file)) continue;
        $stem = pathinfo($file, PATHINFO_FILENAME);
        $path = $transDir . '/' . $file;
        
        // Procurar no analyses.json
        $estado = 'pendente';
        $motivo = '';
        foreach ($analyses as $key => $entry) {
            if (strpos($key, $stem) === 0 || strpos($stem, pathinfo($key, PATHINFO_FILENAME)) === 0) {
                $estado = $entry['estado'] ?? 'pendente';
                $motivo = $entry['motivo'] ?? '';
                break;
            }
        }
        
        // Só incluir se falhou
        if ($estado === 'falhou') {
            $transFalhas[] = [
                'name'     => $file,
                'stem'     => $stem,
                'motivo'   => $motivo ?: 'Falha desconhecida',
                'modified' => date('Y-m-d H:i:s', filemtime($path)),
                'size'     => filesize($path),
                'type'     => 'transcricao',
            ];
        }
    }
    usort($transFalhas, fn($a, $b) => strcmp($b['modified'], $a['modified']));
}

/* ═══ LISTAR ÁUDIOS FALHADOS ═══ */
$audioDir = __DIR__ . '/../backend/storage/audio_failed';
$audioFalhas = [];
if (is_dir($audioDir)) {
    foreach (scandir($audioDir) as $file) {
        if (!preg_match('/\.(mp3|wav|m4a|ogg|flac|aac|vox)$/i', $file)) continue;
        $stem = pathinfo($file, PATHINFO_FILENAME);
        $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $path = $audioDir . '/' . $file;
        
        $motivo = 'Erro no processamento';
        if (stripos($file, 'vox') !== false && $ext !== 'vox') $motivo = 'Ficheiro inválido';
        elseif (stripos($file, 'empty') !== false || stripos($file, 'silence') !== false) $motivo = 'Voz não detectada';
        
        // Cruzar com analyses.json
        foreach ($analyses as $key => $entry) {
            if (strpos($key, $stem) === 0) {
                if (($entry['estado'] ?? '') === 'falhou') {
                    $motivo = $entry['motivo'] ?? $motivo;
                }
                break;
            }
        }
        
        $audioFalhas[] = [
            'name'     => $file,
            'stem'     => $stem,
            'ext'      => $ext,
            'motivo'   => $motivo,
            'modified' => date('Y-m-d H:i:s', filemtime($path)),
            'size'     => filesize($path),
            'type'     => 'audio',
        ];
    }
    usort($audioFalhas, fn($a, $b) => strcmp($b['modified'], $a['modified']));
}

/* ═══ FUNÇÕES AUXILIARES ═══ */
function fl_format_size($bytes): string {
    $bytes = (float)$bytes;
    if ($bytes <= 0) return '—';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) { $bytes /= 1024; $i++; }
    return round($bytes, $i === 0 ? 0 : 1) . ' ' . $units[$i];
}

function fl_motivo_badge(string $motivo): array {
    $m = strtolower($motivo);
    if (strpos($m, 'curta') !== false || strpos($m, 'short') !== false) {
        return ['badge-needs', 'Transcrição curta', 'bi-file-text'];
    }
    if (strpos($m, 'servidor') !== false || strpos($m, 'server') !== false || strpos($m, 'sftp') !== false) {
        return ['badge-needs', 'Erro do servidor', 'bi-hdd-network'];
    }
    if (strpos($m, 'voz') !== false || strpos($m, 'speech') !== false || strpos($m, 'silence') !== false) {
        return ['badge-needs', 'Voz não detectada', 'bi-mic-mute'];
    }
    if (strpos($m, 'inválid') !== false || strpos($m, 'invalid') !== false || strpos($m, 'format') !== false) {
        return ['badge-needs', 'Ficheiro inválido', 'bi-file-exclamation'];
    }
    if (strpos($m, 'groq') !== false || strpos($m, 'api') !== false || strpos($m, '429') !== false) {
        return ['badge-needs', 'Erro de API (Groq)', 'bi-cloud-slash'];
    }
    return ['badge-default', 'Falha desconhecida', 'bi-question-circle'];
}

include 'includes/header.php';
?>
<style>
/* ══════════════════════════════════════════════════════════════════
FALHAS — ESTILO RELATÓRIOS (REUTILIZADO)
══════════════════════════════════════════════════════════════════ */
.fl-toolbar { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
.fl-search { position: relative; flex: 1; min-width: 280px; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 0 16px; height: 46px; display: flex; align-items: center; gap: 10px; transition: var(--trans); }
.fl-search:focus-within { border-color: var(--accent); box-shadow: 0 0 0 4px var(--accent-soft); }
.fl-search i { color: var(--text3); font-size: 15px; flex-shrink: 0; }
.fl-search input { flex: 1; border: 0; outline: 0; background: transparent; color: var(--text); font-family: var(--font-body); font-size: 14px; }
.fl-search input::placeholder { color: var(--text3); }

.fl-chips { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 18px; }
.fl-chip { border: 1px solid var(--border); background: var(--surface); color: var(--text2); padding: 8px 16px; border-radius: 999px; font-size: 13px; font-weight: 600; cursor: pointer; transition: var(--trans); display: inline-flex; align-items: center; gap: 7px; font-family: var(--font-body); }
.fl-chip i { font-size: 14px; }
.fl-chip:hover { border-color: var(--accent); color: var(--text); transform: translateY(-1px); }
.fl-chip.active { background: var(--red); color: #fff; border-color: var(--red); box-shadow: 0 4px 12px rgba(240, 80, 110, 0.35); }
.fl-chip .chip-count { background: rgba(255,255,255,0.25); padding: 2px 7px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.fl-chip:not(.active) .chip-count { background: var(--surface2); color: var(--text3); }

.fl-list { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; }
.fl-list-head { display: grid; grid-template-columns: 2.5fr 2fr 150px; gap: 16px; padding: 14px 22px; border-bottom: 1px solid var(--border); font-size: 11px; letter-spacing: 0.09em; text-transform: uppercase; color: var(--text3); font-weight: 700; background: var(--bg3); }
.fl-row { display: grid; grid-template-columns: 2.5fr 2fr 150px; gap: 16px; align-items: center; padding: 14px 22px; border-bottom: 1px solid var(--border); transition: background 0.18s; }
.fl-row:last-child { border-bottom: none; }
.fl-row:hover { background: var(--red-soft); }

.fl-c-file { display: flex; align-items: center; gap: 12px; min-width: 0; }
.fl-file-icon { width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center; font-size: 18px; flex-shrink: 0; }
.fl-file-icon.txt { background: var(--red-soft); color: var(--red); }
.fl-file-icon.audio { background: var(--amber-soft); color: var(--amber); }
.fl-file-info { min-width: 0; flex: 1; }
.fl-file-name { font-weight: 600; font-size: 13.5px; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 3px; }
.fl-file-sub { font-size: 11px; color: var(--text3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; gap: 5px; }
.fl-file-sub i { font-size: 11px; flex-shrink: 0; }

.fl-c-motivo { display: flex; align-items: center; gap: 8px; }
.fl-motivo-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
.badge-needs { background: var(--red-soft); color: var(--red); border: 1px solid rgba(240, 80, 110, 0.3); }
.badge-default { background: var(--surface2); color: var(--text3); border: 1px solid var(--border); }
.fl-motivo-text { font-size: 12px; color: var(--text3); line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

.fl-c-actions { display: flex; gap: 6px; justify-content: flex-end; align-items: center; }
.fl-btn { display: inline-flex; align-items: center; gap: 6px; padding: 0 12px; height: 34px; background: var(--bg3); border: 1px solid var(--border); border-radius: 9px; font-size: 12px; font-weight: 500; color: var(--text2); cursor: pointer; transition: var(--trans); font-family: var(--font-body); white-space: nowrap; }
.fl-btn:hover { background: var(--surface2); color: var(--text); transform: translateY(-1px); }
.fl-btn.fl-view { background: var(--accent-soft); border-color: rgba(108,99,255,.3); color: var(--accent); }
.fl-btn.fl-view:hover { background: var(--accent); color: #fff; }
.fl-btn.fl-play { background: var(--amber-soft); border-color: rgba(245,166,35,.3); color: var(--amber); }
.fl-btn.fl-play:hover { background: var(--amber); color: #fff; }
.fl-btn.fl-retry { background: var(--green-soft); border-color: rgba(34,197,139,.3); color: var(--green); }
.fl-btn.fl-retry:hover { background: var(--green); color: #fff; }

.fl-empty { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 50px 20px; text-align: center; }
.fl-empty-icon { width: 66px; height: 66px; border-radius: 18px; background: var(--red-soft); color: var(--red); display: grid; place-items: center; margin: 0 auto 16px; font-size: 26px; }
.fl-empty h5 { color: var(--text); font-weight: 700; margin-bottom: 6px; }
.fl-empty p { color: var(--text3); margin: 0; }

[data-theme="light"] .fl-list, [data-theme="light"] .fl-search { background: #fff; border-color: #e5e5ef; }
[data-theme="light"] .fl-row { border-bottom-color: #f0f0f6; }
[data-theme="light"] .fl-row:hover { background: rgba(240, 80, 110, 0.06); }

@media (max-width: 768px) {
    .fl-list-head { display: none; }
    .fl-row { display: grid; grid-template-columns: 1fr; gap: 8px; padding: 14px 16px; }
    .fl-c-actions { justify-content: flex-start; padding-top: 10px; border-top: 1px solid var(--border); }
}
</style>

<!-- Toolbar: pesquisa + chips -->
<div class="fl-toolbar">
    <div class="fl-search">
        <i class="bi bi-search"></i>
        <input type="text" id="flSearchInput" placeholder="Pesquisar por ficheiro ou motivo..." autocomplete="off">
    </div>
</div>

<div class="fl-chips" style="margin-bottom:14px">
    <button class="fl-chip active" data-filter="all" onclick="flFilter('all', this)">
        <i class="bi bi-exclamation-triangle-fill"></i> Todas as falhas
        <span class="chip-count" id="countAll"><?= count($transFalhas) + count($audioFalhas) ?></span>
    </button>
    <button class="fl-chip" data-filter="transcricao" onclick="flFilter('transcricao', this)">
        <i class="bi bi-file-earmark-text"></i> Transcrições falhadas
        <span class="chip-count" id="countTxt"><?= count($transFalhas) ?></span>
    </button>
    <button class="fl-chip" data-filter="audio" onclick="flFilter('audio', this)">
        <i class="bi bi-music-note-beamed"></i> Áudios falhados
        <span class="chip-count" id="countAud"><?= count($audioFalhas) ?></span>
    </button>
</div>

<!-- Lista -->
<div class="fl-list" style="max-height: calc(100vh - 280px); overflow-y: auto;">
    <div class="fl-list-head">
        <div>Ficheiro</div>
        <div>Motivo da Falha</div>
        <div style="text-align:right">Acções</div>
    </div>

    <?php if (empty($transFalhas) && empty($audioFalhas)): ?>
        <div class="fl-empty">
            <div class="fl-empty-icon"><i class="bi bi-check-circle-fill"></i></div>
            <h5>Sem falhas registadas</h5>
            <p>Todas as transcrições e áudios foram processados com sucesso.</p>
        </div>
    <?php else: ?>

        <!-- TRANSCRIÇÕES FALHADAS -->
        <?php foreach ($transFalhas as $t):
            $badge = fl_motivo_badge($t['motivo']);
            $ts = strtotime($t['modified']);
            $dateMain = $ts ? date('d/m/Y', $ts) : '—';
            $dateSub  = $ts ? date('H:i', $ts) : '';
            $search = mb_strtolower($t['name'] . ' ' . $t['motivo']);
        ?>
        <div class="fl-row fl-row-transcricao" data-type="transcricao" data-search="<?= fl_h($search) ?>">
            <div class="fl-c-file">
                <div class="fl-file-icon txt"><i class="bi bi-file-earmark-text-fill"></i></div>
                <div class="fl-file-info">
                    <div class="fl-file-name" title="<?= fl_h($t['name']) ?>"><?= fl_h($t['name']) ?></div>
                    <div class="fl-file-sub">
                        <i class="bi bi-file-earmark"></i> transcrição
                        <span style="color: var(--text3)">·</span>
                        <i class="bi bi-calendar3"></i> <?= fl_h($dateMain) ?>
                        <span style="color: var(--text3)">·</span>
                        <i class="bi bi-clock"></i> <?= fl_h($dateSub) ?>
                    </div>
                </div>
            </div>
            <div class="fl-c-motivo">
                <span class="fl-motivo-badge <?= $badge[0] ?>">
                    <i class="bi <?= $badge[2] ?>"></i>
                    <?= fl_h($badge[1]) ?>
                </span>
                <span class="fl-motivo-text" title="<?= fl_h($t['motivo']) ?>"><?= fl_h($t['motivo']) ?></span>
            </div>
            <div class="fl-c-actions">
                <button type="button" class="fl-btn fl-view" onclick="openTranscricaoModal('<?= fl_h($t['stem']) ?>', '<?= fl_h($t['name']) ?>')">
                    <i class="bi bi-eye"></i> Ver
                </button>
                <button type="button" class="fl-btn fl-retry" onclick="retryAnalise('<?= fl_h($t['name']) ?>', 'transcricao')">
                    <i class="bi bi-arrow-clockwise"></i> Reanalisar
                </button>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- ÁUDIOS FALHADOS -->
        <?php foreach ($audioFalhas as $a):
            $badge = fl_motivo_badge($a['motivo']);
            $ts = strtotime($a['modified']);
            $dateMain = $ts ? date('d/m/Y', $ts) : '—';
            $dateSub  = $ts ? date('H:i', $ts) : '';
            $search = mb_strtolower($a['name'] . ' ' . $a['motivo']);
            $audioUrl = 'backend/storage/audio_failed/' . urlencode($a['name']);
        ?>
        <div class="fl-row fl-row-audio" data-type="audio" data-search="<?= fl_h($search) ?>">
            <div class="fl-c-file">
                <div class="fl-file-icon audio"><i class="bi bi-music-note-beamed"></i></div>
                <div class="fl-file-info">
                    <div class="fl-file-name" title="<?= fl_h($a['name']) ?>"><?= fl_h($a['name']) ?></div>
                    <div class="fl-file-sub">
                        <i class="bi bi-music-note"></i> áudio · <?= strtoupper(fl_h($a['ext'])) ?>
                        <span style="color: var(--text3)">·</span>
                        <i class="bi bi-calendar3"></i> <?= fl_h($dateMain) ?>
                        <span style="color: var(--text3)">·</span>
                        <i class="bi bi-clock"></i> <?= fl_h($dateSub) ?>
                    </div>
                </div>
            </div>
            <div class="fl-c-motivo">
                <span class="fl-motivo-badge <?= $badge[0] ?>">
                    <i class="bi <?= $badge[2] ?>"></i>
                    <?= fl_h($badge[1]) ?>
                </span>
                <span class="fl-motivo-text" title="<?= fl_h($a['motivo']) ?>"><?= fl_h($a['motivo']) ?></span>
            </div>
            <div class="fl-c-actions">
                <button type="button" class="fl-btn fl-play" onclick="openAudioModal('<?= fl_h($a['stem']) ?>', '<?= fl_h($audioUrl) ?>')">
                    <i class="bi bi-play-circle"></i> Ouvir
                </button>
                <button type="button" class="fl-btn fl-retry" onclick="retryAnalise('<?= fl_h($a['name']) ?>', 'audio')">
                    <i class="bi bi-arrow-clockwise"></i> Reanalisar
                </button>
            </div>
        </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div>

<div class="fl-empty d-none mt-3" id="flNoResults">
    <div class="fl-empty-icon"><i class="bi bi-search"></i></div>
    <h5>Sem resultados</h5>
    <p>Tente outro termo ou remova o filtro activo.</p>
</div>

<!-- ══ MODAL: TRANSCRIÇÃO ══ -->
<div class="modal fade" id="transcricaoModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="background:var(--surface);border:1px solid var(--border);border-radius:16px; overflow:hidden;">
            <div class="modal-header" style="border-bottom:1px solid var(--border);padding:16px 20px; display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <h5 class="modal-title" id="transcricaoTitle" style="font-family:var(--font-display);font-size:15px;font-weight:700;color:var(--text);margin:0; padding-right:30px;">Transcrição</h5>
                    <small id="transcricaoSub" style="color:var(--text3);font-size:11px;">Transcrição da chamada (falhada)</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="position:absolute; top:16px; right:16px; z-index:10;"></button>
            </div>
            <div class="modal-body" style="padding:20px; max-height:60vh; overflow-y:auto; background:var(--bg3);">
                <pre id="transcricaoContent" style="white-space:pre-wrap; font-family:var(--font-body); font-size:13px; line-height:1.7; color:var(--text); margin:0;">A carregar...</pre>
            </div>
        </div>
    </div>
</div>

<!-- ═══ MODAL: ÁUDIO ═══ -->
<div class="modal fade" id="audioModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
        <div class="modal-content" style="background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:30px;text-align:center; position:relative;">
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="pauseAudio()" style="position:absolute; top:15px; right:15px; z-index:10;"></button>
            <div style="width:70px;height:70px;border-radius:50%;background:var(--amber-soft);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                <i class="bi bi-exclamation-triangle-fill" style="font-size:28px;color:var(--amber);"></i>
            </div>
            <h5 id="audioTitle" style="font-family:var(--font-display);font-size:16px;font-weight:700;color:var(--text);margin:0 0 4px;word-break:break-all;">—</h5>
            <small id="audioMeta" style="color:var(--text3);font-size:12px;display:block;margin-bottom:20px;">Áudio falhado</small>
            <audio id="audioPlayer" controls controlsList="nodownload" oncontextmenu="return false;" style="width:100%;border-radius:8px;">
                O seu browser não suporta áudio.
            </audio>
        </div>
    </div>
</div>

<script>
(function () {
    const input     = document.getElementById('flSearchInput');
    const rows      = Array.from(document.querySelectorAll('.fl-row'));
    const noResults = document.getElementById('flNoResults');
    let activeFilter = 'all';

    function applyFilters() {
        const q = (input?.value || '').toLowerCase().trim();
        let visible = 0;
        rows.forEach(row => {
            const type    = row.dataset.type;
            const search  = row.dataset.search || '';
            const matchF  = activeFilter === 'all' || type === activeFilter;
            const matchQ  = !q || search.includes(q);
            const show    = matchF && matchQ;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        if (noResults) noResults.classList.toggle('d-none', visible > 0);
    }

    window.flFilter = function (filter, btn) {
        activeFilter = filter;
        document.querySelectorAll('.fl-chip').forEach(c => c.classList.remove('active'));
        if (btn) btn.classList.add('active');
        applyFilters();
    };

    if (input) input.addEventListener('input', applyFilters);

    /* ── MODAL: TRANSCRIÇÃO ── */
    window.openTranscricaoModal = function(stem, name) {
        const modal   = document.getElementById('transcricaoModal');
        const title   = document.getElementById('transcricaoTitle');
        const sub     = document.getElementById('transcricaoSub');
        const content = document.getElementById('transcricaoContent');
        const txtUrl  = 'backend/storage/transcriptions/' + encodeURIComponent(stem + '.txt');

        title.textContent = name;
        sub.textContent   = 'Transcrição da chamada (falhada)';
        content.textContent = 'A carregar...';
        bootstrap.Modal.getOrCreateInstance(modal).show();

        fetch(txtUrl)
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.text();
            })
            .then(txt => {
                if (!txt.trim()) {
                    content.textContent = '(ficheiro vazio)';
                    return;
                }
                const lines = txt.split('\n').filter(l => l.trim());
                content.innerHTML = lines.map((line, i) => {
                    const match = line.match(/^\[([^\]]+)\]\s*(.*)/);
                    if (match) {
                        return `<div style="margin-bottom:8px;padding:6px 10px;border-radius:8px;background:${i % 2 === 0 ? 'rgba(255,255,255,0.05)' : 'transparent'}">
                            <strong style="color:var(--red);font-size:11px;text-transform:uppercase;">${match[1]}</strong>
                            <p style="margin:4px 0 0;font-size:13px;">${match[2]}</p>
                        </div>`;
                    }
                    return `<p style="margin:4px 0;font-size:13px;">${line}</p>`;
                }).join('');
            })
            .catch(() => {
                content.textContent = 'Erro ao carregar transcrição. O ficheiro .txt pode estar corrompido.';
            });
    };

    /* ── MODAL: ÁUDIO ── */
    window.openAudioModal = function(stem, url) {
        const modal  = document.getElementById('audioModal');
        const title  = document.getElementById('audioTitle');
        const meta   = document.getElementById('audioMeta');
        const player = document.getElementById('audioPlayer');

        title.textContent = stem;
        meta.textContent  = 'A carregar áudio falhado...';
        player.pause();
        player.currentTime = 0;
        player.src = url;
        player.load();
        player.onloadedmetadata = function() {
            const duration = formatDuration(player.duration);
            const ext = player.currentSrc.includes('.wav') ? 'WAV' : 'MP3';
            meta.textContent = ext + ' · ' + duration + ' · Falha no processamento';
        };
        player.onerror = function() {
            meta.textContent = 'Áudio não encontrado ou formato não suportado.';
        };
        bootstrap.Modal.getOrCreateInstance(modal).show();
    };

    window.pauseAudio = function() {
        const player = document.getElementById('audioPlayer');
        if (player) { player.pause(); player.currentTime = 0; }
    };
    document.getElementById('audioModal')?.addEventListener('hidden.bs.modal', function() {
        pauseAudio();
    });

    /* ── REANALISAR ─ */
    window.retryAnalise = function(filename, type) {
        if (!confirm('Deseja reenviar "' + filename + '" para análise?')) return;
        
        const API_URL = '<?= API_BASE ?>';
        const endpoint = type === 'transcricao' 
            ? API_URL + '/analise/' + encodeURIComponent(filename)
            : API_URL + '/analise/retry/' + encodeURIComponent(filename);
        
        fetch(endpoint, { method: 'POST' })
            .then(r => r.json())
            .then(data => {
                alert(data.message || 'Reanálise agendada.');
                setTimeout(() => location.reload(), 1000);
            })
            .catch(err => {
                alert('Erro ao reanalisar: ' + err.message);
            });
    };

    function formatDuration(seconds) {
        if (isNaN(seconds)) return '--:--';
        const m = Math.floor(seconds / 60);
        const s = Math.floor(seconds % 60);
        return m + ':' + String(s).padStart(2, '0');
    }
})();
</script>
<?php include 'includes/footer.php'; ?>
<?php
require_once 'includes/config.php';
require_once 'includes/api.php';
requireLogin();
$pageTitle     = 'Ficheiros Detectados';
$activeSection = 'ficheiros';

function fp_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ═══ CARREGAR ANÁLISES (estado) ═══ */
$analyses = [];
$analysesFile = __DIR__ . '/../backend/storage/analyses.json';
if (file_exists($analysesFile)) {
    $analyses = json_decode(file_get_contents($analysesFile), true) ?: [];
}

/* ═══ LISTAR TRANSCRIÇÕES (pasta local) ═══ */
$transcriptions = [];
$transDir = __DIR__ . '/../backend/storage/transcriptions';
if (is_dir($transDir)) {
    foreach (scandir($transDir) as $file) {
        if (preg_match('/\.txt$/i', $file)) {
            $path = $transDir . '/' . $file;
            $transcriptions[] = [
                'name'     => $file,
                'stem'     => pathinfo($file, PATHINFO_FILENAME),
                'size'     => filesize($path),
                'modified' => date('Y-m-d H:i:s', filemtime($path)),
            ];
        }
    }
    usort($transcriptions, fn($a, $b) => strcmp($b['modified'], $a['modified']));
}

/* ══ LISTAR ÁUDIOS (via API) ═══ */
$audios = [];
try {
    $r = @file_get_contents(API_BASE . '/audios/list');
    if ($r !== false) {
        $data = json_decode($r, true);
        $audios = $data['files'] ?? [];
    }
} catch (Exception $e) {
    $audios = [];
}

/* ═══ FUNÇÃO: DETERMINAR ESTADO ═══ */
function fp_estado(string $stem, array $analyses): array {
    // Procurar em analyses.json
    foreach ($analyses as $key => $entry) {
        if (strpos($key, $stem) === 0 || strpos($stem, pathinfo($key, PATHINFO_FILENAME)) === 0) {
            $estado = $entry['estado'] ?? 'pendente';
            if ($estado === 'analisado') return ['transcrito', 'badge-excellent', 'bi-check-circle-fill'];
            if ($estado === 'falhou')    return ['falhou', 'badge-needs', 'bi-exclamation-triangle-fill'];
            return ['pendente', 'badge-default', 'bi-clock'];
        }
    }
    return ['pendente', 'badge-default', 'bi-clock'];
}

function fp_format_size($bytes): string {
    $bytes = (float)$bytes;
    if ($bytes <= 0) return '—';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) { $bytes /= 1024; $i++; }
    return round($bytes, $i === 0 ? 0 : 1) . ' ' . $units[$i];
}

include 'includes/header.php';
?>
<style>
/* Reutilizar estilos dos relatórios */
.fp-toolbar { margin-bottom: 14px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.fp-search { position: relative; flex: 1; min-width: 240px;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--r2); padding: 0 14px; height: 46px;
    display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-sm); transition: var(--trans); }
.fp-search:focus-within { border-color: var(--accent); box-shadow: 0 0 0 4px var(--accent-soft); }
.fp-search i { color: var(--text3); font-size: 14px; }
.fp-search input { flex: 1; border: 0; outline: 0; background: transparent;
    color: var(--text); font-family: var(--font-body); font-size: 14px; }
.fp-search input::placeholder { color: var(--text3); }
.fp-chips { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.fp-chip { border: 1px solid var(--border); background: var(--surface);
    color: var(--text); padding: 6px 14px; border-radius: 99px;
    font-size: 12.5px; font-weight: 500; cursor: pointer; transition: var(--trans);
    display: inline-flex; align-items: center; gap: 6px; }
.fp-chip:hover { border-color: var(--accent); }
.fp-chip.active { background: var(--accent); color: #fff; border-color: var(--accent); }
.fp-chip span { background: rgba(255,255,255,0.2); padding: 1px 7px;
    border-radius: 99px; font-size: 11px; font-weight: 600; }
.fp-chip:not(.active) span { background: var(--surface2); }
.fp-list { background: var(--surface); border: 1px solid var(--border);
    border-radius: 16px; overflow: hidden; }
.fp-list-head { display: grid; grid-template-columns: 2.5fr 1fr 180px 150px;
    gap: 18px; padding: 12px 18px; border-bottom: 1px solid var(--border);
    font-size: 11px; letter-spacing: .09em; text-transform: uppercase;
    color: var(--text3); font-weight: 600; }
.fp-row { display: grid; grid-template-columns: 2.5fr 1fr 180px 150px;
    gap: 18px; align-items: center; padding: 14px 18px;
    border-bottom: 1px solid var(--border); transition: background .18s; }
.fp-row:last-child { border-bottom: none; }
.fp-row:hover { background: var(--accent-soft); }
.fp-c-file { display: flex; align-items: center; gap: 12px; min-width: 0; }
.fp-file-icon { width: 44px; height: 44px; border-radius: 11px;
    display: grid; place-items: center; font-size: 20px; flex-shrink: 0; }
.fp-file-icon.txt { background: var(--red-soft); color: var(--red); }
.fp-file-icon.audio { background: var(--cyan-soft); color: var(--cyan); }
.fp-file-info { min-width: 0; }
.fp-file-name { font-weight: 500; font-size: 14px; color: var(--text);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fp-file-sub { font-size: 11.5px; color: var(--text3); margin-top: 2px;
    display: flex; align-items: center; gap: 6px; }
.fp-c-date .main { font-size: 13.5px; color: var(--text); display: flex; align-items: center; gap: 6px; }
.fp-c-date .sub  { font-size: 12px; color: var(--text3); margin-top: 2px; display: flex; align-items: center; gap: 6px; }
.fp-badge { display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 11px; border-radius: 99px;
    font-size: 11.5px; font-weight: 600; white-space: nowrap; }
.badge-excellent { background: var(--green-soft); color: var(--green); border: 1px solid rgba(34,197,139,.3); }
.badge-needs     { background: var(--red-soft);   color: var(--red);   border: 1px solid rgba(240,80,110,.3); }
.badge-default   { background: var(--surface2);   color: var(--text3); border: 1px solid var(--border); }
.fp-c-actions { display: flex; gap: 6px; justify-content: flex-end; }
.fp-btn { display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 12px; border-radius: 8px; border: 1px solid var(--border);
    background: transparent; color: var(--text3); cursor: pointer;
    transition: all .2s; text-decoration: none; font-size: 12px; font-weight: 500; }
.fp-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-soft); transform: translateY(-1px); }
.fp-btn.fp-view { background: var(--accent-soft); border-color: rgba(108,99,255,.3); color: var(--accent); }
.fp-btn.fp-view:hover { background: var(--accent); color: #fff; }
.fp-btn.fp-play { background: var(--cyan-soft); border-color: rgba(56,189,248,.3); color: var(--cyan); }
.fp-btn.fp-play:hover { background: var(--cyan); color: #fff; }
.fp-empty { background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--r2); padding: 50px 20px; text-align: center; }
.fp-empty-icon { width: 66px; height: 66px; border-radius: 18px;
    background: var(--accent-soft); color: var(--accent);
    display: grid; place-items: center; margin: 0 auto 16px; font-size: 26px; }
.fp-empty h5 { color: var(--text); font-weight: 700; margin-bottom: 6px; }
.fp-empty p { color: var(--text3); margin: 0; }
[data-theme="light"] .fp-list, [data-theme="light"] .fp-search { background: #fff; border-color: #e5e5ef; }
[data-theme="light"] .fp-row { border-bottom-color: #f0f0f6; }
[data-theme="light"] .fp-row:hover { background: rgba(108,99,255,.06); }
@media (max-width: 991px) {
    .fp-list-head { display: none; }
    .fp-row { grid-template-columns: 1fr; gap: 8px; padding: 14px 18px; }
    .fp-c-actions { justify-content: flex-start; padding-top: 10px; border-top: 1px solid var(--border); }
}
</style>

<!-- Toolbar -->
<div class="fp-toolbar">
    <div class="fp-search">
        <i class="bi bi-search"></i>
        <input type="text" id="fpSearchInput" placeholder="Pesquisar por ficheiro, estado..." autocomplete="off">
    </div>
</div>
<div class="fp-chips" style="margin-bottom: 14px">
    <button class="fp-chip active" data-filter="all" onclick="fpFilter('all', this)">
        <i class="bi bi-files"></i> Todos <span id="countAll"><?= count($transcriptions) + count($audios) ?></span>
    </button>
    <button class="fp-chip" data-filter="transcricoes" onclick="fpFilter('transcricoes', this)">
        <i class="bi bi-file-earmark-text"></i> Transcrições <span id="countTxt"><?= count($transcriptions) ?></span>
    </button>
    <button class="fp-chip" data-filter="audios" onclick="fpFilter('audios', this)">
        <i class="bi bi-music-note-beamed"></i> Áudios <span id="countAud"><?= count($audios) ?></span>
    </button>
</div>

<!-- Lista -->
<div class="fp-list" style="max-height: calc(100vh - 280px); overflow-y: auto;">
    <div class="fp-list-head">
        <div>Ficheiro</div>
        <div>Data</div>
        <div>Estado</div>
        <div style="text-align: right">Acções</div>
    </div>

    <?php if (empty($transcriptions) && empty($audios)): ?>
        <div class="fp-empty">
            <div class="fp-empty-icon"><i class="bi bi-inbox"></i></div>
            <h5>Sem ficheiros detectados</h5>
            <p>Assim que o sistema detectar transcrições ou áudios, eles aparecerão aqui.</p>
        </div>
    <?php else: ?>

        <!-- TRANSCRIÇÕES -->
        <?php foreach ($transcriptions as $t):
            $estado = fp_estado($t['stem'], $analyses);
            $ts = strtotime($t['modified']);
            $dateMain = date('d/m/Y', $ts);
            $dateSub  = date('H:i', $ts);
            $search = mb_strtolower($t['name'] . ' ' . $estado[0]);
        ?>
        <div class="fp-row fp-row-transcricao" data-type="transcricoes" data-search="<?= fp_h($search) ?>" data-estado="<?= $estado[0] ?>">
            <div class="fp-c-file">
                <div class="fp-file-icon txt"><i class="bi bi-file-earmark-text-fill"></i></div>
                <div class="fp-file-info">
                    <div class="fp-file-name" title="<?= fp_h($t['name']) ?>"><?= fp_h($t['name']) ?></div>
                    <div class="fp-file-sub">
                        <i class="bi bi-file-earmark"></i> transcrição
                        <span style="color: var(--text3)">·</span>
                        <?= fp_format_size($t['size']) ?>
                    </div>
                </div>
            </div>
            <div class="fp-c-date">
                <div class="main"><i class="bi bi-calendar3"></i> <?= fp_h($dateMain) ?></div>
                <div class="sub"><i class="bi bi-clock"></i> <?= fp_h($dateSub) ?></div>
            </div>
            <div>
                <span class="fp-badge <?= $estado[1] ?>">
                    <i class="bi <?= $estado[2] ?>"></i> <?= ucfirst($estado[0]) ?>
                </span>
            </div>
            <div class="fp-c-actions">
                <button type="button" class="fp-btn fp-view" onclick="openTranscricaoModal('<?= fp_h($t['stem']) ?>', '<?= fp_h($t['name']) ?>')">
                    <i class="bi bi-eye"></i> Ver
                </button>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- ÁUDIOS -->
        <?php foreach ($audios as $a):
            $stem = $a['audio'] ?? pathinfo($a['name'], PATHINFO_FILENAME);
            $estado = fp_estado($stem, $analyses);
            $ts = isset($a['modified_ts']) ? $a['modified_ts'] : strtotime($a['modified'] ?? 'now');
            $dateMain = date('d/m/Y', $ts);
            $dateSub  = date('H:i', $ts);
            $search = mb_strtolower($a['name'] . ' ' . $estado[0]);
            $audioUrl = API_BASE . '/audios/stream/' . urlencode($a['name']);
        ?>
        <div class="fp-row fp-row-audio" data-type="audios" data-search="<?= fp_h($search) ?>" data-estado="<?= $estado[0] ?>">
            <div class="fp-c-file">
                <div class="fp-file-icon audio"><i class="bi bi-music-note-beamed"></i></div>
                <div class="fp-file-info">
                    <div class="fp-file-name" title="<?= fp_h($a['name']) ?>"><?= fp_h($a['name']) ?></div>
                    <div class="fp-file-sub">
                        <i class="bi bi-music-note"></i> áudio
                        <span style="color: var(--text3)">·</span>
                        <?= fp_format_size($a['size'] ?? 0) ?>
                    </div>
                </div>
            </div>
            <div class="fp-c-date">
                <div class="main"><i class="bi bi-calendar3"></i> <?= fp_h($dateMain) ?></div>
                <div class="sub"><i class="bi bi-clock"></i> <?= fp_h($dateSub) ?></div>
            </div>
            <div>
                <span class="fp-badge <?= $estado[1] ?>">
                    <i class="bi <?= $estado[2] ?>"></i> <?= ucfirst($estado[0]) ?>
                </span>
            </div>
            <div class="fp-c-actions">
                <button type="button" class="fp-btn fp-play" onclick="openAudioModal('<?= fp_h($stem) ?>', '<?= fp_h($audioUrl) ?>')">
                    <i class="bi bi-play-circle"></i> Ouvir
                </button>
            </div>
        </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div>

<div class="fp-empty d-none mt-3" id="fpNoResults">
    <div class="fp-empty-icon"><i class="bi bi-search"></i></div>
    <h5>Sem resultados</h5>
    <p>Tente outro termo ou remova o filtro activo.</p>
</div>

<!-- ═══ MODAL: TRANSCRIÇÃO ══ -->
<div class="modal fade" id="transcricaoModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="background: var(--surface); border: 1px solid var(--border); border-radius: 16px; overflow: hidden;">
            <div class="modal-header" style="border-bottom: 1px solid var(--border); padding: 16px 20px; display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h5 class="modal-title" id="transcricaoTitle" style="font-family: var(--font-display); font-size: 15px; font-weight: 700; color: var(--text); margin: 0; padding-right: 30px;">Transcrição</h5>
                    <small id="transcricaoSub" style="color: var(--text3); font-size: 11px;">Transcrição da chamada</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="position: absolute; top: 16px; right: 16px; z-index: 10;"></button>
            </div>
            <div class="modal-body" style="padding: 20px; max-height: 60vh; overflow-y: auto; background: var(--bg3);">
                <pre id="transcricaoContent" style="white-space: pre-wrap; font-family: var(--font-body); font-size: 13px; line-height: 1.7; color: var(--text); margin: 0;">A carregar...</pre>
            </div>
        </div>
    </div>
</div>

<!-- ═══ MODAL: ÁUDIO ═══ -->
<div class="modal fade" id="audioModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 480px;">
        <div class="modal-content" style="background: var(--surface); border: 1px solid var(--border); border-radius: 20px; padding: 30px; text-align: center; position: relative;">
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="pauseAudio()" style="position: absolute; top: 15px; right: 15px; z-index: 10;"></button>
            <div style="width: 70px; height: 70px; border-radius: 50%; background: var(--accent-soft); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="bi bi-soundwave" style="font-size: 28px; color: var(--accent);"></i>
            </div>
            <h5 id="audioTitle" style="font-family: var(--font-display); font-size: 16px; font-weight: 700; color: var(--text); margin: 0 0 4px; word-break: break-all;">—</h5>
            <small id="audioMeta" style="color: var(--text3); font-size: 12px; display: block; margin-bottom: 20px;">—</small>
            <audio id="audioPlayer" controls controlsList="nodownload" oncontextmenu="return false;" style="width: 100%; border-radius: 8px;">
                O seu browser não suporta áudio.
            </audio>
        </div>
    </div>
</div>

<script>
(function () {
    const input     = document.getElementById('fpSearchInput');
    const rows      = Array.from(document.querySelectorAll('.fp-row'));
    const noResults = document.getElementById('fpNoResults');
    let activeFilter = 'all';

    function applyFilters() {
        const q = (input?.value || '').toLowerCase().trim();
        let visible = 0;
        rows.forEach(row => {
            const type    = row.dataset.type;
            const search  = row.dataset.search || '';
            const estado  = row.dataset.estado || '';
            const matchF  = activeFilter === 'all' || type === activeFilter;
            const matchQ  = !q || search.includes(q) || estado.includes(q);
            const show    = matchF && matchQ;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        if (noResults) noResults.classList.toggle('d-none', visible > 0);
    }

    window.fpFilter = function (filter, btn) {
        activeFilter = filter;
        document.querySelectorAll('.fp-chip').forEach(c => c.classList.remove('active'));
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
        const txtUrl  = '<?= API_BASE ?>/transcricoes/view/' + encodeURIComponent(stem + '.txt');

        title.textContent = name;
        sub.textContent   = 'Transcrição da chamada';
        content.textContent = 'A carregar...';
        bootstrap.Modal.getOrCreateInstance(modal).show();

        fetch(txtUrl)
            .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
            .then(txt => {
                if (!txt.trim()) { content.textContent = '(ficheiro vazio)'; return; }
                const lines = txt.split('\n').filter(l => l.trim());
                content.innerHTML = lines.map((line, i) => {
                    const match = line.match(/^\[([^\]]+)\]\s*(.*)/);
                    if (match) {
                        return `<div style="margin-bottom: 8px; padding: 6px 10px; border-radius: 8px; background: ${i % 2 === 0 ? 'rgba(255,255,255,0.05)' : 'transparent'}">
                            <strong style="color: var(--accent); font-size: 11px; text-transform: uppercase;">${match[1]}</strong>
                            <p style="margin: 4px 0 0; font-size: 13px;">${match[2]}</p>
                        </div>`;
                    }
                    return `<p style="margin: 4px 0; font-size: 13px;">${line}</p>`;
                }).join('');
            })
            .catch(() => { content.textContent = 'Erro ao carregar transcrição.'; });
    };

    /* ── MODAL: ÁUDIO ── */
    window.openAudioModal = function(stem, url) {
        const modal  = document.getElementById('audioModal');
        const title  = document.getElementById('audioTitle');
        const meta   = document.getElementById('audioMeta');
        const player = document.getElementById('audioPlayer');

        title.textContent = stem;
        meta.textContent  = 'A carregar áudio...';
        player.pause();
        player.currentTime = 0;
        player.src = url;
        player.load();
        player.onloadedmetadata = function() {
            const duration = formatDuration(player.duration);
            meta.textContent = 'MP3/WAV · ' + duration;
        };
        player.onerror = function() { meta.textContent = 'Áudio não encontrado.'; };
        bootstrap.Modal.getOrCreateInstance(modal).show();
    };

    window.pauseAudio = function() {
        const player = document.getElementById('audioPlayer');
        if (player) { player.pause(); player.currentTime = 0; }
    };
    document.getElementById('audioModal')?.addEventListener('hidden.bs.modal', function() { pauseAudio(); });

    function formatDuration(seconds) {
        if (isNaN(seconds)) return '--:--';
        const m = Math.floor(seconds / 60);
        const s = Math.floor(seconds % 60);
        return m + ':' + String(s).padStart(2, '0');
    }
})();
</script>
<?php include 'includes/footer.php'; ?>
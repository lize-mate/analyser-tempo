<?php
require_once 'includes/config.php';
require_once 'includes/api.php';
requireLogin();
$pageTitle     = 'Ficheiros Pendentes';
$activeSection = 'pendentes';

function pd_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ═══ TRANSCRIÇÕES PENDENTES (via API) ══ */
$transcricoesPendentes = [];
try {
    $r = apiGet('/transcricoes/status');
    $transcricoesPendentes = $r['pendente'] ?? [];
} catch (Exception $e) {
    $transcricoesPendentes = [];
}

/* ═══ ÁUDIOS (lista remota) ═══ */
$audiosPendentes = [];
try {
    $audiosData = fetchRemoteAudios();
    if ($audiosData) {
        // Filtrar apenas áudios que ainda não foram processados
        $analyses = [];
        $analysesFile = __DIR__ . '/../backend/storage/analyses.json';
        if (file_exists($analysesFile)) {
            $analyses = json_decode(file_get_contents($analysesFile), true) ?: [];
        }
        foreach ($audiosData as $a) {
            $stem = pathinfo($a['name'], PATHINFO_FILENAME);
            // Verificar se já existe transcrição ou análise
            $hasTranscription = false;
            foreach ($transcricoesPendentes as $t) {
                if (strpos($t, $stem) !== false) {
                    $hasTranscription = true;
                    break;
                }
            }
            $hasAnalysis = isset($analyses[$stem . '.txt']);
            
            if (!$hasTranscription && !$hasAnalysis) {
                $audiosPendentes[] = $a;
            }
        }
    }
} catch (Exception $e) {
    $audiosPendentes = [];
}

include 'includes/header.php';
?>
<style>
/* ══════════════════════════════════════════════════════════════════
PENDENTES — ESTILO RELATÓRIOS (REUTILIZADO)
══════════════════════════════════════════════════════════════════ */
.pd-toolbar { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
.pd-search { position: relative; flex: 1; min-width: 280px; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 0 16px; height: 46px; display: flex; align-items: center; gap: 10px; transition: var(--trans); }
.pd-search:focus-within { border-color: var(--accent); box-shadow: 0 0 0 4px var(--accent-soft); }
.pd-search i { color: var(--text3); font-size: 15px; flex-shrink: 0; }
.pd-search input { flex: 1; border: 0; outline: 0; background: transparent; color: var(--text); font-family: var(--font-body); font-size: 14px; }
.pd-search input::placeholder { color: var(--text3); }

.pd-chips { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 18px; }
.pd-chip { border: 1px solid var(--border); background: var(--surface); color: var(--text2); padding: 8px 16px; border-radius: 999px; font-size: 13px; font-weight: 600; cursor: pointer; transition: var(--trans); display: inline-flex; align-items: center; gap: 7px; font-family: var(--font-body); }
.pd-chip i { font-size: 14px; }
.pd-chip:hover { border-color: var(--accent); color: var(--text); transform: translateY(-1px); }
.pd-chip.active { background: var(--amber); color: #fff; border-color: var(--amber); box-shadow: 0 4px 12px rgba(245, 166, 35, 0.35); }
.pd-chip .chip-count { background: rgba(255,255,255,0.25); padding: 2px 7px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.pd-chip:not(.active) .chip-count { background: var(--surface2); color: var(--text3); }

.pd-list { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; }
.pd-list-head { display: grid; grid-template-columns: 2.5fr 1fr 1fr; gap: 16px; padding: 14px 22px; border-bottom: 1px solid var(--border); font-size: 11px; letter-spacing: 0.09em; text-transform: uppercase; color: var(--text3); font-weight: 700; background: var(--bg3); }
.pd-row { display: grid; grid-template-columns: 2.5fr 1fr 1fr; gap: 16px; align-items: center; padding: 14px 22px; border-bottom: 1px solid var(--border); transition: background 0.18s; }
.pd-row:last-child { border-bottom: none; }
.pd-row:hover { background: var(--amber-soft); }

.pd-c-file { display: flex; align-items: center; gap: 12px; min-width: 0; }
.pd-file-icon { width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center; font-size: 18px; flex-shrink: 0; }
.pd-file-icon.txt { background: var(--amber-soft); color: var(--amber); }
.pd-file-icon.audio { background: var(--cyan-soft); color: var(--cyan); }
.pd-file-info { min-width: 0; flex: 1; }
.pd-file-name { font-weight: 600; font-size: 13.5px; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 3px; }
.pd-file-sub { font-size: 11px; color: var(--text3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; gap: 5px; }
.pd-file-sub i { font-size: 11px; flex-shrink: 0; }

.pd-c-date { display: flex; flex-direction: column; gap: 2px; }
.pd-c-date .main { font-size: 13px; font-weight: 600; color: var(--text); display: flex; align-items: center; gap: 6px; white-space: nowrap; }
.pd-c-date .main i { color: var(--text3); font-size: 13px; }
.pd-c-date .sub { font-size: 11px; color: var(--text3); display: flex; align-items: center; gap: 5px; white-space: nowrap; }
.pd-c-date .sub i { font-size: 11px; }

.pd-c-status { display: flex; align-items: center; gap: 8px; }
.pd-status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
.pd-status-badge.pending { background: var(--amber-soft); color: var(--amber); border: 1px solid rgba(245, 166, 35, 0.3); }

.pd-empty { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; padding: 50px 20px; text-align: center; }
.pd-empty-icon { width: 66px; height: 66px; border-radius: 18px; background: var(--amber-soft); color: var(--amber); display: grid; place-items: center; margin: 0 auto 16px; font-size: 26px; }
.pd-empty h5 { color: var(--text); font-weight: 700; margin-bottom: 6px; }
.pd-empty p { color: var(--text3); margin: 0; }

[data-theme="light"] .pd-list, [data-theme="light"] .pd-search { background: #fff; border-color: #e5e5ef; }
[data-theme="light"] .pd-row { border-bottom-color: #f0f0f6; }
[data-theme="light"] .pd-row:hover { background: rgba(245, 166, 35, 0.06); }

@media (max-width: 768px) {
    .pd-list-head { display: none; }
    .pd-row { display: grid; grid-template-columns: 1fr; gap: 8px; padding: 14px 16px; }
    .pd-c-status { justify-content: flex-start; padding-top: 10px; border-top: 1px solid var(--border); }
}
</style>

<!-- Toolbar -->
<div class="pd-toolbar">
    <div class="pd-search">
        <i class="bi bi-search"></i>
        <input type="text" id="pdSearchInput" placeholder="Pesquisar por ficheiro..." autocomplete="off">
    </div>
</div>

<div class="pd-chips" style="margin-bottom:14px">
    <button class="pd-chip active" data-filter="all" onclick="pdFilter('all', this)">
        <i class="bi bi-hourglass-split"></i> Todos Pendentes
        <span class="chip-count" id="countAll"><?= count($transcricoesPendentes) + count($audiosPendentes) ?></span>
    </button>
    <button class="pd-chip" data-filter="transcricao" onclick="pdFilter('transcricao', this)">
        <i class="bi bi-file-earmark-text"></i> Transcrições
        <span class="chip-count" id="countTxt"><?= count($transcricoesPendentes) ?></span>
    </button>
    <button class="pd-chip" data-filter="audio" onclick="pdFilter('audio', this)">
        <i class="bi bi-music-note-beamed"></i> Áudios
        <span class="chip-count" id="countAud"><?= count($audiosPendentes) ?></span>
    </button>
</div>

<!-- Lista -->
<div class="pd-list" style="max-height: calc(100vh - 280px); overflow-y: auto;">
    <div class="pd-list-head">
        <div>Ficheiro</div>
        <div>Data de Detecção</div>
        <div style="text-align:right">Estado</div>
    </div>

    <?php if (empty($transcricoesPendentes) && empty($audiosPendentes)): ?>
        <div class="pd-empty">
            <div class="pd-empty-icon"><i class="bi bi-check-circle-fill"></i></div>
            <h5>Sem ficheiros pendentes</h5>
            <p>Todos os ficheiros foram processados com sucesso.</p>
        </div>
    <?php else: ?>

        <!-- TRANSCRIÇÕES PENDENTES -->
        <?php foreach ($transcricoesPendentes as $t):
            $stem = pathinfo($t, PATHINFO_FILENAME);
            $dateMain = date('d/m/Y');
            $dateSub  = date('H:i');
            $search = mb_strtolower($t);
        ?>
        <div class="pd-row pd-row-transcricao" data-type="transcricao" data-search="<?= pd_h($search) ?>">
            <div class="pd-c-file">
                <div class="pd-file-icon txt"><i class="bi bi-file-earmark-text-fill"></i></div>
                <div class="pd-file-info">
                    <div class="pd-file-name" title="<?= pd_h($t) ?>"><?= pd_h($t) ?></div>
                    <div class="pd-file-sub">
                        <i class="bi bi-file-earmark"></i> transcrição
                        <span style="color: var(--text3)">·</span>
                        <i class="bi bi-clock"></i> Aguardando análise
                    </div>
                </div>
            </div>
            <div class="pd-c-date">
                <div class="main"><i class="bi bi-calendar3"></i> <?= pd_h($dateMain) ?></div>
                <div class="sub"><i class="bi bi-clock"></i> <?= pd_h($dateSub) ?></div>
            </div>
            <div class="pd-c-status" style="justify-content: flex-end;">
                <span class="pd-status-badge pending">
                    <i class="bi bi-hourglass-split"></i> Pendente
                </span>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- ÁUDIOS PENDENTES -->
        <?php foreach ($audiosPendentes as $a):
            $stem = pathinfo($a['name'], PATHINFO_FILENAME);
            $ext  = strtoupper(pathinfo($a['name'], PATHINFO_EXTENSION));
            $ts = isset($a['modified_ts']) ? $a['modified_ts'] : strtotime($a['modified'] ?? 'now');
            $dateMain = $ts ? date('d/m/Y', $ts) : date('d/m/Y');
            $dateSub  = $ts ? date('H:i', $ts) : date('H:i');
            $search = mb_strtolower($a['name']);
        ?>
        <div class="pd-row pd-row-audio" data-type="audio" data-search="<?= pd_h($search) ?>">
            <div class="pd-c-file">
                <div class="pd-file-icon audio"><i class="bi bi-music-note-beamed"></i></div>
                <div class="pd-file-info">
                    <div class="pd-file-name" title="<?= pd_h($a['name']) ?>"><?= pd_h($a['name']) ?></div>
                    <div class="pd-file-sub">
                        <i class="bi bi-music-note"></i> áudio · <?= pd_h($ext) ?>
                        <span style="color: var(--text3)">·</span>
                        <i class="bi bi-clock"></i> Aguardando transcrição
                    </div>
                </div>
            </div>
            <div class="pd-c-date">
                <div class="main"><i class="bi bi-calendar3"></i> <?= pd_h($dateMain) ?></div>
                <div class="sub"><i class="bi bi-clock"></i> <?= pd_h($dateSub) ?></div>
            </div>
            <div class="pd-c-status" style="justify-content: flex-end;">
                <span class="pd-status-badge pending">
                    <i class="bi bi-hourglass-split"></i> Pendente
                </span>
            </div>
        </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div>

<div class="pd-empty d-none mt-3" id="pdNoResults">
    <div class="pd-empty-icon"><i class="bi bi-search"></i></div>
    <h5>Sem resultados</h5>
    <p>Tente outro termo ou remova o filtro activo.</p>
</div>

<script>
(function () {
    const input     = document.getElementById('pdSearchInput');
    const rows      = Array.from(document.querySelectorAll('.pd-row'));
    const noResults = document.getElementById('pdNoResults');
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

    window.pdFilter = function (filter, btn) {
        activeFilter = filter;
        document.querySelectorAll('.pd-chip').forEach(c => c.classList.remove('active'));
        if (btn) btn.classList.add('active');
        applyFilters();
    };

    if (input) input.addEventListener('input', applyFilters);
})();
</script>
<?php include 'includes/footer.php'; ?>
<?php

require_once 'includes/config.php';
require_once 'includes/api.php';
requireLogin();

$pageTitle     = 'Relatórios de Desempenho dos Agentes';
$activeSection = 'relatorios';

function rp_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function rp_format_size($bytes): string {
    if (is_string($bytes) && preg_match('/[a-zA-Z]/', $bytes)) return $bytes;
    $bytes = (float)$bytes;
    if ($bytes <= 0) return '—';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) { $bytes /= 1024; $i++; }
    return round($bytes, $i === 0 ? 0 : 1) . ' ' . $units[$i];
}

function rp_class_badge(?float $score, ?string $cls): array {
    if ($score === null) return ['badge-default', 'Sem análise', 'bi-dash-circle'];
    if ($score >= 80)    return ['badge-excellent', 'Bom', 'bi-award-fill'];
    if ($score >= 60)    return ['badge-meets',     'Médio', 'bi-check-circle-fill'];
    return ['badge-needs', 'Necessita Melhoria', 'bi-exclamation-triangle-fill'];
}

function rp_score_color(?float $score): string {
    if ($score === null) return '#6b7280';
    if ($score >= 80)    return '#22c58b';
    if ($score >= 60)    return '#f5a623';
    return '#f0506e';
}

$erroApi = '';
$pdfData = null;
$files   = [];
$stats   = ['total' => 0, 'excellent' => 0, 'meets' => 0, 'needs_work' => 0, 'avg_score' => 0, 'without_score' => 0];

try {
    $pdfData = fetchRemotePdfs();
    if ($pdfData === null) {
        $erroApi = 'Não foi possível contactar o serviço de análise.';
    } else {
        $files = $pdfData['files'] ?? [];
        $stats = $pdfData['stats'] ?? $stats;
    }
} catch (Throwable $e) {
    $erroApi = $e->getMessage();
}

include 'includes/header.php';
?>

<style>
/* ══════════════════════════════════════════════════════════════════
   RELATÓRIOS — TABELA ORGANIZADA (SEM SOBREPOSIÇÕES)
   ══════════════════════════════════════════════════════════════════ */

/* ─ Toolbar ── */
.rp-toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.rp-search {
    position: relative;
    flex: 1;
    min-width: 280px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 0 16px;
    height: 46px;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: var(--trans);
}
.rp-search:focus-within {
    border-color: var(--accent);
    box-shadow: 0 0 0 4px var(--accent-soft);
}
.rp-search i { color: var(--text3); font-size: 15px; flex-shrink: 0; }
.rp-search input {
    flex: 1;
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--text);
    font-family: var(--font-body);
    font-size: 14px;
}
.rp-search input::placeholder { color: var(--text3); }

/* ── Chips de Filtro ── */
.rp-chips {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}
.rp-chip {
    border: 1px solid var(--border);
    background: var(--surface);
    color: var(--text2);
    padding: 8px 16px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--trans);
    display: inline-flex;
    align-items: center;
    gap: 7px;
    font-family: var(--font-body);
}
.rp-chip:hover {
    border-color: var(--accent);
    color: var(--text);
    transform: translateY(-1px);
}
.rp-chip.active {
    background: var(--grad);
    color: #fff;
    border-color: var(--accent);
    box-shadow: 0 4px 12px rgba(108, 99, 255, 0.35);
}
.rp-chip i { font-size: 14px; }

/* ═ TABELA — GRID REORGANIZADO ═══ */
.rp-list {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
}

/* Cabeçalho da tabela */
.rp-list-head {
    display: grid;
    grid-template-columns: 2.2fr 1.1fr 0.9fr 1.4fr 180px;
    gap: 16px;
    padding: 14px 22px;
    border-bottom: 1px solid var(--border);
    font-size: 11px;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    color: var(--text3);
    font-weight: 700;
    background: var(--bg3);
}

/* Linha da tabela */
.rp-row {
    display: grid;
    grid-template-columns: 2.2fr 1.1fr 0.9fr 1.4fr 180px;
    gap: 16px;
    align-items: center;
    padding: 14px 22px;
    border-bottom: 1px solid var(--border);
    transition: background 0.18s;
}
.rp-row:last-child { border-bottom: none; }
.rp-row:hover { background: var(--accent-soft); }

/* ═══ COLUNA: FICHEIRO ══ */
.rp-c-file {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}
.rp-file-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: grid;
    place-items: center;
    font-size: 18px;
    background: var(--red-soft);
    color: var(--red);
    flex-shrink: 0;
}
.rp-file-info {
    min-width: 0;
    flex: 1;
}
.rp-file-name {
    font-weight: 600;
    font-size: 13.5px;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 3px;
}
.rp-file-sub {
    font-size: 11px;
    color: var(--text3);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: flex;
    align-items: center;
    gap: 5px;
}
.rp-file-sub i { font-size: 11px; flex-shrink: 0; }

/* ══ COLUNA: DATA ══ */
.rp-c-date {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.rp-c-date .main {
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}
.rp-c-date .main i { color: var(--text3); font-size: 13px; }
.rp-c-date .sub {
    font-size: 11px;
    color: var(--text3);
    display: flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}
.rp-c-date .sub i { font-size: 11px; }

/* ═══ COLUNA: PONTUAÇÃO (CÍRCULO REDUZIDO) ═══ */
.rp-c-score {
    display: flex;
    align-items: center;
    justify-content: center;
}
.rp-score-ring {
    position: relative;
    width: 44px;
    height: 44px;
    flex-shrink: 0;
}
.rp-score-ring svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}
.rp-score-ring circle {
    fill: none;
    stroke-width: 4;
}
.rp-score-ring .bg  { stroke: var(--border); }
.rp-score-ring .bar { stroke-linecap: round; transition: stroke-dashoffset 0.6s ease; }
.rp-score-value {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 11px;
    color: var(--text);
}

/* ═══ COLUNA: CLASSIFICAÇÃO ══ */
.rp-c-class {
    display: flex;
    align-items: center;
}
.rp-class-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}
.badge-excellent {
    background: var(--green-soft);
    color: var(--green);
    border: 1px solid rgba(34, 197, 139, 0.3);
}
.badge-meets {
    background: var(--amber-soft);
    color: var(--amber);
    border: 1px solid rgba(245, 166, 35, 0.3);
}
.badge-needs {
    background: var(--red-soft);
    color: var(--red);
    border: 1px solid rgba(240, 80, 110, 0.3);
}
.badge-default {
    background: var(--surface2);
    color: var(--text3);
    border: 1px solid var(--border);
}
.rp-class-badge i { font-size: 13px; }

/* ═══ COLUNA: ACÇÕES ═══ */
.rp-c-actions {
    display: flex;
    gap: 6px;
    justify-content: flex-end;
    align-items: center;
}
.rp-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--bg3);
    color: var(--text3);
    cursor: pointer;
    transition: var(--trans);
    font-size: 14px;
    text-decoration: none;
    position: relative;
}
.rp-btn:hover {
    border-color: var(--accent);
    color: var(--accent);
    background: var(--accent-soft);
    transform: translateY(-1px);
}
.rp-btn.rp-play:hover {
    border-color: var(--green);
    color: var(--green);
    background: var(--green-soft);
}
.rp-btn.rp-dl:hover {
    border-color: var(--cyan);
    color: var(--cyan);
    background: var(--cyan-soft);
}

/* ══ ESTADO VAZIO / ALERTA ══ */
.rp-empty, .rp-alert {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 50px 20px;
    text-align: center;
}
.rp-alert {
    border-color: rgba(240, 80, 110, 0.3);
    background: var(--red-soft);
}
.rp-empty-icon, .rp-alert-icon {
    width: 66px;
    height: 66px;
    border-radius: 18px;
    background: var(--accent-soft);
    color: var(--accent);
    display: grid;
    place-items: center;
    margin: 0 auto 16px;
    font-size: 26px;
}
.rp-alert-icon { background: var(--red-soft); color: var(--red); }
.rp-empty h5, .rp-alert h5 {
    color: var(--text);
    font-weight: 700;
    margin-bottom: 6px;
}
.rp-empty p, .rp-alert p { color: var(--text3); margin: 0; }

/* ═══ RESPONSIVO ═══ */
@media (max-width: 1024px) {
    .rp-list-head { display: none; }
    .rp-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        grid-template-areas:
            "file file"
            "date score"
            "class class"
            "actions actions";
        gap: 10px;
        padding: 16px;
    }
    .rp-c-file    { grid-area: file; }
    .rp-c-date    { grid-area: date; }
    .rp-c-score   { grid-area: score; justify-content: flex-end; }
    .rp-c-class   { grid-area: class; }
    .rp-c-actions { grid-area: actions; justify-content: flex-start; padding-top: 8px; border-top: 1px solid var(--border); }
}

@media (max-width: 575px) {
    .rp-row {
        grid-template-columns: 1fr;
        grid-template-areas:
            "file"
            "date"
            "score"
            "class"
            "actions";
    }
    .rp-c-score { justify-content: flex-start; }
    .rp-c-actions { flex-wrap: wrap; }
}

/* ══ ESTILOS PARA TRANSCRIÇÃO COM HÍFEN ═══ */
.transcricao-line {
    margin-bottom: 12px;
    padding: 10px 14px;
    border-radius: 10px;
    background: rgba(255,255,255,0.04);
    border-left: 4px solid var(--accent);
    animation: fadeIn 0.3s ease;
}
.transcricao-line:nth-child(even) {
    background: rgba(108, 99, 255, 0.08);
    border-left-color: var(--cyan);
}
.transcricao-speaker {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    font-weight: 700;
    font-size: 12px;
    text-transform: uppercase;
    color: var(--accent);
    letter-spacing: 0.8px;
}
.transcricao-speaker::before {
    content: '—';
    color: var(--accent);
    font-weight: 900;
    font-size: 16px;
    line-height: 1;
}
.transcricao-text {
    margin: 0;
    font-size: 14px;
    line-height: 1.7;
    color: var(--text);
    padding-left: 0;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<?php if ($erroApi): ?>
    <div class="rp-alert">
        <div class="rp-alert-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <h5>Serviço de análises indisponível</h5>
        <p><?= rp_h($erroApi) ?></p>
        <p style="margin-top:.5rem">Confirme se o Analyser está activo em <code><?= rp_h(API_BASE) ?></code>.</p>
    </div>
<?php elseif (empty($files)): ?>
    <div class="rp-empty">
        <div class="rp-empty-icon"><i class="bi bi-file-earmark-pdf"></i></div>
        <h5>Sem relatórios ainda</h5>
        <p>Assim que o Analyser processar uma transcrição, o PDF aparece aqui.</p>
    </div>
<?php else: ?>
    <!-- Toolbar: pesquisa + chips -->
    <div class="rp-toolbar">
        <div class="rp-search">
            <i class="bi bi-search"></i>
            <input type="text" id="rpSearchInput" placeholder="Pesquisar por agente, cliente, classificação..." autocomplete="off">
        </div>
    </div>
    
    <div class="rp-chips" style="margin-bottom:14px">
        <button class="rp-chip active" data-filter="all" onclick="rpFilter('all', this)">Todos</button>
        <button class="rp-chip" data-filter="excellent" onclick="rpFilter('excellent', this)">
            <i class="bi bi-award-fill"></i> BOM
        </button>
        <button class="rp-chip" data-filter="meets" onclick="rpFilter('meets', this)">
            <i class="bi bi-check-circle-fill"></i> MÉDIO
        </button>
        <button class="rp-chip" data-filter="needs" onclick="rpFilter('needs', this)">
            <i class="bi bi-exclamation-triangle-fill"></i> Necessita Melhoria
        </button>
    </div>

    <!-- Lista com Scroll Interno -->
    <div class="rp-list" style="max-height: calc(500px); overflow-y: auto;">
        <div class="rp-list-head">
            <div>Ficheiro</div>
            <div>Data</div>
            <div>Pontuação</div>
            <div>Classificação</div>
            <div style="text-align:right">Acções</div>
        </div>

        <?php foreach ($files as $f):
            $name     = (string)$f['name'];
            $audio    = (string)($f['audio'] ?? '');
            $sizeH    = rp_format_size($f['size'] ?? 0);
            $dateStr  = $f['modified'] ?? '';
            $ts       = $dateStr ? strtotime($dateStr) : null;
            $dateMain = $ts ? date('d/m/Y', $ts) : '—';
            $dateSub  = $ts ? date('H:i', $ts)   : '';
            $score    = isset($f['score']) && $f['score'] !== null ? (float)$f['score'] : null;
            $cls      = $f['classification'] ?? null;
            $badge    = rp_class_badge($score, $cls);
            $color    = rp_score_color($score);
            
            $filterClass = 'none';
            if ($score !== null) {
                if ($score >= 80)      $filterClass = 'excellent';
                elseif ($score >= 60)  $filterClass = 'meets';
                else                   $filterClass = 'needs';
            }
            
            $parts = explode('_', $audio);
            $cliente = $parts[0] ?? '';
            $agente  = $parts[1] ?? '';
            $search  = mb_strtolower($name . ' ' . $audio . ' ' . $cliente . ' ' . $agente . ' ' . $dateMain . ' ' . ($cls ?: ''));
            $circumference = 2 * M_PI * 20;
            $offset = $score !== null ? $circumference - ($score / 100) * $circumference : $circumference;
            $txtName = $f['text_filename'] ?? $audio;
        ?>
        <div class="rp-row" data-filter="<?= $filterClass ?>" data-search="<?= rp_h($search) ?>">
            <div class="rp-c-file">
                <div class="rp-file-icon"><i class="bi bi-file-earmark-pdf-fill"></i></div>
                <div class="rp-file-info">
                    <div class="rp-file-name" title="<?= rp_h($name) ?>"><?= rp_h($name) ?></div>
                    <div class="rp-file-sub">
                        <i class="bi bi-person-badge"></i> <?= rp_h($agente ?: '—') ?>
                        <span style="color:var(--text3)">·</span>
                        <i class="bi bi-person"></i> <?= rp_h($cliente ?: '—') ?>
                        <span style="color:var(--text3)">·</span>
                        <?= rp_h($sizeH) ?>
                    </div>
                </div>
            </div>
            <div class="rp-c-date">
                <div class="main"><i class="bi bi-calendar3"></i> <?= rp_h($dateMain) ?></div>
                <?php if ($dateSub): ?>
                    <div class="sub"><i class="bi bi-clock"></i> <?= rp_h($dateSub) ?></div>
                <?php endif; ?>
            </div>
            <div class="rp-c-score">
                <div class="rp-score-ring">
                    <svg viewBox="0 0 52 52">
                        <circle class="bg"  cx="26" cy="26" r="20"></circle>
                        <circle class="bar" cx="26" cy="26" r="20"
                            stroke="<?= $color ?>"
                            stroke-dasharray="<?= $circumference ?>"
                            stroke-dashoffset="<?= $offset ?>"></circle>
                    </svg>
                    <div class="rp-score-value" style="color:<?= $color ?>">
                        <?= $score !== null ? (int)$score . '%' : '—' ?>
                    </div>
                </div>
            </div>
            <div>
                <span class="rp-class-badge <?= $badge[0] ?>">
                    <i class="bi <?= $badge[2] ?>"></i>
                    <?= rp_h($badge[1]) ?>
                </span>
            </div>
            <div class="rp-c-actions">
                <button type="button" class="rp-btn rp-action-icon"
                    onclick="openTranscricaoModal('<?= rp_h($txtName) ?>', '<?= rp_h($name) ?>')"
                    title="Ver transcrição">
                    <i class="bi bi-eye"></i>
                    <span class="rp-btn-label">Ver</span>
                </button>
                <button type="button" class="rp-btn rp-action-icon rp-play"
                    onclick="openAudioModal('<?= rp_h($txtName) ?>')"
                    title="Escutar chamada">
                    <i class="bi bi-play-circle"></i>
                    <span class="rp-btn-label">Ouvir</span>
                </button>
                <a class="rp-btn rp-action-icon rp-dl"
                    href="<?= rp_h(remotePdfDownloadUrl($name)) ?>"
                    download
                    title="Transferir PDF">
                    <i class="bi bi-download"></i>
                    <span class="rp-btn-label">Transferir</span>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="rp-empty d-none mt-3" id="rpNoResults">
        <div class="rp-empty-icon"><i class="bi bi-search"></i></div>
        <h5>Sem resultados</h5>
        <p>Tente outro termo ou remova o filtro activo.</p>
    </div>
<?php endif; ?>

<!-- ══════════ MODAL: TRANSCRIÇÃO (.txt) -->
<div class="modal fade" id="transcricaoModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="background:var(--surface);border:1px solid var(--border);border-radius:16px; overflow:hidden;">
            <div class="modal-header" style="border-bottom:1px solid var(--border);padding:16px 20px; display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <h5 class="modal-title" id="transcricaoTitle" style="font-family:var(--font-display);font-size:15px;font-weight:700;color:var(--text);margin:0; padding-right:30px;">Transcrição</h5>
                    <small id="transcricaoSub" style="color:var(--text3);font-size:11px;">Transcrição da chamada</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="position:absolute; top:16px; right:16px; z-index:10;"></button>
            </div>
            <div class="modal-body" style="padding:20px; max-height:60vh; overflow-y:auto; background:var(--bg3);">
                <div id="transcricaoContent" style="font-family:var(--font-body); margin:0; user-select:none; -webkit-user-select:none; -moz-user-select:none; -ms-user-select:none; pointer-events:none;">A carregar...</div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════ MODAL: ÁUDIO (.mp3/.wav) — APENAS LEITURA ══════════ -->
<div class="modal fade" id="audioModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
        <div class="modal-content" style="background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:30px;text-align:center; position:relative;">
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="pauseAudio()" style="position:absolute; top:15px; right:15px; z-index:10;"></button>
            <div style="width:70px;height:70px;border-radius:50%;background:var(--accent-soft);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                <i class="bi bi-soundwave" style="font-size:28px;color:var(--accent);"></i>
            </div>
            <h5 id="audioTitle" style="font-family:var(--font-display);font-size:16px;font-weight:700;color:var(--text);margin:0 0 4px;word-break:break-all;">—</h5>
            <small id="audioMeta" style="color:var(--text3);font-size:12px;display:block;margin-bottom:20px;">—</small>
            <audio id="audioPlayer" controls controlsList="nodownload" oncontextmenu="return false;"  style="width:100%;border-radius:8px;">
                O seu browser não suporta áudio.
            </audio>
        </div>
    </div>
</div>

<script>
(function () {
    const input     = document.getElementById('rpSearchInput');
    const rows      = Array.from(document.querySelectorAll('.rp-row'));
    const count     = document.getElementById('rpSearchCount');
    const noResults = document.getElementById('rpNoResults');
    let activeFilter = 'all';

    function applyFilters() {
        const q = (input?.value || '').toLowerCase().trim();
        let visible = 0;
        rows.forEach(row => {
            const haystack = row.dataset.search || '';
            const filter   = row.dataset.filter;
            const matchQ   = !q || haystack.includes(q);
            const matchF   = activeFilter === 'all' || filter === activeFilter;
            const show     = matchQ && matchF;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        if (count)     count.textContent = `${visible} registo(s)`;
        if (noResults) noResults.classList.toggle('d-none', visible > 0);
    }

    window.rpFilter = function (filter, btn) {
        activeFilter = filter;
        document.querySelectorAll('.rp-chip').forEach(c => c.classList.remove('active'));
        if (btn) btn.classList.add('active');
        applyFilters();
    };

    if (input) input.addEventListener('input', applyFilters);

    /* ── MODAL: TRANSCRIÇÃO (.txt) — DETEÇÃO INTELIGENTE DE LOCUTORES ══ */
    window.openTranscricaoModal = function(audioStem, pdfName) {
        const modal    = document.getElementById('transcricaoModal');
        const title    = document.getElementById('transcricaoTitle');
        const sub      = document.getElementById('transcricaoSub');
        const content  = document.getElementById('transcricaoContent');
        const txtUrl   = '<?= API_BASE ?>/transcricoes/view/' + encodeURIComponent(audioStem + '.txt');

        title.textContent = audioStem + '.txt';
        sub.textContent   = 'Transcrição da chamada';
        content.innerHTML = '<p style="color:var(--text3);text-align:center;padding:40px;"><i class="bi bi-hourglass-split" style="font-size:24px;display:block;margin-bottom:10px;"></i>A carregar...</p>';

        bootstrap.Modal.getOrCreateInstance(modal).show();

        fetch(txtUrl)
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.text();
            })
            .then(txt => {
                if (!txt.trim()) {
                    content.innerHTML = '<p style="color:var(--text3);text-align:center;padding:40px;">(ficheiro vazio)</p>';
                    return;
                }
                
                const lines = txt.split('\n').filter(l => l.trim());
                let html = '';
                let currentSpeaker = null;
                let currentText = [];
                let speakerCounter = 0;
                let lastSpeaker = null;
                
                // Função para renderizar um bloco de fala
                const renderBlock = (speaker, texts, index) => {
                    if (!speaker || texts.length === 0) return '';
                    const speakerDisplay = speaker;
                    const bgClass = index % 2 === 0 ? '' : 'even';
                    return `
                        <div class="transcricao-line">
                            <div class="transcricao-speaker">— ${speakerDisplay}</div>
                            <p class="transcricao-text">${texts.join(' ')}</p>
                        </div>
                    `;
                };
                
                // Padrões para detetar locutor explícito
                const speakerPatterns = [
                    /^\[([^\]]+)\]\s*(.*)/,                    // [Agente] texto
                    /^[-–—]\s*([A-Za-zÀ-ÿ0-9\s]+?):\s*(.*)/,  // - Agente: texto
                    /^([A-Z][A-Za-zÀ-ÿ\s]+?):\s*(.*)/,        // Agente: texto
                ];
                
                // Padrões que indicam PROVÁVEL mudança de locutor (diálogo)
                const turnIndicators = [
                    /^(Tá|Está|Ok|Certo|Sim|Não|Pois|Entendido|Percebi|Ah|Tá bem|Está bem)/i,
                    /^(Obrigado|Obrigada|Adeus|Tchau|Boa tarde|Bom dia|Boa noite)/i,
                    /^(Em que|Como|Qual|Quem|Quando|Onde|Por que)/i,
                    /^(Falo|Fala|Diga|Disse|Pergunto|Queria)/i,
                    /^(Desculpe|Desculpa|Perdão|Com licença)/i,
                    /^(Não conhe|Não sei|Não tenho)/i,
                    /^(Continuação|Tenha|Desejo)/i,
                ];
                
                // Função para detetar se é provavelmente uma mudança de locutor
                const isLikelyTurnChange = (text, prevText) => {
                    if (!prevText || prevText.length < 5) return false;
                    
                    // Se começa com indicadores de resposta/turno
                    for (const pattern of turnIndicators) {
                        if (pattern.test(text)) {
                            return true;
                        }
                    }
                    
                    // Se é uma pergunta após afirmação (ou vice-versa)
                    const isQuestion = text.trim().endsWith('?');
                    const prevIsQuestion = prevText.trim().endsWith('?');
                    if (isQuestion !== prevIsQuestion && text.length > 8) {
                        return true;
                    }
                    
                    // Se há mudança abrupta de assunto/nome próprio
                    const namePattern = /(senhor|senhora|sr\.?|sra\.?)\s+([A-Z][a-z]+)/i;
                    if (namePattern.test(text) && !namePattern.test(prevText)) {
                        return true;
                    }
                    
                    return false;
                };
                
                // Processar cada linha
                lines.forEach((line, idx) => {
                    if (!line.trim()) return;
                    
                    let matched = false;
                    
                    // 1. Tentar detetar locutor explícito
                    for (const pattern of speakerPatterns) {
                        const match = line.match(pattern);
                        if (match) {
                            // Se já tínhamos um locutor, renderizar o bloco anterior
                            if (currentSpeaker && currentText.length > 0) {
                                html += renderBlock(currentSpeaker, currentText, idx);
                            }
                            currentSpeaker = match[1].trim();
                            currentText = match[2] ? [match[2].trim()] : [];
                            lastSpeaker = currentSpeaker;
                            matched = true;
                            break;
                        }
                    }
                    
                    // 2. Se não encontrou marcação explícita
                    if (!matched) {
                        const prevText = currentText.length > 0 ? currentText.join(' ') : '';
                        
                        // Verificar se é provável mudança de locutor
                        if (isLikelyTurnChange(line, prevText) && currentText.length > 0) {
                            // Renderizar bloco anterior
                            if (currentSpeaker && currentText.length > 0) {
                                html += renderBlock(currentSpeaker, currentText, idx);
                            }
                            
                            // Alternar entre Locutor 1 e Locutor 2
                            if (lastSpeaker === 'Locutor 1' || lastSpeaker === 'Agente') {
                                currentSpeaker = 'Locutor 2';
                            } else if (lastSpeaker === 'Locutor 2' || lastSpeaker === 'Cliente') {
                                currentSpeaker = 'Locutor 1';
                            } else {
                                // Primeira deteção automática
                                currentSpeaker = speakerCounter % 2 === 0 ? 'Locutor 1' : 'Locutor 2';
                                speakerCounter++;
                            }
                            lastSpeaker = currentSpeaker;
                            currentText = [line.trim()];
                        } else {
                            // Continuar com o mesmo locutor
                            if (!currentSpeaker) {
                                currentSpeaker = 'Locutor 1';
                                lastSpeaker = 'Locutor 1';
                            }
                            currentText.push(line.trim());
                        }
                    }
                });
                
                // Renderizar o último bloco
                if (currentSpeaker && currentText.length > 0) {
                    html += renderBlock(currentSpeaker, currentText, lines.length);
                }
                
                // 3. Fallback: Se ainda estiver tudo num só bloco, tentar dividir manualmente
                const blockCount = (html.match(/transcricao-line/g) || []).length;
                if (blockCount < 2 && lines.length > 1) {
                    // Texto muito longo sem separação clara - tentar dividir por pontuação forte
                    const fullText = lines.join(' ');
                    
                    // Dividir por pontos finais, interrogações, exclamações seguidos de maiúscula
                    const segments = fullText.split(/(?<=[.!?])\s+(?=[A-ZÀ-ÿ])/).filter(s => s.trim().length > 10);
                    
                    if (segments.length > 1) {
                        html = '';
                        segments.forEach((seg, idx) => {
                            if (!seg.trim()) return;
                            const speaker = idx % 2 === 0 ? 'Locutor 1' : 'Locutor 2';
                            html += renderBlock(speaker, [seg.trim()], idx);
                        });
                    }
                }
                
                content.innerHTML = html || '<p style="color:var(--text3);text-align:center;padding:40px;">Sem conteúdo formatado</p>';
            })
            .catch((err) => {
                console.error('Erro:', err);
                content.innerHTML = '<p style="color:var(--red);text-align:center;padding:40px;"><i class="bi bi-exclamation-triangle" style="font-size:24px;display:block;margin-bottom:10px;"></i>Erro ao carregar transcrição.<br>O ficheiro .txt pode não existir ainda.</p>';
            });
    };

    /* ── MODAL: ÁUDIO (.mp3/.wav) ── */
    window.openAudioModal = function(audioStem) {
        const modal   = document.getElementById('audioModal');
        const title   = document.getElementById('audioTitle');
        const meta    = document.getElementById('audioMeta');
        const player  = document.getElementById('audioPlayer');

        const mp3Url  = '<?= API_BASE ?>/audios/stream/' + encodeURIComponent(audioStem + '.mp3');
        const wavUrl  = '<?= API_BASE ?>/audios/stream/' + encodeURIComponent(audioStem + '.wav');

        title.textContent = audioStem;
        meta.textContent  = 'A carregar áudio...';

        player.pause();
        player.currentTime = 0;
        player.src = mp3Url;
        player.load();

        player.onloadedmetadata = function() {
            const duration = formatDuration(player.duration);
            const ext = player.currentSrc.includes('.wav') ? 'WAV' : 'MP3';
            meta.textContent = ext + ' · ' + duration;
        };

        player.onerror = function() {
            if (player.src.includes('.mp3')) {
                player.src = wavUrl;
                player.load();
            } else {
                meta.textContent = 'Áudio não encontrado no servidor.';
            }
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

    function formatDuration(seconds) {
        if (isNaN(seconds)) return '--:--';
        const m = Math.floor(seconds / 60);
        const s = Math.floor(seconds % 60);
        return m + ':' + String(s).padStart(2, '0');
    }
})();

window.addEventListener('qa-data-updated', function(e) {
    if (e.detail.type === 'pdfs') {
        console.log('[QA] PDFs atualizados:', e.detail.data.files.length);
    }
});
</script>

<?php include 'includes/footer.php'; ?>
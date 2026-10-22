<?php
require_once 'includes/config.php';
require_once 'includes/api.php';
requireLogin();
$agentKey = trim($_GET['id'] ?? '');
if (!$agentKey) { header('Location: agentes.php'); exit; }

$agentsFile = __DIR__ . '/../backend/storage/agentes.json';
$profiles = [];
if (file_exists($agentsFile)) {
    $profiles = json_decode(file_get_contents($agentsFile), true) ?: [];
}

/* ── Buscar Dados Reais ── */
$pdfData = apiGet('/pdfs/list');
$files   = $pdfData['files'] ?? [];

$agentCalls = [];
foreach ($files as $f) {
    $stem  = (string)($f['audio'] ?? '');
    $parts = explode('_', $stem);
    $agentRaw = isset($parts[1]) ? str_replace('-', ' ', $parts[1]) : '';
    if (!$agentRaw) continue;
    $key = strtolower(str_replace([' ', '-'], '_', $agentRaw));
    if ($key !== $agentKey) continue;
    $agentCalls[] = [
        'filename' => $f['name'] ?? $stem,
        'audio'    => $stem,
        'score'    => isset($f['score']) && $f['score'] !== null ? (float)$f['score'] : null,
        'class'    => $f['classification'] ?? null,
        'date'     => $f['analyzed_at'] ?? $f['modified'] ?? '',
    ];
}
usort($agentCalls, fn($a, $b) => strcmp($b['date'], $a['date']));

/* ── Estatísticas gerais ── */
$totalCalls   = count($agentCalls);
$scoredCalls  = count(array_filter($agentCalls, fn($c) => $c['score'] !== null));
$scores       = array_column(array_filter($agentCalls, fn($c) => $c['score'] !== null), 'score');
$avgScore     = $scoredCalls > 0 ? round(array_sum($scores) / $scoredCalls, 1) : 0;
$bestScore    = $scores ? max($scores) : 0;
$worstScore   = $scores ? min($scores) : 0;
$excellent    = count(array_filter($scores, fn($s) => $s >= 80));
$meets        = count(array_filter($scores, fn($s) => $s >= 60 && $s < 80));
$needsWork    = count(array_filter($scores, fn($s) => $s < 60));

/* ── MÉDIAS POR PERÍODO (Diária / Semanal / Mensal) ── */
$todayStr   = date('Y-m-d');
$weekStart  = date('Y-m-d', strtotime('-' . (date('N') - 1) . ' days')); // segunda-feira
$monthStart = date('Y-m-01');

$sumD = $nD = $sumW = $nW = $sumM = $nM = 0;
foreach ($agentCalls as $c) {
    if ($c['score'] === null) continue;
    $d = substr((string)$c['date'], 0, 10);
    if ($d === $todayStr)  { $sumD += $c['score']; $nD++; }
    if ($d >= $weekStart)  { $sumW += $c['score']; $nW++; }
    if ($d >= $monthStart) { $sumM += $c['score']; $nM++; }
}
$mediaDiaria  = $nD > 0 ? round($sumD / $nD, 1) : null;
$mediaSemanal = $nW > 0 ? round($sumW / $nW, 1) : null;
$mediaMensal  = $nM > 0 ? round($sumM / $nM, 1) : null;

$profile = $profiles[$agentKey] ?? null;
$displayName = $profile ? $profile['name'] : ucwords(str_replace(['_', '-'], ' ', $agentKey));
$initials = '';
foreach (explode(' ', $displayName) as $np) {
    if (strlen($np) > 0) $initials .= strtoupper($np[0]);
    if (strlen($initials) >= 2) break;
}

function apBarColor(float $s): string {
    if ($s >= 80) return '#22c58b';
    if ($s >= 60) return '#f5a623';
    return '#f0506e';
}

$pageTitle     = 'Perfil: ' . $displayName;
$activeSection = 'agentes';
include 'includes/header.php';
?>
<style>
/* ══════════════════════════════════════════════════
   PERFIL DO AGENTE — Estilos
   ══════════════════════════════════════════════════ */
.ap-container { max-width: 1440px; margin: 0 auto; }
.ap-back {
    display: inline-flex; align-items: center; gap: 6px; color: var(--text3);
    font-size: 13px; font-weight: 500; text-decoration: none; margin-bottom: 16px;
    transition: color .2s;
}
.ap-back:hover { color: var(--accent); }

/* ── Header do Perfil ── */
.ap-header {
    background: var(--surface); border: 1px solid var(--border); border-radius: 0;
    padding: 24px 28px; display: flex; gap: 24px; align-items: center; flex-wrap: wrap;
    box-shadow: var(--shadow-sm); margin-bottom: 20px;
}
.ap-avatar {
    width: 72px; height: 72px; border-radius: 50%; display: grid; place-items: center;
    font-size: 26px; font-weight: 700; font-family: var(--font-display); color: #fff;
    flex-shrink: 0; background: var(--grad); box-shadow: 0 8px 24px rgba(108,99,255,.3);
}
.ap-avatar.has-profile { background: var(--green); box-shadow: 0 8px 24px rgba(34,197,139,.3); }
.ap-info { flex: 1; min-width: 200px; }
.ap-name { font-family: var(--font-display); font-size: 22px; font-weight: 700; color: var(--text); margin: 0 0 4px; }
.ap-role { font-size: 13px; color: var(--text3); margin-bottom: 10px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.ap-role .badge { padding: 3px 10px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
.ap-role .badge-profile { background: var(--green-soft); color: var(--green); }
.ap-role .badge-team { background: var(--accent-soft); color: var(--accent); }
.ap-details { display: flex; gap: 20px; flex-wrap: wrap; }
.ap-detail { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text2); }
.ap-detail i { color: var(--text3); font-size: 14px; }

/* ── 👈 NOVO: Bloco das 3 Médias (Diária / Semanal / Mensal) ── */
.ap-averages { display: flex; margin-left: auto; align-items: stretch; }
.ap-avg { text-align: center; padding: 6px 26px; border-left: 1px solid var(--border); }
.ap-avg .val { font-family: var(--font-display); font-size: 26px; font-weight: 700; line-height: 1; }
.ap-avg .lbl { font-size: 9px; text-transform: uppercase; letter-spacing: .6px; color: var(--text3); margin-top: 5px; font-weight: 700; }
.ap-avg .sub { font-size: 10px; color: var(--text3); margin-top: 3px; }

/* ── KPIs Grid ── */
.ap-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 20px; }
.ap-kpi { background: var(--surface); border: 1px solid var(--border); border-radius: 0; padding: 18px; box-shadow: var(--shadow-sm); }
.ap-kpi .lbl { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: var(--text3); margin-bottom: 6px; }
.ap-kpi .val { font-family: var(--font-display); font-size: 22px; font-weight: 700; color: var(--text); }
.ap-kpi .sub { font-size: 11px; color: var(--text3); margin-top: 4px; }

/* ── Grid Principal ── */
.ap-grid { display: grid; grid-template-columns: 1fr 360px; gap: 20px; align-items: start; }
@media (max-width: 1200px) { .ap-grid { grid-template-columns: 1fr; } }

/* ── Cards ── */
.ap-card { background: var(--surface); border: 1px solid var(--border); border-radius: 0; box-shadow: var(--shadow-sm); overflow: hidden; }
.ap-card-head {
    padding: 14px 20px; border-bottom: 1px solid var(--border);
    display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;
}
.ap-card-title { font-family: var(--font-display); font-size: 15px; font-weight: 700; color: var(--text); margin: 0; display: flex; align-items: center; gap: 8px; }
.ap-card-title i { color: var(--accent); }

/* ── 👈 NOVO: Botões de Filtro (Hoje / Esta Semana / Todas) ── */
.ap-filters { display: flex; gap: 6px; }
.ap-filter-btn {
    padding: 6px 14px; border: 1px solid var(--border); border-radius: 999px;
    background: transparent; color: var(--text3); font-size: 11px; font-weight: 600;
    cursor: pointer; transition: all .2s; font-family: var(--font-body);
}
.ap-filter-btn:hover { border-color: var(--accent); color: var(--accent); }
.ap-filter-btn.active { background: var(--accent); border-color: var(--accent); color: #fff; }

/* ── Scroll interno do histórico ── */
.ap-scroll { max-height: 420px; overflow-y: auto; }
.ap-scroll::-webkit-scrollbar { width: 6px; }
.ap-scroll::-webkit-scrollbar-thumb { background: var(--border); border-radius: 99px; }

/* ── Tabela ── */
.ap-table { width: 100%; border-collapse: collapse; }
.ap-table thead th {
    font-size: 10px; text-transform: uppercase; letter-spacing: .7px; color: var(--text3);
    text-align: left; padding: 10px 16px; border-bottom: 1px solid var(--border); font-weight: 700;
    background: var(--bg3); position: sticky; top: 0; z-index: 1;
}
.ap-table tbody tr { border-bottom: 1px solid var(--border); transition: background .15s; }
.ap-table tbody tr:last-child { border-bottom: none; }
.ap-table tbody tr:hover { background: var(--accent-soft); }
.ap-table tbody td { padding: 12px 16px; font-size: 13px; color: var(--text); vertical-align: middle; }
.ap-file-cell { display: flex; align-items: center; gap: 10px; min-width: 0; }
.ap-file-icon { width: 34px; height: 34px; border-radius: 8px; display: grid; place-items: center; font-size: 15px; flex-shrink: 0; background: var(--red-soft); color: var(--red); }
.ap-file-name { font-weight: 500; font-size: 12.5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 420px; }
.ap-file-sub { font-size: 11px; color: var(--text3); margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 420px; }
.ap-score-cell { display: flex; align-items: center; gap: 8px; }
.ap-score-mini { width: 50px; height: 4px; background: var(--bg3); border-radius: 0; overflow: hidden; }
.ap-score-mini i { display: block; height: 100%; }
.ap-badge { padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; white-space: nowrap; }
.ap-badge-exc { background: var(--green-soft); color: var(--green); }
.ap-badge-meets { background: var(--amber-soft); color: var(--amber); }
.ap-badge-needs { background: var(--red-soft); color: var(--red); }
.ap-badge-none { background: var(--surface2); color: var(--text3); }

/* ── Sidebar Direita ── */
.ap-sidebar { display: flex; flex-direction: column; gap: 16px; }
.ap-breakdown { list-style: none; padding: 0; margin: 0; }
.ap-breakdown li { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border); }
.ap-breakdown li:last-child { border-bottom: none; }
.ap-breakdown .label { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--text2); }
.ap-breakdown .dot { width: 8px; height: 8px; border-radius: 50%; }
.ap-breakdown .count { font-weight: 700; font-size: 14px; }
.ap-empty { text-align: center; padding: 40px 20px; color: var(--text3); }
.ap-empty i { font-size: 36px; margin-bottom: 10px; display: block; opacity: .4; }
.ap-notes { background: var(--surface); border: 1px solid var(--border); border-radius: 0; padding: 18px; box-shadow: var(--shadow-sm); }
.ap-notes-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--text3); margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
.ap-notes-text { font-size: 13px; color: var(--text); line-height: 1.6; white-space: pre-wrap; }

@media (max-width: 991px) {
    .ap-averages { margin-left: 0; width: 100%; justify-content: space-between; border-top: 1px solid var(--border); padding-top: 14px; }
    .ap-avg { padding: 6px 12px; border-left: none; }
}
</style>

<div class="ap-container">
<a href="agentes.php" class="ap-back"><i class="bi bi-arrow-left"></i> Voltar à lista de agentes</a>

<!-- ── HEADER DO PERFIL ── -->
<div class="ap-header">
    <div class="ap-avatar <?= $profile ? 'has-profile' : '' ?>"><?= $initials ?: '?' ?></div>
    <div class="ap-info">
        <h1 class="ap-name"><?= htmlspecialchars($displayName) ?></h1>
        <div class="ap-role">
            <?php if ($profile): ?>
                <span class="badge badge-profile"><i class="bi bi-patch-check-fill"></i> Perfil Verificado</span>
            <?php endif; ?>
            <?php if ($profile && !empty($profile['team'])): ?>
                <span class="badge badge-team"><?= htmlspecialchars($profile['team']) ?></span>
            <?php endif; ?>
            <span style="color:var(--text3);"><?= htmlspecialchars($profile['role'] ?? 'Agente de Contact Center') ?></span>
        </div>
        <div class="ap-details">
            <?php if ($profile && !empty($profile['email'])): ?>
                <div class="ap-detail"><i class="bi bi-envelope"></i> <?= htmlspecialchars($profile['email']) ?></div>
            <?php endif; ?>
            <?php if ($profile && !empty($profile['phone'])): ?>
                <div class="ap-detail"><i class="bi bi-telephone"></i> <?= htmlspecialchars($profile['phone']) ?></div>
            <?php endif; ?>
            <div class="ap-detail"><i class="bi bi-calendar3"></i> <?= $totalCalls ?> chamadas registadas</div>
        </div>
    </div>

    <!-- 👈 3 MÉDIAS: DIÁRIA / SEMANAL / MENSAL -->
    <div class="ap-averages">
        <div class="ap-avg">
            <div class="val" style="color:<?= $mediaDiaria !== null ? apBarColor($mediaDiaria) : 'var(--text3)' ?>">
                <?= $mediaDiaria !== null ? number_format($mediaDiaria, 0) . '%' : '—' ?>
            </div>
            <div class="lbl">Média Diária</div>
            <div class="sub"><?= $nD ?> chamada(s) hoje</div>
        </div>
        <div class="ap-avg">
            <div class="val" style="color:<?= $mediaSemanal !== null ? apBarColor($mediaSemanal) : 'var(--text3)' ?>">
                <?= $mediaSemanal !== null ? number_format($mediaSemanal, 0) . '%' : '—' ?>
            </div>
            <div class="lbl">Média Semanal</div>
            <div class="sub"><?= $nW ?> chamada(s) esta semana</div>
        </div>
        <div class="ap-avg">
            <div class="val" style="color:<?= $mediaMensal !== null ? apBarColor($mediaMensal) : 'var(--text3)' ?>">
                <?= $mediaMensal !== null ? number_format($mediaMensal, 0) . '%' : '—' ?>
            </div>
            <div class="lbl">Média Mensal</div>
            <div class="sub"><?= $nM ?> chamada(s) este mês</div>
        </div>
    </div>
</div>

<!-- ── KPIs ── -->
<div class="ap-kpis">
    <div class="ap-kpi">
        <div class="lbl">Total Chamadas</div>
        <div class="val"><?= $totalCalls ?></div>
        <div class="sub"><?= $scoredCalls ?> analisadas por IA</div>
    </div>
    <div class="ap-kpi">
        <div class="lbl">Melhor Pontuação</div>
        <div class="val" style="color:var(--green);"><?= $bestScore > 0 ? number_format($bestScore, 0) : '—' ?>%</div>
        <div class="sub">Pico de desempenho</div>
    </div>
    <div class="ap-kpi">
        <div class="lbl">Pior Pontuação</div>
        <div class="val" style="color:var(--red);"><?= ($scoredCalls > 0) ? number_format($worstScore, 0) : '—' ?>%</div>
        <div class="sub">Oportunidade de melhoria</div>
    </div>
    <div class="ap-kpi">
        <div class="lbl">Taxa de Excelência</div>
        <div class="val" style="color:var(--accent);"><?= $scoredCalls > 0 ? round(($excellent / $scoredCalls) * 100) : 0 ?>%</div>
        <div class="sub"><?= $excellent ?> de <?= $scoredCalls ?> chamadas</div>
    </div>
</div>

<!-- ── GRID PRINCIPAL ── -->
<div class="ap-grid">
    <!-- Histórico de Análises com Filtros -->
    <div class="ap-card">
        <div class="ap-card-head">
            <h3 class="ap-card-title"><i class="bi bi-list-ul"></i> Histórico de Análises</h3>
            <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <div class="ap-filters">
                    <button type="button" class="ap-filter-btn" data-range="hoje">Hoje</button>
                    <button type="button" class="ap-filter-btn" data-range="semana">Esta Semana</button>
                    <button type="button" class="ap-filter-btn active" data-range="todas">Todas</button>
                </div>
                <span style="font-size:12px;color:var(--text3);" id="apHistoryCount"><?= $totalCalls ?> registos</span>
            </div>
        </div>
        <div class="ap-scroll">
            <div class="ap-empty" id="apHistoryEmpty" style="display:none; padding:30px 20px;">
                <i class="bi bi-funnel"></i>
                Sem análises neste período.
            </div>
            <?php if (empty($agentCalls)): ?>
                <div class="ap-empty">
                    <i class="bi bi-inbox"></i>
                    <h5>Sem análises ainda</h5>
                    <p>As chamadas analisadas deste agente aparecerão aqui.</p>
                </div>
            <?php else: ?>
                <table class="ap-table">
                    <thead>
                        <tr>
                            <th>Ficheiro</th>
                            <th>Data</th>
                            <th>Pontuação</th>
                            <th>Classificação</th>
                        </tr>
                    </thead>
                    <tbody id="apHistoryBody">
                        <?php foreach ($agentCalls as $call):
                            $c = $call['score'] !== null ? apBarColor($call['score']) : '#5c6278';
                            if ($call['score'] !== null) {
                                if ($call['score'] >= 80)     { $cls = 'ap-badge-exc';   $clsText = 'Bom'; }
                                elseif ($call['score'] >= 60) { $cls = 'ap-badge-meets'; $clsText = 'Médio'; }
                                else                          { $cls = 'ap-badge-needs'; $clsText = 'Necessita Melhoria'; }
                            } else { $cls = 'ap-badge-none'; $clsText = 'Pendente'; }
                            $dateStr  = $call['date'] ? date('d/m/Y H:i', strtotime($call['date'])) : '—';
                            $dateKey  = substr((string)$call['date'], 0, 10);
                        ?>
                        <tr data-date="<?= htmlspecialchars($dateKey) ?>">
                            <td>
                                <div class="ap-file-cell">
                                    <div class="ap-file-icon"><i class="bi bi-file-earmark-pdf-fill"></i></div>
                                    <div style="min-width:0;">
                                        <div class="ap-file-name" title="<?= htmlspecialchars($call['filename']) ?>"><?= htmlspecialchars($call['filename']) ?></div>
                                        <div class="ap-file-sub"><?= htmlspecialchars($call['audio']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="color:var(--text3);font-size:12px;white-space:nowrap;"><?= $dateStr ?></td>
                            <td>
                                <div class="ap-score-cell">
                                    <div class="ap-score-mini"><i style="width:<?= (int)($call['score'] ?? 0) ?>%;background:<?= $c ?>"></i></div>
                                    <span style="font-weight:700;color:<?= $c ?>;min-width:36px;"><?= $call['score'] !== null ? number_format($call['score'], 0) : '—' ?>%</span>
                                </div>
                            </td>
                            <td><span class="ap-badge <?= $cls ?>"><?= $clsText ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sidebar Direita -->
    <div class="ap-sidebar">
        <div class="ap-card">
            <div class="ap-card-head">
                <h3 class="ap-card-title"><i class="bi bi-pie-chart-fill"></i> Distribuição</h3>
            </div>
            <div style="padding:16px 20px;">
                <ul class="ap-breakdown">
                    <li>
                        <span class="label"><span class="dot" style="background:var(--green);"></span> Bom ()</span>
                        <span class="count" style="color:var(--green);"> ≥86%</span>
                    </li>
                    <li>
                        <span class="label"><span class="dot" style="background:var(--amber);"></span> Médio</span>
                        <span class="count" style="color:var(--amber);"> 76%-85%</span>
                    </li>
                    <li>
                        <span class="label"><span class="dot" style="background:var(--red);"></span> Necessita Melhoria </span>
                        <span class="count" style="color:var(--red);"> ≤75% </span>
                    </li>
                    
                </ul>
            </div>
        </div>

        <?php if ($profile && !empty($profile['notes'])): ?>
            <div class="ap-notes">
                <div class="ap-notes-title"><i class="bi bi-journal-text"></i> Notas</div>
                <div class="ap-notes-text"><?= htmlspecialchars($profile['notes']) ?></div>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>

<script>
(function () {
    const TODAY = '<?= $todayStr ?>';
    const WEEK  = '<?= $weekStart ?>';
    const rows  = Array.from(document.querySelectorAll('#apHistoryBody tr'));
    const count = document.getElementById('apHistoryCount');
    const empty = document.getElementById('apHistoryEmpty');
    if (!rows.length) return;

    function applyFilter(range) {
        let visible = 0;
        rows.forEach(tr => {
            const d = tr.dataset.date || '';
            let show = true;
            if (range === 'hoje')        show = (d === TODAY);
            else if (range === 'semana') show = (d >= WEEK);
            tr.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        if (count) count.textContent = visible + ' registo(s)';
        if (empty) empty.style.display = visible ? 'none' : '';
    }

    document.querySelectorAll('.ap-filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.ap-filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            applyFilter(btn.dataset.range);
        });
    });
})();
</script>
<?php include 'includes/footer.php'; ?>
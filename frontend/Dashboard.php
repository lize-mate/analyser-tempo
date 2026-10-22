<?php
require_once 'includes/config.php';
require_once 'includes/api.php';
requireLogin();
$pageTitle     = 'Dashboard';
$activeSection = 'Dashboard';

/* ══ FONTE ÚNICA: ANALYSER — via /stats/dashboard  */
$dash   = apiGet('/stats/dashboard') ?? [];
$cards  = $dash['cards']  ?? [];
$hourly = $dash['hourly'] ?? [];
$todayStr = $dash['date'] ?? date('Y-m-d');

/* ─ Cards Principais ── */
$analisados = (int)($cards['analisado'] ?? 0);
$falhadas   = (int)($cards['falhou'] ?? 0);
$pendentes  = (int)($cards['pendente'] ?? 0);
$totalUniverso = $analisados + $falhadas + $pendentes;
$pctAnalisados = ($totalUniverso > 0) ? round(($analisados / $totalUniverso) * 100) : 0;

/* ── Classificação das Análises ── */
$boas       = (int)($cards['boas'] ?? 0);
$aceitaveis = (int)($cards['aceitaveis'] ?? 0);
$criticas   = (int)($cards['criticas'] ?? 0);
$totalClassificadas = $boas + $aceitaveis + $criticas;

$pctBoas       = ($totalClassificadas > 0) ? round(($boas / $totalClassificadas) * 100) : 0;
$pctAceitaveis = ($totalClassificadas > 0) ? round(($aceitaveis / $totalClassificadas) * 100) : 0;
$pctCriticas   = ($totalClassificadas > 0) ? round(($criticas / $totalClassificadas) * 100) : 0;

/* ─ Tempos Globais (vêm da API ou fallback) ── */
$tempoGastoAPI       = $cards['tempo_gasto'] ?? null;
$tempoEconomizadoAPI = $cards['tempo_economizado'] ?? null;

$fmtDate = fn($d) => $d ? date('d/m/Y', strtotime($d)) : '—';
$desde   = $fmtDate($dash['first_analysis'] ?? null);

/* ═══ CÁLCULO DE EFICIÊNCIA DO ANALYSER (NOVO) ═══ */
/* Se a API não devolver tempos detalhados, calculamos localmente */
if (!$tempoGastoAPI || !$tempoEconomizadoAPI) {
    $pdfData = apiGet('/pdfs/list');
    $allFiles = $pdfData['files'] ?? [];
    
    // Estimativa: 15 minutos por chamada para QA manual completo
    $MINUTES_PER_CALL_HUMAN = 15; 
    
    $totalMachineSeconds = 0;
    $totalCallsProcessed = 0;
    
    foreach ($allFiles as $f) {
        if (($f['estado'] ?? '') === 'analisado') {
            $totalCallsProcessed++;
            // Tenta extrair tempo de processamento do JSON interno ou usa média estimada de 8s por call na IA
            $procTime = isset($f['json']['metadata']['processing_time_seconds']) 
                      ? (float)$f['json']['metadata']['processing_time_seconds'] 
                      : 8.0; 
            $totalMachineSeconds += $procTime;
        }
    }
    
    $totalHumanMinutes = $totalCallsProcessed * $MINUTES_PER_CALL_HUMAN;
    $totalMachineMinutes = round($totalMachineSeconds / 60, 1);
    $savedMinutes = max(0, $totalHumanMinutes - $totalMachineMinutes);
    
    // Formatação para exibição
    $fmtMin = function($m) {
        if ($m >= 60) return floor($m/60).'h '.($m%60).'min';
        return round($m).'min';
    };
    
    $tempoGastoDisplay       = $fmtMin($totalMachineMinutes);
    $tempoHumanoEstimado     = $fmtMin($totalHumanMinutes);
    $tempoEconomizadoDisplay = $fmtMin($savedMinutes);
    $callsForEfficiency      = $totalCallsProcessed;
} else {
    // Fallback se a API já trouxer os valores formatados
    $tempoGastoDisplay       = $tempoGastoAPI;
    $tempoEconomizadoDisplay = $tempoEconomizadoAPI;
    $tempoHumanoEstimado     = 'Calculado pela API';
    $callsForEfficiency      = $analisados;
}

/* ═══ LÓGICA NOVA: TOP OPORTUNIDADES DE COACHING (HOJE) ═══ */
$pdfData = apiGet('/pdfs/list');
$allFiles = $pdfData['files'] ?? [];

$coachingOpps = [];
foreach ($allFiles as $f) {
    $score = isset($f['score']) && $f['score'] !== null ? (float)$f['score'] : null;
    $dateKey = substr((string)($f['analyzed_at'] ?? $f['modified'] ?? ''), 0, 10);
    
    if ($score !== null && $score < 60 && $dateKey === $todayStr) {
        $stem = (string)($f['audio'] ?? '');
        $parts = explode('_', $stem);
        $agentRaw = isset($parts[1]) ? str_replace('-', ' ', $parts[1]) : 'Desconhecido';
        
        $primaryArea = 'Análise detalhada necessária';
        if (!empty($f['json']['coaching']['primary_coaching_area'])) {
            $primaryArea = $f['json']['coaching']['primary_coaching_area'];
        } elseif (!empty($f['json']['coaching']['improvement_areas'][0])) {
            $primaryArea = $f['json']['coaching']['improvement_areas'][0];
        }

        $coachingOpps[] = [
            'agent' => ucwords($agentRaw),
            'score' => $score,
            'area'  => $primaryArea,
            'key'   => strtolower(str_replace([' ', '-'], '_', $agentRaw)),
            'file'  => $f['name'] ?? $stem
        ];
    }
}

usort($coachingOpps, fn($a, $b) => $a['score'] <=> $b['score']);
$topCoaching = array_slice($coachingOpps, 0, 4);

function dbwBarColor(float $s): string {
    if ($s >= 80) return '#22c58b';
    if ($s >= 60) return '#f5a623';
    return '#f0506e';
}
include 'includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* ═══ CARDS COMPACTOS KPI ═══ */
.dbw-stats .dbw-stat {
    height: auto !important; min-height: 85px !important; max-height: 110px !important;
    padding: 12px 14px !important; align-items: center !important;
}
.dbw-stats .dbw-stat > div:first-child { flex: 1; min-width: 0; }
.dbw-stats .dbw-stat .lbl { font-size: 9px !important; margin-bottom: 2px !important; line-height: 1; }
.dbw-stats .dbw-stat .val { font-size: 20px !important; margin-top: 0 !important; line-height: 1.1; }
.dbw-stats .dbw-stat .sub { font-size: 10px !important; margin-top: 2px !important; line-height: 1.2; }
.dbw-stats .dbw-stat .kpi-meaning {
    margin-top: 4px; font-size: 9px; font-weight: 600;
    display: flex; align-items: center; gap: 3px; line-height: 1.2;
}
.dbw-stats .dbw-stat .kpi-extra {
    margin-top: 6px; font-size: 9.5px; color: var(--text3);
    display: flex; flex-direction: column; gap: 2px; line-height: 1.2;
}
.dbw-stats .dbw-stat .kpi-extra strong { color: var(--text); }

/* ═══ HEADER ALINHADO ══ */
.dbw-card-head {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; flex-wrap: nowrap; margin-bottom: 12px;
}
.dbw-head-gap { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }

/* ═══ LIVE BADGE ══ */
.dbw-live {
    display: inline-flex; align-items: center; gap: 5px; font-size: 9px; font-weight: 700;
    color: var(--green); text-transform: uppercase; letter-spacing: .3px;
    padding: 3px 8px; background: rgba(34, 197, 139, 0.08); border-radius: 999px;
}
.dbw-live .dot {
    width: 6px; height: 6px; border-radius: 50%; background: var(--green);
    box-shadow: 0 0 0 0 rgba(34,197,139,.6); animation: dbwPulse 1.8s infinite;
}
@keyframes dbwPulse { 0%{box-shadow:0 0 0 0 rgba(34,197,139,.6)} 70%{box-shadow:0 0 0 6px rgba(34,197,139,0)} 100%{box-shadow:0 0 0 0 rgba(34,197,139,0)} }

/* ══ SELETOR DE DATA ═══ */
.dbw-date-selector {
    display: inline-flex; align-items: center; gap: 4px; background: var(--surface);
    border: 1px solid var(--border); border-radius: 8px; padding: 3px 6px; font-size: 11px; flex-shrink: 0;
}
.dbw-date-selector select {
    background: var(--bg3); border: 1px solid var(--border); color: var(--text);
    padding: 3px 6px; border-radius: 5px; font-size: 11px; font-weight: 600;
    cursor: pointer; outline: none; appearance: none; -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8' viewBox='0 0 24 24' fill='none' stroke='%239aa0b4' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 4px center; padding-right: 16px; height: 24px;
}
.dbw-date-selector .sep { color: var(--text3); font-weight: 700; font-size: 11px; user-select: none; }
.dbw-loading {
    display: inline-flex; align-items: center; gap: 4px; color: var(--amber);
    font-size: 10px; font-weight: 600; margin-left: 4px; opacity: 0; transition: opacity 0.2s;
}
.dbw-loading.show { opacity: 1; }
.dbw-loading .spinner {
    width: 10px; height: 10px; border: 2px solid var(--border);
    border-top-color: var(--amber); border-radius: 50%; animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ═══ ESTILOS ESPECÍFICOS PARA O PAINEL DE COACHING ═══ */
.coaching-item {
    display: flex; align-items: center; gap: 14px; padding: 12px;
    background: var(--bg3); border-radius: 8px; border-left: 3px solid var(--red);
    transition: all 0.2s;
}
.coaching-item:hover { background: var(--surface2); transform: translateX(2px); }
.coaching-score {
    width: 36px; height: 36px; border-radius: 50%; background: var(--red-soft);
    color: var(--red); display: grid; place-items: center; font-weight: 700;
    font-size: 13px; flex-shrink: 0;
}
.coaching-info { flex: 1; min-width: 0; }
.coaching-agent { font-weight: 600; font-size: 13px; color: var(--text); margin-bottom: 2px; }
.coaching-reason {
    font-size: 11px; color: var(--text3); white-space: nowrap;
    overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; gap: 4px;
}
.btn-coach-action {
    padding: 6px 12px; background: var(--surface); border: 1px solid var(--border);
    border-radius: 6px; font-size: 11px; color: var(--accent); text-decoration: none;
    font-weight: 600; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px;
    transition: all 0.2s;
}
.btn-coach-action:hover { background: var(--accent-soft); border-color: var(--accent); }

/* ═══ CARD DE EFICIÊNCIA DO ANALYSER ═══ */
.eff-card-body {
    display: flex; flex-direction: column; gap: 16px; padding: 10px 0;
}
.eff-row {
    display: flex; align-items: center; gap: 14px; padding: 12px;
    background: var(--bg3); border-radius: 8px; border-left: 3px solid var(--accent);
}
.eff-icon {
    width: 36px; height: 36px; border-radius: 50%; background: var(--accent-soft);
    color: var(--accent); display: grid; place-items: center; font-size: 16px; flex-shrink: 0;
}
.eff-info { flex: 1; min-width: 0; }
.eff-label { font-size: 10px; color: var(--text3); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
.eff-value { font-family: var(--font-display); font-size: 18px; font-weight: 700; color: var(--text); }
.eff-sub { font-size: 11px; color: var(--text3); margin-top: 2px; }

.eff-highlight {
    margin-top: 12px; padding: 14px; background: var(--green-soft);
    border-radius: 8px; border: 1px solid rgba(34,197,139,0.2);
    text-align: center;
}
.eff-highlight-title {
    font-size: 10px; color: var(--green); text-transform: uppercase;
    letter-spacing: 0.8px; font-weight: 700; margin-bottom: 6px;
}
.eff-highlight-text {
    font-size: 12px; color: var(--text); line-height: 1.5;
}
</style>

<div id="dbw">
<!-- ── 1. KPIs TOPO (COMPACTOS) ─ -->
<div class="dbw-stats">
    <!-- CARD 1: Ficheiros Analisados + Tempos -->
    <div class="dbw-stat" style="cursor: pointer;" onclick="window.location.href='relatorios.php'">
        <div>
            <div class="lbl">Ficheiros Analisados</div>
            <div class="val val-green" id="kpiAnalisadosVal"><?= $pctAnalisados ?>%</div>
            <div class="sub" id="kpiAnalisadosSub"><?= $analisados ?> ficheiros</div>
            <div class="kpi-extra">
                <span><i class="bi bi-clock"></i> Gasto: <strong id="kpiTempoGasto"><?= htmlspecialchars($tempoGastoDisplay) ?></strong></span>
                <span><i class="bi bi-lightning-charge-fill"></i> Economizado: <strong id="kpiTempoEconomizado" style="color:var(--green);"><?= htmlspecialchars($tempoEconomizadoDisplay) ?></strong></span>
            </div>
        </div>
    </div>

    <!-- CARD 2: Análises Boas -->
    <div class="dbw-stat" style="cursor: pointer;" onclick="window.location.href='relatorios.php?filter=boas'">
        <div>
            <div class="lbl">Desempenho Excelente</div>
            <div class="val val-green" id="kpiBoasVal"><?= $pctBoas ?>%</div>
            <div class="sub" id="kpiBoasSub"><?= $boas ?> ficheiros</div>
            <div class="kpi-meaning" style="color: var(--green);">
                <i class="bi bi-patch-check-fill"></i> ≥ 80% conformidade
            </div>
        </div>
    </div>

    <!-- CARD 3: Análises Aceitáveis -->
    <div class="dbw-stat" style="cursor: pointer;" onclick="window.location.href='relatorios.php?filter=aceitaveis'">
        <div>
            <div class="lbl">Desempenho Aceitável</div>
            <div class="val val-amber" id="kpiAceitaveisVal"><?= $pctAceitaveis ?>%</div>
            <div class="sub" id="kpiAceitaveisSub"><?= $aceitaveis ?> ficheiros</div>
            <div class="kpi-meaning" style="color: var(--amber);">
                <i class="bi bi-patch-exclamation-fill"></i> 60% a 79% conformidade
            </div>
        </div>
    </div>

    <!-- CARD 4: Análises Críticas -->
    <div class="dbw-stat" style="cursor: pointer;" onclick="window.location.href='relatorios.php?filter=criticas'">
        <div>
            <div class="lbl">Desempenho Crítico</div>
            <div class="val val-red" id="kpiCriticasVal"><?= $pctCriticas ?>%</div>
            <div class="sub" id="kpiCriticasSub"><?= $criticas ?> ficheiros</div>
            <div class="kpi-meaning" style="color: var(--red);">
                <i class="bi bi-patch-minus-fill"></i> &lt; 60% conformidade
            </div>
        </div>
     </div>
</div>

<!-- ─ 2. GRELHA PRINCIPAL ─ -->
<div class="dbw-main-grid">
    
    <!-- COLUNA ESQUERDA: OPORTUNIDADES DE COACHING -->
    <div class="dbw-card">
        <div class="dbw-card-head">
            <div class="dbw-head-gap">
                <span class="dbw-title"><i class="bi bi-lightbulb-fill"></i> Oportunidades de Coaching (Hoje)</span>
                <span class="dbw-live" id="liveBadge" title="Actualização automática a cada 3 segundos">
                    <span class="dot"></span> AUTO 3s · <span id="lastUpdate">—</span>
                </span>
            </div>
            <div style="display:flex; align-items:center; gap:6px;">
                <div class="dbw-date-selector" id="dateSelector">
                    <select id="selYear" title="Ano"></select>
                    <span class="sep">/</span>
                    <select id="selMonth" title="Mês"></select>
                    <span class="sep">/</span>
                    <select id="selDay" title="Dia"></select>
                </div>
                <span class="dbw-loading" id="dateLoading"><span class="spinner"></span></span>
            </div>
        </div>

        <?php if (empty($topCoaching)): ?>
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:200px;color:var(--green);text-align:center;">
                <i class="bi bi-check-circle-fill" style="font-size:32px;margin-bottom:10px;"></i>
                <strong>Sem alertas críticos hoje!</strong>
                <span style="font-size:12px;color:var(--text3);margin-top:4px;">Todos os agentes acima de 60%.</span>
            </div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <?php foreach ($topCoaching as $opp): ?>
                <div class="coaching-item">
                    <div class="coaching-score"><?= number_format($opp['score'], 0) ?>%</div>
                    <div class="coaching-info">
                        <div class="coaching-agent"><?= htmlspecialchars($opp['agent']) ?></div>
                        <div class="coaching-reason">
                            <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($opp['area']) ?>
                        </div>
                    </div>
                    <a href="perfil_agente.php?id=<?= urlencode($opp['key']) ?>" class="btn-coach-action">
                        Ver Perfil <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- COLUNA DIREITA: EFICIÊNCIA DO ANALYSER (NOVO) -->
    <div class="dbw-card">
        <div class="dbw-card-head">
            <span class="dbw-title"><i class="bi bi-stopwatch"></i> Eficiência do Analyser</span>
            <span style="font-size:11px;color:var(--text3);"><?= $callsForEfficiency ?> calls processadas</span>
        </div>
        
        <div class="eff-card-body">
            <!-- Tempo Máquina -->
            <div class="eff-row">
                <div class="eff-icon"><i class="bi bi-cpu"></i></div>
                <div class="eff-info">
                    <div class="eff-label">Tempo Analyser</div>
                    <div class="eff-value"><?= htmlspecialchars($tempoGastoDisplay) ?></div>
                    <div class="eff-sub">Processamento automático total</div>
                </div>
            </div>

            <!-- Tempo Humano Estimado -->
            <div class="eff-row" style="border-left-color: var(--amber);">
                <div class="eff-icon" style="background:var(--amber-soft);color:var(--amber);"><i class="bi bi-person"></i></div>
                <div class="eff-info">
                    <div class="eff-label">Tempo Humano Estimado</div>
                    <div class="eff-value"><?= htmlspecialchars($tempoHumanoEstimado) ?></div>
                    <div class="eff-sub">Base: 15 min/call para QA manual</div>
                </div>
            </div>

            <!-- Destaque: Significado para Howard Johnson -->
            <div class="eff-highlight">
                <div class="eff-highlight-title"><i class="bi bi-graph-up-arrow"></i> Impacto Howard Johnson</div>
                <div class="eff-highlight-text">
                    Economizámos <strong><?= htmlspecialchars($tempoEconomizadoDisplay) ?></strong> de trabalho manual.
                    Isso equivale a <strong><?= ceil($savedMinutes / 60) ?> horas</strong> de supervisores focados em coaching estratégico em vez de auditoria repetitiva.
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
(function () {
    const theme = document.documentElement.getAttribute('data-theme') || 'dark';
    const API_URL = '<?= API_BASE ?>';
    const REFRESH_MS = 3000;
    
    const $id = (id) => document.getElementById(id);
    const setText = (id, txt) => { const el = $id(id); if (el) el.textContent = txt; };
    const escapeHtml = (s) => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const fmtDate = (s) => {
        if (!s) return '—';
        const d = new Date(String(s).replace(' ', 'T'));
        return isNaN(d.getTime()) ? '—' : d.toLocaleDateString('pt-PT');
    };
    const pad = (n) => String(n).padStart(2, '0');

    let _busyDash = false;
    let currentSelectedDate = '<?= $todayStr ?>';

    const selYear  = $id('selYear');
    const selMonth = $id('selMonth');
    const selDay   = $id('selDay');
    const loading  = $id('dateLoading');
    const MESES = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];

    function fillSelectors() {
        const now = new Date();
        const curY = now.getFullYear();
        const curM = now.getMonth() + 1;
        const [iy, im, id] = currentSelectedDate.split('-').map(Number);

        selYear.innerHTML = '';
        for (let y = 2024; y <= curY + 2; y++) {
            const opt = document.createElement('option');
            opt.value = y; opt.textContent = y;
            if (y === (iy || curY)) opt.selected = true;
            selYear.appendChild(opt);
        }

        selMonth.innerHTML = '';
        for (let m = 1; m <= 12; m++) {
            const opt = document.createElement('option');
            opt.value = pad(m); opt.textContent = MESES[m-1];
            if (m === (im || curM)) opt.selected = true;
            selMonth.appendChild(opt);
        }
        fillDays();
    }

    function fillDays() {
        const y = parseInt(selYear.value);
        const m = parseInt(selMonth.value);
        const daysInMonth = new Date(y, m, 0).getDate();
        const curD = parseInt(selDay.value) || 1;
        selDay.innerHTML = '';
        for (let d = 1; d <= daysInMonth; d++) {
            const opt = document.createElement('option');
            opt.value = pad(d); opt.textContent = pad(d);
            if (d === curD) opt.selected = true;
            selDay.appendChild(opt);
        }
    }

    function getSelectedDate() { return `${selYear.value}-${selMonth.value}-${selDay.value}`; }

    selYear.addEventListener('change', () => { fillDays(); onDateChange(); });
    selMonth.addEventListener('change', () => { fillDays(); onDateChange(); });
    selDay.addEventListener('change', onDateChange);

    function onDateChange() {
        const newDate = getSelectedDate();
        if (newDate === currentSelectedDate) return;
        currentSelectedDate = newDate;
        refreshDashboard(true);
    }

    function stampUpdate(ok) {
        const el = $id('lastUpdate');
        const badge = $id('liveBadge');
        if (el) el.textContent = new Date().toLocaleTimeString('pt-PT');
        if (badge) badge.classList.toggle('stale', !ok);
    }

    async function refreshDashboard(forced = false) {
        if (_busyDash) return;
        _busyDash = true;
        if (forced && loading) loading.classList.add('show');

        try {
            const url = API_URL + '/stats/dashboard?date=' + encodeURIComponent(currentSelectedDate) + '&t=' + Date.now();
            const r = await fetch(url, { cache: 'no-store' });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const d = await r.json();
            
            const c = d.cards || {};
            const an = c.analisado ?? 0, fa = c.falhou ?? 0, pe = c.pendente ?? 0;
            const tot = an + fa + pe;
            const pct = (n) => tot > 0 ? Math.round((n / tot) * 100) : 0;
            
            const boas = c.boas ?? 0;
            const aceitaveis = c.aceitaveis ?? 0;
            const criticas = c.criticas ?? 0;
            const totalClass = boas + aceitaveis + criticas;
            const pctClass = (n) => totalClass > 0 ? Math.round((n / totalClass) * 100) : 0;
            
            setText('kpiAnalisadosVal', pct(an) + '%');
            setText('kpiAnalisadosSub', an + ' ficheiros');
            setText('kpiTempoGasto', c.tempo_gasto ?? '—');
            setText('kpiTempoEconomizado', c.tempo_economizado ?? '—');
            
            setText('kpiBoasVal', pctClass(boas) + '%');
            setText('kpiBoasSub', boas + ' ficheiros');
            setText('kpiAceitaveisVal', pctClass(aceitaveis) + '%');
            setText('kpiAceitaveisSub', aceitaveis + ' ficheiros');
            setText('kpiCriticasVal', pctClass(criticas) + '%');
            setText('kpiCriticasSub', criticas + ' ficheiros');
            
            stampUpdate(true);
        } catch (e) {
            console.warn('Auto-refresh falhou:', e.message);
            stampUpdate(false);
        } finally {
            _busyDash = false;
            if (loading) loading.classList.remove('show');
        }
    }

    fillSelectors();

    (function tickDash() {
        if (!document.hidden) refreshDashboard(false);
        setTimeout(tickDash, REFRESH_MS);
    })();

    refreshDashboard(false);
})();
</script>
<?php include 'includes/footer.php'; ?>
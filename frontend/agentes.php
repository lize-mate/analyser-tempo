<?php
require_once 'includes/config.php';
require_once 'includes/api.php';
requireLogin();
$pageTitle     = 'Perfil dos Agentes';
$activeSection = 'agentes';
/* ── Perfis guardados ── */
$agentsFile = __DIR__ . '/../backend/storage/agentes.json';
if (!is_dir(dirname($agentsFile))) mkdir(dirname($agentsFile), 0777, true);
$profiles = file_exists($agentsFile) ? (json_decode(file_get_contents($agentsFile), true) ?: []) : [];
$feedback = ''; $feedbackType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
if ($_POST['action'] === 'create_agent') {
$name = trim($_POST['agent_name'] ?? '');
if ($name === '') { $feedback = 'O nome do agente é obrigatório.'; $feedbackType = 'error'; }
else {
$key = strtolower(str_replace([' ', '-'], '_', $name));
$profiles[$key] = [
'name'       => $name,
'email'      => trim($_POST['agent_email'] ?? ''),
'team'       => trim($_POST['agent_team'] ?? ''),   // Campanha
'role'       => trim($_POST['agent_role'] ?? ''),   // Categoria
'phone'      => trim($_POST['agent_phone'] ?? ''),
'notes'      => '',
'created_at' => date('Y-m-d H:i:s'),
];
file_put_contents($agentsFile, json_encode($profiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
$feedback = "Perfil de \"$name\" guardado com sucesso."; $feedbackType = 'success';
}
}
if ($_POST['action'] === 'delete_agent') {
$key = trim($_POST['agent_key'] ?? '');
if (isset($profiles[$key])) {
$n = $profiles[$key]['name'];
unset($profiles[$key]);
file_put_contents($agentsFile, json_encode($profiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
$feedback = "Perfil de \"$n\" eliminado."; $feedbackType = 'success';
}
}
}
/* ── Estatísticas reais por agente ── */
$pdfData = apiGet('/pdfs/list');
$files   = $pdfData['files'] ?? [];
$agentStats = [];
foreach ($files as $f) {
$stem  = (string)($f['audio'] ?? '');
$parts = explode('_', $stem);
$raw   = isset($parts[1]) ? str_replace('-', ' ', $parts[1]) : '';
if ($raw === '') continue;
$key   = strtolower(str_replace([' ', '-'], '_', $raw));
$score = isset($f['score']) && $f['score'] !== null ? (float)$f['score'] : null;
if (!isset($agentStats[$key])) $agentStats[$key] = ['name' => ucwords($raw), 'total' => 0, 'scored' => 0, 'soma' => 0];
$agentStats[$key]['total']++;
if ($score !== null) { $agentStats[$key]['scored']++; $agentStats[$key]['soma'] += $score; }
}
$allAgents = [];
foreach ($agentStats as $key => $s) {
$allAgents[$key] = [
'name' => $s['name'], 'total' => $s['total'], 'scored' => $s['scored'],
'avg' => $s['scored'] > 0 ? round($s['soma'] / $s['scored'], 1) : 0,
'profile' => $profiles[$key] ?? null,
];
}
foreach ($profiles as $key => $p) {
if (!isset($allAgents[$key])) $allAgents[$key] = ['name' => $p['name'], 'total' => 0, 'scored' => 0, 'avg' => 0, 'profile' => $p];
}
usort($allAgents, fn($a, $b) => $b['avg'] <=> $a['avg']);
function agColor(float $s): string {
if ($s >= 80) return '#22c58b';
if ($s >= 60) return '#f5a623';
return '#f0506e';
}
include 'includes/header.php';
?>
<div class="ag-container">
<?php if ($feedback): ?>
<div class="alert alert-<?= $feedbackType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" style="border-radius:0; flex-shrink:0;">
<?= htmlspecialchars($feedback) ?>
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<div class="ag-list-card" style="width:100%;">
<div class="ag-list-head">
<h3 class="ag-list-title"><i class="bi bi-people-fill"></i> Agentes Registados</h3>
<span class="ag-count"><?= count($allAgents) ?> agente(s)</span>
</div>
<div class="ag-list-scroll">
<?php if (empty($allAgents)): ?>
<div class="ag-empty">
<i class="bi bi-people"></i>
Sem agentes registados ainda.
</div>
<?php else: ?>
<table class="ag-table" style="width:100%;">
<thead>
<tr>
<th style="width:40px;">#</th>
<th>Agente</th>
<th style="text-align:center;">Chamadas</th>
<th style="text-align:right;">Média</th>
<th style="text-align:right; width:110px;">Acções</th>
</tr>
</thead>
<tbody>
<?php $i = 1; foreach ($allAgents as $key => $a): $c = agColor($a['avg']); ?>
<tr>
<td style="color:var(--text3);"><?= $i++ ?></td>
<td>
<div class="ag-name-cell">
<div class="ag-avatar <?= $a['profile'] ? 'has-profile' : '' ?>">
<?= htmlspecialchars(mb_strtoupper(mb_substr($a['name'], 0, 1) . mb_substr($a['name'], -1, 1))) ?>
</div>
<div>
<div style="font-weight:600;"><?= htmlspecialchars($a['name']) ?></div>
<div style="font-size:11px;color:var(--text3);"><?= htmlspecialchars($a['profile']['team'] ?? ($a['profile']['role'] ?? 'Sem perfil')) ?></div>
</div>
</div>
</td>
<td style="text-align:center;"><?= $a['total'] ?></td>
<td style="text-align:right;">
<span class="ag-pct" style="color:<?= $c ?>"><?= $a['avg'] > 0 ? number_format($a['avg'], 0) . '%' : '—' ?></span>
</td>
<td style="text-align:right;">
<a class="ag-btn-sm" href="perfil_agente.php?id=<?= urlencode($key) ?>" title="Ver perfil"><i class="bi bi-eye"></i></a>
<?php if ($a['profile']): ?>
<form method="POST" action="perfil_agente.php" style="display:inline;" onsubmit="return confirm('Eliminar este perfil?');">
<input type="hidden" name="action" value="delete_agent">
<input type="hidden" name="agent_key" value="<?= htmlspecialchars($key) ?>">
<button type="submit" class="ag-btn-sm danger" title="Eliminar perfil"><i class="bi bi-trash3"></i></button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
</div>
</div>
<?php include 'includes/footer.php'; ?>
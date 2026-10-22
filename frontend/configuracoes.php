<?php
require_once 'includes/config.php';
require_once 'includes/api.php';
if (file_exists(__DIR__ . '/includes/mailer.php')) require_once 'includes/mailer.php';
if (file_exists(__DIR__ . '/includes/logs.php')) require_once __DIR__ . '/includes/logs.php';
requireLogin();
requireAdmin();
$pageTitle     = 'Definições';
$activeSection = 'configuracoes';

/* ── Fallbacks para funções que podem não existir ── */
if (!function_exists('qa_logs_read')) {
    function qa_logs_read(string $file): array {
        $p = __DIR__ . '/../backend/storage/' . $file;
        if (!file_exists($p)) return [];
        $d = json_decode(file_get_contents($p), true);
        return is_array($d) ? $d : [];
    }
}
if (!function_exists('qa_logs_write')) {
    function qa_logs_write(string $file, array $data): void {
        $dir = __DIR__ . '/../backend/storage';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        file_put_contents($dir . '/' . $file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
if (!function_exists('logAudit')) {
    function logAudit(string $action, string $details = ''): void {
        $u = function_exists('currentUser') ? currentUser() : [];
        $logs = qa_logs_read('audit_log.json');
        array_unshift($logs, ['user' => $u['username'] ?? 'system', 'action' => $action, 'details' => $details, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'at' => date('Y-m-d H:i:s')]);
        qa_logs_write('audit_log.json', array_slice($logs, 0, 500));
    }
}
if (!function_exists('sessionKill')) {
    function sessionKill(string $sid): bool {
        $s = qa_logs_read('sessions.json'); $done = false;
        foreach ($s as &$x) { if (($x['sid'] ?? '') === $sid) { $x['active'] = false; $done = true; break; } }
        unset($x);
        if ($done) qa_logs_write('sessions.json', $s);
        return $done;
    }
}
if (!function_exists('shortAgent')) {
    function shortAgent(string $a): string {
        $os = preg_match('/Windows/i', $a) ? 'Windows' : (preg_match('/Macintosh|Mac OS/i', $a) ? 'macOS' : (preg_match('/Android/i', $a) ? 'Android' : (preg_match('/Linux/i', $a) ? 'Linux' : 'SO desconhecido')));
        $br = preg_match('/Edg\//i', $a) ? 'Edge' : (preg_match('/Firefox/i', $a) ? 'Firefox' : (preg_match('/Chrome/i', $a) ? 'Chrome' : (preg_match('/Safari/i', $a) ? 'Safari' : 'Browser')));
        return $br . ' · ' . $os;
    }
}

/* ── Definições da plataforma ── */
$storageDir = __DIR__ . '/../backend/storage';
if (!is_dir($storageDir)) mkdir($storageDir, 0777, true);
$platformFile = $storageDir . '/platform.json';
$platformDefaults = [
    'app_name' => 'QA Análise',
    'org_name' => 'Howard Johnson Call Centre',
    'org_email' => '', 'org_phone' => '', 'org_address' => '',
    'logo' => 'assets/logo2.png',
    'campaigns' => ['Debt collection', 'PMA'],
    'default_account_state' => 'active',
    'notification_recipients' => 'all',
    'timezone' => 'Europe/Lisbon',
    'continent' => 'Europa',
    'country' => 'Portugal',
    'city' => '',
];
$platform = array_merge($platformDefaults, file_exists($platformFile) ? (json_decode(file_get_contents($platformFile), true) ?: []) : []);
$savePlatform = function () use (&$platform, $platformFile) {
    file_put_contents($platformFile, json_encode($platform, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
};

/* ── Timezones disponíveis ── */
$timezones = [
    'Africa' => ['Africa/Lisbon' => 'Lisboa', 'Africa/Luanda' => 'Luanda', 'Africa/Maputo' => 'Maputo'],
    'Europa' => ['Europe/Lisbon' => 'Lisboa', 'Europe/London' => 'Londres', 'Europe/Paris' => 'Paris', 'Europe/Madrid' => 'Madrid', 'Europe/Berlin' => 'Berlim', 'Europe/Rome' => 'Roma'],
    'Américas' => ['America/New_York' => 'Nova Iorque', 'America/Los_Angeles' => 'Los Angeles', 'America/Chicago' => 'Chicago', 'America/Sao_Paulo' => 'São Paulo', 'America/Lima' => 'Lima'],
    'Ásia' => ['Asia/Dubai' => 'Dubai', 'Asia/Kolkata' => 'Mumbai', 'Asia/Singapore' => 'Singapura', 'Asia/Tokyo' => 'Tóquio', 'Asia/Shanghai' => 'Xangai'],
];

/* ── Processamento POST Unificado ── */
$feedback = '';
$feedbackType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_env';
    try {
        switch ($action) {
            case 'save_platform':
                $platform['app_name']  = trim($_POST['app_name']  ?? $platform['app_name']);
                $platform['org_name']  = trim($_POST['org_name']  ?? '');
                $platform['org_email'] = trim($_POST['org_email'] ?? '');
                $platform['org_phone'] = trim($_POST['org_phone'] ?? '');
                $platform['org_address'] = trim($_POST['org_address'] ?? '');
                $platform['default_account_state'] = ($_POST['default_account_state'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
                $platform['notification_recipients'] = ($_POST['notification_recipients'] ?? 'all') === 'admin' ? 'admin' : 'all';
                $platform['timezone'] = $_POST['timezone'] ?? $platform['timezone'];
                $platform['continent'] = trim($_POST['continent'] ?? $platform['continent']);
                $platform['country'] = trim($_POST['country'] ?? $platform['country']);
                $platform['city'] = trim($_POST['city'] ?? $platform['city']);
                
                if (!empty($_FILES['logo_file']['tmp_name']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'webp'], true)) {
                        $feedback = 'Formato de imagem não suportado.';
                        $feedbackType = 'error';
                    } elseif ($_FILES['logo_file']['size'] > 2 * 1024 * 1024) {
                        $feedback = 'Ficheiro muito grande. Máximo 2MB.';
                        $feedbackType = 'error';
                    } else {
                        $upDir = __DIR__ . '/assets/uploads';
                        if (!is_dir($upDir)) mkdir($upDir, 0777, true);
                        $newName = 'org_logo_' . time() . '.' . $ext;
                        if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $upDir . '/' . $newName)) {
                            $oldLogo = $platform['logo'];
                            if ($oldLogo !== 'assets/logo2.png' && file_exists(__DIR__ . '/' . $oldLogo)) {
                                @unlink(__DIR__ . '/' . $oldLogo);
                            }
                            $platform['logo'] = 'assets/uploads/' . $newName;
                            $feedback = 'Logotipo atualizado!';
                        }
                    }
                }
                $savePlatform();
                logAudit('platform.update', 'Definições guardadas');
                if ($feedback === '') $feedback = 'Configurações guardadas.';
                break;
                
            case 'reset_logo':
                $oldLogo = $platform['logo'];
                if ($oldLogo !== 'assets/logo2.png' && file_exists(__DIR__ . '/' . $oldLogo)) {
                    @unlink(__DIR__ . '/' . $oldLogo);
                }
                $platform['logo'] = 'assets/logo2.png';
                $savePlatform();
                $feedback = 'Logotipo revertido.';
                break;
                
            case 'add_campaign':
                $c = trim($_POST['campaign_name'] ?? '');
                if ($c !== '' && !in_array($c, $platform['campaigns'], true)) {
                    $platform['campaigns'][] = $c;
                    $savePlatform();
                    $feedback = "Campanha \"$c\" adicionada.";
                }
                break;
                
            case 'remove_campaign':
                $c = trim($_POST['campaign_name'] ?? '');
                $platform['campaigns'] = array_values(array_filter($platform['campaigns'], fn($x) => $x !== $c));
                $savePlatform();
                $feedback = "Campanha removida.";
                break;
                
            case 'create':
                if (!function_exists('getUserByUsername') || !function_exists('createUser')) {
                    throw new RuntimeException('Funções de gestão de utilizadores não disponíveis.');
                }
                $newUser  = trim($_POST['username']  ?? '');
                $newName  = trim($_POST['name']      ?? '');
                $newEmail = trim($_POST['email']     ?? '');
                $newPass  = $_POST['password']       ?? '';
                $newRole  = strtolower(trim($_POST['role'] ?? 'gestor'));
                if (!preg_match('/^[a-z0-9_]{3,30}$/', $newUser)) throw new RuntimeException('Utilizador inválido.');
                if (getUserByUsername($newUser)) throw new RuntimeException("Utilizador \"$newUser\" já existe.");
                if (strlen($newName) < 2) throw new RuntimeException('Nome obrigatório.');
                if (!empty($newEmail) && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('E-mail inválido.');
                if (strlen($newPass) < 6) throw new RuntimeException('Password mínima: 6 caracteres.');
                if (!in_array($newRole, ['admin', 'gestor'])) throw new RuntimeException('Perfil inválido.');
                $hashedPass = password_hash($newPass, PASSWORD_DEFAULT);
                createUser($newUser, $hashedPass, $newName, makeInitials($newName), $newRole, $newEmail);
                if (!empty($newEmail) && function_exists('sendAccountEmail')) {
                    sendAccountEmail($newEmail, $newName, $newUser, $newPass);
                }
                logAudit('user.create', "Utilizador $newUser criado");
                header('Location: configuracoes.php?tab=contas&success=1');
                exit;
                
            case 'edit_user':
                if (!function_exists('getUserByUsername') || !function_exists('editUser')) {
                    throw new RuntimeException('Funções de edição não disponíveis.');
                }
                $target    = trim($_POST['target'] ?? '');
                $newName   = trim($_POST['edit_name'] ?? '');
                $newUser   = trim($_POST['edit_username'] ?? '');
                $newEmail  = trim($_POST['edit_email'] ?? '');
                $newPass   = $_POST['edit_password'] ?? '';
                if (strlen($newName) < 2) throw new RuntimeException('Nome obrigatório.');
                if (!preg_match('/^[a-z0-9_]{3,30}$/', $newUser)) throw new RuntimeException('Utilizador inválido.');
                $existing = getUserByUsername($newUser);
                if ($existing && $existing['username'] !== $target) throw new RuntimeException("Utilizador \"$newUser\" já existe.");
                $updateData = ['name' => $newName, 'email' => $newEmail];
                if ($newUser !== $target) $updateData['username'] = $newUser;
                if (!empty($newPass)) {
                    if (strlen($newPass) < 6) throw new RuntimeException('Password mínima: 6 caracteres.');
                    $updateData['password'] = password_hash($newPass, PASSWORD_DEFAULT);
                }
                editUser($target, $updateData);
                $feedback = "Utilizador atualizado.";
                break;
                
            case 'toggle':
                if (!function_exists('toggleUserActive')) throw new RuntimeException('Função não disponível.');
                $target = $_POST['target'] ?? '';
                if ($target === 'admin' || $target === currentUser()['username']) throw new RuntimeException('Não pode alterar esta conta.');
                $newState = toggleUserActive($target);
                $feedback = "Conta " . ($newState ? 'ativada' : 'desativada') . ".";
                break;
                
            case 'delete':
                if (!function_exists('deleteUser')) throw new RuntimeException('Função não disponível.');
                $target = $_POST['target'] ?? '';
                if ($target === 'admin' || $target === currentUser()['username']) throw new RuntimeException('Não pode eliminar esta conta.');
                deleteUser($target);
                $feedback = "Conta eliminada.";
                break;
                
            case 'kill_session':
                $sid = (string)($_POST['sid'] ?? '');
                if ($sid === session_id()) {
                    session_destroy();
                    header('Location: index.php');
                    exit;
                }
                sessionKill($sid);
                $feedback = 'Sessão terminada.';
                break;
                
            default:
                $envFile = __DIR__ . '/../backend/.env';
                $envContent = file_exists($envFile) ? file_get_contents($envFile) : '';
                $updateEnv = function ($key, $value) use (&$envContent) {
                    $value = trim($value);
                    $value = str_replace('"', '\"', $value);
                    if (preg_match("/^{$key}=.*/m", $envContent)) {
                        $envContent = preg_replace("/^{$key}=.*/m", "{$key}=\"{$value}\"", $envContent);
                    } else {
                        $envContent .= "\n{$key}=\"{$value}\"";
                    }
                };
                
                // Atualiza múltiplas chaves para garantir compatibilidade entre todos os scripts Python
                $fields = [
                    'audio_dir_remote' => ['AUDIO_DIR', 'AUDIOS_DIR'],
                    'text_dir'         => 'TEXT_DIR',
                    'pdf_dir'          => 'PDF_DIR',
                    'sync_interval'    => 'SYNC_INTERVAL'
                ];
                
                foreach ($fields as $formField => $envKeys) {
                    if (isset($_POST[$formField])) {
                        $keys = is_array($envKeys) ? $envKeys : [$envKeys];
                        foreach ($keys as $envKey) {
                            $updateEnv($envKey, $_POST[$formField]);
                        }
                    }
                }
                file_put_contents($envFile, trim($envContent));
                $feedback = 'Configurações guardadas! Os serviços Python detectarão a mudança automaticamente no próximo ciclo.';
                break;
        }
    } catch (Exception $e) {
        $feedback = 'Erro: ' . $e->getMessage();
        $feedbackType = 'error';
    }
}

/* ── Valores actuais (.env) ── */
$envFile = __DIR__ . '/../backend/.env';
$envVars = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $envVars[trim($key)] = trim($val, " \"'");
        }
    }
}
$getEnv = fn($key, $default = '') => $envVars[$key] ?? $default;

/* ── Dados de segurança ── */
$sessions   = qa_logs_read('sessions.json');
$accessLogs = array_slice(qa_logs_read('access_log.json'), 0, 15);
$auditLogs  = array_slice(qa_logs_read('audit_log.json'), 0, 15);

/* ── Carregar utilizadores (com fallback) ── */
$users = [];
$dbOk = true;
$dbError = '';
try {
    if (function_exists('loadUsers')) {
        $users = loadUsers();
    } else {
        $usersFile = $storageDir . '/users.json';
        if (file_exists($usersFile)) {
            $users = json_decode(file_get_contents($usersFile), true) ?: [];
        }
    }
} catch (Exception $e) {
    $dbOk = false;
    $dbError = $e->getMessage();
}
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $feedback = 'Conta criada com sucesso!';
    $feedbackType = 'success';
}

include 'includes/header.php';
?>
<style>
/* ══════════════════════════════════════════════════════════════════
SETTINGS — LAYOUT BASE
══════════════════════════════════════════════════════════════════ */
.settings-wrapper { max-width: 1100px; margin: 0 auto; padding: 20px 0 60px; }
.settings-header { margin-bottom: 24px; }
.settings-header h1 { font-family: var(--font-display); font-size: 26px; font-weight: 700; color: var(--text); margin: 0 0 4px 0; }
.settings-header p { font-size: 13px; color: var(--text3); margin: 0; }
.settings-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 24px; }
.settings-tab { padding: 8px 16px; border-radius: 999px; font-size: 13px; font-weight: 500; color: var(--text2); background: var(--bg3); border: 1px solid var(--border); cursor: pointer; transition: all 0.2s ease; font-family: var(--font-body); display: inline-flex; align-items: center; gap: 6px; }
.settings-tab:hover { background: var(--surface2); color: var(--text); }
.settings-tab.active { background: var(--accent); color: #fff; border-color: var(--accent); }
.settings-panel { display: none; }
.settings-panel.active { display: block; animation: fadePanel 0.25s ease; }
@keyframes fadePanel { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
.settings-section { background: var(--surface, #13151a); border: 1px solid var(--border); border-radius: 12px; padding: 24px; margin-bottom: 16px; }
.settings-section:last-child { margin-bottom: 0; }
.settings-section-header { margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px solid var(--border); }
.settings-section-title { font-family: var(--font-display); font-size: 16px; font-weight: 600; color: var(--text); margin: 0 0 4px 0; }
.settings-section-desc { font-size: 12.5px; color: var(--text3); margin: 0; line-height: 1.4; }
.settings-section-content { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; padding-top: 4px; }
/* Logo Upload */
.logo-upload-row { display: flex; align-items: center; gap: 24px; flex-wrap: wrap; width: 100%; }
.logo-preview { width: 160px; height: 60px; object-fit: contain; background: var(--bg3); border: 1px solid var(--border); border-radius: 8px; padding: 8px; }
.logo-controls { display: flex; flex-direction: column; gap: 10px; flex: 1; min-width: 200px; }
.logo-upload-btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; border: 1px solid var(--border); background: var(--surface); color: var(--text); cursor: pointer; transition: all 0.2s; }
.logo-upload-btn:hover { border-color: var(--accent); color: var(--accent); }
.logo-current-info { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--text3); }
.logo-current-info code { background: var(--bg3); padding: 3px 8px; border-radius: 4px; font-size: 11px; color: var(--accent); }
.logo-remove-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; border: 1px solid var(--border); background: transparent; color: var(--red); cursor: pointer; }
.logo-remove-btn:hover { background: var(--red-soft); border-color: var(--red); }
/* Form Fields */
.settings-field { margin-bottom: 16px; }
.settings-field label { display: block; font-size: 13px; font-weight: 600; color: var(--text2); margin-bottom: 8px; }
.settings-field input[type="text"], .settings-field input[type="email"], .settings-field input[type="password"], .settings-field input[type="number"], .settings-field select { width: 100%; padding: 12px 14px; background: var(--surface); border: 1.5px solid var(--border); border-radius: 8px; color: var(--text); font-size: 14px; outline: none; min-height: 46px; box-sizing: border-box; }
.settings-field input:focus, .settings-field select:focus { border-color: var(--accent); box-shadow: 0 0 0 4px var(--accent-soft); }
.settings-select-sm { width: 200px; padding: 12px 14px; background: var(--surface); border: 1.5px solid var(--border); border-radius: 8px; color: var(--text); font-size: 14px; outline: none; cursor: pointer; min-height: 46px; }
.btn-settings-save { display: inline-flex; align-items: center; gap: 6px; background: var(--accent); color: #fff; border: none; padding: 12px 18px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; min-height: 46px; }
.btn-settings-save:hover { opacity: 0.9; }
.settings-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; }
.settings-alert { padding: 12px 18px; border-radius: 8px; font-size: 14px; font-weight: 500; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
.settings-alert-success { background: var(--green-soft); color: var(--green); border: 1px solid rgba(34,197,139,0.2); }
.settings-alert-error { background: var(--red-soft); color: var(--red); border: 1px solid rgba(240,80,110,0.2); }
/* Input Line Style - Only Bottom Border */
.path-input-line {
    width: 100%;
    padding: 10px 0;
    background: transparent;
    border: none;
    border-bottom: 2px solid var(--border);
    color: var(--text);
    font-size: 14px;
    outline: none;
    transition: border-color 0.3s ease;
}
.path-input-line:focus {
    border-bottom-color: var(--accent);
}
.path-input-line::placeholder {
    color: var(--text3);
}
.settings-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
.settings-table th { font-size: 10px; text-transform: uppercase; color: var(--text3); text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--border); font-weight: 700; background: var(--bg3); }
.settings-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); font-size: 13px; color: var(--text2); }
.settings-badge { display: inline-block; padding: 2px 7px; border-radius: 4px; font-size: 9.5px; font-weight: 700; }
.settings-badge-ok { background: var(--green-soft); color: var(--green); }
.settings-badge-fail { background: var(--red-soft); color: var(--red); }
.settings-badge-end { background: var(--surface2); color: var(--text3); }
/* ═══ GESTÃO DE CONTAS ═══ */
.gc-search-wrap { position: relative; width: 100%; margin-bottom: 18px; }
.gc-search-input { width: 100%; height: 50px; padding: 0 18px 0 48px; background: var(--surface); border: 1px solid var(--border); border-radius: 14px; color: var(--text); font-size: 14px; outline: none; }
.gc-search-input:focus { border-color: var(--accent); box-shadow: 0 0 0 4px var(--accent-soft); }
.gc-search-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--text3); font-size: 16px; }
.gc-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-bottom: 20px; }
.gc-chips { display: flex; gap: 10px; flex-wrap: wrap; }
.gc-chip { display: inline-flex; align-items: center; gap: 7px; padding: 9px 18px; background: var(--surface); border: 1px solid var(--border); border-radius: 999px; font-size: 13px; font-weight: 600; color: var(--text2); cursor: pointer; }
.gc-chip.active { background: var(--accent); border-color: var(--accent); color: #fff; }
.gc-chip .chip-count { background: rgba(255,255,255,0.2); padding: 2px 7px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.btn-gc-open { display: inline-flex; align-items: center; gap: 9px; height: 44px; padding: 0 22px; border-radius: 999px; background: var(--accent); color: #fff; border: none; font-size: 14px; font-weight: 600; cursor: pointer; }
.btn-gc-open:hover { opacity: 0.9; }
.gc-users-card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; overflow: hidden; }
.gc-users-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 22px; border-bottom: 1px solid var(--border); background: var(--bg3); }
.gc-users-title { display: flex; align-items: center; gap: 10px; font-size: 14px; font-weight: 600; color: var(--text); }
.gc-users-count { background: var(--accent-soft); color: var(--accent); border-radius: 999px; font-size: 12px; font-weight: 700; padding: 3px 11px; }
.gc-users-list { display: flex; flex-direction: column; }
.gc-user-item { display: flex; align-items: center; gap: 16px; padding: 18px 22px; border-bottom: 1px solid var(--border); }
.gc-user-item:last-child { border-bottom: none; }
.gc-user-item:hover { background: var(--accent-soft); }
.gc-user-avatar { width: 44px; height: 44px; border-radius: 50%; background: var(--accent-soft); color: var(--accent); display: grid; place-items: center; font-size: 14px; font-weight: 700; flex-shrink: 0; }
.gc-user-avatar.is-admin { background: var(--accent); color: #fff; }
.gc-user-main { flex: 1; min-width: 0; }
.gc-user-name-row { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; }
.gc-user-name { font-weight: 600; font-size: 14px; color: var(--text); }
.gc-badge-you { background: var(--accent-soft); color: var(--accent); border-radius: 999px; font-size: 10px; font-weight: 700; padding: 2px 8px; }
.gc-user-meta { display: flex; flex-wrap: wrap; gap: 14px; font-size: 12px; color: var(--text3); }
.gc-meta-item { display: flex; align-items: center; gap: 4px; }
.gc-user-right { display: flex; align-items: center; gap: 16px; flex-shrink: 0; }
.gc-user-status { display: flex; align-items: center; gap: 7px; font-size: 12.5px; font-weight: 500; color: var(--text2); }
.gc-status-dot { width: 8px; height: 8px; border-radius: 50%; }
.gc-status-dot.active { background: var(--green); }
.gc-status-dot.inactive { background: var(--text3); }
.gc-user-actions { display: flex; gap: 7px; }
.gc-action-btn { display: inline-flex; align-items: center; gap: 6px; padding: 0 13px; height: 34px; background: var(--bg3); border: 1px solid var(--border); border-radius: 9px; font-size: 12.5px; color: var(--text2); cursor: pointer; }
.gc-action-btn:hover { background: var(--surface2); color: var(--text); }
.gc-action-btn.danger { color: var(--red); }
.gc-action-btn.danger:hover { background: var(--red-soft); }
.gc-protected { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; border: 1px dashed var(--border); border-radius: 999px; font-size: 11.5px; color: var(--text3); }
.gc-empty { text-align: center; padding: 60px 20px; color: var(--text3); }
/* Modals */
.gc-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(6px); z-index: 2000; display: none; align-items: center; justify-content: center; padding: 20px; }
.gc-modal-overlay.show { display: flex; }
.gc-modal { background: var(--surface); border: 1px solid var(--border); border-radius: 20px; width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; }
.gc-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 22px 26px; border-bottom: 1px solid var(--border); }
.gc-modal-title { font-size: 18px; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 10px; }
.gc-modal-close { width: 34px; height: 34px; border-radius: 50%; background: transparent; border: 1px solid var(--border); color: var(--text3); cursor: pointer; display: grid; place-items: center; }
.gc-modal-close:hover { background: var(--red-soft); color: var(--red); }
.gc-modal-body { padding: 26px; }
.gc-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.gc-form-field { display: flex; flex-direction: column; }
.gc-form-field.full { grid-column: 1 / -1; }
.gc-form-field label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 7px; }
.gc-form-field input, .gc-form-field select { width: 100%; height: 44px; padding: 0 14px; background: var(--surface); border: 1px solid var(--border); border-radius: 10px; color: var(--text); font-size: 13.5px; outline: none; }
.gc-form-field input:focus, .gc-form-field select:focus { border-color: var(--accent); box-shadow: 0 0 0 4px var(--accent-soft); }
.gc-pass-row { display: flex; gap: 8px; align-items: center; }
.gc-pass-row input { flex: 1; }
.gc-pass-copy { width: 44px; height: 44px; border-radius: 10px; background: var(--bg3); border: 1px solid var(--border); color: var(--text3); cursor: pointer; display: grid; place-items: center; }
.gc-modal-footer { padding: 18px 26px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; }
.btn-gc-cancel { padding: 0 20px; height: 42px; border-radius: 10px; background: var(--bg3); border: 1px solid var(--border); color: var(--text2); font-weight: 600; cursor: pointer; }
.btn-gc-create { display: inline-flex; align-items: center; gap: 8px; background: var(--accent); color: #fff; border: none; padding: 0 20px; height: 42px; border-radius: 10px; font-weight: 600; cursor: pointer; }
@media (max-width: 768px) {
    .settings-section-content { flex-direction: column; align-items: flex-start; }
    .logo-upload-row { flex-direction: column; align-items: flex-start; }
    .gc-form-grid { grid-template-columns: 1fr; }
    .gc-toolbar { flex-direction: column; align-items: stretch; }
    .gc-user-item { flex-wrap: wrap; }
    .gc-user-right { width: 100%; justify-content: space-between; padding-top: 10px; border-top: 1px solid var(--border); margin-top: 4px; }
}
</style>

<div class="settings-wrapper">
    <?php if ($feedback): ?>
    <div class="settings-alert settings-alert-<?= $feedbackType === 'success' ? 'success' : 'error' ?>">
        <i class="bi bi-<?= $feedbackType === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill' ?>"></i>
        <?= htmlspecialchars($feedback) ?>
    </div>
    <?php endif; ?>
    
    <div class="settings-header">
        <h1>Configurações</h1>
        <p>Modifique as configurações da plataforma, contas e preferências</p>
    </div>
    
    <!-- Tabs -->
    <div class="settings-tabs" role="tablist">
        <button class="settings-tab active" data-tab="geral" role="tab">
            <i class="bi bi-display"></i> Geral
        </button>
        <button class="settings-tab" data-tab="contas" role="tab">
            <i class="bi bi-people-fill"></i> Contas
        </button>
        <button class="settings-tab" data-tab="sistema" role="tab">
            <i class="bi bi-gear-fill"></i> Sistema
        </button>
        <button class="settings-tab" data-tab="registos" role="tab">
            <i class="bi bi-shield-lock-fill"></i> Registos
        </button>
    </div>
    
    <!-- ══ TAB 1: GERAL ═══ -->
    <div class="settings-panel active" id="panel-geral">
        <!-- Logótipo -->
        <div class="settings-section">
            <div class="settings-section-header">
                <h3 class="settings-section-title">Logótipo da Empresa</h3>
                <p class="settings-section-desc">Carregue o logotipo da sua organização (PNG, JPG, SVG, WebP — máx. 2MB)</p>
            </div>
            <div class="settings-section-content">
                <form method="POST" action="configuracoes.php" enctype="multipart/form-data" id="logoForm" class="logo-upload-row">
                    <input type="hidden" name="action" value="save_platform">
                    <img src="<?= htmlspecialchars($platform['logo']) ?>" class="logo-preview" alt="Logotipo" id="logoPreview">
                    <div class="logo-controls">
                        <label class="logo-upload-btn" for="logo_file"><i class="bi bi-camera-fill"></i> Alterar imagem</label>
                        <input type="file" id="logo_file" name="logo_file" accept=".png,.jpg,.jpeg,.svg,.webp" style="display:none;" onchange="document.getElementById('logoForm').submit()">
                        <div class="logo-current-info">
                            <span>Ficheiro atual:</span>
                            <code><?= htmlspecialchars($platform['logo']) ?></code>
                        </div>
                        <?php if ($platform['logo'] !== 'assets/logo2.png'): ?>
                        <button type="button" class="logo-remove-btn" onclick="if(confirm('Remover logotipo?')){document.getElementById('logoForm').action='configuracoes.php?reset=1';document.getElementById('logoForm').submit();}">
                            <i class="bi bi-trash3"></i> Remover
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Estado Padrão -->
        <div class="settings-section">
            <div class="settings-section-header">
                <h3 class="settings-section-title">Estado Padrão das Contas</h3>
                <p class="settings-section-desc">Estado aplicado ao criar novas contas</p>
            </div>
            <div class="settings-section-content">
                <form method="POST" action="configuracoes.php" style="display:flex; align-items:center; gap:12px; width:100%;">
                    <input type="hidden" name="action" value="save_platform">
                    <select name="default_account_state" class="settings-select-sm">
                        <option value="active" <?= $platform['default_account_state'] === 'active' ? 'selected' : '' ?>>Ativo</option>
                        <option value="inactive" <?= $platform['default_account_state'] === 'inactive' ? 'selected' : '' ?>>Inativo</option>
                    </select>
                    <button type="submit" class="btn-settings-save"><i class="bi bi-check-lg"></i> Guardar</button>
                </form>
            </div>
        </div>
        
        <!-- Pastas e Sincronização -->
        <div class="settings-section">
            <div class="settings-section-header">
                <h3 class="settings-section-title">Pastas e Sincronização</h3>
                <p class="settings-section-desc">Caminhos das pastas e intervalo de sincronização</p>
            </div>
            <div class="settings-section-content" style="flex-direction:column; align-items:stretch; max-width:700px;">
                <form method="POST" action="configuracoes.php" style="width:100%;">
                    <input type="hidden" name="action" value="save_env">
                    <div class="settings-field">
                        <label for="audio_dir_remote">Pasta de Áudios</label>
                        <input type="text" id="audio_dir_remote" name="audio_dir_remote" class="path-input-line" value="<?= htmlspecialchars($getEnv('AUDIO_DIR', $getEnv('AUDIOS_DIR', '/var/spool/asterisk/monitor'))) ?>" placeholder="Digite o caminho da pasta">
                    </div>
                    <div class="settings-field">
                        <label for="text_dir">Pasta de Transcrições</label>
                        <input type="text" id="text_dir" name="text_dir" class="path-input-line" value="<?= htmlspecialchars($getEnv('TEXT_DIR', '~/text_files')) ?>" placeholder="Insira o caminho da pasta">
                    </div>
                    <div class="settings-field">
                        <label for="pdf_dir">Pasta de PDFs</label>
                        <input type="text" id="pdf_dir" name="pdf_dir" class="path-input-line" value="<?= htmlspecialchars($getEnv('PDF_DIR', '/home/hjit/pdf_files')) ?>" placeholder="Caminho da pasta de PDFs">
                    </div>
                    <div class="settings-field">
                        <label for="sync_interval">Intervalo de Sincronização (segundos)</label>
                        <input type="number" id="sync_interval" name="sync_interval" class="path-input-line" value="<?= htmlspecialchars($getEnv('SYNC_INTERVAL', '30')) ?>" placeholder="Ex: 30" min="5">
                    </div>
                    <div class="settings-actions">
                        <button type="submit" class="btn-settings-save"><i class="bi bi-check-lg"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- ══ TAB 2: CONTAS ═══ -->
    <div class="settings-panel" id="panel-contas">
        <!-- Search -->
        <div class="gc-search-wrap">
            <i class="bi bi-search gc-search-icon"></i>
            <input type="text" class="gc-search-input" id="gcSearch" placeholder="Pesquisar por nome, utilizador ou e-mail...">
        </div>
        <!-- Toolbar -->
        <div class="gc-toolbar">
            <div class="gc-chips">
                <button class="gc-chip active" data-filter="todos">
                    <span>Todos</span>
                    <span class="chip-count" id="countTodos"><?= count($users) ?></span>
                </button>
                <button class="gc-chip" data-filter="admin">
                    <i class="bi bi-shield-fill"></i>
                    <span>Administradores</span>
                    <span class="chip-count" id="countAdmin"><?= count(array_filter($users, fn($u) => ($u['role'] ?? '') === 'admin')) ?></span>
                </button>
                <button class="gc-chip" data-filter="gestor">
                    <i class="bi bi-person-fill"></i>
                    <span>Gestores</span>
                    <span class="chip-count" id="countGestor"><?= count(array_filter($users, fn($u) => ($u['role'] ?? '') === 'gestor')) ?></span>
                </button>
            </div>
            <button class="btn-gc-open" id="btnOpenCreate">
                <i class="bi bi-plus-lg"></i> Criar Conta
            </button>
        </div>
        <!-- User List -->
        <div class="gc-users-card">
            <div class="gc-users-header">
                <div class="gc-users-title"><i class="bi bi-people-fill"></i> Utilizadores Registados</div>
                <div class="gc-users-count"><?= count($users) ?></div>
            </div>
            <?php if (!$dbOk): ?>
            <div class="gc-empty">
                <i class="bi bi-database-x" style="font-size:40px;opacity:0.4"></i>
                <h5>Erro de ligação à base de dados</h5>
                <p><?= htmlspecialchars($dbError) ?></p>
            </div>
            <?php elseif (empty($users)): ?>
            <div class="gc-empty">
                <i class="bi bi-people" style="font-size:40px;opacity:0.4"></i>
                <h5>Sem utilizadores</h5>
            </div>
            <?php else: ?>
            <div class="gc-users-list" id="gcUsersList">
                <?php foreach ($users as $uname => $u):
                    $isMe    = ($uname === (currentUser()['username'] ?? ''));
                    $active  = (int)($u['active'] ?? 1) === 1;
                    $role    = $u['role'] ?? 'gestor';
                    $isProtected = ($uname === 'admin' || $isMe);
                    $initials = strtoupper(mb_substr($u['name'] ?? $uname, 0, 2));
                ?>
                <div class="gc-user-item" data-role="<?= htmlspecialchars($role) ?>" data-name="<?= htmlspecialchars(strtolower(($u['name'] ?? '') . ' ' . $uname . ' ' . ($u['email'] ?? ''))) ?>">
                    <div class="gc-user-avatar <?= $role === 'admin' ? 'is-admin' : '' ?>"><?= htmlspecialchars($initials) ?></div>
                    <div class="gc-user-main">
                        <div class="gc-user-name-row">
                            <span class="gc-user-name"><?= htmlspecialchars($u['name'] ?? $uname) ?></span>
                            <?php if ($isMe): ?><span class="gc-badge-you">VOCÊ</span><?php endif; ?>
                        </div>
                        <div class="gc-user-meta">
                            <span class="gc-meta-item"><i class="bi bi-at"></i> <?= htmlspecialchars($uname) ?></span>
                            <span class="gc-meta-item"><i class="bi bi-<?= $role === 'admin' ? 'shield-fill' : 'person-fill' ?>"></i> <?= $role === 'admin' ? 'Administrador' : 'Gestor' ?></span>
                            <?php if (!empty($u['email'])): ?><span class="gc-meta-item"><i class="bi bi-envelope"></i> <?= htmlspecialchars($u['email']) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <div class="gc-user-right">
                        <div class="gc-user-status">
                            <span class="gc-status-dot <?= $active ? 'active' : 'inactive' ?>"></span>
                            <?= $active ? 'Ativo' : 'Inativo' ?>
                        </div>
                        <?php if (!$isProtected): ?>
                        <div class="gc-user-actions">
                            <form method="POST" action="configuracoes.php" style="margin:0;display:inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="target" value="<?= htmlspecialchars($uname) ?>">
                                <button type="submit" class="gc-action-btn" title="<?= $active ? 'Desativar' : 'Ativar' ?>">
                                    <i class="bi bi-<?= $active ? 'pause-circle' : 'play-circle' ?>"></i> <?= $active ? 'Desativar' : 'Ativar' ?>
                                </button>
                            </form>
                            <button class="gc-action-btn" onclick="openEditModal('<?= htmlspecialchars($uname) ?>','<?= htmlspecialchars($u['name'] ?? '') ?>','<?= htmlspecialchars($u['email'] ?? '') ?>')">
                                <i class="bi bi-pencil-square"></i> Editar
                            </button>
                            <button class="gc-action-btn danger" onclick="if(confirm('Eliminar conta <?= htmlspecialchars($uname) ?>?')){let f=document.createElement('form');f.method='POST';f.action='configuracoes.php';let a=document.createElement('input');a.type='hidden';a.name='action';a.value='delete';let t=document.createElement('input');t.type='hidden';t.name='target';t.value='<?= htmlspecialchars($uname) ?>';f.appendChild(a);f.appendChild(t);document.body.appendChild(f);f.submit();}">
                                <i class="bi bi-trash3-fill"></i>
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="gc-protected"><i class="bi bi-lock-fill"></i> Protegida</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ═══ TAB 3: SISTEMA ═══ -->
    <div class="settings-panel" id="panel-sistema">
        <!-- Data e Hora -->
        <div class="settings-section">
            <div class="settings-section-header">
                <h3 class="settings-section-title"><i class="bi bi-clock-fill"></i> Data e Hora do Sistema</h3>
                <p class="settings-section-desc">Configure o fuso horário e a data/hora do servidor</p>
            </div>
            <div class="settings-section-content" style="flex-direction:column; align-items:stretch; max-width:700px;">
                <form method="POST" action="configuracoes.php" style="width:100%;">
                    <input type="hidden" name="action" value="save_platform">
                    <div class="settings-field">
                        <label for="timezone">Fuso Horário (Timezone)</label>
                        <select name="timezone" id="timezone" class="settings-select-sm" style="width:100%;">
                            <?php foreach ($timezones as $continent => $cities): ?>
                                <optgroup label="<?= htmlspecialchars($continent) ?>">
                                    <?php foreach ($cities as $tz => $cityName): ?>
                                        <option value="<?= htmlspecialchars($tz) ?>" <?= ($platform['timezone'] ?? '') === $tz ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cityName) ?> (<?= htmlspecialchars($tz) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="settings-field">
                        <label>Data e Hora Atual do Servidor</label>
                        <div style="padding:12px 14px; background:var(--bg3); border:1px solid var(--border); border-radius:8px; font-size:14px; color:var(--text);">
                            <i class="bi bi-calendar-check"></i> <?= date('d/m/Y') ?> &nbsp;&nbsp;
                            <i class="bi bi-clock"></i> <?= date('H:i:s') ?> &nbsp;&nbsp;
                            <span style="color:var(--text3);">(<?= date_default_timezone_get() ?>)</span>
                        </div>
                    </div>
                    <div class="settings-actions">
                        <button type="submit" class="btn-settings-save"><i class="bi bi-check-lg"></i> Guardar Fuso Horário</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Localização -->
        <div class="settings-section">
            <div class="settings-section-header">
                <h3 class="settings-section-title"><i class="bi bi-geo-alt-fill"></i> Localização</h3>
                <p class="settings-section-desc">Defina a localização geográfica da organização</p>
            </div>
            <div class="settings-section-content" style="flex-direction:column; align-items:stretch; max-width:700px;">
                <form method="POST" action="configuracoes.php" style="width:100%;">
                    <input type="hidden" name="action" value="save_platform">
                    <div class="settings-field">
                        <label for="continent">Continente</label>
                        <select name="continent" id="continent" class="settings-select-sm" style="width:100%;">
                            <option value="Europa" <?= ($platform['continent'] ?? '') === 'Europa' ? 'selected' : '' ?>>Europa</option>
                            <option value="África" <?= ($platform['continent'] ?? '') === 'África' ? 'selected' : '' ?>>África</option>
                            <option value="América do Norte" <?= ($platform['continent'] ?? '') === 'América do Norte' ? 'selected' : '' ?>>América do Norte</option>
                            <option value="América do Sul" <?= ($platform['continent'] ?? '') === 'América do Sul' ? 'selected' : '' ?>>América do Sul</option>
                            <option value="Ásia" <?= ($platform['continent'] ?? '') === 'Ásia' ? 'selected' : '' ?>>Ásia</option>
                            <option value="Oceania" <?= ($platform['continent'] ?? '') === 'Oceania' ? 'selected' : '' ?>>Oceania</option>
                        </select>
                    </div>
                    <div class="settings-field">
                        <label for="country">País</label>
                        <input type="text" id="country" name="country" value="<?= htmlspecialchars($platform['country'] ?? '') ?>" placeholder="Ex: Portugal">
                    </div>
                    <div class="settings-field">
                        <label for="city">Cidade</label>
                        <input type="text" id="city" name="city" value="<?= htmlspecialchars($platform['city'] ?? '') ?>" placeholder="Ex: Lisboa">
                    </div>
                    <div class="settings-actions">
                        <button type="submit" class="btn-settings-save"><i class="bi bi-check-lg"></i> Guardar Localização</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Informações do Sistema -->
        <div class="settings-section">
            <div class="settings-section-header">
                <h3 class="settings-section-title"><i class="bi bi-info-circle-fill"></i> Informações do Sistema</h3>
                <p class="settings-section-desc">Detalhes técnicos do servidor</p>
            </div>
            <div class="settings-section-content" style="flex-direction:column; align-items:stretch;">
                <table class="settings-table">
                    <tr>
                        <td style="font-weight:600; color:var(--text);">Sistema Operativo</td>
                        <td><?= php_uname('s') ?> <?= php_uname('r') ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; color:var(--text);">Versão do PHP</td>
                        <td><?= phpversion() ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; color:var(--text);">Servidor Web</td>
                        <td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; color:var(--text);">Fuso Horário Configurado</td>
                        <td><?= date_default_timezone_get() ?></td>
                    </tr>
                    <tr>
                        <td style="font-weight:600; color:var(--text);">Data/Hora do Servidor</td>
                        <td><?= date('d/m/Y H:i:s') ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <!-- ═══ TAB 4: REGISTOS ═══ -->
    <div class="settings-panel" id="panel-registos">
        <div class="settings-section">
            <div class="settings-section-header">
                <h3 class="settings-section-title">Registos de Acesso</h3>
                <p class="settings-section-desc">Histórico recente de acessos ao sistema</p>
            </div>
            <div class="settings-section-content" style="flex-direction:column; align-items:stretch;">
                <table class="settings-table">
                    <thead><tr><th>Data/Hora</th><th>Utilizador</th><th>Resultado</th><th>IP</th><th>Dispositivo</th></tr></thead>
                    <tbody>
                        <?php if (empty($accessLogs)): ?>
                        <tr><td colspan="5" style="text-align:center; color:var(--text3); padding:20px;">Nenhum registo de acesso.</td></tr>
                        <?php else: foreach ($accessLogs as $l): ?>
                        <tr>
                            <td><?= htmlspecialchars($l['at'] ?? '') ?></td>
                            <td><strong style="color:var(--text);"><?= htmlspecialchars($l['user'] ?? '') ?></strong></td>
                            <td><?php
                                $st = $l['status'] ?? '';
                                if ($st === 'ok') echo '<span class="settings-badge settings-badge-ok">Sucesso</span>';
                                elseif ($st === 'logout') echo '<span class="settings-badge settings-badge-end">Saída</span>';
                                else echo '<span class="settings-badge settings-badge-fail">Falha</span>';
                            ?></td>
                            <td><?= htmlspecialchars($l['ip'] ?? '') ?></td>
                            <td><?= htmlspecialchars(shortAgent($l['agent'] ?? '')) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="settings-section">
            <div class="settings-section-header">
                <h3 class="settings-section-title">Atividade Administrativa</h3>
                <p class="settings-section-desc">Ações administrativas recentes</p>
            </div>
            <div class="settings-section-content" style="flex-direction:column; align-items:stretch;">
                <table class="settings-table">
                    <thead><tr><th>Data/Hora</th><th>Utilizador</th><th>Ação</th><th>Detalhes</th></tr></thead>
                    <tbody>
                        <?php if (empty($auditLogs)): ?>
                        <tr><td colspan="4" style="text-align:center; color:var(--text3); padding:20px;">Nenhuma atividade registada.</td></tr>
                        <?php else: foreach ($auditLogs as $l): ?>
                        <tr>
                            <td><?= htmlspecialchars($l['at'] ?? '') ?></td>
                            <td><strong style="color:var(--text);"><?= htmlspecialchars($l['user'] ?? '') ?></strong></td>
                            <td><code style="font-size:10.5px; background:var(--accent-soft); color:var(--accent); padding:2px 6px; border-radius:4px;"><?= htmlspecialchars($l['action'] ?? '') ?></code></td>
                            <td><?= htmlspecialchars($l['details'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ═══ MODAL: CRIAR CONTA ═══ -->
<div class="gc-modal-overlay" id="createModal">
    <div class="gc-modal">
        <div class="gc-modal-header">
            <div class="gc-modal-title"><i class="bi bi-person-plus-fill"></i> Criar Nova Conta</div>
            <button class="gc-modal-close" onclick="closeCreateModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST" action="configuracoes.php" autocomplete="off" id="createForm">
            <div class="gc-modal-body">
                <input type="hidden" name="action" value="create">
                <div class="gc-form-grid">
                    <div class="gc-form-field">
                        <label>Utilizador <span style="color:var(--accent)">*</span></label>
                        <input type="text" name="username" id="createUsername" placeholder="ex: joao.silva" pattern="[a-z0-9_]{3,30}" required>
                    </div>
                    <div class="gc-form-field">
                        <label>Perfil <span style="color:var(--accent)">*</span></label>
                        <select name="role" id="createRole" required>
                            <option value="gestor" selected>Gestor</option>
                            <?php if (isAdmin()): ?><option value="admin">Administrador</option><?php endif; ?>
                        </select>
                    </div>
                    <div class="gc-form-field full">
                        <label>Nome Completo <span style="color:var(--accent)">*</span></label>
                        <input type="text" name="name" id="createName" placeholder="ex: João Silva" required minlength="2">
                    </div>
                    <div class="gc-form-field full">
                        <label>E-mail <span style="color:var(--accent)">*</span></label>
                        <input type="email" name="email" id="createEmail" placeholder="ex: joao@empresa.com">
                    </div>
                    <div class="gc-form-field full">
                        <label>Palavra-passe</label>
                        <div class="gc-pass-row">
                            <input type="text" name="password" id="createPassword" placeholder="Gerada automaticamente" readonly required minlength="6">
                            <button type="button" class="gc-pass-copy" title="Copiar" onclick="navigator.clipboard.writeText(document.getElementById('createPassword').value)"><i class="bi bi-clipboard"></i></button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="gc-modal-footer">
                <button type="button" class="btn-gc-cancel" onclick="closeCreateModal()">Cancelar</button>
                <button type="submit" class="btn-gc-create"><i class="bi bi-person-plus-fill"></i> Criar Conta</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ MODAL: EDITAR PERFIL ═══ -->
<div class="gc-modal-overlay" id="editModal">
    <div class="gc-modal">
        <div class="gc-modal-header">
            <div class="gc-modal-title"><i class="bi bi-pencil-square"></i> Editar Perfil</div>
            <button class="gc-modal-close" onclick="closeEditModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST" action="configuracoes.php" autocomplete="off">
            <div class="gc-modal-body">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="target" id="editTarget">
                <div class="gc-form-grid">
                    <div class="gc-form-field full">
                        <label>Nome Completo <span style="color:var(--accent)">*</span></label>
                        <input type="text" name="edit_name" id="editName" required minlength="2">
                    </div>
                    <div class="gc-form-field full">
                        <label>Nome de Utilizador <span style="color:var(--accent)">*</span></label>
                        <input type="text" name="edit_username" id="editUsername" pattern="[a-z0-9_]{3,30}" required>
                    </div>
                    <div class="gc-form-field full">
                        <label>E-mail</label>
                        <input type="email" name="edit_email" id="editEmail">
                    </div>
                    <div class="gc-form-field full">
                        <label>Nova Palavra-passe <span style="font-size:10px;color:var(--text3);font-weight:400;text-transform:none">(deixe vazio para manter)</span></label>
                        <input type="password" name="edit_password" id="editPassword" placeholder="Mínimo 6 caracteres" minlength="6">
                    </div>
                </div>
            </div>
            <div class="gc-modal-footer">
                <button type="button" class="btn-gc-cancel" onclick="closeEditModal()">Cancelar</button>
                <button type="submit" class="btn-gc-create"><i class="bi bi-check-lg"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
/* ── Troca de abas (versão robusta) ── */
(function() {
    'use strict';
    const tabs = document.querySelectorAll('.settings-tab');
    const panels = document.querySelectorAll('.settings-panel');
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            tabs.forEach(t => t.classList.remove('active'));
            panels.forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            const tabId = this.getAttribute('data-tab');
            const panel = document.getElementById('panel-' + tabId);
            if (panel) {
                panel.classList.add('active');
            }
        });
    });
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab') === 'contas') {
        const contasTab = document.querySelector('.settings-tab[data-tab="contas"]');
        if (contasTab) contasTab.click();
    } else if (urlParams.get('tab') === 'sistema') {
        const sistemaTab = document.querySelector('.settings-tab[data-tab="sistema"]');
        if (sistemaTab) sistemaTab.click();
    }
})();

/* ═══ GESTÃO DE CONTAS ═══ */
const createModal = document.getElementById('createModal');
const btnOpenCreate = document.getElementById('btnOpenCreate');
if (btnOpenCreate) {
    btnOpenCreate.addEventListener('click', () => {
        createModal.classList.add('show');
        document.body.style.overflow = 'hidden';
        setTimeout(() => document.getElementById('createUsername').focus(), 100);
    });
}
function closeCreateModal() {
    createModal.classList.remove('show');
    document.body.style.overflow = '';
}
createModal.addEventListener('click', (e) => { if (e.target === createModal) closeCreateModal(); });

const editModal = document.getElementById('editModal');
function openEditModal(username, name, email) {
    document.getElementById('editTarget').value = username;
    document.getElementById('editName').value = name;
    document.getElementById('editUsername').value = username;
    document.getElementById('editEmail').value = email;
    document.getElementById('editPassword').value = '';
    editModal.classList.add('show');
    document.body.style.overflow = 'hidden';
}
function closeEditModal() {
    editModal.classList.remove('show');
    document.body.style.overflow = '';
}
editModal.addEventListener('click', (e) => { if (e.target === editModal) closeEditModal(); });

let currentFilter = 'todos';
document.querySelectorAll('.gc-chip').forEach(chip => {
    chip.addEventListener('click', () => {
        document.querySelectorAll('.gc-chip').forEach(c => c.classList.remove('active'));
        chip.classList.add('active');
        currentFilter = chip.dataset.filter;
        applyFilters();
    });
});
const searchInput = document.getElementById('gcSearch');
if (searchInput) {
    searchInput.addEventListener('input', applyFilters);
}
function applyFilters() {
    const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const rows = document.querySelectorAll('#gcUsersList .gc-user-item');
    let visibleCount = 0;
    rows.forEach(row => {
        const role = row.dataset.role;
        const name = row.dataset.name;
        const matchFilter = currentFilter === 'todos' || role === currentFilter;
        const matchSearch = !query || name.includes(query);
        const show = matchFilter && matchSearch;
        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });
    const countEl = document.getElementById('countTodos');
    if (countEl) countEl.textContent = visibleCount;
}

function generatePassword(fullName) {
    const name = fullName.trim();
    if (name.length < 1) return '';
    const letters = Array.from(name).filter(c => /\p{L}/u.test(c));
    if (letters.length < 1) return '';
    const l1 = letters[0] ? letters[0].toUpperCase() : '';
    const l2 = letters[1] ? letters[1].toLowerCase() : (l1.toLowerCase() || '');
    const l3 = letters[2] ? letters[2].toLowerCase() : (l2 || '');
    const specials = ['@', '#', '!', '$', '%', '&', '*'];
    const special = specials[Math.floor(Math.random() * specials.length)];
    const digits = String(Math.floor(Math.random() * 900) + 100);
    return l1 + l2 + l3 + special + digits;
}
const nameInput = document.getElementById('createName');
const passInput = document.getElementById('createPassword');
if (nameInput && passInput) {
    nameInput.addEventListener('blur', function () {
        const val = this.value.trim();
        if (val.length >= 2) passInput.value = generatePassword(val);
    });
    nameInput.addEventListener('input', function () {
        if (this.value.trim().length < 2) passInput.value = '';
    });
}
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { closeCreateModal(); closeEditModal(); }
});
</script>
<?php include 'includes/footer.php'; ?>
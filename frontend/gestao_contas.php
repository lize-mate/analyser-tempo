<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // ... código existente ...
} catch (Throwable $e) {
    echo '<pre>Erro: ' . $e->getMessage() . "\n";
    echo 'Ficheiro: ' . $e->getFile() . "\n";
    echo 'Linha: ' . $e->getLine() . '</pre>';
    exit;
}

require_once 'includes/config.php';
require_once 'includes/api.php';
require_once 'includes/mailer.php';
requireAdmin();
$pageTitle     = 'Gestão de Contas';
$activeSection = 'gestao_contas';
$feedback = '';
$feedbackType = 'success';
if (isset($_GET['success'])) {
    $feedback = 'Conta criada com sucesso.';
    $feedbackType = 'success';
}

// ── Processar acções POST ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create') {
            $newUser  = trim($_POST['username']  ?? '');
            $newName  = trim($_POST['name']      ?? '');
            $newEmail = trim($_POST['email']     ?? '');
            $newPass  = $_POST['password']       ?? '';
            $newRole  = strtolower(trim($_POST['role'] ?? 'gestor'));

            if (!preg_match('/^[a-z0-9_]{3,30}$/', $newUser)) {
                throw new RuntimeException('Nome de utilizador inválido (3-30 caracteres, apenas letras minúsculas, números e _).');
            }
            if (getUserByUsername($newUser)) {
                throw new RuntimeException("O utilizador \"$newUser\" já existe na base de dados.");
            }
            if (strlen($newName) < 2) {
                throw new RuntimeException('Nome completo obrigatório (mínimo 2 caracteres).');
            }
            if (!empty($newEmail) && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Endereço de e-mail inválido.');
            }
            if (strlen($newPass) < 6) {
                throw new RuntimeException('A palavra-passe deve ter pelo menos 6 caracteres.');
            }
            if (!in_array($newRole, ['admin', 'gestor'])) {
                throw new RuntimeException('Perfil inválido.');
            }
            if ($newRole === 'admin' && !isAdmin()) {
                throw new RuntimeException('Apenas administradores podem criar contas de administrador.');
            }

            $hashedPass = password_hash($newPass, PASSWORD_DEFAULT);
            createUser($newUser, $hashedPass, $newName, makeInitials($newName), $newRole, $newEmail);
            if (!empty($newEmail)) {
                sendAccountEmail($newEmail, $newName, $newUser, $newPass);
            }
            header('Location: gestao_contas.php?success=1');
            exit;
        }
        // ═══ EDITAR UTILIZADOR ══
        elseif ($action === 'edit_user') {
            $target    = trim($_POST['target'] ?? '');
            $newName   = trim($_POST['edit_name'] ?? '');
            $newUser   = trim($_POST['edit_username'] ?? '');
            $newEmail  = trim($_POST['edit_email'] ?? '');
            $newPass   = $_POST['edit_password'] ?? '';

            if ($target === 'admin' && !isAdmin()) {
                throw new RuntimeException('Não tem permissão para editar administradores.');
            }
            if (strlen($newName) < 2) {
                throw new RuntimeException('Nome completo obrigatório (mínimo 2 caracteres).');
            }
            if (!preg_match('/^[a-z0-9_]{3,30}$/', $newUser)) {
                throw new RuntimeException('Nome de utilizador inválido (3-30 caracteres, apenas letras minúsculas, números e _).');
            }
            if (!empty($newEmail) && !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Endereço de e-mail inválido.');
            }
            // Verificar se o novo username já existe (exceto o próprio)
            $existing = getUserByUsername($newUser);
            if ($existing && $existing['username'] !== $target) {
                throw new RuntimeException("O utilizador \"$newUser\" já existe na base de dados.");
            }

            // Atualizar dados
            $updateData = [
                'name'  => $newName,
                'email' => $newEmail,
            ];
            if ($newUser !== $target) {
                $updateData['username'] = $newUser;
            }
            if (!empty($newPass)) {
                if (strlen($newPass) < 6) {
                    throw new RuntimeException('A nova palavra-passe deve ter pelo menos 6 caracteres.');
                }
                $updateData['password'] = password_hash($newPass, PASSWORD_DEFAULT);
            }

            editUser($target, $updateData);
            $feedback = "Utilizador \"$target\" atualizado com sucesso.";
        }
        elseif ($action === 'toggle') {
            $target = $_POST['target'] ?? '';
            if ($target === 'admin' || $target === currentUser()['username']) {
                throw new RuntimeException('Não é possível alterar o estado desta conta.');
            }
            $newState = toggleUserActive($target);
            $label    = $newState ? 'ativada' : 'desativada';
            $feedback = "Conta \"$target\" $label com sucesso.";
        }
        elseif ($action === 'delete') {
            $target = $_POST['target'] ?? '';
            if ($target === 'admin' || $target === currentUser()['username']) {
                throw new RuntimeException('Não é possível eliminar esta conta.');
            }
            deleteUser($target);
            $feedback = "Conta \"$target\" eliminada da base de dados.";
        }
    } catch (RuntimeException $e) {
        $feedback     = $e->getMessage();
        $feedbackType = 'error';
    } catch (PDOException $e) {
        $feedback     = 'Erro de base de dados: ' . $e->getMessage();
        $feedbackType = 'error';
    }
}

try {
    $users = loadUsers();
    $dbOk  = true;
} catch (Exception $e) {
    $users = [];
    $dbOk  = false;
    $dbError = $e->getMessage();
}

include 'includes/header.php';
?>

<style>
/* ══════════════════════════════════════════════════════════════════
   GESTÃO DE CONTAS — LAYOUT UNIFICADO E TABELA ALINHADA
   ══════════════════════════════════════════════════════════════════ */
/* ── Barra de Pesquisa ── */
.gc-search-wrap { position: relative; width: 100%; margin-bottom: 18px; }
.gc-search-wrap i.gc-search-icon { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--text3); font-size: 16px; pointer-events: none; }
.gc-search-input { width: 100%; height: 50px; padding: 0 18px 0 48px; background: var(--surface); border: 1px solid var(--border); border-radius: 14px; color: var(--text); font-size: 14px; font-family: var(--font-body); outline: none; transition: var(--trans); }
.gc-search-input::placeholder { color: var(--text3); }
.gc-search-input:focus { border-color: var(--accent); box-shadow: 0 0 0 4px var(--accent-soft); }
/* ── Toolbar (Chips + Botão) ── */
.gc-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-bottom: 20px; }
.gc-chips { display: flex; gap: 10px; flex-wrap: wrap; }
.gc-chip { display: inline-flex; align-items: center; gap: 7px; padding: 9px 18px; background: var(--surface); border: 1px solid var(--border); border-radius: 999px; font-size: 13px; font-weight: 600; color: var(--text2); cursor: pointer; transition: var(--trans); font-family: var(--font-body); user-select: none; }
.gc-chip i { font-size: 14px; }
.gc-chip:hover { border-color: var(--accent); color: var(--text); transform: translateY(-1px); }
.gc-chip.active { background: var(--grad); border-color: var(--accent); color: #fff; box-shadow: 0 6px 16px rgba(108, 99, 255, 0.35); }
.gc-chip .chip-count { background: rgba(255,255,255,0.2); padding: 2px 7px; border-radius: 999px; font-size: 11px; font-weight: 700; }
.gc-chip:not(.active) .chip-count { background: var(--surface2); color: var(--text3); }
/* Botão Criar Conta */
.btn-gc-open { display: inline-flex; align-items: center; gap: 9px; height: 44px; padding: 0 22px; border-radius: 999px; background: var(--grad); color: #fff; border: none; font-family: var(--font-body); font-size: 14px; font-weight: 600; cursor: pointer; box-shadow: 0 10px 25px -5px rgba(138, 92, 246, 0.45); transition: var(--trans); white-space: nowrap; }
.btn-gc-open:hover { transform: translateY(-2px) scale(1.02); box-shadow: 0 14px 30px -5px rgba(138, 92, 246, 0.55); filter: brightness(1.1); }
.btn-gc-open:active { transform: translateY(0) scale(0.98); }

/* ══════════════════════════════════════════════════════════════════
   LISTA DE UTILIZADORES — NOVO LAYOUT
   ══════════════════════════════════════════════════════════════════ */
.gc-users-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.gc-users-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 22px;
    border-bottom: 1px solid var(--border);
    background: var(--bg3);
}
.gc-users-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
}
.gc-users-title i { color: var(--accent); font-size: 16px; }
.gc-users-count {
    background: var(--accent-soft);
    color: var(--accent);
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    padding: 3px 11px;
    min-width: 28px;
    text-align: center;
}
.gc-users-list { display: flex; flex-direction: column; }
.gc-user-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 18px 22px;
    border-bottom: 1px solid var(--border);
    transition: background 0.15s;
}
.gc-user-item:last-child { border-bottom: none; }
.gc-user-item:hover { background: var(--accent-soft); }
.gc-user-avatar {
    width: 44px; height: 44px;
    border-radius: 50%;
    background: var(--accent-soft);
    color: var(--accent);
    display: grid; place-items: center;
    font-size: 14px; font-weight: 700;
    font-family: var(--font-display);
    flex-shrink: 0;
}
.gc-user-avatar.is-admin { background: var(--grad); color: #fff; }
.gc-user-main { flex: 1; min-width: 0; }
.gc-user-name-row { display: flex; align-items: center; gap: 10px; margin-bottom: 4px; flex-wrap: wrap; }
.gc-user-name { font-weight: 600; font-size: 14px; color: var(--text); }
.gc-badge-you {
    background: var(--accent-soft); color: var(--accent);
    border-radius: 999px; font-size: 10px; font-weight: 700;
    padding: 2px 8px; text-transform: uppercase; letter-spacing: 0.5px;
}
.gc-user-meta { display: flex; flex-wrap: wrap; gap: 14px; font-size: 12px; color: var(--text3); }
.gc-meta-item { display: flex; align-items: center; gap: 4px; }
.gc-meta-item i { font-size: 12px; opacity: 0.7; }
.gc-user-right { display: flex; align-items: center; gap: 16px; flex-shrink: 0; }
.gc-user-status { display: flex; align-items: center; gap: 7px; font-size: 12.5px; font-weight: 500; color: var(--text2); min-width: 70px; }
.gc-status-dot { width: 8px; height: 8px; border-radius: 50%; }
.gc-status-dot.active { background: var(--green); box-shadow: 0 0 0 3px var(--green-soft); }
.gc-status-dot.inactive { background: var(--text3); }
.gc-user-actions { display: flex; gap: 7px; flex-wrap: wrap; }
.gc-action-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 0 13px; height: 34px;
    background: var(--bg3); border: 1px solid var(--border);
    border-radius: 9px; font-size: 12.5px; font-weight: 500;
    color: var(--text2); cursor: pointer;
    transition: var(--trans); font-family: var(--font-body); white-space: nowrap;
}
.gc-action-btn:hover { background: var(--surface2); color: var(--text); transform: translateY(-1px); }
.gc-action-btn.toggle:hover { color: var(--amber); border-color: var(--amber); background: var(--amber-soft); }
.gc-action-btn.pass:hover { color: var(--accent); border-color: var(--accent); background: var(--accent-soft); }
.gc-action-btn.danger { color: var(--red); }
.gc-action-btn.danger:hover { background: var(--red-soft); border-color: var(--red); color: var(--red); }
.gc-action-form { margin: 0; display: inline; }
.gc-protected {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 12px; border: 1px dashed var(--border2);
    border-radius: 999px; font-size: 11.5px; color: var(--text3);
}
.gc-empty { text-align: center; padding: 60px 20px; color: var(--text3); }
.gc-empty i { font-size: 40px; opacity: 0.4; margin-bottom: 10px; display: block; }
.gc-feedback { display: flex; align-items: center; gap: 11px; padding: 14px 18px; border-radius: 12px; font-size: 13.5px; font-weight: 500; margin-bottom: 16px; }
.gc-feedback.success { background: var(--green-soft); color: var(--green); border: 1px solid rgba(34,197,139,0.25); }
.gc-feedback.error { background: var(--red-soft); color: var(--red); border: 1px solid rgba(240,80,110,0.25); }

/* ═══ MODAL CRIAR/EDITAR CONTA ═══ */
.gc-modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(6px);
    z-index: 2000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: gcFadeIn 0.2s ease;
}
.gc-modal-overlay.show { display: flex; }
@keyframes gcFadeIn { from { opacity: 0; } to { opacity: 1; } }
.gc-modal {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    width: 100%;
    max-width: 560px;
    box-shadow: var(--shadow-lg);
    animation: gcSlideUp 0.25s ease;
    max-height: 90vh;
    overflow-y: auto;
}
@keyframes gcSlideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.gc-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 22px 26px; border-bottom: 1px solid var(--border); }
.gc-modal-title { font-family: var(--font-display); font-size: 18px; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 10px; }
.gc-modal-title i { color: var(--accent); }
.gc-modal-close {
    width: 34px; height: 34px;
    border-radius: 50%; background: transparent;
    border: 1px solid var(--border);
    color: var(--text3); cursor: pointer;
    display: grid; place-items: center;
    font-size: 16px; transition: var(--trans);
}
.gc-modal-close:hover { background: var(--red-soft); color: var(--red); border-color: var(--red); }
.gc-modal-body { padding: 26px; }
.gc-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.gc-form-field { display: flex; flex-direction: column; }
.gc-form-field.full { grid-column: 1 / -1; }
.gc-form-field label { font-size: 11px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; color: var(--text3); margin-bottom: 7px; }
.gc-form-field label .req { color: var(--accent); }
.gc-form-field input, .gc-form-field select {
    width: 100%; height: 44px;
    padding: 0 14px;
    background: var(--input-bg);
    border: 1px solid var(--input-border);
    border-radius: 10px;
    color: var(--input-color);
    font-size: 13.5px;
    font-family: var(--font-body);
    outline: none;
    transition: var(--trans);
}
.gc-form-field input:focus, .gc-form-field select:focus { border-color: var(--accent); box-shadow: 0 0 0 4px var(--accent-soft); }
.gc-form-field select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%239aa0b4' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 36px;
    cursor: pointer;
}
.gc-pass-row { display: flex; gap: 8px; align-items: center; }
.gc-pass-row input { flex: 1; }
.gc-pass-copy {
    width: 44px; height: 44px;
    border-radius: 10px;
    background: var(--bg3);
    border: 1px solid var(--border);
    color: var(--text3);
    cursor: pointer;
    display: grid; place-items: center;
    font-size: 16px;
    transition: var(--trans);
    flex-shrink: 0;
}
.gc-pass-copy:hover { color: var(--accent); border-color: var(--accent); }
.gc-modal-footer { padding: 18px 26px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; }
.btn-gc-cancel { padding: 0 20px; height: 42px; border-radius: 10px; background: var(--bg3); border: 1px solid var(--border); color: var(--text2); font-weight: 600; font-size: 13.5px; cursor: pointer; transition: var(--trans); font-family: var(--font-body); }
.btn-gc-cancel:hover { background: var(--surface2); color: var(--text); }

/* ══ RESPONSIVO ═══ */
@media (max-width: 1024px) {
    .gc-user-meta { gap: 10px; font-size: 11px; }
}
@media (max-width: 768px) {
    .gc-form-grid { grid-template-columns: 1fr; }
    .gc-toolbar { flex-direction: column; align-items: stretch; }
    .gc-chips { overflow-x: auto; flex-wrap: nowrap; padding-bottom: 4px; }
    .gc-chip { flex-shrink: 0; }
    .btn-gc-open { width: 100%; justify-content: center; }
    .gc-user-item { flex-wrap: wrap; gap: 12px; padding: 14px 16px; }
    .gc-user-main { flex: 1 1 calc(100% - 60px); min-width: 0; }
    .gc-user-right { width: 100%; justify-content: space-between; padding-top: 10px; border-top: 1px solid var(--border); margin-top: 4px; }
    .gc-user-actions { flex: 1; justify-content: flex-end; }
    .gc-user-meta { gap: 8px; }
}
@media (max-width: 480px) {
    .gc-user-meta { flex-direction: column; gap: 4px; align-items: flex-start; }
    .gc-user-actions { flex-direction: column; align-items: stretch; }
    .gc-action-btn { justify-content: center; }
}
</style>

<!-- ═══ FEEDBACK ═══ -->
<?php if ($feedback): ?>
<div class="gc-feedback <?= $feedbackType ?>">
    <i class="bi bi-<?= $feedbackType === 'error' ? 'exclamation-triangle-fill' : 'check-circle-fill' ?>"></i>
    <?= htmlspecialchars($feedback) ?>
</div>
<?php endif; ?>

<!-- ═══ SEARCH ═══ -->
<div class="gc-search-wrap">
    <i class="bi bi-search gc-search-icon"></i>
    <input type="text" class="gc-search-input" id="gcSearch" placeholder="Pesquisar por agente, cliente, classificação...">
</div>

<!-- ═══ TOOLBAR ═══ -->
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
        <i class="bi bi-plus-lg"></i>
        Criar Conta
    </button>
</div>

<!-- ═ LISTA DE UTILIZADORES ═══ -->
<div class="gc-users-card">
    <div class="gc-users-header">
        <div class="gc-users-title">
            <i class="bi bi-people-fill"></i>
            <span>Utilizadores Registados</span>
        </div>
        <div class="gc-users-count"><?= count($users) ?></div>
    </div>

    <?php if (!$dbOk): ?>
        <div class="gc-empty">
            <i class="bi bi-database-x"></i>
            <h5>Erro de ligação à base de dados</h5>
            <p><?= htmlspecialchars($dbError ?? '') ?></p>
        </div>
    <?php elseif (empty($users)): ?>
        <div class="gc-empty">
            <i class="bi bi-people"></i>
            <h5>Sem utilizadores</h5>
            <p>Execute <code>db_setup.sql</code> para criar os utilizadores padrão.</p>
        </div>
    <?php else: ?>
    <div class="gc-users-list" id="gcUsersList">
        <?php foreach ($users as $uname => $u):
            $isMe    = ($uname === currentUser()['username']);
            $active  = (int)($u['active'] ?? 1) === 1;
            $role    = $u['role'] ?? 'gestor';
            $isProtected = ($uname === 'admin' || $isMe);
            $initials = strtoupper(mb_substr($u['name'] ?? $uname, 0, 2));
        ?>
        <div class="gc-user-item" data-role="<?= htmlspecialchars($role) ?>" data-name="<?= htmlspecialchars(strtolower(($u['name'] ?? '') . ' ' . $uname)) ?>">
            <!-- Avatar -->
            <div class="gc-user-avatar <?= $role === 'admin' ? 'is-admin' : '' ?>">
                <?= htmlspecialchars($initials) ?>
            </div>
            <!-- Info Principal -->
            <div class="gc-user-main">
                <div class="gc-user-name-row">
                    <span class="gc-user-name"><?= htmlspecialchars($u['name'] ?? $uname) ?></span>
                    <?php if ($isMe): ?>
                        <span class="gc-badge-you">VOCÊ</span>
                    <?php endif; ?>
                </div>
                <div class="gc-user-meta">
                    <span class="gc-meta-item">
                        <i class="bi bi-at"></i> <?= htmlspecialchars($uname) ?>
                    </span>
                    <span class="gc-meta-item">
                        <i class="bi bi-<?= $role === 'admin' ? 'shield-fill' : 'heart-fill' ?>"></i>
                        <?= $role === 'admin' ? 'Administrador' : 'Gestor' ?>
                    </span>
                    <span class="gc-meta-item">
                        <i class="bi bi-database"></i> qa_users · id <?= (int)($u['id'] ?? 0) ?>
                    </span>
                    <?php if (!empty($u['created_at'])): ?>
                    <span class="gc-meta-item">
                        <i class="bi bi-calendar3"></i> <?= date('d/m/Y H:i', strtotime($u['created_at'])) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Status + Ações -->
            <div class="gc-user-right">
                <div class="gc-user-status">
                    <span class="gc-status-dot <?= $active ? 'active' : 'inactive' ?>"></span>
                    <?= $active ? 'Ativo' : 'Inativo' ?>
                </div>
                <?php if (!$isProtected): ?>
                <div class="gc-user-actions">
                    <form method="POST" action="gestao_contas.php" class="gc-action-form">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="target" value="<?= htmlspecialchars($uname) ?>">
                        <button type="submit" class="gc-action-btn toggle" title="<?= $active ? 'Desativar' : 'Ativar' ?>">
                            <i class="bi bi-<?= $active ? 'pause-circle' : 'play-circle' ?>"></i>
                            <?= $active ? 'Desativar' : 'Ativar' ?>
                        </button>
                    </form>
                    <!-- ✅ EDITAR PERFIL (substitui Redefinir Pass) -->
                    <button class="gc-action-btn pass" onclick="openEditModal('<?= htmlspecialchars($uname) ?>','<?= htmlspecialchars($u['name'] ?? '') ?>','<?= htmlspecialchars($u['email'] ?? '') ?>')">
                        <i class="bi bi-pencil-square"></i> Editar Perfil
                    </button>
                    <button class="gc-action-btn danger" onclick="openDeleteModal('<?= htmlspecialchars($uname) ?>','<?= htmlspecialchars($u['name'] ?? $uname) ?>')">
                        <i class="bi bi-trash3-fill"></i>
                    </button>
                </div>
                <?php else: ?>
                <div class="gc-protected">
                    <i class="bi bi-lock-fill"></i> Protegida
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ═══ MODAL: CRIAR CONTA ═══ -->
<div class="gc-modal-overlay" id="createModal">
    <div class="gc-modal">
        <div class="gc-modal-header">
            <div class="gc-modal-title">
                <i class="bi bi-person-plus-fill"></i>
                Criar Nova Conta
            </div>
            <button class="gc-modal-close" onclick="closeCreateModal()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <form method="POST" action="gestao_contas.php" autocomplete="off" id="createForm">
            <div class="gc-modal-body">
                <input type="hidden" name="action" value="create">
                <div class="gc-form-grid">
                    <div class="gc-form-field">
                        <label>Utilizador <span class="req">*</span></label>
                        <input type="text" name="username" id="createUsername" placeholder="ex: joao.silva" pattern="[a-z0-9_]{3,30}" required autocomplete="off">
                    </div>
                    <div class="gc-form-field">
                        <label>Perfil <span class="req">*</span></label>
                        <select name="role" id="createRole" required>
                            <option value="gestor" selected>👤 Gestor</option>
                            <?php if (isAdmin()): ?>
                            <option value="admin">🛡️ Administrador</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="gc-form-field full">
                        <label>Nome Completo <span class="req">*</span></label>
                        <input type="text" name="name" id="createName" placeholder="ex: João Silva" required minlength="2">
                    </div>
                    <div class="gc-form-field full">
                        <label>E-mail <span class="req">*</span></label>
                        <input type="email" name="email" id="createEmail" placeholder="ex: joao.silva@empresa.com" autocomplete="off">
                    </div>
                    <div class="gc-form-field full">
                        <label>Palavra-passe</label>
                        <div class="gc-pass-row">
                            <input type="text" name="password" id="createPassword" placeholder="Gerada automaticamente" readonly style="letter-spacing:.05em" required minlength="6" autocomplete="off">
                            <button type="button" class="gc-pass-copy" title="Copiar" onclick="copyGeneratedPassword()">
                                <i class="bi bi-clipboard" id="copyPassIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="gc-modal-footer">
                <button type="button" class="btn-gc-cancel" onclick="closeCreateModal()">Cancelar</button>
                <button type="submit" class="btn-gc-create" <?= !$dbOk ? 'disabled' : '' ?> style="border-radius:10px">
                    <i class="bi bi-person-plus-fill"></i> Criar Conta
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ NOVO MODAL: EDITAR PERFIL ═══ -->
<div class="gc-modal-overlay" id="editModal">
    <div class="gc-modal">
        <div class="gc-modal-header">
            <div class="gc-modal-title">
                <i class="bi bi-pencil-square"></i>
                Editar Perfil
            </div>
            <button class="gc-modal-close" onclick="closeEditModal()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <form method="POST" action="gestao_contas.php" autocomplete="off" id="editForm">
            <div class="gc-modal-body">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="target" id="editTarget">
                <div class="gc-form-grid">
                    <div class="gc-form-field full">
                        <label>Nome Completo <span class="req">*</span></label>
                        <input type="text" name="edit_name" id="editName" placeholder="ex: João Silva" required minlength="2">
                    </div>
                    <div class="gc-form-field full">
                        <label>Nome de Utilizador <span class="req">*</span></label>
                        <input type="text" name="edit_username" id="editUsername" placeholder="ex: joao.silva" pattern="[a-z0-9_]{3,30}" required autocomplete="off">
                    </div>
                    <div class="gc-form-field full">
                        <label>E-mail</label>
                        <input type="email" name="edit_email" id="editEmail" placeholder="ex: joao.silva@empresa.com" autocomplete="off">
                    </div>
                    <div class="gc-form-field full">
                        <label>Nova Palavra-passe <span style="font-size:10px;color:var(--text3);font-weight:400;text-transform:none">(deixe vazio para manter)</span></label>
                        <div class="gc-pass-row">
                            <input type="password" name="edit_password" id="editPassword" placeholder="Mínimo 6 caracteres" minlength="6" autocomplete="new-password">
                            <button type="button" class="gc-pass-copy" title="Mostrar/Ocultar" onclick="toggleEditPassword()">
                                <i class="bi bi-eye" id="toggleEditPassIcon"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="gc-modal-footer">
                <button type="button" class="btn-gc-cancel" onclick="closeEditModal()">Cancelar</button>
                <button type="submit" class="btn-gc-create" style="border-radius:10px">
                    <i class="bi bi-check-lg"></i> Guardar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ═══ MODAL: ELIMINAR ═══ -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:360px">
        <div class="modal-content app-modal-content p-4 text-center">
            <div class="gc-delete-icon mb-3"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <h5 class="fw-bold mb-1">Eliminar Conta</h5>
            <p class="text-muted small mb-3" id="deleteUserLabel"></p>
            <form method="POST" action="gestao_contas.php">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="target" id="deleteUserTarget">
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn-gc-action" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-gc-action btn-gc-delete" style="padding:0 20px">
                        <i class="bi bi-trash3-fill"></i> Eliminar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ═══ MODAL CRIAR CONTA ═══
const createModal = document.getElementById('createModal');
const btnOpenCreate = document.getElementById('btnOpenCreate');
btnOpenCreate.addEventListener('click', () => {
    createModal.classList.add('show');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('createUsername').focus(), 100);
});
function closeCreateModal() {
    createModal.classList.remove('show');
    document.body.style.overflow = '';
}
createModal.addEventListener('click', (e) => { if (e.target === createModal) closeCreateModal(); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeCreateModal(); });

// ═══ NOVO: MODAL EDITAR PERFIL ═══
const editModal = document.getElementById('editModal');
function openEditModal(username, name, email) {
    document.getElementById('editTarget').value = username;
    document.getElementById('editName').value = name;
    document.getElementById('editUsername').value = username;
    document.getElementById('editEmail').value = email;
    document.getElementById('editPassword').value = '';
    editModal.classList.add('show');
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('editName').focus(), 100);
}
function closeEditModal() {
    editModal.classList.remove('show');
    document.body.style.overflow = '';
}
editModal.addEventListener('click', (e) => { if (e.target === editModal) closeEditModal(); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeEditModal(); });

function toggleEditPassword() {
    const passInput = document.getElementById('editPassword');
    const icon = document.getElementById('toggleEditPassIcon');
    if (passInput.type === 'password') {
        passInput.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        passInput.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

// ═══ FILTROS (chips) ═══
let currentFilter = 'todos';
document.querySelectorAll('.gc-chip').forEach(chip => {
    chip.addEventListener('click', () => {
        document.querySelectorAll('.gc-chip').forEach(c => c.classList.remove('active'));
        chip.classList.add('active');
        currentFilter = chip.dataset.filter;
        applyFilters();
    });
});

// ═══ PESQUISA ═══
const searchInput = document.getElementById('gcSearch');
searchInput.addEventListener('input', applyFilters);
function applyFilters() {
    const query = searchInput.value.toLowerCase().trim();
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
    document.getElementById('countTodos').textContent = visibleCount;
}

// ═══ MODAL ELIMINAR ═══
function openDeleteModal(username, name) {
    document.getElementById('deleteUserTarget').value = username;
    document.getElementById('deleteUserLabel').textContent = 'Tem a certeza que pretende eliminar a conta de "' + name + '" (' + username + ')? Esta acção é irreversível.';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteUserModal')).show();
}

// ══ GERAÇÃO AUTOMÁTICA DE PASSWORD ══
function generatePassword(fullName) {
    const name = fullName.trim();
    if (name.length < 1) return '';
    const letters = Array.from(name).filter(c => /\p{L}/u.test(c));
    if (letters.length < 1) return '';
    const l1 = letters[0] ? letters[0].toUpperCase() : '';
    const l2 = letters[1] ? letters[1].toLowerCase() : (l1.toLowerCase() || '');
    const l3 = letters[2] ? letters[2].toLowerCase() : (l2 || '');
    const specials = ['@', '#', '!', '$', '%', '&', '*'];
    const special  = specials[Math.floor(Math.random() * specials.length)];
    const digits = String(Math.floor(Math.random() * 900) + 100);
    return l1 + l2 + l3 + special + digits;
}
const nameInput = document.getElementById('createName');
const passInput = document.getElementById('createPassword');
if (nameInput && passInput) {
    nameInput.addEventListener('blur', function () {
        const val = this.value.trim();
        if (val.length >= 2) {
            passInput.value = generatePassword(val);
        }
    });
    nameInput.addEventListener('input', function () {
        if (this.value.trim().length < 2) passInput.value = '';
    });
}
function copyGeneratedPassword() {
    const pwd = passInput ? passInput.value : '';
    if (!pwd) return;
    navigator.clipboard.writeText(pwd).then(() => {
        const icon = document.getElementById('copyPassIcon');
        if (icon) {
            icon.className = 'bi bi-clipboard-check';
            setTimeout(() => { icon.className = 'bi bi-clipboard'; }, 2000);
        }
    }).catch(() => { passInput.select(); document.execCommand('copy'); });
}
</script>

<?php include 'includes/footer.php'; ?>
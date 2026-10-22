<?php
// QA Analyser — Config (usa API 8001, não 8000)
define('API_BASE', 'http://localhost:8001');
define('DB_USERS', 'qa_users');
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', '');

// ══════════════════════════════════════════════════════════════════
//  TIMEOUT DE SESSÃO — 60 MINUTOS
// ══════════════════════════════════════════════════════════════════
define('SESSION_TIMEOUT', 3600); // 60 minutos em segundos

// ── Conexão PDO à BD de utilizadores ───────────────────────────
function dbUsers(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST, DB_PORT, DB_USERS
    );
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        throw new RuntimeException("Erro de conexão com a BD: " . $e->getMessage());
    }
    return $pdo;
}

// ── CRUD de utilizadores ────────────────────────────────────────
function loadUsers(): array {
    try {
        $rows = dbUsers()->query("SELECT * FROM users ORDER BY FIELD(role,'admin','gestor'), name")->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['username']] = $r;
        }
        return $out;
    } catch (Exception $e) {
        return [];
    }
}
function getUserByUsername(string $username): ?array {
    try {
        $st = dbUsers()->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $st->execute([$username]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Exception $e) {
        return null;
    }
}
function createUser(string $username, string $password, string $name, string $initials, string $role = 'gestor', string $email = ''): void {
    $st = dbUsers()->prepare(
        "INSERT INTO users (username, password, name, initials, role, email, active) VALUES (?, ?, ?, ?, ?, ?, 1)"
    );
    $st->execute([$username, $password, $name, $initials, $role, $email]);
}
function toggleUserActive(string $username): bool {
    $st = dbUsers()->prepare("UPDATE users SET active = IF(active=1, 0, 1) WHERE username = ?");
    $st->execute([$username]);
    return (bool) getUserByUsername($username)['active'];
}
function resetUserPassword(string $username, string $newPassword): void {
    $st = dbUsers()->prepare("UPDATE users SET password = ? WHERE username = ?");
    $st->execute([$newPassword, $username]);
}
function deleteUser(string $username): void {
    $st = dbUsers()->prepare("DELETE FROM users WHERE username = ?");
    $st->execute([$username]);
}

// ── Helpers ─────────────────────────────────────────────────────
function makeInitials(string $name): string {
    $parts = array_filter(explode(' ', trim($name)));
    if (count($parts) >= 2) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    }
    return strtoupper(mb_substr($name, 0, 2));
}

// ─ Sessão ──────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica se a sessão expirou por inatividade (60 min).
 * Se expirou, destrói a sessão e redireciona para login.
 */
function checkSessionTimeout(): void {
    if (isset($_SESSION['user']) && isset($_SESSION['last_activity'])) {
        $elapsed = time() - $_SESSION['last_activity'];
        if ($elapsed > SESSION_TIMEOUT) {
            // Sessão expirada — limpar e redirecionar
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
            header('Location: index.php?timeout=1');
            exit;
        }
    }
    // Atualizar timestamp de atividade
    if (isset($_SESSION['user'])) {
        $_SESSION['last_activity'] = time();
    }
}

function isLoggedIn(): bool {
    checkSessionTimeout();
    return isset($_SESSION['user']);
}

function currentUser(): array {
    checkSessionTimeout();
    return $_SESSION['user'] ?? [];
}

function isAdmin(): bool {
    checkSessionTimeout();
    return (currentUser()['role'] ?? '') === 'admin';
}

function requireLogin(): void {
    checkSessionTimeout();
    if (!isLoggedIn()) {
        header('Location: index.php?timeout=1');
        exit;
    }
}

function requireAdmin(): void {
    checkSessionTimeout();
    requireLogin();
    if (!isAdmin()) {
        header('Location: Dashboard.php');
        exit;
    }
}
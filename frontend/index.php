<?php
require_once 'includes/config.php';

// Redirecionar se já estiver logado
if (isLoggedIn()) {
    header('Location: Dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    try {
        $u = getUserByUsername($username);
        // Verifica password (suporte para hash e texto simples legacy)
        $passMatch = false;
        if ($u) {
            if (password_verify($password, $u['password'])) $passMatch = true;
            elseif ($u['password'] === $password) $passMatch = true;
        }

        if ($u && (int)$u['active'] === 1 && $passMatch) {
            $_SESSION['user'] = [
                'username' => $u['username'],
                'name'     => $u['name'],
                'initials' => $u['initials'],
                'role'     => $u['role'],
            ];
            header('Location: Dashboard.php');
            exit;
        } else {
            $error = 'Credenciais inválidas ou conta desativada.';
        }
    } catch (Exception $e) {
        $error = 'Erro de ligação à base de dados.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QA Análise — Login</title>

    <!-- 🛡️ SCRIPT PRECOCE: Aplica o tema ANTES do CSS carregar (Evita Flash) -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('qa-theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>

    <!-- CSS Externo (Bootstrap + Icons + Fonts + App) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/syne/600.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/syne/700.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/outfit/400.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/outfit/600.css">
    <link rel="stylesheet" href="assets/css/app.css">

    
</head>
<body class="login-body">

    <!-- Controlos Flutuantes (Tema + Idioma) -->
    <div class="top-controls">
        <button class="control-btn" id="btnLang" title="Português / English">
            <i class="bi bi-globe2"></i>
        </button>
        <button class="control-btn" id="btnTheme" title="Alternar Tema">
            <i class="bi bi-moon-fill" id="themeIcon"></i>
        </button>
    </div>

    <div class="login-card-custom">
        <div class="text-center mb-4">
        
            <?php
$platformFile = __DIR__ . '/backend/storage/platform.json';
$platformDefaults = ['logo' => 'assets/logo2.png'];
$platform = file_exists($platformFile) 
    ? array_merge($platformDefaults, json_decode(file_get_contents($platformFile), true) ?: [])
    : $platformDefaults;
$logoPath = htmlspecialchars($platform['logo']);
?>

<!-- No formulário de login -->
<div class="login-logo-wrap">
    <img src="<?= $logoPath ?>?v=<?= time() ?>" alt="Logo" class="login-logo-img">
</div>
        </div>
        
        <h2 class="text-center fw-bold mb-1" style="font-family: var(--font-display); color: var(--text);" data-i18n="Bem-vindo de volta">Bem-vindo de volta</h2>
        <p class="text-center text-muted small mb-4" data-i18n="Plataforma de análise de qualidade de chamadas">Plataforma de análise de qualidade de chamadas</p>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:13px; border-radius:8px;">
            <i class="bi bi-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="index.php" novalidate>
            
            <!-- Utilizador -->
            <div>
                <label class="form-label-custom" data-i18n="Utilizador">Utilizador</label>
                <div class="input-group-custom">
                    <i class="bi bi-person input-icon"></i>
                    <input type="text" name="username" class="form-control-custom"
                           placeholder="admin ou gestor" data-i18n-placeholder="admin ou gestor"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                           required autocomplete="username">
                </div>
            </div>

            <!-- Palavra-passe -->
            <div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label-custom mb-0" data-i18n="Palavra-passe">Palavra-passe</label>
                    <a href="recuperar.php" class="forgot-link" data-i18n="Esqueceu a palavra-passe?">Esqueceu a palavra-passe?</a>
                </div>
                <div class="input-group-custom">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" name="password" id="passwordInput" class="form-control-custom"
                           placeholder="••••••••" 
                           required autocomplete="current-password">
                    <i class="bi bi-eye toggle-password" id="togglePassword"></i>
                </div>
            </div>

            <button type="submit" class="btn-login-custom mt-2">
                <i class="bi bi-box-arrow-in-right me-2"></i> <span data-i18n="Entrar">Entrar</span>
            </button>
        </form>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- 🧠 LÓGICA INTERNA (Tema + Tradução + Password) -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            initTheme();
            initLang();
            initPasswordToggle();
        });

        /* ── 1. TEMA (Dark/Light) ── */
        function initTheme() {
            const saved = localStorage.getItem('qa-theme') || 'dark';
            applyTheme(saved);

            document.getElementById('btnTheme').addEventListener('click', () => {
                const current = document.documentElement.getAttribute('data-theme');
                const next = current === 'dark' ? 'light' : 'dark';
                localStorage.setItem('qa-theme', next);
                applyTheme(next);
            });
        }

        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            const icon = document.getElementById('themeIcon');
            if (icon) {
                icon.className = theme === 'dark' ? 'bi bi-moon-fill' : 'bi bi-sun-fill';
            }
        }

        /* ── 2. TRADUÇÃO (PT/EN) ── */
        const DICT = {
            "Bem-vindo de volta": "Welcome back",
            "Plataforma de análise de qualidade de chamadas": "Call quality analysis platform",
            "Utilizador": "Username",
            "Palavra-passe": "Password",
            "Entrar": "Sign in",
            "Esqueceu a palavra-passe?": "Forgot password?",
            "admin ou gestor": "admin or manager",
            "Credenciais inválidas ou conta desativada.": "Invalid credentials or disabled account."
        };

        function initLang() {
            const saved = localStorage.getItem('qa-lang') || 'pt';
            
            // Aplicar tradução se for EN
            if (saved === 'en') translatePage(DICT);

            document.getElementById('btnLang').addEventListener('click', () => {
                const current = localStorage.getItem('qa-lang') || 'pt';
                const next = current === 'pt' ? 'en' : 'pt';
                localStorage.setItem('qa-lang', next);
                location.reload(); // Recarrega para aplicar limpo
            });
        }

        function translatePage(dict) {
            // Traduzir textos com data-i18n
            document.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                if (dict[key]) el.textContent = dict[key];
            });

            // Traduzir placeholders com data-i18n-placeholder
            document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
                const key = el.getAttribute('data-i18n-placeholder');
                if (dict[key]) el.setAttribute('placeholder', dict[key]);
            });
        }

        /* ── 3. MOSTRAR/ESCONDER PASSWORD ── */
        function initPasswordToggle() {
            const passInput = document.getElementById('passwordInput');
            const toggleBtn = document.getElementById('togglePassword');

            if (toggleBtn && passInput) {
                toggleBtn.addEventListener('click', () => {
                    const type = passInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passInput.setAttribute('type', type);
                    
                    // Alternar ícone
                    toggleBtn.classList.toggle('bi-eye');
                    toggleBtn.classList.toggle('bi-eye-slash');
                });
            }
        }
    </script>
</body>
</html>
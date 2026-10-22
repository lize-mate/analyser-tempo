<?php
require_once 'includes/config.php';

// Se já estiver logado, vai para o dashboard
if (isLoggedIn()) {
    header('Location: Dashboard.php');
    exit;
}

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor, insira um endereço de email válido.';
    } else {
        // --- LÓGICA DE RECUPERAÇÃO ---
        // 1. Verificar se o email existe na BD (opcional, por segurança pode-se não revelar)
        // 2. Gerar token único
        // 3. Enviar email com link
        
        // Simulação de sucesso para demonstração visual
        // Num cenário real, aqui chamarias: sendPasswordResetEmail($email);
        
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QA Análise — Recuperar Palavra-passe</title>
    
    <!-- Script de Tema Precoce (Evita flash de cor) -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('qa-theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>

    <!-- CSS Externo -->
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
        <button class="control-btn" onclick="toggleLang()" title="Português / English">
            <i class="bi bi-globe2"></i>
        </button>
        <button class="control-btn" onclick="toggleTheme()" title="Alternar Tema">
            <i class="bi bi-moon-fill" id="themeIcon"></i>
        </button>
    </div>

    <div class="login-card-custom">
        <!-- Header com Logo e Ícones Sociais -->
        <div class="d-flex justify-content-between align-items-start mb-4">
            <img src="assets/logo2.png" alt="Howard Johnson" class="login-logo-img" style="margin-bottom:0; max-height:50px;">
            
            
        </div>
        
        <hr style="border-color: #e5e5ef; margin-bottom: 25px;">

        <?php if ($success): ?>
            <!-- Estado de Sucesso -->
            <div class="text-center py-4">
                <div style="width:60px; height:60px; background:rgba(34,197,139,0.1); color:#22c58b; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:28px; margin:0 auto 20px;">
                    <i class="bi bi-check-lg"></i>
                </div>
                <h2 class="login-title" style="font-size:20px;">Verifique o seu Email</h2>
                <p class="login-subtitle" style="margin-bottom:20px;">
                    Enviámos um link de redefinição para <br>
                    <strong style="color:var(--accent)"><?= htmlspecialchars($email) ?></strong>
                </p>
                <a href="index.php" class="btn-login-custom" style="background:transparent; border:1px solid #e5e5ef; color:#5c6278; box-shadow:none;">
                    Voltar ao Login
                </a>
            </div>



        <?php else: ?>
            <!-- Formulário de Recuperação -->
            <h2 class="login-title text-start" style="display:flex; align-items:center; gap:10px;">
                Recuperar Palavra-passe
            </h2>
            <p class="login-subtitle text-start">Introduza o seu email e enviaremos um link de redefinição.</p>

            <?php if ($error): ?>
            <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:13px; border-radius:8px;">
                <i class="bi bi-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="recuperar.php" novalidate>
                
                <!-- Email -->
                <div>
                    <label class="form-label-custom">EMAIL</label>
                    <div class="input-group-custom">
                        <i class="bi bi-envelope input-icon"></i>
                        <input type="email" name="email" class="form-control-custom"
                               placeholder="nome@empresa.co.mz"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                               required autocomplete="email" autofocus>
                    </div>
                </div>

                <button type="submit" class="btn-login-custom mt-2">
                    <i class="bi bi-send-fill me-2"></i> Enviar Link de Redefinição
                </button>
            </form>
            
            <div class="text-center mt-4">
                <span style="color:#6b7280; font-size:13px;">Lembrou-se da palavra-passe?</span>
                <a href="index.php" style="color:var(--accent); font-weight:600; font-size:13px; text-decoration:none;">Entrar</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    
    <script>
        // Garantir que o ícone do tema está correto ao carregar
        document.addEventListener('DOMContentLoaded', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const icon = document.getElementById('themeIcon');
            if (icon) {
                icon.className = currentTheme === 'dark' ? 'bi bi-moon-fill' : 'bi bi-sun-fill';
            }
        });
    </script>
</body>
</html>
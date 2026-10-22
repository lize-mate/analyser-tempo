<?php $user = currentUser(); ?>
<!DOCTYPE html>
<html lang="pt" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QA Análise — <?= htmlspecialchars($pageTitle ?? 'Painel') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/syne/600.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/syne/700.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/outfit/300.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/outfit/400.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/outfit/500.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/outfit/600.css">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body data-username="<?= htmlspecialchars($user['username'] ?? '') ?>" 
      data-role="<?= htmlspecialchars($user['role'] ?? '') ?>"
      data-page="<?= strtolower($activeSection ?? '') ?>">

<div class="d-flex" id="app-wrapper">

  <!-- ── SIDEBAR ── -->
  <nav class="sidebar" id="sidebar">
    <div class="sidebar-head">
     
      <?php
// Ler logotipo do platform.json
$platformFile = __DIR__ . '/../backend/storage/platform.json';
$platformDefaults = ['logo' => 'assets/logo2.png'];
$platform = file_exists($platformFile) 
    ? array_merge($platformDefaults, json_decode(file_get_contents($platformFile), true) ?: [])
    : $platformDefaults;
$logoPath = htmlspecialchars($platform['logo']);
?>

<!-- Na sidebar -->
<div class="sidebar-head">
    <img src="<?= $logoPath ?>?v=<?= filemtime(__DIR__ . '/../frontend/' . $platform['logo']) ?>" 
         alt="Logo" 
         class="sidebar-logo-img">
</div>
    </div>
    
    <div class="sidebar-nav">
      <!-- PRINCIPAL -->
      <div class="nav-section">
        <div class="nav-section-label">Principal</div>
        <a href="Dashboard.php" class="nav-item <?= ($activeSection ?? '') === 'Dashboard' ? 'active' : '' ?>">
          <i class="bi bi-grid-1x2-fill nav-icon"></i> Dashboard
        </a>
        <a href="relatorios.php" class="nav-item <?= ($activeSection ?? '') === 'relatorios' ? 'active' : '' ?>">
          <i class="bi bi-file-earmark-text nav-icon"></i> Relatórios disponíveis(PDFs)
        </a>
      </div>

      <!-- ADMINISTRAÇÃO -->
      
      <div class="nav-section">
        <div class="nav-section-label">Administração</div>
        <?php if (isAdmin()): ?>
          <a href="configuracoes.php" class="nav-item <?= ($activeSection ?? '') === 'configuracoes' ? 'active' : '' ?>">
          <i class="bi bi-gear nav-icon"></i> Configurações
          </a>
          <a href="gestao_contas.php" class="nav-item <?= ($activeSection ?? '') === 'gestao_contas' ? 'active' : '' ?>">
            <i class="bi bi-people nav-icon"></i> Gestão de Contas
          </a>
        <?php endif; ?>
       <!--a href="agentes.php" class="nav-item <?= ($activeSection ?? '') === 'agentes' ? 'active' : '' ?>">
        <i class="bi bi-people-fill nav-icon"></i>Perfil dos Agentes
       </a-->
      </div>
      

      <!-- SUPORTE -->
      <div class="nav-section">
        <div class="nav-section-label">Suporte</div>
        <a href="guia.php" class="nav-item <?= ($activeSection ?? '') === 'guia' ? 'active' : '' ?>">
          <i class="bi bi-book"></i> Guia de usuário
        </a>
      </div>
      
  </nav>

  <!-- ── Logout Modal (Global) ── -->
  <div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 360px">
      <div class="modal-content app-modal-content p-4 text-center">
        <div class="logout-modal-icon"><i class="bi bi-box-arrow-left"></i></div>
        <h5 class="fw-bold mb-1">Sair da conta?</h5>
        <p class="text-muted small mb-4">Tem a certeza que deseja terminar a sessão?</p>
        <div class="d-flex gap-2 justify-content-center">
          <button type="button" class="btn-logout-cancel" data-bs-dismiss="modal">
            <i class="bi bi-x-lg"></i> Cancelar
          </button>
          <a href="logout.php" class="btn-logout-confirm">
            <i class="bi bi-box-arrow-left"></i> Sim, sair
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- ── MAIN CONTENT WRAPPER ── -->
  <div class="content-wrapper d-flex flex-column flex-grow-1" style="min-width: 0">
    
    <!-- TOPBAR -->
<header class="topbar">
    <div class="topbar-left">
        <button id="sidebar-toggle" onclick="document.body.classList.toggle('sidebar-open')">
            <i class="bi bi-list"></i>
        </button>
        <h1 class="topbar-title"><?= htmlspecialchars($pageTitle ?? '') ?></h1>
    </div>
    
    <!-- ✅ NOVO: TÍTULO CENTRAL "QA-analyser" -->
    <div class="topbar-center">
        <span class="topbar-brand">QA-analyser</span>
    </div>
    
    <div class="topbar-right">
        <button type="button" class="lang-pill" id="langPill" onclick="toggleLang()" title="Português / English">
            <i class="bi bi-globe2"></i> <span id="langLabel">PT</span>
        </button>
        <button class="btn-theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Alternar tema">
            <i class="bi bi-moon-fill" id="themeIcon"></i>
        </button>
        <div class="user-wrap">
            <button type="button" class="user-chip" id="userChipBtn" onclick="toggleUserMenu(event)">
                <div class="topbar-avatar"><?= htmlspecialchars($user['initials'] ?? '') ?></div>
                <span class="topbar-name d-none d-sm-block"><?= htmlspecialchars($user['name'] ?? '') ?></span>
                <i class="bi bi-chevron-down user-chip-arrow"></i>
            </button>
            <div class="user-menu" id="userMenu">
                <div class="user-menu-head">
                    <div class="topbar-avatar"><?= htmlspecialchars($user['initials'] ?? '') ?></div>
                    <div>
                        <div class="user-menu-name"><?= htmlspecialchars($user['name'] ?? '') ?></div>
                        <div class="user-menu-role"><?= htmlspecialchars($user['role'] ?? 'gestor') ?></div>
                    </div>
                </div>
                <div class="user-menu-sep"></div>
                <a href="perfil.php" class="user-menu-item">
                    <i class="bi bi-person-gear"></i> Ver perfil
                </a>
                <button type="button" class="user-menu-item user-menu-logout" onclick="confirmLogout()">
                    <i class="bi bi-box-arrow-left"></i> Terminar sessão
                </button>
            </div>
        </div>
    </div>
      </header>

    <!-- INÍCIO DO CONTEÚDO DA PÁGINA -->
    <main class="main-body flex-grow-1">
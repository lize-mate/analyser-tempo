<?php
require_once 'includes/config.php';
require_once 'includes/api.php';
requireLogin();

$pageTitle     = 'Meu Perfil';
$activeSection = 'Perfil';

$user      = currentUser();
$name      = $user['name']     ?? '';
$username  = $user['username'] ?? '';
$initials  = $user['initials'] ?? strtoupper(substr($username, 0, 2));
$role      = $user['role']     ?? 'gestor';
$roleLabel = ($role === 'admin') ? 'Administrador' : 'Gestor';
$email     = $user['email'] ?? ($username . '@howardjohnson.co.mz');

// ── Valores por defeito ──
$function         = ($role === 'admin') ? 'Administração de Sistemas' : 'Gestão de Qualidade';
$department       = 'Qualidade / Contact Center';
$registrationDate = '—';
$lastLogin        = '—';
$passwordChange   = '—';
$status           = 'Ativo';

// ── Ler colunas opcionais da BD (se existirem) ──
try {
    $st = dbUsers()->prepare("SELECT * FROM users WHERE username = ?");
    $st->execute([$username]);
    $row = $st->fetch();
    if ($row) {
        if (!empty($row['function']))            $function         = $row['function'];
        if (!empty($row['department']))          $department       = $row['department'];
        if (!empty($row['created_at']))          $registrationDate = date('d M Y', strtotime($row['created_at']));
        if (!empty($row['last_login']))          $lastLogin        = date('d M Y · H:i', strtotime($row['last_login']));
        if (!empty($row['password_changed_at'])) $passwordChange   = date('d M Y', strtotime($row['password_changed_at']));
        if (isset($row['active']))               $status           = ((int)$row['active'] === 1) ? 'Ativo' : 'Inativo';
    }
} catch (Exception $e) { /* colunas opcionais — mantém defaults */ }

// ── Escapar no fim ──
$nameE      = htmlspecialchars($name !== '' ? $name : $username, ENT_QUOTES, 'UTF-8');
$usernameE  = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
$initialsE  = htmlspecialchars($initials, ENT_QUOTES, 'UTF-8');
$roleE      = htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8');
$emailE     = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$functionE  = htmlspecialchars($function, ENT_QUOTES, 'UTF-8');
$departE    = htmlspecialchars($department, ENT_QUOTES, 'UTF-8');
$regDateE   = htmlspecialchars($registrationDate, ENT_QUOTES, 'UTF-8');
$lastLogE   = htmlspecialchars($lastLogin, ENT_QUOTES, 'UTF-8');
$passChgE   = htmlspecialchars($passwordChange, ENT_QUOTES, 'UTF-8');
$statusE    = htmlspecialchars($status, ENT_QUOTES, 'UTF-8');
$usernameJs = json_encode($username);
$initialsJs = json_encode($initials);

include 'includes/header.php';
?>

<style>
/* ── Extras de perfil (tema-aware) ── */
.profile-col{display:flex;flex-direction:column;gap:22px;min-width:0}

/* ✅ FIX: wrapper com tamanho FIXO — não colapsa quando a foto entra */
.avatar-wrapper{position:relative;width:110px;height:110px;margin:0 auto 16px}
.avatar{width:100%;height:100%;border-radius:50%;background:var(--grad);color:#fff;display:grid;place-items:center;font-size:38px;font-weight:700;font-family:var(--font-display);box-shadow:0 12px 28px rgba(108,99,255,.4)}
.avatar-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:50%;box-shadow:0 12px 28px rgba(108,99,255,.4);display:none;z-index:1}
.avatar-wrapper .change-photo{z-index:2}

.btn-remove-photo{display:inline-flex;align-items:center;gap:7px;background:transparent;border:1px solid var(--border);border-radius:var(--r);padding:7px 14px;color:var(--text3);font-size:12.5px;cursor:pointer;transition:var(--trans);font-family:var(--font-body)}
.btn-remove-photo:hover{color:var(--red);border-color:var(--red);background:var(--red-soft)}
.photo-hint{color:var(--text3);font-size:11.5px}
</style>

<div class="profile-grid">

    <!-- ══════════ CARTÃO ESQUERDO: IDENTIDADE + FOTO ══════════ -->
    <section class="profile-card">
        <div class="avatar-wrapper">
            <div class="avatar" id="avatarBox"><?= $initialsE ?></div>
            <img id="avatarImg" class="avatar-img" src="" alt="Foto de perfil">
            <button type="button" class="change-photo" title="Trocar foto de perfil"
                    onclick="document.getElementById('photoInput').click()">
                <i class="bi bi-camera-fill"></i>
            </button>
            <input type="file" id="photoInput" accept="image/png,image/jpeg" hidden>
        </div>

        <h2><?= $nameE ?></h2>
        <span class="role"><?= $roleE ?></span>
        <div class="email"><i class="bi bi-envelope"></i> <?= $emailE ?></div>

        <div style="margin-top:14px;display:flex;flex-direction:column;align-items:center;gap:8px">
            <button type="button" class="btn-remove-photo" id="removePhotoBtn" style="display:none">
                <i class="bi bi-trash3"></i> Remover foto
            </button>
            <small class="photo-hint" id="photoHint">PNG ou JPG · máx. 5MB</small>
        </div>

        <hr>

        <div class="info-list">
            <div class="info-item">
                <i class="bi bi-briefcase"></i>
                <div><strong>Função</strong><span><?= $functionE ?></span></div>
            </div>
            <div class="info-item">
                <i class="bi bi-diagram-3"></i>
                <div><strong>Departamento</strong><span><?= $departE ?></span></div>
            </div>
            <div class="info-item">
                <i class="bi bi-check2-circle"></i>
                <div><strong>Estado</strong><span class="status <?= $status === 'Ativo' ? 'active' : '' ?>"><?= $statusE ?></span></div>
            </div>
        </div>
    </section>

    <!-- ══════════ COLUNA DIREITA: DETALHES ══════════ -->
    <div class="profile-col">

        <section class="profile-form-card">
            <h2><i class="bi bi-person"></i>Informações Pessoais</h2>
            <div class="info-list">
                <div class="info-item">
                    <i class="bi bi-person-badge"></i>
                    <div><strong>Nome Completo</strong><span><?= $nameE ?></span></div>
                </div>
                <div class="info-item">
                    <i class="bi bi-envelope"></i>
                    <div><strong>Email</strong><span><?= $emailE ?></span></div>
                </div>
                <div class="info-item">
                    <i class="bi bi-at"></i>
                    <div><strong>Nome de Utilizador</strong><span><?= $usernameE ?></span></div>
                </div>
            </div>
        </section>

        <section class="profile-form-card">
            <h2><i class="bi bi-shield-lock"></i>Informações da Conta</h2>
            <div class="info-list">
                <div class="info-item">
                    <i class="bi bi-calendar3"></i>
                    <div><strong>Data de Cadastro</strong><span><?= $regDateE ?></span></div>
                </div>
                <div class="info-item">
                    <i class="bi bi-box-arrow-in-right"></i>
                    <div><strong>Último Início de Sessão</strong><span><?= $lastLogE ?></span></div>
                </div>
                <div class="info-item">
                    <i class="bi bi-key"></i>
                    <div><strong>Alteração de Senha</strong><span><?= $passChgE ?></span></div>
                </div>
                <div class="info-item">
                    <i class="bi bi-toggle2-on"></i>
                    <div><strong>Status</strong><span class="status <?= $status === 'Ativo' ? 'active' : '' ?>"><?= $statusE ?></span></div>
                </div>
            </div>
        </section>

    </div>
</div>

<script>
(function () {
    const USERNAME = <?= $usernameJs ?>;
    const INITIALS = <?= $initialsJs ?>;
    const KEY   = 'qa-photo-' + USERNAME;
    const box   = document.getElementById('avatarBox');
    const img   = document.getElementById('avatarImg');
    const input = document.getElementById('photoInput');
    const rmBtn = document.getElementById('removePhotoBtn');
    const hint  = document.getElementById('photoHint');

    /* ── Aplica/remove a foto SEM esconder o círculo base ── */
    function paint(src) {
        if (src) {
            img.src = src;
            img.style.display = 'block';   // foto por cima das iniciais
            rmBtn.style.display = 'inline-flex';
            if (hint) hint.textContent = 'Foto actualizada ✓';
        } else {
            img.removeAttribute('src');
            img.style.display = 'none';    // volta a mostrar as iniciais
            rmBtn.style.display = 'none';
            if (hint) hint.textContent = 'PNG ou JPG · máx. 5MB';
        }
        // ✅ NOTA: o #avatarBox NUNCA é escondido — ele mantém o tamanho do wrapper

        // Espelha na topbar (desta página)
        document.querySelectorAll('.topbar-avatar').forEach(el => {
            if (src) {
                el.innerHTML = '<img src="' + src + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block">';
            } else {
                el.textContent = INITIALS;
            }
        });
    }

    // Carregar foto guardada
    paint(localStorage.getItem(KEY));

    /* ── Escolher nova foto ── */
    input.addEventListener('change', function () {
        const f = this.files[0];
        this.value = '';
        if (!f) return;

        if (!/image\/(png|jpe?g)/.test(f.type)) {
            alert('Formato não suportado. Use PNG ou JPG.');
            return;
        }
        if (f.size > 5 * 1024 * 1024) {
            alert('A imagem deve ter no máximo 5MB.');
            return;
        }

        const reader = new FileReader();
        reader.onload = e => {
            const im = new Image();
            im.onload = () => {
                // Recorte quadrado centrado + redução para 256px
                const side = 256;
                const min  = Math.min(im.width, im.height);
                const c = document.createElement('canvas');
                c.width = side; c.height = side;
                c.getContext('2d').drawImage(
                    im,
                    (im.width - min) / 2, (im.height - min) / 2, min, min,
                    0, 0, side, side
                );
                const data = c.toDataURL('image/jpeg', 0.85);
                try {
                    localStorage.setItem(KEY, data);
                } catch (err) {
                    alert('Não foi possível guardar a foto neste navegador.');
                    return;
                }
                paint(data);
            };
            im.src = e.target.result;
        };
        reader.readAsDataURL(f);
    });

    /* ── Remover foto ── */
    rmBtn.addEventListener('click', () => {
        localStorage.removeItem(KEY);
        paint(null);
    });
})();
</script>

<?php include 'includes/footer.php'; ?>
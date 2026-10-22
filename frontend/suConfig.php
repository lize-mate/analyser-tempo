<?php
require_once 'includes/config.php';
require_once 'includes/api.php';
requireLogin();
requireAdmin();

$pageTitle     = 'Definições';
$activeSection = 'configuracoes';

// --- Lógica de Processamento (Guardar no .env) ---
$feedback = '';
$feedbackType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $envFile = __DIR__ . '/../backend/.env';
        $envContent = file_exists($envFile) ? file_get_contents($envFile) : '';
        
        $updateEnv = function($key, $value) use (&$envContent) {
            $value = trim($value);
            $value = str_replace('"', '\"', $value);
            if (preg_match("/^{$key}=.*/m", $envContent)) {
                $envContent = preg_replace("/^{$key}=.*/m", "{$key}=\"{$value}\"", $envContent);
            } else {
                $envContent .= "\n{$key}=\"{$value}\"";
            }
        };

        // Mapeamento dos campos do formulário para variáveis .env
        $fields = [
            'app_name' => 'APP_NAME', 'timezone' => 'APP_TIMEZONE', 
            'records_per_page' => 'RECORDS_PER_PAGE', 'remote_host' => 'AUDIOS_HOST',
            'remote_port' => 'AUDIOS_PORT', 'remote_user' => 'AUDIOS_USER',
            'remote_pass' => 'AUDIOS_PASS', 'audio_dir_remote' => 'AUDIO_DIR',
            'text_dir' => 'TEXT_DIR', 'sync_interval' => 'SYNC_INTERVAL',
            'groq_api_key' => 'GROQ_API_KEY', 'groq_model' => 'GROQ_MODEL',
            'whisper_model' => 'WHISPER_MODEL', 'api_endpoint' => 'GROQ_URL'
        ];

        foreach ($fields as $formField => $envKey) {
            if (isset($_POST[$formField])) $updateEnv($envKey, $_POST[$formField]);
        }

        // Toggles
        $updateEnv('SHOW_NOTIFICATIONS', isset($_POST['show_notifications']) ? 'true' : 'false');
        $updateEnv('MAINTENANCE_MODE', isset($_POST['maintenance_mode']) ? 'true' : 'false');
        $updateEnv('ENABLE_AI_ANALYSIS', isset($_POST['enable_ai']) ? 'true' : 'false');

        file_put_contents($envFile, trim($envContent));
        $feedback = 'Configurações guardadas com sucesso! O sistema pode precisar de reiniciar.';
        
    } catch (Exception $e) {
        $feedback = 'Erro ao guardar: ' . $e->getMessage();
        $feedbackType = 'error';
    }
}

// --- Carregar valores atuais ---
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

include 'includes/header.php';
?>



<div class="cfg-container">

    <?php if ($feedback): ?>
    <div class="alert alert-<?= $feedbackType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show mb-4" role="alert" style="background: var(--green-soft); color: var(--green); border: 1px solid rgba(34, 197, 139, 0.2); border-radius: 8px;">
        <?= htmlspecialchars($feedback) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

   

    <form method="POST" action="">

        <!-- ══════════ SECÇÃO 1: CONTA & PREFERÊNCIAS ══════════ -->
        <div class="cfg-section-title"><i class="bi bi-person-fill"></i> Conta & Preferências</div>
        <div class="cfg-list-card">
            
            <!-- Perfil -->
            <div class="cfg-item">
                <div class="cfg-item-left">
                    <div class="cfg-item-icon icon-purple"><i class="bi bi-person-fill"></i></div>
                    <div class="cfg-item-text">
                        <h4><?= htmlspecialchars($user['name'] ?? 'Utilizador') ?></h4>
                        <p><?= htmlspecialchars($user['email'] ?? '') ?> <span class="cfg-badge"><?= strtoupper($user['role'] ?? 'USER') ?></span></p>
                    </div>
                </div>
                <div class="cfg-item-action">
                    <a href="perfil.php" class="btn-cfg-save" style="background:transparent; border:1px solid var(--border); color:var(--text); padding:8px 16px; font-size:13px;"><i class="bi bi-pencil-square"></i> Editar Perfil</a>
                </div>
            </div>

            <!-- Idioma -->
            <div class="cfg-item">
                <div class="cfg-item-left">
                    <div class="cfg-item-icon icon-blue"><i class="bi bi-translate"></i></div>
                    <div class="cfg-item-text">
                        <h4>Idioma / Language</h4>
                        <p>Língua da interface da plataforma</p>
                    </div>
                </div>
                <div class="cfg-item-action">
                    <select name="language" class="cfg-input-sm cfg-select-sm" disabled>
                        <option>🇲🇿 Português (PT)</option>
                        <option>🇺🇸 English (EN)</option>
                    </select>
                </div>
            </div>

            <!-- Tema -->
            <div class="cfg-item">
                <div class="cfg-item-left">
                    <div class="cfg-item-icon icon-orange"><i class="bi bi-circle-half"></i></div>
                    <div class="cfg-item-text">
                        <h4>Tema</h4>
                        <p>Aparência visual da aplicação</p>
                    </div>
                </div>
                <div class="cfg-item-action">
                    <div style="display:flex; gap:8px;">
                        <button type="button" class="cfg-input-sm" id="btnThemeDark" onclick="setCfgTheme('dark')" style="width:auto; cursor:pointer; display:flex; align-items:center; gap:6px;"><i class="bi bi-moon-fill"></i> Escuro</button>
                        <button type="button" class="cfg-input-sm" id="btnThemeLight" onclick="setCfgTheme('light')" style="width:auto; cursor:pointer; display:flex; align-items:center; gap:6px;"><i class="bi bi-sun-fill"></i> Claro</button>
                    </div>
                </div>
            </div>

            <!-- Notificações -->
            <div class="cfg-item">
                <div class="cfg-item-left">
                    <div class="cfg-item-icon icon-cyan"><i class="bi bi-bell-fill"></i></div>
                    <div class="cfg-item-text">
                        <h4>Notificações do Sistema</h4>
                        <p>Exibir alertas e avisos no topo da aplicação</p>
                    </div>
                </div>
                <div class="cfg-item-action">
                    <input type="checkbox" name="show_notifications" value="1" style="display:none" <?= $getEnv('SHOW_NOTIFICATIONS', 'true') === 'true' ? 'checked' : '' ?>>
                    <div class="cfg-switch <?= $getEnv('SHOW_NOTIFICATIONS', 'true') === 'true' ? 'active' : '' ?>" 
                         onclick="this.classList.toggle('active'); this.previousElementSibling.checked = this.classList.contains('active');"></div>
                </div>
            </div>

            <!-- Registos por Página -->
            <div class="cfg-item">
                <div class="cfg-item-left">
                    <div class="cfg-item-icon icon-blue"><i class="bi bi-list-ul"></i></div>
                    <div class="cfg-item-text">
                        <h4>Registos por Página</h4>
                        <p>Número de itens exibidos nas tabelas</p>
                    </div>
                </div>
                <div class="cfg-item-action">
                    <select name="records_per_page" class="cfg-input-sm cfg-select-sm">
                        <option value="25" <?= $getEnv('RECORDS_PER_PAGE') == '25' ? 'selected' : '' ?>>25</option>
                        <option value="50" <?= $getEnv('RECORDS_PER_PAGE') == '50' ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $getEnv('RECORDS_PER_PAGE') == '100' ? 'selected' : '' ?>>100</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- ══════════ SECÇÃO 2: CONFIGURAÇÃO DO SISTEMA ══════════ -->
        <div class="cfg-section-title"><i class="bi bi-hdd-network-fill"></i> Configuração do Sistema</div>
        <div class="cfg-list-card">

            <!-- Servidor Remoto -->
            <div class="cfg-item">
                <div class="cfg-item-left">
                    <div class="cfg-item-icon icon-green"><i class="bi bi-server"></i></div>
                    <div class="cfg-item-text">
                        <h4>Servidor Remoto (SFTP)</h4>
                        <p>Host e porta para conexão SSH/SFTP</p>
                    </div>
                </div>
                <div class="cfg-item-action" style="display:flex; gap:8px;">
                    <input type="text" name="remote_host" class="cfg-input-sm" value="<?= htmlspecialchars($getEnv('AUDIOS_HOST', '192.168.4.55')) ?>" placeholder="Host" style="width:140px;">
                    <input type="number" name="remote_port" class="cfg-input-sm" value="<?= htmlspecialchars($getEnv('AUDIOS_PORT', '22')) ?>" placeholder="Porta" style="width:70px;">
                </div>
            </div>

            <!-- Credenciais SSH -->
            <div class="cfg-item">
                <div class="cfg-item-left">
                    <div class="cfg-item-icon icon-green"><i class="bi bi-key-fill"></i></div>
                    <div class="cfg-item-text">
                        <h4>Credenciais SSH</h4>
                        <p>Utilizador e palavra-passe do servidor remoto</p>
                    </div>
                </div>
                <div class="cfg-item-action" style="display:flex; gap:8px;">
                    <input type="text" name="remote_user" class="cfg-input-sm" value="<?= htmlspecialchars($getEnv('AUDIOS_USER', 'hjit')) ?>" placeholder="User" style="width:100px;">
                    <div class="pwd-wrap">
                        <input type="password" name="remote_pass" class="cfg-input-sm" value="<?= htmlspecialchars($getEnv('AUDIOS_PASS')) ?>" placeholder="Password">
                        <i class="bi bi-eye" onclick="let i=this.previousElementSibling; i.type=i.type==='password'?'text':'password'"></i>
                    </div>
                </div>
            </div>

            <!-- Pasta de Áudios -->
            <div class="cfg-item">
                <div class="cfg-item-left">
                    <div class="cfg-item-icon icon-green"><i class="bi bi-folder-fill"></i></div>
                    <div class="cfg-item-text">
                        <h4>Pasta de Áudios (Remota)</h4>
                        <p>Caminho no servidor onde os áudios são armazenados</p>
                    </div>
                </div>
                <div class="cfg-item-action">
                    <input type="text" name="audio_dir_remote" class="cfg-input-sm" value="<?= htmlspecialchars($getEnv('AUDIO_DIR', '~/Recordings')) ?>">
                </div>
            </div>

            <!-- Pasta de Transcrições -->
            <div class="cfg-item">
                <div class="cfg-item-left">
                    <div class="cfg-item-icon icon-green"><i class="bi bi-file-earmark-text-fill"></i></div>
                    <div class="cfg-item-text">
                        <h4>Pasta de Transcrições (Remota)</h4>
                        <p>Caminho onde os ficheiros .txt são guardados</p>
                    </div>
                </div>
                <div class="cfg-item-action">
                    <input type="text" name="text_dir" class="cfg-input-sm" value="<?= htmlspecialchars($getEnv('TEXT_DIR', '~/text_files')) ?>">
                </div>
            </div>

            <!-- Intervalo de Sync -->
            <div class="cfg-item">
                <div class="cfg-item-left">
                    <div class="cfg-item-icon icon-orange"><i class="bi bi-clock-fill"></i></div>
                    <div class="cfg-item-text">
                        <h4>Intervalo de Verificação</h4>
                        <p>Frequência de sincronização com o servidor (segundos)</p>
                    </div>
                </div>
                <div class="cfg-item-action">
                    <input type="number" name="sync_interval" class="cfg-input-sm" value="<?= htmlspecialchars($getEnv('SYNC_INTERVAL', '30')) ?>" min="5" style="width:100px;">
                </div>
            </div>

            <!-- Modo Manutenção -->
            <div class="cfg-item">
                <div class="cfg-item-left">
                    <div class="cfg-item-icon icon-red"><i class="bi bi-exclamation-triangle-fill"></i></div>
                    <div class="cfg-item-text">
                        <h4>Modo de Manutenção</h4>
                        <p>Restringir acesso apenas a administradores</p>
                    </div>
                </div>
                <div class="cfg-item-action">
                    <input type="checkbox" name="maintenance_mode" value="1" style="display:none" <?= $getEnv('MAINTENANCE_MODE') === 'true' ? 'checked' : '' ?>>
                    <div class="cfg-switch <?= $getEnv('MAINTENANCE_MODE') === 'true' ? 'active' : '' ?>" 
                         onclick="this.classList.toggle('active'); this.previousElementSibling.checked = this.classList.contains('active');"></div>
                </div>
            </div>
        </div>

        <!-- ══════════ SECÇÃO 3: INTEGRAÇÕES IA ══════════ -->
        <div class="cfg-section-title"><i class="bi bi-cpu-fill"></i> Integrações Externas (IA)</div>
        <div class="cfg-list-card">

            <!-- Groq API Key -->
            <div class="cfg-item">
                <div class="cfg-item-left">
                    <div class="cfg-item-icon icon-purple"><i class="bi bi-key-fill"></i></div>
                    <div class="cfg-item-text">
                        <h4>Groq API Key</h4>
                        <p>Chave de acesso à API do Groq para análise</p>
                    </div>
                </div>
                <div class="cfg-item-action">
                    <div class="pwd-wrap">
                        <input type="password" name="groq_api_key" class="cfg-input-sm" value="<?= htmlspecialchars($getEnv('GROQ_API_KEY')) ?>" placeholder="gsk_...">
                        <i class="bi bi-eye" onclick="let i=this.previousElementSibling; i.type=i.type==='password'?'text':'password'"></i>
                    </div>
                </div>
            </div>

            <!-- Modelo Groq -->
            <div class="cfg-item">
                <div class="cfg-item-left">
                    <div class="cfg-item-icon icon-purple"><i class="bi bi-robot"></i></div>
                    <div class="cfg-item-text">
                        <h4>Modelo Groq (LLM)</h4>
                        <p>Modelo de linguagem para análise de relatórios</p>
                    </div>
                </div>
                <div class="cfg-item-action">
                    <select name="groq_model" class="cfg-input-sm cfg-select-sm">
                        <option value="llama3-70b-8192" <?= $getEnv('GROQ_MODEL') === 'llama3-70b-8192' ? 'selected' : '' ?>>Llama 3 70b</option>
                        <option value="mixtral-8x7b-32768" <?= $getEnv('GROQ_MODEL') === 'mixtral-8x7b-32768' ? 'selected' : '' ?>>Mixtral 8x7b</option>
                        <option value="gemma-7b-it" <?= $getEnv('GROQ_MODEL') === 'gemma-7b-it' ? 'selected' : '' ?>>Gemma 7b</option>
                        <option value="qwen/qwen3.8-27b" <?= strpos($getEnv('GROQ_MODEL'), 'qwen') !== false ? 'selected' : '' ?>>Qwen 3.8 27b</option>
                    </select>
                </div>
            </div>

            <!-- Modelo Whisper -->
            <div class="cfg-item">
                <div class="cfg-item-left">
                    <div class="cfg-item-icon icon-cyan"><i class="bi bi-mic-fill"></i></div>
                    <div class="cfg-item-text">
                        <h4>Modelo Whisper (Local)</h4>
                        <p>Modelo de transcrição de áudio local</p>
                    </div>
                </div>
                <div class="cfg-item-action">
                    <select name="whisper_model" class="cfg-input-sm cfg-select-sm">
                        <option value="tiny" <?= $getEnv('WHISPER_MODEL') === 'tiny' ? 'selected' : '' ?>>Tiny (Rápido)</option>
                        <option value="base" <?= $getEnv('WHISPER_MODEL') === 'base' ? 'selected' : '' ?>>Base</option>
                        <option value="small" <?= $getEnv('WHISPER_MODEL') === 'small' ? 'selected' : '' ?>>Small</option>
                        <option value="medium" <?= $getEnv('WHISPER_MODEL') === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="large-v3" <?= $getEnv('WHISPER_MODEL') === 'large-v3' ? 'selected' : '' ?>>Large v3 (Preciso)</option>
                    </select>
                </div>
            </div>

            <!-- Endpoint API -->
            <div class="cfg-item">
                <div class="cfg-item-left">
                    <div class="cfg-item-icon icon-blue"><i class="bi bi-link-45deg"></i></div>
                    <div class="cfg-item-text">
                        <h4>Endpoint API</h4>
                        <p>URL da API do Groq (opcional)</p>
                    </div>
                </div>
                <div class="cfg-item-action">
                    <input type="text" name="api_endpoint" class="cfg-input-sm" value="<?= htmlspecialchars($getEnv('GROQ_URL', 'https://api.groq.com/openai/v1/chat/completions')) ?>" style="width:280px;">
                </div>
            </div>

            <!-- Análise Automática -->
            <div class="cfg-item">
                <div class="cfg-item-left">
                    <div class="cfg-item-icon icon-purple"><i class="bi bi-lightning-fill"></i></div>
                    <div class="cfg-item-text">
                        <h4>Análise Automática com IA</h4>
                        <p>Processar transcrições automaticamente após conversão</p>
                    </div>
                </div>
                <div class="cfg-item-action">
                    <input type="checkbox" name="enable_ai" value="1" style="display:none" <?= $getEnv('ENABLE_AI_ANALYSIS', 'true') === 'true' ? 'checked' : '' ?>>
                    <div class="cfg-switch <?= $getEnv('ENABLE_AI_ANALYSIS', 'true') === 'true' ? 'active' : '' ?>" 
                         onclick="this.classList.toggle('active'); this.previousElementSibling.checked = this.classList.contains('active');"></div>
                </div>
            </div>
        </div>

        <!-- ══════════ SECÇÃO 4: INFORMAÇÕES DO SISTEMA ══════════ -->
        <div class="cfg-section-title"><i class="bi bi-info-circle-fill"></i> Informações do Sistema</div>
        <div class="cfg-list-card">
            <div class="cfg-item">
                <div class="cfg-item-left">
                    <div class="cfg-item-icon icon-blue"><i class="bi bi-pc-display"></i></div>
                    <div class="cfg-item-text"><h4>Servidor</h4><p>Nome e endereço IP</p></div>
                </div>
                <div class="cfg-item-action"><span class="cfg-info-val"><?= $_SERVER['SERVER_NAME'] ?? 'localhost' ?> (<?= $_SERVER['SERVER_ADDR'] ?? '127.0.0.1' ?>)</span></div>
            </div>
            <div class="cfg-item">
                <div class="cfg-item-left">
                    <div class="cfg-item-icon icon-green"><i class="bi bi-filetype-php"></i></div>
                    <div class="cfg-item-text"><h4>Versão PHP</h4><p>Versão instalada no servidor</p></div>
                </div>
                <div class="cfg-item-action"><span class="cfg-info-val"><?= phpversion() ?></span></div>
            </div>
            <div class="cfg-item">
                <div class="cfg-item-left">
                    <div class="cfg-item-icon icon-orange"><i class="bi bi-memory"></i></div>
                    <div class="cfg-item-text"><h4>Memória Limite</h4><p>Limite de memória PHP</p></div>
                </div>
                <div class="cfg-item-action"><span class="cfg-info-val"><?= ini_get('memory_limit') ?></span></div>
            </div>
            <div class="cfg-item">
                <div class="cfg-item-left">
                    <div class="cfg-item-icon icon-cyan"><i class="bi bi-hdd-network"></i></div>
                    <div class="cfg-item-text"><h4>Web Server</h4><p>Software do servidor web</p></div>
                </div>
                <div class="cfg-item-action"><span class="cfg-info-val"><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></span></div>
            </div>
            <div class="cfg-item">
                <div class="cfg-item-left">
                    <div class="cfg-item-icon icon-green"><i class="bi bi-activity"></i></div>
                    <div class="cfg-item-text"><h4>Estado Analyser</h4><p>Serviço Python na porta 8001</p></div>
                </div>
                <div class="cfg-item-action"><span class="cfg-badge">Online</span></div>
            </div>
        </div>

        <!-- Barra de Guardar -->
        <div class="cfg-save-bar">
            <button type="submit" class="btn-cfg-save"><i class="bi bi-save"></i> Guardar Todas as Alterações</button>
        </div>

    </form>
</div>

<script>
function setCfgTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('qa-theme', theme);
    
    const btnDark = document.getElementById('btnThemeDark');
    const btnLight = document.getElementById('btnThemeLight');
    
    if (theme === 'dark') {
        btnDark.style.borderColor = 'var(--accent)'; btnDark.style.color = 'var(--accent)'; btnDark.style.background = 'var(--accent-soft)';
        btnLight.style.borderColor = 'var(--border)'; btnLight.style.color = 'var(--text)'; btnLight.style.background = 'var(--bg3)';
    } else {
        btnLight.style.borderColor = 'var(--accent)'; btnLight.style.color = 'var(--accent)'; btnLight.style.background = 'var(--accent-soft)';
        btnDark.style.borderColor = 'var(--border)'; btnDark.style.color = 'var(--text)'; btnDark.style.background = 'var(--bg3)';
    }
    
    const headerIcon = document.getElementById('themeIcon');
    if (headerIcon) headerIcon.className = theme === 'dark' ? 'bi bi-moon-fill' : 'bi bi-sun-fill';
}

document.addEventListener('DOMContentLoaded', () => {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
    setCfgTheme(currentTheme);
});
</script>

<?php include 'includes/footer.php'; ?>
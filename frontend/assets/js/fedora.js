/* ══════════════════════════════════════════════════════════════════
   QA Análise — app.js (Centralizado & Corrigido v2)
   Contém: Theme, i18n (Expandido), Chart.js, Dashboard Logic, Modals
══════════════════════════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Inicializar Tema (Crucial para o index.php funcionar)
    initTheme();
    
    // 2. Inicializar Tradução
    initI18n();
    
    // 3. Funcionalidades Globais
    initGlobalSearch();
    initSidebarMobile();
    initUserDropdown();

    // 4. Detectar página actual e inicializar módulos específicos
    const body = document.body;
    const page = body.dataset.page || ''; // Segurança se data-page não existir

    if (page === 'dashboard') initDashboard();
    if (page === 'audios') initAudios();
    if (page === 'relatorios') initRelatorios();
    if (page === 'gestao_contas') initGestao();
});

/* ══════════ 1. TEMA (Dark/Light) ══════════ */
function initTheme() {
    const saved = localStorage.getItem('qa-theme') || 'dark';
    applyTheme(saved, false);
}

window.toggleTheme = function() {
    const current = document.documentElement.getAttribute('data-theme') || 'dark';
    const next = current === 'dark' ? 'light' : 'dark';
    applyTheme(next, true);
    localStorage.setItem('qa-theme', next);
};

function applyTheme(theme, animate) {
    if (animate) {
        document.documentElement.style.transition = 'background .3s, color .3s';
        setTimeout(() => document.documentElement.style.transition = '', 400);
    }
    document.documentElement.setAttribute('data-theme', theme);
    
    // Atualizar ícone (Lua/Sol)
    const icon = document.getElementById('themeIcon');
    if (icon) {
        icon.className = theme === 'dark' ? 'bi bi-moon-fill' : 'bi bi-sun-fill';
    }
    
    // Atualizar gráficos se existirem (Dashboard)
    if (window.__qaCharts) {
        Object.values(window.__qaCharts).forEach(chart => updateChartTheme(chart, theme));
    }
}

function updateChartTheme(chart, theme) {
    if (!chart) return;
    const gridColor = theme === 'dark' ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.06)';
    const tickColor = theme === 'dark' ? '#9aa0b4' : '#5c6278';
    
    if (chart.options.scales.x) chart.options.scales.x.ticks.color = tickColor;
    if (chart.options.scales.y) {
        chart.options.scales.y.ticks.color = tickColor;
        chart.options.scales.y.grid.color = gridColor;
    }
    if (chart.options.plugins.legend) chart.options.plugins.legend.labels.color = tickColor;
    chart.update();
}

/* ══════════ 2. TRADUÇÃO (i18n) — DICIONÁRIO EXPANDIDO ══════════ */
const PT_EN = {
    // --- Sidebar & Nav ---
    "Principal": "Home", 
    "Início": "Home",
    "Dashboard": "Dashboard", 
    "Gestão de Dados": "Data Management",
    "Ficheiros": "Files",
    "Áudios": "Audios", 
    "Áudios detectados": "Detected Audios",
    "Transcrições": "Transcriptions", 
    "Ficheiros de texto": "Text Files",
    "Histórico": "History",
    "Administração": "Administration", 
    "Configurações": "Settings", 
    "Utilizadores": "Users",
    "Gestão de Contas": "Account Management",
    "Logs do Sistema": "System Logs", 
    "Suporte": "Support", 
    "Guia de usuário": "User Guide", 
    "Fale Connosco": "Contact Us",
    "Meu Perfil": "My Profile",
    "Sair da conta": "Sign out",
    "Sair": "Sign out",

    // --- Topbar & Geral ---
    "Pesquisar...": "Search...", 
    "Notificações": "Notifications",
    "Alternar tema": "Toggle theme",
    "Editar perfil": "Edit profile", 
    "Terminar sessão": "Sign out",
    "Actualizar": "Refresh",
    "Atualizar": "Refresh",

    // --- Login & Recuperar ---
    "Entrar": "Sign in", 
    "Utilizador": "Username", 
    "Palavra-passe": "Password",
    "Esqueceu a palavra-passe?": "Forgot password?", 
    "Bem-vindo de volta": "Welcome back",
    "QA Análise de Qualidade": "QA Quality Analysis",
    "Plataforma de análise de qualidade de chamadas": "Call quality analysis platform",
    "Credenciais inválidas ou conta desativada.": "Invalid credentials or disabled account.",
    "Recuperar Palavra-passe": "Recover Password",
    "Introduza o seu email e enviaremos um link de redefinição.": "Enter your email and we will send a reset link.",
    "Enviar Link de Redefinição": "Send Reset Link",
    "Lembrou-se da palavra-passe?": "Remembered your password?",

    // --- Dashboard ---
    "Atividades em Curso": "Ongoing Activities",
    "Transcrições por Hora": "Transcriptions per Hour", 
    "Hoje": "Today",
    "Distribuição de Resultados": "Results Distribution",
    "Transcrições concluídas": "Completed transcriptions", 
    "Transcrições pendentes": "Pending transcriptions",
    "Falhas de transcrição": "Transcription failures", 
    "Total de ficheiros de áudio": "Total audio files",
    "Ficheiros de áudio": "Audio files",
    "Tamanho total (áudios)": "Total size (audios)",
    "Tamanho total (transcrições)": "Total size (transcriptions)",
    "Progresso da Transcrição": "Transcription Progress",
    "Sem transcrição em curso — barras a 0%.": "No transcription in progress — bars at 0%.",
    "Sem transcrição em curso.": "No transcription in progress.",
    "A processar: ": "Processing: ",
    "Download": "Download", "Conversão": "Conversion", "Transcrição": "Transcription", "Upload": "Upload",
    "Actividade por Dia (últimos 7 dias)": "Activity per Day (last 7 days)",
    "Actividade Recente": "Recent Activity",
    "A aguardar actividade do daemon…": "Waiting for daemon activity...",

    // --- Áudios ---
    "Pesquisar por ficheiro, data ou tipo...": "Search by file, date or type...",
    "Todos": "All",
    "Ficheiro": "File", "Data": "Date", "Tipo": "Type", "Tamanho": "Size", "Acções": "Actions",
    "Ouvir": "Listen", "Só download": "Download only",
    "Servidor de áudios indisponível": "Audio server unavailable",
    "Pasta remota vazia": "Remote folder empty",
    "Ainda não existem áudios em": "No audios yet in",
    "Sem resultados": "No results",
    "Tente outro termo de pesquisa ou remova o filtro de tipo.": "Try another search term or remove the type filter.",
    "Baixar ficheiro": "Download file", "Fechar": "Close",

    // --- Relatórios ---
    "Pesquisar por ficheiro, áudio ou data...": "Search by file, audio or date...",
    "Ver": "View", "Baixar .txt": "Download .txt",
    "Sem transcrições ainda": "No transcriptions yet",
    "Assim que o daemon transcrever um áudio, o .txt aparece aqui.": "As soon as the daemon transcribes an audio, the .txt appears here.",
    "Servidor de transcrições indisponível": "Transcription server unavailable",
    "A carregar…": "Loading...",
    "Não foi possível carregar o conteúdo.": "Could not load content.",
    "Erro ao carregar conteúdo.": "Error loading content.",

    // --- Gestão de Contas ---
    "Criar Nova Conta": "Create New Account",
    "Nome Completo": "Full Name", "E-mail": "Email",
    "Gerada automaticamente": "Auto-generated",
    "Criar Conta": "Create Account",
    "Utilizadores Registados": "Registered Users",
    "Sem utilizadores": "No users",
    "Execute": "Run", "para criar os utilizadores padrão.": "to create default users.",
    "Você": "You", "Administrador": "Administrator", "Gestor": "Manager",
    "Ativo": "Active", "Inativo": "Inactive",
    "Desativar": "Deactivate", "Ativar": "Activate",
    "Redefinir Pass": "Reset Pass", "Protegida": "Protected",
    "Redefinir Palavra-passe": "Reset Password",
    "Nova Palavra-passe": "New Password", "Mínimo 6 caracteres": "Min 6 chars",
    "Cancelar": "Cancel", "Redefinir": "Reset",
    "Eliminar Conta": "Delete Account", "Eliminar": "Delete",
    "Tem a certeza que pretende eliminar a conta de": "Are you sure you want to delete account of",
    "Esta acção é irreversível.": "This action is irreversible.",
    "Conta criada com sucesso.": "Account created successfully."
};

function initI18n() {
    const lang = localStorage.getItem('qa-lang') || 'pt';
    const label = document.getElementById('langLabel');
    if (label) label.textContent = lang.toUpperCase();
    
    if (lang === 'en') {
        translatePage(PT_EN);
    }
}

window.toggleLang = function() {
    const next = (localStorage.getItem('qa-lang') || 'pt') === 'pt' ? 'en' : 'pt';
    localStorage.setItem('qa-lang', next);
    location.reload(); // Recarrega para aplicar tradução limpa
};

function translatePage(dict) {
    // Traduzir nós de texto
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    
    nodes.forEach(node => {
        // Ignorar scripts, styles, e inputs (value)
        if (node.parentElement.closest('script, style, textarea, input')) return;
        
        const text = node.textContent.trim();
        // Procura exacta no dicionário
        if (dict[text]) {
            node.textContent = node.textContent.replace(text, dict[text]);
        } else {
            // Tentativa de tradução parcial (para frases com variáveis)
            for (const [pt, en] of Object.entries(dict)) {
                if (text.includes(pt) && pt.length > 3) {
                    node.textContent = node.textContent.replace(pt, en);
                }
            }
        }
    });

    // Traduzir Placeholders e Titles
    document.querySelectorAll('[placeholder], [title]').forEach(el => {
        ['placeholder', 'title'].forEach(attr => {
            const val = el.getAttribute(attr);
            if (val) {
                const trimmed = val.trim();
                if (dict[trimmed]) {
                    el.setAttribute(attr, dict[trimmed]);
                } else {
                    // Tradução parcial para placeholders
                    for (const [pt, en] of Object.entries(dict)) {
                        if (trimmed.includes(pt) && pt.length > 3) {
                            el.setAttribute(attr, trimmed.replace(pt, en));
                            break;
                        }
                    }
                }
            }
        });
    });
}

/* ══════════ 3. LÓGICA GLOBAL ══════════ */
function initGlobalSearch() {
    const gs = document.getElementById('globalSearch');
    const ls = document.getElementById('searchInput');
    if (gs && ls) {
        gs.addEventListener('input', () => {
            ls.value = gs.value;
            ls.dispatchEvent(new Event('input'));
        });
    }
}

function initSidebarMobile() {
    document.addEventListener('click', (e) => {
        if (window.innerWidth > 991) return;
        const sb = document.getElementById('sidebar');
        const tg = document.getElementById('sidebar-toggle');
        if (sb && document.body.classList.contains('sidebar-open') 
            && !sb.contains(e.target) && !tg.contains(e.target)) {
            document.body.classList.remove('sidebar-open');
        }
    });
}

function initUserDropdown() {
    // Fechar dropdown ao clicar fora
    document.addEventListener('click', (e) => {
        const wrap = document.querySelector('.user-wrap');
        if (wrap && wrap.classList.contains('open') && !wrap.contains(e.target)) {
            wrap.classList.remove('open');
        }
    });

    // Fechar com ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelector('.user-wrap')?.classList.remove('open');
        }
    });
}

window.confirmLogout = function() {
    const m = document.getElementById('logoutModal');
    if (m) bootstrap.Modal.getOrCreateInstance(m).show();
    else window.location.href = 'logout.php';
};

window.toggleUserMenu = function(e) {
    e.stopPropagation();
    document.getElementById('userChipBtn')?.closest('.user-wrap')?.classList.toggle('open');
};

/* ══════════ 4. DASHBOARD (Gráficos + Carrossel + Progresso) ══════════ */
function initDashboard() {
    window.__qaCharts = {};
    const theme = document.documentElement.getAttribute('data-theme') || 'dark';
    const tickColor = theme === 'dark' ? '#9aa0b4' : '#5c6278';
    const gridColor = theme === 'dark' ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.06)';
    
    // ... (mantenha o código dos gráficos Chart.js aqui) ...

    // --- Polling de Progresso (a cada 3 segundos) ---
    pollProgress();
    setInterval(pollProgress, 3000); // 3000ms = 3 segundos
}

async function pollProgress() {
    try {
        // ✅ CORRETO: Usa a variável global definida no header.php + timestamp para evitar cache
        const baseUrl = (typeof window.QA_API_BASE !== 'undefined') ? window.QA_API_BASE : 'http://localhost:8001';
        const r = await fetch(baseUrl + '/health?t=' + Date.now(), { cache: 'no-store' });
        
        if (!r.ok) return;
        const d = await r.json();
        
        // Atualizar mensagem de idle se existir
        const idle = document.getElementById('dbIdleMsg');
        if (idle) {
            if (d.queue_size > 0) {
                idle.classList.add('busy');
                idle.textContent = `A processar: ${d.queue_size} na fila`;
            } else {
                idle.classList.remove('busy');
                idle.textContent = 'Sem transcrição em curso.';
            }
        }
        
        // Atualizar indicador "AO VIVO" no Dashboard
        const liveBadge = document.getElementById('liveBadge');
        const lastUpdate = document.getElementById('lastUpdate');
        if (liveBadge && lastUpdate) {
            liveBadge.classList.remove('stale');
            lastUpdate.textContent = new Date().toLocaleTimeString('pt-PT');
        }
    } catch (e) {
        const liveBadge = document.getElementById('liveBadge');
        if (liveBadge) liveBadge.classList.add('stale');
        // console.warn('[QA] Progress polling offline:', e.message);
    }
}

/* ══════════ 5. AUDIOS & RELATORIOS (Filtros e Modais) ══════════ */
function initAudios() {
    window.filterCards = function() {
        const q = document.getElementById('searchInput')?.value.toLowerCase().trim();
        const type = window.arActiveType || '';
        const rows = document.querySelectorAll('.ar-row');
        let visible = 0;

        rows.forEach(r => {
            const matchQ = !q || r.dataset.search.includes(q);
            const matchT = !type || r.dataset.type === type;
            const show = matchQ && matchT;
            r.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        const count = document.getElementById('searchCount');
        if (count) count.textContent = `${visible} registo(s)`;
        
        const noRes = document.getElementById('arNoResults');
        if (noRes) noRes.classList.toggle('d-none', visible > 0);
    };

    window.filterType = function(type, btn) {
        window.arActiveType = (type !== '' && window.arActiveType === type) ? '' : type;
        document.querySelectorAll('.ar-chip').forEach(c => c.classList.remove('active'));
        if (btn) btn.classList.add('active');
        else document.querySelector('.ar-chip[data-type=""]')?.classList.add('active');
        window.filterCards();
    };

    window.openAudioPlayer = function(btn) {
        document.getElementById('arpTitle').textContent = btn.dataset.name;
        document.getElementById('arpMeta').textContent = `${btn.dataset.type} · ${btn.dataset.size}`;
        const audio = document.getElementById('arpAudio');
        audio.src = btn.dataset.stream;
        document.getElementById('arpDownload').href = btn.dataset.download;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('audioPlayerModal')).show();
        audio.play().catch(() => {});
    };
}

function initRelatorios() {
    window.filterCards = function() {
        const q = document.getElementById('searchInput')?.value.toLowerCase().trim();
        const rows = document.querySelectorAll('.rr-row');
        let visible = 0;
        rows.forEach(r => {
            const show = !q || r.dataset.search.includes(q);
            r.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        const noRes = document.getElementById('rrNoResults');
        if (noRes) noRes.classList.toggle('d-none', visible > 0);
    };

    window.openTxtModal = function(btn) {
        document.getElementById('rrpTitle').textContent = btn.dataset.name;
        document.getElementById('rrpMeta').textContent = btn.dataset.meta;
        document.getElementById('rrpDownload').href = btn.dataset.download;
        const pre = document.getElementById('rrpContent');
        
        // Texto de carregamento traduzido
        const isLoading = localStorage.getItem('qa-lang') === 'en' ? 'Loading...' : 'A carregar...';
        pre.innerHTML = `<div style="text-align:center; padding:20px">${isLoading}</div>`;
        
        bootstrap.Modal.getOrCreateInstance(document.getElementById('txtModal')).show();

        fetch(btn.dataset.view)
            .then(r => r.ok ? r.text() : Promise.reject())
            .then(txt => {
                const lines = txt.split('\n').filter(l => l.trim());
                pre.innerHTML = lines.map((line, i) => {
                    const match = line.match(/^\[([^\]]+)\]\s*(.*)/);
                    if (match) {
                        return `<div class="speaker-line ${i % 2 === 0 ? '' : 'even'}">
                            <span class="speaker-badge">${match[1]}</span>
                            <span class="speaker-text">${match[2]}</span>
                        </div>`;
                    }
                    return `<div class="speaker-line"><span class="speaker-text">${line}</span></div>`;
                }).join('');
            })
            .catch(() => { 
                const errMsg = localStorage.getItem('qa-lang') === 'en' ? 'Error loading content.' : 'Erro ao carregar conteúdo.';
                pre.textContent = errMsg; 
            });
    };
}

/* ══════════ 6. GESTÃO DE CONTAS ══════════ */
function initGestao() {
    window.openResetModal = function(username, name) {
        document.getElementById('resetPassTarget').value = username;
        const label = localStorage.getItem('qa-lang') === 'en' ? 'User' : 'Utilizador';
        document.getElementById('resetPassLabel').textContent = `${label}: ${name} (${username})`;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('resetPassModal')).show();
    };

    window.openDeleteModal = function(username, name) {
        document.getElementById('deleteUserTarget').value = username;
        const msg = localStorage.getItem('qa-lang') === 'en' 
            ? `Are you sure you want to delete "${name}"?` 
            : `Tem a certeza que pretende eliminar "${name}"?`;
        document.getElementById('deleteUserLabel').textContent = msg;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteUserModal')).show();
    };

    // Geração automática de password
    const nameInput = document.getElementById('createName');
    const passInput = document.getElementById('createPassword');
    if (nameInput && passInput) {
        nameInput.addEventListener('blur', function() {
            if (this.value.length >= 2) {
                const letters = this.value.substring(0, 3);
                const pwd = letters.charAt(0).toUpperCase() + letters.slice(1).toLowerCase() + 
                            ['@','#','!'][Math.floor(Math.random()*3)] + 
                            Math.floor(100 + Math.random() * 900);
                passInput.value = pwd;
            }
        });
    }

    window.copyGeneratedPassword = function() {
        const pwd = document.getElementById('createPassword').value;
        if (pwd) {
            navigator.clipboard.writeText(pwd);
            const icon = document.getElementById('copyPassIcon');
            if(icon) {
                icon.className = 'bi bi-clipboard-check';
                setTimeout(() => icon.className = 'bi bi-clipboard', 2000);
            }
        }
    };
}

/* ══════════ CHATBOT IA (UNIFICADO) ══════════ */

// Toggle: abre se fechado, fecha se aberto
window.toggleChatbot = function() {
    const widget = document.getElementById('chatbotWidget');
    const fab = document.getElementById('chatbotFab');
    if (!widget) return;

    const isHidden = widget.style.display === 'none' || widget.style.display === '';

    if (isHidden) {
        widget.style.display = 'flex';
        if (fab) fab.innerHTML = '<i class="bi bi-x-lg"></i>';
        setTimeout(() => {
            const input = document.getElementById('chatbotInput');
            if (input) input.focus();
        }, 150);
    } else {
        widget.style.display = 'none';
        if (fab) fab.innerHTML = '<i class="bi bi-robot"></i><span class="chatbot-fab-badge">IA</span>';
    }
};

// Alias para compatibilidade com header.php
window.openChatbot = function() {
    const widget = document.getElementById('chatbotWidget');
    if (widget && (widget.style.display === 'none' || widget.style.display === '')) {
        toggleChatbot();
    }
};

window.closeChatbot = function() {
    const widget = document.getElementById('chatbotWidget');
    if (widget && widget.style.display !== 'none') {
        toggleChatbot();
    }
};

// Enviar mensagem
window.sendChatMessage = function() {
    const input = document.getElementById('chatbotInput');
    const body = document.getElementById('chatbotBody');
    if (!input || !body) return;

    const text = input.value.trim();
    if (!text) return;

    input.value = '';

    const userMsg = document.createElement('div');
    userMsg.className = 'chat-msg user';
    userMsg.textContent = text;
    body.appendChild(userMsg);
    body.scrollTop = body.scrollHeight;

    setTimeout(() => {
        const botMsg = document.createElement('div');
        botMsg.className = 'chat-msg bot';
        botMsg.textContent = "Estou a analisar os relatórios para responder à sua pergunta sobre '" + text + "'... (Funcionalidade de IA em desenvolvimento)";
        body.appendChild(botMsg);
        body.scrollTop = body.scrollHeight;
    }, 1000);
};

// Clicar numa sugestão
window.askSuggestion = function(btn) {
    let text = btn.innerText.trim();
    text = text.replace(/^[\u{1F300}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}]\s*/u, '');
    if (!text) return;

    const sc = document.getElementById('chatSuggestions');
    if (sc) sc.remove();

    const input = document.getElementById('chatbotInput');
    if (input) {
        input.value = text;
        window.sendChatMessage();
    }
};
<?php
require_once 'includes/config.php';
require_once 'includes/api.php';
requireLogin();
$pageTitle     = 'Guia de Usuário';
$activeSection = 'guia';

// Ler tickets do utilizador para mostrar histórico
$ticketsFile = __DIR__ . '/../backend/storage/support_tickets.json';
$myTickets = [];
if (file_exists($ticketsFile)) {
    $content = file_get_contents($ticketsFile);
    if ($content) {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            $user = currentUser();
            $username = $user['username'] ?? '';
            // Filtrar apenas tickets do utilizador actual
            $myTickets = array_filter($decoded, fn($t) => ($t['username'] ?? '') === $username);
            $myTickets = array_values($myTickets);
        }
    }
}

// Feedback após submissão
$ticketFeedback = $_GET['ticket'] ?? '';
$ticketMsg = $_GET['msg'] ?? '';

include 'includes/header.php';
?>
<style>
/* ══════ TICKETS HISTÓRICO ══════ */
.help-tickets { margin-top: 30px; }
.help-tickets h3 {
    font-family: var(--font-display); font-size: 18px; font-weight: 600;
    color: var(--text); margin-bottom: 16px;
    display: flex; align-items: center; gap: 8px;
}
.help-tickets h3 i { color: var(--accent); }
.ticket-list { display: flex; flex-direction: column; gap: 10px; }
.ticket-item {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 10px; padding: 14px 18px;
    display: flex; justify-content: space-between; align-items: center;
    transition: border-color .2s;
}
.ticket-item:hover { border-color: var(--accent); }
.ticket-info h4 { margin: 0 0 4px; font-size: 14px; color: var(--text); font-weight: 600; }
.ticket-info p  { margin: 0; font-size: 12px; color: var(--text3); }
.ticket-badge {
    display: inline-block; padding: 3px 10px; border-radius: 4px;
    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px;
}
.ticket-badge-enviado   { background: rgba(34,197,139,.1); color: var(--green); border: 1px solid rgba(34,197,139,.25); }
.ticket-badge-pendente  { background: rgba(245,166,35,.1); color: var(--amber); border: 1px solid rgba(245,166,35,.25); }
.ticket-badge-respondido { background: rgba(56,189,248,.1); color: var(--cyan); border: 1px solid rgba(56,189,248,.25); }

/* ══════ ALERTA FEEDBACK ══════ */
.ticket-alert {
    padding: 14px 18px; border-radius: 10px; margin-bottom: 20px;
    display: flex; align-items: center; gap: 10px; font-size: 14px; font-weight: 500;
    animation: fadeIn .3s ease;
}
.ticket-alert-success { background: var(--green-soft); color: var(--green); border: 1px solid rgba(34,197,139,.25); }
.ticket-alert-error   { background: var(--red-soft); color: var(--red); border: 1px solid rgba(240,80,110,.25); }
.ticket-alert i { font-size: 18px; flex-shrink: 0; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

/* ══════ MODAL FORM ══════ */
.support-modal .modal-content {
    background: var(--surface); border: 1px solid var(--border); border-radius: 16px;
}
.support-modal .modal-header {
    border-bottom: 1px solid var(--border); padding: 20px 24px;
}
.support-modal .modal-body { padding: 24px; }
.support-modal .modal-footer { border-top: 1px solid var(--border); padding: 16px 24px; }
.support-modal .form-label {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .5px; color: var(--text3); margin-bottom: 6px;
}
.support-modal .form-control {
    background: var(--bg3); border: 1px solid var(--border); color: var(--text);
    border-radius: 8px; padding: 10px 14px; width: 100%; font-size: 14px;
    font-family: var(--font-body);
}
.support-modal .form-control:focus {
    background: var(--surface); border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(108,99,255,.1); color: var(--text); outline: none;
}
.btn-submit-ticket {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--accent); color: #fff; border: none;
    padding: 10px 24px; border-radius: 8px; font-size: 14px; font-weight: 600;
    cursor: pointer; transition: all .2s;
}
.btn-submit-ticket:hover { background: #5a52d5; transform: translateY(-1px); }
</style>

<div class="help-wrapper">
<div class="help-grid">
<main class="help-content" id="home">

    <!-- ══ ALERTA DE FEEDBACK ══ -->
    <?php if ($ticketFeedback === 'success'): ?>
    <div class="ticket-alert ticket-alert-success" id="ticketAlert">
        <i class="bi bi-check-circle-fill"></i>
        <span>Pedido enviado com sucesso! Receberá uma resposta em breve.</span>
    </div>
    <?php elseif ($ticketFeedback === 'error'): ?>
    <div class="ticket-alert ticket-alert-error" id="ticketAlert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span><?= htmlspecialchars($ticketMsg ?: 'Erro ao enviar o pedido.') ?></span>
    </div>
    <?php endif; ?>

    <h1 class="help-title">Bem-vindo ao Centro de Ajuda</h1>
    

    <!-- Caixa de Informação -->
    <div class="help-info-box">
        <div class="help-info-icon"><i class="bi bi-info-circle-fill"></i></div>
        <div class="help-info-content">
            <!--h4>O que está coberto agora</!--h4>
            <p>
                Esta versão cobre <strong>Transcrições de Áudio</strong> e <strong>Relatórios PDF</strong>.
                Guias para <strong>Análise de Sentimento Avançada</strong> e <strong>Integração API</strong> estarão disponíveis em breve.
            </p>-->
            <p class="help-intro">
                Este guia orienta você passo a passo no uso do sistema de Análise de chamadas em linguagem simples e directa.<br>Siga os passos apresentados abaixo para melhor experiência </br> 
            </p>
        </div>
    </div>

    <!-- Secção: Por onde começar -->
    <section class="help-section" id="start">
        <h2>Por onde começar</h2>
        <ul class="help-list">
            <li>Novo no sistema? Comece com <a href="#dashboard" class="help-link">Visão Geral do Dashboard</a>.</li>
            <li>Pronto para verificar análises de chamadas? Vá para <a href="#relatorios" class="help-link">Relatórios → Análise de Qualidade</a>.</li>
            <li>Algo não está a funcionar? Verifique <a href="#troubleshooting" class="help-link">Resolução de Problemas & FAQ</a>.</li>
        </ul>
    </section>

    

   <!-- Secção: Dashboard -->
<section class="help-section" id="dashboard">
    <h2>Visão Geral do Dashboard</h2>
    <p style="color: var(--text2); line-height: 1.6; margin-bottom: 20px;">
        O Dashboard é o seu centro de comando. Para aceder, clique em <strong>Dashboard</strong> no menu lateral esquerdo. Aqui encontra:
    </p>

    <!-- Item 1: KPIs -->
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 20px; margin-bottom: 16px;">
        <h3 style="font-size: 16px; font-weight: 600; color: var(--text); margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
            <i class="bi bi-bar-chart-fill" style="color: var(--accent);"></i> Cartões de métricas
        </h3>
        <p style="color: var(--text2); font-size: 14px; line-height: 1.6; margin: 0 0 10px 0;">
            Quatro cartões no topo da página mostram as métricas essenciais em tempo real:
        </p>
        <ul class="help-list" style="margin: 0;">
            <li><strong>Ficheiros Detectados:</strong> Total de transcrições disponíveis no sistema.</li>
            <li><strong>Analisados com Sucesso:</strong> Percentagem e número de chamadas já processadas pela IA.</li>
            <li><strong>Falha de Análise:</strong> Ficheiros que não puderam ser analisados (ex: transcrição demasiado curta).</li>
            <li><strong>Pendentes:</strong> Ficheiros na fila de espera para análise.</li>
        </ul>
        <div style="margin-top: 12px; padding: 10px 14px; background: var(--bg3); border-radius: 6px; font-size: 13px; color: var(--text3);">
            <i class="bi bi-eye" style="color: var(--accent);"></i> <strong>Como visualizar:</strong> Basta abrir o Dashboard — os cartões aparecem automaticamente no topo, actualizando-se a cada 8 segundos.
        </div>
    </div>

    <!-- Item 2: Gráfico de Performance -->
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 20px; margin-bottom: 16px;">
        <h3 style="font-size: 16px; font-weight: 600; color: var(--text); margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
            <i class="bi bi-graph-up" style="color: var(--accent);"></i> Gráfico de Performance
        </h3>
        <p style="color: var(--text2); font-size: 14px; line-height: 1.6; margin: 0 0 10px 0;">
            Gráfico de linhas que mostra o volume de análises por hora do dia (00:00 às 23:00), com três linhas coloridas:
        </p>
        <ul class="help-list" style="margin: 0;">
            <li><strong style="color: #22c58b;">Linha Verde (Sucesso):</strong> Análises concluídas com êxito.</li>
            <li><strong style="color: #f0506e;">Linha Vermelha (Falhas):</strong> Análises que falharam.</li>
            <li><strong style="color: #f5a623;">Linha Amarela (Pendentes):</strong> Análises ainda em processamento.</li>
            <li><strong style="color: rgba(108, 99, 255, 0.7);">Barras Roxas (Volume Total):</strong> Total de actividades por hora.</li>
        </ul>
        <div style="margin-top: 12px; padding: 10px 14px; background: var(--bg3); border-radius: 6px; font-size: 13px; color: var(--text3);">
            <i class="bi bi-eye" style="color: var(--accent);"></i> <strong>Como visualizar:</strong> O gráfico aparece na coluna esquerda do Dashboard. Use o selector <strong>"Dia"</strong> no canto superior direito do gráfico para filtrar por um dia específico do mês. Passe o rato sobre qualquer ponto para ver os valores exactos.
        </div>
    </div>

    <!-- Item 3: Tabela de Agentes -->
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 20px; margin-bottom: 16px;">
        <h3 style="font-size: 16px; font-weight: 600; color: var(--text); margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
            <i class="bi bi-trophy-fill" style="color: var(--accent);"></i> Tabelas de Agentes
        </h3>
        <p style="color: var(--text2); font-size: 14px; line-height: 1.6; margin: 0 0 10px 0;">
            Na coluna direita do Dashboard encontra duas tabelas distintas:
        </p>
        <ul class="help-list" style="margin: 0;">
            <li><strong style="color: #22c58b;">🏆 Melhores Agentes de Hoje:</strong> Top 3 agentes com melhor média de pontuação nas análises concluídas no dia actual.</li>
            <li><strong style="color: #f0506e;">⚠️ Agentes que Necessitam de Melhoria:</strong> Lista de agentes com média entre 0% e 50%, ordenados do pior para o melhor, para priorizar formação.</li>
        </ul>
        <div style="margin-top: 12px; padding: 10px 14px; background: var(--bg3); border-radius: 6px; font-size: 13px; color: var(--text3);">
            <i class="bi bi-eye" style="color: var(--accent);"></i> <strong>Como visualizar:</strong> As tabelas actualizam-se automaticamente. A percentagem de cada agente é colorida — <span style="color: #22c58b;">verde</span> (≥80%), <span style="color: #f5a623;">laranja</span> (60-79%) ou <span style="color: #f0506e;">vermelho</span> (&lt;60%). Para ver o perfil completo de um agente, vá a <strong>Administração → Perfil dos Agentes</strong>.
        </div>
    </div>

    <!-- Item 4: Live Activity Feed -->
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 20px; margin-bottom: 16px;">
        <h3 style="font-size: 16px; font-weight: 600; color: var(--text); margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
            <i class="bi bi-activity" style="color: var(--accent);"></i> Actividade Recente (Live Feed)
        </h3>
        <p style="color: var(--text2); font-size: 14px; line-height: 1.6; margin: 0 0 10px 0;">
            Lista em tempo real das últimas 8 análises processadas pelo sistema, com indicador de estado:
        </p>
        <ul class="help-list" style="margin: 0;">
            <li><strong style="color: #22c58b;">● Ponto Verde:</strong> Análise concluída com sucesso — mostra o nome do ficheiro e a pontuação final.</li>
            <li><strong style="color: #f0506e;">● Ponto Vermelho:</strong> Análise falhou — mostra o motivo do erro.</li>
            <li><strong style="color: #f5a623;">● Ponto Amarelo:</strong> Análise em processamento.</li>
        </ul>
        <div style="margin-top: 12px; padding: 10px 14px; background: var(--bg3); border-radius: 6px; font-size: 13px; color: var(--text3);">
            <i class="bi bi-eye" style="color: var(--accent);"></i> <strong>Como visualizar:</strong> O feed actualiza-se automaticamente a cada 8 segundos. O tempo relativo ("há 5m", "há 2h") indica quando cada análise foi processada.
        </div>
    </div>
</section>

<!-- Secção: Relatórios -->
<section class="help-section" id="relatorios">
    <h2>Análise de Relatórios</h2>
    <p style="color: var(--text2); line-height: 1.6; margin-bottom: 20px;">
        Para aceder aos relatórios, clique em <strong>Relatórios disponíveis (PDFs)</strong> no menu lateral. Cada chamada analisada gera um relatório PDF completo com as seguintes secções:
    </p>

    <!-- Item 1: Pontuação Global -->
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 20px; margin-bottom: 16px;">
        <h3 style="font-size: 16px; font-weight: 600; color: var(--text); margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
            <i class="bi bi-speedometer2" style="color: var(--accent);"></i> Pontuação Global (0–100%)
        </h3>
        <p style="color: var(--text2); font-size: 14px; line-height: 1.6; margin: 0 0 10px 0;">
            A pontuação final da chamada, calculada com base em 5 categorias ponderadas. Na lista de relatórios, é apresentada como um anel circular colorido:
        </p>
        <ul class="help-list" style="margin: 0;">
            <li><strong style="color: #22c58b;">Verde (≥80%):</strong> Classificação <strong>"BOM"</strong> — chamada de excelência.</li>
            <li><strong style="color: #f5a623;">Laranja (60-79%):</strong> Classificação <strong>"MÉDIO"</strong> — cumpre expectativas.</li>
            <li><strong style="color: #f0506e;">Vermelho (&lt;60%):</strong> Classificação <strong>"Necessita Melhoria"</strong> — requer atenção.</li>
        </ul>
        <div style="margin-top: 12px; padding: 10px 14px; background: var(--bg3); border-radius: 6px; font-size: 13px; color: var(--text3);">
            <i class="bi bi-eye" style="color: var(--accent);"></i> <strong>Como visualizar:</strong> Na página de Relatórios, a coluna <strong>"Pontuação"</strong> mostra o anel com a percentagem. Pode filtrar rapidamente usando os botões <strong>BOM</strong>, <strong>MÉDIO</strong> ou <strong>Necessita Melhoria</strong> no topo da lista. A pontuação detalhada por categoria encontra-se dentro do PDF — clique em <strong>Transferir</strong> para o abrir.
        </div>
    </div>

    <!-- Item 2: Categorias de Análise -->
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 20px; margin-bottom: 16px;">
        <h3 style="font-size: 16px; font-weight: 600; color: var(--text); margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
            <i class="bi bi-list-check" style="color: var(--accent);"></i> Categorias de Análise
        </h3>
        <p style="color: var(--text2); font-size: 14px; line-height: 1.6; margin: 0 0 10px 0;">
            Cada chamada é avaliada em 5 categorias, cada uma com pontuação de 0 a 5 e peso específico:
        </p>
        <ul class="help-list" style="margin: 0;">
            <li><strong>Abertura e Conformidade (15%):</strong> Saudação, identificação e cumprimento de protocolos.</li>
            <li><strong>Clareza e Precisão (20%):</strong> Comunicação clara, informação correcta e sem ambiguidades.</li>
            <li><strong>Profissionalismo e Tom (20%):</strong> Linguagem adequada, cordialidade e postura profissional.</li>
            <li><strong>Empatia e Escuta Activa (25%):</strong> Capacidade de compreender o cliente e demonstrar interesse genuíno.</li>
            <li><strong>Identificação e Resolução de Problemas (20%):</strong> Eficácia na resolução da necessidade do cliente.</li>
        </ul>
        <div style="margin-top: 12px; padding: 10px 14px; background: var(--bg3); border-radius: 6px; font-size: 13px; color: var(--text3);">
            <i class="bi bi-eye" style="color: var(--accent);"></i> <strong>Como visualizar:</strong> Transfira o PDF clicando no botão <strong><i class="bi bi-download"></i> Transferir</strong> na coluna de Acções. Abra o PDF e procure a secção <strong>"AVALIAÇÃO POR CATEGORIAS"</strong> — aí encontra uma tabela com a pontuação, o peso, os pontos positivos (✔) e os pontos de melhoria (✘) de cada categoria.
        </div>
    </div>

    <!-- Item 3: Resumo Executivo -->
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 20px; margin-bottom: 16px;">
        <h3 style="font-size: 16px; font-weight: 600; color: var(--text); margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
            <i class="bi bi-file-earmark-text" style="color: var(--accent);"></i> Resumo Executivo
        </h3>
        <p style="color: var(--text2); font-size: 14px; line-height: 1.6; margin: 0 0 10px 0;">
            O PDF contém uma análise narrativa completa com as seguintes secções:
        </p>
        <ul class="help-list" style="margin: 0;">
            <li><strong>Sumário Executivo:</strong> Resumo geral da chamada em linguagem simples.</li>
            <li><strong>✔ Pontos Fortes:</strong> O que o agente fez bem, com exemplos concretos da transcrição.</li>
            <li><strong>⚠ Pontos Fracos / Problemas:</strong> Aspectos negativos identificados durante a chamada.</li>
            <li><strong>↑ O Que Precisa Ser Melhorado:</strong> Recomendações práticas e accionáveis.</li>
            <li><strong>★ O Que Foi Perfeito:</strong> Momentos de excelência que devem ser replicados.</li>
            <li><strong>Análise de Sentimento:</strong> Emoção detectada no cliente e no agente durante a chamada.</li>
            <li><strong>Conformidade:</strong> Verificação de frases obrigatórias, frases proibidas e acções-chave (ex: plano de pagamento oferecido).</li>
        </ul>
        <div style="margin-top: 12px; padding: 10px 14px; background: var(--bg3); border-radius: 6px; font-size: 13px; color: var(--text3);">
            <i class="bi bi-eye" style="color: var(--accent);"></i> <strong>Como visualizar:</strong> Transfira o PDF e navegue pelas secções coloridas. Cada secção tem um ícone e cor próprios para facilitar a leitura rápida. O relatório é gerado automaticamente pela IA em português europeu.
        </div>
    </div>

    <!-- Item 4: Acções Disponíveis -->
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 20px; margin-bottom: 16px;">
        <h3 style="font-size: 16px; font-weight: 600; color: var(--text); margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
            <i class="bi bi-hand-index-thumb" style="color: var(--accent);"></i> Acções Disponíveis na Lista
        </h3>
        <p style="color: var(--text2); font-size: 14px; line-height: 1.6; margin: 0 0 10px 0;">
            Para cada relatório na lista, tem três acções rápidas na coluna da direita:
        </p>
        <ul class="help-list" style="margin: 0;">
            <li><strong><i class="bi bi-eye" style="color: var(--accent);"></i> Ver (Transcrição):</strong> Abre um modal com o texto completo da transcrição da chamada, com os intervenientes identificados.</li>
            <li><strong><i class="bi bi-play-circle" style="color: var(--green);"></i> Ouvir (Áudio):</strong> Abre um leitor de áudio para escutar a chamada original directamente no browser.</li>
            <li><strong><i class="bi bi-download" style="color: var(--cyan);"></i> Transferir (PDF):</strong> Descarrega o relatório PDF completo para o seu computador.</li>
        </ul>
        <div style="margin-top: 12px; padding: 10px 14px; background: var(--bg3); border-radius: 6px; font-size: 13px; color: var(--text3);">
            <i class="bi bi-eye" style="color: var(--accent);"></i> <strong>Como visualizar:</strong> Passe o rato sobre os ícones na coluna <strong>"Acções"</strong> para ver o nome de cada acção. Clique em qualquer um para executar. Pode também usar a <strong>barra de pesquisa</strong> no topo para encontrar relatórios por agente, cliente ou classificação.
        </div>
    </div>
</section>

    <!-- Secção: Troubleshooting -->
    <section class="help-section" id="troubleshooting">
        <h2>Problemas Comuns</h2>
        <p style="color: var(--text2); line-height: 1.6;">
            <strong>Problema:</strong> O relatório não aparece na lista.<br>
            <strong>Solução:</strong> Verifique se o ficheiro de áudio foi colocado na pasta correcta no servidor e aguarde alguns minutos para o processamento.
        </p>
    </section>
<!-- Secção: Precisa de mais ajuda? -->
    <section class="help-section" id="support">
        <h2>Precisa de ajuda?</h2>
        <p style="font-size: 15px; color: var(--text); line-height: 1.6; margin-bottom: 20px;">
            Se este guia não responder à sua pergunta, entre em contacto com  a nossa equipa de suporte. 
        <!-- ✅ BOTÃO ABRE MODAL -->
      <div>
        <button type="button" class="btn-report" data-bs-toggle="modal" data-bs-target="#modalSuporte"
                style="display: inline-flex; align-items: center; gap: 8px; background: var(--accent); color: white; padding: 10px 20px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; transition: opacity 0.2s; box-shadow: 0 4px 12px rgba(108,99,255,.2);">
            <i class="bi bi-chat-dots"></i> Fale connosco
        </button>
      </div>
    </section>
    <!-- ══ HISTÓRICO DE TICKETS ══ -->
    <?php if (!empty($myTickets)): ?>
    <section class="help-tickets">
        <h3><i class="bi bi-clock-history"></i> Os meus pedidos de suporte</h3>
        <div class="ticket-list">
            <?php foreach ($myTickets as $ticket):
                $estado = $ticket['estado'] ?? 'Pendente';
                $respondido = !empty($ticket['resposta']) || $estado === 'Respondido';
                if ($respondido) {
                    $badgeClass = 'ticket-badge-respondido'; $badgeText = 'Respondido';
                } elseif ($estado === 'Enviado') {
                    $badgeClass = 'ticket-badge-enviado'; $badgeText = 'Enviado';
                } else {
                    $badgeClass = 'ticket-badge-pendente'; $badgeText = 'Pendente';
                }
            ?>
            <div class="ticket-item">
                <div class="ticket-info">
                    <h4><?= htmlspecialchars($ticket['assunto']) ?></h4>
                    <p><?= date('d/m/Y · H:i', strtotime($ticket['data'])) ?></p>
                </div>
                <span class="ticket-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</main>
</div>
</div>

<!-- ══════════ MODAL: TICKET DE SUPORTE ══════════ -->
<div class="modal fade support-modal" id="modalSuporte" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" style="color: var(--text);">
                    <i class="bi bi-headset text-accent me-2"></i>Relatar Problema
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="fale_conosco.php">
                <input type="hidden" name="action" value="submit_ticket">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="assunto" class="form-label">Assunto</label>
                        <input type="text" class="form-control" id="assunto" name="assunto"
                               placeholder="Ex: Erro ao gerar relatório" required>
                    </div>
                    <div class="mb-0">
                        <label for="problema" class="form-label">Descrição do Problema</label>
                        <textarea class="form-control" id="problema" name="problema" rows="4"
                                  placeholder="Descreva detalhadamente o que aconteceu..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                            style="border-radius: 8px; border: 1px solid var(--border); background: transparent; color: var(--text3);"
                            data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-submit-ticket">
                        <i class="bi bi-send"></i> Enviar 
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Auto-fechar alerta após 5 segundos
(function () {
    const alert = document.getElementById('ticketAlert');
    if (alert) {
        setTimeout(() => {
            alert.style.transition = 'opacity .4s, transform .4s';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-8px)';
            setTimeout(() => alert.remove(), 400);
        }, 5000);
    }

    // Limpar parâmetros da URL sem recarregar
    if (window.history.replaceState) {
        const url = new URL(window.location);
        url.searchParams.delete('ticket');
        url.searchParams.delete('msg');
        window.history.replaceState({}, '', url);
    }
})();

// Scroll spy para o TOC
document.addEventListener('DOMContentLoaded', function() {
    const sections = document.querySelectorAll('.help-section');
    window.addEventListener('scroll', function() {
        let current = '';
        sections.forEach(section => {
            if (pageYOffset >= section.offsetTop - 150) {
                current = section.getAttribute('id');
            }
        });
    });
});
</script>
<?php include 'includes/footer.php'; ?>
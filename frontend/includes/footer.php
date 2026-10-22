<!-- ══════════ CHATBOT IA (FAB + WIDGET) ══════════ -->
<!-- Botão Flutuante (Estilo Generate) -->
<button type="button" class="chatbot-fab" id="chatbotFab" onclick="toggleChatbot()" title="Assistente IA">
    <i class="bi bi-stars"></i> <!-- Ícone de Estrelas/Sparkles -->
    <span>Assistente</span> <!-- Texto do botão -->
</button>

<!-- Widget do Chatbot (Mantém igual) -->
<div class="chatbot-widget" id="chatbotWidget" style="display: none;">
    <div class="chatbot-header">
        <div class="chatbot-header-icon"><i class="bi bi-robot"></i></div>
        <div style="flex: 1; min-width: 0;">
            <div class="chatbot-header-title">Assistente QA</div>
            <div class="chatbot-header-sub">Pergunte sobre relatórios e análises.</div>
        </div>
        <button type="button" class="chatbot-close-btn" onclick="toggleChatbot()" title="Fechar">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <div class="chatbot-body" id="chatbotBody">
        <div class="chat-msg bot">Olá! Sou o assistente de QA. Posso analisar relatórios, explicar pontuações e sugerir melhorias.</div>
    
    </div>
    <div class="chatbot-footer">
        <div class="chatbot-input-row">
            <input type="text" id="chatbotInput" placeholder="Escreva a sua pergunta..." onkeypress="if(event.key==='Enter') sendChatMessage()">
            <button type="button" class="chatbot-send" onclick="sendChatMessage()"><i class="bi bi-send-fill"></i></button>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
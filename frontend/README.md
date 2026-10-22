# QualityCall — Frontend PHP + Bootstrap 5

## Estrutura de ficheiros

```
qualitycall/
├── index.php               ← Página de Login
├── logout.php              ← Encerrar sessão
├── analise.php             ← Nova Análise (upload + progresso)
├── relatorios.php          ← Lista de relatórios gerados
├── historico.php           ← Histórico de análises
│
├── includes/
│   ├── config.php          ← Configuração (URL da API, utilizadores, sessão)
│   ├── api.php             ← Funções cURL para comunicar com a API FastAPI
│   ├── header.php          ← HTML head + sidebar + topbar
│   └── footer.php          ← Modais (PDF, sucesso) + scripts JS
│
└── assets/
    ├── css/app.css         ← Tema escuro customizado sobre Bootstrap 5
    └── js/app.js           ← Lógica do cliente (upload, etapas, modais)
```

## Requisitos

- PHP >= 8.0 com extensão **cURL** activada
- Servidor web (Apache, Nginx ou `php -S localhost:8080`)
- API FastAPI a correr em `http://localhost:8000`

## Configuração rápida

1. Copia a pasta `qualitycall/` para o teu servidor web (ex: `htdocs/`, `www/`)

2. Abre `includes/config.php` e ajusta:
   ```php
   define('API_BASE', 'http://localhost:8000'); // URL do teu backend FastAPI
   ```

3. Para adicionar utilizadores reais, edita `DEMO_USERS` no mesmo ficheiro
   ou liga a autenticação à tua base de dados MySQL.

4. Inicia o servidor de desenvolvimento PHP:
   ```bash
   cd qualitycall
   php -S localhost:8080
   ```

5. Abre `http://localhost:8080` no browser.

## Credenciais demo

| Utilizador | Palavra-passe |
|------------|---------------|
| admin      | admin123      |
| gestor     | gestor123     |

## Ligação ao Backend

A variável `API_BASE` em `includes/config.php` (PHP, para chamadas server-side)
e em `assets/js/app.js` (JS, para o processamento assíncrono) devem apontar
para o mesmo endereço da tua API FastAPI.

> **CORS:** certifica-te que o teu backend FastAPI permite pedidos da origem
> do teu frontend PHP, adicionando o middleware `CORSMiddleware` ao `main.py`.

<?php
require_once 'includes/config.php';
require_once 'includes/api.php';
require_once 'includes/mailer.php';
requireLogin();

$ticketsFile = __DIR__ . '/../backend/storage/support_tickets.json';

if (!is_dir(dirname($ticketsFile))) {
    mkdir(dirname($ticketsFile), 0777, true);
}

// Carregar tickets existentes
$tickets = [];
if (file_exists($ticketsFile)) {
    $content = file_get_contents($ticketsFile);
    if ($content) {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            $tickets = $decoded;
        }
    }
}

// Processar submissão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_ticket') {
    $assunto  = trim($_POST['assunto'] ?? '');
    $problema = trim($_POST['problema'] ?? '');

    if (empty($assunto) || empty($problema)) {
        header('Location: guia.php?ticket=error&msg=' . urlencode('Preencha todos os campos.'));
        exit;
    }

    $user = currentUser();
    $emailSent = false;

    if (function_exists('sendSupportEmail')) {
        $emailSent = sendSupportEmail(
            $assunto,
            $problema,
            $user['name'] ?? $user['username'],
            $user['email'] ?? ''
        );
    }

    $estado = $emailSent ? 'Enviado' : 'Pendente';

    $newTicket = [
        'id'            => count($tickets) + 1,
        'user'          => $user['name'] ?? $user['username'],
        'username'      => $user['username'] ?? '',
        'email'         => $user['email'] ?? '',
        'assunto'       => $assunto,
        'problema'      => $problema,
        'data'          => date('Y-m-d H:i:s'),
        'estado'        => $estado,
        'resposta'      => null,
        'data_resposta' => null,
    ];

    array_unshift($tickets, $newTicket);

    $json = json_encode($tickets, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json !== false) {
        file_put_contents($ticketsFile, $json);
    }

    // Redirecionar de volta para guia.php com mensagem de sucesso
    header('Location: guia.php?ticket=success');
    exit;
}

// Se alguém aceder directamente, redirecionar para o guia
header('Location: guia.php');
exit;
<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ── Função existente: envio de credenciais ──
function sendAccountEmail(
    string $email,
    string $nome,
    string $username,
    string $password
): bool {

    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'callcenter.howard@gmail.com';
        $mail->Password   = 'sjbtpvhnaezlrwrh';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('callcenter.howard@gmail.com', 'QA Analise de Qualidade');
        $mail->addAddress($email, $nome);

        $mail->isHTML(true);
        $mail->Subject = 'Credenciais de acesso ao Sistema QA';

        $mail->Body = "
        <div style='font-family:Arial,sans-serif'>
            <h2 style='color:#0d6efd'>Conta criada com sucesso</h2>
            <p>Olá <strong>{$nome}</strong>,</p>
            <p>Foi criada uma conta para acesso ao Sistema QA.</p>
            <table style='border-collapse:collapse;width:100%'>
                <tr>
                    <td style='border:1px solid #ddd;padding:8px'><strong>Utilizador</strong></td>
                    <td style='border:1px solid #ddd;padding:8px'>{$username}</td>
                </tr>
                <tr>
                    <td style='border:1px solid #ddd;padding:8px'><strong>Palavra-passe</strong></td>
                    <td style='border:1px solid #ddd;padding:8px'>{$password}</td>
                </tr>
            </table>
            <p>Use o link: http://192.168.4.55/qa/index.php para aceder ao Sistema QA.</p>
            <p>Atenciosamente,<br>IT Suporte-Howard Johnson</p>
        </div>";

        $mail->AltBody =
            "Conta criada com sucesso.\n\n" .
            "Utilizador: {$username}\n" .
            "Palavra-passe: {$password}\n\n";

        return $mail->send();

    } catch (Exception $e) {
        error_log('Erro ao enviar email: ' . $mail->ErrorInfo);
        return false;
    }
}

// ── NOVA FUNÇÃO: envio de pedido de suporte ──
function sendSupportEmail(
    string $assunto,
    string $problema,
    string $nomeUtilizador,
    string $emailUtilizador
): bool {

    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'callcenter.howard@gmail.com';
        $mail->Password   = 'sjbtpvhnaezlrwrh';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // De: sistema → Para: suporte (o próprio Gmail recebe a notificação)
        $mail->setFrom('callcenter.howard@gmail.com', 'QA Suporte');
        $mail->addAddress('callcenter.howard@gmail.com', 'IT Suporte Howard Johnson');

        // Reply-To: email do utilizador (para responder directamente)
        if (!empty($emailUtilizador)) {
            $mail->addReplyTo($emailUtilizador, $nomeUtilizador);
        }

        $mail->isHTML(true);
        $mail->Subject = '[QA Suporte] ' . $assunto;

        $problemaHtml = nl2br(htmlspecialchars($problema));
        $nomeHtml     = htmlspecialchars($nomeUtilizador);
        $emailHtml    = htmlspecialchars($emailUtilizador);
        $dataHtml     = date('d/m/Y H:i');

        $mail->Body = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto'>
            <div style='background:#6c63ff;color:#fff;padding:20px;border-radius:8px 8px 0 0'>
                <h2 style='margin:0;font-size:20px'>📩 Novo Pedido de Suporte</h2>
            </div>
            <div style='background:#fff;padding:25px;border:1px solid #e0e0e0;border-top:none;border-radius:0 0 8px 8px'>
                <table style='width:100%;border-collapse:collapse;margin-bottom:20px'>
                    <tr>
                        <td style='padding:8px 0;color:#666;width:120px'><strong>Utilizador:</strong></td>
                        <td style='padding:8px 0'>{$nomeHtml}</td>
                    </tr>
                    <tr>
                        <td style='padding:8px 0;color:#666'><strong>Email:</strong></td>
                        <td style='padding:8px 0'><a href='mailto:{$emailHtml}' style='color:#6c63ff'>{$emailHtml}</a></td>
                    </tr>
                    <tr>
                        <td style='padding:8px 0;color:#666'><strong>Data:</strong></td>
                        <td style='padding:8px 0'>{$dataHtml}</td>
                    </tr>
                    <tr>
                        <td style='padding:8px 0;color:#666'><strong>Assunto:</strong></td>
                        <td style='padding:8px 0;font-weight:bold'>{$assunto}</td>
                    </tr>
                </table>
                <div style='background:#f8f9fa;padding:15px;border-radius:6px;border-left:4px solid #6c63ff'>
                    <strong style='color:#333'>Descrição do Problema:</strong>
                    <p style='margin:10px 0 0;color:#555;line-height:1.6'>{$problemaHtml}</p>
                </div>
                <p style='margin-top:20px;color:#888;font-size:12px'>
                    Para responder, use \"Responder\" no seu cliente de email (o reply-to está configurado para o utilizador).<br>
                    Ou responda através da plataforma QA → Fale Connosco.
                </p>
            </div>
        </div>";

        $mail->AltBody =
            "NOVO PEDIDO DE SUPORTE\n" .
            "========================\n\n" .
            "Utilizador: {$nomeUtilizador}\n" .
            "Email: {$emailUtilizador}\n" .
            "Data: {$dataHtml}\n" .
            "Assunto: {$assunto}\n\n" .
            "DESCRIÇÃO:\n{$problema}\n\n" .
            "---\nPara responder, use Reply-To ou a plataforma QA.";

        return $mail->send();

    } catch (Exception $e) {
        error_log('Erro ao enviar email de suporte: ' . $mail->ErrorInfo);
        return false;
    }
}
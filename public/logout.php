<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$destino = urlBase() . '/public/login.php';

// Se alguém abrir /logout.php direto pelo navegador, só volta ao login.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $destino);
    exit;
}

if (!validarTokenCsrf($_POST['token_csrf'] ?? null)) {
    http_response_code(403);
    exit('Solicitação inválida. Atualize a página e tente novamente.');
}

encerrarSessao();

header('Location: ' . $destino);
exit;
<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);

    session_start();
}

/**
 * Verifica se existe um usuário autenticado.
 */
function usuarioAutenticado(): bool
{
    return isset($_SESSION['usuario'])
        && isset($_SESSION['usuario']['id'])
        && isset($_SESSION['usuario']['perfil']);
}

/**
 * Retorna os dados do usuário autenticado.
 */
function usuarioAtual(): ?array
{
    return usuarioAutenticado()
        ? $_SESSION['usuario']
        : null;
}

/**
 * Exige autenticação para acessar uma página.
 */
function exigirLogin(): void
{
    if (!usuarioAutenticado()) {
        header('Location: /interclasse-etec/public/login.php');
        exit;
    }
}

/**
 * Permite acesso somente aos perfis autorizados.
 */
function exigirPerfil(string ...$perfisPermitidos): void
{
    exigirLogin();

    $perfil = $_SESSION['usuario']['perfil'];

    if (!in_array($perfil, $perfisPermitidos, true)) {
        http_response_code(403);
        exit('Acesso negado. Você não tem permissão para esta página.');
    }
}

/**
 * Gera um token CSRF para formulários.
 */
function gerarTokenCsrf(): string
{
    if (empty($_SESSION['token_csrf'])) {
        $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['token_csrf'];
}

/**
 * Valida o token CSRF enviado pelo formulário.
 */
function validarTokenCsrf(?string $token): bool
{
    return isset($_SESSION['token_csrf'])
        && is_string($token)
        && hash_equals($_SESSION['token_csrf'], $token);
}

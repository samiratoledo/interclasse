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
 * Descobre a pasta do projeto na URL (tudo que vem antes de /public/).
 * Ex.: /interclasse-etec/public/admin/x.php => /interclasse-etec
 */
function urlBase(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $pos = strpos($script, '/public/');

    return $pos === false ? '' : substr($script, 0, $pos);
}

/**
 * Página inicial de cada perfil.
 */
function urlInicial(string $perfil): string
{
    $base = urlBase() . '/public/';

    return match ($perfil) {
        'admin' => $base . 'admin/index.php',
        'representante' => $base . 'representante/index.php',
        'arbitro' => $base . 'arbitro/index.php',
        default => $base . 'login.php',
    };
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
 * Apaga a sessão e o cookie.
 */
function encerrarSessao(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $p['path'],
            'secure' => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

/**
 * Confere no banco se a conta continua ativa e com o mesmo perfil.
 * Exige que a página já tenha carregado config/database.php ($pdo).
 */
function contaAindaValida(): bool
{
    global $pdo;

    if (!($pdo instanceof PDO)) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT perfil, turma_id FROM usuarios WHERE id = ? AND ativo = 1 LIMIT 1'
        );
        $stmt->execute([(int) $_SESSION['usuario']['id']]);
        $conta = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        return false;
    }

    if (!$conta || $conta['perfil'] !== $_SESSION['usuario']['perfil']) {
        return false;
    }

    // Mantém a turma da sessão sempre atualizada.
    $_SESSION['usuario']['turma_id'] = $conta['turma_id'] !== null
        ? (int) $conta['turma_id']
        : null;

    return true;
}

/**
 * Exige autenticação para acessar uma página.
 */
function exigirLogin(): void
{
    if (!usuarioAutenticado() || !contaAindaValida()) {
        if (usuarioAutenticado()) {
            encerrarSessao();
        }

        header('Location: ' . urlBase() . '/public/login.php');
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
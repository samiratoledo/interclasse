<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Redireciona quem já está autenticado.
if (usuarioAutenticado()) {
    $perfil = $_SESSION['usuario']['perfil'];

    if ($perfil === 'admin') {
        header('Location: /interclasse-etec/public/admin/index.php');
    } elseif ($perfil === 'representante') {
        header('Location: /interclasse-etec/public/representante/index.php');
    } else {
        header('Location: /interclasse-etec/public/arbitro/partida.php');
    }

    exit;
}

$erro = '';
$nomeUsuario = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomeUsuario = trim($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $token = $_POST['token_csrf'] ?? null;

    if (!validarTokenCsrf($token)) {
        $erro = 'Sessão expirada ou formulário inválido. Atualize a página.';
    } elseif (
        $nomeUsuario === ''
        || strlen($nomeUsuario) > 50
        || $senha === ''
    ) {
        $erro = 'Informe um usuário válido e sua senha.';
    } else {
        try {

            $sql = 'SELECT id, nome, usuario_login, senha_hash, perfil, turma_id
FROM usuarios
WHERE (
    usuario_login = :usuario_login
    OR (
        usuario_login IS NULL
        AND nome = :nome
    )
)
AND ativo = 1
LIMIT 1';

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'usuario_login' => $nomeUsuario,
                'nome' => $nomeUsuario
            ]);

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'nome' => $nomeUsuario
            ]);

            $conta = $stmt->fetch(PDO::FETCH_ASSOC);

            // Confere se a conta existe e se a senha está correta.
            if (
                !$conta
                || !password_verify($senha, $conta['senha_hash'])
            ) {
                $erro = 'Usuário ou senha incorretos.';
            } elseif (
                $conta['perfil'] === 'representante'
                && empty($conta['turma_id'])
            ) {
                $erro = 'Sua conta ainda não está vinculada a uma turma. Procure a administração.';
            } else {
                // Renova o identificador da sessão após o login.
                session_regenerate_id(true);

                $_SESSION['usuario'] = [
                    'id' => (int) $conta['id'],
                    'nome' => $conta['nome'],
                    'perfil' => $conta['perfil'],
                    'turma_id' => $conta['turma_id'] !== null
                        ? (int) $conta['turma_id']
                        : null,
                ];

                // Redireciona de acordo com o perfil.
                if ($conta['perfil'] === 'admin') {
                    header('Location: /interclasse-etec/public/admin/index.php');
                } elseif ($conta['perfil'] === 'representante') {
                    header('Location: /interclasse-etec/public/representante/index.php');
                } elseif ($conta['perfil'] === 'arbitro') {
                    header('Location: /interclasse-etec/public/arbitro/partida.php');
                } else {
                    // Perfil desconhecido: encerra a sessão.
                    $_SESSION = [];
                    session_destroy();

                    $erro = 'Perfil de acesso inválido.';
                }

                if ($erro === '') {
                    exit;
                }
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $erro = 'Não foi possível realizar o login. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar | Interclasse ETEC</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css">
</head>

<body>
    <section class="section">
        <div class="container" style="max-width: 440px;">
            <h1 class="title has-text-centered">Interclasse ETEC</h1>

            <p class="subtitle has-text-centered">
                Acesse sua conta
            </p>

            <?php if ($erro !== ''): ?>
                <div class="notification is-danger is-light">
                    <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="token_csrf" value="<?= htmlspecialchars(
                    gerarTokenCsrf(),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>">

                <div class="field">
                    <label class="label" for="usuario">Usuário</label>

                    <div class="control">
                        <input class="input" type="text" id="usuario" name="usuario" placeholder="Digite seu usuário"
                            value="<?= htmlspecialchars(
                                $nomeUsuario,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>" maxlength="50" autocomplete="username" required>
                    </div>
                </div>

                <div class="field">
                    <label class="label" for="senha">Senha</label>

                    <div class="control">
                        <input class="input" type="password" id="senha" name="senha" placeholder="Digite sua senha"
                            autocomplete="current-password" required>
                    </div>
                </div>

                <div class="field mt-5">
                    <button class="button is-primary is-fullwidth" type="submit">
                        Entrar
                    </button>
                </div>
            </form>
        </div>
    </section>
</body>

</html>
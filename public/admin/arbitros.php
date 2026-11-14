<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

exigirPerfil('admin');

$usuario = usuarioAtual();

$erro = '';
$sucesso = '';
$mensagemArbitro = '';

/**
 * Escapa valores para HTML.
 */
function escaparArbitros(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redireciona para a própria página.
 */
function redirecionarArbitros(): never
{
    header('Location: arbitros.php');
    exit;
}

/**
 * Gera uma senha aleatória.
 */
function gerarSenhaArbitro(): string
{
    $caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    $parte1 = '';
    $parte2 = '';

    for ($i = 0; $i < 4; $i++) {
        $parte1 .= $caracteres[random_int(0, strlen($caracteres) - 1)];
        $parte2 .= $caracteres[random_int(0, strlen($caracteres) - 1)];
    }

    return 'Etec-' . $parte1 . '-' . $parte2;
}

/**
 * Gera ou recupera o token CSRF.
 */
if (empty($_SESSION['csrf_arbitros'])) {
    $_SESSION['csrf_arbitros'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_arbitros'];

/**
 * Processamento dos formulários.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = (string) ($_POST['csrf_token'] ?? '');

        if (!hash_equals($csrf, $token)) {
            throw new RuntimeException('Token de segurança inválido. Atualize a página e tente novamente.');
        }

        $acao = (string) ($_POST['acao'] ?? '');

        /*
         * CADASTRAR ÁRBITRO
         */
        if ($acao === 'cadastrar') {
            $nome = trim((string) ($_POST['nome'] ?? ''));
            $usuarioLogin = trim((string) ($_POST['usuario_login'] ?? ''));

            if ($nome === '') {
                throw new RuntimeException('Informe o nome do árbitro.');
            }

            if (mb_strlen($nome) > 150) {
                throw new RuntimeException('O nome deve ter no máximo 150 caracteres.');
            }

            if (
                $usuarioLogin === '' ||
                !preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $usuarioLogin)
            ) {
                throw new RuntimeException(
                    'O usuário deve ter entre 3 e 50 caracteres e conter apenas letras, números, ponto, hífen ou underline.'
                );
            }

            /*
             * Verifica se o usuário já existe.
             */
            $consulta = $pdo->prepare("
                SELECT id
                FROM usuarios
                WHERE usuario_login = :usuario
                LIMIT 1
            ");

            $consulta->execute([
                'usuario' => $usuarioLogin
            ]);

            if ($consulta->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException('Esse nome de usuário já está sendo utilizado. Escolha outro.');
            }

            /*
             * A senha continua sendo gerada automaticamente.
             */
            $senha = gerarSenhaArbitro();
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

            $inserir = $pdo->prepare("
                INSERT INTO usuarios (
                    nome,
                    usuario_login,
                    senha_hash,
                    perfil,
                    turma_id,
                    ativo
                ) VALUES (
                    :nome,
                    :usuario_login,
                    :senha_hash,
                    'arbitro',
                    NULL,
                    1
                )
            ");

            try {
                $inserir->execute([
                    'nome' => $nome,
                    'usuario_login' => $usuarioLogin,
                    'senha_hash' => $senhaHash
                ]);
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    throw new RuntimeException('Esse nome de usuário já está sendo utilizado. Escolha outro.');
                }

                throw $e;
            }

            $mensagemArbitro =
                "Árbitro cadastrado com sucesso!\n\n" .
                "Nome: {$nome}\n" .
                "Usuário: {$usuarioLogin}\n" .
                "Senha: {$senha}\n\n" .
                "Guarde essas informações. A senha não será exibida novamente.";

            $sucesso = 'Árbitro cadastrado com sucesso!';
        }

        /*
         * ATIVAR OU DESATIVAR ÁRBITRO
         */ elseif ($acao === 'alterar_status') {
            $arbitroId = filter_var(
                $_POST['arbitro_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$arbitroId || $arbitroId < 1) {
                throw new RuntimeException('Árbitro inválido.');
            }

            $consulta = $pdo->prepare("
                SELECT id, perfil, ativo
                FROM usuarios
                WHERE id = :id
                LIMIT 1
            ");

            $consulta->execute([
                'id' => $arbitroId
            ]);

            $arbitro = $consulta->fetch(PDO::FETCH_ASSOC);

            if (!$arbitro || $arbitro['perfil'] !== 'arbitro') {
                throw new RuntimeException('Árbitro não encontrado.');
            }

            $novoStatus = (int) $arbitro['ativo'] === 1 ? 0 : 1;

            $atualizar = $pdo->prepare("
                UPDATE usuarios
                SET ativo = :ativo
                WHERE id = :id
                  AND perfil = 'arbitro'
            ");

            $atualizar->execute([
                'ativo' => $novoStatus,
                'id' => $arbitroId
            ]);

            $sucesso = $novoStatus === 1
                ? 'Árbitro ativado com sucesso!'
                : 'Árbitro desativado com sucesso!';
        } else {
            throw new RuntimeException('Ação inválida.');
        }

    } catch (RuntimeException $e) {
        $erro = $e->getMessage();

    } catch (PDOException $e) {
        $erro = 'Não foi possível concluir a operação. Verifique os dados e tente novamente.';
    }
}

/**
 * Busca os árbitros cadastrados.
 */
$consultaArbitros = $pdo->query("
    SELECT
        id,
        nome,
        usuario_login,
        ativo,
        criado_em
    FROM usuarios
    WHERE perfil = 'arbitro'
    ORDER BY nome ASC
");

$arbitros = $consultaArbitros->fetchAll(PDO::FETCH_ASSOC);

$quantidadeAtivos = 0;

foreach ($arbitros as $arbitro) {
    if ((int) $arbitro['ativo'] === 1) {
        $quantidadeAtivos++;
    }
}

require_once __DIR__ . '/../../includes/admin/header.php';

?>

<div class="mb-5">
    <h1 class="title is-3">Gerenciar árbitros</h1>

    <p class="subtitle is-6">
        Cadastre, consulte e gerencie os acessos dos árbitros.
    </p>
</div>

<?php if ($erro !== ''): ?>
    <div class="notification is-danger is-dark">
        <?= escaparArbitros($erro) ?>
    </div>
<?php endif; ?>

<?php if ($sucesso !== ''): ?>
    <div class="notification is-success is-dark">
        <h2 class="title is-5">
            <?= escaparArbitros($sucesso) ?>
        </h2>
    </div>
<?php endif; ?>

<?php if ($mensagemArbitro !== ''): ?>
    <div class="box mb-5">
        <h2 class="title is-5">Credenciais do árbitro</h2>

        <p class="mb-3">
            Copie e guarde as informações abaixo. A senha não será exibida novamente.
        </p>

        <div class="field">
            <label class="label" for="credenciais-arbitro">
                Dados de acesso
            </label>

            <div class="control">
                <textarea class="textarea" id="credenciais-arbitro" readonly
                    rows="6"><?= escaparArbitros($mensagemArbitro) ?></textarea>
            </div>
        </div>

        <button type="button" class="button is-link" onclick="copiarCredenciaisArbitro()">
            Copiar credenciais
        </button>
    </div>
<?php endif; ?>

<div class="box mb-5">
    <p class="heading">Total de árbitros</p>

    <p class="title is-3">
        <?= count($arbitros) ?>
    </p>

    <div class="tags mt-3">
        <span class="tag is-success is-dark">
            <?= $quantidadeAtivos ?> ativos
        </span>

        <span class="tag is-dark">
            <?= count($arbitros) - $quantidadeAtivos ?> inativos
        </span>
    </div>
</div>

<div class="box mb-5">
    <div class="level mb-4">
        <div class="level-left">
            <div>
                <h2 class="title is-5">Cadastrar árbitro</h2>

                <p class="subtitle is-6">
                    Defina o nome e o usuário de acesso do árbitro.
                </p>
            </div>
        </div>
    </div>

    <form method="POST" action="arbitros.php">
        <input type="hidden" name="csrf_token" value="<?= escaparArbitros($csrf) ?>">

        <input type="hidden" name="acao" value="cadastrar">

        <div class="field">
            <label class="label" for="nome">
                Nome completo
            </label>

            <div class="control">
                <input class="input" type="text" id="nome" name="nome" maxlength="150" autocomplete="off"
                    placeholder="Exemplo: João Silva" required>
            </div>
        </div>

        <div class="field">
            <label class="label" for="usuario_login">
                Nome de usuário
            </label>

            <div class="control">
                <input class="input" type="text" id="usuario_login" name="usuario_login" minlength="3" maxlength="50"
                    pattern="[a-zA-Z0-9._-]+" autocomplete="off" placeholder="Exemplo: joao.arbitro" required>
            </div>

            <p class="help">
                Você escolhe o usuário que o árbitro utilizará para entrar no sistema.
            </p>

            <p class="help">
                Permitidos: letras, números, ponto, hífen e underline.
            </p>
        </div>

        <div class="field">
            <label class="label">Senha</label>

            <div class="control">
                <input class="input" type="text" value="Gerada automaticamente" disabled>
            </div>

            <p class="help">
                Uma senha será criada automaticamente após o cadastro.
            </p>
        </div>

        <div class="field mt-4">
            <button class="button is-link" type="submit">
                Cadastrar árbitro
            </button>
        </div>
    </form>
</div>

<div class="box">
    <div class="level mb-4">
        <div class="level-left">
            <div>
                <h2 class="title is-5">Árbitros cadastrados</h2>

                <p class="subtitle is-6">
                    Consulte os árbitros e gerencie seus acessos.
                </p>
            </div>
        </div>

        <div class="level-right">
            <span class="tag is-info is-dark is-medium">
                <?= count($arbitros) ?>
                <?= count($arbitros) === 1 ? 'árbitro' : 'árbitros' ?>
            </span>
        </div>
    </div>

    <?php if ($arbitros === []): ?>

        <div class="has-text-centered py-6">
            <p class="title is-5">Nenhum árbitro cadastrado</p>

            <p class="has-text-grey">
                Os árbitros cadastrados aparecerão nesta tabela.
            </p>
        </div>

    <?php else: ?>

        <div class="table-container">
            <table class="table is-fullwidth is-striped is-hoverable">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Usuário</th>
                        <th>Situação</th>
                        <th class="has-text-centered">Ações</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($arbitros as $arbitro): ?>
                        <?php $ativo = (int) $arbitro['ativo'] === 1; ?>

                        <tr>
                            <td>
                                <strong>
                                    <?= escaparArbitros($arbitro['nome']) ?>
                                </strong>

                                <br>

                                <span class="is-size-7 has-text-grey">
                                    Cadastro:
                                    <?= escaparArbitros(
                                        date(
                                            'd/m/Y H:i',
                                            strtotime($arbitro['criado_em'])
                                        )
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= escaparArbitros($arbitro['usuario_login']) ?>
                            </td>

                            <td>
                                <?php if ($ativo): ?>
                                    <span class="tag is-success is-dark">
                                        Ativo
                                    </span>
                                <?php else: ?>
                                    <span class="tag is-dark">
                                        Inativo
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="is-flex is-justify-content-center">
                                    <form method="POST" action="arbitros.php"
                                        onsubmit="return confirm('Deseja realmente <?= $ativo ? 'desativar' : 'reativar' ?> o acesso deste árbitro?');">
                                        <input type="hidden" name="csrf_token" value="<?= escaparArbitros($csrf) ?>">

                                        <input type="hidden" name="acao" value="alterar_status">

                                        <input type="hidden" name="arbitro_id" value="<?= (int) $arbitro['id'] ?>">

                                        <button
                                            class="button is-small <?= $ativo ? 'is-danger is-dark' : 'is-success is-dark' ?>"
                                            type="submit">
                                            <?= $ativo ? 'Desativar acesso' : 'Reativar acesso' ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>


<script>
    function copiarCredenciaisArbitro() {
        const campo = document.getElementById('credenciais-arbitro');

        if (!campo) {
            return;
        }

        campo.select();
        campo.setSelectionRange(0, 99999);

        navigator.clipboard.writeText(campo.value)
            .then(() => {
                alert('Credenciais copiadas!');
            })
            .catch(() => {
                document.execCommand('copy');
                alert('Credenciais copiadas!');
            });
    }
</script>

<?php

require_once __DIR__ . '/../../includes/admin/footer.php';

?>
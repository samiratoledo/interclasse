<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

exigirPerfil('representante');

$usuarioSessao = $_SESSION['usuario'] ?? [];

if (!is_array($usuarioSessao)) {
    $usuarioSessao = [];
}

$turmaId = (int) ($usuarioSessao['turma_id'] ?? 0);
$usuarioId = (int) ($usuarioSessao['id'] ?? 0);
$erro = '';
$sucesso = is_string($_GET['sucesso'] ?? null) ? $_GET['sucesso'] : '';
$atletas = [];
$nomeTurma = 'Sua turma';
$atletaEdicao = null;

function escaparAtletasRepresentante(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

if ($turmaId <= 0 || $usuarioId <= 0) {
    http_response_code(403);
    exit('Não foi possível identificar sua conta e sua turma. Entre novamente no sistema.');
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validarTokenCsrf($_POST['token_csrf'] ?? null)) {
            throw new RuntimeException(
                'Formulário expirado ou inválido. Atualize a página e tente novamente.'
            );
        }

        $acao = is_string($_POST['acao'] ?? null) ? $_POST['acao'] : '';

        /*
         * CADASTRAR OU EDITAR ATLETA
         */
        if ($acao === 'cadastrar' || $acao === 'editar') {
            $nome = trim((string) ($_POST['nome'] ?? ''));
            $numeroTexto = trim((string) ($_POST['numero_camisa'] ?? ''));
            $sexo = is_string($_POST['sexo'] ?? null) ? $_POST['sexo'] : '';

            $atletaId = $acao === 'editar'
                ? (int) ($_POST['atleta_id'] ?? 0)
                : 0;

            if ($nome === '' || mb_strlen($nome, 'UTF-8') > 120) {
                throw new RuntimeException(
                    'Informe o nome do atleta, com até 120 caracteres.'
                );
            }

            if (!in_array($sexo, ['masculino', 'feminino'], true)) {
                throw new RuntimeException('Selecione o gênero do atleta.');
            }

            $numeroCamisa = null;

            if ($numeroTexto !== '') {
                $numeroValidado = filter_var(
                    $numeroTexto,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 0, 'max_range' => 999]]
                );

                if ($numeroValidado === false) {
                    throw new RuntimeException(
                        'O número da camisa deve ser um número inteiro entre 0 e 999.'
                    );
                }

                $numeroCamisa = $numeroValidado;
            }

            if ($acao === 'editar' && $atletaId <= 0) {
                throw new RuntimeException('Atleta inválido para edição.');
            }

            $consultaTurma = $pdo->prepare(
                'SELECT id FROM turmas WHERE id = :id AND ativa = 1'
            );

            $consultaTurma->execute(['id' => $turmaId]);

            if (!$consultaTurma->fetch()) {
                throw new RuntimeException(
                    'Sua turma não está ativa. Procure a administração.'
                );
            }

            /*
             * Confere se o atleta que está sendo editado
             * pertence à turma do representante.
             */
            if ($acao === 'editar') {
                $consultaAtleta = $pdo->prepare(
                    'SELECT id
                     FROM atletas
                     WHERE id = :id
                       AND turma_id = :turma_id
                       AND ativo = 1'
                );

                $consultaAtleta->execute([
                    'id' => $atletaId,
                    'turma_id' => $turmaId
                ]);

                if (!$consultaAtleta->fetch()) {
                    throw new RuntimeException(
                        'Atleta não encontrado na sua turma.'
                    );
                }
            }

            /*
             * Confere se o número da camisa já está sendo utilizado.
             */
            if ($numeroCamisa !== null) {
                $sqlNumero = 'SELECT id
                              FROM atletas
                              WHERE turma_id = :turma_id
                                AND numero_camisa = :numero_camisa
                                AND ativo = 1';

                $parametrosNumero = [
                    'turma_id' => $turmaId,
                    'numero_camisa' => $numeroCamisa
                ];

                if ($acao === 'editar') {
                    $sqlNumero .= ' AND id <> :atleta_id';
                    $parametrosNumero['atleta_id'] = $atletaId;
                }

                $consultaNumero = $pdo->prepare($sqlNumero);
                $consultaNumero->execute($parametrosNumero);

                if ($consultaNumero->fetch()) {
                    throw new RuntimeException(
                        'Esse número de camisa já está sendo usado por outro atleta da turma.'
                    );
                }
            }

            /*
             * CADASTRO
             */
            if ($acao === 'cadastrar') {
                $cadastro = $pdo->prepare(
                    'INSERT INTO atletas
                        (
                            turma_id,
                            cadastrado_por,
                            nome,
                            sexo,
                            numero_camisa,
                            ativo,
                            status_aprovacao
                        )
                     VALUES
                        (
                            :turma_id,
                            :cadastrado_por,
                            :nome,
                            :sexo,
                            :numero_camisa,
                            1,
                            \'pendente\'
                        )'
                );

                $cadastro->execute([
                    'turma_id' => $turmaId,
                    'cadastrado_por' => $usuarioId,
                    'nome' => $nome,
                    'sexo' => $sexo,
                    'numero_camisa' => $numeroCamisa
                ]);

                header(
                    'Location: atletas.php?sucesso=' .
                    urlencode('Atleta cadastrado e enviado para aprovação.')
                );
                exit;
            }

            /*
             * EDIÇÃO
             *
             * Ao editar, o cadastro volta para pendente,
             * precisando ser aprovado novamente pela administração.
             */
            $edicao = $pdo->prepare(
                'UPDATE atletas
                 SET nome = :nome,
                     sexo = :sexo,
                     numero_camisa = :numero_camisa,
                     status_aprovacao = \'pendente\',
                     aprovado_por = NULL,
                     aprovado_em = NULL,
                     observacao_aprovacao = NULL
                 WHERE id = :id
                   AND turma_id = :turma_id
                   AND ativo = 1'
            );

            $edicao->execute([
                'nome' => $nome,
                'sexo' => $sexo,
                'numero_camisa' => $numeroCamisa,
                'id' => $atletaId,
                'turma_id' => $turmaId
            ]);

            header(
                'Location: atletas.php?sucesso=' .
                urlencode('Atleta atualizado e enviado novamente para aprovação.')
            );
            exit;
        }

        /*
         * EXCLUSÃO LÓGICA
         *
         * Preserva os vínculos com partidas e eventos anteriores.
         */
        if ($acao === 'excluir') {
            $atletaId = (int) ($_POST['atleta_id'] ?? 0);

            if ($atletaId <= 0) {
                throw new RuntimeException('Atleta inválido para exclusão.');
            }

            $exclusao = $pdo->prepare(
                'UPDATE atletas
                 SET ativo = 0,
                     numero_camisa = NULL
                 WHERE id = :id
                   AND turma_id = :turma_id
                   AND ativo = 1'
            );

            $exclusao->execute([
                'id' => $atletaId,
                'turma_id' => $turmaId
            ]);

            if ($exclusao->rowCount() === 0) {
                throw new RuntimeException(
                    'Atleta não encontrado ou já excluído.'
                );
            }

            header(
                'Location: atletas.php?sucesso=' .
                urlencode('Atleta removido da lista.')
            );
            exit;
        }

        throw new RuntimeException('Ação inválida.');
    }

    /*
     * CARREGAR ATLETA PARA EDIÇÃO
     */
    if (isset($_GET['editar'])) {
        $idEdicao = filter_var($_GET['editar'], FILTER_VALIDATE_INT);

        if ($idEdicao === false || $idEdicao === null || $idEdicao <= 0) {
            throw new RuntimeException('Identificador de atleta inválido.');
        }

        $consultaEdicao = $pdo->prepare(
            'SELECT id, nome, sexo, numero_camisa
             FROM atletas
             WHERE id = :id
               AND turma_id = :turma_id
               AND ativo = 1'
        );

        $consultaEdicao->execute([
            'id' => $idEdicao,
            'turma_id' => $turmaId
        ]);

        $atletaEdicao = $consultaEdicao->fetch(PDO::FETCH_ASSOC);

        if (!$atletaEdicao) {
            throw new RuntimeException(
                'Atleta não encontrado na sua turma.'
            );
        }
    }

    /*
     * LISTAR ATLETAS DA TURMA
     */
    $consultaAtletas = $pdo->prepare(
        'SELECT
            id,
            nome,
            sexo,
            numero_camisa,
            ativo,
            status_aprovacao,
            observacao_aprovacao,
            criado_em
         FROM atletas
         WHERE turma_id = :turma_id
           AND ativo = 1
         ORDER BY criado_em DESC, nome ASC'
    );

    $consultaAtletas->execute(['turma_id' => $turmaId]);

    $atletas = $consultaAtletas->fetchAll(PDO::FETCH_ASSOC);

    /*
     * NOME DA TURMA
     */
    $consultaTurma = $pdo->prepare(
        'SELECT nome FROM turmas WHERE id = :id'
    );

    $consultaTurma->execute(['id' => $turmaId]);

    $nomeTurma = $consultaTurma->fetchColumn() ?: 'Turma não identificada';

} catch (RuntimeException $e) {
    $erro = $e->getMessage();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $erro = 'Não foi possível concluir a operação. Verifique os dados e tente novamente.';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Meus atletas | Interclasse ETEC</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css">
    <link rel="stylesheet" href="../assets/style.css">
</head>

<body>

    <nav class="navbar is-dark" role="navigation" aria-label="Navegação principal">
        <div class="navbar-brand">
            <a class="navbar-item has-text-weight-bold" href="index.php">
                Interclasse ETEC
            </a>
        </div>

        <div class="navbar-menu is-active">
            <div class="navbar-start">
                <a class="navbar-item" href="index.php">Painel</a>
                <a class="navbar-item is-active" href="atletas.php">Meus atletas</a>
            </div>

            <div class="navbar-end">
                <div class="navbar-item">
                    <form action="../logout.php" method="POST">
                        <input
                            type="hidden"
                            name="token_csrf"
                            value="<?= escaparAtletasRepresentante(gerarTokenCsrf()) ?>"
                        >
                        <button class="button is-light is-small" type="submit">
                            Sair
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <section class="section">
        <div class="container">

            <div class="level is-align-items-flex-start">
                <div class="level-left">
                    <div>
                        <h1 class="title">Meus atletas</h1>
                        <p class="subtitle">
                            Turma: <?= escaparAtletasRepresentante($nomeTurma) ?>
                        </p>
                    </div>
                </div>

                <div class="level-right">
                    <a class="button is-light" href="index.php">
                        Voltar ao painel
                    </a>
                </div>
            </div>

            <?php if ($erro !== ''): ?>
                <div class="notification is-danger is-light">
                    <?= escaparAtletasRepresentante($erro) ?>
                </div>
            <?php endif; ?>

            <?php if ($sucesso !== ''): ?>
                <div class="notification is-success is-light">
                    <?= escaparAtletasRepresentante($sucesso) ?>
                </div>
            <?php endif; ?>

            <?php if ($atletaEdicao): ?>

                <!-- FORMULÁRIO DE EDIÇÃO -->

                <div class="box">
                    <h2 class="title is-4">Editar atleta</h2>

                    <p class="mb-4">
                        Após salvar, o cadastro voltará para a fila de aprovação da administração.
                    </p>

                    <form method="POST" action="atletas.php">

                        <input
                            type="hidden"
                            name="token_csrf"
                            value="<?= escaparAtletasRepresentante(gerarTokenCsrf()) ?>"
                        >

                        <input type="hidden" name="acao" value="editar">

                        <input
                            type="hidden"
                            name="atleta_id"
                            value="<?= (int) $atletaEdicao['id'] ?>"
                        >

                        <div class="field">
                            <label class="label" for="nome_edicao">
                                Nome completo do atleta
                            </label>

                            <div class="control">
                                <input
                                    class="input"
                                    type="text"
                                    id="nome_edicao"
                                    name="nome"
                                    maxlength="120"
                                    value="<?= escaparAtletasRepresentante($atletaEdicao['nome']) ?>"
                                    required
                                >
                            </div>
                        </div>

                        <div class="field">
                            <label class="label" for="sexo_edicao">
                                Gênero do atleta *
                            </label>

                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select id="sexo_edicao" name="sexo" required>
                                        <option value="">Selecione o gênero</option>

                                        <option
                                            value="masculino"
                                            <?= ($atletaEdicao['sexo'] ?? '') === 'masculino' ? 'selected' : '' ?>
                                        >
                                            Masculino
                                        </option>

                                        <option
                                            value="feminino"
                                            <?= ($atletaEdicao['sexo'] ?? '') === 'feminino' ? 'selected' : '' ?>
                                        >
                                            Feminino
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="field">
                            <label class="label" for="numero_edicao">
                                Número da camisa (opcional)
                            </label>

                            <div class="control">
                                <input
                                    class="input"
                                    type="number"
                                    id="numero_edicao"
                                    name="numero_camisa"
                                    min="0"
                                    max="999"
                                    value="<?= escaparAtletasRepresentante($atletaEdicao['numero_camisa'] ?? '') ?>"
                                >
                            </div>
                        </div>

                        <button class="button is-primary" type="submit">
                            Salvar alterações
                        </button>

                        <a class="button is-light" href="atletas.php">
                            Cancelar
                        </a>

                    </form>
                </div>

            <?php else: ?>

                <!-- FORMULÁRIO DE CADASTRO -->

                <div class="box">
                    <h2 class="title is-4">Cadastrar atleta</h2>

                    <p class="mb-4">
                        O cadastro será enviado à administração para aprovação.
                    </p>

                    <form method="POST" action="atletas.php">

                        <input
                            type="hidden"
                            name="token_csrf"
                            value="<?= escaparAtletasRepresentante(gerarTokenCsrf()) ?>"
                        >

                        <input type="hidden" name="acao" value="cadastrar">

                        <div class="field">
                            <label class="label" for="nome">
                                Nome completo do atleta
                            </label>

                            <div class="control">
                                <input
                                    class="input"
                                    type="text"
                                    id="nome"
                                    name="nome"
                                    maxlength="120"
                                    required
                                >
                            </div>
                        </div>

                        <div class="field">
                            <label class="label" for="sexo">
                                Gênero do atleta *
                            </label>

                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select id="sexo" name="sexo" required>
                                        <option value="">Selecione o gênero</option>
                                        <option value="masculino">Masculino</option>
                                        <option value="feminino">Feminino</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="field">
                            <label class="label" for="numero_camisa">
                                Número da camisa (opcional)
                            </label>

                            <div class="control">
                                <input
                                    class="input"
                                    type="number"
                                    id="numero_camisa"
                                    name="numero_camisa"
                                    min="0"
                                    max="999"
                                >
                            </div>

                            <p class="help">
                                Não informe um número que já esteja sendo usado por outro atleta da turma.
                            </p>
                        </div>

                        <button class="button is-primary" type="submit">
                            Cadastrar atleta
                        </button>

                    </form>
                </div>

            <?php endif; ?>

            <!-- LISTAGEM DE ATLETAS -->

            <div class="box">
                <h2 class="title is-4">Atletas cadastrados</h2>

                <?php if ($atletas === []): ?>

                    <div class="notification is-info is-light">
                        Sua turma ainda não possui atletas cadastrados.
                    </div>

                <?php else: ?>

                    <div class="table-container">
                        <table class="table is-fullwidth is-striped is-hoverable">

                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>Gênero</th>
                                    <th>Camisa</th>
                                    <th>Aprovação</th>
                                    <th>Observação da administração</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($atletas as $atleta): ?>

                                    <tr>
                                        <td>
                                            <?= escaparAtletasRepresentante($atleta['nome']) ?>
                                        </td>

                                        <td>
                                            <?php
                                            if ($atleta['sexo'] === 'masculino') {
                                                echo 'Masculino';
                                            } elseif ($atleta['sexo'] === 'feminino') {
                                                echo 'Feminino';
                                            } else {
                                                echo 'Não informado';
                                            }
                                            ?>
                                        </td>

                                        <td>
                                            <?= $atleta['numero_camisa'] === null
                                                ? '—'
                                                : (int) $atleta['numero_camisa'] ?>
                                        </td>

                                        <td>
                                            <?php if ($atleta['status_aprovacao'] === 'aprovado'): ?>

                                                <span class="tag is-success is-light">
                                                    Aprovado
                                                </span>

                                            <?php elseif ($atleta['status_aprovacao'] === 'rejeitado'): ?>

                                                <span class="tag is-danger is-light">
                                                    Rejeitado
                                                </span>

                                            <?php else: ?>

                                                <span class="tag is-warning is-light">
                                                    Pendente
                                                </span>

                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?= escaparAtletasRepresentante(
                                                $atleta['observacao_aprovacao'] ?? '—'
                                            ) ?>
                                        </td>

                                        <td>
                                            <div class="buttons are-small">

                                                <a
                                                    class="button is-info is-light"
                                                    href="atletas.php?editar=<?= (int) $atleta['id'] ?>"
                                                >
                                                    Editar
                                                </a>

                                                <form
                                                    method="POST"
                                                    action="atletas.php"
                                                    onsubmit="return confirm('Tem certeza de que deseja remover este atleta da lista?');"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="token_csrf"
                                                        value="<?= escaparAtletasRepresentante(gerarTokenCsrf()) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="acao"
                                                        value="excluir"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="atleta_id"
                                                        value="<?= (int) $atleta['id'] ?>"
                                                    >

                                                    <button
                                                        class="button is-danger is-light"
                                                        type="submit"
                                                    >
                                                        Excluir
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

        </div>
    </section>

</body>
</html>
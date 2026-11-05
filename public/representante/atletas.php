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

<?php

require_once __DIR__ . '/../../includes/representante/header.php';

?>

<style>
    .atletas-page {
        padding-bottom: 25px;
    }

    .atletas-intro {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 25px;
    }

    .atletas-intro .title {
        margin-bottom: 6px;
    }

    .atletas-card {
        padding: 25px;
        margin-bottom: 22px;
    }

    .atletas-card-title {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
    }

    .atletas-card-title .icon {
        color: #c99400;
    }

    .atletas-description {
        color: #746b53;
        font-size: 13px;
        margin-bottom: 22px;
    }

    .atletas-form {
        max-width: 750px;
    }

    .atletas-form .label {
        color: #40391f;
        font-size: 13px;
    }

    .atletas-form .input,
    .atletas-form .select select {
        border-radius: 8px;
    }

    .atletas-form .input:focus,
    .atletas-form .select select:focus {
        border-color: #e3ad12;
        box-shadow: 0 0 0 0.125em #e3ad1230;
    }

    .atletas-table {
        overflow-x: auto;
    }

    .atletas-table .table {
        min-width: 850px;
    }

    .atletas-table .table th {
        color: #655629;
        font-size: 12px;
        white-space: nowrap;
    }

    .atletas-table .table td {
        vertical-align: middle;
        font-size: 13px;
    }

    .atletas-table .table tbody tr:hover {
        background: #fffaf0;
    }

    .atletas-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .atletas-actions form {
        display: inline-flex;
    }

    .atletas-empty {
        text-align: center;
        padding: 35px 15px;
        color: #746b53;
    }

    .atletas-empty .icon {
        color: #d5b34e;
        margin-bottom: 12px;
    }

    .atletas-count {
        background: #fff4cc;
        color: #665018;
        border-radius: 20px;
        padding: 5px 12px;
        font-size: 12px;
        font-weight: 700;
    }

    @media (max-width: 600px) {
        .atletas-card {
            padding: 17px;
        }

        .atletas-intro {
            align-items: flex-start;
        }

        .atletas-intro .button {
            width: 100%;
        }
    }
</style>

<section class="atletas-page">

    <!-- CABEÇALHO DA PÁGINA -->

    <div class="atletas-intro">

        <div>
            <h1 class="title is-3">Meus atletas</h1>

            <p class="subtitle is-6">
                Gerencie os alunos que representarão sua turma no Interclasse.
            </p>

            <span class="atletas-count">
                Turma: <?= escaparAtletasRepresentante($nomeTurma) ?>
            </span>
        </div>

        <a class="button is-dark" href="index.php">
            <span>Voltar ao painel</span>
        </a>

    </div>

    <!-- MENSAGENS -->

    <?php if ($erro !== ''): ?>
        <div class="notification is-danger is-dark">
            <?= escaparAtletasRepresentante($erro) ?>
        </div>
    <?php endif; ?>

    <?php if ($sucesso !== ''): ?>
        <div class="notification is-success is-light">
            <?= escaparAtletasRepresentante($sucesso) ?>
        </div>
    <?php endif; ?>

    <!-- FORMULÁRIO -->

    <div class="box atletas-card">

        <div class="atletas-card-title">
            <h2 class="title is-4 mb-0">
                <?= $atletaEdicao ? 'Editar atleta' : 'Cadastrar atleta' ?>
            </h2>
        </div>

        <p class="atletas-description">
            <?php if ($atletaEdicao): ?>
                Altere os dados do atleta. Após salvar, o cadastro voltará para aprovação da administração.
            <?php else: ?>
                Preencha os dados abaixo para adicionar um aluno à equipe da sua turma. O cadastro ficará pendente até a aprovação da administração.
            <?php endif; ?>
        </p>

        <form
            method="POST"
            action="atletas.php"
            class="atletas-form"
        >

            <input
                type="hidden"
                name="token_csrf"
                value="<?= escaparAtletasRepresentante(gerarTokenCsrf()) ?>"
            >

            <input
                type="hidden"
                name="acao"
                value="<?= $atletaEdicao ? 'editar' : 'cadastrar' ?>"
            >

            <?php if ($atletaEdicao): ?>
                <input
                    type="hidden"
                    name="atleta_id"
                    value="<?= (int) $atletaEdicao['id'] ?>"
                >
            <?php endif; ?>

            <div class="field">

                <label class="label" for="nome">
                    Nome completo do atleta *
                </label>

                <div class="control">
                    <input
                        class="input"
                        type="text"
                        id="nome"
                        name="nome"
                        maxlength="120"
                        placeholder="Digite o nome completo"
                        value="<?= escaparAtletasRepresentante($atletaEdicao['nome'] ?? '') ?>"
                        required
                    >
                </div>

            </div>

            <div class="columns">

                <div class="column">

                    <div class="field">

                        <label class="label" for="sexo">
                            Gênero do atleta *
                        </label>

                        <div class="control">
                            <div class="select is-fullwidth">
                                <select id="sexo" name="sexo" required>

                                    <option value="">
                                        Selecione
                                    </option>

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

                </div>

                <div class="column">

                    <div class="field">

                        <label class="label" for="numero_camisa">
                            Número da camisa
                        </label>

                        <div class="control">
                            <input
                                class="input"
                                type="number"
                                id="numero_camisa"
                                name="numero_camisa"
                                min="0"
                                max="999"
                                placeholder="Opcional"
                                value="<?= escaparAtletasRepresentante($atletaEdicao['numero_camisa'] ?? '') ?>"
                            >
                        </div>

                        <p class="help">
                            Não pode repetir na mesma turma.
                        </p>

                    </div>

                </div>

            </div>

            <div class="field is-grouped is-grouped-multiline mt-5">

                <div class="control">
                    <button class="button is-primary" type="submit">
                        <span>
                            <?= $atletaEdicao ? 'Salvar alterações' : 'Cadastrar atleta' ?>
                        </span>
                    </button>
                </div>

                <?php if ($atletaEdicao): ?>
                    <div class="control">
                        <a class="button is-dark" href="atletas.php">
                            Cancelar edição
                        </a>
                    </div>
                <?php endif; ?>

            </div>

        </form>

    </div>

    <!-- LISTAGEM -->

    <div class="box atletas-card">

        <div class="atletas-intro">

            <div>
                <h2 class="title is-4 mb-2">
                    Atletas cadastrados
                </h2>

                <p class="atletas-description mb-0">
                    Consulte a situação dos cadastros da sua turma.
                </p>
            </div>

            <span class="atletas-count">
                <?= count($atletas) ?> atleta(s)
            </span>

        </div>

        <?php if ($atletas === []): ?>

            <div class="atletas-empty">

                <h3 class="title is-5">
                    Nenhum atleta cadastrado
                </h3>

                <p>
                    Utilize o formulário acima para cadastrar o primeiro atleta da sua turma.
                </p>

            </div>

        <?php else: ?>

            <div class="atletas-table">

                <table class="table is-fullwidth is-hoverable">

                    <thead>
                        <tr>
                            <th>Atleta</th>
                            <th>Gênero</th>
                            <th>Camisa</th>
                            <th>Situação</th>
                            <th>Observação</th>
                            <th>Ações</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($atletas as $atleta): ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= escaparAtletasRepresentante($atleta['nome']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= $atleta['sexo'] === 'masculino'
                                        ? 'Masculino'
                                        : ($atleta['sexo'] === 'feminino' ? 'Feminino' : 'Não informado') ?>
                                </td>

                                <td>
                                    <?php if ($atleta['numero_camisa'] !== null): ?>
                                        <span class="tag is-dark">
                                            #<?= (int) $atleta['numero_camisa'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="has-text-grey">—</span>
                                    <?php endif; ?>
                                </td>

                                <td>

                                    <?php if ($atleta['status_aprovacao'] === 'aprovado'): ?>

                                        <span class="tag is-success is-dark">
                                            Aprovado
                                        </span>

                                    <?php elseif ($atleta['status_aprovacao'] === 'rejeitado'): ?>

                                        <span class="tag is-danger is-dark">
                                            Rejeitado
                                        </span>

                                    <?php else: ?>

                                        <span class="tag is-warning is-dark">
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

                                    <div class="atletas-actions">

                                        <a
                                            class="button is-small is-info is-dark"
                                            href="atletas.php?editar=<?= (int) $atleta['id'] ?>"
                                            title="Editar atleta"
                                        >
                                            <span>Editar</span>
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
                                                class="button is-small is-danger is-dark"
                                                type="submit"
                                                title="Excluir atleta"
                                            >
                                                <span>Excluir</span>
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

</section>

<?php
require_once __DIR__ . '/../../includes/representante/footer.php';
?>

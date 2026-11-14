<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

exigirPerfil('admin');

$admin = usuarioAtual();
$erro = '';
$sucesso = '';

$pendentes = [];
$analisados = [];

function escaparAtleta(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

if (isset($_SESSION['mensagem_aprovacao'])) {
    $sucesso = (string) $_SESSION['mensagem_aprovacao'];
    unset($_SESSION['mensagem_aprovacao']);
}

// Processamento da aprovação ou rejeição.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token_csrf'] ?? null;
    $atletaId = filter_input(INPUT_POST, 'atleta_id', FILTER_VALIDATE_INT);
    $acao = $_POST['acao'] ?? '';
    $observacao = trim((string) ($_POST['observacao_aprovacao'] ?? ''));

    if (!validarTokenCsrf(is_string($token) ? $token : null)) {
        $erro = 'Sessão expirada ou formulário inválido. Atualize a página.';
    } elseif (
        !$atletaId ||
        !in_array($acao, ['aprovar', 'rejeitar'], true)
    ) {
        $erro = 'Solicitação inválida.';
    } elseif (strlen($observacao) > 10000) {
        $erro = 'A observação deve ter no máximo 10.000 bytes.';
    } else {
        try {
            $status = $acao === 'aprovar' ? 'aprovado' : 'rejeitado';

            $sql = "
                UPDATE atletas
                SET status_aprovacao = :status,
                    observacao_aprovacao = :observacao,
                    aprovado_por = :admin_id,
                    aprovado_em = NOW()
                WHERE id = :atleta_id
                  AND ativo = 1
                  AND status_aprovacao = 'pendente'
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                'status' => $status,
                'observacao' => $observacao !== '' ? $observacao : null,
                'admin_id' => (int) $admin['id'],
                'atleta_id' => $atletaId,
            ]);

            if ($stmt->rowCount() === 1) {
                $_SESSION['mensagem_aprovacao'] =
                    $status === 'aprovado'
                    ? 'Atleta aprovado com sucesso!'
                    : 'Atleta rejeitado.';

                unset($_SESSION['token_csrf']);

                header('Location: aprovar-atletas.php');
                exit;
            }

            $erro = 'Atleta não encontrado, já analisado ou inativo.';

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $erro = 'Não foi possível atualizar a aprovação. Verifique a coluna observacao_aprovacao.';
        }
    }
}

// Carrega atletas pendentes.
try {
    $sqlPendentes = "
        SELECT
            a.id,
            a.nome,
            a.numero_camisa,
            a.criado_em,
            t.nome AS turma_nome,
            u.nome AS representante_nome
        FROM atletas a
        INNER JOIN turmas t ON t.id = a.turma_id
        LEFT JOIN usuarios u ON u.id = a.cadastrado_por
        WHERE a.ativo = 1
          AND a.status_aprovacao = 'pendente'
        ORDER BY a.criado_em ASC, a.nome ASC
    ";

    $pendentes = $pdo->query($sqlPendentes)->fetchAll(PDO::FETCH_ASSOC);

    // Carrega atletas aprovados e rejeitados.
    $sqlAnalisados = "
    SELECT
        a.id,
        a.nome,
        a.numero_camisa,
        a.status_aprovacao,
        a.observacao_aprovacao,
        a.criado_em,
        a.aprovado_em,
        t.nome AS turma_nome,
        u.nome AS representante_nome,
        admin.nome AS administrador_nome
    FROM atletas a
    INNER JOIN turmas t ON t.id = a.turma_id
    LEFT JOIN usuarios u ON u.id = a.cadastrado_por
    LEFT JOIN usuarios admin ON admin.id = a.aprovado_por
    WHERE a.ativo = 1
      AND a.status_aprovacao IN ('aprovado', 'rejeitado')
    ORDER BY a.aprovado_em DESC, a.nome ASC
";

    $analisados = $pdo->query($sqlAnalisados)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    $erro = 'Não foi possível carregar os atletas. Verifique as colunas do banco de dados.';
}

$totalPendentes = count($pendentes);
$totalAprovados = count(array_filter(
    $analisados,
    fn($a) => $a['status_aprovacao'] === 'aprovado'
));
$totalRejeitados = count(array_filter(
    $analisados,
    fn($a) => $a['status_aprovacao'] === 'rejeitado'
));

require_once __DIR__ . '/../../includes/admin/header.php';
?>

<div class="mb-5">
    <h1 class="title is-3">Aprovação de atletas</h1>

    <p class="subtitle is-6">
        Analise os cadastros enviados pelos representantes das turmas.
    </p>
</div>

<?php if ($erro !== ''): ?>
    <div class="notification is-danger is-dark">
        <?= escaparAtleta($erro) ?>
    </div>
<?php endif; ?>

<?php if ($sucesso !== ''): ?>
    <div class="notification is-success is-dark">
        <?= escaparAtleta($sucesso) ?>
    </div>
<?php endif; ?>

<!-- Resumo -->
<div class="columns is-multiline">

    <div class="column is-4">
        <div class="box">
            <p class="heading">Aguardando análise</p>
            <p class="title is-3 has-text-warning">
                <?= $totalPendentes ?>
            </p>
        </div>
    </div>

    <div class="column is-4">
        <div class="box">
            <p class="heading">Aprovados</p>
            <p class="title is-3 has-text-success">
                <?= $totalAprovados ?>
            </p>
        </div>
    </div>

    <div class="column is-4">
        <div class="box">
            <p class="heading">Rejeitados</p>
            <p class="title is-3 has-text-danger">
                <?= $totalRejeitados ?>
            </p>
        </div>
    </div>

</div>

<!-- Atletas pendentes -->
<section class="box mb-5">

    <div class="level mb-4">
        <div class="level-left">
            <div>
                <h2 class="title is-5">Atletas pendentes</h2>
                <p class="subtitle is-6">
                    Registre uma observação antes de aprovar ou rejeitar.
                </p>
            </div>
        </div>

        <div class="level-right">
            <span class="tag is-warning is-dark is-medium">
                <?= $totalPendentes ?> pendente(s)
            </span>
        </div>
    </div>

    <?php if (empty($pendentes)): ?>

        <div class="has-text-centered py-6">
            <p class="title is-5">Tudo em dia!</p>
            <p class="has-text-grey">
                Não há atletas pendentes de aprovação.
            </p>
        </div>

    <?php else: ?>

        <div class="table-container">
            <table class="table is-fullwidth is-striped is-hoverable">

                <thead>
                    <tr>
                        <th>Atleta</th>
                        <th>Turma</th>
                        <th>Número da camisa</th>
                        <th>Cadastrado por</th>
                        <th>Data do cadastro</th>
                        <th style="min-width:300px;">Observação da administração</th>
                        <th>Ações</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($pendentes as $atleta): ?>

                        <tr>
                            <td>
                                <strong>
                                    <?= escaparAtleta($atleta['nome']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= escaparAtleta($atleta['turma_nome']) ?>
                            </td>

                            <td>
                                <?= $atleta['numero_camisa'] !== null
                                    ? escaparAtleta($atleta['numero_camisa'])
                                    : '<span class="has-text-grey">Não informado</span>' ?>
                            </td>

                            <td>
                                <?= escaparAtleta(
                                    $atleta['representante_nome'] ?? 'Não identificado'
                                ) ?>
                            </td>

                            <td>
                                <?= escaparAtleta(
                                    date('d/m/Y H:i', strtotime($atleta['criado_em']))
                                ) ?>
                            </td>

                            <td colspan="2">
                                <form method="POST" action="aprovar-atletas.php" class="is-flex is-flex-direction-column"
                                    style="gap:0.6rem;min-width:300px;">

                                    <input type="hidden" name="token_csrf" value="<?= escaparAtleta(gerarTokenCsrf()) ?>">

                                    <input type="hidden" name="atleta_id" value="<?= (int) $atleta['id'] ?>">

                                    <textarea class="textarea" name="observacao_aprovacao" rows="2" maxlength="10000"
                                        placeholder="Escreva uma observação para o representante..."></textarea>

                                    <div class="buttons mb-0">
                                        <button class="button is-success is-small" type="submit" name="acao" value="aprovar">
                                            Aprovar
                                        </button>

                                        <button class="button is-danger is-dark is-small" type="submit" name="acao"
                                            value="rejeitar">
                                            Rejeitar
                                        </button>
                                    </div>

                                </form>
                            </td>
                        </tr>

                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>

    <?php endif; ?>

</section>

<!-- Atletas já analisados -->
<section class="box">

    <div class="level mb-4">
        <div class="level-left">
            <div>
                <h2 class="title is-5">Atletas já analisados</h2>
                <p class="subtitle is-6">
                    Histórico de aprovações e rejeições.
                </p>
            </div>
        </div>

        <div class="level-right">
            <span class="tag is-info is-dark is-medium">
                <?= count($analisados) ?> analisado(s)
            </span>
        </div>
    </div>

    <?php if (empty($analisados)): ?>

        <div class="has-text-centered py-6">
            <p class="has-text-grey">
                Nenhum atleta foi analisado ainda.
            </p>
        </div>

    <?php else: ?>

        <div class="table-container">
            <table class="table is-fullwidth is-striped is-hoverable">

                <thead>
                    <tr>
                        <th>Atleta</th>
                        <th>Turma</th>
                        <th>Número da camisa</th>
                        <th>Status</th>
                        <th>Observação da administração</th>
                        <th>Data da análise</th>
                        <th>Analisado por</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($analisados as $atleta): ?>

                        <tr>
                            <td>
                                <strong>
                                    <?= escaparAtleta($atleta['nome']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= escaparAtleta($atleta['turma_nome']) ?>
                            </td>

                            <td>
                                <?= $atleta['numero_camisa'] !== null
                                    ? escaparAtleta($atleta['numero_camisa'])
                                    : '<span class="has-text-grey">Não informado</span>' ?>
                            </td>

                            <td>
                                <?php if ($atleta['status_aprovacao'] === 'aprovado'): ?>
                                    <span class="tag is-success is-dark">
                                        Aprovado
                                    </span>
                                <?php else: ?>
                                    <span class="tag is-danger is-dark">
                                        Rejeitado
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td style="min-width:250px;max-width:400px;white-space:normal;overflow-wrap:anywhere;">
                                <?php if (!empty($atleta['observacao_aprovacao'])): ?>
                                    <?= nl2br(escaparAtleta($atleta['observacao_aprovacao'])) ?>
                                <?php else: ?>
                                    <span class="has-text-grey">
                                        Nenhuma observação registrada.
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!empty($atleta['aprovado_em'])): ?>
                                    <?= escaparAtleta(
                                        date('d/m/Y H:i', strtotime($atleta['aprovado_em']))
                                    ) ?>
                                <?php else: ?>
                                    <span class="has-text-grey">Não informado</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= escaparAtleta(
                                    $atleta['administrador_nome'] ?? 'Administrador não identificado'
                                ) ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>

    <?php endif; ?>

</section>

<a class="button is-light mt-5" href="index.php">
    Voltar ao painel
</a>

<?php
require_once __DIR__ . '/../../includes/admin/footer.php';
?>
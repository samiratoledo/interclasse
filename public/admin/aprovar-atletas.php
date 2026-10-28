<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

exigirPerfil('admin');

$admin = usuarioAtual();
$erro = '';
$sucesso = '';

$pendentes = [];
$totalPendentes = 0;

function escaparAtleta(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

if (isset($_SESSION['mensagem_aprovacao'])) {
    $sucesso = (string) $_SESSION['mensagem_aprovacao'];
    unset($_SESSION['mensagem_aprovacao']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token_csrf'] ?? null;
    $atletaId = filter_input(INPUT_POST, 'atleta_id', FILTER_VALIDATE_INT);
    $acao = $_POST['acao'] ?? '';

    if (!validarTokenCsrf(is_string($token) ? $token : null)) {
        $erro = 'Sessão expirada ou formulário inválido. Atualize a página.';
    } elseif (
        !$atletaId ||
        !in_array($acao, ['aprovar', 'rejeitar'], true)
    ) {
        $erro = 'Solicitação inválida.';
    } else {
        try {
            $status = $acao === 'aprovar' ? 'aprovado' : 'rejeitado';

            $sql = "UPDATE atletas
                    SET status_aprovacao = :status,
                        aprovado_por = :admin_id,
                        aprovado_em = NOW()
                    WHERE id = :atleta_id
                      AND ativo = 1
                      AND status_aprovacao = 'pendente'";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                'status' => $status,
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
            $erro = 'Não foi possível atualizar a aprovação.';
        }
    }
}

$filtroPeriodo = 'todos';
$filtroStatus = 'todas';

try {
    $sql = "
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

    $pendentes = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $totalPendentes = count($pendentes);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $erro = 'Não foi possível carregar os atletas pendentes.';
}

require_once __DIR__ . '/../../includes/admin/header.php';
?>

<div class="mb-5">
    <h1 class="title is-3">Aprovação de atletas</h1>

    <p class="subtitle is-6">
        Analise os cadastros enviados pelos representantes das turmas.
    </p>
</div>

<?php if ($erro !== ''): ?>
    <div class="notification is-danger is-light">
        <?= escaparAtleta($erro) ?>
    </div>
<?php endif; ?>

<?php if ($sucesso !== ''): ?>
    <div class="notification is-success is-light">
        <?= escaparAtleta($sucesso) ?>
    </div>
<?php endif; ?>

<div class="box mb-5">
    <p class="heading">Atletas aguardando análise</p>
    <p class="title is-3"><?= $totalPendentes ?></p>
</div>

<div class="box">
    <div class="level mb-4">
        <div class="level-left">
            <div>
                <h2 class="title is-5">Atletas pendentes</h2>
                <p class="subtitle is-6">
                    Confira os dados antes de aprovar ou rejeitar.
                </p>
            </div>
        </div>

        <div class="level-right">
            <span class="tag is-warning is-light is-medium">
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
                        <th class="has-text-centered">Ações</th>
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
                                <?php if ($atleta['numero_camisa'] !== null): ?>
                                    <?= escaparAtleta($atleta['numero_camisa']) ?>
                                <?php else: ?>
                                    <span class="has-text-grey">
                                        Não informado
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= escaparAtleta(
                                    $atleta['representante_nome']
                                    ?? 'Não identificado'
                                ) ?>
                            </td>

                            <td>
                                <?= escaparAtleta(
                                    date(
                                        'd/m/Y H:i',
                                        strtotime($atleta['criado_em'])
                                    )
                                ) ?>
                            </td>

                            <td>
                                <div
                                    class="is-flex is-justify-content-center is-align-items-center"
                                    style="gap: 0.5rem;">

                                    <form method="POST" action="">
                                        <input
                                            type="hidden"
                                            name="token_csrf"
                                            value="<?= escaparAtleta(gerarTokenCsrf()) ?>">

                                        <input
                                            type="hidden"
                                            name="atleta_id"
                                            value="<?= (int) $atleta['id'] ?>">

                                        <input
                                            type="hidden"
                                            name="acao"
                                            value="aprovar">

                                        <button
                                            class="button is-success is-small"
                                            type="submit">
                                            Aprovar
                                        </button>
                                    </form>

                                    <form method="POST" action="">
                                        <input
                                            type="hidden"
                                            name="token_csrf"
                                            value="<?= escaparAtleta(gerarTokenCsrf()) ?>">

                                        <input
                                            type="hidden"
                                            name="atleta_id"
                                            value="<?= (int) $atleta['id'] ?>">

                                        <input
                                            type="hidden"
                                            name="acao"
                                            value="rejeitar">

                                        <button
                                            class="button is-danger is-light is-small"
                                            type="submit">
                                            Rejeitar
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

<div class="buttons mt-5">
    <a class="button is-light" href="index.php">
        <span>Voltar ao painel</span>
    </a>
</div>

<?php
require_once __DIR__ . '/../../includes/admin/footer.php';
?>
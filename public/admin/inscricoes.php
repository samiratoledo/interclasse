<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

exigirPerfil('admin');

$admin = usuarioAtual();
$erro = '';
$sucesso = '';

function escaparInscricaoAdmin(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

if (isset($_SESSION['mensagem_inscricao_admin'])) {
    $sucesso = (string) $_SESSION['mensagem_inscricao_admin'];
    unset($_SESSION['mensagem_inscricao_admin']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token_csrf'] ?? null;
    $id = filter_input(INPUT_POST, 'inscricao_id', FILTER_VALIDATE_INT);
    $acao = $_POST['acao'] ?? '';

    if (!validarTokenCsrf(is_string($token) ? $token : null)) {
        $erro = 'Formulário expirado ou inválido. Atualize a página.';
    } elseif (!$id || !in_array($acao, ['aprovar', 'rejeitar', 'salvar_observacao'], true)) {
        $erro = 'Solicitação inválida.';
    } else {
        try {
            if ($acao === 'salvar_observacao') {
                $observacaoAdmin = trim((string) ($_POST['observacao_admin'] ?? ''));

                if (strlen($observacaoAdmin) > 10000) {
                    throw new RuntimeException('A observação deve ter no máximo 10.000 bytes.');
                }

                $verificar = $pdo->prepare(
                    'SELECT id FROM inscricoes WHERE id = :id'
                );
                $verificar->execute(['id' => $id]);

                if (!$verificar->fetchColumn()) {
                    throw new RuntimeException('Inscrição não encontrada.');
                }

                $salvar = $pdo->prepare(
                    'UPDATE inscricoes
                     SET observacao_admin = :observacao
                     WHERE id = :id'
                );

                $salvar->execute([
                    'observacao' => $observacaoAdmin !== '' ? $observacaoAdmin : null,
                    'id' => $id,
                ]);

                $_SESSION['mensagem_inscricao_admin'] =
                    'Observação salva com sucesso! O representante poderá consultá-la no perfil dele.';

                header('Location: inscricoes.php');
                exit;
            }

            $novoStatus = $acao === 'aprovar' ? 'aprovada' : 'rejeitada';

            $pdo->beginTransaction();

            $consulta = $pdo->prepare(
                'SELECT id, status
                 FROM inscricoes
                 WHERE id = :id
                 FOR UPDATE'
            );
            $consulta->execute(['id' => $id]);
            $inscricao = $consulta->fetch(PDO::FETCH_ASSOC);

            if (!$inscricao || $inscricao['status'] !== 'pendente') {
                throw new RuntimeException('Inscrição não encontrada ou já analisada.');
            }

            if ($novoStatus === 'aprovada') {
                $validar = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM inscricao_atletas ia
                     INNER JOIN atletas a ON a.id = ia.atleta_id
                     WHERE ia.inscricao_id = :id
                       AND a.ativo = 1
                       AND a.status_aprovacao = 'aprovado'"
                );
                $validar->execute(['id' => $id]);
                $quantidadeValida = (int) $validar->fetchColumn();

                $totalInscritos = $pdo->prepare(
                    'SELECT COUNT(*)
                     FROM inscricao_atletas
                     WHERE inscricao_id = :id'
                );
                $totalInscritos->execute(['id' => $id]);
                $total = (int) $totalInscritos->fetchColumn();

                if ($total === 0 || $total !== $quantidadeValida) {
                    throw new RuntimeException(
                        'Não é possível aprovar: confira se todos os atletas inscritos estão aprovados e ativos.'
                    );
                }
            }

            $atualizar = $pdo->prepare(
                "UPDATE inscricoes
                 SET status = :status
                 WHERE id = :id AND status = 'pendente'"
            );
            $atualizar->execute([
                'status' => $novoStatus,
                'id' => $id,
            ]);

            if ($atualizar->rowCount() !== 1) {
                throw new RuntimeException(
                    'A inscrição foi alterada por outra operação. Atualize a página.'
                );
            }

            $pdo->commit();

            $_SESSION['mensagem_inscricao_admin'] =
                $novoStatus === 'aprovada'
                    ? 'Inscrição aprovada com sucesso!'
                    : 'Inscrição rejeitada.';

            header('Location: inscricoes.php');
            exit;

        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $erro = $e->getMessage();

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log($e->getMessage());
            $erro = 'Não foi possível processar a solicitação. Verifique se a coluna observacao_admin existe no banco.';
        }
    }
}

$inscricoes = [];

try {
    $sql = "
        SELECT
            i.id,
            i.status,
            i.observacao,
            i.observacao_admin,
            i.criado_em,
            t.nome AS turma_nome,
            m.nome AS modalidade_nome,
            GROUP_CONCAT(a.nome ORDER BY a.nome SEPARATOR ', ') AS nomes_atletas,
            COUNT(a.id) AS quantidade_atletas
        FROM inscricoes i
        INNER JOIN turmas t ON t.id = i.turma_id
        INNER JOIN modalidades m ON m.id = i.modalidade_id
        LEFT JOIN inscricao_atletas ia ON ia.inscricao_id = i.id
        LEFT JOIN atletas a ON a.id = ia.atleta_id
        GROUP BY
            i.id, i.status, i.observacao, i.observacao_admin,
            i.criado_em, t.nome, m.nome
        ORDER BY
            CASE WHEN i.status = 'pendente' THEN 0 ELSE 1 END,
            i.criado_em DESC
    ";

    $inscricoes = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    $erro = 'Não foi possível carregar as inscrições. Confira as colunas observacao e observacao_admin no banco.';
}

require_once __DIR__ . '/../../includes/admin/header.php';
?>

<div class="mb-5">
    <h1 class="title is-3">Inscrições nas modalidades</h1>

    <p class="subtitle is-6">
        Confira as equipes, analise as solicitações e envie observações aos representantes.
    </p>
</div>

<?php if ($erro !== ''): ?>
    <div class="notification is-danger is-light">
        <?= escaparInscricaoAdmin($erro) ?>
    </div>
<?php endif; ?>

<?php if ($sucesso !== ''): ?>
    <div class="notification is-success is-light">
        <?= escaparInscricaoAdmin($sucesso) ?>
    </div>
<?php endif; ?>

<section class="box">
    <?php if (empty($inscricoes)): ?>
        <p class="has-text-grey">
            Nenhuma inscrição encontrada.
        </p>
    <?php else: ?>
        <div class="table-container">
            <table class="table is-fullwidth is-striped is-hoverable">
                <thead>
                    <tr>
                        <th>Turma</th>
                        <th>Modalidade</th>
                        <th>Atletas</th>
                        <th>Quantidade</th>
                        <th>Observação do representante</th>
                        <th>Observação da administração</th>
                        <th>Solicitada em</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($inscricoes as $inscricao): ?>
                        <tr>
                            <td><?= escaparInscricaoAdmin($inscricao['turma_nome']) ?></td>

                            <td><?= escaparInscricaoAdmin($inscricao['modalidade_nome']) ?></td>

                            <td>
                                <?= escaparInscricaoAdmin(
                                    $inscricao['nomes_atletas'] ?? 'Nenhum atleta vinculado'
                                ) ?>
                            </td>

                            <td><?= (int) $inscricao['quantidade_atletas'] ?></td>

                            <td>
                                <?php if (!empty($inscricao['observacao'])): ?>
                                    <div style="min-width:220px;max-width:360px;white-space:normal;overflow-wrap:anywhere;">
                                        <?= nl2br(escaparInscricaoAdmin($inscricao['observacao'])) ?>
                                    </div>
                                <?php else: ?>
                                    <span class="has-text-grey">Nenhuma observação</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <form method="POST" action="inscricoes.php"
                                      class="is-flex is-flex-direction-column"
                                      style="gap:0.5rem;min-width:240px;">

                                    <input type="hidden" name="token_csrf"
                                           value="<?= escaparInscricaoAdmin(gerarTokenCsrf()) ?>">

                                    <input type="hidden" name="inscricao_id"
                                           value="<?= (int) $inscricao['id'] ?>">

                                    <input type="hidden" name="acao" value="salvar_observacao">

                                    <textarea class="textarea" name="observacao_admin"
                                              rows="3" maxlength="10000"
                                              placeholder="Escreva uma mensagem para o representante..."><?= escaparInscricaoAdmin($inscricao['observacao_admin'] ?? '') ?></textarea>

                                    <button class="button is-info is-small" type="submit">
                                        Salvar observação
                                    </button>
                                </form>
                            </td>

                            <td>
                                <?= escaparInscricaoAdmin(
                                    date('d/m/Y H:i', strtotime($inscricao['criado_em']))
                                ) ?>
                            </td>

                            <td>
                                <?php
                                $rotulos = [
                                    'pendente' => 'Pendente',
                                    'aprovada' => 'Aprovada',
                                    'rejeitada' => 'Rejeitada',
                                    'cancelada' => 'Cancelada',
                                ];
                                ?>

                                <span class="tag <?= $inscricao['status'] === 'aprovada'
                                    ? 'is-success'
                                    : ($inscricao['status'] === 'pendente' ? 'is-warning' : 'is-light') ?>">
                                    <?= escaparInscricaoAdmin(
                                        $rotulos[$inscricao['status']] ?? $inscricao['status']
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?php if ($inscricao['status'] === 'pendente'): ?>
                                    <div class="is-flex is-flex-direction-column" style="gap:0.5rem;">
                                        <form method="POST" action="inscricoes.php">
                                            <input type="hidden" name="token_csrf"
                                                   value="<?= escaparInscricaoAdmin(gerarTokenCsrf()) ?>">
                                            <input type="hidden" name="inscricao_id"
                                                   value="<?= (int) $inscricao['id'] ?>">
                                            <input type="hidden" name="acao" value="aprovar">

                                            <button class="button is-success is-small" type="submit">
                                                Aprovar
                                            </button>
                                        </form>

                                        <form method="POST" action="inscricoes.php">
                                            <input type="hidden" name="token_csrf"
                                                   value="<?= escaparInscricaoAdmin(gerarTokenCsrf()) ?>">
                                            <input type="hidden" name="inscricao_id"
                                                   value="<?= (int) $inscricao['id'] ?>">
                                            <input type="hidden" name="acao" value="rejeitar">

                                            <button class="button is-danger is-light is-small" type="submit">
                                                Rejeitar
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="has-text-grey">Já analisada</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<a class="button is-link is-light mt-3" href="index.php">
    Voltar ao painel
</a>

<?php
require_once __DIR__ . '/../../includes/admin/footer.php';
?>

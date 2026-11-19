<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

exigirPerfil('admin');

/*
 * Define a fase (oitavas, quartas, semifinal, final...) de cada partida.
 * Essas fases alimentam a tela pública de Chaveamento.
 */

$erro = '';
$sucesso = '';
$temFase = true;

try {
    $pdo->query('SELECT fase FROM partidas LIMIT 1');
} catch (PDOException $e) {
    $temFase = false;
}

$modalidades = $pdo->query("SELECT id, nome FROM modalidades WHERE ativa = 1 ORDER BY nome")->fetchAll();
$ids = array_map('intval', array_column($modalidades, 'id'));

$modalidadeId = filter_input(INPUT_POST, 'modalidade_id', FILTER_VALIDATE_INT)
    ?: filter_input(INPUT_GET, 'modalidade', FILTER_VALIDATE_INT)
    ?: ($ids[0] ?? 0);

if (!in_array($modalidadeId, $ids, true)) {
    $modalidadeId = $ids[0] ?? 0;
}

if ($temFase && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!validarTokenCsrf($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Sessão expirada. Atualize a página e tente novamente.');
        }

        $envio = $_POST['fase'] ?? [];
        if (!is_array($envio)) {
            throw new RuntimeException('Dados inválidos.');
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE partidas SET fase = ? WHERE id = ? AND modalidade_id = ?');

        foreach ($envio as $partidaId => $fase) {
            $partidaId = filter_var($partidaId, FILTER_VALIDATE_INT);
            $fase = (string) $fase;

            if ($partidaId === false || ($fase !== '' && !isset(FASES_PARTIDA[$fase]))) {
                throw new RuntimeException('Fase inválida.');
            }

            $stmt->execute([$fase !== '' ? $fase : null, $partidaId, $modalidadeId]);
        }

        $pdo->commit();
        $sucesso = 'Fases salvas.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof PDOException) {
            error_log('Erro ao salvar fases: ' . $e->getMessage());
            $erro = 'Não foi possível salvar as fases.';
        } else {
            $erro = $e->getMessage();
        }
    }
}

$partidas = [];
if ($temFase && $modalidadeId > 0) {
    $stmt = $pdo->prepare("
        SELECT p.id, p.fase, p.status, p.inicio_previsto,
               tc.nome AS casa_nome, tv.nome AS visitante_nome
        FROM partidas p
        INNER JOIN turmas tc ON tc.id = p.turma_casa_id
        INNER JOIN turmas tv ON tv.id = p.turma_visitante_id
        WHERE p.modalidade_id = ? AND p.status <> 'cancelada'
        ORDER BY p.inicio_previsto IS NULL, p.inicio_previsto, p.id
    ");
    $stmt->execute([$modalidadeId]);
    $partidas = $stmt->fetchAll();
}

require_once __DIR__ . '/../../includes/admin/header.php';
?>

<div class="container">
    <h1 class="title is-3">Fases das partidas</h1>
    <p class="subtitle is-6">Defina a fase de cada partida para montar o chaveamento público.</p>

    <?php if (!$temFase): ?>
        <div class="notification is-warning is-light">
            A coluna <code>fase</code> ainda não existe na tabela <code>partidas</code>.
            Recrie o banco com o <code>database/schema.sql</code> atualizado e recarregue esta página.
        </div>
    <?php else: ?>

        <?php if ($erro !== ''): ?>
            <div class="notification is-danger is-light"><?= escapar($erro) ?></div>
        <?php endif; ?>
        <?php if ($sucesso !== ''): ?>
            <div class="notification is-success is-light"><?= escapar($sucesso) ?></div>
        <?php endif; ?>

        <form method="GET" action="fases.php" class="mb-4">
            <div class="field has-addons">
                <div class="control is-expanded">
                    <div class="select is-fullwidth">
                        <select name="modalidade" onchange="this.form.submit()" aria-label="Modalidade">
                            <?php foreach ($modalidades as $m): ?>
                                <option value="<?= (int) $m['id'] ?>" <?= $modalidadeId === (int) $m['id'] ? 'selected' : '' ?>><?= escapar($m['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="control"><button class="button is-link" type="submit">Ver</button></div>
            </div>
        </form>

        <?php if (!$partidas): ?>
            <p>Esta modalidade ainda não tem partidas.</p>
        <?php else: ?>
            <form method="POST" action="fases.php?modalidade=<?= (int) $modalidadeId ?>">
                <input type="hidden" name="csrf_token" value="<?= escapar(gerarTokenCsrf()) ?>">
                <input type="hidden" name="modalidade_id" value="<?= (int) $modalidadeId ?>">

                <div class="table-container">
                    <table class="table is-fullwidth is-striped">
                        <thead>
                            <tr><th>Confronto</th><th>Data</th><th>Fase</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($partidas as $p): ?>
                                <tr>
                                    <td><?= escapar($p['casa_nome']) ?> × <?= escapar($p['visitante_nome']) ?></td>
                                    <td><?= escapar(formatarDataHora($p['inicio_previsto'])) ?></td>
                                    <td>
                                        <div class="select is-small">
                                            <select name="fase[<?= (int) $p['id'] ?>]" aria-label="Fase da partida <?= (int) $p['id'] ?>">
                                                <option value="">Sem fase</option>
                                                <?php foreach (FASES_PARTIDA as $valor => $rotulo): ?>
                                                    <option value="<?= $valor ?>" <?= $p['fase'] === $valor ? 'selected' : '' ?>><?= escapar($rotulo) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <button class="button is-link" type="submit">Salvar fases</button>
            </form>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/admin/footer.php'; ?>
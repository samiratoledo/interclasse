<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

exigirPerfil('admin');

$baseUrl = '/interclasse-etec';
$erro = '';

$modalidades = $pdo->query(
    "SELECT id, nome, categoria
     FROM modalidades
     WHERE ativa = 1
     ORDER BY nome"
)->fetchAll();

$turmas = $pdo->query(
    "SELECT id, nome, periodo
     FROM turmas
     WHERE ativa = 1
     ORDER BY periodo, nome"
)->fetchAll();

$arbitros = $pdo->query(
    "SELECT id, nome
     FROM usuarios
     WHERE ativo = 1
       AND perfil IN ('admin', 'arbitro')
     ORDER BY nome"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!validarTokenCsrf($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException('Sessão expirada. Atualize a página e tente novamente.');
        }

        $modalidadeId = filter_input(INPUT_POST, 'modalidade_id', FILTER_VALIDATE_INT);
        $turmaCasaId = filter_input(INPUT_POST, 'turma_casa_id', FILTER_VALIDATE_INT);
        $turmaVisitanteId = filter_input(INPUT_POST, 'turma_visitante_id', FILTER_VALIDATE_INT);
        $responsavelId = filter_input(INPUT_POST, 'responsavel_id', FILTER_VALIDATE_INT);

        $data = trim($_POST['data'] ?? '');
        $horario = trim($_POST['horario'] ?? '');
        $periodo = $_POST['periodo_escolar'] ?? '';
        $local = trim($_POST['local_partida'] ?? '');
        $duracao = filter_input(INPUT_POST, 'duracao_periodo_min', FILTER_VALIDATE_INT);
        $quantidade = filter_input(INPUT_POST, 'quantidade_periodos', FILTER_VALIDATE_INT);
        $intervalo = filter_input(INPUT_POST, 'intervalo_min', FILTER_VALIDATE_INT);
        $youtube = trim($_POST['youtube_url'] ?? '');

        if (!$modalidadeId || !$turmaCasaId || !$turmaVisitanteId) {
            throw new RuntimeException('Selecione a modalidade e as duas turmas.');
        }

        if ($turmaCasaId === $turmaVisitanteId) {
            throw new RuntimeException('As duas equipes precisam ser de turmas diferentes.');
        }

        if (!in_array($periodo, ['manha', 'tarde'], true)) {
            throw new RuntimeException('Selecione o período escolar.');
        }

        if ($data !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            throw new RuntimeException('Data inválida.');
        }

        if ($horario !== '' && !preg_match('/^\d{2}:\d{2}$/', $horario)) {
            throw new RuntimeException('Horário inválido.');
        }

        if (($data === '') !== ($horario === '')) {
            throw new RuntimeException('Preencha a data e o horário juntos, ou deixe ambos vazios.');
        }

        if ($duracao === false || $duracao < 1 || $duracao > 300) {
            throw new RuntimeException('A duração de cada período deve ser entre 1 e 300 minutos.');
        }

        if ($quantidade === false || $quantidade < 1 || $quantidade > 10) {
            throw new RuntimeException('A quantidade de períodos deve ser entre 1 e 10.');
        }

        if ($intervalo === false || $intervalo < 0 || $intervalo > 120) {
            throw new RuntimeException('O intervalo deve ser entre 0 e 120 minutos.');
        }

        if ($youtube !== '' && !filter_var($youtube, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Informe um link válido para a transmissão.');
        }

        if ($youtube !== '' && !preg_match('~^https?://~i', $youtube)) {
            throw new RuntimeException('O link da transmissão deve começar com http:// ou https://.');
        }

        // Confere se as referências existem e estão ativas.
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM modalidades WHERE id = ? AND ativa = 1'
        );
        $stmt->execute([$modalidadeId]);

        if ((int) $stmt->fetchColumn() !== 1) {
            throw new RuntimeException('A modalidade selecionada não está disponível.');
        }

        $stmt = $pdo->prepare(
            'SELECT id, periodo FROM turmas WHERE id IN (?, ?) AND ativa = 1'
        );
        $stmt->execute([$turmaCasaId, $turmaVisitanteId]);
        $turmasSelecionadas = $stmt->fetchAll();

        if (count($turmasSelecionadas) !== 2) {
            throw new RuntimeException('Uma das turmas selecionadas não está disponível.');
        }

        if ($responsavelId) {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM usuarios
                 WHERE id = ? AND ativo = 1
                   AND perfil IN ('admin', 'arbitro')"
            );
            $stmt->execute([$responsavelId]);

            if ((int) $stmt->fetchColumn() !== 1) {
                throw new RuntimeException('O responsável selecionado não está disponível.');
            }
        }

        $inicioPrevisto = $data !== ''
            ? $data . ' ' . $horario . ':00'
            : null;

        $youtubeStatus = $youtube !== '' ? 'agendada' : 'sem_transmissao';

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "INSERT INTO partidas (
                modalidade_id,
                turma_casa_id,
                turma_visitante_id,
                inicio_previsto,
                periodo_escolar,
                local_partida,
                responsavel_id,
                duracao_periodo_min,
                quantidade_periodos,
                intervalo_min,
                youtube_url,
                youtube_status,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'agendada')"
        );

        $stmt->execute([
            $modalidadeId,
            $turmaCasaId,
            $turmaVisitanteId,
            $inicioPrevisto,
            $periodo,
            $local !== '' ? $local : null,
            $responsavelId ?: null,
            $duracao,
            $quantidade,
            $intervalo,
            $youtube !== '' ? $youtube : null,
            $youtubeStatus
        ]);

        $partidaId = (int) $pdo->lastInsertId();

        $usuario = usuarioAtual();
        $usuarioId = (int) ($usuario['id'] ?? 0);

        $stmt = $pdo->prepare(
            "INSERT INTO eventos_partida
                (partida_id, usuario_id, tipo, descricao)
             VALUES (?, ?, 'criacao', ?)"
        );

        $stmt->execute([
            $partidaId,
            $usuarioId ?: null,
            'Partida cadastrada pelo painel administrativo.'
        ]);

        $pdo->commit();

        header('Location: ' . $baseUrl . '/public/admin/partidas.php?criada=1');
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof PDOException) {
            error_log('Erro ao cadastrar partida: ' . $e->getMessage());
            $erro = 'Não foi possível cadastrar a partida. Confira os dados e tente novamente.';
        } else {
            $erro = $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/admin/header.php';
?>

<div class="container">
    <h1 class="title is-3">Criar partida</h1>
    <p class="subtitle is-6">
        Cadastre um confronto e configure seus horários, responsáveis e transmissão.
    </p>

    <?php if ($erro !== ''): ?>
        <div class="notification is-danger is-light">
            <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="box">
        <form method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars(gerarTokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">

            <h2 class="title is-5">Confronto</h2>

            <div class="field">
                <label class="label">Modalidade</label>
                <div class="control select is-fullwidth">
                    <select name="modalidade_id" required>
                        <option value="">Selecione a modalidade</option>
                        <?php foreach ($modalidades as $m): ?>
                            <option value="<?= (int) $m['id'] ?>"
                                <?= (string) ($_POST['modalidade_id'] ?? '') === (string) $m['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['nome'], ENT_QUOTES, 'UTF-8') ?>
                                — <?= htmlspecialchars(ucfirst($m['categoria']), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="columns">
                <?php foreach (['turma_casa_id' => 'Turma da casa', 'turma_visitante_id' => 'Turma visitante'] as $campo => $rotulo): ?>
                    <div class="column">
                        <div class="field">
                            <label class="label"><?= $rotulo ?></label>
                            <div class="control select is-fullwidth">
                                <select name="<?= $campo ?>" required>
                                    <option value="">Selecione a turma</option>
                                    <?php foreach ($turmas as $t): ?>
                                        <option value="<?= (int) $t['id'] ?>"
                                            <?= (string) ($_POST[$campo] ?? '') === (string) $t['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($t['nome'], ENT_QUOTES, 'UTF-8') ?>
                                            (<?= $t['periodo'] === 'manha' ? 'Manhã' : 'Tarde' ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="columns">
                <div class="column">
                    <div class="field">
                        <label class="label">Data</label>
                        <div class="control">
                            <input class="input" type="date" name="data"
                                   value="<?= htmlspecialchars($_POST['data'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                </div>
                <div class="column">
                    <div class="field">
                        <label class="label">Horário</label>
                        <div class="control">
                            <input class="input" type="time" name="horario"
                                   value="<?= htmlspecialchars($_POST['horario'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                </div>
                <div class="column">
                    <div class="field">
                        <label class="label">Período escolar</label>
                        <div class="control select is-fullwidth">
                            <select name="periodo_escolar" required>
                                <option value="">Selecione</option>
                                <option value="manha" <?= ($_POST['periodo_escolar'] ?? '') === 'manha' ? 'selected' : '' ?>>Manhã</option>
                                <option value="tarde" <?= ($_POST['periodo_escolar'] ?? '') === 'tarde' ? 'selected' : '' ?>>Tarde</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="field">
                <label class="label">Local da partida</label>
                <div class="control">
                    <input class="input" type="text" name="local_partida"
                           maxlength="120" placeholder="Ex.: Quadra coberta"
                           value="<?= htmlspecialchars($_POST['local_partida'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="field">
                <label class="label">Árbitro ou responsável</label>
                <div class="control select is-fullwidth">
                    <select name="responsavel_id">
                        <option value="">A definir</option>
                        <?php foreach ($arbitros as $a): ?>
                            <option value="<?= (int) $a['id'] ?>"
                                <?= (string) ($_POST['responsavel_id'] ?? '') === (string) $a['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($a['nome'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <hr>
            <h2 class="title is-5">Configuração de tempo</h2>

            <div class="columns">
                <div class="column">
                    <label class="label">Duração de cada período (min)</label>
                    <input class="input" type="number" name="duracao_periodo_min"
                           min="1" max="300" required
                           value="<?= htmlspecialchars($_POST['duracao_periodo_min'] ?? '10', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="column">
                    <label class="label">Quantidade de períodos</label>
                    <input class="input" type="number" name="quantidade_periodos"
                           min="1" max="10" required
                           value="<?= htmlspecialchars($_POST['quantidade_periodos'] ?? '2', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="column">
                    <label class="label">Intervalo (min)</label>
                    <input class="input" type="number" name="intervalo_min"
                           min="0" max="120" required
                           value="<?= htmlspecialchars($_POST['intervalo_min'] ?? '5', ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <hr>
            <h2 class="title is-5">Transmissão pelo YouTube</h2>

            <div class="field">
                <label class="label">Link da transmissão (opcional)</label>
                <div class="control">
                    <input class="input" type="url" name="youtube_url"
                           maxlength="500" placeholder="https://www.youtube.com/..."
                           value="<?= htmlspecialchars($_POST['youtube_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="field is-grouped mt-5">
                <div class="control">
                    <button type="submit" class="button is-primary">
                        Cadastrar partida
                    </button>
                </div>
                <div class="control">
                    <a class="button is-light"
                       href="<?= $baseUrl ?>/public/admin/partidas.php">
                        Ver partidas
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/admin/footer.php'; ?>

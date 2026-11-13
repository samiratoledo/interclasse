<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$periodo  = (string) ($_GET['periodo'] ?? '');
$status   = (string) ($_GET['status'] ?? '');
$local    = trim((string) ($_GET['local'] ?? ''));
$modalidadeId = filter_input(INPUT_GET, 'modalidade', FILTER_VALIDATE_INT) ?: 0;
$turmaId  = filter_input(INPUT_GET, 'turma', FILTER_VALIDATE_INT) ?: 0;

if (!isset(PERIODOS_ESCOLARES[$periodo])) {
    $periodo = '';
}
if (!isset(STATUS_PARTIDA[$status])) {
    $status = '';
}

$partidas = $modalidades = $turmas = $locais = [];
$erro = '';

try {
    $modalidades = $pdo->query("SELECT id, nome FROM modalidades WHERE ativa = 1 ORDER BY nome")->fetchAll();
    $turmas = $pdo->query("SELECT id, nome FROM turmas WHERE ativa = 1 ORDER BY periodo, id")->fetchAll();
    $locais = $pdo->query("
        SELECT DISTINCT local_partida FROM partidas
        WHERE local_partida IS NOT NULL AND local_partida <> ''
        ORDER BY local_partida
    ")->fetchAll(PDO::FETCH_COLUMN);

    $where = [];
    $params = [];

    if ($periodo !== '') {
        $where[] = 'p.periodo_escolar = :periodo';
        $params['periodo'] = $periodo;
    }
    if ($status !== '') {
        $where[] = 'p.status = :status';
        $params['status'] = $status;
    }
    if ($modalidadeId > 0) {
        $where[] = 'p.modalidade_id = :modalidade';
        $params['modalidade'] = $modalidadeId;
    }
    if ($turmaId > 0) {
        $where[] = '(p.turma_casa_id = :turma1 OR p.turma_visitante_id = :turma2)';
        $params['turma1'] = $turmaId;
        $params['turma2'] = $turmaId;
    }
    if ($local !== '') {
        $where[] = 'p.local_partida = :local';
        $params['local'] = $local;
    }

    $sql = sqlPartidas()
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY p.inicio_previsto IS NULL, p.inicio_previsto ASC, p.id ASC LIMIT 200';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $partidas = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $erro = 'Não foi possível carregar as partidas agora.';
}

// Agrupa por dia
$porDia = [];
foreach ($partidas as $p) {
    $chave = $p['inicio_previsto'] ? date('Y-m-d', strtotime($p['inicio_previsto'])) : 'sem-data';
    $porDia[$chave][] = $p;
}

$filtrando = $periodo || $status || $local || $modalidadeId || $turmaId;

$tituloPagina = 'Partidas';
$paginaAtual = 'partidas';
require __DIR__ . '/../includes/header.php';
?>

<div class="container pub-conteudo">
    <h1 class="title is-3">Partidas</h1>

    <form class="pub-filtros" method="GET" action="partidas.php">
        <div class="field">
            <label class="label is-small" for="f-periodo">Período</label>
            <div class="select is-small is-fullwidth"><select id="f-periodo" name="periodo">
                <option value="">Todos</option>
                <?php foreach (PERIODOS_ESCOLARES as $v => $r): ?>
                    <option value="<?= $v ?>" <?= $periodo === $v ? 'selected' : '' ?>><?= $r ?></option>
                <?php endforeach; ?>
            </select></div>
        </div>
        <div class="field">
            <label class="label is-small" for="f-modalidade">Modalidade</label>
            <div class="select is-small is-fullwidth"><select id="f-modalidade" name="modalidade">
                <option value="">Todas</option>
                <?php foreach ($modalidades as $m): ?>
                    <option value="<?= (int) $m['id'] ?>" <?= $modalidadeId === (int) $m['id'] ? 'selected' : '' ?>><?= escapar($m['nome']) ?></option>
                <?php endforeach; ?>
            </select></div>
        </div>
        <div class="field">
            <label class="label is-small" for="f-turma">Turma</label>
            <div class="select is-small is-fullwidth"><select id="f-turma" name="turma">
                <option value="">Todas</option>
                <?php foreach ($turmas as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= $turmaId === (int) $t['id'] ? 'selected' : '' ?>><?= escapar($t['nome']) ?></option>
                <?php endforeach; ?>
            </select></div>
        </div>
        <div class="field">
            <label class="label is-small" for="f-status">Status</label>
            <div class="select is-small is-fullwidth"><select id="f-status" name="status">
                <option value="">Todos</option>
                <?php foreach (STATUS_PARTIDA as $v => [$r]): ?>
                    <option value="<?= $v ?>" <?= $status === $v ? 'selected' : '' ?>><?= $r ?></option>
                <?php endforeach; ?>
            </select></div>
        </div>
        <div class="field">
            <label class="label is-small" for="f-local">Local</label>
            <div class="select is-small is-fullwidth"><select id="f-local" name="local">
                <option value="">Todos</option>
                <?php foreach ($locais as $l): ?>
                    <option value="<?= escapar($l) ?>" <?= $local === $l ? 'selected' : '' ?>><?= escapar($l) ?></option>
                <?php endforeach; ?>
            </select></div>
        </div>
        <div class="field pub-filtros__acoes">
            <button class="button is-link is-small" type="submit">Filtrar</button>
            <?php if ($filtrando): ?>
                <a class="button is-light is-small" href="partidas.php">Limpar</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($erro !== ''): ?>
        <div class="notification is-danger is-light"><?= escapar($erro) ?></div>
    <?php elseif (!$partidas): ?>
        <p class="pub-vazio"><?= $filtrando ? 'Nenhuma partida encontrada com esses filtros.' : 'Ainda não há partidas cadastradas.' ?></p>
    <?php endif; ?>

    <?php foreach ($porDia as $dia => $lista): ?>
        <section class="pub-secao">
            <h2 class="pub-secao__titulo">
                <?= $dia === 'sem-data' ? 'Data a definir' : escapar(date('d/m/Y', strtotime($dia))) ?>
            </h2>
            <div class="pub-grade">
                <?php foreach ($lista as $p): ?>
                    <?= cartaoPartida($p) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
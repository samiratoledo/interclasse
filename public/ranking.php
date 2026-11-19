<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

/*
 * Ranking de turmas.
 * Pontuação do regulamento: vitória 3, empate 1, derrota 0 (W.O. = derrota).
 * Só entram partidas encerradas. Sem filtro = classificação geral (todas as modalidades).
 */

$modalidadeId = filter_input(INPUT_GET, 'modalidade', FILTER_VALIDATE_INT) ?: 0;

$modalidades = [];
$tabela = [];
$totalPartidas = 0;
$erro = '';

try {
    $modalidades = $pdo->query("SELECT id, nome FROM modalidades WHERE ativa = 1 ORDER BY nome")->fetchAll();

    $ids = array_map('intval', array_column($modalidades, 'id'));
    if ($modalidadeId > 0 && !in_array($modalidadeId, $ids, true)) {
        $modalidadeId = 0;
    }

    $sql = "
        SELECT
            p.turma_casa_id, p.turma_visitante_id, p.turma_vencedora_id,
            p.placar_casa, p.placar_visitante, p.status,
            tc.nome AS casa_nome, tc.imagem AS casa_imagem,
            tv.nome AS visitante_nome, tv.imagem AS visitante_imagem
        FROM partidas p
        INNER JOIN turmas tc ON tc.id = p.turma_casa_id
        INNER JOIN turmas tv ON tv.id = p.turma_visitante_id
        WHERE p.status = 'encerrada'
    ";
    $params = [];
    if ($modalidadeId > 0) {
        $sql .= ' AND p.modalidade_id = ?';
        $params[] = $modalidadeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $partidas = $stmt->fetchAll();
    $totalPartidas = count($partidas);

    $novaLinha = static fn (string $nome, ?string $img): array => [
        'nome' => $nome, 'imagem' => $img,
        'j' => 0, 'v' => 0, 'e' => 0, 'd' => 0, 'gp' => 0, 'gc' => 0, 'pts' => 0,
    ];

    $linhas = [];
    foreach ($partidas as $p) {
        $casa = (int) $p['turma_casa_id'];
        $vis = (int) $p['turma_visitante_id'];

        $linhas[$casa] ??= $novaLinha($p['casa_nome'], $p['casa_imagem']);
        $linhas[$vis] ??= $novaLinha($p['visitante_nome'], $p['visitante_imagem']);

        $gc = (int) $p['placar_casa'];
        $gv = (int) $p['placar_visitante'];

        $linhas[$casa]['j']++;
        $linhas[$vis]['j']++;
        $linhas[$casa]['gp'] += $gc;
        $linhas[$casa]['gc'] += $gv;
        $linhas[$vis]['gp'] += $gv;
        $linhas[$vis]['gc'] += $gc;

        $vencedora = vencedoraPartida($p);

        if ($vencedora === null) {
            $linhas[$casa]['e']++;
            $linhas[$vis]['e']++;
            $linhas[$casa]['pts'] += 1;
            $linhas[$vis]['pts'] += 1;
        } else {
            $perdedora = $vencedora === $casa ? $vis : $casa;
            $linhas[$vencedora]['v']++;
            $linhas[$vencedora]['pts'] += 3;
            $linhas[$perdedora]['d']++;
        }
    }

    $tabela = array_values($linhas);
    usort($tabela, static function (array $a, array $b): int {
        return [$b['pts'], $b['v'], $b['gp'] - $b['gc'], $b['gp'], $a['nome']]
           <=> [$a['pts'], $a['v'], $a['gp'] - $a['gc'], $a['gp'], $b['nome']];
    });
} catch (PDOException $e) {
    error_log($e->getMessage());
    $erro = 'Não foi possível carregar o ranking agora. Tente novamente em instantes.';
}

$nomeModalidade = 'Todas as modalidades';
foreach ($modalidades as $m) {
    if ((int) $m['id'] === $modalidadeId) {
        $nomeModalidade = $m['nome'];
    }
}

$tituloPagina = 'Ranking';
$paginaAtual = 'ranking';
require __DIR__ . '/../includes/header.php';
?>

<div class="container pub-conteudo">
    <h1 class="title is-3">Ranking</h1>

    <form class="pub-filtros pub-filtros--simples" method="GET" action="ranking.php">
        <div class="field">
            <label class="label is-small" for="f-modalidade">Modalidade</label>
            <div class="select is-small is-fullwidth">
                <select id="f-modalidade" name="modalidade" onchange="this.form.submit()">
                    <option value="0">Todas (classificação geral)</option>
                    <?php foreach ($modalidades as $m): ?>
                        <option value="<?= (int) $m['id'] ?>" <?= $modalidadeId === (int) $m['id'] ? 'selected' : '' ?>><?= escapar($m['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="field pub-filtros__acoes">
            <button class="button is-link is-small" type="submit">Filtrar</button>
        </div>
    </form>

    <?php if ($erro !== ''): ?>
        <div class="notification is-danger is-light"><?= escapar($erro) ?></div>
    <?php endif; ?>

    <section class="pub-secao">
        <h2 class="pub-secao__titulo"><?= escapar($nomeModalidade) ?></h2>

        <?php if (!$tabela): ?>
            <p class="pub-vazio">Ainda não há partidas encerradas<?= $modalidadeId ? ' nesta modalidade' : '' ?>.</p>
        <?php else: ?>
            <div class="pub-tabela-wrap">
                <table class="pub-tabela">
                    <thead>
                        <tr>
                            <th class="pub-col-pos" scope="col">#</th>
                            <th class="pub-col-turma" scope="col">Turma</th>
                            <th scope="col" title="Pontos">P</th>
                            <th scope="col" title="Jogos">J</th>
                            <th scope="col" title="Vitórias">V</th>
                            <th scope="col" title="Empates">E</th>
                            <th scope="col" title="Derrotas">D</th>
                            <th class="pub-col-extra" scope="col" title="Gols/pontos pró">GP</th>
                            <th class="pub-col-extra" scope="col" title="Gols/pontos contra">GC</th>
                            <th scope="col" title="Saldo">SG</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tabela as $i => $l): ?>
                            <?php $saldo = $l['gp'] - $l['gc']; ?>
                            <tr class="<?= $i < 3 ? 'pub-pos-' . ($i + 1) : '' ?>">
                                <td class="pub-col-pos"><?= $i + 1 ?></td>
                                <td class="pub-col-turma">
                                    <span class="pub-turma-linha">
                                        <?= brasaoHtml($l['nome'], $l['imagem'], 'pub-brasao--mini') ?>
                                        <span><?= escapar($l['nome']) ?></span>
                                    </span>
                                </td>
                                <td><strong><?= $l['pts'] ?></strong></td>
                                <td><?= $l['j'] ?></td>
                                <td><?= $l['v'] ?></td>
                                <td><?= $l['e'] ?></td>
                                <td><?= $l['d'] ?></td>
                                <td class="pub-col-extra"><?= $l['gp'] ?></td>
                                <td class="pub-col-extra"><?= $l['gc'] ?></td>
                                <td><?= $saldo > 0 ? '+' . $saldo : $saldo ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="pub-nota">
                Vitória = 3 pontos · empate = 1 · derrota = 0 · W.O. conta como derrota.
                Desempate: vitórias, saldo e pontos pró.
                Baseado em <?= $totalPartidas ?> partida<?= $totalPartidas === 1 ? '' : 's' ?> encerrada<?= $totalPartidas === 1 ? '' : 's' ?>.
            </p>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
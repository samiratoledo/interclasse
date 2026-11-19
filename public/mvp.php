<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

/*
 * MVP.
 * - "MVP confirmado": decisão oficial gravada em mvp_decisoes.
 * - "Pontuação parcial": soma dos eventos das partidas, usando as regras de
 *   regras_pontuacao_mvp da modalidade (ex.: gol/cesta = 2, assistência = 1).
 */

$modalidadeId = filter_input(INPUT_GET, 'modalidade', FILTER_VALIDATE_INT) ?: 0;

$modalidades = $confirmados = $ranking = $regras = [];
$decisaoAtual = null;
$erro = '';

try {
    $modalidades = $pdo->query("SELECT id, nome FROM modalidades WHERE ativa = 1 ORDER BY nome")->fetchAll();
    $ids = array_map('intval', array_column($modalidades, 'id'));

    // MVPs já confirmados pela organização
    $confirmados = $pdo->query("
        SELECT d.modalidade_id, d.atleta_id, d.justificativa,
               m.nome AS modalidade_nome,
               a.nome AS atleta_nome, a.numero_camisa,
               t.nome AS turma_nome, t.imagem AS turma_imagem
        FROM mvp_decisoes d
        INNER JOIN modalidades m ON m.id = d.modalidade_id
        INNER JOIN atletas a ON a.id = d.atleta_id
        INNER JOIN turmas t ON t.id = a.turma_id
        ORDER BY m.nome
    ")->fetchAll();

    // Modalidade padrão: a primeira que já tem eventos com atleta
    if (!in_array($modalidadeId, $ids, true)) {
        $comEventos = $pdo->query("
            SELECT DISTINCT p.modalidade_id
            FROM eventos_partida ep
            INNER JOIN partidas p ON p.id = ep.partida_id
            WHERE ep.atleta_id IS NOT NULL AND p.status <> 'cancelada'
        ")->fetchAll(PDO::FETCH_COLUMN);
        $comEventos = array_map('intval', $comEventos);

        $modalidadeId = 0;
        foreach ($ids as $id) {
            if (in_array($id, $comEventos, true)) {
                $modalidadeId = $id;
                break;
            }
        }
        if ($modalidadeId === 0 && $ids) {
            $modalidadeId = $ids[0];
        }
    }

    if ($modalidadeId > 0) {
        // Regras de pontuação da modalidade
        $stmt = $pdo->prepare("
            SELECT tipo_evento, pontos FROM regras_pontuacao_mvp
            WHERE modalidade_id = ? AND ativo = 1
            ORDER BY pontos DESC, tipo_evento
        ");
        $stmt->execute([$modalidadeId]);
        foreach ($stmt->fetchAll() as $r) {
            $regras[$r['tipo_evento']] = (int) $r['pontos'];
        }

        // Quantidade de eventos por atleta e tipo (autor do evento + assistências)
        $stmt = $pdo->prepare("
            SELECT atleta_id, tipo, SUM(qtd) AS qtd FROM (
                SELECT ep.atleta_id AS atleta_id, ep.tipo AS tipo, COUNT(*) AS qtd
                FROM eventos_partida ep
                INNER JOIN partidas p ON p.id = ep.partida_id
                WHERE p.modalidade_id = ? AND p.status <> 'cancelada' AND ep.atleta_id IS NOT NULL
                GROUP BY ep.atleta_id, ep.tipo
                UNION ALL
                SELECT ep.assistencia_id, 'assistencia', COUNT(*)
                FROM eventos_partida ep
                INNER JOIN partidas p ON p.id = ep.partida_id
                WHERE p.modalidade_id = ? AND p.status <> 'cancelada' AND ep.assistencia_id IS NOT NULL
                GROUP BY ep.assistencia_id
            ) x
            GROUP BY atleta_id, tipo
        ");
        $stmt->execute([$modalidadeId, $modalidadeId]);

        $placar = [];
        foreach ($stmt->fetchAll() as $linha) {
            $tipo = (string) $linha['tipo'];
            if (!isset($regras[$tipo])) {
                continue; // evento que não pontua (ex.: período, ocorrência)
            }

            $aid = (int) $linha['atleta_id'];
            $qtd = (int) $linha['qtd'];
            $placar[$aid] ??= ['pontos' => 0, 'feitos' => 0, 'assist' => 0];
            $placar[$aid]['pontos'] += $qtd * $regras[$tipo];

            if ($tipo === 'assistencia') {
                $placar[$aid]['assist'] += $qtd;
            } elseif ($regras[$tipo] > 0) {
                $placar[$aid]['feitos'] += $qtd;
            }
        }

        if ($placar) {
            $marcadores = implode(',', array_fill(0, count($placar), '?'));
            $stmt = $pdo->prepare("
                SELECT a.id, a.nome, a.numero_camisa, t.nome AS turma_nome, t.imagem AS turma_imagem
                FROM atletas a
                INNER JOIN turmas t ON t.id = a.turma_id
                WHERE a.id IN ($marcadores)
            ");
            $stmt->execute(array_keys($placar));
            $atletas = [];
            foreach ($stmt->fetchAll() as $a) {
                $atletas[(int) $a['id']] = $a;
            }

            foreach ($placar as $aid => $dados) {
                if (!isset($atletas[$aid])) {
                    continue;
                }
                $ranking[] = $dados + $atletas[$aid];
            }

            usort($ranking, static function (array $a, array $b): int {
                return [$b['pontos'], $b['feitos'], $b['assist'], $a['nome']]
                   <=> [$a['pontos'], $a['feitos'], $a['assist'], $b['nome']];
            });
            $ranking = array_slice($ranking, 0, 10);
        }

        foreach ($confirmados as $c) {
            if ((int) $c['modalidade_id'] === $modalidadeId) {
                $decisaoAtual = $c;
            }
        }
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    $erro = 'Não foi possível carregar o MVP agora. Tente novamente em instantes.';
}

$tituloPagina = 'MVP';
$paginaAtual = 'mvp';
require __DIR__ . '/../includes/header.php';
?>

<div class="container pub-conteudo">
    <h1 class="title is-3">MVP</h1>

    <?php if ($erro !== ''): ?>
        <div class="notification is-danger is-light"><?= escapar($erro) ?></div>
    <?php endif; ?>

    <?php if ($confirmados): ?>
        <section class="pub-secao">
            <h2 class="pub-secao__titulo">🏅 MVP confirmado</h2>
            <div class="pub-grade">
                <?php foreach ($confirmados as $c): ?>
                    <article class="pub-card pub-mvp-card">
                        <p class="pub-card__modalidade"><?= escapar($c['modalidade_nome']) ?></p>
                        <div class="pub-mvp-card__corpo">
                            <?= brasaoHtml($c['turma_nome'], $c['turma_imagem']) ?>
                            <div>
                                <p class="pub-mvp-card__nome"><?= escapar($c['atleta_nome']) ?></p>
                                <p class="pub-mvp-card__turma">
                                    <?= escapar($c['turma_nome']) ?><?= $c['numero_camisa'] !== null ? ' · camisa ' . (int) $c['numero_camisa'] : '' ?>
                                </p>
                            </div>
                        </div>
                        <?php if (!empty($c['justificativa'])): ?>
                            <p class="pub-nota"><?= escapar($c['justificativa']) ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <form class="pub-filtros pub-filtros--simples" method="GET" action="mvp.php">
        <div class="field">
            <label class="label is-small" for="f-modalidade">Modalidade</label>
            <div class="select is-small is-fullwidth">
                <select id="f-modalidade" name="modalidade" onchange="this.form.submit()">
                    <?php foreach ($modalidades as $m): ?>
                        <option value="<?= (int) $m['id'] ?>" <?= $modalidadeId === (int) $m['id'] ? 'selected' : '' ?>><?= escapar($m['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="field pub-filtros__acoes">
            <button class="button is-link is-small" type="submit">Ver</button>
        </div>
    </form>

    <section class="pub-secao">
        <h2 class="pub-secao__titulo">Pontuação parcial</h2>

        <?php if ($decisaoAtual): ?>
            <p class="pub-nota pub-nota--destaque">
                MVP desta modalidade confirmado: <strong><?= escapar($decisaoAtual['atleta_nome']) ?></strong>
                (<?= escapar($decisaoAtual['turma_nome']) ?>).
            </p>
        <?php endif; ?>

        <?php if (!$ranking): ?>
            <p class="pub-vazio">Ainda não há pontuação registrada nesta modalidade.</p>
        <?php else: ?>
            <div class="pub-tabela-wrap">
                <table class="pub-tabela">
                    <thead>
                        <tr>
                            <th class="pub-col-pos" scope="col">#</th>
                            <th class="pub-col-turma" scope="col">Atleta</th>
                            <th scope="col" title="Pontos de MVP">Pts</th>
                            <th class="pub-col-extra" scope="col" title="Gols, cestas ou pontos">Marcou</th>
                            <th class="pub-col-extra" scope="col" title="Assistências">Assist.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ranking as $i => $a): ?>
                            <tr class="<?= $i < 3 ? 'pub-pos-' . ($i + 1) : '' ?>">
                                <td class="pub-col-pos"><?= $i + 1 ?></td>
                                <td class="pub-col-turma">
                                    <span class="pub-turma-linha">
                                        <?= brasaoHtml($a['turma_nome'], $a['turma_imagem'], 'pub-brasao--mini') ?>
                                        <span>
                                            <strong><?= escapar($a['nome']) ?></strong><br>
                                            <small><?= escapar($a['turma_nome']) ?><?= $a['numero_camisa'] !== null ? ' · ' . (int) $a['numero_camisa'] : '' ?></small>
                                        </span>
                                    </span>
                                </td>
                                <td><strong><?= (int) $a['pontos'] ?></strong></td>
                                <td class="pub-col-extra"><?= (int) $a['feitos'] ?></td>
                                <td class="pub-col-extra"><?= (int) $a['assist'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($regras): ?>
            <p class="pub-nota">
                Como pontua:
                <?php
                $partes = [];
                foreach ($regras as $tipo => $pts) {
                    $partes[] = escapar(rotuloTipoEvento($tipo)) . ' (' . ($pts > 0 ? '+' : '') . $pts . ')';
                }
                echo implode(' · ', $partes);
                ?>.
                A pontuação apoia a escolha; o MVP oficial é confirmado pela organização.
            </p>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
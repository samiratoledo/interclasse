<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

/*
 * Chaveamento (mata-mata) por modalidade.
 * Usa a coluna partidas.fase (database/schema.sql);
 * o admin define a fase de cada partida em admin/fases.php.
 */

// Fases que aparecem na chave (a fase de grupos fica no Ranking)
$fasesChave = array_diff_key(FASES_PARTIDA, ['grupos' => true]);

$modalidadeId = filter_input(INPUT_GET, 'modalidade', FILTER_VALIDATE_INT) ?: 0;

$modalidades = $porFase = [];
$campea = null;
$temFase = true;
$erro = '';

try {
    try {
        $pdo->query('SELECT fase FROM partidas LIMIT 1');
    } catch (PDOException $e) {
        $temFase = false;
        error_log('Chaveamento: recrie o banco com database/schema.sql atualizado (' . $e->getMessage() . ')');
    }

    $modalidades = $pdo->query("SELECT id, nome FROM modalidades WHERE ativa = 1 ORDER BY nome")->fetchAll();
    $ids = array_map('intval', array_column($modalidades, 'id'));

    if ($temFase) {
        $marcFases = implode(',', array_fill(0, count($fasesChave), '?'));

        if (!in_array($modalidadeId, $ids, true)) {
            // Padrão: primeira modalidade que já tem partida em alguma fase da chave
            $stmt = $pdo->prepare("SELECT DISTINCT modalidade_id FROM partidas WHERE fase IN ($marcFases) AND status <> 'cancelada'");
            $stmt->execute(array_keys($fasesChave));
            $comChave = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            $modalidadeId = 0;
            foreach ($ids as $id) {
                if (in_array($id, $comChave, true)) {
                    $modalidadeId = $id;
                    break;
                }
            }
            if ($modalidadeId === 0 && $ids) {
                $modalidadeId = $ids[0];
            }
        }

        if ($modalidadeId > 0) {
            $stmt = $pdo->prepare("
                SELECT p.id, p.status, p.fase, p.inicio_previsto,
                       p.placar_casa, p.placar_visitante,
                       p.turma_casa_id, p.turma_visitante_id, p.turma_vencedora_id,
                       tc.nome AS casa_nome, tc.imagem AS casa_imagem,
                       tv.nome AS visitante_nome, tv.imagem AS visitante_imagem
                FROM partidas p
                INNER JOIN turmas tc ON tc.id = p.turma_casa_id
                INNER JOIN turmas tv ON tv.id = p.turma_visitante_id
                WHERE p.modalidade_id = ? AND p.status <> 'cancelada' AND p.fase IN ($marcFases)
                ORDER BY p.inicio_previsto IS NULL, p.inicio_previsto, p.id
            ");
            $stmt->execute(array_merge([$modalidadeId], array_keys($fasesChave)));

            foreach ($stmt->fetchAll() as $p) {
                $porFase[$p['fase']][] = $p;
            }

            foreach ($porFase['final'] ?? [] as $final) {
                $v = vencedoraPartida($final);
                if ($v !== null) {
                    $campea = $v === (int) $final['turma_casa_id'] ? $final['casa_nome'] : $final['visitante_nome'];
                }
            }
        }
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    $erro = 'Não foi possível carregar o chaveamento agora. Tente novamente em instantes.';
}

/** Uma linha (turma) do confronto, com destaque para a vencedora. */
function linhaChave(array $p, string $lado): string
{
    $casa = $lado === 'casa';
    $nome = $casa ? $p['casa_nome'] : $p['visitante_nome'];
    $img = $casa ? $p['casa_imagem'] : $p['visitante_imagem'];
    $id = (int) ($casa ? $p['turma_casa_id'] : $p['turma_visitante_id']);
    $placar = (int) ($casa ? $p['placar_casa'] : $p['placar_visitante']);
    $mostrar = $p['status'] !== 'agendada';
    $venceu = vencedoraPartida($p) === $id;

    return '<div class="pub-chave__time' . ($venceu ? ' is-vencedora' : '') . '">'
        . brasaoHtml($nome, $img, 'pub-brasao--mini')
        . '<span class="pub-chave__nome">' . escapar($nome) . '</span>'
        . '<strong class="pub-chave__placar" data-campo="placar_' . ($casa ? 'casa' : 'visitante') . '">'
        . ($mostrar ? $placar : '–') . '</strong></div>';
}

$tituloPagina = 'Chaveamento';
$paginaAtual = 'chaveamento';
require __DIR__ . '/../includes/header.php';
?>

<div class="container pub-conteudo">
    <h1 class="title is-3">Chaveamento</h1>

    <?php if ($erro !== ''): ?>
        <div class="notification is-danger is-light"><?= escapar($erro) ?></div>
    <?php elseif (!$temFase): ?>
        <div class="notification is-warning is-light">O chaveamento ainda não foi publicado.</div>
    <?php else: ?>

        <form class="pub-filtros pub-filtros--simples" method="GET" action="chaveamento.php">
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

        <?php if ($campea !== null): ?>
            <div class="pub-campea">🏆 Campeã: <strong><?= escapar($campea) ?></strong></div>
        <?php endif; ?>

        <?php if (!$porFase): ?>
            <section class="pub-secao">
                <p class="pub-vazio">O mata-mata desta modalidade ainda não foi definido.
                    Veja a <a href="ranking.php?modalidade=<?= (int) $modalidadeId ?>">classificação</a>.</p>
            </section>
        <?php else: ?>
            <section class="pub-secao pub-secao--chave">
                <div class="pub-chave" role="region" aria-label="Chaveamento" tabindex="0">
                    <?php foreach ($fasesChave as $chave => $rotulo): ?>
                        <?php if (empty($porFase[$chave])) { continue; } ?>
                        <div class="pub-chave__coluna">
                            <h2 class="pub-chave__titulo"><?= escapar($rotulo) ?></h2>
                            <div class="pub-chave__jogos">
                                <?php foreach ($porFase[$chave] as $p): ?>
                                    <a class="pub-chave__jogo" href="partida.php?id=<?= (int) $p['id'] ?>" data-partida-id="<?= (int) $p['id'] ?>">
                                        <?= linhaChave($p, 'casa') ?>
                                        <?= linhaChave($p, 'visitante') ?>
                                        <span class="pub-chave__info">
                                            <span class="tag <?= escapar(STATUS_PARTIDA[$p['status']][1] ?? 'is-light') ?>" data-campo="status"><?= escapar(STATUS_PARTIDA[$p['status']][0] ?? '—') ?></span>
                                            <span><?= escapar(formatarDataHora($p['inicio_previsto'])) ?></span>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="pub-nota pub-nota--rolagem">Arraste para o lado para ver as próximas fases →</p>
            </section>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
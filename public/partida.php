<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(400);
    exit('Partida inválida.');
}

$partida = null;
$eventos = [];

try {
    $stmt = $pdo->prepare(sqlPartidas() . ' WHERE p.id = ? LIMIT 1');
    $stmt->execute([$id]);
    $partida = $stmt->fetch();

    if ($partida) {
        // Só eventos públicos: ocorrências internas e correções não aparecem.
        $stmt = $pdo->prepare("
            SELECT tipo, descricao, periodo, criado_em
            FROM eventos_partida
            WHERE partida_id = ?
              AND tipo IN ('gol', 'periodo', 'cronometro', 'encerramento')
            ORDER BY criado_em DESC, id DESC
            LIMIT 40
        ");
        $stmt->execute([$id]);
        $eventos = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Não foi possível carregar a partida agora.');
}

if (!$partida) {
    http_response_code(404);
    exit('Partida não encontrada.');
}

[$rotuloStatus, $classeStatus] = STATUS_PARTIDA[$partida['status']] ?? ['—', 'is-light'];
$ativa = in_array($partida['status'], ['em_andamento', 'pausada'], true);
$mostrarPlacar = $partida['status'] !== 'agendada';

$ytId = youtubeId($partida['youtube_url']);
$ytMostrar = $ytId !== null && $partida['youtube_status'] !== 'sem_transmissao';
$ytLink = $ytId ? 'https://www.youtube.com/watch?v=' . $ytId : null;
$ytEmbed = $ytId ? 'https://www.youtube-nocookie.com/embed/' . $ytId : null;

$brasao = static function (string $nome, ?string $img): string {
    $src = brasaoSrc($img);
    return $src
        ? '<img class="pub-brasao pub-brasao--grande" src="' . escapar($src) . '" alt="">'
        : '<span class="pub-brasao pub-brasao--grande pub-brasao--vazio" aria-hidden="true">' . escapar(iniciais($nome)) . '</span>';
};

$tituloPagina = $partida['casa_nome'] . ' × ' . $partida['visitante_nome'];
$paginaAtual = 'partidas';
require __DIR__ . '/../includes/header.php';
?>

<div class="container pub-conteudo" data-partida-id="<?= (int) $partida['id'] ?>" data-detalhe="1">
    <p class="mb-3"><a href="partidas.php">← Todas as partidas</a></p>

    <section class="pub-placar-grande">
        <div class="pub-placar-grande__topo">
            <span><?= escapar($partida['modalidade_nome']) ?></span>
            <span class="tag <?= $classeStatus ?>" data-campo="status"><?= escapar($rotuloStatus) ?></span>
        </div>

        <div class="pub-card__confronto">
            <div class="pub-time">
                <?= $brasao($partida['casa_nome'], $partida['casa_imagem']) ?>
                <span class="pub-time__nome"><?= escapar($partida['casa_nome']) ?></span>
            </div>
            <div class="pub-placar pub-placar--grande">
                <span data-campo="placar_casa"><?= $mostrarPlacar ? (int) $partida['placar_casa'] : '–' ?></span>
                <span class="pub-placar__x">×</span>
                <span data-campo="placar_visitante"><?= $mostrarPlacar ? (int) $partida['placar_visitante'] : '–' ?></span>
            </div>
            <div class="pub-time">
                <?= $brasao($partida['visitante_nome'], $partida['visitante_imagem']) ?>
                <span class="pub-time__nome"><?= escapar($partida['visitante_nome']) ?></span>
            </div>
        </div>

        <p class="pub-relogio" data-campo="tempo">
            <?php if ($ativa): ?>
                P<?= (int) ($partida['periodo_atual'] ?? 1) ?> · <?= formatarTempo(segundosDecorridos($partida)) ?>
            <?php elseif ($partida['status'] === 'encerrada'): ?>
                Fim de jogo
            <?php else: ?>
                <?= escapar(formatarDataHora($partida['inicio_previsto'])) ?>
            <?php endif; ?>
        </p>
    </section>

    <?php if ($ytMostrar): ?>
        <section class="pub-secao">
            <h2 class="pub-secao__titulo">
                <?php if ($partida['youtube_status'] === 'ao_vivo'): ?><span class="pub-ponto"></span> Transmissão ao vivo<?php
                elseif ($partida['youtube_status'] === 'agendada'): ?>Transmissão programada<?php
                else: ?>Transmissão<?php endif; ?>
            </h2>
            <div class="pub-video">
                <iframe src="<?= escapar($ytEmbed) ?>" title="Transmissão da partida" loading="lazy"
                        allow="accelerometer; encrypted-media; picture-in-picture; fullscreen"
                        referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
            </div>
            <p class="mt-3 is-size-7">
                Se o vídeo não carregar, <a href="<?= escapar($ytLink) ?>" target="_blank" rel="noopener noreferrer">abra no YouTube</a>.
            </p>
        </section>
    <?php endif; ?>

    <section class="pub-secao">
        <h2 class="pub-secao__titulo">Informações</h2>
        <dl class="pub-info">
            <div><dt>Horário previsto</dt><dd><?= escapar(formatarDataHora($partida['inicio_previsto'])) ?></dd></div>
            <div><dt>Período</dt><dd><?= escapar(PERIODOS_ESCOLARES[$partida['periodo_escolar'] ?? ''] ?? '—') ?></dd></div>
            <div><dt>Local</dt><dd><?= escapar($partida['local_partida'] ?: 'A definir') ?></dd></div>
            <div><dt>Formato</dt><dd><?= (int) $partida['quantidade_periodos'] ?> período(s) de <?= (int) $partida['duracao_periodo_min'] ?> min</dd></div>
        </dl>
    </section>

    <section class="pub-secao">
        <h2 class="pub-secao__titulo">Linha do tempo</h2>
        <?php if ($eventos): ?>
            <ul class="pub-timeline">
                <?php foreach ($eventos as $e): ?>
                    <li>
                        <time><?= escapar(date('H:i', strtotime($e['criado_em']))) ?></time>
                        <span><?= escapar($e['descricao']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="pub-vazio">Ainda não há lances registrados.</p>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
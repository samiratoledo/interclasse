<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$aoVivo = $proximas = $resultados = [];
$erro = '';

try {
    $base = sqlPartidas();

    $aoVivo = $pdo->query($base . "
        WHERE p.status IN ('em_andamento', 'pausada')
        ORDER BY p.inicio_previsto ASC, p.id ASC
    ")->fetchAll();

    $proximas = $pdo->query($base . "
        WHERE p.status = 'agendada'
        ORDER BY p.inicio_previsto IS NULL, p.inicio_previsto ASC, p.id ASC
        LIMIT 8
    ")->fetchAll();

    $resultados = $pdo->query($base . "
        WHERE p.status = 'encerrada'
        ORDER BY p.fim_real DESC, p.id DESC
        LIMIT 6
    ")->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $erro = 'Não foi possível carregar as partidas agora. Tente novamente em instantes.';
}

$tituloPagina = 'Início';
$paginaAtual = 'inicio';
require __DIR__ . '/../includes/header.php';
?>

<section class="pub-hero">
    <div class="container">
        <h1 class="title is-2">Interclasse ETEC Zona Leste</h1>
        <p class="subtitle is-5">Acompanhe placares, horários e transmissões das partidas.</p>
        <div class="buttons">
            <a class="button is-link is-light" href="partidas.php">Ver todas as partidas</a>
            <a class="button is-link is-light" href="regulamento.php">Regulamento</a>
        </div>
    </div>
</section>

<div class="container pub-conteudo">
    <?php if ($erro !== ''): ?>
        <div class="notification is-danger is-light"><?= escapar($erro) ?></div>
    <?php endif; ?>

    <section class="pub-secao" id="ao-vivo">
        <h2 class="pub-secao__titulo"><span class="pub-ponto"></span> Ao vivo agora</h2>
        <?php if ($aoVivo): ?>
            <div class="pub-grade">
                <?php foreach ($aoVivo as $p): ?>
                    <?= cartaoPartida($p) ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="pub-vazio">Nenhuma partida em andamento neste momento.</p>
        <?php endif; ?>
    </section>

    <section class="pub-secao">
        <h2 class="pub-secao__titulo">Próximas partidas</h2>
        <?php if ($proximas): ?>
            <div class="pub-grade">
                <?php foreach ($proximas as $p): ?>
                    <?= cartaoPartida($p) ?>
                <?php endforeach; ?>
            </div>
            <p class="mt-4"><a href="partidas.php?status=agendada">Ver programação completa →</a></p>
        <?php else: ?>
            <p class="pub-vazio">Nenhuma partida agendada por enquanto.</p>
        <?php endif; ?>
    </section>

    <section class="pub-secao">
        <h2 class="pub-secao__titulo">Últimos resultados</h2>
        <?php if ($resultados): ?>
            <div class="pub-grade">
                <?php foreach ($resultados as $p): ?>
                    <?= cartaoPartida($p) ?>
                <?php endforeach; ?>
            </div>
            <p class="mt-4"><a href="partidas.php?status=encerrada">Ver todos os resultados →</a></p>
        <?php else: ?>
            <p class="pub-vazio">Ainda não há partidas encerradas.</p>
        <?php endif; ?>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
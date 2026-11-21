<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

function escaparRegulamento(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$secoes = [];
$erro = null;

try {
    $stmt = $pdo->query("
        SELECT titulo, slug, conteudo
        FROM regulamentos
        WHERE ativo = 1
        ORDER BY ordem ASC, titulo ASC
    ");

    $secoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $erro = 'Não foi possível carregar o regulamento.';
}

/*
 * Cabeçalho/rodapé:
 *  - visitante (sem login) -> cabeçalho público (includes/header.php), igual às outras páginas públicas;
 *  - usuário logado        -> cabeçalho do painel do seu perfil.
 * Cada include já abre/fecha <html>, <head>, <main> e <body>; a página não declara mais um segundo documento.
 */
$perfil = usuarioAtual()['perfil'] ?? null;

$painel = [
    'admin'         => 'admin',
    'arbitro'       => 'arbitro',
    'representante' => 'representante',
][$perfil] ?? null;

$tituloPagina = 'Regulamento';
$paginaAtual = 'regulamento';

if ($painel !== null) {
    require __DIR__ . '/../includes/' . $painel . '/header.php';
} else {
    require __DIR__ . '/../includes/header.php';
}
?>

<link rel="stylesheet" href="assets/regulamento.css?v=1">

<div class="reg-container" id="topo">

    <div class="reg-topo">
        <p class="reg-topo__escola">ETEC ZONA LESTE</p>
        <h1 class="title is-2">Regulamento Interclasse</h1>
        <p>Competir com respeito, crescer com o outro.</p>
    </div>

    <?php if ($erro !== null): ?>

        <div class="notification is-danger is-light"><?= escaparRegulamento($erro) ?></div>

    <?php elseif (empty($secoes)): ?>

        <div class="notification is-warning is-light">O regulamento ainda não foi publicado.</div>

    <?php else: ?>

        <div class="reg-layout">

            <aside class="reg-sidebar" aria-label="Seções do regulamento">
                <div class="reg-sidebar__box">
                    <p class="reg-sidebar__titulo">Navegação</p>
                    <nav class="reg-sidebar__links">
                        <?php foreach ($secoes as $secao): ?>
                            <a href="#<?= escaparRegulamento($secao['slug']) ?>"><?= escaparRegulamento($secao['titulo']) ?></a>
                        <?php endforeach; ?>
                    </nav>
                </div>
            </aside>

            <div class="reg-conteudo">
                <?php foreach ($secoes as $secao): ?>
                    <article class="reg-secao" id="<?= escaparRegulamento($secao['slug']) ?>">
                        <h2 class="title is-4"><?= escaparRegulamento($secao['titulo']) ?></h2>
                        <div class="reg-texto"><?= escaparRegulamento($secao['conteudo']) ?></div>
                        <div class="has-text-right mt-4">
                            <a href="#topo" class="reg-voltar">Voltar ao início ↑</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

        </div>

    <?php endif; ?>

</div>

<?php
if ($painel !== null) {
    require __DIR__ . '/../includes/' . $painel . '/footer.php';
} else {
    require __DIR__ . '/../includes/footer.php';
}
?>
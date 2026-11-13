<?php
/*
 * Cabeçalho das páginas públicas.
 * Antes de incluir, defina $tituloPagina e (opcional) $paginaAtual.
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$tituloPagina = $tituloPagina ?? 'Interclasse';
$paginaAtual  = $paginaAtual ?? '';
$usuarioLogado = usuarioAtual();
$painel = match ($usuarioLogado['perfil'] ?? null) {
    'admin'         => 'admin/index.php',
    'arbitro'       => 'arbitro/index.php',
    'representante' => 'representante/index.php',
    default         => null,
};
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escapar($tituloPagina) ?> | Interclasse ETEC Zona Leste</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css">
    <link rel="stylesheet" href="assets/publico.css?v=2">
</head>
<body class="pub-body">

<nav class="navbar pub-nav" role="navigation" aria-label="principal">
    <div class="container">
        <div class="navbar-brand">
            <a class="navbar-item pub-marca" href="index.php">
                <img src="assets/logo-etec-zl.png" alt="ETEC Zona Leste">
                <span>Interclasse ETEC Zona Leste</span>
            </a>
            <a role="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="menuPublico">
                <span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span>
            </a>
        </div>

        <div id="menuPublico" class="navbar-menu">
            <div class="navbar-end">
                <a class="navbar-item <?= $paginaAtual === 'inicio' ? 'is-active' : '' ?>" href="index.php">Início</a>
                <a class="navbar-item <?= $paginaAtual === 'partidas' ? 'is-active' : '' ?>" href="partidas.php">Partidas</a>
                <a class="navbar-item <?= $paginaAtual === 'regulamento' ? 'is-active' : '' ?>" href="regulamento.php">Regulamento</a>
                <div class="navbar-item">
                    <?php if ($painel): ?>
                        <a class="button is-light is-small pub-btn-painel" href="<?= escapar($painel) ?>">Meu painel</a>
                    <?php else: ?>
                        <a class="button is-light is-small pub-btn-painel" href="login.php">Entrar</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</nav>

<main class="pub-main">
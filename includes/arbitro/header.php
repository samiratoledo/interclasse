<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$baseUrl = '/interclasse-etec';
$nomeUsuario = $_SESSION['usuario']['nome'] ?? 'Árbitro';
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Interclasse ETEC Zona Leste</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f4f7fb;
            color: #243247;
            font-family: Arial, sans-serif;
        }

        .site-header {
            background: #0b2948;
            color: white;
        }

        .header-container {
            max-width: 1200px;
            margin: auto;
            padding: 12px 20px 0;
        }

        .header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        .branding {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo {
            width: 60px;
            height: 60px;
            object-fit: contain;
            border-radius: 8px;
            padding: 4px;
        }

        .brand-title {
            font-size: 18px;
            font-weight: bold;
            margin: 0;
        }

        .brand-subtitle {
            font-size: 12px;
            color: #d8e4f1;
            margin: 5px 0 0;
        }

        .profile-name {
            font-size: 12px;
            border: 1px solid #ffffff55;
            border-radius: 20px;
            padding: 8px 12px;
        }

        .main-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            padding: 12px 0;
        }

        .main-nav a {
            color: #edf4fc;
            text-decoration: none;
            padding: 9px 12px;
            border-radius: 7px;
            font-size: 14px;
        }

        .main-nav a:hover {
            background: #ffffff20;
        }

        .main-nav .logout {
            margin-left: auto;
            color: #ffe0dc;
        }

        .page-content {
            max-width: 1200px;
            min-height: 55vh;
            margin: 24px auto;
            padding: 0 20px;
        }

        @media (max-width: 600px) {
            .logo {
                width: 45px;
                height: 45px;
            }

            .brand-title {
                font-size: 15px;
            }

            .main-nav a {
                padding: 8px;
                font-size: 13px;
            }

            .main-nav .logout {
                margin-left: 0;
            }
        }
    </style>
</head>

<body>

    <header class="site-header">
        <div class="header-container">

            <div class="header-top">
                <div class="branding">

                    <img class="logo" src="<?= $baseUrl ?>/public/assets/logo-etec-zl.png" alt="Logo ETEC Zona Leste">

                    <div>
                        <p class="brand-title">Interclasse ETEC Zona Leste</p>
                        <p class="brand-subtitle">Painel de arbitragem</p>
                    </div>

                    <!-- <img class="logo" src="<?= $baseUrl ?>/public/assets/img/logo-prisma.png" alt="Logo Grêmio Prisma"> -->

                </div>

                <span class="profile-name">
                    Árbitro ·
                    <?= htmlspecialchars($nomeUsuario, ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>

            <nav class="main-nav">
                <a href="<?= $baseUrl ?>/public/arbitro/index.php">Painel</a>
                <a href="<?= $baseUrl ?>/public/arbitro/partidas.php">Partidas</a>
                <a class="logout" href="<?= $baseUrl ?>/public/logout.php">Sair</a>
            </nav>

        </div>
    </header>

    <main class="page-content"></main>
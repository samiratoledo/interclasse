<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$baseUrl = '/interclasse-etec';
$nomeUsuario = $_SESSION['usuario']['nome'] ?? 'Administrador';
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Interclasse ETEC Zona Leste</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/assets/style.css?v=2">

    <style>
        * {
            box-sizing: border-box;
        }


        html {
            min-height: 100%;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #1f3d63;
            color: #243247;
            font-family: Arial, sans-serif;
        }

        /* Footer sempre no final */
        .site-footer {
            width: 100%;
            margin-top: auto;
            background: #0b2948;
            border-top: 1px solid #d6e0eb;
            color: #d6e0eb;
            padding: 18px;
            text-align: center;
            font-size: 12px;
        }


        .site-header {
            background: #0b2948;
            color: white;
            padding: 0;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 8px 20px;
        }

        .header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .branding {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo {
            width: 42px;
            height: 42px;
            object-fit: contain;
            border-radius: 6px;
            padding: 3px;
        }

        .brand-title {
            font-size: 16px;
            font-weight: bold;
            margin: 0;
        }

        .brand-subtitle {
            font-size: 11px;
            color: #d8e4f1;
            margin: 2px 0 0;
        }

        .profile-name {
            font-size: 12px;
            border: 1px solid #ffffff55;
            border-radius: 20px;
            padding: 6px 10px;
        }

        .main-nav {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 4px;
            padding-top: 6px;
        }

        .main-nav a {
            color: #edf4fc;
            text-decoration: none;
            padding: 7px 10px;
            border-radius: 6px;
            font-size: 13px;
        }

        .main-nav a:hover {
            background: #ffffff20;
            color: white;
        }

        .main-nav .logout {
            margin-left: auto;
            color: #ffe0dc;
        }

        .page-content {
            flex: 1;
            width: 100%;
            max-width: 1200px;
            min-height: 0;
            margin: 12px auto;
            padding: 0 20px;
        }

        .box {
            background: #0b2948;
        }

        @media (max-width: 600px) {
            .header-container {
                padding: 8px 12px;
            }

            .header-top {
                gap: 8px;
            }

            .logo {
                width: 34px;
                height: 34px;
            }

            .branding {
                gap: 6px;
            }

            .brand-title {
                font-size: 13px;
            }

            .brand-subtitle {
                font-size: 10px;
            }

            .profile-name {
                font-size: 11px;
            }

            .main-nav a {
                padding: 7px;
                font-size: 12px;
            }

            .main-nav .logout {
                margin-left: 0;
            }

            .page-content {
                padding: 0 12px;
                margin: 10px auto;
            }
        }
    </style>
</head>

<body>
    <header class="site-header">
        <div class="header-container">
            <div class="header-top">
                <div class="branding">
                    <img class="logo" src="<?= $baseUrl ?>/public/assets/logo-etec-zl.png"
                        alt="Logo ETEC Zona Leste">

                    <div>
                        <p class="brand-title">Interclasse ETEC Zona Leste</p>
                        <p class="brand-subtitle">Painel administrativo</p>
                    </div>

                    <!-- <img class="logo" src="<?= $baseUrl ?>/public/assets/img/logo-prisma.png" alt="Logo Grêmio Prisma"> -->
                </div>

                <span class="profile-name">
                    Administração ·
                    <?= htmlspecialchars($nomeUsuario, ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>

            <nav class="main-nav">
                <a href="<?= $baseUrl ?>/public/admin/index.php">Painel</a>
                <a href="<?= $baseUrl ?>/public/admin/turmas.php">Turmas</a>
                <a href="<?= $baseUrl ?>/public/admin/modalidades.php">Modalidades</a>
                <a href="<?= $baseUrl ?>/public/admin/inscricoes.php">Inscrições</a>
                <a href="<?= $baseUrl ?>/public/admin/aprovar-atletas.php">Aprovar atletas</a>
                <a href="<?= $baseUrl ?>/public/admin/representantes.php">Representantes</a>
                <a href="<?= $baseUrl ?>/public/admin/partidas.php">Partidas</a>
                <a class="logout" href="<?= $baseUrl ?>/public/logout.php">Sair</a>
            </nav>
        </div>
    </header>

    <main class="page-content">
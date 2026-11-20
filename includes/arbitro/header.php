<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$baseUrl = urlBase();
$nomeUsuario = $_SESSION['usuario']['nome'] ?? 'Árbitro';
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
            background: #fffafa;
            color: #2b1c1c;
            font-family: 'Montserrat', sans-serif;
        }

        /* Cabeçalho */

        .site-header {
            background: #c62828;
            color: #ffffff;
            box-shadow: 0 3px 12px #0002;
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 12px 20px 0;
        }

        .header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .branding {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo {
            width: 48px;
            height: 48px;
            object-fit: contain;
            border-radius: 8px;
            padding: 3px;
            background: #ffffff;
        }

        .brand-title {
            font-size: 17px;
            font-weight: 800;
            margin: 0;
            color: #ffffff;
        }

        .brand-subtitle {
            font-size: 11px;
            color: #ffd9d9;
            margin: 3px 0 0;
        }

        .profile-name {
            font-size: 12px;
            font-weight: 600;
            border: 1px solid #ffffff88;
            border-radius: 20px;
            padding: 8px 12px;
            color: #ffffff;
        }

        /* Navegação */

        .main-nav {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 5px;
            padding: 12px 0;
        }

        .main-nav a,
        .main-nav .logout {
            color: #ffffff;
            text-decoration: none;
            padding: 9px 12px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 600;
            transition: background .2s;
        }

        .main-nav a:hover,
        .main-nav .logout:hover {
            background: #ffffff30;
            color: #ffffff;
        }

        .logout-form {
            margin-left: auto;
        }

        .main-nav .logout {
            background: transparent;
            border: none;
            cursor: pointer;
            font-family: inherit;
        }

        /* Conteúdo */

        .page-content {
            flex: 1;
            width: 100%;
            max-width: 1200px;
            min-height: 0;
            margin: 24px auto;
            padding: 0 20px;
        }

        .box {
            background: #ffffff;
            border: 1px solid #f3d6d6;
            box-shadow: 0 3px 12px #0000000a;
            border-radius: 12px;
        }

        .title {
            color: #2b1c1c;
        }

        .subtitle {
            color: #7a5353;
        }

        /* Botões */

        .button.is-primary {
            background: #c62828;
            border-color: #c62828;
            color: #ffffff;
            font-weight: 700;
        }

        .button.is-primary:hover {
            background: #a31f1f;
            border-color: #a31f1f;
            color: #ffffff;
        }

        .button.is-link {
            background: #7a1c1c;
            border-color: #7a1c1c;
            color: #ffffff;
        }

        .button.is-link:hover {
            background: #5c1515;
            color: #ffffff;
        }

        .button.is-link.is-light {
            background: #fde4e4;
            border-color: #fde4e4;
            color: #8a1f1f;
        }

        .button.is-link.is-light:hover {
            background: #f9c9c9;
        }

        /* Rodapé */

        .site-footer {
            width: 100%;
            align-self: stretch;
            margin-top: auto;
            background: #2b1c1c;
            color: #f0dcdc;
            padding: 18px;
            text-align: center;
            font-size: 12px;
            border-top: 1px solid #f0dcdc;
        }

        .site-footer strong {
            color: #ff7a7a;
        }

        @media (max-width: 600px) {
            .header-container {
                padding: 10px 12px 0;
            }

            .logo {
                width: 38px;
                height: 38px;
            }

            .brand-title {
                font-size: 13px;
            }

            .brand-subtitle {
                font-size: 10px;
            }

            .profile-name {
                font-size: 10px;
                padding: 6px 9px;
            }

            .main-nav {
                gap: 3px;
            }

            .main-nav a,
            .main-nav .logout {
                padding: 7px;
                font-size: 11px;
            }

            .logout-form {
                margin-left: 0;
            }

            .page-content {
                padding: 0 12px;
                margin: 16px auto;
            }
        }
    </style>
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/assets/mobile.css?v=1">
</head>

<body class="painel-arbitro">

    <header class="site-header">

        <div class="header-container">

            <div class="header-top">

                <div class="branding">

                    <img class="logo" src="<?= $baseUrl ?>/public/assets/logo-etec-zl.png" alt="Logo ETEC Zona Leste">

                    <div>
                        <p class="brand-title">
                            Interclasse ETEC Zona Leste
                        </p>

                        <p class="brand-subtitle">
                            Painel de arbitragem
                        </p>
                    </div>

                </div>

                <span class="profile-name">
                    Árbitro ·
                    <?= htmlspecialchars($nomeUsuario, ENT_QUOTES, 'UTF-8') ?>
                </span>

            </div>

            <nav class="main-nav">

                <a href="<?= $baseUrl ?>/public/arbitro/index.php">
                    Painel
                </a>

                <form action="<?= $baseUrl ?>/public/logout.php" method="POST" class="logout-form">

                    <input type="hidden" name="token_csrf"
                        value="<?= htmlspecialchars(gerarTokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">

                    <button type="submit" class="logout">
                        Sair
                    </button>

                </form>

            </nav>

        </div>

    </header>

    <main class="page-content">
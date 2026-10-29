<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

function escaparRegulamento(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

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
    $secoes = [];
    $erro = 'Não foi possível carregar o regulamento.';
}

$usuario = usuarioAtual();
$perfil = $usuario['perfil'] ?? null;

$headers = [
    'admin' => [
        'header' => __DIR__ . '/../includes/admin/header.php',
        'footer' => __DIR__ . '/../includes/admin/footer.php',
    ],
    'arbitro' => [
        'header' => __DIR__ . '/../includes/arbitro/header.php',
        'footer' => __DIR__ . '/../includes/arbitro/footer.php',
    ],
    'representante' => [
        'header' => __DIR__ . '/../includes/representante/header.php',
        'footer' => __DIR__ . '/../includes/representante/footer.php',
    ],
];

$headerPerfil = $headers[$perfil]['header'] ?? null;
$footerPerfil = $headers[$perfil]['footer'] ?? null;
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Regulamento | Interclasse</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.2/css/bulma.min.css">

    <style>
        html {
            scroll-behavior: smooth;
        }

        body {
            background: #f5f7fa;
            min-height: 100vh;
        }

        .regulamento-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .regulamento-topo {
            background: #0b2948;
            color: white;
            padding: 3rem 1.5rem;
            border-radius: 12px;
        }

        .regulamento-topo h1,
        .regulamento-topo p {
            color: white;
        }

        /* Layout principal */
        .regulamento-layout {
            display: flex;
            align-items: stretch;
            gap: 1.5rem;
            height: 70vh;
            min-height: 400px;
            overflow: hidden;
        }

        /* Sidebar com rolagem independente */
        .regulamento-sidebar {
            flex: 0 0 25%;
            max-width: 25%;
            height: 100%;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 0 8px 12px 0;
        }

        .sidebar-box {
            background: white;
            padding: 1.2rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .05);
        }

        .sidebar-titulo {
            color: #0b2948;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .sidebar-links {
            display: flex;
            flex-direction: column;
            gap: .3rem;
        }

        .sidebar-links a {
            display: block;
            padding: .65rem .8rem;
            border-radius: 6px;
            color: #4a5568;
            font-size: .9rem;
            transition: .2s;
        }

        .sidebar-links a:hover {
            background: #edf2f7;
            color: #0b2948;
        }

        .sidebar-links a:focus {
            background: #e5edf5;
            color: #0b2948;
        }

        /* Área do regulamento com rolagem independente */
        .regulamento-conteudo {
            flex: 1;
            min-width: 0;
            height: 100%;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 0 8px 20px 0;
            scroll-behavior: smooth;
        }

        .secao-regulamento {
            scroll-margin-top: 15px;
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .04);
        }

        .secao-regulamento h2 {
            color: #0b2948;
            border-bottom: 2px solid #e8edf2;
            padding-bottom: .8rem;
            margin-bottom: 1.2rem;
        }

        .conteudo-regulamento {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            line-height: 1.8;
        }

        .voltar-topo {
            color: #3273dc;
            font-size: .85rem;
        }

        /* Rolagem em telas menores */
        @media (max-width: 768px) {
            .regulamento-topo {
                padding: 2rem 1rem;
            }

            .regulamento-layout {
                flex-direction: column;
                height: 75vh;
                min-height: 450px;
                gap: 1rem;
            }

            .regulamento-sidebar {
                flex: 0 0 180px;
                max-width: 100%;
                width: 100%;
                height: 180px;
                padding: 0;
            }

            .sidebar-links {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .regulamento-conteudo {
                flex: 1;
                width: 100%;
                min-height: 0;
                padding: 0 4px 15px 0;
            }

            .secao-regulamento {
                padding: 1.2rem;
            }
        }
    </style>
</head>

<body>

    <?php
    if ($headerPerfil !== null && file_exists($headerPerfil)) {
        require_once $headerPerfil;
    }
    ?>

    <section class="section" id="topo">

        <div class="regulamento-container">

            <!-- Cabeçalho -->

            <div class="regulamento-topo has-text-centered mb-5">

                <p class="is-size-6 mb-2">
                    ETEC DA ZONA LESTE
                </p>

                <h1 class="title is-2">
                    Regulamento Interclasse
                </h1>

                <p>
                    Competir com respeito, crescer com o outro.
                </p>

            </div>

            <?php if (isset($erro)): ?>

                <div class="notification is-danger is-light">
                    <?= escaparRegulamento($erro) ?>
                </div>

            <?php elseif (empty($secoes)): ?>

                <div class="notification is-warning is-light">
                    O regulamento ainda não foi publicado.
                </div>

            <?php else: ?>

                <div class="regulamento-layout">

                    <!-- Sidebar -->

                    <aside class="regulamento-sidebar">

                        <div class="sidebar-box">

                            <p class="sidebar-titulo">
                                Navegação
                            </p>

                            <nav class="sidebar-links">

                                <?php foreach ($secoes as $secao): ?>

                                    <a href="#<?= escaparRegulamento($secao['slug']) ?>">
                                        <?= escaparRegulamento($secao['titulo']) ?>
                                    </a>

                                <?php endforeach; ?>

                            </nav>

                        </div>

                    </aside>

                    <!-- Conteúdo -->

                    <main class="regulamento-conteudo">

                        <?php foreach ($secoes as $secao): ?>

                            <article
                                class="secao-regulamento"
                                id="<?= escaparRegulamento($secao['slug']) ?>">

                                <h2 class="title is-4">
                                    <?= escaparRegulamento($secao['titulo']) ?>
                                </h2>

                                <div class="conteudo-regulamento">
                                    <?= escaparRegulamento($secao['conteudo']) ?>
                                </div>

                                <div class="has-text-right mt-4">

                                    <a href="#topo" class="voltar-topo">
                                        Voltar ao início ↑
                                    </a>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </main>

                </div>

            <?php endif; ?>

        </div>

    </section>

    <?php
    if ($footerPerfil !== null && file_exists($footerPerfil)) {
        require_once $footerPerfil;
    }
    ?>

</body>

</html>

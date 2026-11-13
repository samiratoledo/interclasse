<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

exigirPerfil('admin', 'arbitro');

$usuario = usuarioAtual();
$usuarioId = (int) $usuario['id'];
$ehAdmin = ($usuario['perfil'] ?? '') === 'admin';

$partidas = [];
$erro = '';

try {
    // Árbitro vê só as partidas em que é responsável; admin vê todas.
    $filtro = $ehAdmin ? '' : 'AND p.responsavel_id = :usuario';

    $stmt = $pdo->prepare(
        sqlPartidas() . "
        WHERE p.status <> 'cancelada' $filtro
        ORDER BY
            FIELD(p.status, 'em_andamento', 'pausada', 'agendada', 'encerrada'),
            p.inicio_previsto IS NULL, p.inicio_previsto ASC, p.id ASC
        LIMIT 100"
    );
    $stmt->execute($ehAdmin ? [] : ['usuario' => $usuarioId]);
    $partidas = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $erro = 'Não foi possível carregar as partidas.';
}

$grupos = [
    'Em andamento' => [],
    'Próximas'     => [],
    'Encerradas'   => [],
];
foreach ($partidas as $p) {
    if (in_array($p['status'], ['em_andamento', 'pausada'], true)) {
        $grupos['Em andamento'][] = $p;
    } elseif ($p['status'] === 'agendada') {
        $grupos['Próximas'][] = $p;
    } else {
        $grupos['Encerradas'][] = $p;
    }
}

if ($ehAdmin) {
    require_once __DIR__ . '/../../includes/admin/header.php';
} else {
    require_once __DIR__ . '/../../includes/arbitro/header.php';
}
?>

<style>
    .arb-titulo {
        margin: 0 0 4px;
        font-size: 28px;
        font-weight: 800;
        color: #2b1c1c;
    }

    .arb-sub {
        margin: 0 0 24px;
        font-size: 15px;
        color: #2b1c1c;
    }

    .arb-grupo {
        margin-bottom: 28px;
    }

    .arb-grupo h2 {
        font-size: 18px;
        font-weight: 700;
        margin: 0 0 12px;
        color: #2b1c1c;
    }

    .arb-lista {
        display: grid;
        gap: 12px;
    }

    .arb-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
        background: #ffffff;
        border: 1px solid #f3d6d6;
        box-shadow: 0 3px 12px #0000000a;
        border-radius: 12px;
        padding: 16px 20px;
    }

    .arb-item__info {
        flex: 1;
        min-width: 220px;
    }

    .arb-item__info strong {
        display: block;
        font-weight: 700;
        color: #2b1c1c;
    }

    .arb-item__info span {
        font-size: 13px;
        color: #7a5353;
    }

    .arb-placar {
        font-size: 22px;
        font-weight: 800;
        color: #c62828;
    }

    .arb-btn {
        display: inline-block;
        padding: 9px 18px;
        border-radius: 8px;
        text-decoration: none;
        background: #c62828;
        color: #ffffff;
        font-size: 14px;
        font-weight: 700;
        transition: background .2s;
    }

    .arb-btn:hover {
        background: #a31f1f;
        color: #ffffff;
    }

    .arb-vazio {
        color: #7a5353;
        font-style: italic;
    }

    .arb-erro {
        background: #fde4e4;
        color: #8a1f1f;
        padding: 12px 16px;
        border-radius: 8px;
    }

    @media (max-width: 600px) {
        .arb-titulo {
            font-size: 22px;
        }

        .arb-item {
            padding: 14px;
        }

        .arb-btn {
            width: 100%;
            text-align: center;
        }
    }
</style>

<?php if ($ehAdmin): ?>
<style>
    /* Cores de texto apenas para o admin (fundo escuro) */
    .arb-titulo {
        color: #ffffff;
    }

    .arb-sub{
        color: #b8c4d4;
    }
    .arb-vazio {
        color: #b8c4d4;
    }

    .arb-grupo h2 {
        color: #ffffff;
    }
</style>
<?php endif; ?>

<h1 class="arb-titulo"><?= $ehAdmin ? 'Partidas' : 'Minhas partidas' ?></h1>
<p class="arb-sub">Escolha uma partida para controlar o cronômetro, o placar e os lances.</p>

<?php if ($erro !== ''): ?>
    <p class="arb-erro"><?= escapar($erro) ?></p>
<?php endif; ?>

<?php foreach ($grupos as $titulo => $lista): ?>
    <section class="arb-grupo">
        <h2><?= escapar($titulo) ?> (<?= count($lista) ?>)</h2>

        <?php if (!$lista): ?>
            <p class="arb-vazio">
                <?= $titulo === 'Em andamento' ? 'Nenhuma partida em andamento.'
                    : ($titulo === 'Próximas' ? 'Nenhuma partida agendada para você.' : 'Nenhuma partida encerrada.') ?>
            </p>
        <?php else: ?>
            <div class="arb-lista">
                <?php foreach ($lista as $p): ?>
                    <div class="arb-item">
                        <div class="arb-item__info">
                            <strong><?= escapar($p['casa_nome']) ?> × <?= escapar($p['visitante_nome']) ?></strong>
                            <span>
                                <?= escapar($p['modalidade_nome']) ?> ·
                                <?= escapar(formatarDataHora($p['inicio_previsto'])) ?>
                                <?= $p['local_partida'] ? ' · ' . escapar($p['local_partida']) : '' ?>
                                · <?= escapar(STATUS_PARTIDA[$p['status']][0] ?? $p['status']) ?>
                            </span>
                        </div>
                        <?php if ($p['status'] !== 'agendada'): ?>
                            <span class="arb-placar"><?= (int) $p['placar_casa'] ?> – <?= (int) $p['placar_visitante'] ?></span>
                        <?php endif; ?>
                        <a class="arb-btn" href="partida.php?id=<?= (int) $p['id'] ?>">
                            <?= $p['status'] === 'encerrada' ? 'Ver' : 'Controlar' ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endforeach; ?>

<?php
if ($ehAdmin) {
    require_once __DIR__ . '/../../includes/admin/footer.php';
} else {
    require_once __DIR__ . '/../../includes/arbitro/footer.php';
}
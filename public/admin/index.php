<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

exigirPerfil('admin');

$admin = usuarioAtual();

$estatisticas = [
    'turmas' => 0,
    'modalidades' => 0,
    'partidas' => 0,
    'usuarios' => 0
];

$partidasRecentes = [];
$erro = '';

// Carrega cada estatística de forma independente.
$consultas = [
    'turmas' => 'SELECT COUNT(*) FROM turmas WHERE ativa = 1',
    'modalidades' => 'SELECT COUNT(*) FROM modalidades WHERE ativa = 1',
    'partidas' => "SELECT COUNT(*) FROM partidas
                   WHERE status IN ('agendada', 'em_andamento', 'pausada')",
    'usuarios' => 'SELECT COUNT(*) FROM usuarios WHERE ativo = 1'
];

foreach ($consultas as $chave => $sql) {
    try {
        $estatisticas[$chave] = (int) $pdo
            ->query($sql)
            ->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erro na estatística {$chave}: " . $e->getMessage());
        $erro = 'Não foi possível carregar algumas estatísticas.';
    }
}

// Carrega as partidas recentes separadamente.
try {
    $sql = "
        SELECT
            p.id,
            p.inicio_previsto,
            p.status,
            p.placar_casa,
            p.placar_visitante,
            m.nome AS modalidade,
            tc.nome AS turma_casa,
            tv.nome AS turma_visitante
        FROM partidas p
        INNER JOIN modalidades m ON m.id = p.modalidade_id
        INNER JOIN turmas tc ON tc.id = p.turma_casa_id
        INNER JOIN turmas tv ON tv.id = p.turma_visitante_id
        ORDER BY
            CASE
                WHEN p.status = 'em_andamento' THEN 0
                WHEN p.status = 'pausada' THEN 1
                WHEN p.status = 'agendada' THEN 2
                ELSE 3
            END,
            p.inicio_previsto IS NULL,
            p.inicio_previsto ASC
        LIMIT 5
    ";

    $partidasRecentes = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Erro ao carregar partidas recentes: ' . $e->getMessage());
    $erro = 'Não foi possível carregar as partidas recentes.';
}

function escapar(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function nomeStatus(string $status): string
{
    return match ($status) {
        'agendada' => 'Agendada',
        'em_andamento' => 'Em andamento',
        'pausada' => 'Pausada',
        'encerrada' => 'Encerrada',
        'cancelada' => 'Cancelada',
        default => 'Desconhecido',
    };
}

require_once __DIR__ . '/../../includes/admin/header.php';
?>

<div class="mb-5">
    <h1 class="title is-3">Painel administrativo</h1>

    <p class="subtitle is-6">
        Gerencie as informações do Interclasse ETEC.
    </p>
</div>

<?php if ($erro !== ''): ?>
    <div class="notification is-danger is-light">
        <?= escapar($erro) ?>
    </div>
<?php endif; ?>

<div class="columns is-multiline">

    <div class="column is-6-tablet is-3-desktop">
        <div class="box">
            <p class="heading">Turmas ativas</p>
            <p class="title is-3"><?= $estatisticas['turmas'] ?></p>
            <a href="turmas.php">Gerenciar turmas</a>
        </div>
    </div>

    <div class="column is-6-tablet is-3-desktop">
        <div class="box">
            <p class="heading">Modalidades ativas</p>
            <p class="title is-3"><?= $estatisticas['modalidades'] ?></p>
            <a href="modalidades.php">Gerenciar modalidades</a>
        </div>
    </div>

    <div class="column is-6-tablet is-3-desktop">
        <div class="box">
            <p class="heading">Partidas pendentes ou ao vivo</p>
            <p class="title is-3"><?= $estatisticas['partidas'] ?></p>
            <a href="partidas.php">Gerenciar partidas</a>
        </div>
    </div>

    <div class="column is-6-tablet is-3-desktop">
        <div class="box">
            <p class="heading">Usuários ativos</p>
            <p class="title is-3"><?= $estatisticas['usuarios'] ?></p>
        </div>
    </div>

</div>

<section class="box mt-5">
    <h2 class="title is-5">Partidas prioritárias</h2>

    <?php if (empty($partidasRecentes)): ?>

        <p class="has-text-grey">
            Nenhuma partida cadastrada ainda.
        </p>

    <?php else: ?>

        <div class="table-container">
            <table class="table is-fullwidth is-striped is-hoverable">
                <thead>
                    <tr>
                        <th>Modalidade</th>
                        <th>Confronto</th>
                        <th>Placar</th>
                        <th>Data prevista</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($partidasRecentes as $partida): ?>
                        <tr>
                            <td>
                                <?= escapar($partida['modalidade']) ?>
                            </td>

                            <td>
                                <?= escapar($partida['turma_casa']) ?>
                                <span class="has-text-grey"> x </span>
                                <?= escapar($partida['turma_visitante']) ?>
                            </td>

                            <td>
                                <?= (int) $partida['placar_casa'] ?>
                                -
                                <?= (int) $partida['placar_visitante'] ?>
                            </td>

                            <td>
                                <?php if ($partida['inicio_previsto']): ?>
                                    <?= escapar(
                                        date(
                                            'd/m/Y H:i',
                                            strtotime($partida['inicio_previsto'])
                                        )
                                    ) ?>
                                <?php else: ?>
                                    Não definida
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= escapar(nomeStatus($partida['status'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

    <a class="button is-link is-light mt-3" href="partidas.php">
        Ver todas as partidas
    </a>
</section>

<?php
require_once __DIR__ . '/../../includes/admin/footer.php';
?>
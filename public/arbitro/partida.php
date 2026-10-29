<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Autorização
|--------------------------------------------------------------------------
| O administrador também pode controlar partidas.
*/

$usuario = usuarioAtual();

if (
    !$usuario ||
    !in_array($usuario['perfil'] ?? '', ['admin', 'arbitro'], true)
) {
    http_response_code(403);
    exit('Acesso permitido somente para administradores e árbitros.');
}

$usuarioId = (int) $usuario['id'];
$ehAdmin = ($usuario['perfil'] ?? '') === 'admin';

/*
|--------------------------------------------------------------------------
| Validação da partida
|--------------------------------------------------------------------------
*/

$partidaId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$partidaId || $partidaId < 1) {
    http_response_code(400);
    exit('Partida inválida.');
}

$stmt = $pdo->prepare("
    SELECT
        p.*,
        m.nome AS modalidade_nome,
        tc.nome AS turma_casa_nome,
        tv.nome AS turma_visitante_nome
    FROM partidas p
    INNER JOIN modalidades m
        ON m.id = p.modalidade_id
    INNER JOIN turmas tc
        ON tc.id = p.turma_casa_id
    INNER JOIN turmas tv
        ON tv.id = p.turma_visitante_id
    WHERE p.id = ?
    LIMIT 1
");

$stmt->execute([$partidaId]);
$partida = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$partida) {
    http_response_code(404);
    exit('Partida não encontrada.');
}

/*
|--------------------------------------------------------------------------
| Token CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_partida_arbitro'])) {
    $_SESSION['csrf_partida_arbitro'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_partida_arbitro'];

$mensagem = '';
$erro = '';

/*
|--------------------------------------------------------------------------
| Funções auxiliares
|--------------------------------------------------------------------------
*/

function escapar(mixed $valor): string
{
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        'UTF-8'
    );
}

function registrarOcorrencia(
    PDO $pdo,
    int $partidaId,
    int $usuarioId,
    string $tipo,
    string $descricao
): void {
    $stmt = $pdo->prepare("
        INSERT INTO eventos_partida
            (partida_id, usuario_id, tipo, descricao)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([
        $partidaId,
        $usuarioId,
        $tipo,
        $descricao
    ]);
}

/*
|--------------------------------------------------------------------------
| Inicializar o estado persistente
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    INSERT IGNORE INTO estado_partida (partida_id)
    VALUES (?)
");

$stmt->execute([$partidaId]);

/*
|--------------------------------------------------------------------------
| Processamento das ações
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenRecebido = $_POST['csrf_token'] ?? '';

    if (
        !is_string($tokenRecebido) ||
        !hash_equals($csrf, $tokenRecebido)
    ) {
        $erro = 'Sessão de segurança inválida. Atualize a página.';
    } else {
        $acao = $_POST['acao'] ?? '';

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT *
                FROM partidas
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$partidaId]);
            $partidaAtual = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$partidaAtual) {
                throw new RuntimeException('Partida não encontrada.');
            }

            $stmt = $pdo->prepare("
                SELECT *
                FROM estado_partida
                WHERE partida_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$partidaId]);
            $estado = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$estado) {
                throw new RuntimeException(
                    'Não foi possível carregar o cronômetro.'
                );
            }

            $fase = $estado['fase'];

            /*
             * Atualiza o tempo acumulado antes de qualquer
             * ação que possa interromper ou alterar o relógio.
             */
            $segundos = (int) $estado['segundos_acumulados'];

            if ($estado['cronometro_iniciado_em'] !== null) {
                $inicio = strtotime(
                    $estado['cronometro_iniciado_em']
                );

                if ($inicio !== false) {
                    $segundos += max(0, time() - $inicio);
                }
            }

            $novoInicio = $estado['cronometro_iniciado_em'] !== null
                ? date('Y-m-d H:i:s')
                : null;

            $novoPeriodo = (int) $estado['periodo_atual'];
            $novaFase = $fase;

            switch ($acao) {
                case 'iniciar':
                    if ($partidaAtual['status'] === 'cancelada') {
                        throw new RuntimeException(
                            'Uma partida cancelada não pode ser iniciada.'
                        );
                    }

                    if ($fase === 'encerrado') {
                        throw new RuntimeException(
                            'A partida já foi encerrada.'
                        );
                    }

                    if ($fase !== 'intervalo') {
                        $novoInicio = date('Y-m-d H:i:s');
                    }

                    if ($fase === 'intervalo') {
                        $novaFase = 'jogo';
                        $novoInicio = date('Y-m-d H:i:s');
                    }

                    if ($partidaAtual['inicio_real'] === null) {
                        $stmt = $pdo->prepare("
                            UPDATE partidas
                            SET inicio_real = NOW(),
                                status = 'em_andamento'
                            WHERE id = ?
                        ");
                        $stmt->execute([$partidaId]);
                    } elseif (
                        $partidaAtual['status'] === 'pausada'
                    ) {
                        $stmt = $pdo->prepare("
                            UPDATE partidas
                            SET status = 'em_andamento'
                            WHERE id = ?
                        ");
                        $stmt->execute([$partidaId]);
                    }

                    registrarOcorrencia(
                        $pdo,
                        $partidaId,
                        $usuarioId,
                        'cronometro',
                        'Cronômetro iniciado ou retomado.'
                    );
                    break;

                case 'pausar':
                    if ($estado['cronometro_iniciado_em'] === null) {
                        throw new RuntimeException(
                            'O cronômetro já está pausado.'
                        );
                    }

                    $stmt = $pdo->prepare("
                        UPDATE partidas
                        SET status = 'pausada'
                        WHERE id = ?
                          AND status = 'em_andamento'
                    ");
                    $stmt->execute([$partidaId]);

                    registrarOcorrencia(
                        $pdo,
                        $partidaId,
                        $usuarioId,
                        'cronometro',
                        'Cronômetro pausado.'
                    );
                    break;

                case 'proximo_periodo':
                    if ($estado['cronometro_iniciado_em'] !== null) {
                        throw new RuntimeException(
                            'Pause o cronômetro antes de avançar o período.'
                        );
                    }

                    if ($fase === 'encerrado') {
                        throw new RuntimeException(
                            'A partida já foi encerrada.'
                        );
                    }

                    if (
                        $novoPeriodo >=
                        (int) $partidaAtual['quantidade_periodos']
                    ) {
                        throw new RuntimeException(
                            'Todos os períodos já foram concluídos.'
                        );
                    }

                    $novoPeriodo++;
                    $segundos = 0;
                    $novaFase = 'intervalo';

                    registrarOcorrencia(
                        $pdo,
                        $partidaId,
                        $usuarioId,
                        'periodo',
                        'Avanço para o período ' . $novoPeriodo . '.'
                    );
                    break;

                case 'retomar_periodo':
                    if ($fase !== 'intervalo') {
                        throw new RuntimeException(
                            'A partida não está no intervalo.'
                        );
                    }

                    $novaFase = 'jogo';
                    $novoInicio = date('Y-m-d H:i:s');

                    $stmt = $pdo->prepare("
                        UPDATE partidas
                        SET status = 'em_andamento'
                        WHERE id = ?
                    ");
                    $stmt->execute([$partidaId]);

                    registrarOcorrencia(
                        $pdo,
                        $partidaId,
                        $usuarioId,
                        'periodo',
                        'Início do período ' . $novoPeriodo . '.'
                    );
                    break;

                case 'gol_casa':
                case 'gol_visitante':
                    if ($fase === 'encerrado') {
                        throw new RuntimeException(
                            'Não é possível alterar o placar após o encerramento.'
                        );
                    }

                    if ($acao === 'gol_casa') {
                        $stmt = $pdo->prepare("
                            UPDATE partidas
                            SET placar_casa = placar_casa + 1
                            WHERE id = ?
                        ");
                        $descricao = 'Gol da equipe da casa.';
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE partidas
                            SET placar_visitante = placar_visitante + 1
                            WHERE id = ?
                        ");
                        $descricao = 'Gol da equipe visitante.';
                    }

                    $stmt->execute([$partidaId]);

                    registrarOcorrencia(
                        $pdo,
                        $partidaId,
                        $usuarioId,
                        'gol',
                        $descricao
                    );
                    break;

                case 'remover_gol_casa':
                case 'remover_gol_visitante':
                    if ($fase === 'encerrado') {
                        throw new RuntimeException(
                            'A partida já foi encerrada.'
                        );
                    }

                    if ($acao === 'remover_gol_casa') {
                        $stmt = $pdo->prepare("
                            UPDATE partidas
                            SET placar_casa = GREATEST(placar_casa - 1, 0)
                            WHERE id = ?
                        ");
                        $descricao = 'Correção: gol removido da equipe da casa.';
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE partidas
                            SET placar_visitante =
                                GREATEST(placar_visitante - 1, 0)
                            WHERE id = ?
                        ");
                        $descricao = 'Correção: gol removido da equipe visitante.';
                    }

                    $stmt->execute([$partidaId]);

                    registrarOcorrencia(
                        $pdo,
                        $partidaId,
                        $usuarioId,
                        'correcao_placar',
                        $descricao
                    );
                    break;

                case 'ocorrencia':
                    if ($fase === 'encerrado') {
                        throw new RuntimeException(
                            'A partida já foi encerrada.'
                        );
                    }

                    $descricao = trim(
                        (string) ($_POST['descricao'] ?? '')
                    );

                    if ($descricao === '') {
                        throw new RuntimeException(
                            'Digite a descrição da ocorrência.'
                        );
                    }

                    if (mb_strlen($descricao) > 2000) {
                        throw new RuntimeException(
                            'A ocorrência deve ter no máximo 2000 caracteres.'
                        );
                    }

                    registrarOcorrencia(
                        $pdo,
                        $partidaId,
                        $usuarioId,
                        'ocorrencia',
                        $descricao
                    );
                    break;

                case 'encerrar':
                    if ($estado['cronometro_iniciado_em'] !== null) {
                        throw new RuntimeException(
                            'Pause o cronômetro antes de encerrar.'
                        );
                    }

                    $novaFase = 'encerrado';
                    $novoInicio = null;

                    $stmt = $pdo->prepare("
                        UPDATE partidas
                        SET status = 'encerrada',
                            fim_real = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$partidaId]);

                    registrarOcorrencia(
                        $pdo,
                        $partidaId,
                        $usuarioId,
                        'encerramento',
                        'Partida encerrada.'
                    );
                    break;

                default:
                    throw new RuntimeException(
                        'Ação não reconhecida.'
                    );
            }

            /*
             * Em uma pausa, conserva o tempo acumulado e
             * limpa o horário de início do cronômetro.
             */
            if (
                $acao === 'pausar' ||
                $acao === 'proximo_periodo' ||
                $acao === 'encerrar'
            ) {
                $novoInicio = null;
            }

            $stmt = $pdo->prepare("
                UPDATE estado_partida
                SET periodo_atual = ?,
                    segundos_acumulados = ?,
                    cronometro_iniciado_em = ?,
                    fase = ?
                WHERE partida_id = ?
            ");

            $stmt->execute([
                $novoPeriodo,
                $segundos,
                $novoInicio,
                $novaFase,
                $partidaId
            ]);

            $pdo->commit();

            /*
             * Redireciona para evitar repetir a ação
             * caso a página seja atualizada.
             */
            header(
                'Location: partida.php?id=' . $partidaId
            );
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $erro = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Não foi possível processar a ação. Confira o banco de dados.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Recarregar dados atualizados
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM partidas
    WHERE id = ?
");
$stmt->execute([$partidaId]);
$partida = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT *
    FROM estado_partida
    WHERE partida_id = ?
");
$stmt->execute([$partidaId]);
$estado = $stmt->fetch(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Sincronizar partida reprogramada pelo administrador
|--------------------------------------------------------------------------
*/

if (
    $partida['status'] === 'agendada' &&
    $estado['fase'] === 'encerrado'
) {
    $stmt = $pdo->prepare("
        UPDATE estado_partida
        SET periodo_atual = 1,
            segundos_acumulados = 0,
            cronometro_iniciado_em = NULL,
            fase = 'jogo'
        WHERE partida_id = ?
    ");

    $stmt->execute([$partidaId]);

    $stmt = $pdo->prepare("
        UPDATE partidas
        SET inicio_real = NULL,
            fim_real = NULL
        WHERE id = ?
    ");

    $stmt->execute([$partidaId]);

    $estado['periodo_atual'] = 1;
    $estado['segundos_acumulados'] = 0;
    $estado['cronometro_iniciado_em'] = null;
    $estado['fase'] = 'jogo';

    $partida['inicio_real'] = null;
    $partida['fim_real'] = null;
}

$stmt = $pdo->prepare("
    SELECT
        p.*,
        m.nome AS modalidade_nome,
        tc.nome AS turma_casa_nome,
        tv.nome AS turma_visitante_nome
    FROM partidas p
    INNER JOIN modalidades m
        ON m.id = p.modalidade_id
    INNER JOIN turmas tc
        ON tc.id = p.turma_casa_id
    INNER JOIN turmas tv
        ON tv.id = p.turma_visitante_id
    WHERE p.id = ?
    LIMIT 1
");

$stmt->execute([$partidaId]);
$partida = $stmt->fetch(PDO::FETCH_ASSOC);

$segundosExibidos = (int) $estado['segundos_acumulados'];

if ($estado['cronometro_iniciado_em'] !== null) {
    $inicio = strtotime($estado['cronometro_iniciado_em']);

    if ($inicio !== false) {
        $segundosExibidos += max(0, time() - $inicio);
    }
}

$minutos = intdiv($segundosExibidos, 60);
$segundos = $segundosExibidos % 60;

$cronometroAtivo =
    $estado['cronometro_iniciado_em'] !== null;

$encerrada = $estado['fase'] === 'encerrado';

$nomeCasa = $partida['turma_casa_nome'];
$nomeVisitante = $partida['turma_visitante_nome'];

// Carrega o cabeçalho correspondente ao perfil.
$stmt = $pdo->prepare("
    SELECT
        ep.*,
        u.nome AS usuario_nome
    FROM eventos_partida ep
    LEFT JOIN usuarios u ON u.id = ep.usuario_id
    WHERE ep.partida_id = ?
    ORDER BY ep.criado_em DESC, ep.id DESC
");
$stmt->execute([$partidaId]);
$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($ehAdmin) {
    require_once __DIR__ . '/../../includes/admin/header.php';
} else {
    require_once __DIR__ . '/../../includes/arbitro/header.php';
}
?>

<style>
    body {
        background: #f4f6f9;
        min-height: 100vh;
    }

    .painel {
        max-width: 1000px;
        margin: 30px auto;
        padding: 20px;
    }

    .cartao {
        background: white;
        padding: 24px;
        border-radius: 14px;
        box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
        margin-bottom: 20px;
    }

    .placar {
        font-size: clamp(2.5rem, 8vw, 5rem);
        font-weight: 800;
        line-height: 1.2;
    }

    .relogio {
        font-size: clamp(2rem, 6vw, 3.5rem);
        font-weight: 800;
        font-variant-numeric: tabular-nums;
    }

    .equipe {
        overflow-wrap: anywhere;
    }

    .botoes {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .evento {
        padding: 12px 0;
        border-bottom: 1px solid #eee;
    }

    .evento:last-child {
        border-bottom: 0;
    }

    h1.title,
    h2.title,
    h3.title,
    h4.title,
    h5.title,
    h6.title,
    .title,
    .label,
    .textarea,
    strong {
        color: #0b2948 !important;
    }
</style>

<div class="painel">

    <div class="mb-4">
        <a href="../admin/partidas.php" class="button is-light">
            ← Voltar para partidas
        </a>
    </div>

    <div class="cartao">
        <p class="has-text-grey mb-2">
            <?= escapar($partida['modalidade_nome']) ?>
        </p>

        <h1 class="title is-3">
            Painel de controle da partida #<?= (int) $partidaId ?>
        </h1>

        <p class="subtitle is-6">
            Controle disponível para árbitros e administradores.
        </p>

        <?php if ($erro !== ''): ?>
            <div class="notification is-danger is-light">
                <?= escapar($erro) ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="cartao">
        <div class="columns is-vcentered has-text-centered">

            <div class="column equipe">
                <h2 class="title is-4">
                    <?= escapar($nomeCasa) ?>
                </h2>

                <div class="placar" id="placarCasa">
                    <?= (int) $partida['placar_casa'] ?>
                </div>

                <form method="post" class="mt-3">
                    <input type="hidden" name="csrf_token" value="<?= escapar($csrf) ?>">
                    <input type="hidden" name="acao" value="gol_casa">

                    <button class="button is-success is-medium" <?= $encerrada ? 'disabled' : '' ?>>
                        + Gol
                    </button>
                </form>

                <form method="post" class="mt-2">
                    <input type="hidden" name="csrf_token" value="<?= escapar($csrf) ?>">
                    <input type="hidden" name="acao" value="remover_gol_casa">

                    <button class="button is-light is-small" <?= $encerrada ? 'disabled' : '' ?>>
                        − Corrigir gol
                    </button>
                </form>
            </div>

            <div class="column is-narrow">
                <p class="heading">PLACAR</p>
                <p class="title is-4">×</p>
            </div>

            <div class="column equipe">
                <h2 class="title is-4">
                    <?= escapar($nomeVisitante) ?>
                </h2>

                <div class="placar" id="placarVisitante">
                    <?= (int) $partida['placar_visitante'] ?>
                </div>

                <form method="post" class="mt-3">
                    <input type="hidden" name="csrf_token" value="<?= escapar($csrf) ?>">
                    <input type="hidden" name="acao" value="gol_visitante">

                    <button class="button is-success is-medium" <?= $encerrada ? 'disabled' : '' ?>>
                        + Gol
                    </button>
                </form>

                <form method="post" class="mt-2">
                    <input type="hidden" name="csrf_token" value="<?= escapar($csrf) ?>">
                    <input type="hidden" name="acao" value="remover_gol_visitante">

                    <button class="button is-light is-small" <?= $encerrada ? 'disabled' : '' ?>>
                        − Corrigir gol
                    </button>
                </form>
            </div>

        </div>
    </div>

    <div class="cartao has-text-centered">
        <p class="heading">CRONÔMETRO</p>

        <div class="relogio" id="cronometro" data-segundos="<?= (int) $segundosExibidos ?>"
            data-ativo="<?= $cronometroAtivo ? '1' : '0' ?>"
            data-duracao="<?= (int) $partida['duracao_periodo_min'] * 60 ?>"
            data-periodo="<?= (int) $estado['periodo_atual'] ?>"
            data-total-periodos="<?= (int) $partida['quantidade_periodos'] ?>"
            data-encerrada="<?= $encerrada ? '1' : '0' ?>">
            <?= sprintf('%02d:%02d', $minutos, $segundos) ?>
        </div>

        <div class="mb-4">
            <button type="button" id="ativarNotificacoes" class="button is-link is-light">
                🔔 Ativar notificações e vibração
            </button>

            <p class="is-size-7 has-text-grey mt-2" id="statusNotificacoes">
                Ative para receber alertas neste dispositivo.
            </p>
        </div>

        <p class="mb-4">
            Período <?= (int) $estado['periodo_atual'] ?>
            de <?= (int) $partida['quantidade_periodos'] ?>
            ·
            <?= $encerrada
                ? 'Encerrada'
                : ($cronometroAtivo
                    ? 'Em andamento'
                    : ($estado['fase'] === 'intervalo'
                        ? 'Intervalo'
                        : 'Pausada')) ?>
        </p>

        <div class="botoes is-justify-content-center">

            <?php if (!$cronometroAtivo && !$encerrada): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= escapar($csrf) ?>">
                    <input type="hidden" name="acao" value="<?= $estado['fase'] === 'intervalo'
                        ? 'retomar_periodo'
                        : 'iniciar' ?>">

                    <button class="button is-success is-medium">
                        ▶ Iniciar / retomar
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($cronometroAtivo && !$encerrada): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= escapar($csrf) ?>">
                    <input type="hidden" name="acao" value="pausar">

                    <button class="button is-warning is-medium">
                        ⏸ Pausar
                    </button>
                </form>
            <?php endif; ?>

            <?php if (!$cronometroAtivo && !$encerrada): ?>
                <form method="post" onsubmit="return confirm('Avançar para o próximo período?');">
                    <input type="hidden" name="csrf_token" value="<?= escapar($csrf) ?>">
                    <input type="hidden" name="acao" value="proximo_periodo">

                    <button class="button is-info is-medium" <?= (int) $estado['periodo_atual'] >=
                        (int) $partida['quantidade_periodos']
                        ? 'disabled'
                        : '' ?>>
                        Próximo período
                    </button>
                </form>
            <?php endif; ?>

            <?php if (!$cronometroAtivo && !$encerrada): ?>
                <form method="post" onsubmit="return confirm('Tem certeza que deseja encerrar a partida?');">
                    <input type="hidden" name="csrf_token" value="<?= escapar($csrf) ?>">
                    <input type="hidden" name="acao" value="encerrar">

                    <button class="button is-danger is-medium">
                        Encerrar partida
                    </button>
                </form>
            <?php endif; ?>

        </div>
    </div>

    <?php if (!$encerrada): ?>
        <div class="cartao">
            <h2 class="title is-4">Registrar ocorrência</h2>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= escapar($csrf) ?>">
                <input type="hidden" name="acao" value="ocorrencia">

                <div class="field">
                    <label class="label" for="descricao">
                        Descrição
                    </label>

                    <div class="control">
                        <textarea class="textarea" id="descricao" name="descricao" maxlength="2000"
                            placeholder="Descreva a ocorrência da partida..." required></textarea>
                    </div>
                </div>

                <button class="button is-link">
                    Registrar ocorrência
                </button>
            </form>
        </div>
    <?php endif; ?>

    <div class="cartao">
        <h2 class="title is-4">Histórico da partida</h2>

        <?php if (empty($eventos)): ?>
            <p class="has-text-grey">
                Nenhuma ocorrência registrada até o momento.
            </p>
        <?php else: ?>
            <?php foreach ($eventos as $evento): ?>
                <div class="evento">
                    <p>
                        <strong>
                            <?= escapar($evento['tipo']) ?>
                        </strong>
                    </p>

                    <p>
                        <?= nl2br(escapar($evento['descricao'])) ?>
                    </p>

                    <p class="is-size-7 has-text-grey mt-1">
                        <?= escapar($evento['criado_em']) ?>
                    </p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<script>

    (() => {
        const relogio = document.getElementById('cronometro');

        if (!relogio) return;

        const botaoNotificacoes =
            document.getElementById('ativarNotificacoes');

        const statusNotificacoes =
            document.getElementById('statusNotificacoes');

        const segundosBase =
            Number(relogio.dataset.segundos || 0);

        const ativo =
            relogio.dataset.ativo === '1';

        const duracao =
            Number(relogio.dataset.duracao || 0);

        const periodo =
            Number(relogio.dataset.periodo || 1);

        const totalPeriodos =
            Number(relogio.dataset.totalPeriodos || 1);

        const encerrada =
            relogio.dataset.encerrada === '1';

        const instanteInicial = Date.now();

        // Evita repetir os alertas durante a mesma abertura da página.
        let alertaUmMinutoDisparado = false;
        let alertaFimDisparado = false;

        // Vibração e notificação.
        function notificar(titulo, mensagem) {

            // Vibração, quando suportada pelo dispositivo.
            if ('vibrate' in navigator) {
                navigator.vibrate([500, 200, 500, 200, 800]);
            }

            // Notificação do navegador.
            if (
                'Notification' in window &&
                Notification.permission === 'granted'
            ) {
                new Notification(titulo, {
                    body: mensagem,
                    tag: 'partida-' + periodo + '-' + titulo,
                    requireInteraction: true
                });
            } else {
                // Alternativa visual caso não haja permissão.
                const aviso = document.createElement('div');

                aviso.className = 'notification is-warning';
                aviso.style.position = 'fixed';
                aviso.style.bottom = '20px';
                aviso.style.left = '20px';
                aviso.style.right = '20px';
                aviso.style.zIndex = '99999';
                aviso.style.boxShadow = '0 3px 15px rgba(0,0,0,.2)';

                const texto = document.createElement('strong');
                texto.textContent = titulo;

                const detalhe = document.createElement('p');
                detalhe.textContent = mensagem;

                aviso.appendChild(texto);
                aviso.appendChild(detalhe);

                document.body.appendChild(aviso);

                setTimeout(() => aviso.remove(), 10000);
            }
        }

        // Solicita permissão somente após interação do usuário.
        botaoNotificacoes?.addEventListener('click', async () => {

            if (!('Notification' in window)) {
                statusNotificacoes.textContent =
                    'Este navegador não oferece suporte a notificações.';

                return;
            }

            try {
                const permissao =
                    await Notification.requestPermission();

                if (permissao === 'granted') {

                    statusNotificacoes.textContent =
                        'Notificações ativadas neste dispositivo!';

                    notificar(
                        'Notificações ativadas!',
                        'Você receberá alertas durante a partida.'
                    );

                } else {
                    statusNotificacoes.textContent =
                        'Permissão não concedida. Verifique as configurações do navegador.';
                }

            } catch (erro) {
                statusNotificacoes.textContent =
                    'Não foi possível ativar as notificações.';
            }
        });

        function atualizar() {

            let total = segundosBase;

            if (ativo) {
                total += Math.floor(
                    (Date.now() - instanteInicial) / 1000
                );
            }

            const minutos = Math.floor(total / 60);
            const segundos = total % 60;

            relogio.textContent =
                String(minutos).padStart(2, '0') +
                ':' +
                String(segundos).padStart(2, '0');

            if (!ativo || encerrada || duracao <= 0) {
                return;
            }

            // ALERTA: falta 1 minuto.
            const limiteUmMinuto = duracao - 60;

            if (
                total >= limiteUmMinuto &&
                total < duracao &&
                !alertaUmMinutoDisparado
            ) {
                alertaUmMinutoDisparado = true;

                notificar(
                    'Falta 1 minuto!',
                    'Atenção, árbitro! O período ' +
                    periodo +
                    ' está chegando ao fim.'
                );
            }

            // ALERTA: tempo do período encerrado.
            if (
                total >= duracao &&
                !alertaFimDisparado
            ) {
                alertaFimDisparado = true;

                if (periodo >= totalPeriodos) {
                    notificar(
                        'Tempo da partida encerrado!',
                        'O último período chegou ao fim.'
                    );
                } else {
                    notificar(
                        'Fim do período!',
                        'O período ' +
                        periodo +
                        ' terminou. Prepare-se para o próximo.'
                    );
                }
            }
        }

        atualizar();

        if (ativo) {
            setInterval(atualizar, 1000);
        }

    })();

</script>

<?php
// Fecha o layout correspondente ao perfil.
if ($ehAdmin) {
    require_once __DIR__ . '/../../includes/admin/footer.php';
} else {
    require_once __DIR__ . '/../../includes/arbitro/footer.php';
}
?>
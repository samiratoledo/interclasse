<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);

    echo json_encode([
        'erro' => 'Partida inválida.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    // Busca os dados da partida.
    $stmt = $pdo->prepare(
        sqlPartidas() . ' WHERE p.id = ? LIMIT 1'
    );

    $stmt->execute([$id]);

    $partida = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$partida) {
        http_response_code(404);

        echo json_encode([
            'erro' => 'Partida não encontrada.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    // Busca o estado atual do cronômetro.
    $stmt = $pdo->prepare("
        SELECT *
        FROM estado_partida
        WHERE partida_id = ?
        LIMIT 1
    ");

    $stmt->execute([$id]);

    $estado = $stmt->fetch(PDO::FETCH_ASSOC);

    // Valores padrão caso ainda não exista estado.
    $segundos = 0;
    $ativo = false;
    $periodo = 1;
    $fase = 'jogo';

    if ($estado) {
        $segundos = (int) $estado['segundos_acumulados'];
        $periodo = (int) $estado['periodo_atual'];
        $fase = $estado['fase'];

        // Se o cronômetro estiver rodando, soma o tempo decorrido.
        if ($estado['cronometro_iniciado_em'] !== null) {
            $inicio = strtotime(
                $estado['cronometro_iniciado_em']
            );

            if ($inicio !== false) {
                $segundos += max(0, time() - $inicio);
            }

            $ativo = true;
        }
    }

    // Identifica se a partida já foi iniciada.
    $inicioReal = $partida['inicio_real'] ?? null;

    $iniciada = (
        $inicioReal !== null ||
        $ativo ||
        $segundos > 0 ||
        in_array($partida['status'], [
            'em_andamento',
            'pausada',
            'encerrada'
        ], true) ||
        $fase === 'intervalo' ||
        $fase === 'encerrado'
    );

    // Identifica se a partida foi encerrada.
    $encerrada = (
        $partida['status'] === 'encerrada' ||
        $fase === 'encerrado'
    );

    // Recupera o rótulo e a classe do status.
    [$rotuloStatus, $classeStatus] =
        STATUS_PARTIDA[$partida['status']] ?? ['—', 'is-light'];

    // Busca somente eventos que podem aparecer ao público.
    $stmt = $pdo->prepare("
        SELECT
            tipo,
            descricao,
            periodo,
            criado_em
        FROM eventos_partida
        WHERE partida_id = ?
          AND tipo IN (
              'gol',
              'periodo',
              'cronometro',
              'encerramento'
          )
        ORDER BY criado_em DESC, id DESC
        LIMIT 40
    ");

    $stmt->execute([$id]);

    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);


    // Histórico completo para o painel do árbitro.
    $stmt = $pdo->prepare("
    SELECT
    ep.tipo,
    ep.descricao,
    ep.periodo,
    ep.tempo_segundos,
    ep.criado_em,
    u.nome AS usuario_nome
FROM eventos_partida ep
LEFT JOIN usuarios u ON u.id = ep.usuario_id
WHERE ep.partida_id = ?
ORDER BY ep.criado_em DESC, ep.id DESC
LIMIT 60
");

    $stmt->execute([$id]);

    $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);


    // Retorna os dados atualizados em JSON.
    echo json_encode([
        'status' => $partida['status'],

        'rotulo_status' => $rotuloStatus,
        'classe_status' => $classeStatus,

        'placar_casa' => (int) $partida['placar_casa'],
        'placar_visitante' => (int) $partida['placar_visitante'],

        'periodo' => $periodo,
        'total_periodos' => (int) $partida['quantidade_periodos'],

        'duracao' => (int) $partida['duracao_periodo_min'] * 60,

        'fase' => $fase,

        'segundos' => $segundos,

        'ativo' => $ativo,
        'iniciada' => $iniciada,
        'encerrada' => $encerrada,

        'inicio_previsto' => $partida['inicio_previsto'],

        'eventos' => $eventos,
        'historico' => $historico

    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Throwable $e) {
    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'erro' => 'Não foi possível atualizar a partida.'
    ], JSON_UNESCAPED_UNICODE);
}
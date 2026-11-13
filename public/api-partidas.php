<?php

declare(strict_types=1);

/**
 * Estado público das partidas, consultado pelo app.js a cada poucos segundos.
 * Uso: api-partidas.php?ids=1,2,3
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$ids = array_values(array_unique(array_filter(
    array_map('intval', explode(',', (string) ($_GET['ids'] ?? ''))),
    static fn (int $i): bool => $i > 0
)));
$ids = array_slice($ids, 0, 50);

if (!$ids) {
    echo json_encode(['partidas' => []]);
    exit;
}

try {
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(sqlPartidas() . " WHERE p.id IN ($marcadores)");
    $stmt->execute($ids);

    $saida = [];
    foreach ($stmt->fetchAll() as $p) {
        $saida[] = estadoPublico($p);
    }

    echo json_encode(['partidas' => $saida]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['erro' => 'indisponivel']);
}
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

exigirPerfil('admin', 'arbitro');

$usuario = usuarioAtual();

if (!$usuario || !in_array($usuario['perfil'] ?? '', ['admin', 'arbitro'], true)) {
    http_response_code(403);
    exit('Acesso permitido somente para administradores e árbitros.');
}

$usuarioId = (int) $usuario['id'];
$ehAdmin = ($usuario['perfil'] ?? '') === 'admin';

$partidaId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$partidaId || $partidaId < 1) {
    http_response_code(400);
    exit('Partida inválida.');
}

$stmt = $pdo->prepare("
    SELECT
        p.*,
        m.nome AS modalidade_nome,
        m.minimo_atletas,
        m.maximo_atletas,
        tc.nome AS turma_casa_nome,
        tv.nome AS turma_visitante_nome
    FROM partidas p
    INNER JOIN modalidades m ON m.id = p.modalidade_id
    INNER JOIN turmas tc ON tc.id = p.turma_casa_id
    INNER JOIN turmas tv ON tv.id = p.turma_visitante_id
    WHERE p.id = ?
    LIMIT 1
");

$stmt->execute([$partidaId]);
$partida = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$partida) {
    http_response_code(404);
    exit('Partida não encontrada.');
}

if (!$ehAdmin && (int) ($partida['responsavel_id'] ?? 0) !== $usuarioId) {
    http_response_code(403);
    exit('Você não é o responsável por esta partida.');
}

if (empty($_SESSION['csrf_partida_arbitro'])) {
    $_SESSION['csrf_partida_arbitro'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_partida_arbitro'];
$erro = '';

function escapar(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function formatarTempoJogo(mixed $tempo): string
{
    if ($tempo === null || $tempo === '') {
        return '--:--';
    }

    $tempo = max(0, (int) $tempo);

    return sprintf('%02d:%02d', intdiv($tempo, 60), $tempo % 60);
}

function nomeTipoEvento(string $tipo): string
{
    return [
        'gol' => '⚽ Gol',
        'correcao_placar' => '🖋️ Correção de placar',
        'periodo' => '🏁 Período',
        'cronometro' => '⏱️ Cronômetro',
        'encerramento' => '🏆 Fim de partida',
        'ocorrencia' => '📋 Ocorrência',
        'cartao_amarelo' => '🟨 Cartão amarelo',
        'cartao_vermelho' => '🟥 Cartão vermelho',
        'elenco' => '👥 Elenco da partida',
        'substituicao' => '🔄 Substituição',
    ][$tipo] ?? '📌 Registro da partida';
}

function registrarOcorrencia(
    PDO $pdo,
    int $partidaId,
    int $usuarioId,
    string $tipo,
    string $descricao,
    int $periodo,
    int $tempoSegundos,
    ?int $atletaId = null,
    ?int $assistenciaId = null
): void {
    $stmt = $pdo->prepare("
        INSERT INTO eventos_partida
            (partida_id, usuario_id, atleta_id, assistencia_id,
             tipo, descricao, periodo, tempo_segundos)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $partidaId,
        $usuarioId,
        $atletaId,
        $assistenciaId,
        $tipo,
        $descricao,
        $periodo,
        $tempoSegundos
    ]);
}

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

    if (!is_string($tokenRecebido) || !hash_equals($csrf, $tokenRecebido)) {
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

            if (!$ehAdmin && (int) $partidaAtual['responsavel_id'] !== $usuarioId) {
                throw new RuntimeException('Você não é o responsável por esta partida.');
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
                throw new RuntimeException('Não foi possível carregar o cronômetro.');
            }

            $fase = $estado['fase'];
            $segundos = (int) $estado['segundos_acumulados'];

            if ($estado['cronometro_iniciado_em'] !== null) {
                $inicio = strtotime($estado['cronometro_iniciado_em']);

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

                /*
                |--------------------------------------------------------------------------
                | Gols e cartões por jogador
                |--------------------------------------------------------------------------
                */

                case 'gol_atleta':
                case 'cartao_amarelo':
                case 'cartao_vermelho':

                    if ($fase === 'encerrado') {
                        throw new RuntimeException('A partida já foi encerrada.');
                    }

                    $atletaId = filter_var(
                        $_POST['atleta_id'] ?? null,
                        FILTER_VALIDATE_INT
                    );

                    if (!$atletaId || $atletaId < 1) {
                        throw new RuntimeException('Jogador inválido.');
                    }

                    $stmt = $pdo->prepare("
                        SELECT a.id, a.nome, a.turma_id
                        FROM atletas a
                        INNER JOIN inscricao_atletas ia ON ia.atleta_id = a.id
                        INNER JOIN inscricoes i ON i.id = ia.inscricao_id
                        WHERE a.id = ?
                          AND i.modalidade_id = ?
                          AND i.status = 'aprovada'
                          AND i.turma_id IN (?, ?)
                          AND a.status_aprovacao = 'aprovado'
                          AND a.ativo = 1
                        LIMIT 1
                    ");

                    $stmt->execute([
                        $atletaId,
                        $partidaAtual['modalidade_id'],
                        $partidaAtual['turma_casa_id'],
                        $partidaAtual['turma_visitante_id']
                    ]);

                    $atleta = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$atleta) {
                        throw new RuntimeException('Jogador não aprovado ou não inscrito nesta partida.');
                    }

                    if ($acao === 'gol_atleta') {

                        $assistenciaId = null;
                        $assistenciaNome = null;
                        $assistenciaRecebida = $_POST['assistencia_id'] ?? '';

                        if ($assistenciaRecebida !== '') {

                            $assistenciaId = filter_var(
                                $assistenciaRecebida,
                                FILTER_VALIDATE_INT
                            );

                            if (
                                !$assistenciaId ||
                                $assistenciaId < 1 ||
                                $assistenciaId === (int) $atleta['id']
                            ) {
                                throw new RuntimeException('Jogador da assistência inválido.');
                            }

                            $stmt = $pdo->prepare("
                                SELECT a.id, a.nome
                                FROM atletas a
                                INNER JOIN inscricao_atletas ia ON ia.atleta_id = a.id
                                INNER JOIN inscricoes i ON i.id = ia.inscricao_id
                                WHERE a.id = ?
                                  AND a.turma_id = ?
                                  AND i.modalidade_id = ?
                                  AND i.turma_id = ?
                                  AND i.status = 'aprovada'
                                  AND a.status_aprovacao = 'aprovado'
                                  AND a.ativo = 1
                                LIMIT 1
                            ");

                            $stmt->execute([
                                $assistenciaId,
                                $atleta['turma_id'],
                                $partidaAtual['modalidade_id'],
                                $atleta['turma_id']
                            ]);

                            $assistencia = $stmt->fetch(PDO::FETCH_ASSOC);

                            if (!$assistencia) {
                                throw new RuntimeException('O jogador da assistência não pertence à equipe.');
                            }

                            $assistenciaNome = $assistencia['nome'];
                        }

                        if ((int) $atleta['turma_id'] === (int) $partidaAtual['turma_casa_id']) {
                            $stmt = $pdo->prepare("
                                UPDATE partidas
                                SET placar_casa = placar_casa + 1
                                WHERE id = ?
                            ");
                        } else {
                            $stmt = $pdo->prepare("
                                UPDATE partidas
                                SET placar_visitante = placar_visitante + 1
                                WHERE id = ?
                            ");
                        }

                        $stmt->execute([$partidaId]);

                        $descricao = 'Gol de ' . $atleta['nome'];

                        if ($assistenciaNome !== null) {
                            $descricao .= ', assistência de ' . $assistenciaNome;
                        }

                        registrarOcorrencia(
                            $pdo,
                            $partidaId,
                            $usuarioId,
                            'gol',
                            $descricao . '.',
                            $novoPeriodo,
                            $segundos,
                            (int) $atleta['id'],
                            $assistenciaId
                        );

                    } elseif ($acao === 'cartao_amarelo') {

                        registrarOcorrencia(
                            $pdo,
                            $partidaId,
                            $usuarioId,
                            'cartao_amarelo',
                            'Cartão amarelo para ' . $atleta['nome'] . '.',
                            $novoPeriodo,
                            $segundos,
                            (int) $atleta['id']
                        );

                    } else {

                        registrarOcorrencia(
                            $pdo,
                            $partidaId,
                            $usuarioId,
                            'cartao_vermelho',
                            'Cartão vermelho para ' . $atleta['nome'] . '.',
                            $novoPeriodo,
                            $segundos,
                            (int) $atleta['id']
                        );
                    }

                    break;

                /*
                |--------------------------------------------------------------------------
                | Salvar elenco: mínimo de titulares e máximo total
                |--------------------------------------------------------------------------
                */

                case 'salvar_elenco':

                    if ($partidaAtual['inicio_real'] !== null || $partidaAtual['status'] !== 'agendada') {
                        throw new RuntimeException('O elenco só pode ser configurado antes do início da partida.');
                    }

                    $elenco = $_POST['elenco'] ?? [];

                    if (!is_array($elenco)) {
                        throw new RuntimeException('Seleção de elenco inválida.');
                    }

                    $stmt = $pdo->prepare("
                        SELECT minimo_atletas, maximo_atletas
                        FROM modalidades
                        WHERE id = ?
                        LIMIT 1
                    ");
                    $stmt->execute([$partidaAtual['modalidade_id']]);
                    $limites = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$limites) {
                        throw new RuntimeException('Limites da modalidade não encontrados.');
                    }

                    $minimoTitulares = (int) $limites['minimo_atletas'];
                    $maximoElenco = (int) $limites['maximo_atletas'];

                    if ($minimoTitulares < 1 || $maximoElenco < $minimoTitulares) {
                        throw new RuntimeException('Os limites cadastrados para esta modalidade são inválidos.');
                    }

                    $stmt = $pdo->prepare("
                        SELECT DISTINCT a.id, a.turma_id
                        FROM inscricoes i
                        INNER JOIN inscricao_atletas ia ON ia.inscricao_id = i.id
                        INNER JOIN atletas a ON a.id = ia.atleta_id
                        WHERE i.modalidade_id = ?
                          AND i.status = 'aprovada'
                          AND i.turma_id IN (?, ?)
                          AND a.status_aprovacao = 'aprovado'
                          AND a.ativo = 1
                    ");

                    $stmt->execute([
                        $partidaAtual['modalidade_id'],
                        $partidaAtual['turma_casa_id'],
                        $partidaAtual['turma_visitante_id']
                    ]);

                    $permitidos = [];

                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
                        $permitidos[(int) $item['id']] = (int) $item['turma_id'];
                    }

                    $contagem = [
                        (int) $partidaAtual['turma_casa_id'] => [
                            'titulares' => 0,
                            'reservas' => 0,
                            'total' => 0
                        ],
                        (int) $partidaAtual['turma_visitante_id'] => [
                            'titulares' => 0,
                            'reservas' => 0,
                            'total' => 0
                        ]
                    ];

                    $selecionados = [];

                    foreach ($elenco as $id => $funcao) {

                        $id = filter_var($id, FILTER_VALIDATE_INT);

                        if (!$id || !isset($permitidos[$id])) {
                            throw new RuntimeException('Um dos atletas selecionados não está aprovado ou inscrito.');
                        }

                        if (!in_array($funcao, ['titular', 'reserva'], true)) {
                            throw new RuntimeException('Função inválida para um atleta.');
                        }

                        $turmaId = $permitidos[$id];

                        $contagem[$turmaId]['total']++;

                        if ($funcao === 'titular') {
                            $contagem[$turmaId]['titulares']++;
                        } else {
                            $contagem[$turmaId]['reservas']++;
                        }

                        $selecionados[] = [
                            'id' => (int) $id,
                            'funcao' => $funcao
                        ];
                    }

                    foreach ($contagem as $quantidades) {

                        if ($quantidades['titulares'] < $minimoTitulares) {
                            throw new RuntimeException(
                                'Cada equipe precisa selecionar pelo menos ' .
                                $minimoTitulares . ' titular(es).'
                            );
                        }

                        if ($quantidades['total'] > $maximoElenco) {
                            throw new RuntimeException(
                                'Cada equipe pode selecionar no máximo ' .
                                $maximoElenco . ' atleta(s), incluindo reservas.'
                            );
                        }
                    }

                    $stmt = $pdo->prepare("
                        DELETE FROM partidas_atletas
                        WHERE partida_id = ?
                    ");
                    $stmt->execute([$partidaId]);

                    $stmt = $pdo->prepare("
                        INSERT INTO partidas_atletas
                            (partida_id, atleta_id, funcao_inicial, situacao_atual)
                        VALUES (?, ?, ?, ?)
                    ");

                    foreach ($selecionados as $atleta) {

                        $situacao = $atleta['funcao'] === 'titular'
                            ? 'em_campo'
                            : 'reserva';

                        $stmt->execute([
                            $partidaId,
                            $atleta['id'],
                            $atleta['funcao'],
                            $situacao
                        ]);
                    }

                    registrarOcorrencia(
                        $pdo,
                        $partidaId,
                        $usuarioId,
                        'elenco',
                        'Elenco inicial configurado para as duas equipes.',
                        $novoPeriodo,
                        $segundos
                    );

                    break;

                /*
                |--------------------------------------------------------------------------
                | Substituição de jogadores
                |--------------------------------------------------------------------------
                */

                case 'substituir':

                    if ($fase === 'encerrado' || $partidaAtual['inicio_real'] === null) {
                        throw new RuntimeException('As substituições só podem ocorrer durante uma partida iniciada.');
                    }

                    if ($partidaAtual['status'] !== 'em_andamento' && $partidaAtual['status'] !== 'pausada') {
                        throw new RuntimeException('Não é possível substituir jogadores neste momento.');
                    }

                    $atletaSaiId = filter_var(
                        $_POST['atleta_sai_id'] ?? null,
                        FILTER_VALIDATE_INT
                    );

                    $atletaEntraId = filter_var(
                        $_POST['atleta_entra_id'] ?? null,
                        FILTER_VALIDATE_INT
                    );

                    if (
                        !$atletaSaiId || !$atletaEntraId ||
                        $atletaSaiId < 1 || $atletaEntraId < 1 ||
                        $atletaSaiId === $atletaEntraId
                    ) {
                        throw new RuntimeException('Selecione corretamente quem sai e quem entra.');
                    }

                    $stmt = $pdo->prepare("
                        SELECT
                            pa.atleta_id,
                            pa.situacao_atual,
                            a.nome,
                            a.turma_id
                        FROM partidas_atletas pa
                        INNER JOIN atletas a ON a.id = pa.atleta_id
                        WHERE pa.partida_id = ?
                          AND pa.atleta_id IN (?, ?)
                        FOR UPDATE
                    ");

                    $stmt->execute([
                        $partidaId,
                        $atletaSaiId,
                        $atletaEntraId
                    ]);

                    $jogadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $jogadorSai = null;
                    $jogadorEntra = null;

                    foreach ($jogadores as $jogador) {
                        if ((int) $jogador['atleta_id'] === $atletaSaiId) {
                            $jogadorSai = $jogador;
                        }

                        if ((int) $jogador['atleta_id'] === $atletaEntraId) {
                            $jogadorEntra = $jogador;
                        }
                    }

                    if (!$jogadorSai || !$jogadorEntra) {
                        throw new RuntimeException('Um dos jogadores não pertence ao elenco desta partida.');
                    }

                    if (
                        $jogadorSai['situacao_atual'] !== 'em_campo' ||
                        $jogadorEntra['situacao_atual'] !== 'reserva'
                    ) {
                        throw new RuntimeException('O jogador que sai precisa estar em campo e o que entra precisa ser reserva.');
                    }

                    if ((int) $jogadorSai['turma_id'] !== (int) $jogadorEntra['turma_id']) {
                        throw new RuntimeException('A substituição deve acontecer entre jogadores da mesma equipe.');
                    }

                    $stmt = $pdo->prepare("
                        UPDATE partidas_atletas
                        SET situacao_atual = 'substituido'
                        WHERE partida_id = ?
                          AND atleta_id = ?
                          AND situacao_atual = 'em_campo'
                    ");
                    $stmt->execute([$partidaId, $atletaSaiId]);

                    if ($stmt->rowCount() !== 1) {
                        throw new RuntimeException('Não foi possível retirar o jogador de campo.');
                    }

                    $stmt = $pdo->prepare("
                        UPDATE partidas_atletas
                        SET situacao_atual = 'em_campo'
                        WHERE partida_id = ?
                          AND atleta_id = ?
                          AND situacao_atual = 'reserva'
                    ");
                    $stmt->execute([$partidaId, $atletaEntraId]);

                    if ($stmt->rowCount() !== 1) {
                        throw new RuntimeException('Não foi possível colocar o reserva em campo.');
                    }

                    registrarOcorrencia(
                        $pdo,
                        $partidaId,
                        $usuarioId,
                        'substituicao',
                        $jogadorSai['nome'] . ' saiu; ' .
                        $jogadorEntra['nome'] . ' entrou.',
                        $novoPeriodo,
                        $segundos,
                        $atletaSaiId,
                        $atletaEntraId
                    );

                    break;

                /*
                |--------------------------------------------------------------------------
                | Iniciar ou retomar partida
                |--------------------------------------------------------------------------
                */

                case 'iniciar':

                    if ($partidaAtual['status'] === 'cancelada') {
                        throw new RuntimeException('Uma partida cancelada não pode ser iniciada.');
                    }

                    if ($fase === 'encerrado') {
                        throw new RuntimeException('A partida já foi encerrada.');
                    }

                    if ($fase !== 'intervalo') {
                        $novoInicio = date('Y-m-d H:i:s');
                    } else {
                        $novaFase = 'jogo';
                        $novoInicio = date('Y-m-d H:i:s');
                    }

                    if ($partidaAtual['inicio_real'] === null) {

                        $stmt = $pdo->prepare("
                            UPDATE partidas
                            SET inicio_real = NOW(), status = 'em_andamento'
                            WHERE id = ?
                        ");
                        $stmt->execute([$partidaId]);

                    } elseif ($partidaAtual['status'] === 'pausada') {

                        $stmt = $pdo->prepare("
                            UPDATE partidas
                            SET status = 'em_andamento'
                            WHERE id = ?
                        ");
                        $stmt->execute([$partidaId]);
                    }

                    registrarOcorrencia(
                        $pdo, $partidaId, $usuarioId,
                        'cronometro', 'Cronômetro iniciado ou retomado.',
                        $novoPeriodo, $segundos
                    );

                    break;

                case 'pausar':

                    if ($estado['cronometro_iniciado_em'] === null) {
                        throw new RuntimeException('O cronômetro já está pausado.');
                    }

                    $stmt = $pdo->prepare("
                        UPDATE partidas
                        SET status = 'pausada'
                        WHERE id = ? AND status = 'em_andamento'
                    ");
                    $stmt->execute([$partidaId]);

                    registrarOcorrencia(
                        $pdo, $partidaId, $usuarioId,
                        'cronometro', 'Cronômetro pausado.',
                        $novoPeriodo, $segundos
                    );

                    break;

                case 'proximo_periodo':

                    if ($estado['cronometro_iniciado_em'] !== null) {
                        throw new RuntimeException('Pause o cronômetro antes de avançar o período.');
                    }

                    if ($fase === 'encerrado') {
                        throw new RuntimeException('A partida já foi encerrada.');
                    }

                    if ($novoPeriodo >= (int) $partidaAtual['quantidade_periodos']) {
                        throw new RuntimeException('Todos os períodos já foram concluídos.');
                    }

                    $novoPeriodo++;
                    $segundos = 0;
                    $novaFase = 'intervalo';

                    registrarOcorrencia(
                        $pdo, $partidaId, $usuarioId,
                        'periodo', 'Avanço para o período ' . $novoPeriodo . '.',
                        $novoPeriodo, $segundos
                    );

                    break;

                case 'retomar_periodo':

                    if ($fase !== 'intervalo') {
                        throw new RuntimeException('A partida não está no intervalo.');
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
                        $pdo, $partidaId, $usuarioId,
                        'periodo', 'Início do período ' . $novoPeriodo . '.',
                        $novoPeriodo, $segundos
                    );

                    break;

                /*
                |--------------------------------------------------------------------------
                | Gols manuais
                |--------------------------------------------------------------------------
                */

                case 'gol_casa':
                case 'gol_visitante':

                    if ($fase === 'encerrado') {
                        throw new RuntimeException('Não é possível alterar o placar após o encerramento.');
                    }

                    if ($acao === 'gol_casa') {

                        $stmt = $pdo->prepare("
                            UPDATE partidas
                            SET placar_casa = placar_casa + 1
                            WHERE id = ?
                        ");

                        $descricao = 'Gol do ' . $partidaAtual['turma_casa_nome'] . '.';

                    } else {

                        $stmt = $pdo->prepare("
                            UPDATE partidas
                            SET placar_visitante = placar_visitante + 1
                            WHERE id = ?
                        ");

                        $descricao = 'Gol do ' . $partidaAtual['turma_visitante_nome'] . '.';
                    }

                    $stmt->execute([$partidaId]);

                    registrarOcorrencia(
                        $pdo, $partidaId, $usuarioId,
                        'gol', $descricao, $novoPeriodo, $segundos
                    );

                    break;

                case 'remover_gol_casa':
                case 'remover_gol_visitante':

                    if ($fase === 'encerrado') {
                        throw new RuntimeException('A partida já foi encerrada.');
                    }

                    if ($acao === 'remover_gol_casa') {

                        $stmt = $pdo->prepare("
                            UPDATE partidas
                            SET placar_casa = placar_casa - 1
                            WHERE id = ? AND placar_casa > 0
                        ");

                        $descricao = 'Correção: gol removido do ' . $partidaAtual['turma_casa_nome'] . '.';

                    } else {

                        $stmt = $pdo->prepare("
                            UPDATE partidas
                            SET placar_visitante = placar_visitante - 1
                            WHERE id = ? AND placar_visitante > 0
                        ");

                        $descricao = 'Correção: gol removido do ' . $partidaAtual['turma_visitante_nome'] . '.';
                    }

                    $stmt->execute([$partidaId]);

                    if ($stmt->rowCount() === 0) {
                        throw new RuntimeException('O placar já está em 0. Não há gol para remover.');
                    }

                    registrarOcorrencia(
                        $pdo, $partidaId, $usuarioId,
                        'correcao_placar', $descricao, $novoPeriodo, $segundos
                    );

                    break;

                case 'ocorrencia':

                    if ($fase === 'encerrado') {
                        throw new RuntimeException('A partida já foi encerrada.');
                    }

                    $descricao = trim((string) ($_POST['descricao'] ?? ''));

                    if ($descricao === '') {
                        throw new RuntimeException('Digite a descrição da ocorrência.');
                    }

                    if (mb_strlen($descricao) > 2000) {
                        throw new RuntimeException('A ocorrência deve ter no máximo 2000 caracteres.');
                    }

                    registrarOcorrencia(
                        $pdo, $partidaId, $usuarioId,
                        'ocorrencia', $descricao, $novoPeriodo, $segundos
                    );

                    break;

                case 'encerrar':

                    if ($estado['cronometro_iniciado_em'] !== null) {
                        throw new RuntimeException('Pause o cronômetro antes de encerrar.');
                    }

                    $novaFase = 'encerrado';
                    $novoInicio = null;

                    $stmt = $pdo->prepare("
                        UPDATE partidas
                        SET status = 'encerrada', fim_real = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$partidaId]);

                    registrarOcorrencia(
                        $pdo, $partidaId, $usuarioId,
                        'encerramento', 'Partida encerrada.',
                        $novoPeriodo, $segundos
                    );

                    break;

                default:
                    throw new RuntimeException('Ação não reconhecida.');
            }

            if (in_array($acao, ['pausar', 'proximo_periodo', 'encerrar'], true)) {
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

            header('Location: partida.php?id=' . $partidaId);
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
| Recarregar dados
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("SELECT * FROM partidas WHERE id = ?");
$stmt->execute([$partidaId]);
$partida = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM estado_partida WHERE partida_id = ?");
$stmt->execute([$partidaId]);
$estado = $stmt->fetch(PDO::FETCH_ASSOC);

if ($partida['status'] === 'agendada' && $estado['fase'] === 'encerrado') {

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
        SET inicio_real = NULL, fim_real = NULL
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
        m.minimo_atletas,
        m.maximo_atletas,
        tc.nome AS turma_casa_nome,
        tv.nome AS turma_visitante_nome
    FROM partidas p
    INNER JOIN modalidades m ON m.id = p.modalidade_id
    INNER JOIN turmas tc ON tc.id = p.turma_casa_id
    INNER JOIN turmas tv ON tv.id = p.turma_visitante_id
    WHERE p.id = ?
    LIMIT 1
");

$stmt->execute([$partidaId]);
$partida = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$partida) {
    http_response_code(404);
    exit('Partida não encontrada.');
}

$segundosExibidos = (int) $estado['segundos_acumulados'];

if ($estado['cronometro_iniciado_em'] !== null) {
    $inicio = strtotime($estado['cronometro_iniciado_em']);

    if ($inicio !== false) {
        $segundosExibidos += max(0, time() - $inicio);
    }
}

$minutos = intdiv($segundosExibidos, 60);
$segundos = $segundosExibidos % 60;

$cronometroAtivo = $estado['cronometro_iniciado_em'] !== null;
$encerrada = $estado['fase'] === 'encerrado';

$nomeCasa = $partida['turma_casa_nome'];
$nomeVisitante = $partida['turma_visitante_nome'];

/*
|--------------------------------------------------------------------------
| Atletas disponíveis e elenco da partida
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT DISTINCT
        a.id,
        a.nome,
        a.numero_camisa,
        a.turma_id,
        t.nome AS turma_nome,
        pa.funcao_inicial,
        pa.situacao_atual
    FROM inscricoes i
    INNER JOIN inscricao_atletas ia ON ia.inscricao_id = i.id
    INNER JOIN atletas a ON a.id = ia.atleta_id
    INNER JOIN turmas t ON t.id = a.turma_id
    LEFT JOIN partidas_atletas pa
        ON pa.atleta_id = a.id
        AND pa.partida_id = ?
    WHERE i.modalidade_id = ?
      AND i.status = 'aprovada'
      AND i.turma_id IN (?, ?)
      AND a.status_aprovacao = 'aprovado'
      AND a.ativo = 1
    ORDER BY t.nome, a.nome
");

$stmt->execute([
    $partidaId,
    $partida['modalidade_id'],
    $partida['turma_casa_id'],
    $partida['turma_visitante_id']
]);

$atletasDisponiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);

$atletasCasa = [];
$atletasVisitante = [];

foreach ($atletasDisponiveis as $atleta) {
    if ((int) $atleta['turma_id'] === (int) $partida['turma_casa_id']) {
        $atletasCasa[] = $atleta;
    } else {
        $atletasVisitante[] = $atleta;
    }
}

$elencoConfigurado = false;

foreach ($atletasDisponiveis as $atleta) {
    if ($atleta['funcao_inicial'] !== null) {
        $elencoConfigurado = true;
        break;
    }
}

$stmt = $pdo->prepare("
    SELECT ep.*, u.nome AS usuario_nome
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
.painel {
    width: 100%;
    max-width: 1000px;
    margin: 24px auto;
    padding: 0 16px;
    color: #243247;
    font-family: 'Montserrat', sans-serif;
}

.painel .cartao {
    background: #fff;
    color: #243247;
    padding: 24px;
    border: 1px solid #E1E8F0;
    border-radius: 14px;
    box-shadow: 0 3px 12px rgba(11,41,72,.06);
    margin-bottom: 20px;
}

.painel h1.title,
.painel h2.title,
.painel h3.title,
.painel .title,
.painel .label {
    color: #0B2948;
    font-family: 'Montserrat', sans-serif;
}

.painel .subtitle,
.painel .heading,
.painel .has-text-grey {
    color: #66768A !important;
}

.painel .placar {
    color: #0B2948;
    font-size: clamp(2.8rem, 8vw, 5rem);
    font-weight: 800;
    line-height: 1.2;
    font-variant-numeric: tabular-nums;
}

.painel .relogio {
    color: #0B2948;
    font-size: clamp(2.2rem, 6vw, 3.8rem);
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    margin: 12px 0;
}

.painel .equipe {
    overflow-wrap: anywhere;
}

.painel .botoes {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px;
}

.painel .evento {
    padding: 14px 0;
    border-bottom: 1px solid #E7EDF4;
}

.painel .evento:last-child {
    border-bottom: 0;
}

.painel .evento strong {
    color: #0B2948;
}

.painel .textarea,
.painel .select select {
    border-color: #D6E0EB;
    color: #243247;
    box-shadow: none;
}

.painel .textarea:focus,
.painel .select select:focus {
    border-color: #0B2948;
    box-shadow: 0 0 0 2px rgba(11,41,72,.10);
}

.painel .button {
    border-radius: 8px;
    font-family: 'Montserrat', sans-serif;
    font-weight: 600;
}

.painel .button.is-success { background: #23865B; }
.painel .button.is-link { background: #0B2948; }
.painel .button.is-link.is-light {
    background: #E8F0F8;
    color: #0B2948;
}
.painel .button.is-info { background: #3273A8; }
.painel .button.is-warning {
    background: #E6A23C;
    color: #fff;
}
.painel .button.is-danger { background: #D94B4B; }

.painel .jogador-linha {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px;
    margin-bottom: 9px;
    background: #F7F9FC;
    border: 1px solid #E1E8F0;
    border-radius: 10px;
}

.painel .jogador-identificacao {
    display: flex;
    flex-direction: column;
    min-width: 0;
    overflow-wrap: anywhere;
}

.painel .jogador-identificacao strong { color: #0B2948; }

.painel .jogador-acoes {
    display: flex;
    align-items: center;
    gap: 5px;
    flex-shrink: 0;
}

.painel .jogador-acoes form { margin: 0; }

.painel .form-gol {
    display: flex;
    align-items: center;
    gap: 5px;
}

.painel .form-gol .select select {
    max-width: 175px;
    font-size: .75rem;
}

.painel .botao-amarelo {
    background: #F2C94C;
    color: #493600;
    border: none;
}

.painel .botao-vermelho {
    background: #D94B4B;
    color: #fff;
    border: none;
}

.painel .select-branco select,
.painel .select-branco select option {
    background: #fff;
    color: #000;
}

.painel .textarea {
    background: #fff;
    color: #000;
    border: 1px solid #D6E0EB;
}

.painel .situacao-jogador {
    display: inline-block;
    font-size: .72rem;
    font-weight: 700;
    margin-top: 3px;
}

.painel .situacao-em-campo { color: #23865B; }
.painel .situacao-reserva { color: #3273A8; }
.painel .situacao-substituido { color: #8A94A3; }

@media (max-width: 600px) {
    .painel {
        margin: 14px auto;
        padding: 0 10px;
    }

    .painel .cartao {
        padding: 16px;
        border-radius: 12px;
    }

    .painel .columns {
        margin-left: -8px;
        margin-right: -8px;
    }

    .painel .botoes {
        flex-direction: column;
    }

    .painel .botoes form,
    .painel .botoes .button {
        width: 100%;
    }

    .painel .jogador-linha {
        align-items: flex-start;
        flex-direction: column;
    }

    .painel .jogador-acoes {
        width: 100%;
        flex-wrap: wrap;
    }

    .painel .form-gol {
        flex: 1;
        min-width: 100%;
    }

    .painel .form-gol .select { flex: 1; }

    .painel .form-gol .select select {
        max-width: none;
        width: 100%;
    }
}
</style>

<div class="painel">

    <div class="mb-4">
        <a href="../admin/partidas.php" class="button is-dark">
            Voltar para partidas
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

    <!-- Configuração do elenco -->

    <?php if ($partida['status'] === 'agendada' && $partida['inicio_real'] === null): ?>

        <div class="cartao">

            <h2 class="title is-4">Elenco da partida</h2>

            <div class="notification is-info is-light">
                Cada equipe precisa selecionar pelo menos
                <strong><?= (int) $partida['minimo_atletas'] ?> titulares</strong>
                e pode selecionar até
                <strong><?= (int) $partida['maximo_atletas'] ?> atletas no total</strong>,
                incluindo reservas.
            </div>

            <form method="post">

                <input type="hidden" name="csrf_token" value="<?= escapar($csrf) ?>">
                <input type="hidden" name="acao" value="salvar_elenco">

                <div class="columns">

                    <?php foreach ([
                        ['nome' => $nomeCasa, 'atletas' => $atletasCasa],
                        ['nome' => $nomeVisitante, 'atletas' => $atletasVisitante]
                    ] as $equipe): ?>

                        <div class="column">

                            <h3 class="title is-5">
                                <?= escapar($equipe['nome']) ?>
                            </h3>

                            <?php if (empty($equipe['atletas'])): ?>
                                <p class="has-text-grey">
                                    Nenhum atleta aprovado nesta modalidade.
                                </p>
                            <?php endif; ?>

                            <?php foreach ($equipe['atletas'] as $atleta): ?>

                                <div class="field">
                                    <label class="label">
                                        <?= escapar($atleta['nome']) ?>

                                        <?php if ($atleta['numero_camisa'] !== null): ?>
                                            <span class="has-text-grey">
                                                #<?= (int) $atleta['numero_camisa'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </label>

                                    <div class="select is-fullwidth select-branco">
                                        <select name="elenco[<?= (int) $atleta['id'] ?>]">

                                            <option value="">
                                                Não selecionado
                                            </option>

                                            <option
                                                value="titular"
                                                <?= ($atleta['funcao_inicial'] ?? '') === 'titular' ? 'selected' : '' ?>
                                            >
                                                Titular
                                            </option>

                                            <option
                                                value="reserva"
                                                <?= ($atleta['funcao_inicial'] ?? '') === 'reserva' ? 'selected' : '' ?>
                                            >
                                                Reserva
                                            </option>

                                        </select>
                                    </div>
                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php endforeach; ?>

                </div>

                <button type="submit" class="button is-link">
                    Salvar elenco da partida
                </button>

            </form>
        </div>

    <?php endif; ?>

    <!-- Jogadores e ações -->

    <div class="cartao">

        <h2 class="title is-4">Jogadores e ações</h2>

        <p class="has-text-grey mb-4">
            Registre gols, assistências, cartões e substituições.
        </p>

        <div class="columns">

            <?php
            $equipes = [
                ['nome' => $nomeCasa, 'atletas' => $atletasCasa],
                ['nome' => $nomeVisitante, 'atletas' => $atletasVisitante]
            ];
            ?>

            <?php foreach ($equipes as $equipe): ?>

                <div class="column">

                    <h3 class="title is-5">
                        <?= escapar($equipe['nome']) ?>
                    </h3>

                    <?php
                    $atletasDaEquipe = array_values(array_filter(
                        $equipe['atletas'],
                        fn($a) => !empty($a['funcao_inicial'])
                    ));
                    ?>

                    <?php if (empty($atletasDaEquipe)): ?>

                        <p class="has-text-grey is-size-7">
                            O elenco desta equipe ainda não foi configurado.
                        </p>

                    <?php else: ?>

                        <?php foreach ($atletasDaEquipe as $atleta): ?>

                            <div class="jogador-linha">

                                <div class="jogador-identificacao">

                                    <strong><?= escapar($atleta['nome']) ?></strong>

                                    <?php if ($atleta['numero_camisa'] !== null): ?>
                                        <span class="has-text-grey">
                                            #<?= (int) $atleta['numero_camisa'] ?>
                                        </span>
                                    <?php endif; ?>

                                    <span class="situacao-jogador
                                        <?= $atleta['situacao_atual'] === 'em_campo'
                                            ? 'situacao-em-campo'
                                            : ($atleta['situacao_atual'] === 'reserva'
                                                ? 'situacao-reserva'
                                                : 'situacao-substituido') ?>">

                                        <?= match ($atleta['situacao_atual']) {
                                            'em_campo' => 'Em campo',
                                            'reserva' => 'Reserva',
                                            'substituido' => 'Substituído',
                                            default => 'Não definido'
                                        } ?>

                                    </span>

                                </div>

                                <?php if ($atleta['situacao_atual'] === 'em_campo' && !$encerrada): ?>

                                    <div class="jogador-acoes">

                                        <form method="post" class="form-gol">

                                            <input type="hidden" name="csrf_token" value="<?= escapar($csrf) ?>">
                                            <input type="hidden" name="acao" value="gol_atleta">
                                            <input type="hidden" name="atleta_id" value="<?= (int) $atleta['id'] ?>">

                                            <div class="select is-small select-branco">
                                                <select name="assistencia_id">
                                                    <option value="">Sem assistência</option>

                                                    <?php foreach ($atletasDaEquipe as $colega): ?>

                                                        <?php if (
                                                            (int) $colega['id'] === (int) $atleta['id'] ||
                                                            $colega['situacao_atual'] === 'substituido'
                                                        ) {
                                                            continue;
                                                        } ?>

                                                        <option value="<?= (int) $colega['id'] ?>">
                                                            Assistência: <?= escapar($colega['nome']) ?>
                                                        </option>

                                                    <?php endforeach; ?>

                                                </select>
                                            </div>

                                            <button type="submit" class="button is-small is-success" title="Registrar gol">
                                                ⚽
                                            </button>

                                        </form>

                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= escapar($csrf) ?>">
                                            <input type="hidden" name="acao" value="cartao_amarelo">
                                            <input type="hidden" name="atleta_id" value="<?= (int) $atleta['id'] ?>">

                                            <button type="submit" class="button is-small botao-amarelo" title="Cartão amarelo">
                                                🟨
                                            </button>
                                        </form>

                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= escapar($csrf) ?>">
                                            <input type="hidden" name="acao" value="cartao_vermelho">
                                            <input type="hidden" name="atleta_id" value="<?= (int) $atleta['id'] ?>">

                                            <button type="submit" class="button is-small botao-vermelho" title="Cartão vermelho">
                                                🟥
                                            </button>
                                        </form>

                                    </div>

                                <?php endif; ?>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                    <!-- Botão de substituição -->

                    <?php if (
                        $partida['inicio_real'] !== null &&
                        !$encerrada &&
                        !empty($atletasDaEquipe)
                    ): ?>

                        <?php
                        $emCampo = array_filter(
                            $atletasDaEquipe,
                            fn($a) => $a['situacao_atual'] === 'em_campo'
                        );

                        $reservas = array_filter(
                            $atletasDaEquipe,
                            fn($a) => $a['situacao_atual'] === 'reserva'
                        );
                        ?>

                        <?php if (!empty($emCampo) && !empty($reservas)): ?>

                            <button
                                type="button"
                                class="button is-info is-light is-fullwidth mt-3"
                                onclick="abrirSubstituicao('substituicao-<?= (int) $equipe['atletas'][0]['turma_id'] ?>')"
                            >
                                🔄 Substituir jogador
                            </button>

                            <div
                                id="substituicao-<?= (int) $equipe['atletas'][0]['turma_id'] ?>"
                                class="box mt-3"
                                hidden
                            >

                                <h4 class="title is-6">
                                    Substituição — <?= escapar($equipe['nome']) ?>
                                </h4>

                                <form method="post">

                                    <input type="hidden" name="csrf_token" value="<?= escapar($csrf) ?>">
                                    <input type="hidden" name="acao" value="substituir">

                                    <div class="field">

                                        <label class="label">
                                            Jogador que vai sair
                                        </label>

                                        <div class="select is-fullwidth select-branco">
                                            <select name="atleta_sai_id" required>

                                                <option value="">
                                                    Selecione quem está em campo
                                                </option>

                                                <?php foreach ($emCampo as $jogador): ?>
                                                    <option value="<?= (int) $jogador['id'] ?>">
                                                        <?= escapar($jogador['nome']) ?>
                                                    </option>
                                                <?php endforeach; ?>

                                            </select>
                                        </div>

                                    </div>

                                    <div class="field">

                                        <label class="label">
                                            Jogador que vai entrar
                                        </label>

                                        <div class="select is-fullwidth select-branco">
                                            <select name="atleta_entra_id" required>

                                                <option value="">
                                                    Selecione um reserva
                                                </option>

                                                <?php foreach ($reservas as $jogador): ?>
                                                    <option value="<?= (int) $jogador['id'] ?>">
                                                        <?= escapar($jogador['nome']) ?>
                                                    </option>
                                                <?php endforeach; ?>

                                            </select>
                                        </div>

                                    </div>

                                    <div class="buttons">

                                        <button type="submit" class="button is-info">
                                            Confirmar substituição
                                        </button>

                                        <button
                                            type="button"
                                            class="button is-light"
                                            onclick="fecharSubstituicao('substituicao-<?= (int) $equipe['atletas'][0]['turma_id'] ?>')"
                                        >
                                            Cancelar
                                        </button>

                                    </div>

                                </form>

                            </div>

                        <?php endif; ?>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        </div>
    </div>

    <!-- Placar -->

    <div class="cartao">

        <div class="columns is-vcentered has-text-centered">

            <div class="column equipe">

                <h2 class="title is-4"><?= escapar($nomeCasa) ?></h2>

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

                <h2 class="title is-4"><?= escapar($nomeVisitante) ?></h2>

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

    <!-- Cronômetro -->

    <div class="cartao has-text-centered">

        <p class="heading">CRONÔMETRO</p>

        <div
            class="relogio"
            id="cronometro"
            data-segundos="<?= (int) $segundosExibidos ?>"
            data-ativo="<?= $cronometroAtivo ? '1' : '0' ?>"
            data-duracao="<?= (int) $partida['duracao_periodo_min'] * 60 ?>"
            data-periodo="<?= (int) $estado['periodo_atual'] ?>"
            data-total-periodos="<?= (int) $partida['quantidade_periodos'] ?>"
            data-encerrada="<?= $encerrada ? '1' : '0' ?>"
        >
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

        <p class="mb-4" id="informacaoPeriodo">
            Período <?= (int) $estado['periodo_atual'] ?>
            de <?= (int) $partida['quantidade_periodos'] ?>
            ·
            <?= $encerrada
                ? 'Encerrada'
                : ($cronometroAtivo
                    ? 'Em andamento'
                    : ($estado['fase'] === 'intervalo' ? 'Intervalo' : 'Pausada')) ?>
        </p>

        <div class="botoes">

            <?php if (!$cronometroAtivo && !$encerrada): ?>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= escapar($csrf) ?>">

                    <input
                        type="hidden"
                        name="acao"
                        value="<?= $estado['fase'] === 'intervalo' ? 'retomar_periodo' : 'iniciar' ?>"
                    >

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

                    <button
                        class="button is-info is-medium"
                        <?= (int) $estado['periodo_atual'] >= (int) $partida['quantidade_periodos'] ? 'disabled' : '' ?>
                    >
                        Próximo período
                    </button>
                </form>

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

    <!-- Ocorrências -->

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
                        <textarea
                            class="textarea"
                            id="descricao"
                            name="descricao"
                            maxlength="2000"
                            required
                        ></textarea>
                    </div>

                </div>

                <button class="button is-link">
                    Registrar ocorrência
                </button>

            </form>

        </div>

    <?php endif; ?>

    <!-- Histórico -->

    <div class="cartao">

        <h2 class="title is-4">Histórico da partida</h2>

        <div id="historicoPartida">

            <?php if (empty($eventos)): ?>

                <p class="has-text-grey">
                    Nenhuma ocorrência registrada até o momento.
                </p>

            <?php else: ?>

                <?php foreach ($eventos as $evento): ?>

                    <div class="evento">

                        <p>
                            <strong>
                                <?= escapar(nomeTipoEvento($evento['tipo'])) ?>
                            </strong>
                        </p>

                        <p>
                            <?= nl2br(escapar($evento['descricao'])) ?>
                        </p>

                        <p class="is-size-7 has-text-grey mt-1">

                            <?php if ($evento['periodo'] !== null): ?>
                                <?= (int) $evento['periodo'] ?>º período
                                · <?= escapar(formatarTempoJogo($evento['tempo_segundos'])) ?>
                                ·
                            <?php endif; ?>

                            <?= escapar(date('H:i:s', strtotime($evento['criado_em']))) ?>

                            <?php if (!empty($evento['usuario_nome'])): ?>
                                · <?= escapar($evento['usuario_nome']) ?>
                            <?php endif; ?>

                        </p>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </div>

    </div>

</div>

<script>
function abrirSubstituicao(id) {
    const elemento = document.getElementById(id);

    if (elemento) {
        elemento.hidden = false;
        elemento.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    }
}

function fecharSubstituicao(id) {
    const elemento = document.getElementById(id);

    if (elemento) {
        elemento.hidden = true;
    }
}

(() => {

    const relogio = document.getElementById('cronometro');

    if (!relogio) return;

    const botaoNotificacoes = document.getElementById('ativarNotificacoes');
    const statusNotificacoes = document.getElementById('statusNotificacoes');

    let segundosBase = Number(relogio.dataset.segundos || 0);
    let ativo = relogio.dataset.ativo === '1';
    let periodo = Number(relogio.dataset.periodo || 1);
    let encerrada = relogio.dataset.encerrada === '1';

    let duracao = Number(relogio.dataset.duracao || 0);
    let totalPeriodos = Number(relogio.dataset.totalPeriodos || 1);

    let instanteInicial = Date.now();

    let alertaUmMinutoDisparado = false;
    let alertaFimDisparado = false;

    function notificar(titulo, mensagem) {

        if ('vibrate' in navigator) {
            navigator.vibrate([500, 200, 500, 200, 800]);
        }

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

            aviso.append(texto, detalhe);
            document.body.appendChild(aviso);

            setTimeout(() => aviso.remove(), 10000);
        }
    }

    botaoNotificacoes?.addEventListener('click', async () => {

        if (!('Notification' in window)) {
            statusNotificacoes.textContent =
                'Este navegador não oferece suporte a notificações.';
            return;
        }

        try {

            const permissao = await Notification.requestPermission();

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
            total += Math.floor((Date.now() - instanteInicial) / 1000);
        }

        total = Math.max(0, total);

        const minutos = Math.floor(total / 60);
        const segundos = total % 60;

        relogio.textContent =
            String(minutos).padStart(2, '0') +
            ':' +
            String(segundos).padStart(2, '0');

        if (!ativo || encerrada || duracao <= 0) {
            return;
        }

        const limiteUmMinuto = duracao - 60;

        if (
            total >= limiteUmMinuto &&
            total < duracao &&
            !alertaUmMinutoDisparado
        ) {

            alertaUmMinutoDisparado = true;

            notificar(
                'Falta 1 minuto!',
                'O período ' + periodo + ' está chegando ao fim.'
            );
        }

        if (total >= duracao && !alertaFimDisparado) {

            alertaFimDisparado = true;

            if (periodo >= totalPeriodos) {

                notificar(
                    'Tempo da partida encerrado!',
                    'O último período chegou ao fim.'
                );

            } else {

                notificar(
                    'Fim do período!',
                    'O período ' + periodo + ' terminou.'
                );
            }
        }
    }

    function formatarTempo(segundos) {

        if (segundos === null || segundos === undefined) {
            return '--:--';
        }

        const total = Math.max(0, Number(segundos));
        const minutos = Math.floor(total / 60);
        const resto = total % 60;

        return String(minutos).padStart(2, '0') +
            ':' +
            String(resto).padStart(2, '0');
    }

    function formatarHorario(data) {

        if (!data) return '';

        const dataFormatada = new Date(
            String(data).replace(' ', 'T')
        );

        if (Number.isNaN(dataFormatada.getTime())) {
            return data;
        }

        return dataFormatada.toLocaleTimeString('pt-BR', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }

    const nomesEventos = {
        gol: '⚽ Gol',
        correcao_placar: '🖋️ Correção de placar',
        cronometro: '⏱️ Cronômetro',
        periodo: '🏁 Período',
        ocorrencia: '📋 Ocorrência',
        encerramento: '🏆 Fim de partida',
        cartao_amarelo: '🟨 Cartão amarelo',
        cartao_vermelho: '🟥 Cartão vermelho',
        elenco: '👥 Elenco da partida',
        substituicao: '🔄 Substituição'
    };

    function atualizarHistorico(eventos) {

        const historico = document.getElementById('historicoPartida');

        if (!historico) return;

        historico.replaceChildren();

        if (!Array.isArray(eventos) || eventos.length === 0) {

            const vazio = document.createElement('p');
            vazio.className = 'has-text-grey';
            vazio.textContent =
                'Nenhuma ocorrência registrada até o momento.';

            historico.appendChild(vazio);
            return;
        }

        eventos.forEach(evento => {

            const item = document.createElement('div');
            item.className = 'evento';

            const titulo = document.createElement('p');
            const destaque = document.createElement('strong');

            destaque.textContent =
                nomesEventos[evento.tipo] ||
                '📌 Registro da partida';

            titulo.appendChild(destaque);

            const descricao = document.createElement('p');
            descricao.textContent = evento.descricao || '';

            const detalhes = document.createElement('p');
            detalhes.className = 'is-size-7 has-text-grey mt-1';

            const partes = [];

            if (
                evento.periodo !== null &&
                evento.periodo !== undefined
            ) {
                partes.push(evento.periodo + 'º período');
                partes.push(formatarTempo(evento.tempo_segundos));
            }

            const horario = formatarHorario(evento.criado_em);

            if (horario) {
                partes.push(horario);
            }

            if (evento.usuario_nome) {
                partes.push(evento.usuario_nome);
            }

            detalhes.textContent = partes.join(' · ');

            item.append(titulo, descricao, detalhes);
            historico.appendChild(item);
        });
    }

    async function sincronizarPartida() {

        try {

            const resposta = await fetch(
                `../api/partida-status.php?id=<?= (int) $partidaId ?>&t=${Date.now()}`,
                { cache: 'no-store' }
            );

            if (!resposta.ok) return;

            const dados = await resposta.json();

            document.getElementById('placarCasa').textContent =
                dados.placar_casa;

            document.getElementById('placarVisitante').textContent =
                dados.placar_visitante;

            atualizarHistorico(dados.historico);

            const periodoAnterior = periodo;

            segundosBase = Number(dados.segundos);
            ativo = Boolean(dados.ativo);
            periodo = Number(dados.periodo);
            encerrada = Boolean(dados.encerrada);
            duracao = Number(dados.duracao);
            totalPeriodos = Number(dados.total_periodos);

            instanteInicial = Date.now();

            if (periodo !== periodoAnterior) {
                alertaUmMinutoDisparado = false;
                alertaFimDisparado = false;
            }

            relogio.dataset.segundos = dados.segundos;
            relogio.dataset.ativo = dados.ativo ? '1' : '0';
            relogio.dataset.periodo = dados.periodo;
            relogio.dataset.encerrada = dados.encerrada ? '1' : '0';

            atualizar();

            const informacaoPeriodo =
                document.getElementById('informacaoPeriodo');

            if (informacaoPeriodo) {

                informacaoPeriodo.textContent =
                    `Período ${dados.periodo} de ${dados.total_periodos} · ` +
                    (dados.encerrada
                        ? 'Encerrada'
                        : dados.ativo
                            ? 'Em andamento'
                            : dados.fase === 'intervalo'
                                ? 'Intervalo'
                                : 'Pausada');
            }

        } catch (erro) {

            console.error('Erro ao sincronizar partida:', erro);
        }
    }

    atualizar();
    setInterval(atualizar, 1000);

    sincronizarPartida();
    setInterval(sincronizarPartida, 1000);

})();
</script>

<?php
if ($ehAdmin) {
    require_once __DIR__ . '/../../includes/admin/footer.php';
} else {
    require_once __DIR__ . '/../../includes/arbitro/footer.php';
}
?>
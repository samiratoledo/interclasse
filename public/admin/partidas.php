<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

exigirPerfil('admin');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_partidas'])) {
    $_SESSION['csrf_partidas'] = bin2hex(random_bytes(32));
}

$tokenCsrf = $_SESSION['csrf_partidas'];
$erros = [];
$mensagem = '';
$editando = null;

$statusOpcoes = [
    'agendada' => 'Programada',
    'em_andamento' => 'Em andamento',
    'pausada' => 'Pausada',
    'encerrada' => 'Finalizada',
    'cancelada' => 'Cancelada',
];

function escaparPartida(mixed $valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

function validarCsrfPartidas(): bool
{
    return isset($_POST['token_csrf'], $_SESSION['csrf_partidas'])
        && is_string($_POST['token_csrf'])
        && hash_equals($_SESSION['csrf_partidas'], $_POST['token_csrf']);
}

function buscarPartida(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM partidas WHERE id = :id LIMIT 1'
    );

    $stmt->execute(['id' => $id]);
    $partida = $stmt->fetch(PDO::FETCH_ASSOC);

    return $partida ?: null;
}

function buscarConfiguracaoModalidade(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT
            id,
            nome,
            formato_partida,
            duracao_periodo_segundos,
            quantidade_periodos,
            intervalo_segundos,
            quantidade_sets,
            pontos_por_set,
            pontos_set_decisivo,
            limite_pontos,
            duracao_maxima_segundos
         FROM modalidades
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([$id]);

    $modalidade = $stmt->fetch(PDO::FETCH_ASSOC);

    return $modalidade ?: null;
}

function registrarEvento(
    PDO $pdo,
    int $partidaId,
    ?int $usuarioId,
    string $tipo,
    string $descricao
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO eventos_partida
            (partida_id, usuario_id, tipo, descricao)
         VALUES (:partida, :usuario, :tipo, :descricao)'
    );

    $stmt->execute([
        'partida' => $partidaId,
        'usuario' => $usuarioId,
        'tipo' => $tipo,
        'descricao' => $descricao,
    ]);
}

function redirecionarPartidas(string $sucesso): never
{
    header('Location: partidas.php?sucesso=' . urlencode($sucesso));
    exit;
}

function nomeFormatoPartida(string $formato): string
{
    return match ($formato) {
        'periodos' => 'Por períodos',
        'sets' => 'Por sets',
        'tempo_pontos' => 'Por tempo e pontuação',
        'eliminacao' => 'Por eliminações',
        'tempo_individual' => 'Tempo individual',
        'coroas' => 'Por coroas',
        default => 'Não definido',
    };
}

function montarDescricaoFormato(array $config): string
{
    $formato = $config['formato_partida'] ?? '';

    return match ($formato) {
        'periodos' =>
            ((int) ($config['quantidade_periodos'] ?? 0)) .
            ' períodos de ' .
            ((int) ($config['duracao_periodo_segundos'] ?? 0) / 60) .
            ' minutos; intervalo de ' .
            ((int) ($config['intervalo_segundos'] ?? 0)) .
            ' segundos',

        'sets' =>
            'Melhor de ' .
            ((int) ($config['quantidade_sets'] ?? 0)) .
            ' sets; ' .
            ((int) ($config['pontos_por_set'] ?? 0)) .
            ' pontos por set; set decisivo com ' .
            ((int) ($config['pontos_set_decisivo'] ?? 0)) .
            ' pontos',

        'tempo_pontos' =>
            'Limite de ' .
            ((int) ($config['limite_pontos'] ?? 0)) .
            ' pontos; tempo máximo de ' .
            ((int) ($config['duracao_maxima_segundos'] ?? 0) / 60) .
            ' minutos',

        'eliminacao' =>
            !empty($config['duracao_maxima_segundos'])
                ? 'Por eliminações; tempo máximo de ' .
                    ((int) $config['duracao_maxima_segundos'] / 60) .
                    ' minutos'
                : 'Por eliminações, sem tempo máximo definido',

        'tempo_individual' =>
            'Tempo individual de ' .
            ((int) ($config['duracao_maxima_segundos'] ?? 0) / 60) .
            ' minutos',

        'coroas' => 'Vence quem conquistar mais coroas',

        default => 'Formato não configurado',
    };
}

$usuario = usuarioAtual();
$usuarioId = isset($usuario['id']) ? (int) $usuario['id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarCsrfPartidas()) {
        $erros[] = 'Sessão de segurança inválida. Atualize a página e tente novamente.';
    } else {
        $acao = (string) ($_POST['acao'] ?? '');

        $idValidado = filter_var(
            $_POST['id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $id = $idValidado !== false && $idValidado !== null
            ? (int) $idValidado
            : 0;

        if ($acao === 'editar') {
            $editando = $id > 0 ? buscarPartida($pdo, $id) : null;

            if (!$editando) {
                $erros[] = 'Partida não encontrada.';
            }

        } elseif ($acao === 'salvar') {
            $modalidadeId = filter_var(
                $_POST['modalidade_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $turmaCasaId = filter_var(
                $_POST['turma_casa_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $turmaVisitanteId = filter_var(
                $_POST['turma_visitante_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $responsavelValor = trim((string) ($_POST['responsavel_id'] ?? ''));

            $responsavelId = $responsavelValor === ''
                ? null
                : filter_var($responsavelValor, FILTER_VALIDATE_INT);

            $data = trim((string) ($_POST['data'] ?? ''));
            $horario = trim((string) ($_POST['horario'] ?? ''));
            $periodo = (string) ($_POST['periodo_escolar'] ?? '');
            $local = trim((string) ($_POST['local_partida'] ?? ''));

            $placarCasa = filter_var(
                $_POST['placar_casa'] ?? 0,
                FILTER_VALIDATE_INT
            );

            $placarVisitante = filter_var(
                $_POST['placar_visitante'] ?? 0,
                FILTER_VALIDATE_INT
            );

            $status = (string) ($_POST['status'] ?? 'agendada');
            $youtube = trim((string) ($_POST['youtube_url'] ?? ''));

            $youtubeStatus = (string) (
                $_POST['youtube_status'] ?? 'sem_transmissao'
            );

            $observacoes = trim((string) ($_POST['observacoes'] ?? ''));

            $partidaOriginal = $id > 0
                ? buscarPartida($pdo, $id)
                : null;

            if ($id < 0 || ($id > 0 && !$partidaOriginal)) {
                $erros[] = 'A partida selecionada não foi encontrada.';
            }

            if (
                !$modalidadeId || !$turmaCasaId || !$turmaVisitanteId
                || $modalidadeId < 1
                || $turmaCasaId < 1
                || $turmaVisitanteId < 1
            ) {
                $erros[] = 'Selecione uma modalidade e as duas turmas.';
            }

            if (
                $turmaCasaId && $turmaVisitanteId
                && $turmaCasaId === $turmaVisitanteId
            ) {
                $erros[] = 'As duas equipes precisam ser de turmas diferentes.';
            }

            if (!in_array($periodo, ['manha', 'tarde'], true)) {
                $erros[] = 'Selecione o período escolar.';
            }

            if (($data === '') !== ($horario === '')) {
                $erros[] = 'Preencha a data e o horário juntos, ou deixe ambos vazios.';
            }

            $inicioPrevisto = null;

            if ($data !== '' && $horario !== '') {
                $dataHora = DateTime::createFromFormat(
                    '!Y-m-d H:i',
                    $data . ' ' . $horario
                );

                if (
                    !$dataHora
                    || $dataHora->format('Y-m-d H:i') !== $data . ' ' . $horario
                ) {
                    $erros[] = 'Informe uma data e um horário válidos.';
                } else {
                    $inicioPrevisto = $dataHora->format('Y-m-d H:i:s');
                }
            }

            if (
                $placarCasa === false || $placarCasa === null
                || $placarCasa < 0 || $placarCasa > 65535
            ) {
                $erros[] = 'Informe um placar válido para a turma da casa.';
            }

            if (
                $placarVisitante === false || $placarVisitante === null
                || $placarVisitante < 0 || $placarVisitante > 65535
            ) {
                $erros[] = 'Informe um placar válido para a turma visitante.';
            }

            if (!array_key_exists($status, $statusOpcoes)) {
                $erros[] = 'Selecione um status válido.';
            }

            if (!in_array(
                $youtubeStatus,
                ['sem_transmissao', 'agendada', 'ao_vivo', 'encerrada'],
                true
            )) {
                $erros[] = 'Selecione um estado válido para a transmissão.';
            }

            if (mb_strlen($local) > 120 || mb_strlen($youtube) > 500) {
                $erros[] = 'O local ou o link da transmissão ultrapassa o limite permitido.';
            }

            if ($youtube !== '' && (
                !filter_var($youtube, FILTER_VALIDATE_URL)
                || !preg_match('~^https?://~i', $youtube)
            )) {
                $erros[] = 'Informe um link HTTP ou HTTPS válido para o YouTube.';
            }

            if ($youtube === '') {
                $youtubeStatus = 'sem_transmissao';
            }

            if (mb_strlen($observacoes) > 5000) {
                $erros[] = 'As observações devem ter no máximo 5.000 caracteres.';
            }

            if ($responsavelId !== null && (
                $responsavelId === false || $responsavelId < 1
            )) {
                $erros[] = 'Selecione um responsável válido.';
            }

            $configuracao = null;

            if (!$erros) {
                $configuracaoModalidade = buscarConfiguracaoModalidade(
                    $pdo,
                    (int) $modalidadeId
                );

                if (!$configuracaoModalidade) {
                    $erros[] = 'Modalidade não encontrada.';
                } else {
                    // Sempre utiliza a configuração atual da modalidade.
                    $configuracao = [
                        'formato_partida' => $configuracaoModalidade['formato_partida'],
                        'duracao_periodo_segundos' => $configuracaoModalidade['duracao_periodo_segundos'],
                        'quantidade_periodos' => $configuracaoModalidade['quantidade_periodos'],
                        'intervalo_segundos' => $configuracaoModalidade['intervalo_segundos'],
                        'quantidade_sets' => $configuracaoModalidade['quantidade_sets'],
                        'pontos_por_set' => $configuracaoModalidade['pontos_por_set'],
                        'pontos_set_decisivo' => $configuracaoModalidade['pontos_set_decisivo'],
                        'limite_pontos' => $configuracaoModalidade['limite_pontos'],
                        'duracao_maxima_segundos' => $configuracaoModalidade['duracao_maxima_segundos'],
                    ];

                    $formatoPermitido = [
                        'periodos',
                        'sets',
                        'tempo_pontos',
                        'eliminacao',
                        'tempo_individual',
                        'coroas',
                    ];

                    if (!in_array($configuracao['formato_partida'], $formatoPermitido, true)) {
                        $erros[] = 'O formato da modalidade não está configurado corretamente.';
                    }

                    if (
                        $configuracao['formato_partida'] === 'periodos'
                        && (
                            (int) $configuracao['duracao_periodo_segundos'] < 1
                            || (int) $configuracao['quantidade_periodos'] < 1
                        )
                    ) {
                        $erros[] = 'Configure a duração e a quantidade de períodos da modalidade.';
                    }

                    if (
                        $configuracao['formato_partida'] === 'sets'
                        && (
                            (int) $configuracao['quantidade_sets'] < 1
                            || (int) $configuracao['pontos_por_set'] < 1
                            || (int) $configuracao['pontos_set_decisivo'] < 1
                        )
                    ) {
                        $erros[] = 'Configure a quantidade de sets e a pontuação da modalidade.';
                    }

                    if (
                        $configuracao['formato_partida'] === 'tempo_pontos'
                        && (
                            (int) $configuracao['limite_pontos'] < 1
                            || (int) $configuracao['duracao_maxima_segundos'] < 1
                        )
                    ) {
                        $erros[] = 'Configure o limite de pontos e o tempo máximo da modalidade.';
                    }

                    if (
                        $configuracao['formato_partida'] === 'tempo_individual'
                        && (int) $configuracao['duracao_maxima_segundos'] < 1
                    ) {
                        $erros[] = 'Configure o tempo individual da modalidade.';
                    }
                }
            }

            if (!$erros) {
                $stmt = $pdo->prepare(
                    'SELECT COUNT(*) FROM turmas WHERE id IN (?, ?)'
                );

                $stmt->execute([$turmaCasaId, $turmaVisitanteId]);

                if ((int) $stmt->fetchColumn() !== 2) {
                    $erros[] = 'Uma das turmas não foi encontrada.';
                }

                if ($responsavelId !== null) {
                    $stmt = $pdo->prepare(
                        "SELECT COUNT(*) FROM usuarios
                         WHERE id = ? AND ativo = 1
                           AND perfil IN ('admin', 'arbitro')"
                    );

                    $stmt->execute([$responsavelId]);

                    if ((int) $stmt->fetchColumn() !== 1) {
                        $erros[] = 'O responsável precisa ser um administrador ou árbitro ativo.';
                    }
                }
            }

            if (!$erros && $configuracao) {
                try {
                    $pdo->beginTransaction();

                    $duracaoSegundos = (int) (
                        $configuracao['duracao_periodo_segundos'] ?? 0
                    );

                    $quantidadePeriodos = (int) (
                        $configuracao['quantidade_periodos'] ?? 0
                    );

                    $intervaloSegundos = (int) (
                        $configuracao['intervalo_segundos'] ?? 0
                    );

                    // Valores de compatibilidade com os campos antigos.
                    $duracaoMinutos = $duracaoSegundos > 0
                        ? max(1, (int) ceil($duracaoSegundos / 60))
                        : 10;

                    $intervaloMinutos = (int) floor($intervaloSegundos / 60);

                    $dados = [
                        'modalidade' => $modalidadeId,
                        'casa' => $turmaCasaId,
                        'visitante' => $turmaVisitanteId,
                        'inicio' => $inicioPrevisto,
                        'periodo' => $periodo,
                        'local' => $local !== '' ? $local : null,
                        'responsavel' => $responsavelId,

                        'duracao_legada' => $duracaoMinutos,

                        // A coluna antiga pode ser obrigatória.
                        // Modalidades sem períodos recebem 1.
                        'quantidade_periodos' => $quantidadePeriodos > 0
                            ? $quantidadePeriodos
                            : 1,

                        'intervalo_legado' => $intervaloMinutos,

                        'formato' => $configuracao['formato_partida'],
                        'duracao_segundos' => $configuracao['duracao_periodo_segundos'],
                        'intervalo_segundos' => $intervaloSegundos,
                        'quantidade_sets' => $configuracao['quantidade_sets'],
                        'pontos_por_set' => $configuracao['pontos_por_set'],
                        'pontos_set_decisivo' => $configuracao['pontos_set_decisivo'],
                        'limite_pontos' => $configuracao['limite_pontos'],
                        'duracao_maxima' => $configuracao['duracao_maxima_segundos'],

                        'youtube' => $youtube !== '' ? $youtube : null,
                        'youtube_status' => $youtubeStatus,
                        'placar_casa' => $placarCasa,
                        'placar_visitante' => $placarVisitante,
                        'status' => $status,
                        'observacoes' => $observacoes !== '' ? $observacoes : null,
                    ];

                    if ($id > 0) {
                        $sql = 'UPDATE partidas SET
                                    modalidade_id = :modalidade,
                                    turma_casa_id = :casa,
                                    turma_visitante_id = :visitante,
                                    inicio_previsto = :inicio,
                                    periodo_escolar = :periodo,
                                    local_partida = :local,
                                    responsavel_id = :responsavel,

                                    duracao_periodo_min = :duracao_legada,
                                    quantidade_periodos = :quantidade_periodos,
                                    intervalo_min = :intervalo_legado,

                                    formato_partida = :formato,
                                    duracao_periodo_segundos = :duracao_segundos,
                                    intervalo_segundos = :intervalo_segundos,
                                    quantidade_sets = :quantidade_sets,
                                    pontos_por_set = :pontos_por_set,
                                    pontos_set_decisivo = :pontos_set_decisivo,
                                    limite_pontos = :limite_pontos,
                                    duracao_maxima_segundos = :duracao_maxima,

                                    youtube_url = :youtube,
                                    youtube_status = :youtube_status,
                                    placar_casa = :placar_casa,
                                    placar_visitante = :placar_visitante,
                                    status = :status,
                                    observacoes = :observacoes
                                WHERE id = :id';

                        $dados['id'] = $id;

                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($dados);

                        registrarEvento(
                            $pdo,
                            $id,
                            $usuarioId,
                            'edicao',
                            'Dados da partida atualizados pelo painel administrativo.'
                        );

                        $pdo->commit();
                        redirecionarPartidas('editada');
                    }

                    $sql = 'INSERT INTO partidas (
                                modalidade_id,
                                turma_casa_id,
                                turma_visitante_id,
                                inicio_previsto,
                                periodo_escolar,
                                local_partida,
                                responsavel_id,

                                duracao_periodo_min,
                                quantidade_periodos,
                                intervalo_min,

                                formato_partida,
                                duracao_periodo_segundos,
                                intervalo_segundos,
                                quantidade_sets,
                                pontos_por_set,
                                pontos_set_decisivo,
                                limite_pontos,
                                duracao_maxima_segundos,

                                youtube_url,
                                youtube_status,
                                placar_casa,
                                placar_visitante,
                                status,
                                observacoes
                            ) VALUES (
                                :modalidade,
                                :casa,
                                :visitante,
                                :inicio,
                                :periodo,
                                :local,
                                :responsavel,

                                :duracao_legada,
                                :quantidade_periodos,
                                :intervalo_legado,

                                :formato,
                                :duracao_segundos,
                                :intervalo_segundos,
                                :quantidade_sets,
                                :pontos_por_set,
                                :pontos_set_decisivo,
                                :limite_pontos,
                                :duracao_maxima,

                                :youtube,
                                :youtube_status,
                                :placar_casa,
                                :placar_visitante,
                                :status,
                                :observacoes
                            )';

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($dados);

                    $novoId = (int) $pdo->lastInsertId();

                    registrarEvento(
                        $pdo,
                        $novoId,
                        $usuarioId,
                        'criacao',
                        'Partida cadastrada pelo painel administrativo.'
                    );

                    $pdo->commit();
                    redirecionarPartidas('cadastrada');

                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    error_log('ERRO AO SALVAR PARTIDA: ' . $e->getMessage());

                    // Exibição temporária para diagnóstico.
                    $erros[] = 'Erro técnico ao salvar: ' . $e->getMessage();
                }
            }

            $editando = [
                'id' => $id ?: '',
                'modalidade_id' => $modalidadeId ?: '',
                'turma_casa_id' => $turmaCasaId ?: '',
                'turma_visitante_id' => $turmaVisitanteId ?: '',
                'inicio_previsto' => $inicioPrevisto,
                'periodo_escolar' => $periodo,
                'local_partida' => $local,
                'responsavel_id' => $responsavelId ?: '',
                'youtube_url' => $youtube,
                'youtube_status' => $youtubeStatus,
                'placar_casa' => $placarCasa,
                'placar_visitante' => $placarVisitante,
                'status' => $status,
                'observacoes' => $observacoes,
            ];

            if ($configuracao) {
                $editando = array_merge($editando, $configuracao);
            }

        } elseif ($acao === 'cancelar') {
            $partida = $id > 0 ? buscarPartida($pdo, $id) : null;

            if (!$partida) {
                $erros[] = 'Partida não encontrada.';
            } elseif ($partida['status'] === 'encerrada') {
                $erros[] = 'Uma partida finalizada não pode ser cancelada por esta ação.';
            } elseif ($partida['status'] === 'cancelada') {
                $erros[] = 'Esta partida já está cancelada.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        "UPDATE partidas SET status = 'cancelada' WHERE id = ?"
                    );

                    $stmt->execute([$id]);

                    registrarEvento(
                        $pdo,
                        $id,
                        $usuarioId,
                        'cancelamento',
                        'Partida cancelada pelo painel administrativo.'
                    );

                    $pdo->commit();
                    redirecionarPartidas('cancelada');

                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    error_log('Erro ao cancelar partida: ' . $e->getMessage());
                    $erros[] = 'Não foi possível cancelar a partida: ' . $e->getMessage();
                }
            }

        } else {
            $erros[] = 'Ação inválida.';
        }
    }
}

if (isset($_GET['sucesso'])) {
    $mensagem = match ($_GET['sucesso']) {
        'cadastrada' => 'Partida cadastrada com sucesso!',
        'editada' => 'Partida atualizada com sucesso!',
        'cancelada' => 'Partida cancelada com sucesso!',
        default => '',
    };
}

/*
 * Carrega as modalidades com todas as configurações.
 */
$modalidades = $pdo->query(
    'SELECT
        id,
        nome,
        categoria,
        formato_partida,
        duracao_periodo_segundos,
        quantidade_periodos,
        intervalo_segundos,
        quantidade_sets,
        pontos_por_set,
        pontos_set_decisivo,
        limite_pontos,
        duracao_maxima_segundos
     FROM modalidades
     ORDER BY nome'
)->fetchAll(PDO::FETCH_ASSOC);

$turmas = $pdo->query(
    'SELECT id, nome, periodo FROM turmas ORDER BY periodo, nome'
)->fetchAll(PDO::FETCH_ASSOC);

$responsaveis = $pdo->query(
    "SELECT id, nome FROM usuarios
     WHERE ativo = 1 AND perfil IN ('admin', 'arbitro')
     ORDER BY nome"
)->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query(
    "SELECT p.*,
            m.nome AS modalidade,
            m.categoria,
            tc.nome AS turma_casa,
            tv.nome AS turma_visitante,
            u.nome AS responsavel
     FROM partidas p
     INNER JOIN modalidades m ON m.id = p.modalidade_id
     INNER JOIN turmas tc ON tc.id = p.turma_casa_id
     INNER JOIN turmas tv ON tv.id = p.turma_visitante_id
     LEFT JOIN usuarios u ON u.id = p.responsavel_id
     ORDER BY
        CASE p.status
            WHEN 'em_andamento' THEN 1
            WHEN 'pausada' THEN 2
            WHEN 'agendada' THEN 3
            WHEN 'encerrada' THEN 4
            ELSE 5
        END,
        p.inicio_previsto IS NULL,
        p.inicio_previsto ASC,
        p.id DESC"
);

$partidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$valoresFormulario = $editando ?? [
    'id' => '',
    'modalidade_id' => '',
    'turma_casa_id' => '',
    'turma_visitante_id' => '',
    'inicio_previsto' => '',
    'periodo_escolar' => 'manha',
    'local_partida' => '',
    'responsavel_id' => '',
    'youtube_url' => '',
    'youtube_status' => 'sem_transmissao',
    'placar_casa' => 0,
    'placar_visitante' => 0,
    'status' => 'agendada',
    'observacoes' => '',
];

$dataFormulario = '';
$horarioFormulario = '';

if (!empty($valoresFormulario['inicio_previsto'])) {
    $timestamp = strtotime((string) $valoresFormulario['inicio_previsto']);

    if ($timestamp !== false) {
        $dataFormulario = date('Y-m-d', $timestamp);
        $horarioFormulario = date('H:i', $timestamp);
    }
}

/*
 * Configuração exibida inicialmente no formulário.
 */
$configuracaoInicial = null;

if (!empty($valoresFormulario['modalidade_id'])) {
    foreach ($modalidades as $modalidade) {
        if ((int) $modalidade['id'] === (int) $valoresFormulario['modalidade_id']) {
            $configuracaoInicial = $modalidade;
            break;
        }
    }

    if (!empty($valoresFormulario['id']) && !empty($valoresFormulario['formato_partida'])) {
        $configuracaoInicial = array_merge(
            $configuracaoInicial ?? [],
            $valoresFormulario
        );
    }
}

$modalidadesJson = [];

foreach ($modalidades as $modalidade) {
    $modalidadesJson[(string) $modalidade['id']] = [
        'nome' => $modalidade['nome'],
        'formato' => $modalidade['formato_partida'],
        'duracao' => $modalidade['duracao_periodo_segundos'],
        'periodos' => $modalidade['quantidade_periodos'],
        'intervalo' => $modalidade['intervalo_segundos'],
        'sets' => $modalidade['quantidade_sets'],
        'pontosSet' => $modalidade['pontos_por_set'],
        'pontosDecisivo' => $modalidade['pontos_set_decisivo'],
        'limitePontos' => $modalidade['limite_pontos'],
        'duracaoMaxima' => $modalidade['duracao_maxima_segundos'],
    ];
}

require_once __DIR__ . '/../../includes/admin/header.php';
?>

<div class="mb-5">
    <h1 class="title is-3">Gerenciar partidas</h1>
    <p class="subtitle is-6">
        Cadastre, edite e acompanhe os confrontos do Interclasse.
    </p>
</div>

<?php if ($mensagem !== ''): ?>
    <div class="notification is-success is-dark">
        <button class="delete" type="button"
                onclick="this.parentElement.remove()"></button>
        <?= escaparPartida($mensagem) ?>
    </div>
<?php endif; ?>

<?php if ($erros): ?>
    <div class="notification is-danger is-dark">
        <strong>Confira os seguintes pontos:</strong>
        <ul class="mt-2">
            <?php foreach ($erros as $erro): ?>
                <li><?= escaparPartida($erro) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="box mb-5">
    <h2 class="title is-5">
        <?= !empty($valoresFormulario['id']) ? 'Editar partida' : 'Cadastrar partida' ?>
    </h2>

    <form method="POST" action="partidas.php">
        <input type="hidden" name="token_csrf" value="<?= escaparPartida($tokenCsrf) ?>">
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" value="<?= escaparPartida($valoresFormulario['id']) ?>">

        <div class="columns is-multiline">
            <div class="column is-6">
                <label class="label">Modalidade *</label>
                <div class="select is-fullwidth">
                    <select name="modalidade_id" id="modalidade_id" required>
                        <option value="">Selecione</option>

                        <?php foreach ($modalidades as $m): ?>
                            <option value="<?= (int) $m['id'] ?>"
                                <?= (string) $valoresFormulario['modalidade_id'] === (string) $m['id'] ? 'selected' : '' ?>>
                                <?= escaparPartida($m['nome']) ?> —
                                <?= escaparPartida(ucfirst($m['categoria'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php foreach ([
                'turma_casa_id' => 'Turma da casa *',
                'turma_visitante_id' => 'Turma visitante *'
            ] as $campo => $rotulo): ?>
                <div class="column is-3">
                    <label class="label"><?= $rotulo ?></label>

                    <div class="select is-fullwidth">
                        <select name="<?= escaparPartida($campo) ?>" required>
                            <option value="">Selecione</option>

                            <?php foreach ($turmas as $t): ?>
                                <option value="<?= (int) $t['id'] ?>"
                                    <?= (string) $valoresFormulario[$campo] === (string) $t['id'] ? 'selected' : '' ?>>
                                    <?= escaparPartida($t['nome']) ?>
                                    (<?= $t['periodo'] === 'manha' ? 'Manhã' : 'Tarde' ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="column is-3">
                <label class="label">Data</label>
                <input class="input" type="date" name="data"
                       value="<?= escaparPartida($dataFormulario) ?>">
            </div>

            <div class="column is-3">
                <label class="label">Horário</label>
                <input class="input" type="time" name="horario"
                       value="<?= escaparPartida($horarioFormulario) ?>">
            </div>

            <div class="column is-3">
                <label class="label">Período escolar *</label>

                <div class="select is-fullwidth">
                    <select name="periodo_escolar" required>
                        <option value="manha"
                            <?= $valoresFormulario['periodo_escolar'] === 'manha' ? 'selected' : '' ?>>
                            Manhã
                        </option>

                        <option value="tarde"
                            <?= $valoresFormulario['periodo_escolar'] === 'tarde' ? 'selected' : '' ?>>
                            Tarde
                        </option>
                    </select>
                </div>
            </div>

            <div class="column is-3">
                <label class="label">Local</label>

                <input class="input" type="text" name="local_partida"
                       maxlength="120"
                       value="<?= escaparPartida($valoresFormulario['local_partida']) ?>"
                       placeholder="Ex.: Quadra coberta">
            </div>

            <div class="column is-4">
                <label class="label">Árbitro ou responsável</label>

                <div class="select is-fullwidth">
                    <select name="responsavel_id">
                        <option value="">A definir</option>

                        <?php foreach ($responsaveis as $r): ?>
                            <option value="<?= (int) $r['id'] ?>"
                                <?= (string) $valoresFormulario['responsavel_id'] === (string) $r['id'] ? 'selected' : '' ?>>
                                <?= escaparPartida($r['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div id="configuracaoModalidade" class="notification is-info is-dark mt-3"
             style="display:none;">
            <strong class="is-block mb-2">Formato da modalidade</strong>

            <p id="formatoNome" class="mb-2"></p>

            <div id="detalhesFormato"></div>
        </div>

        <div class="columns is-multiline mt-2">
            <div class="column is-3">
                <label class="label">Placar da casa</label>

                <input class="input" type="number" name="placar_casa"
                       min="0" max="65535" required
                       value="<?= escaparPartida($valoresFormulario['placar_casa']) ?>">
            </div>

            <div class="column is-3">
                <label class="label">Placar visitante</label>

                <input class="input" type="number" name="placar_visitante"
                       min="0" max="65535" required
                       value="<?= escaparPartida($valoresFormulario['placar_visitante']) ?>">
            </div>

            <div class="column is-3">
                <label class="label">Status</label>

                <div class="select is-fullwidth">
                    <select name="status" required>
                        <?php foreach ($statusOpcoes as $valor => $rotulo): ?>
                            <option value="<?= escaparPartida($valor) ?>"
                                <?= $valoresFormulario['status'] === $valor ? 'selected' : '' ?>>
                                <?= escaparPartida($rotulo) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="column is-3">
                <label class="label">Transmissão</label>

                <div class="select is-fullwidth">
                    <select name="youtube_status" id="youtube_status">
                        <?php foreach ([
                            'sem_transmissao' => 'Sem transmissão',
                            'agendada' => 'Agendada',
                            'ao_vivo' => 'Ao vivo',
                            'encerrada' => 'Encerrada',
                        ] as $valor => $rotulo): ?>
                            <option value="<?= escaparPartida($valor) ?>"
                                <?= $valoresFormulario['youtube_status'] === $valor ? 'selected' : '' ?>>
                                <?= escaparPartida($rotulo) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="column is-6">
                <label class="label">Link do YouTube</label>

                <input class="input" type="url" name="youtube_url"
                       maxlength="500"
                       value="<?= escaparPartida($valoresFormulario['youtube_url']) ?>"
                       placeholder="https://www.youtube.com/...">
            </div>

            <div class="column is-6">
                <label class="label">Observações</label>

                <textarea class="textarea" name="observacoes"
                          maxlength="5000" rows="2"><?= escaparPartida($valoresFormulario['observacoes']) ?></textarea>
            </div>
        </div>

        <div class="buttons mt-4">
            <button type="submit" class="button is-link">
                <?= !empty($valoresFormulario['id']) ? 'Salvar alterações' : 'Cadastrar partida' ?>
            </button>

            <?php if (!empty($valoresFormulario['id'])): ?>
                <a href="partidas.php" class="button is-dark">
                    Cancelar edição
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="box">
    <h2 class="title is-5">
        Partidas cadastradas (<?= count($partidas) ?>)
    </h2>

    <?php if (!$partidas): ?>
        <p class="has-text-centered has-text-grey p-5">
            Nenhuma partida cadastrada ainda.
        </p>
    <?php else: ?>
        <div class="table-container">
            <table class="table is-fullwidth is-striped is-hoverable">
                <thead>
                    <tr>
                        <th>Confronto</th>
                        <th>Modalidade</th>
                        <th>Data e horário</th>
                        <th>Local</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($partidas as $p): ?>
                        <tr>
                            <td>
                                <strong><?= escaparPartida($p['turma_casa']) ?></strong>
                                <?= (int) $p['placar_casa'] ?>
                                ×
                                <?= (int) $p['placar_visitante'] ?>
                                <strong><?= escaparPartida($p['turma_visitante']) ?></strong>
                            </td>

                            <td>
                                <?= escaparPartida($p['modalidade']) ?>
                                <br>
                                <small><?= escaparPartida(ucfirst($p['categoria'])) ?></small>
                                <br>
                                <small class="has-text-grey">
                                    <?= escaparPartida(nomeFormatoPartida($p['formato_partida'])) ?>
                                </small>
                            </td>

                            <td>
                                <?php if ($p['inicio_previsto']): ?>
                                    <?= date('d/m/Y H:i', strtotime($p['inicio_previsto'])) ?>
                                <?php else: ?>
                                    A definir
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= escaparPartida($p['local_partida'] ?: 'A definir') ?>
                            </td>

                            <td>
                                <span class="tag <?= match ($p['status']) {
                                    'em_andamento' => 'is-success',
                                    'pausada' => 'is-warning',
                                    'encerrada' => 'is-info',
                                    'cancelada' => 'is-danger',
                                    default => 'is-dark',
                                } ?>">
                                    <?= escaparPartida($statusOpcoes[$p['status']] ?? $p['status']) ?>
                                </span>
                            </td>

                            <td>
                                <div class="buttons">
                                    <form method="POST" action="partidas.php">
                                        <input type="hidden" name="token_csrf"
                                               value="<?= escaparPartida($tokenCsrf) ?>">
                                        <input type="hidden" name="acao" value="editar">
                                        <input type="hidden" name="id"
                                               value="<?= (int) $p['id'] ?>">

                                        <button type="submit" class="button is-small is-link">
                                            Editar
                                        </button>
                                    </form>

                                    <a class="button is-small is-success"
                                       href="../arbitro/partida.php?id=<?= (int) $p['id'] ?>">
                                        Controlar partida
                                    </a>

                                    <a class="button is-small is-info"
                                       href="../partida.php?id=<?= (int) $p['id'] ?>"
                                       target="_blank" rel="noopener noreferrer">
                                        Ver página pública
                                    </a>

                                    <?php if (!in_array($p['status'], ['encerrada', 'cancelada'], true)): ?>
                                        <form method="POST" action="partidas.php"
                                              onsubmit="return confirm('Deseja cancelar esta partida?');">
                                            <input type="hidden" name="token_csrf"
                                                   value="<?= escaparPartida($tokenCsrf) ?>">
                                            <input type="hidden" name="acao" value="cancelar">
                                            <input type="hidden" name="id"
                                                   value="<?= (int) $p['id'] ?>">

                                            <button type="submit"
                                                    class="button is-small is-danger is-dark">
                                                Cancelar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
const configuracoesModalidades = <?= json_encode(
    $modalidadesJson,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) ?>;

const modalidadeSelect = document.getElementById('modalidade_id');
const painelConfiguracao = document.getElementById('configuracaoModalidade');
const formatoNome = document.getElementById('formatoNome');
const detalhesFormato = document.getElementById('detalhesFormato');

const nomesFormatos = {
    periodos: 'Por períodos',
    sets: 'Por sets',
    tempo_pontos: 'Por tempo e pontuação',
    eliminacao: 'Por eliminações',
    tempo_individual: 'Tempo individual',
    coroas: 'Por coroas'
};

function formatarDuracao(segundos) {
    if (!segundos) {
        return 'Não definido';
    }

    const minutos = Math.floor(segundos / 60);
    const resto = segundos % 60;

    if (resto === 0) {
        return minutos + ' minuto(s)';
    }

    if (minutos === 0) {
        return resto + ' segundo(s)';
    }

    return minutos + 'min' + String(resto).padStart(2, '0') + 's';
}

function mostrarConfiguracaoModalidade(id) {
    const config = configuracoesModalidades[id];

    if (!config) {
        painelConfiguracao.style.display = 'none';
        return;
    }

    painelConfiguracao.style.display = 'block';

    formatoNome.textContent =
        'Formato: ' + (nomesFormatos[config.formato] || 'Não definido');

    let detalhes = '';

    switch (config.formato) {
        case 'periodos':
            detalhes = `
                <p><strong>Duração:</strong>
                    ${config.periodos} período(s) de
                    ${formatarDuracao(config.duracao)}
                </p>
                <p><strong>Intervalo:</strong>
                    ${formatarDuracao(config.intervalo)}
                </p>
            `;
            break;

        case 'sets':
            detalhes = `
                <p><strong>Quantidade:</strong>
                    Melhor de ${config.sets} sets
                </p>
                <p><strong>Pontuação dos sets:</strong>
                    ${config.pontosSet} pontos
                </p>
                <p><strong>Set decisivo:</strong>
                    ${config.pontosDecisivo} pontos
                </p>
                <p class="has-text-grey is-size-7">
                    Os sets precisam respeitar a diferença de pontos prevista no regulamento.
                </p>
            `;
            break;

        case 'tempo_pontos':
            detalhes = `
                <p><strong>Limite de pontuação:</strong>
                    ${config.limitePontos} pontos
                </p>
                <p><strong>Tempo máximo:</strong>
                    ${formatarDuracao(config.duracaoMaxima)}
                </p>
            `;
            break;

        case 'eliminacao':
            detalhes = `
                <p><strong>Objetivo:</strong> Eliminar os adversários.</p>
                <p><strong>Tempo máximo:</strong>
                    ${config.duracaoMaxima
                        ? formatarDuracao(config.duracaoMaxima)
                        : 'Sem limite definido'}
                </p>
            `;
            break;

        case 'tempo_individual':
            detalhes = `
                <p><strong>Tempo individual:</strong>
                    ${formatarDuracao(config.duracaoMaxima)}
                </p>
            `;
            break;

        case 'coroas':
            detalhes = `
                <p><strong>Objetivo:</strong>
                    Conquistar mais coroas que o adversário.
                </p>
            `;
            break;
    }

    detalhesFormato.innerHTML = detalhes;
}

modalidadeSelect.addEventListener('change', function () {
    mostrarConfiguracaoModalidade(this.value);
});

if (modalidadeSelect.value) {
    mostrarConfiguracaoModalidade(modalidadeSelect.value);
}
</script>

<?php require_once __DIR__ . '/../../includes/admin/footer.php'; ?>

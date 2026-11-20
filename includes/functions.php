<?php

declare(strict_types=1);

/**
 * Funções comuns usadas pelas páginas públicas.
 * (urlBase() fica no includes/auth.php.)
 */

function escapar(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

const STATUS_PARTIDA = [
    'agendada'     => ['Agendada', 'is-info is-light'],
    'em_andamento' => ['Ao vivo', 'is-danger'],
    'pausada'      => ['Pausada', 'is-warning'],
    'encerrada'    => ['Encerrada', 'is-success is-light'],
    'cancelada'    => ['Cancelada', 'is-dark is-light'],
];

const PERIODOS_ESCOLARES = ['manha' => 'Manhã', 'tarde' => 'Tarde'];

/** Duas primeiras letras do nome (sem depender da extensão mbstring). */
function iniciais(string $nome): string
{
    return preg_match('/^.{1,2}/u', trim($nome), $m) ? $m[0] : '';
}

/** Consulta base das partidas, já com nomes, brasões e estado do cronômetro. */
function sqlPartidas(): string
{
    return "
        SELECT
            p.id, p.status, p.inicio_previsto, p.periodo_escolar,
            p.modalidade_id, p.turma_casa_id, p.turma_visitante_id,
            p.turma_vencedora_id, p.tipo_resultado,
            p.local_partida, p.placar_casa, p.placar_visitante,
            p.quantidade_periodos, p.duracao_periodo_min,
            p.youtube_url, p.youtube_status,
            m.nome  AS modalidade_nome,
            tc.nome AS casa_nome,  tc.imagem AS casa_imagem,
            tv.nome AS visitante_nome, tv.imagem AS visitante_imagem,
            e.periodo_atual, e.segundos_acumulados,
            e.cronometro_iniciado_em, e.fase
        FROM partidas p
        INNER JOIN modalidades m  ON m.id  = p.modalidade_id
        INNER JOIN turmas tc      ON tc.id = p.turma_casa_id
        INNER JOIN turmas tv      ON tv.id = p.turma_visitante_id
        LEFT  JOIN estado_partida e ON e.partida_id = p.id
    ";
}

/** Segundos decorridos no período atual (o servidor é a fonte do tempo). */
function segundosDecorridos(array $p): int
{
    $s = (int) ($p['segundos_acumulados'] ?? 0);

    if (!empty($p['cronometro_iniciado_em'])) {
        $inicio = strtotime((string) $p['cronometro_iniciado_em']);
        if ($inicio !== false) {
            $s += max(0, time() - $inicio);
        }
    }

    return $s;
}

function formatarTempo(int $segundos): string
{
    return sprintf('%02d:%02d', intdiv($segundos, 60), $segundos % 60);
}

function formatarDataHora(?string $data, bool $comDiaSemana = false): string
{
    if (!$data) {
        return 'Horário a definir';
    }
    $ts = strtotime($data);
    if (!$ts) {
        return 'Horário a definir';
    }
    $dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    $prefixo = $comDiaSemana ? $dias[(int) date('w', $ts)] . ', ' : '';
    return $prefixo . date('d/m · H:i', $ts);
}

/** Dados que o JavaScript usa para atualizar um cartão de partida. */
function estadoPublico(array $p): array
{
    $rodando = !empty($p['cronometro_iniciado_em']) && $p['status'] === 'em_andamento';

    return [
        'id'        => (int) $p['id'],
        'status'    => $p['status'],
        'placar_casa'      => (int) $p['placar_casa'],
        'placar_visitante' => (int) $p['placar_visitante'],
        'periodo'   => (int) ($p['periodo_atual'] ?? 1),
        'fase'      => $p['fase'] ?? 'jogo',
        'segundos'  => segundosDecorridos($p),
        'rodando'   => $rodando,
        'youtube_status' => $p['youtube_status'],
    ];
}

/**
 * Extrai o ID do vídeo de uma URL do YouTube.
 * Retorna null se o domínio não for do YouTube ou o ID for inválido.
 */
function youtubeId(?string $url): ?string
{
    if (!$url) {
        return null;
    }

    $partes = parse_url(trim($url));
    if (!$partes || empty($partes['host']) || !preg_match('~^https?$~i', $partes['scheme'] ?? '')) {
        return null;
    }

    $host = strtolower($partes['host']);
    $host = preg_replace('~^(www\.|m\.)~', '', $host);
    $caminho = $partes['path'] ?? '';
    $id = null;

    if ($host === 'youtu.be') {
        $id = trim($caminho, '/');
    } elseif (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
        if ($caminho === '/watch') {
            parse_str($partes['query'] ?? '', $q);
            $id = $q['v'] ?? null;
        } elseif (preg_match('~^/(live|embed|shorts)/([^/?]+)~', $caminho, $m)) {
            $id = $m[2];
        }
    }

    return (is_string($id) && preg_match('~^[A-Za-z0-9_-]{11}$~', $id)) ? $id : null;
}

/** Imagem do brasão (caminho relativo à pasta public/) ou null. */
function brasaoSrc(?string $caminho): ?string
{
    if (!$caminho || str_contains($caminho, '..')) {
        return null;
    }
    $arquivo = __DIR__ . '/../public/' . $caminho;
    return is_file($arquivo) ? $caminho : null;
}

/** Cartão de partida (usado na página inicial e na programação). */
function cartaoPartida(array $p, string $raiz = ''): string
{
    [$rotuloStatus, $classeStatus] = STATUS_PARTIDA[$p['status']] ?? ['—', 'is-light'];
    $ativa = in_array($p['status'], ['em_andamento', 'pausada'], true);
    $mostrarPlacar = $p['status'] !== 'agendada';
    $temLive = $p['youtube_status'] === 'ao_vivo' && youtubeId($p['youtube_url']);

    $time = static function (string $nome, ?string $img) use ($raiz): string {
        $src = brasaoSrc($img);
        $brasao = $src
            ? '<img class="pub-brasao" src="' . escapar($raiz . $src) . '" alt="">'
            : '<span class="pub-brasao pub-brasao--vazio" aria-hidden="true">' . escapar(iniciais($nome)) . '</span>';
        return '<div class="pub-time">' . $brasao . '<span class="pub-time__nome">' . escapar($nome) . '</span></div>';
    };

    $html  = '<a class="pub-card' . ($ativa ? ' pub-card--ao-vivo' : '') . '" href="' . escapar($raiz . 'partida.php?id=' . (int) $p['id']) . '"';
    $html .= ' data-partida-id="' . (int) $p['id'] . '">';
    $html .= '<div class="pub-card__topo"><span class="pub-card__modalidade">' . escapar($p['modalidade_nome']) . '</span>';
    $html .= '<span class="tag ' . $classeStatus . '" data-campo="status">' . escapar($rotuloStatus) . '</span></div>';
    $html .= '<div class="pub-card__confronto">' . $time($p['casa_nome'], $p['casa_imagem']);
    $html .= '<div class="pub-placar"><span data-campo="placar_casa">' . ($mostrarPlacar ? (int) $p['placar_casa'] : '–') . '</span>';
    $html .= '<span class="pub-placar__x">×</span>';
    $html .= '<span data-campo="placar_visitante">' . ($mostrarPlacar ? (int) $p['placar_visitante'] : '–') . '</span></div>';
    $html .= $time($p['visitante_nome'], $p['visitante_imagem']) . '</div>';
    $html .= '<div class="pub-card__rodape">';
    $html .= '<span data-campo="tempo">' . ($ativa ? 'P' . (int) ($p['periodo_atual'] ?? 1) . ' · ' . formatarTempo(segundosDecorridos($p)) : escapar(formatarDataHora($p['inicio_previsto'], true))) . '</span>';
    if (!empty($p['local_partida'])) {
        $html .= '<span>' . escapar($p['local_partida']) . '</span>';
    }
    if ($temLive) {
        $html .= '<span class="tag is-danger is-light">▶ Transmissão</span>';
    }
    $html .= '</div></a>';

    return $html;
}

/** Fases do mata-mata (e fase de grupos), na ordem em que acontecem. */
const FASES_PARTIDA = [
    'grupos'         => 'Fase de grupos',
    'oitavas'        => 'Oitavas de final',
    'quartas'        => 'Quartas de final',
    'semifinal'      => 'Semifinal',
    'final'          => 'Final',
    'terceiro_lugar' => 'Disputa de 3º lugar',
];

/**
 * Id da turma vencedora de uma partida encerrada.
 * Retorna null em caso de empate ou se a partida ainda não terminou.
 * (W.O. usa turma_vencedora_id, que o admin/árbitro preenche.)
 */
function vencedoraPartida(array $p): ?int
{
    if (($p['status'] ?? '') !== 'encerrada') {
        return null;
    }

    if (!empty($p['turma_vencedora_id'])) {
        return (int) $p['turma_vencedora_id'];
    }

    $casa = (int) ($p['placar_casa'] ?? 0);
    $visitante = (int) ($p['placar_visitante'] ?? 0);

    if ($casa > $visitante) {
        return (int) $p['turma_casa_id'];
    }
    if ($visitante > $casa) {
        return (int) $p['turma_visitante_id'];
    }

    return null;
}

/** Brasão da turma (imagem ou iniciais), no mesmo padrão dos cartões. */
function brasaoHtml(string $nome, ?string $imagem, string $classeExtra = ''): string
{
    $src = brasaoSrc($imagem);
    $classe = trim('pub-brasao ' . $classeExtra);

    return $src
        ? '<img class="' . escapar($classe) . '" src="' . escapar($src) . '" alt="" loading="lazy">'
        : '<span class="' . escapar($classe) . ' pub-brasao--vazio" aria-hidden="true">' . escapar(iniciais($nome)) . '</span>';
}

/** "cesta_fora_arco" -> "Cesta fora arco" (rótulo simples para tipos de evento). */
function rotuloTipoEvento(string $tipo): string
{
    $mapa = [
        'gol' => 'Gol',
        'assistencia' => 'Assistência',
        'cartao_amarelo' => 'Cartão amarelo',
        'cartao_vermelho' => 'Cartão vermelho',
        'cesta_dentro_arco' => 'Cesta dentro do arco',
        'cesta_fora_arco' => 'Cesta fora do arco',
        'lance_livre' => 'Lance livre',
        'ponto' => 'Ponto',
        'ace' => 'Ace',
        'bloqueio' => 'Bloqueio',
        'eliminacao' => 'Eliminação',
        'defesa' => 'Defesa',
        'vitoria' => 'Vitória',
        'coroa' => 'Coroa',
    ];

    return $mapa[$tipo] ?? ucfirst(str_replace('_', ' ', $tipo));
}
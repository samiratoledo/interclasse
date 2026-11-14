<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

function nomeTipoEvento(string $tipo): string
{
    return [
        'gol' => '⚽ Gol',
        'correcao_placar' => '🖋️ Correção de placar',
        'periodo' => '🏁 Período',
        'cronometro' => '⏱️ Cronômetro',
        'encerramento' => '🏆 Fim de partida',
    ][$tipo] ?? '📌 Registro da partida';
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    http_response_code(400);
    exit('Partida inválida.');
}

$partida = null;
$eventos = [];

try {
    $stmt = $pdo->prepare(sqlPartidas() . ' WHERE p.id = ? LIMIT 1');
    $stmt->execute([$id]);
    $partida = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($partida) {
        // Carrega os eventos que podem ser exibidos ao público.
        $stmt = $pdo->prepare("
            SELECT tipo, descricao, periodo, criado_em
            FROM eventos_partida
            WHERE partida_id = ?
              AND tipo IN (
                  'gol',
                  'correcao_placar',
                  'periodo',
                  'cronometro',
                  'encerramento'
              )
            ORDER BY criado_em DESC, id DESC
            LIMIT 40
        ");

        $stmt->execute([$id]);
        $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Não foi possível carregar a partida agora.');
}


if (!$partida) {
    http_response_code(404);
    exit('Partida não encontrada.');
}

[$rotuloStatus, $classeStatus] =
    STATUS_PARTIDA[$partida['status']] ?? ['—', 'is-light'];

$inicioReal = $partida['inicio_real'] ?? null;

$iniciada = $inicioReal !== null
    || in_array($partida['status'], ['em_andamento', 'pausada', 'encerrada'], true);

$ativa = in_array($partida['status'], [
    'em_andamento',
    'pausada'
], true);

$encerrada = $partida['status'] === 'encerrada';

$mostrarPlacar = $iniciada;

$ytId = youtubeId($partida['youtube_url']);
$ytMostrar = $ytId !== null &&
    $partida['youtube_status'] !== 'sem_transmissao';

$ytLink = $ytId
    ? 'https://www.youtube.com/watch?v=' . $ytId
    : null;

$ytEmbed = $ytId
    ? 'https://www.youtube-nocookie.com/embed/' . $ytId
    : null;

$brasao = static function (string $nome, ?string $img): string {
    $src = brasaoSrc($img);

    return $src
        ? '<img class="pub-brasao pub-brasao--grande" src="' .
        escapar($src) . '" alt="">'
        : '<span class="pub-brasao pub-brasao--grande pub-brasao--vazio" aria-hidden="true">' .
        escapar(iniciais($nome)) .
        '</span>';
};

$tituloPagina = $partida['casa_nome'] . ' × ' . $partida['visitante_nome'];
$paginaAtual = 'partidas';

require __DIR__ . '/../includes/header.php';
?>

<div class="container pub-conteudo" data-partida-id="<?= (int) $partida['id'] ?>" data-detalhe="1">
    <p class="mb-3">
        <a href="partidas.php">← Todas as partidas</a>
    </p>

    <section class="pub-placar-grande">

        <div class="pub-placar-grande__topo">
            <span><?= escapar($partida['modalidade_nome']) ?></span>

            <span class="tag <?= escapar($classeStatus) ?>" data-campo="status">
                <?= escapar($rotuloStatus) ?>
            </span>
        </div>

        <div class="pub-card__confronto">

            <div class="pub-time">
                <?= $brasao(
                    $partida['casa_nome'],
                    $partida['casa_imagem']
                ) ?>

                <span class="pub-time__nome">
                    <?= escapar($partida['casa_nome']) ?>
                </span>
            </div>

            <div class="pub-placar pub-placar--grande">
                <span data-campo="placar_casa">
                    <?= $mostrarPlacar
                        ? (int) $partida['placar_casa']
                        : '–' ?>
                </span>

                <span class="pub-placar__x">×</span>

                <span data-campo="placar_visitante">
                    <?= $mostrarPlacar
                        ? (int) $partida['placar_visitante']
                        : '–' ?>
                </span>
            </div>

            <div class="pub-time">
                <?= $brasao(
                    $partida['visitante_nome'],
                    $partida['visitante_imagem']
                ) ?>

                <span class="pub-time__nome">
                    <?= escapar($partida['visitante_nome']) ?>
                </span>
            </div>

        </div>

        <p class="pub-relogio" data-campo="tempo">
            <?php if ($encerrada): ?>
                Fim de jogo

            <?php elseif ($ativa): ?>
                P<?= (int) ($partida['periodo_atual'] ?? 1) ?>
                · <?= formatarTempo(segundosDecorridos($partida)) ?>

            <?php else: ?>
                <?= escapar(formatarDataHora($partida['inicio_previsto'])) ?>
            <?php endif; ?>
        </p>

    </section>

    <?php if ($ytMostrar): ?>
        <section class="pub-secao">

            <h2 class="pub-secao__titulo">
                <?php if ($partida['youtube_status'] === 'ao_vivo'): ?>
                    <span class="pub-ponto"></span>
                    Transmissão ao vivo
                <?php elseif ($partida['youtube_status'] === 'agendada'): ?>
                    Transmissão programada
                <?php else: ?>
                    Transmissão
                <?php endif; ?>
            </h2>

            <div class="pub-video">
                <iframe src="<?= escapar($ytEmbed) ?>" title="Transmissão da partida" loading="lazy"
                    allow="accelerometer; encrypted-media; picture-in-picture; fullscreen"
                    referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
            </div>

            <p class="mt-3 is-size-7">
                Se o vídeo não carregar,
                <a href="<?= escapar($ytLink) ?>" target="_blank" rel="noopener noreferrer">
                    abra no YouTube
                </a>.
            </p>

        </section>
    <?php endif; ?>

    <section class="pub-secao">

        <h2 class="pub-secao__titulo">Informações</h2>

        <dl class="pub-info">
            <div>
                <dt>Horário previsto</dt>
                <dd>
                    <?= escapar(formatarDataHora($partida['inicio_previsto'])) ?>
                </dd>
            </div>

            <div>
                <dt>Período</dt>
                <dd>
                    <?= escapar(
                        PERIODOS_ESCOLARES[$partida['periodo_escolar'] ?? ''] ?? '—'
                    ) ?>
                </dd>
            </div>

            <div>
                <dt>Local</dt>
                <dd>
                    <?= escapar($partida['local_partida'] ?: 'A definir') ?>
                </dd>
            </div>

            <div>
                <dt>Formato</dt>
                <dd>
                    <?= (int) $partida['quantidade_periodos'] ?>
                    período(s) de
                    <?= (int) $partida['duracao_periodo_min'] ?>
                    min
                </dd>
            </div>
        </dl>

    </section>

    <section class="pub-secao" id="secaoTimeline">

        <h2 class="pub-secao__titulo">Linha do tempo</h2>

        <?php if ($eventos): ?>

            <ul class="pub-timeline">
                <?php foreach ($eventos as $e): ?>
                    <li>
                        <time>
                            <?= escapar(date(
                                'H:i',
                                strtotime($e['criado_em'])
                            )) ?>
                        </time>

                        <div class="pub-timeline__conteudo">
                            <strong>
                                <?= escapar(nomeTipoEvento($e['tipo'])) ?>
                            </strong>

                            <span>
                                <?= escapar($e['descricao']) ?>
                            </span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

        <?php else: ?>

            <p class="pub-vazio">
                Ainda não há lances registrados.
            </p>

        <?php endif; ?>

    </section>

</div>

<script>
    (() => {
        const container = document.querySelector('[data-detalhe="1"]');

        if (!container) {
            return;
        }

        const partidaId = container.dataset.partidaId;

        const relogio = container.querySelector('[data-campo="tempo"]');
        const placarCasa = container.querySelector('[data-campo="placar_casa"]');
        const placarVisitante = container.querySelector('[data-campo="placar_visitante"]');
        const status = container.querySelector('[data-campo="status"]');

        const secaoTimeline = document.getElementById('secaoTimeline');

        let segundosBase = 0;
        let ativo = false;
        let iniciada = false;
        let encerrada = false;
        let periodo = 1;

        let instanteInicial = Date.now();

        function formatarTempo(segundos) {
            segundos = Math.max(0, Math.floor(Number(segundos) || 0));

            const minutos = Math.floor(segundos / 60);
            const restante = segundos % 60;

            return String(minutos).padStart(2, '0') + ':' +
                String(restante).padStart(2, '0');
        }

        function formatarDataHora(valor) {
            if (!valor) {
                return 'Aguardando início';
            }

            const data = new Date(valor.replace(' ', 'T'));

            if (Number.isNaN(data.getTime())) {
                return valor;
            }

            return data.toLocaleString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function atualizarRelogio() {
            if (!relogio) {
                return;
            }

            if (encerrada) {
                relogio.textContent = 'Fim de jogo';
                return;
            }

            if (!iniciada) {
                return;
            }

            let total = segundosBase;

            if (ativo) {
                total += Math.floor(
                    (Date.now() - instanteInicial) / 1000
                );
            }

            relogio.textContent =
                `P${periodo} · ${formatarTempo(total)}`;
        }

        function atualizarLinhaDoTempo(eventos) {
            if (!secaoTimeline) {
                return;
            }

            const titulo = secaoTimeline.querySelector('h2');

            const lista = document.createElement('ul');
            lista.className = 'pub-timeline';

            if (!Array.isArray(eventos) || eventos.length === 0) {
                const vazio = document.createElement('p');

                vazio.className = 'pub-vazio';
                vazio.textContent = 'Ainda não há lances registrados.';

                secaoTimeline.replaceChildren(titulo, vazio);
                return;
            }

            eventos.forEach(evento => {
                const item = document.createElement('li');

                const horario = document.createElement('time');
                const conteudo = document.createElement('div');
                const destaque = document.createElement('strong');
                const descricao = document.createElement('span');

                conteudo.className = 'pub-timeline__conteudo';

                const nomesEventos = {
                    gol: '⚽ Gol - ',
                    correcao_placar: '🖋️ Correção de placar - ',
                    periodo: '🏁 Período - ',
                    cronometro: '⏱️ Cronômetro - ',
                    encerramento: '🏆 Fim de partida - '
                };

                destaque.textContent =
                    nomesEventos[evento.tipo] || '📌 Registro da partida';

                const data = new Date(
                    String(evento.criado_em).replace(' ', 'T')
                );

                horario.textContent = Number.isNaN(data.getTime())
                    ? ''
                    : data.toLocaleTimeString('pt-BR', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });

                descricao.textContent = evento.descricao;

                conteudo.append(destaque, descricao);
                item.append(horario, conteudo);
                lista.appendChild(item);
            });

            secaoTimeline.replaceChildren(titulo, lista);
        }

        async function atualizarPartida() {
            try {
                const resposta = await fetch(
                    `api/partida-status.php?id=${encodeURIComponent(partidaId)}&_=${Date.now()}`,
                    {
                        cache: 'no-store'
                    }
                );

                if (!resposta.ok) {
                    console.error('Erro na API:', resposta.status);
                    return;
                }

                const dados = await resposta.json();

                if (dados.erro) {
                    console.error(dados.erro);
                    return;
                }

                if (placarCasa) {
                    placarCasa.textContent = dados.iniciada
                        ? dados.placar_casa
                        : '–';
                }

                if (placarVisitante) {
                    placarVisitante.textContent = dados.iniciada
                        ? dados.placar_visitante
                        : '–';
                }

                if (status) {
                    status.textContent = dados.rotulo_status;
                    status.className = 'tag ' + dados.classe_status;
                }

                // Sincroniza o cronômetro com o servidor.
                segundosBase = Math.max(0, Number(dados.segundos) || 0);
                ativo = dados.ativo === true;
                iniciada = dados.iniciada === true;
                encerrada = dados.encerrada === true;
                periodo = Math.max(1, Number(dados.periodo) || 1);

                // Reinicia a referência de tempo da contagem local.
                instanteInicial = Date.now();

                atualizarRelogio();

                if (relogio && !iniciada && !encerrada) {
                    relogio.textContent =
                        formatarDataHora(dados.inicio_previsto);
                }

                atualizarLinhaDoTempo(dados.eventos);

            } catch (erro) {
                console.error('Erro ao atualizar partida:', erro);
            }
        }

        // Atualização inicial.
        atualizarPartida();

        // Sincronização com o servidor a cada 1 segundo.
        setInterval(atualizarPartida, 1000);

        // Contagem visual independente, a cada segundo.
        setInterval(() => {
            atualizarRelogio();
        }, 1000);

    })();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
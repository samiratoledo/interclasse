<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

/*
 * Placar público (modo TV/telão).
 * - placar.php            -> a tela
 * - placar.php?dados=1    -> JSON consultado pela tela a cada poucos segundos
 */

/** Dados de uma partida no formato que a tela do placar consome. */
function itemPlacar(array $p): array
{
    $time = static fn (string $nome, ?string $img): array => [
        'nome' => $nome,
        'imagem' => brasaoSrc($img),
        'iniciais' => iniciais($nome),
    ];

    return estadoPublico($p) + [
        'modalidade' => $p['modalidade_nome'],
        'casa' => $time($p['casa_nome'], $p['casa_imagem']),
        'visitante' => $time($p['visitante_nome'], $p['visitante_imagem']),
        'local' => $p['local_partida'],
        'horario' => formatarDataHora($p['inicio_previsto']),
        'transmissao' => $p['youtube_status'] === 'ao_vivo' && youtubeId($p['youtube_url']) !== null,
    ];
}

if (isset($_GET['dados'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    try {
        $base = sqlPartidas();
        $aoVivo = $pdo->query($base . " WHERE p.status IN ('em_andamento', 'pausada') ORDER BY p.inicio_previsto, p.id")->fetchAll();
        $proximas = $pdo->query($base . " WHERE p.status = 'agendada' ORDER BY p.inicio_previsto IS NULL, p.inicio_previsto, p.id LIMIT 6")->fetchAll();
        $resultados = $pdo->query($base . " WHERE p.status = 'encerrada' ORDER BY p.fim_real DESC, p.id DESC LIMIT 4")->fetchAll();

        echo json_encode([
            'aoVivo' => array_map('itemPlacar', $aoVivo),
            'proximas' => array_map('itemPlacar', $proximas),
            'resultados' => array_map('itemPlacar', $resultados),
        ], JSON_UNESCAPED_UNICODE);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        http_response_code(500);
        echo json_encode(['erro' => 'indisponivel']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Placar ao vivo | Interclasse ETEC Zona Leste</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css">
    <link rel="stylesheet" href="assets/publico.css?v=4">
</head>

<body class="tv-body">

    <header class="tv-topo">
        <a class="tv-marca" href="index.php">
            <img src="assets/logo-etec-zl.png" alt="ETEC Zona Leste">
            <span>Interclasse ETEC Zona Leste</span>
        </a>
        <div class="tv-topo__acoes">
            <span class="tv-relogio" id="tvRelogio" aria-label="Hora atual">--:--</span>
            <button type="button" class="button is-small is-light" id="tvTelaCheia">Tela cheia</button>
            <a class="button is-small is-light" href="index.php">Sair</a>
        </div>
    </header>

    <main class="tv-main">
        <section aria-labelledby="tvTituloAoVivo">
            <h1 class="tv-titulo" id="tvTituloAoVivo"><span class="pub-ponto"></span> Ao vivo agora</h1>
            <div class="tv-grade" id="tvAoVivo"><p class="tv-vazio">Carregando…</p></div>
        </section>

        <section aria-labelledby="tvTituloProximas">
            <h2 class="tv-titulo tv-titulo--menor" id="tvTituloProximas">Próximas partidas</h2>
            <div class="tv-grade tv-grade--pequena" id="tvProximas"></div>
        </section>

        <section aria-labelledby="tvTituloResultados">
            <h2 class="tv-titulo tv-titulo--menor" id="tvTituloResultados">Últimos resultados</h2>
            <div class="tv-grade tv-grade--pequena" id="tvResultados"></div>
        </section>
    </main>

    <noscript><p class="tv-vazio">Ative o JavaScript para ver o placar ao vivo.</p></noscript>

    <script>
        (function () {
            'use strict';

            var INTERVALO_MS = 5000;
            var elAoVivo = document.getElementById('tvAoVivo');
            var elProximas = document.getElementById('tvProximas');
            var elResultados = document.getElementById('tvResultados');
            var relogios = []; // { el, item, desde }

            function el(tag, classe, texto) {
                var n = document.createElement(tag);
                if (classe) { n.className = classe; }
                if (texto !== undefined && texto !== null) { n.textContent = texto; }
                return n;
            }

            function fmt(seg) {
                var m = Math.floor(seg / 60), s = seg % 60;
                return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
            }

            function brasao(time) {
                if (time.imagem) {
                    var img = el('img', 'pub-brasao pub-brasao--grande');
                    img.src = time.imagem;
                    img.alt = '';
                    return img;
                }
                return el('span', 'pub-brasao pub-brasao--grande pub-brasao--vazio', time.iniciais);
            }

            function lado(time) {
                var d = el('div', 'tv-time');
                d.appendChild(brasao(time));
                d.appendChild(el('span', 'tv-time__nome', time.nome));
                return d;
            }

            function cartao(item, tipo) {
                var c = el('article', 'tv-card tv-card--' + tipo);

                var topo = el('div', 'tv-card__topo');
                topo.appendChild(el('span', 'tv-card__modalidade', item.modalidade));
                if (tipo === 'aovivo') {
                    topo.appendChild(el('span', 'tag is-danger', item.status === 'pausada' ? 'Pausada' : 'Ao vivo'));
                } else if (tipo === 'resultado') {
                    topo.appendChild(el('span', 'tag is-success is-light', 'Encerrada'));
                } else {
                    topo.appendChild(el('span', 'tag is-info is-light', 'Agendada'));
                }
                c.appendChild(topo);

                var conf = el('div', 'tv-card__confronto');
                conf.appendChild(lado(item.casa));
                var pl = el('div', 'tv-placar');
                var mostrar = item.status !== 'agendada';
                pl.appendChild(el('span', '', mostrar ? item.placar_casa : '–'));
                pl.appendChild(el('span', 'tv-placar__x', '×'));
                pl.appendChild(el('span', '', mostrar ? item.placar_visitante : '–'));
                conf.appendChild(pl);
                conf.appendChild(lado(item.visitante));
                c.appendChild(conf);

                var rod = el('div', 'tv-card__rodape');
                if (tipo === 'aovivo') {
                    var t = el('span', 'tv-tempo');
                    rod.appendChild(t);
                    relogios.push({ el: t, item: item, desde: Date.now() });
                } else {
                    rod.appendChild(el('span', '', item.horario));
                }
                if (item.local) { rod.appendChild(el('span', '', item.local)); }
                c.appendChild(rod);

                return c;
            }

            function desenhar(container, lista, tipo, vazio) {
                container.textContent = '';
                if (!lista.length) {
                    container.appendChild(el('p', 'tv-vazio', vazio));
                    return;
                }
                lista.forEach(function (item) { container.appendChild(cartao(item, tipo)); });
            }

            function tique() {
                relogios.forEach(function (r) {
                    var seg = r.item.segundos + (r.item.rodando ? Math.floor((Date.now() - r.desde) / 1000) : 0);
                    var prefixo = r.item.fase === 'intervalo' ? 'Intervalo · ' : 'P' + r.item.periodo + ' · ';
                    r.el.textContent = prefixo + fmt(seg);
                });
                var agora = new Date();
                document.getElementById('tvRelogio').textContent =
                    ('0' + agora.getHours()).slice(-2) + ':' + ('0' + agora.getMinutes()).slice(-2);
            }

            function atualizar() {
                if (document.hidden) { return; }
                fetch('placar.php?dados=1', { cache: 'no-store' })
                    .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                    .then(function (d) {
                        relogios = [];
                        desenhar(elAoVivo, d.aoVivo, 'aovivo', 'Nenhuma partida em andamento neste momento.');
                        desenhar(elProximas, d.proximas, 'proxima', 'Nenhuma partida agendada.');
                        desenhar(elResultados, d.resultados, 'resultado', 'Ainda não há partidas encerradas.');
                        tique();
                    })
                    .catch(function () { /* mantém a tela atual e tenta no próximo ciclo */ });
            }

            document.getElementById('tvTelaCheia').addEventListener('click', function () {
                if (document.fullscreenElement) {
                    document.exitFullscreen();
                } else if (document.documentElement.requestFullscreen) {
                    document.documentElement.requestFullscreen();
                }
            });

            atualizar();
            setInterval(atualizar, INTERVALO_MS);
            setInterval(tique, 1000);
            document.addEventListener('visibilitychange', function () { if (!document.hidden) { atualizar(); } });
        })();
    </script>
</body>

</html>
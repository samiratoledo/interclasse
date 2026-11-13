/*
 * Páginas públicas do Interclasse.
 * - menu mobile
 * - atualização periódica de placar/status/cronômetro das partidas na tela
 * O servidor é a fonte oficial; o relógio local só "anima" entre as consultas.
 */
(function () {
    'use strict';

    // ---- menu mobile (Bulma) ----
    document.querySelectorAll('.navbar-burger').forEach(function (b) {
        b.addEventListener('click', function () {
            var alvo = document.getElementById(b.dataset.target);
            var aberto = b.classList.toggle('is-active');
            if (alvo) { alvo.classList.toggle('is-active', aberto); }
            b.setAttribute('aria-expanded', aberto ? 'true' : 'false');
        });
    });

    // ---- atualização das partidas ----
    var ROTULOS = {
        agendada: ['Agendada', 'is-info is-light'],
        em_andamento: ['Ao vivo', 'is-danger'],
        pausada: ['Pausada', 'is-warning'],
        encerrada: ['Encerrada', 'is-success is-light'],
        cancelada: ['Cancelada', 'is-dark is-light']
    };
    var INTERVALO_MS = 5000;

    var elementos = Array.prototype.slice.call(document.querySelectorAll('[data-partida-id]'));
    if (!elementos.length) { return; }

    var ids = [];
    elementos.forEach(function (el) {
        var id = el.getAttribute('data-partida-id');
        if (ids.indexOf(id) === -1) { ids.push(id); }
    });

    var relogios = {}; // id -> { segundos, rodando, periodo, desde }

    function fmt(seg) {
        var m = Math.floor(seg / 60), s = seg % 60;
        return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }

    function campo(el, nome) { return el.querySelector('[data-campo="' + nome + '"]'); }

    function aplicar(p) {
        var rel = relogios[p.id] = {
            segundos: p.segundos, rodando: p.rodando, periodo: p.periodo,
            status: p.status, fase: p.fase, desde: Date.now()
        };
        elementos.forEach(function (el) {
            if (el.getAttribute('data-partida-id') !== String(p.id)) { return; }

            var mostrar = p.status !== 'agendada';
            var c = campo(el, 'placar_casa'), v = campo(el, 'placar_visitante'), st = campo(el, 'status');
            if (c) { c.textContent = mostrar ? p.placar_casa : '–'; }
            if (v) { v.textContent = mostrar ? p.placar_visitante : '–'; }
            if (st && ROTULOS[p.status]) {
                st.textContent = ROTULOS[p.status][0];
                st.className = 'tag ' + ROTULOS[p.status][1];
            }
            el.classList.toggle('pub-card--ao-vivo', p.status === 'em_andamento' || p.status === 'pausada');
            desenharRelogio(el, rel);
        });
    }

    function desenharRelogio(el, rel) {
        var t = campo(el, 'tempo');
        if (!t) { return; }
        var ativa = rel.status === 'em_andamento' || rel.status === 'pausada';
        if (!ativa) { return; } // agendada/encerrada: mantém o texto do servidor
        var seg = rel.segundos + (rel.rodando ? Math.floor((Date.now() - rel.desde) / 1000) : 0);
        var prefixo = rel.fase === 'intervalo' ? 'Intervalo · ' : 'P' + rel.periodo + ' · ';
        t.textContent = prefixo + fmt(seg);
    }

    function tique() {
        elementos.forEach(function (el) {
            var rel = relogios[el.getAttribute('data-partida-id')];
            if (rel && rel.rodando) { desenharRelogio(el, rel); }
        });
    }

    function consultar() {
        if (document.hidden) { return; }
        fetch('api-partidas.php?ids=' + encodeURIComponent(ids.join(',')), { cache: 'no-store' })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
            .then(function (dados) { (dados.partidas || []).forEach(aplicar); })
            .catch(function () { /* mantém o que está na tela; tenta de novo no próximo ciclo */ });
    }

    // Só vale a pena consultar se houver partida que possa mudar.
    var temAtiva = elementos.some(function (el) { return !!el.querySelector('[data-campo="status"]'); });
    if (temAtiva) {
        consultar();
        setInterval(consultar, INTERVALO_MS);
        setInterval(tique, 1000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) { consultar(); } });
    }
})();
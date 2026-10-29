
-- =====================================================
-- INTERCLASSE ETEC
-- BANCO DE DADOS COMPLETO - INSTALAÇÃO DO ZERO
-- =====================================================

CREATE DATABASE IF NOT EXISTS interclasse_etec
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE interclasse_etec;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS
    estado_partida,
    eventos_partida,
    partidas_arbitros,
    auditoria,
    inscricao_atletas,
    inscricoes,
    regulamentos,
    partidas,
    atletas,
    usuarios,
    modalidades,
    turmas;

SET FOREIGN_KEY_CHECKS = 1;


-- =====================================================
-- 1. TURMAS
-- =====================================================

CREATE TABLE turmas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(30) NOT NULL UNIQUE,
    periodo ENUM('manha', 'tarde') NOT NULL,
    imagem VARCHAR(255) NULL,
    ativa BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- 2. MODALIDADES
-- =====================================================

CREATE TABLE modalidades (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    descricao TEXT NULL,

    categoria ENUM(
        'masculino',
        'feminino',
        'misto'
    ) NOT NULL DEFAULT 'misto',

    minimo_atletas SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    maximo_atletas SMALLINT UNSIGNED NOT NULL DEFAULT 30,

    icone VARCHAR(255) NULL,
    ativa BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- 3. USUÁRIOS
-- =====================================================

CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    usuario_login VARCHAR(50) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,

    perfil ENUM(
        'admin',
        'arbitro',
        'representante'
    ) NOT NULL,

    turma_id INT UNSIGNED NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_usuarios_login (usuario_login),
    UNIQUE KEY uq_usuarios_turma_representante (turma_id),

    CONSTRAINT fk_usuarios_turma
        FOREIGN KEY (turma_id)
        REFERENCES turmas(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- 4. ATLETAS
-- =====================================================

CREATE TABLE atletas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    numero_camisa TINYINT UNSIGNED NULL,
    turma_id INT UNSIGNED NOT NULL,
    cadastrado_por INT UNSIGNED NOT NULL,

    status_aprovacao ENUM(
        'pendente',
        'aprovado',
        'rejeitado'
    ) NOT NULL DEFAULT 'pendente',

    aprovado_por INT UNSIGNED NULL,
    aprovado_em DATETIME NULL,

    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_atletas_turma (turma_id),
    INDEX idx_atletas_status (status_aprovacao),
    INDEX idx_atletas_cadastrado_por (cadastrado_por),

    CONSTRAINT fk_atletas_turma
        FOREIGN KEY (turma_id)
        REFERENCES turmas(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_atletas_cadastrado_por
        FOREIGN KEY (cadastrado_por)
        REFERENCES usuarios(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_atletas_aprovado_por
        FOREIGN KEY (aprovado_por)
        REFERENCES usuarios(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- 5. PARTIDAS
-- =====================================================

CREATE TABLE partidas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    modalidade_id INT UNSIGNED NOT NULL,
    turma_casa_id INT UNSIGNED NOT NULL,
    turma_visitante_id INT UNSIGNED NOT NULL,

    inicio_previsto DATETIME NULL,

    periodo_escolar ENUM('manha', 'tarde') NULL,
    local_partida VARCHAR(120) NULL,
    responsavel_id INT UNSIGNED NULL,

    duracao_periodo_min SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    quantidade_periodos TINYINT UNSIGNED NOT NULL DEFAULT 2,
    intervalo_min SMALLINT UNSIGNED NOT NULL DEFAULT 5,

    youtube_url VARCHAR(500) NULL,

    youtube_status ENUM(
        'sem_transmissao',
        'agendada',
        'ao_vivo',
        'encerrada'
    ) NOT NULL DEFAULT 'sem_transmissao',

    inicio_real DATETIME NULL,
    fim_real DATETIME NULL,

    placar_casa SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    placar_visitante SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    status ENUM(
        'agendada',
        'em_andamento',
        'pausada',
        'encerrada',
        'cancelada'
    ) NOT NULL DEFAULT 'agendada',

    observacoes TEXT NULL,

    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    atualizado_em TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_partidas_status (status),
    INDEX idx_partidas_inicio (inicio_previsto),
    INDEX idx_partidas_modalidade (modalidade_id),
    INDEX idx_partidas_turma_casa (turma_casa_id),
    INDEX idx_partidas_turma_visitante (turma_visitante_id),
    INDEX idx_partidas_responsavel (responsavel_id),

    CONSTRAINT fk_partidas_modalidade
        FOREIGN KEY (modalidade_id)
        REFERENCES modalidades(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_partidas_turma_casa
        FOREIGN KEY (turma_casa_id)
        REFERENCES turmas(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_partidas_turma_visitante
        FOREIGN KEY (turma_visitante_id)
        REFERENCES turmas(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_partidas_responsavel
        FOREIGN KEY (responsavel_id)
        REFERENCES usuarios(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- 6. EVENTOS DAS PARTIDAS
-- =====================================================

CREATE TABLE eventos_partida (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    partida_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NULL,

    atleta_id INT UNSIGNED NULL,
    assistencia_id INT UNSIGNED NULL,

    tipo VARCHAR(40) NOT NULL,
    descricao TEXT NOT NULL,

    periodo SMALLINT UNSIGNED NULL,
    tempo_segundos INT UNSIGNED NULL,

    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_eventos_partida (partida_id, criado_em),
    INDEX idx_eventos_atleta (atleta_id),
    INDEX idx_eventos_assistencia (assistencia_id),

    CONSTRAINT fk_eventos_partida
        FOREIGN KEY (partida_id)
        REFERENCES partidas(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    CONSTRAINT fk_eventos_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_eventos_atleta
        FOREIGN KEY (atleta_id)
        REFERENCES atletas(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,

    CONSTRAINT fk_eventos_assistencia
        FOREIGN KEY (assistencia_id)
        REFERENCES atletas(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- 7. ESTADO DAS PARTIDAS
-- =====================================================

CREATE TABLE estado_partida (
    partida_id INT UNSIGNED NOT NULL,

    periodo_atual TINYINT UNSIGNED NOT NULL DEFAULT 1,
    segundos_acumulados INT UNSIGNED NOT NULL DEFAULT 0,
    cronometro_iniciado_em DATETIME NULL,

    fase ENUM(
        'jogo',
        'intervalo',
        'encerrado'
    ) NOT NULL DEFAULT 'jogo',

    atualizado_em TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (partida_id),

    CONSTRAINT fk_estado_partida_partida
        FOREIGN KEY (partida_id)
        REFERENCES partidas(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- 8. REGULAMENTOS
-- =====================================================

CREATE TABLE regulamentos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(100) NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    conteudo LONGTEXT NOT NULL,

    ordem SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,

    atualizado_por INT UNSIGNED DEFAULT NULL,

    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    atualizado_em TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    UNIQUE KEY uk_regulamentos_slug (slug),
    KEY idx_regulamentos_ordem (ordem),

    CONSTRAINT fk_regulamentos_admin
        FOREIGN KEY (atualizado_por)
        REFERENCES usuarios(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================
-- 9. CADASTRO DAS TURMAS
-- =====================================================

INSERT INTO turmas (nome, periodo) VALUES
('1º TEC', 'manha'),
('2º TEC', 'manha'),
('3º TEC', 'manha'),
('1º ADM-A', 'manha'),
('2º ADM-A', 'manha'),
('3º ADM-A', 'manha'),
('1º DS', 'manha'),
('2º DS', 'manha'),
('3º DS', 'manha'),
('1º RH-A', 'manha'),
('2º RH-A', 'manha'),
('3º RH-A', 'manha'),
('1º CONT', 'manha'),
('1º AMS', 'tarde'),
('2º AMS', 'tarde'),
('3º AMS', 'tarde'),
('1º LOG', 'tarde'),
('2º LOG', 'tarde'),
('3º LOG', 'tarde'),
('1º RH-B', 'tarde'),
('1º ADM-B', 'tarde'),
('2º ADM-B', 'tarde'),
('3º ADM-B', 'tarde'),
('1º SJ', 'tarde'),
('2º LOG-N', 'tarde'),
('1º LM', 'tarde'),
('2º LM', 'tarde');


-- =====================================================
-- 10. CADASTRO DAS MODALIDADES
-- =====================================================

INSERT INTO modalidades (nome, descricao, categoria) VALUES
('Futsal Masculino', NULL, 'masculino'),
('Futsal Feminino', NULL, 'feminino'),
('Basquetebol Masculino', NULL, 'masculino'),
('Basquetebol Feminino', NULL, 'feminino'),
('Handebol Masculino', NULL, 'masculino'),
('Handebol Feminino', NULL, 'feminino'),
('Tênis de Mesa Masculino', NULL, 'masculino'),
('Tênis de Mesa Feminino', NULL, 'feminino'),
('Voleibol Misto', 'Modalidade mista', 'misto'),
('Queimada Misto', 'Modalidade mista', 'misto'),
('Xadrez Misto', 'Modalidade mista', 'misto'),
('EAFC FIFA 26 (E-Games) Misto',
 'Modalidade mista de jogos eletrônicos', 'misto'),
('Brawl Stars (E-Games) Misto',
 'Modalidade mista de jogos eletrônicos', 'misto'),
('Free Fire (E-Games) Misto',
 'Modalidade mista de jogos eletrônicos', 'misto'),
('Clash Royale (E-Games) Misto',
 'Modalidade mista de jogos eletrônicos', 'misto');


-- =====================================================
-- 11. CADASTRO DOS REGULAMENTOS TEMPORÁRIOS
-- =====================================================


INSERT INTO regulamentos (slug, titulo, conteudo, ordem) VALUES

('apresentacao', 'Apresentação',
'INTERCLASSE — CULTURA DE PAZ

O Interclasse é um evento esportivo, recreativo e de integração destinado aos estudantes da ETEC Zona Leste.

A iniciativa tem como finalidade promover a prática esportiva, a convivência respeitosa, o trabalho em equipe e o protagonismo estudantil.

Cada turma poderá participar das modalidades disponíveis, respeitando os critérios de inscrição, a quantidade de participantes e as regras específicas de cada competição.

As equipes poderão utilizar mascotes, gritos de guerra, uniformes e outras formas de identificação, desde que respeitem os demais participantes e as normas da instituição.

As regras oficiais das modalidades serão utilizadas como referência, com adaptações necessárias à realidade, à estrutura e à segurança do ambiente escolar.

A participação deverá ser pautada pela inclusão, pelo respeito e pela igualdade de oportunidades.', 1),

('objetivos', 'Objetivos e valores',
'O Interclasse tem como objetivos:

• Promover a cultura de paz e a convivência respeitosa.
• Incentivar o protagonismo, a liderança e a responsabilidade dos estudantes.
• Desenvolver o espírito esportivo, valorizando tanto a vitória quanto o respeito diante da derrota.
• Incentivar a inclusão e o respeito às diferenças.
• Valorizar o trabalho em equipe, a cooperação e a participação coletiva.
• Estimular hábitos saudáveis por meio da prática esportiva.
• Promover a integração entre turmas, cursos, períodos e membros da comunidade escolar.
• Integrar esporte, arte, cultura e lazer.
• Preservar os espaços, materiais e equipamentos utilizados durante o evento.
• Reconhecer atitudes de respeito, solidariedade e fair play.

Os capitães deverão colaborar com a organização da equipe e auxiliar na comunicação com a comissão organizadora.

É proibida qualquer forma de discriminação, intimidação, provocação ofensiva ou violência.

A participação deverá respeitar o Manual do Aluno do Centro Paula Souza e as orientações da unidade escolar.

A organização poderá intervir em situações que comprometam a segurança, a integridade ou o andamento das atividades.

Ao final do evento, poderá ser realizada uma fotografia coletiva com as equipes finalistas, respeitando as orientações institucionais sobre uso de imagem.', 2),

('premiacao', 'Premiação',
'A premiação oficial será destinada às equipes campeãs de cada modalidade.

As premiações previstas são:

• Troféu personalizado para a equipe campeã de cada modalidade.
• Reconhecimento de melhor jogador ou jogadora, quando a modalidade permitir avaliação individual.
• Medalhas de ouro para os atletas inscritos nas equipes campeãs.
• Divulgação dos resultados e de registros do evento nos canais oficiais, conforme autorização institucional.

A escolha do melhor jogador ou jogadora deverá considerar critérios relacionados ao desempenho, à técnica, à participação e ao espírito esportivo.

A cerimônia de premiação será organizada pela comissão, preferencialmente em ordem crescente das modalidades, reservando o encerramento para as equipes campeãs.

Não haverá premiação específica para segundo e terceiro lugares, salvo decisão posterior da organização.

A entrega dos prêmios dependerá da confirmação oficial dos resultados.', 3),

('penalidades', 'Punições e penalidades',
'Todas as equipes e participantes deverão manter uma conduta respeitosa durante as partidas e em todos os espaços do evento.

As penalidades serão aplicadas de acordo com a gravidade da infração, considerando as regras da modalidade, a segurança dos participantes e as orientações da comissão organizadora.

1. Advertência verbal:
• Condutas antidesportivas leves.
• Reclamações excessivas ou desrespeitosas.
• Desrespeito às orientações da arbitragem ou organização.
• Atitudes que prejudiquem o andamento da partida.

2. Advertência formal:
• Reincidência em condutas inadequadas.
• Provocações ofensivas.
• Desrespeito aos adversários, colegas, árbitros ou professores.
• Descumprimento de orientações após advertência verbal.

3. Suspensão ou exclusão da partida:
• Conduta agressiva ou antidesportiva grave.
• Ameaças, ofensas discriminatórias ou intimidação.
• Tentativa de interferir indevidamente na arbitragem.
• Participação irregular de atleta não inscrito ou não autorizado.
• Reincidência em infrações que prejudiquem a segurança ou a competição.

4. Penalidades aplicáveis à equipe:
• Perda de pontos, quando previsto no regulamento da modalidade.
• Perda da partida por W.O., em caso de ausência ou impossibilidade de participação conforme as regras.
• Desclassificação da modalidade em situações graves ou de irregularidade comprovada.

5. Disposições gerais:
• A arbitragem deverá registrar as ocorrências relevantes.
• A comissão organizadora será responsável por analisar situações não previstas e decidir sobre as medidas cabíveis.
• O participante terá a oportunidade de apresentar sua versão dos fatos à organização.
• Nenhuma equipe poderá utilizar atletas não inscritos ou pertencentes a outra equipe.
• As decisões deverão ser comunicadas aos responsáveis pelas equipes.
• Casos que ultrapassem a competência da organização serão encaminhados à coordenação escolar.

As penalidades deverão ser aplicadas com proporcionalidade, imparcialidade e respeito ao caráter educativo do evento.', 4),

('futsal-masculino', 'Futsal masculino',
'Regulamento de referência: regras do futsal adaptadas ao contexto escolar.

1. Formação das equipes:
• 5 jogadores em quadra, incluindo o goleiro.
• Até 4 jogadores reservas.
• A equipe deverá iniciar a partida com o número mínimo de jogadores exigido pela arbitragem.
• As substituições serão ilimitadas e realizadas com autorização da arbitragem, respeitando as regras da modalidade.

2. Duração:
• 2 tempos de 8 minutos corridos.
• Intervalo de 1 minuto e 30 segundos.
• A arbitragem poderá interromper o cronômetro em situações que comprometam a segurança ou o andamento da partida.

3. Faltas:
• As faltas serão registradas pela arbitragem.
• A partir da 5ª falta acumulada por equipe em cada tempo, será aplicada cobrança de tiro livre direto sem barreira, conforme a regra adaptada pela organização.
• Infrações graves poderão resultar em cartão, independentemente da quantidade de faltas acumuladas.

4. Cartões:
• Cartão amarelo: advertência.
• Cartão vermelho: expulsão da partida.
• O atleta expulso não poderá retornar à mesma partida.
• A organização avaliará a possibilidade de suspensão para partidas seguintes em casos graves.

5. Empate:
• Em partidas eliminatórias, o empate será decidido por cobranças de pênaltis.
• Cada equipe realizará 3 cobranças alternadas.
• Persistindo o empate, serão realizadas cobranças alternadas até que haja um vencedor.

6. Conduta:
• É proibido contato físico intencional que coloque em risco a integridade dos participantes.
• Agressões, ameaças e ofensas poderão resultar em expulsão e outras penalidades.
• As decisões da arbitragem deverão ser respeitadas.', 5),

('futsal-feminino', 'Futsal feminino',
'Regulamento de referência: regras do futsal adaptadas ao contexto escolar.

1. Formação das equipes:
• 5 jogadoras em quadra, incluindo a goleira.
• Até 4 jogadoras reservas.
• A equipe deverá iniciar a partida com o número mínimo de jogadoras exigido pela arbitragem.
• As substituições serão ilimitadas e realizadas com autorização da arbitragem, respeitando as regras da modalidade.

2. Duração:
• 2 tempos de 8 minutos corridos.
• Intervalo de 1 minuto e 30 segundos.
• A arbitragem poderá interromper o cronômetro em situações que comprometam a segurança ou o andamento da partida.

3. Faltas:
• As faltas serão registradas pela arbitragem.
• A partir da 5ª falta acumulada por equipe em cada tempo, será aplicada cobrança de tiro livre direto sem barreira, conforme a regra adaptada pela organização.
• Infrações graves poderão resultar em cartão, independentemente da quantidade de faltas acumuladas.

4. Cartões:
• Cartão amarelo: advertência.
• Cartão vermelho: expulsão da partida.
• A atleta expulsa não poderá retornar à mesma partida.
• A organização avaliará a possibilidade de suspensão para partidas seguintes em casos graves.

5. Empate:
• Em partidas eliminatórias, o empate será decidido por cobranças de pênaltis.
• Cada equipe realizará 3 cobranças alternadas.
• Persistindo o empate, serão realizadas cobranças alternadas até que haja uma vencedora.

6. Conduta:
• É proibido contato físico intencional que coloque em risco a integridade das participantes.
• Agressões, ameaças e ofensas poderão resultar em expulsão e outras penalidades.
• As decisões da arbitragem deverão ser respeitadas.', 6),

('basquete-masculino', 'Basquetebol masculino',
'Regulamento de referência: regras do basquetebol 3x3 adaptadas ao contexto escolar.

1. Formação:
• 3 jogadores em quadra.
• Até 2 reservas.
• As substituições serão ilimitadas, respeitando a autorização da arbitragem.

2. Duração:
• 10 minutos corridos ou até uma equipe alcançar 21 pontos.
• A partida será encerrada quando o tempo terminar ou uma equipe atingir 21 pontos, prevalecendo o critério que ocorrer primeiro.
• A arbitragem poderá interromper o cronômetro em situações necessárias.

3. Pontuação:
• Arremessos dentro do arco valem 1 ponto.
• Arremessos realizados fora do arco valem 2 pontos.
• Lances livres valem 1 ponto.

4. Faltas:
• As faltas serão registradas pela arbitragem.
• Infrações deverão ser punidas conforme a gravidade e a situação de jogo.
• A partir da 5ª falta individual, o jogador será retirado da partida, podendo ser substituído por um reserva.
• Faltas antidesportivas ou agressões poderão resultar em exclusão imediata.

5. Empate:
• Em caso de empate ao término do tempo, será realizada prorrogação.
• Vencerá a equipe que marcar primeiro 2 pontos na prorrogação.

6. Conduta:
• É proibido empurrar, segurar ou atingir intencionalmente outro participante.
• Reclamações deverão ser feitas pelo capitão, de forma respeitosa.', 7),

('basquete-feminino', 'Basquetebol feminino',
'Regulamento de referência: regras do basquetebol 3x3 adaptadas ao contexto escolar.

1. Formação:
• 3 jogadoras em quadra.
• Até 2 reservas.
• As substituições serão ilimitadas, respeitando a autorização da arbitragem.

2. Duração:
• 10 minutos corridos ou até uma equipe alcançar 21 pontos.
• A partida será encerrada quando o tempo terminar ou uma equipe atingir 21 pontos, prevalecendo o critério que ocorrer primeiro.
• A arbitragem poderá interromper o cronômetro em situações necessárias.

3. Pontuação:
• Arremessos dentro do arco valem 1 ponto.
• Arremessos realizados fora do arco valem 2 pontos.
• Lances livres valem 1 ponto.

4. Faltas:
• As faltas serão registradas pela arbitragem.
• Infrações deverão ser punidas conforme a gravidade e a situação de jogo.
• A partir da 5ª falta individual, a jogadora será retirada da partida, podendo ser substituída por uma reserva.
• Faltas antidesportivas ou agressões poderão resultar em exclusão imediata.

5. Empate:
• Em caso de empate ao término do tempo, será realizada prorrogação.
• Vencerá a equipe que marcar primeiro 2 pontos na prorrogação.

6. Conduta:
• É proibido empurrar, segurar ou atingir intencionalmente outra participante.
• Reclamações deverão ser feitas pela capitã, de forma respeitosa.', 8),

('handebol-masculino', 'Handebol masculino',
'Regulamento de referência: regras do handebol adaptadas ao contexto escolar.

1. Formação:
• 7 jogadores em quadra, incluindo o goleiro.
• Até 1 reserva.
• As substituições serão ilimitadas, respeitando as regras da modalidade e a autorização da arbitragem.

2. Duração:
• 2 tempos de 6 minutos corridos.
• Intervalo de 1 minuto e 30 segundos.
• O cronômetro poderá ser interrompido pela arbitragem quando necessário.

3. Regras de jogo:
• A bola deverá ser conduzida e passada conforme as regras do handebol.
• Não será permitido empurrar, segurar, golpear ou colocar em risco outro participante.
• O contato físico deverá respeitar os limites da modalidade.
• Infrações poderão resultar em tiro livre ou tiro de 7 metros, conforme a situação.

4. Penalidades:
• Advertência verbal ou cartão amarelo.
• Exclusão temporária, quando aplicada pela arbitragem.
• Cartão vermelho em situações de infração grave.
• O atleta expulso não poderá retornar à partida.

5. Empate:
• Em partidas eliminatórias, serão realizadas 3 cobranças de 7 metros por equipe.
• Persistindo o empate, serão realizadas cobranças alternadas até a definição do vencedor.

6. Conduta:
• Agressões, ameaças e ofensas poderão resultar em expulsão e outras penalidades.
• As decisões da arbitragem deverão ser respeitadas.', 9),

('handebol-feminino', 'Handebol feminino',
'Regulamento de referência: regras do handebol adaptadas ao contexto escolar.

1. Formação:
• 7 jogadoras em quadra, incluindo a goleira.
• Até 1 reserva.
• As substituições serão ilimitadas, respeitando as regras da modalidade e a autorização da arbitragem.

2. Duração:
• 2 tempos de 6 minutos corridos.
• Intervalo de 1 minuto e 30 segundos.
• O cronômetro poderá ser interrompido pela arbitragem quando necessário.

3. Regras de jogo:
• A bola deverá ser conduzida e passada conforme as regras do handebol.
• Não será permitido empurrar, segurar, golpear ou colocar em risco outra participante.
• O contato físico deverá respeitar os limites da modalidade.
• Infrações poderão resultar em tiro livre ou tiro de 7 metros, conforme a situação.

4. Penalidades:
• Advertência verbal ou cartão amarelo.
• Exclusão temporária, quando aplicada pela arbitragem.
• Cartão vermelho em situações de infração grave.
• A atleta expulsa não poderá retornar à partida.

5. Empate:
• Em partidas eliminatórias, serão realizadas 3 cobranças de 7 metros por equipe.
• Persistindo o empate, serão realizadas cobranças alternadas até a definição da vencedora.

6. Conduta:
• Agressões, ameaças e ofensas poderão resultar em expulsão e outras penalidades.
• As decisões da arbitragem deverão ser respeitadas.', 10),

('volei-misto', 'Voleibol misto',
'Regulamento de referência: regras do voleibol adaptadas ao contexto escolar.

1. Formação:
• 6 jogadores em quadra.
• Até 2 reservas.
• A composição das equipes deverá respeitar o caráter misto da modalidade.
• A organização deverá informar previamente o critério de composição das equipes.

2. Sistema de disputa:
• Partida em melhor de 3 sets.
• Os dois primeiros sets serão disputados até 12 pontos, com diferença mínima de 2 pontos.
• O terceiro set, quando necessário, será disputado até 10 pontos, também com diferença mínima de 2 pontos.

3. Regras de jogo:
• Cada equipe poderá realizar até 3 toques antes de enviar a bola à quadra adversária, sem contar o toque de bloqueio.
• O saque deverá ser realizado atrás da linha de fundo.
• A rotação seguirá o sistema 6x0.
• As substituições serão permitidas conforme orientação da arbitragem.

4. Desempate:
• Não haverá empate em sets.
• Caso a partida esteja empatada em 1 a 1, será disputado o terceiro set.
• A equipe que vencer o terceiro set será declarada vencedora.

5. Conduta:
• É proibido tocar intencionalmente na rede durante a ação de jogo.
• Não será permitido invadir a quadra adversária de forma que prejudique o jogo ou coloque alguém em risco.
• As decisões da arbitragem deverão ser respeitadas.', 11),

('queimada-mista', 'Queimada mista',
'Regulamento adaptado ao contexto escolar, priorizando a segurança e a participação.

1. Formação:
• 7 participantes por equipe em quadra.
• Até 1 reserva.
• As equipes deverão respeitar o caráter misto da modalidade.

2. Duração:
• 7 minutos ou até a eliminação de todos os participantes da equipe adversária.
• A partida será encerrada quando ocorrer uma dessas condições.

3. Eliminação:
• Será eliminado o participante atingido diretamente pela bola, desde que o contato ocorra antes de a bola tocar o chão.
• A bola deverá atingir regiões permitidas do corpo.
• Não serão consideradas eliminações quando a bola tocar o chão antes de atingir o participante.
• O participante poderá se defender utilizando as mãos e os braços, conforme orientação da organização.
• Atingir a cabeça ou o rosto não contará como eliminação.
• Arremessos intencionais direcionados à cabeça ou ao rosto serão considerados infração.

4. Segurança:
• É proibido arremessar a bola com força excessiva ou de maneira intencionalmente perigosa.
• Não será permitido empurrar, segurar ou atingir outro participante.
• A organização poderá interromper a partida sempre que identificar risco à segurança.

5. Empate:
• Em caso de empate ao término do tempo, será realizada prorrogação.
• Vencerá a equipe que conseguir a primeira eliminação válida.

6. Conduta:
• A arbitragem será responsável por validar as eliminações.
• Discussões e reclamações deverão ser direcionadas ao responsável pela equipe, de maneira respeitosa.', 12),

('tenis-mesa-masculino', 'Tênis de mesa masculino',
'Regulamento de referência: regras do tênis de mesa adaptadas ao contexto escolar.

1. Sistema de disputa:
• Partida em melhor de 3 sets.
• Cada set será disputado até 11 pontos.
• Para vencer o set, é necessário obter diferença mínima de 2 pontos.

2. Saque:
• O saque será alternado a cada 2 pontos.
• Em caso de empate em 10 a 10, o saque será alternado a cada ponto.
• O saque deverá ser realizado conforme orientação da arbitragem.

3. Pontuação:
• Cada jogada resultará em ponto para um dos participantes.
• Vencerá o set o jogador que atingir a pontuação necessária com a diferença mínima exigida.

4. Desempate:
• A partida será vencida pelo participante que conquistar 2 sets.
• Não haverá empate na partida.

5. Conduta:
• É proibido danificar os materiais ou interferir intencionalmente na partida.
• A arbitragem poderá advertir participantes por conduta antidesportiva.', 13),

('tenis-mesa-feminino', 'Tênis de mesa feminino',
'Regulamento de referência: regras do tênis de mesa adaptadas ao contexto escolar.

1. Sistema de disputa:
• Partida em melhor de 3 sets.
• Cada set será disputado até 11 pontos.
• Para vencer o set, é necessário obter diferença mínima de 2 pontos.

2. Saque:
• O saque será alternado a cada 2 pontos.
• Em caso de empate em 10 a 10, o saque será alternado a cada ponto.
• O saque deverá ser realizado conforme orientação da arbitragem.

3. Pontuação:
• Cada jogada resultará em ponto para uma das participantes.
• Vencerá o set a jogadora que atingir a pontuação necessária com a diferença mínima exigida.

4. Desempate:
• A partida será vencida pela participante que conquistar 2 sets.
• Não haverá empate na partida.

5. Conduta:
• É proibido danificar os materiais ou interferir intencionalmente na partida.
• A arbitragem poderá advertir participantes por conduta antidesportiva.', 14),

('xadrez-misto', 'Xadrez misto',
'Regulamento de referência: regras do xadrez adaptadas ao contexto escolar.

1. Sistema de disputa:
• As partidas serão realizadas individualmente.
• Será utilizado relógio digital ou aplicativo de controle de tempo.
• Cada jogador terá 10 minutos para realizar seus lances.

2. Condições de vitória:
• Xeque-mate.
• Término do tempo do adversário, desde que o jogador ainda tenha material suficiente para dar xeque-mate.
• Desistência do adversário.

3. Empate:
• O empate poderá ocorrer por afogamento, repetição de posição, acordo entre os jogadores ou outras situações previstas nas regras do xadrez.
• Em partidas eliminatórias, caso seja necessário definir um vencedor, será realizada uma partida Blitz com 5 minutos para cada jogador.

4. Conduta:
• Os jogadores deverão respeitar o silêncio e a concentração dos participantes.
• Não será permitido receber ajuda externa durante a partida.
• Qualquer irregularidade deverá ser comunicada ao responsável pela arbitragem.', 15),

('eafc-misto', 'EAFC FIFA 26 — E-Games misto',
'Regulamento adaptado ao contexto escolar.

1. Sistema de disputa:
• Partida única.
• A duração será de 2 tempos de 3 minutos.
• As configurações de jogo serão definidas previamente pela organização.
• Os participantes deverão utilizar as configurações estabelecidas para todos os confrontos.

2. Regras:
• As equipes deverão respeitar os horários e a ordem dos confrontos.
• Não será permitido alterar configurações sem autorização.
• A partida deverá ser realizada no equipamento disponibilizado ou autorizado pela organização.
• Problemas técnicos deverão ser comunicados imediatamente ao responsável.

3. Resultado:
• Vencerá o participante ou equipe que obtiver o maior placar ao término da partida.
• Em caso de empate em eliminatórias, será realizada prorrogação e, se necessário, disputa de pênaltis, conforme configuração definida pela organização.

4. Conduta:
• É proibido atrapalhar intencionalmente o adversário ou danificar os equipamentos.
• Os participantes deverão respeitar as decisões do responsável pela modalidade.', 16),

('brawl-stars-misto', 'Brawl Stars — E-Games misto',
'Regulamento adaptado ao contexto escolar.

1. Sistema de disputa:
• Partida em melhor de 3.
• O modo de jogo e o mapa serão definidos previamente pela organização.
• As equipes deverão utilizar os personagens e configurações permitidos pela organização.

2. Resultado:
• Vencerá a equipe que conquistar 2 vitórias.
• Cada confronto será decidido conforme o objetivo do modo de jogo escolhido.
• Em caso de falha técnica, a organização avaliará se a partida deverá ser reiniciada.

3. Participação:
• Os jogadores deverão estar inscritos e identificados antes do início do confronto.
• Não será permitida a substituição de jogadores durante uma partida em andamento, salvo autorização da organização.

4. Conduta:
• É proibido utilizar recursos externos, programas ou meios que ofereçam vantagem indevida.
• Os participantes deverão respeitar os adversários e a organização.', 17),

('free-fire-misto', 'Free Fire — E-Games misto',
'Regulamento adaptado ao contexto escolar.

1. Sistema de disputa:
• O formato da partida, o mapa e as configurações serão definidos previamente pela organização.
• A composição das equipes deverá respeitar os critérios de inscrição da modalidade.

2. Resultado:
• A classificação será definida pelo desempenho das equipes na partida.
• Serão considerados os critérios previamente divulgados pela organização, incluindo colocação final e pontuação por eliminações, caso adotada.
• Em caso de empate na pontuação, serão utilizados os critérios de desempate divulgados antes da competição.

3. Participação:
• Todos os jogadores deverão estar inscritos.
• Não será permitida a participação de pessoas externas às equipes.
• Problemas técnicos deverão ser comunicados imediatamente ao responsável pela modalidade.

4. Conduta:
• É proibido utilizar programas, modificações ou recursos externos que ofereçam vantagem indevida.
• Os participantes deverão respeitar os adversários e a organização.', 18),

('clash-royale-misto', 'Clash Royale — E-Games misto',
'Regulamento adaptado ao contexto escolar.

1. Sistema de disputa:
• Partida única.
• O formato e as configurações serão definidos previamente pela organização.
• Os participantes deverão utilizar suas próprias contas ou contas autorizadas pela organização.

2. Resultado:
• Vencerá o participante que conquistar o maior número de coroas.
• Em caso de empate, será realizada uma nova partida de desempate.
• A organização deverá manter as mesmas configurações para todos os confrontos.

3. Conduta:
• É proibido utilizar recursos externos ou qualquer meio que ofereça vantagem indevida.
• Os participantes deverão respeitar os adversários e a organização.
• Problemas técnicos deverão ser comunicados imediatamente ao responsável pela modalidade.', 19),

('pontuacao', 'Sistema de pontuação',
'A classificação geral do Interclasse será definida pela soma dos pontos conquistados pelas turmas nas modalidades.

1. Pontuação por modalidade:
• 1º lugar: 10 pontos.
• 2º lugar: 7 pontos.
• 3º lugar: 5 pontos.
• Participação: 2 pontos para as equipes que disputarem a modalidade até sua eliminação regular.

2. Critérios:
• A pontuação será atribuída conforme a classificação final de cada modalidade.
• Equipes desclassificadas por irregularidade poderão perder os pontos da modalidade, conforme decisão da organização.
• A ausência sem justificativa ou o W.O. não garantirá pontos de participação.
• Os resultados deverão ser registrados e conferidos pela comissão organizadora.

3. Desempate na classificação geral:
• Maior número de primeiros lugares.
• Maior número de segundos lugares.
• Maior número de terceiros lugares.
• Persistindo o empate, a organização definirá um critério de desempate previamente comunicado às turmas.

4. Divulgação:
• A classificação geral será atualizada conforme o encerramento das modalidades.
• Os resultados oficiais serão divulgados pelos canais definidos pela organização.

A pontuação poderá ser ajustada pela comissão antes do início das competições, desde que a alteração seja comunicada igualmente a todas as turmas.', 20),

('organizacao', 'Organização e funções',
'A organização do Interclasse será composta pelos seguintes responsáveis:

1. Coordenação geral:
• Professor de Educação Física.
• Responsável pela supervisão geral do evento, validação das regras e acompanhamento das atividades.

2. Comissão de esportes:
• Atlética da Zona Leste.
• Responsável pelo apoio à organização das modalidades, controle dos confrontos, acompanhamento dos resultados e comunicação com as equipes.

3. Arbitragem:
• Responsável por conduzir as partidas, aplicar as regras específicas de cada modalidade, registrar os resultados e comunicar ocorrências à organização.
• Deverá atuar com imparcialidade e respeito a todos os participantes.

4. Capitães:
• Representar suas equipes perante a organização.
• Transmitir orientações aos demais integrantes.
• Auxiliar na organização e no cumprimento dos horários.
• Encaminhar dúvidas e reclamações de forma respeitosa.

5. Disposições gerais:
• A comissão organizadora poderá resolver situações não previstas neste regulamento.
• Alterações necessárias deverão ser comunicadas às equipes antes de sua aplicação, sempre que possível.
• As decisões deverão considerar a segurança, a igualdade de condições e o caráter educativo do evento.
• Todos os participantes deverão respeitar as orientações dos professores, árbitros e responsáveis pela organização.', 21);


-- =====================================================
-- 12. CONFERÊNCIA DO BANCO
-- =====================================================

SHOW TABLES;

SELECT * FROM usuarios;

SELECT * FROM turmas
ORDER BY periodo, id;

SELECT * FROM modalidades
ORDER BY id;

SELECT * FROM regulamentos
ORDER BY ordem;
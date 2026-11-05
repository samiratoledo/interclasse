-- =========================================================
-- INTERCLASSE ETEC - BANCO DE DADOS COMPLETO
-- MySQL 8 (InnoDB, utf8mb4)
-- =========================================================

CREATE DATABASE IF NOT EXISTS interclasse_etec
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE interclasse_etec;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS
    historico_regulamentos,
    estado_partida,
    inscricao_atletas,
    eventos_partida,
    partidas_arbitros,
    partidas,
    inscricoes,
    atletas,
    regulamentos,
    usuarios,
    modalidades,
    turmas;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- 1. TURMAS
-- =========================================================
CREATE TABLE turmas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(30) NOT NULL UNIQUE,
    periodo ENUM('manha', 'tarde') NOT NULL,
    imagem VARCHAR(255) NULL,
    ativa BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- =========================================================
-- 2. MODALIDADES
-- =========================================================
CREATE TABLE modalidades (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE,
    descricao TEXT NULL,
    categoria ENUM('masculino', 'feminino', 'misto')
        NOT NULL DEFAULT 'misto',
    minimo_atletas SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    maximo_atletas SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    icone VARCHAR(255) NULL,
    ativa BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
('EAFC FIFA 26 (E-Games) Misto', 'Modalidade mista de jogos eletrônicos', 'misto'),
('Brawl Stars (E-Games) Misto', 'Modalidade mista de jogos eletrônicos', 'misto'),
('Free Fire (E-Games) Misto', 'Modalidade mista de jogos eletrônicos', 'misto'),
('Clash Royale (E-Games) Misto', 'Modalidade mista de jogos eletrônicos', 'misto');

-- =========================================================
-- 3. USUARIOS
-- Não são criadas contas automaticamente.
-- =========================================================
CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    usuario_login VARCHAR(50) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    perfil ENUM('admin', 'arbitro', 'representante') NOT NULL,
    turma_id INT UNSIGNED NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_usuarios_login (usuario_login),
    UNIQUE KEY uq_usuarios_turma_representante (turma_id),

    CONSTRAINT fk_usuarios_turma
        FOREIGN KEY (turma_id) REFERENCES turmas(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 4. ATLETAS
-- =========================================================
CREATE TABLE atletas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    sexo ENUM('masculino', 'feminino') NOT NULL,
    numero_camisa TINYINT UNSIGNED NULL,
    turma_id INT UNSIGNED NOT NULL,
    cadastrado_por INT UNSIGNED NOT NULL,
    status_aprovacao ENUM('pendente', 'aprovado', 'rejeitado')
        NOT NULL DEFAULT 'pendente',
    aprovado_por INT UNSIGNED NULL,
    aprovado_em DATETIME NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observacao_aprovacao TEXT NULL,

    INDEX idx_atletas_turma (turma_id),
    INDEX idx_atletas_status (status_aprovacao),
    INDEX idx_atletas_cadastrado_por (cadastrado_por),

    CONSTRAINT fk_atletas_turma
        FOREIGN KEY (turma_id) REFERENCES turmas(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_atletas_cadastrado_por
        FOREIGN KEY (cadastrado_por) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_atletas_aprovado_por
        FOREIGN KEY (aprovado_por) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 5. INSCRICOES E ATLETAS INSCRITOS
-- =========================================================
CREATE TABLE inscricoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    turma_id INT UNSIGNED NOT NULL,
    modalidade_id INT UNSIGNED NOT NULL,
    status ENUM('pendente', 'aprovada', 'rejeitada', 'cancelada')
        NOT NULL DEFAULT 'pendente',
    observacao TEXT NULL,
    observacao_admin TEXT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_inscricoes_turma (turma_id),
    INDEX idx_inscricoes_modalidade (modalidade_id),
    INDEX idx_inscricoes_status (status),

    CONSTRAINT fk_inscricoes_turma
        FOREIGN KEY (turma_id) REFERENCES turmas(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_inscricoes_modalidade
        FOREIGN KEY (modalidade_id) REFERENCES modalidades(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inscricao_atletas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inscricao_id INT UNSIGNED NOT NULL,
    atleta_id INT UNSIGNED NOT NULL,

    UNIQUE KEY uq_inscricao_atleta (inscricao_id, atleta_id),
    INDEX idx_inscricao_atletas_atleta (atleta_id),

    CONSTRAINT fk_inscricao_atletas_inscricao
        FOREIGN KEY (inscricao_id) REFERENCES inscricoes(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_inscricao_atletas_atleta
        FOREIGN KEY (atleta_id) REFERENCES atletas(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 6. PARTIDAS
-- Permite partidas simultaneas: nao existe bloqueio por horario.
-- =========================================================
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

    inicio_real DATETIME NULL,
    fim_real DATETIME NULL,
    placar_casa SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    placar_visitante SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    status ENUM('agendada', 'em_andamento', 'pausada', 'encerrada', 'cancelada')
        NOT NULL DEFAULT 'agendada',

    youtube_url VARCHAR(500) NULL,
    youtube_status ENUM('sem_transmissao', 'agendada', 'ao_vivo', 'encerrada')
        NOT NULL DEFAULT 'sem_transmissao',

    observacoes TEXT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_partidas_status (status),
    INDEX idx_partidas_inicio (inicio_previsto),
    INDEX idx_partidas_modalidade (modalidade_id),
    INDEX idx_partidas_turma_casa (turma_casa_id),
    INDEX idx_partidas_turma_visitante (turma_visitante_id),
    INDEX idx_partidas_responsavel (responsavel_id),

    CONSTRAINT fk_partidas_modalidade
        FOREIGN KEY (modalidade_id) REFERENCES modalidades(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_partidas_turma_casa
        FOREIGN KEY (turma_casa_id) REFERENCES turmas(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_partidas_turma_visitante
        FOREIGN KEY (turma_visitante_id) REFERENCES turmas(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_partidas_responsavel
        FOREIGN KEY (responsavel_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 7. ARBITROS VINCULADOS A CADA PARTIDA
-- Relação N:N: uma partida pode ter mais de um árbitro.
-- =========================================================
CREATE TABLE partidas_arbitros (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partida_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_partida_arbitro (partida_id, usuario_id),
    INDEX idx_partidas_arbitros_usuario (usuario_id),

    CONSTRAINT fk_partidas_arbitros_partida
        FOREIGN KEY (partida_id) REFERENCES partidas(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_partidas_arbitros_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 8. EVENTOS DA PARTIDA
-- atleta_id = autor do evento (gol, ponto, cartao etc.).
-- assistencia_id = atleta que deu a assistencia, quando houver.
-- =========================================================
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
    INDEX idx_eventos_usuario (usuario_id),

    CONSTRAINT fk_eventos_partida
        FOREIGN KEY (partida_id) REFERENCES partidas(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_eventos_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_eventos_atleta
        FOREIGN KEY (atleta_id) REFERENCES atletas(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_eventos_assistencia
        FOREIGN KEY (assistencia_id) REFERENCES atletas(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 9. ESTADO DO CRONOMETRO
-- =========================================================
CREATE TABLE estado_partida (
    partida_id INT UNSIGNED NOT NULL,
    periodo_atual TINYINT UNSIGNED NOT NULL DEFAULT 1,
    segundos_acumulados INT UNSIGNED NOT NULL DEFAULT 0,
    cronometro_iniciado_em DATETIME NULL,
    fase ENUM('jogo', 'intervalo', 'encerrado') NOT NULL DEFAULT 'jogo',
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (partida_id),

    CONSTRAINT fk_estado_partida_partida
        FOREIGN KEY (partida_id) REFERENCES partidas(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 10. REGULAMENTOS
-- Cada assunto fica em uma secao independente, com titulo,
-- conteudo, ordem e ativacao. Nao consolidar em documento unico.
-- =========================================================
CREATE TABLE regulamentos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    conteudo LONGTEXT NOT NULL,
    ordem SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    atualizado_por INT UNSIGNED NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_regulamentos_slug (slug),
    KEY idx_regulamentos_ordem (ordem),

    CONSTRAINT fk_regulamentos_admin
        FOREIGN KEY (atualizado_por) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO regulamentos (slug, titulo, conteudo, ordem) VALUES
('apresentacao', 'Apresentação', 'INTERCLASSE CULTURA DE PAZ

Evento esportivo destinado às turmas do Ensino Médio, com o objetivo de promover a competição saudável, o respeito e a integração entre os estudantes.

Cada turma poderá participar com sua equipe, mascote, grito de guerra e postura coletiva.

As regras serão adaptadas ao contexto escolar para incentivar a participação dos estudantes, independentemente de seu nível de habilidade.

A inclusão é obrigatória.', 1),

('objetivos', 'Objetivos e valores', 'O Interclasse tem como objetivos:

• Promover uma cultura de paz.
• Incentivar o protagonismo, a liderança e a responsabilidade.
• Desenvolver o espírito esportivo: saber vencer com humildade e perder com respeito.
• Incentivar a inclusão e o respeito às diferenças.
• Valorizar o respeito aos atletas, árbitros, professores e organização.
• Fortalecer a identidade e o trabalho em equipe das turmas.
• Integrar esporte, arte e cultura.
• Zelar pela limpeza e organização dos espaços utilizados.
• Valorizar atitudes positivas também diante dos adversários.

Os capitães devem auxiliar na mediação de conflitos.

A exclusão de participantes não será tolerada e poderá acarretar W.O., conforme decisão da organização.

As turmas deverão respeitar o Manual do Aluno do Centro Paula Souza.

Ao final, deverá ser realizada uma fotografia coletiva com os finalistas.', 2),

('premiacao', 'Premiação', 'A premiação será destinada somente aos campeões de cada modalidade.

• Troféu personalizado por modalidade.
• Reconhecimento de melhor jogador(a) por modalidade.
• Medalhas de ouro para todos os atletas inscritos nas equipes campeãs.
• Divulgação de fotografias nas redes sociais do evento.

A sequência da premiação será organizada em ordem crescente, deixando as equipes campeãs para o encerramento.

A cerimônia de encerramento contará com a participação dos campeões.

Não haverá premiação ou cerimônia específica para segundo e terceiro lugares.', 3),

('penalidades', 'Punições e penalidades', 'Esta seção deverá ser preenchida pela organização do Interclasse.', 4),

('futsal-masculino', 'Futsal masculino', 'Regulamento de referência: regras adaptadas da CBFS.

• Duração: 2 tempos de 8 minutos corridos.
• Intervalo: 1 minuto e 30 segundos.
• Formação: 5 jogadores em quadra e até 4 reservas.
• Substituições: ilimitadas.
• Decisão em eliminatórias: disputa de pênaltis, com 3 cobranças por equipe e, se necessário, cobranças alternadas.
• Faltas acumuladas: a partir da 5ª falta por tempo, será cobrada falta direta sem barreira.
• Cartões: amarelo como advertência e vermelho como expulsão.', 5),

('futsal-feminino', 'Futsal feminino', 'Regulamento de referência: regras adaptadas da CBFS.

• Duração: 2 tempos de 8 minutos corridos.
• Intervalo: 1 minuto e 30 segundos.
• Formação: 5 jogadoras em quadra e até 4 reservas.
• Substituições: ilimitadas.
• Decisão em eliminatórias: disputa de pênaltis, com 3 cobranças por equipe e, se necessário, cobranças alternadas.
• Faltas acumuladas: a partir da 5ª falta por tempo, será cobrada falta direta sem barreira.
• Cartões: amarelo como advertência e vermelho como expulsão.', 6),

('basquete-masculino', 'Basquetebol masculino', 'Regulamento de referência: regras adaptadas da FIBA.

• Duração: 10 minutos corridos ou até uma equipe alcançar 21 pontos.
• Pontuação: arremessos dentro do arco valem 1 ponto; fora do arco valem 2 pontos.
• Formação: 3 jogadores em quadra e até 2 reservas.
• Substituições: ilimitadas.
• Faltas: a partir da 5ª falta acumulada, o jogador será retirado da partida.
• Empate: decisão por lances livres.', 7),

('basquete-feminino', 'Basquetebol feminino', 'Regulamento de referência: regras adaptadas da FIBA.

• Duração: 10 minutos corridos ou até uma equipe alcançar 21 pontos.
• Pontuação: arremessos dentro do arco valem 1 ponto; fora do arco valem 2 pontos.
• Formação: 3 jogadoras em quadra e até 2 reservas.
• Substituições: ilimitadas.
• Faltas: a partir da 5ª falta acumulada, a jogadora será retirada da partida.
• Empate: decisão por lances livres.', 8),

('handebol-masculino', 'Handebol masculino', 'Regulamento de referência: regras adaptadas da IHF.

• Duração: 2 tempos de 6 minutos corridos.
• Intervalo: 1 minuto e 30 segundos.
• Formação: 7 jogadores em quadra e até 1 reserva.
• Substituições: ilimitadas.
• Empate: cobranças de 7 metros, com 3 cobranças por equipe e, se necessário, cobranças alternadas.
• Faltas acumuladas: a partir da 5ª por tempo, tiro livre de 7 metros.
• Cartões: amarelo como advertência e vermelho como expulsão.', 9),

('handebol-feminino', 'Handebol feminino', 'Regulamento de referência: regras adaptadas da IHF.

• Duração: 2 tempos de 6 minutos corridos.
• Intervalo: 1 minuto e 30 segundos.
• Formação: 7 jogadoras em quadra e até 1 reserva.
• Substituições: ilimitadas.
• Empate: cobranças de 7 metros, com 3 cobranças por equipe e, se necessário, cobranças alternadas.
• Faltas acumuladas: a partir da 5ª por tempo, tiro livre de 7 metros.
• Cartões: amarelo como advertência e vermelho como expulsão.', 10),

('volei-misto', 'Voleibol misto', 'Regulamento de referência: regras adaptadas da FIVB.

• Partida em melhor de 3 sets.
• Sets até 12 pontos, com diferença mínima de 2 pontos.
• Set decisivo até 10 pontos.
• Formação: 6 jogadores em quadra e até 2 reservas.
• Sistema de rotação 6x0, para que todos participem das posições.
• Substituições: ilimitadas.
• Desempate: a redação do regulamento anterior deverá ser revisada pela organização.', 11),

('queimada-mista', 'Queimada mista', 'Regulamento adaptado ao contexto escolar.

• Formação: 7 participantes por equipe em quadra, com 1 reserva.
• Duração: 7 minutos ou até a eliminação de toda a equipe adversária.
• Eliminação: ocorre quando o participante é atingido por uma bola lançada e não consegue se defender.
• Proteção: o regulamento anterior prevê proteção pelas palmas das mãos e pela cabeça, além de não considerar o impacto quando a bola toca o chão antes.
• Empate: prorrogação em que a primeira eliminação decide a partida.

A organização deverá revisar e confirmar os critérios de proteção antes da publicação definitiva.', 12),

('tenis-mesa-masculino', 'Tênis de mesa masculino', 'Regulamento de referência: regras adaptadas da ITTF.

• Partida em melhor de 3 sets.
• Sets até 11 pontos, com diferença mínima de 2 pontos.
• Saque alternado a cada 2 pontos.
• Após o empate em 10 a 10, o saque alterna a cada ponto.
• O critério de desempate adicional deverá ser confirmado pela organização.', 13),

('tenis-mesa-feminino', 'Tênis de mesa feminino', 'Regulamento de referência: regras adaptadas da ITTF.

• Partida em melhor de 3 sets.
• Sets até 11 pontos, com diferença mínima de 2 pontos.
• Saque alternado a cada 2 pontos.
• Após o empate em 10 a 10, o saque alterna a cada ponto.
• O critério de desempate adicional deverá ser confirmado pela organização.', 14),

('xadrez-misto', 'Xadrez misto', 'Regulamento de referência: regras adaptadas da CBX.

• Relógio digital ou aplicativo.
• Tempo de 10 minutos para cada jogador.
• Vitória por xeque-mate, término do tempo do adversário ou desistência.
• Empate: partida Blitz de 5 minutos.', 15),

('eafc-misto', 'EAFC FIFA 26 — E-Games misto', '• Partida única.
• Duração: 2 tempos de 3 minutos.', 16),

('brawl-stars-misto', 'Brawl Stars — E-Games misto', '• Partida em melhor de 3.
• Vence a equipe que eliminar todos os adversários, conforme o modo de jogo definido pela organização.', 17),

('free-fire-misto', 'Free Fire — E-Games misto', '• Vence a equipe que eliminar todos os adversários, conforme o modo de jogo definido pela organização.', 18),

('clash-royale-misto', 'Clash Royale — E-Games misto', '• Partida única.
• Vence a equipe que conquistar o maior número de coroas.', 19),

('pontuacao', 'Sistema de pontuação', 'Esta seção deverá ser preenchida pela organização do Interclasse.', 20),

('organizacao', 'Organização e funções', 'Coordenação geral: Professor de Educação Física.

Comissão de esportes: Atlética da Zona Leste.

As atribuições incluem supervisionar o evento, resolver conflitos, organizar as modalidades e atuar na arbitragem, conforme a modalidade e a necessidade de acompanhamento dos professores.', 21);

-- =========================================================
-- 11. HISTORICO DOS REGULAMENTOS
-- ON DELETE RESTRICT impede apagar uma secao que tenha historico.
-- Para retirar uma secao da pagina publica, use ativo = 0.
-- =========================================================
CREATE TABLE historico_regulamentos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    regulamento_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NULL,
    versao INT UNSIGNED NOT NULL,
    acao ENUM('criacao', 'edicao', 'importacao_pdf', 'restauracao') NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    conteudo LONGTEXT NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_historico_regulamento (regulamento_id),
    INDEX idx_historico_usuario (usuario_id),
    INDEX idx_historico_data (criado_em),

    CONSTRAINT fk_historico_regulamento
        FOREIGN KEY (regulamento_id) REFERENCES regulamentos(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_historico_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 12. CONFERENCIA
-- =========================================================
SHOW TABLES;

SELECT * FROM turmas ORDER BY periodo, id;
SELECT * FROM modalidades ORDER BY id;
SELECT id, nome, usuario_login, perfil, turma_id, ativo FROM usuarios;
SELECT id, titulo, slug, ordem, ativo FROM regulamentos ORDER BY ordem;
SELECT * FROM usuarios;
-- =========================================================
-- INTERCLASSE ETEC - BANCO DE DADOS COMPLETO
-- MySQL 8.0+
-- InnoDB | utf8mb4
-- CRIAÇÃO DO BANCO DO ZERO
-- =========================================================

-- ATENÇÃO: apaga completamente o banco anterior!
DROP DATABASE IF EXISTS interclasse_etec;

CREATE DATABASE interclasse_etec
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE interclasse_etec;

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

    -- CONFIGURAÇÃO DO FORMATO DA PARTIDA
    formato_partida ENUM(
        'periodos',
        'sets',
        'tempo_pontos',
        'eliminacao',
        'tempo_individual',
        'coroas'
    ) NOT NULL DEFAULT 'periodos',

    duracao_periodo_segundos SMALLINT UNSIGNED NULL,
    quantidade_periodos TINYINT UNSIGNED NULL,
    intervalo_segundos SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    quantidade_sets TINYINT UNSIGNED NULL,
    pontos_por_set SMALLINT UNSIGNED NULL,
    pontos_set_decisivo SMALLINT UNSIGNED NULL,

    limite_pontos SMALLINT UNSIGNED NULL,
    duracao_maxima_segundos SMALLINT UNSIGNED NULL,

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

-- Quantidade mínima e máxima de atletas
UPDATE modalidades
SET
    minimo_atletas = CASE id
        WHEN 1 THEN 5
        WHEN 2 THEN 5
        WHEN 3 THEN 3
        WHEN 4 THEN 3
        WHEN 5 THEN 6
        WHEN 6 THEN 6
        WHEN 7 THEN 1
        WHEN 8 THEN 1
        WHEN 9 THEN 6
        WHEN 10 THEN 7
        WHEN 11 THEN 1
        WHEN 12 THEN 1
        WHEN 13 THEN 1
        WHEN 14 THEN 1
        WHEN 15 THEN 1
    END,

    maximo_atletas = CASE id
        WHEN 1 THEN 9
        WHEN 2 THEN 9
        WHEN 3 THEN 5
        WHEN 4 THEN 5
        WHEN 5 THEN 7
        WHEN 6 THEN 7
        WHEN 7 THEN 1
        WHEN 8 THEN 1
        WHEN 9 THEN 8
        WHEN 10 THEN 8
        WHEN 11 THEN 1
        WHEN 12 THEN 2
        WHEN 13 THEN 2
        WHEN 14 THEN 2
        WHEN 15 THEN 2
    END
WHERE id BETWEEN 1 AND 15;

-- Configurações de cada modalidade

-- Futsal
UPDATE modalidades
SET formato_partida = 'periodos',
    duracao_periodo_segundos = 480,
    quantidade_periodos = 2,
    intervalo_segundos = 90
WHERE id IN (1, 2);

-- Basquete
UPDATE modalidades
SET formato_partida = 'tempo_pontos',
    duracao_periodo_segundos = NULL,
    quantidade_periodos = NULL,
    intervalo_segundos = 0,
    limite_pontos = 21,
    duracao_maxima_segundos = 600
WHERE id IN (3, 4);

-- Handebol
UPDATE modalidades
SET formato_partida = 'periodos',
    duracao_periodo_segundos = 360,
    quantidade_periodos = 2,
    intervalo_segundos = 90
WHERE id IN (5, 6);

-- Tênis de mesa
UPDATE modalidades
SET formato_partida = 'sets',
    quantidade_sets = 3,
    pontos_por_set = 11,
    pontos_set_decisivo = 11
WHERE id IN (7, 8);

-- Vôlei
UPDATE modalidades
SET formato_partida = 'sets',
    quantidade_sets = 3,
    pontos_por_set = 12,
    pontos_set_decisivo = 10
WHERE id = 9;

-- Queimada
UPDATE modalidades
SET formato_partida = 'eliminacao',
    duracao_maxima_segundos = 420
WHERE id = 10;

-- Xadrez
UPDATE modalidades
SET formato_partida = 'tempo_individual',
    duracao_maxima_segundos = 600
WHERE id = 11;

-- EAFC
UPDATE modalidades
SET formato_partida = 'periodos',
    duracao_periodo_segundos = 240,
    quantidade_periodos = 2,
    intervalo_segundos = 0
WHERE id = 12;

-- Brawl Stars e Free Fire
UPDATE modalidades
SET formato_partida = 'eliminacao',
    duracao_maxima_segundos = NULL
WHERE id IN (13, 14);

-- Clash Royale
UPDATE modalidades
SET formato_partida = 'coroas'
WHERE id = 15;

-- =========================================================
-- 3. USUARIOS
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

-- As contas serão cadastradas pelo sistema.
-- Não existem usuários criados automaticamente.

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
    observacao_aprovacao TEXT NULL,

    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

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
-- 5. INSCRICOES
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
-- =========================================================

CREATE TABLE partidas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    modalidade_id INT UNSIGNED NOT NULL,
    turma_casa_id INT UNSIGNED NOT NULL,
    turma_visitante_id INT UNSIGNED NOT NULL,

    inicio_previsto DATETIME NULL,
    periodo_escolar ENUM('manha', 'tarde') NULL,
    local_partida VARCHAR(120) NULL,

    -- Fase do campeonato (usada no chaveamento público). NULL = não definida.
    fase ENUM('grupos', 'oitavas', 'quartas', 'semifinal', 'final', 'terceiro_lugar') NULL,

    responsavel_id INT UNSIGNED NULL,

    -- Campos antigos mantidos para compatibilidade
    -- com o controle de partidas existente.
    duracao_periodo_min SMALLINT UNSIGNED NOT NULL DEFAULT 10,

    -- Para modalidades sem períodos, o sistema utiliza 1.
    quantidade_periodos TINYINT UNSIGNED NOT NULL DEFAULT 1,

    intervalo_min SMALLINT UNSIGNED NOT NULL DEFAULT 5,

    -- Configuração do formato salva na partida.
    formato_partida ENUM(
        'periodos',
        'sets',
        'tempo_pontos',
        'eliminacao',
        'tempo_individual',
        'coroas'
    ) NOT NULL DEFAULT 'periodos',

    duracao_periodo_segundos SMALLINT UNSIGNED NULL,
    intervalo_segundos SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    quantidade_sets TINYINT UNSIGNED NULL,
    pontos_por_set SMALLINT UNSIGNED NULL,
    pontos_set_decisivo SMALLINT UNSIGNED NULL,

    limite_pontos SMALLINT UNSIGNED NULL,
    duracao_maxima_segundos SMALLINT UNSIGNED NULL,

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

    tipo_resultado ENUM('normal', 'wo')
        NOT NULL DEFAULT 'normal',

    turma_vencedora_id INT UNSIGNED NULL,

    youtube_url VARCHAR(500) NULL,

    youtube_status ENUM(
        'sem_transmissao',
        'agendada',
        'ao_vivo',
        'encerrada'
    ) NOT NULL DEFAULT 'sem_transmissao',

    observacoes TEXT NULL,

    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_partidas_status (status),
    INDEX idx_partidas_inicio (inicio_previsto),
    INDEX idx_partidas_fase (modalidade_id, fase),
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
        ON UPDATE CASCADE ON DELETE SET NULL,

    CONSTRAINT fk_partidas_turma_vencedora
        FOREIGN KEY (turma_vencedora_id) REFERENCES turmas(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 7. ARBITROS DAS PARTIDAS
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
-- 8. ATLETAS VINCULADOS À PARTIDA
-- =========================================================

CREATE TABLE partidas_atletas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    partida_id INT UNSIGNED NOT NULL,
    atleta_id INT UNSIGNED NOT NULL,

    funcao_inicial ENUM('titular', 'reserva')
        NOT NULL DEFAULT 'reserva',

    situacao_atual ENUM('em_campo', 'reserva', 'substituido')
        NOT NULL DEFAULT 'reserva',

    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_partida_atleta (partida_id, atleta_id),
    INDEX idx_partidas_atletas_atleta (atleta_id),

    CONSTRAINT fk_partidas_atletas_partida
        FOREIGN KEY (partida_id) REFERENCES partidas(id)
        ON UPDATE CASCADE ON DELETE CASCADE,

    CONSTRAINT fk_partidas_atletas_atleta
        FOREIGN KEY (atleta_id) REFERENCES atletas(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 9. EVENTOS DA PARTIDA
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
-- 10. EVENTOS DISPONIVEIS POR MODALIDADE
-- =========================================================

CREATE TABLE eventos_modalidade (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    modalidade_id INT UNSIGNED NOT NULL,

    tipo_evento VARCHAR(40) NOT NULL,
    nome VARCHAR(100) NOT NULL,

    pontos_placar SMALLINT NOT NULL DEFAULT 0,

    exige_atleta BOOLEAN NOT NULL DEFAULT TRUE,
    permite_assistencia BOOLEAN NOT NULL DEFAULT FALSE,

    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    ordem SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    UNIQUE KEY uq_evento_modalidade (modalidade_id, tipo_evento),

    CONSTRAINT fk_eventos_modalidade
        FOREIGN KEY (modalidade_id) REFERENCES modalidades(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO eventos_modalidade
(modalidade_id, tipo_evento, nome, pontos_placar,
 exige_atleta, permite_assistencia, ativo, ordem)
VALUES

-- FUTSAL MASCULINO
(1, 'gol', 'Gol', 1, TRUE, TRUE, TRUE, 1),
(1, 'cartao_amarelo', 'Cartão amarelo', 0, TRUE, FALSE, TRUE, 2),
(1, 'cartao_vermelho', 'Cartão vermelho', 0, TRUE, FALSE, TRUE, 3),

-- FUTSAL FEMININO
(2, 'gol', 'Gol', 1, TRUE, TRUE, TRUE, 1),
(2, 'cartao_amarelo', 'Cartão amarelo', 0, TRUE, FALSE, TRUE, 2),
(2, 'cartao_vermelho', 'Cartão vermelho', 0, TRUE, FALSE, TRUE, 3),

-- BASQUETE MASCULINO
(3, 'cesta_dentro_arco', 'Cesta dentro do arco', 1, TRUE, FALSE, TRUE, 1),
(3, 'cesta_fora_arco', 'Cesta fora do arco', 2, TRUE, FALSE, TRUE, 2),
(3, 'lance_livre', 'Lance livre convertido', 1, TRUE, FALSE, TRUE, 3),
(3, 'falta', 'Falta', 0, TRUE, FALSE, TRUE, 4),

-- BASQUETE FEMININO
(4, 'cesta_dentro_arco', 'Cesta dentro do arco', 1, TRUE, FALSE, TRUE, 1),
(4, 'cesta_fora_arco', 'Cesta fora do arco', 2, TRUE, FALSE, TRUE, 2),
(4, 'lance_livre', 'Lance livre convertido', 1, TRUE, FALSE, TRUE, 3),
(4, 'falta', 'Falta', 0, TRUE, FALSE, TRUE, 4),

-- HANDEBOL MASCULINO
(5, 'gol', 'Gol', 1, TRUE, TRUE, TRUE, 1),
(5, 'cartao_amarelo', 'Cartão amarelo', 0, TRUE, FALSE, TRUE, 2),
(5, 'cartao_vermelho', 'Cartão vermelho', 0, TRUE, FALSE, TRUE, 3),
(5, 'exclusao_2min', 'Exclusão de 2 minutos', 0, TRUE, FALSE, TRUE, 4),

-- HANDEBOL FEMININO
(6, 'gol', 'Gol', 1, TRUE, TRUE, TRUE, 1),
(6, 'cartao_amarelo', 'Cartão amarelo', 0, TRUE, FALSE, TRUE, 2),
(6, 'cartao_vermelho', 'Cartão vermelho', 0, TRUE, FALSE, TRUE, 3),
(6, 'exclusao_2min', 'Exclusão de 2 minutos', 0, TRUE, FALSE, TRUE, 4);

-- =========================================================
-- 11. ESTADO DO CRONOMETRO
-- =========================================================

CREATE TABLE estado_partida (
    partida_id INT UNSIGNED NOT NULL,

    periodo_atual TINYINT UNSIGNED NOT NULL DEFAULT 1,
    segundos_acumulados INT UNSIGNED NOT NULL DEFAULT 0,

    cronometro_iniciado_em DATETIME NULL,

    fase ENUM('jogo', 'intervalo', 'encerrado')
        NOT NULL DEFAULT 'jogo',

    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (partida_id),

    CONSTRAINT fk_estado_partida_partida
        FOREIGN KEY (partida_id) REFERENCES partidas(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 12. REGULAMENTOS
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

('apresentacao', 'Apresentação', 'INTERCLASSE — 2º SEMESTRE DE 2026

Ensino Médio
Duração: 6 dias
Locais: Quadra interna e externa

O Interclasse é um evento esportivo que une as turmas do Ensino Médio em uma semana e meia de competição saudável, respeito mútuo e celebração da diversidade.

Cada turma é uma equipe completa, com postura coletiva dentro e fora da quadra. Todas as modalidades possuem regras adaptadas para garantir que todos participem, independentemente de suas habilidades esportivas.

A inclusão não é opcional, mas uma regra do evento.

REGRAS GERAIS E RESTRIÇÕES

• É proibido emprestar alunos para completar equipes de outras turmas.
• A modalidade Badminton foi removida.
• As músicas voltarão a tocar nos intervalos dos jogos.
• Os banheiros utilizados serão os localizados em frente à quadra.
• Alunos com advertência ou suspensão no prontuário durante o ano letivo não poderão participar do evento.
• Alunos que estejam em Conselho de Escola também não poderão jogar.
• A ausência de jogadores fora da quadra poderá resultar em W.O. após ultrapassada a tolerância de 5 minutos.
• O técnico deverá ter menos de 18 anos e apresentar RG na bancada para poder jogar.
• Gestos ofensivos, como dedo do meio, e ofensas poderão resultar em punições de W.O.', 1),

('objetivos', 'Objetivos e valores', 'O Interclasse tem como objetivos:

• Promover a competição como valor central do campeonato.
• Desenvolver protagonismo, liderança e responsabilidade entre os estudantes.
• Jogar com esportividade: ganhar com humildade e perder com grandeza.
• Garantir a participação dos alunos, respeitando habilidades e limitações.
• Tratar todos os participantes, árbitros e professores com respeito e dignidade.
• Fortalecer a identidade e o espírito de equipe de cada turma.
• Integrar esportes, arte e expressão cultural em um mesmo evento.
• Incluir os membros da equipe nas atividades, sem exclusão por habilidade.
• Zelar pelos espaços coletivos, deixando-os sempre limpos e organizados.
• Reconhecer e valorizar as boas atitudes dos adversários.
• Mediar conflitos com responsabilidade de cada capitão de turma.

A exclusão de participantes não será tolerada e poderá resultar em punição para a equipe.

Ao final do evento, deverá ser realizada uma fotografia coletiva com todos os finalistas, independentemente do resultado.', 2),

('premiacao', 'Premiação', 'Será premiada apenas a equipe campeã de cada modalidade, recebendo:

• Troféu personalizado por modalidade.
• Medalhas de ouro para todos os jogadores inscritos na equipe campeã.
• Divulgação de fotografias nas redes sociais do Grêmio Estudantil Prisma ZL.', 3),

('penalidades', 'Punições e penalidades', 'As seguintes infrações estão sujeitas às respectivas punições:

USO DE JOGADOR IRREGULAR
Punição: eliminação da equipe da modalidade.

AGRESSÃO FÍSICA OU VERBAL A JOGADOR, ÁRBITRO OU COMISSÃO
Punição: expulsão imediata e suspensão do evento.

CARTÃO VERMELHO DIRETO OU REINCIDÊNCIA DE ADVERTÊNCIAS
Punição: suspensão do próximo jogo.

W.O. SEM JUSTIFICATIVA
Punição: derrota automática.

INTERFERÊNCIA EXTERNA (TORCIDA, EX-ALUNO ETC.)
Punição: advertência e possível desclassificação da equipe da modalidade.

Outras situações poderão ser analisadas pela organização do Interclasse.', 4),

('futsal-masculino', 'Futsal masculino', 'Regras da CBFS adaptadas.

• Duração: 2 tempos de 8 minutos corridos.
• Intervalo: 1 minuto e 30 segundos.
• Formação: 5 jogadores em quadra e 4 reservas.
• Substituições: livres e ilimitadas.
• Eliminatórias: disputa de pênaltis, com 3 cobranças por equipe e cobranças alternadas, se necessário.
• Faltas acumulativas: a partir da 5ª falta por tempo, será cobrado tiro livre sem barreira.
• Cartão amarelo: advertência.
• Cartão vermelho: expulsão do jogo.', 5),

('futsal-feminino', 'Futsal feminino', 'Regras da CBFS adaptadas.

• Duração: 2 tempos de 8 minutos corridos.
• Intervalo: 1 minuto e 30 segundos.
• Formação: 5 jogadoras em quadra e 4 reservas.
• Substituições: livres e ilimitadas.
• Eliminatórias: disputa de pênaltis, com 3 cobranças por equipe e cobranças alternadas, se necessário.
• Faltas acumulativas: a partir da 5ª falta por tempo, será cobrado tiro livre sem barreira.
• Cartão amarelo: advertência.
• Cartão vermelho: expulsão do jogo.', 6),

('handebol-masculino', 'Handebol masculino', 'Regras da IHF adaptadas.

• Duração: 2 tempos de 6 minutos corridos.
• Intervalo: 1 minuto e 30 segundos.
• Formação: 6 jogadores em quadra e 1 reserva.
• Substituições: livres e ilimitadas.
• Empate: disputa de 7 metros, com 3 cobranças por equipe e cobranças alternadas, se necessário.
• Faltas acumulativas: a partir da 5ª falta por tempo, tiro livre de 7 metros.
• Cartão amarelo: advertência.
• Cartão vermelho: expulsão do jogo.', 7),

('handebol-feminino', 'Handebol feminino', 'Regras da IHF adaptadas.

• Duração: 2 tempos de 6 minutos corridos.
• Intervalo: 1 minuto e 30 segundos.
• Formação: 6 jogadoras em quadra e 1 reserva.
• Substituições: livres e ilimitadas.
• Empate: disputa de 7 metros, com 3 cobranças por equipe e cobranças alternadas, se necessário.
• Faltas acumulativas: a partir da 5ª falta por tempo, tiro livre de 7 metros.
• Cartão amarelo: advertência.
• Cartão vermelho: expulsão do jogo.', 8),

('volei-misto', 'Voleibol misto', 'Regras da FIVB adaptadas.

• Formato: melhor de 3 sets, com sets de 12 pontos e diferença mínima de 2 pontos. O set decisivo será de 10 pontos.
• Formação: 6 jogadores em quadra e 2 reservas.
• Posicionamento e rotação: sistema 6x0, no qual todos os participantes rodam e jogam em todas as posições, garantindo oportunidades iguais de aprendizagem, de acordo com os conteúdos trabalhados na escola.
• Substituições: livres e ilimitadas.
• Eliminatórias: prorrogação de Zerou vai à 2, com diferença de 2 pontos.', 9),

('basquete-masculino', 'Basquetebol masculino', 'Regras da FIBA adaptadas.

• Duração: 10 minutos corridos ou até uma equipe atingir 21 pontos.
• Pontuação: cestas dentro do arco valem 1 ponto; fora do arco valem 2 pontos.
• Formação: 3 jogadores em quadra e 2 reservas.
• Substituições: livres e ilimitadas.
• Faltas acumulativas: a partir da 5ª falta, o jogador será ejetado do jogo.
• Empate: disputa de lances livres.', 10),

('basquete-feminino', 'Basquetebol feminino', 'Regras da FIBA adaptadas.

• Duração: 10 minutos corridos ou até uma equipe atingir 21 pontos.
• Pontuação: cestas dentro do arco valem 1 ponto; fora do arco valem 2 pontos.
• Formação: 3 jogadoras em quadra e 2 reservas.
• Substituições: livres e ilimitadas.
• Faltas acumulativas: a partir da 5ª falta, a jogadora será ejetada do jogo.
• Empate: disputa de lances livres.', 11),

('queimada-mista', 'Queimada mista', 'Regras adaptadas ao ambiente escolar.

• Formação: 7 participantes por equipe em quadra e 1 reserva.
• Duração: 7 minutos ou até a eliminação total de uma equipe.
• Proteção: apenas as palmas das mãos e a cabeça evitam uma eliminação. Também não ocorre eliminação quando a bola toca o chão antes de atingir o jogador.
• Eliminação: ocorre quando o jogador é atingido por uma bola lançada sem conseguir se defender.
• Empate: prorrogação em que a primeira eliminação decide o vencedor.', 12),

('tenis-mesa-masculino', 'Tênis de mesa masculino', 'Regras da ITTF adaptadas.

• Partidas: melhor de 3 sets, com até 11 pontos e diferença mínima de 2 pontos.
• Saques: alternância a cada 2 pontos.
• Após o empate em 10 a 10, o saque alterna a cada ponto.
• Empate: set extra único de 11 pontos.', 13),

('tenis-mesa-feminino', 'Tênis de mesa feminino', 'Regras da ITTF adaptadas.

• Partidas: melhor de 3 sets, com até 11 pontos e diferença mínima de 2 pontos.
• Saques: alternância a cada 2 pontos.
• Após o empate em 10 a 10, o saque alterna a cada ponto.
• Empate: set extra único de 11 pontos.', 14),

('xadrez-misto', 'Xadrez misto', 'Regras da CBX adaptadas.

• Tempo: relógio digital ou aplicativo, com 10 minutos por jogador.
• Vitória: ocorre por xeque-mate, tempo excedido ou desistência.
• Empate: partida Blitz de 5 minutos.', 15),

('eafc-misto', 'EAFC FIFA 26 — E-Games misto', '• Formato: partidas 1x1 ou 2x2.
• Partida única.
• Duração: 2 tempos de 4 minutos cada.', 16),

('brawl-stars-misto', 'Brawl Stars — E-Games misto', '• Formato: partidas 1x1 ou 2x2.
• Vence a equipe que eliminar todos os inimigos.', 17),

('free-fire-misto', 'Free Fire — E-Games misto', '• Formato: partidas 1x1 ou 2x2.
• Vence a equipe que eliminar todos os inimigos.', 18),

('clash-royale-misto', 'Clash Royale — E-Games misto', '• Formato: partidas 1x1 ou 2x2.
• Vence a equipe que conquistar mais coroas do adversário.', 19),

('pontuacao', 'Sistema de pontuação', 'A pontuação será atribuída conforme o resultado de cada partida:

• Vitória: 3 pontos.
• Empate: 1 ponto.
• Derrota: 0 pontos.
• W.O.: derrota automática.', 20),

('organizacao', 'Organização e funções', 'COORDENADOR GERAL
Responsabilidade: supervisionar o evento e resolver os conflitos maiores.

PROFESSOR DE EDUCAÇÃO FÍSICA
Responsabilidade: apitar o jogo, aplicar as regras e apitar as infrações.

COMISSÃO DE ESPORTES
Responsabilidade: organizar e apitar os esportes sem contato.

ATLÉTICA DA ZONA LESTE
Responsabilidade: apitar com o professor nos esportes de contato.

GRÊMIO PRISMA ZL
Responsabilidade: marcar resultados, registrar punições e controlar o som.

TORCIDA DAS TURMAS
Responsabilidade: permanecer na quadra e apoiar os times de sua sala.

JOGADORES DAS EQUIPES
Responsabilidade: respeitar o Manual do Aluno do CPS e o Regulamento do Interclasse.', 21);

-- =========================================================
-- 13. HISTORICO DOS REGULAMENTOS
-- =========================================================

CREATE TABLE historico_regulamentos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    regulamento_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NULL,

    versao INT UNSIGNED NOT NULL,

    acao ENUM(
        'criacao',
        'edicao',
        'importacao_pdf',
        'restauracao'
    ) NOT NULL,

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
-- 14. REGRAS DE PONTUACAO DO MVP
-- =========================================================

CREATE TABLE regras_pontuacao_mvp (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    modalidade_id INT UNSIGNED NOT NULL,
    tipo_evento VARCHAR(40) NOT NULL,

    pontos SMALLINT NOT NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,

    UNIQUE KEY uq_regra_mvp (modalidade_id, tipo_evento),

    INDEX idx_regras_mvp_modalidade (modalidade_id),

    CONSTRAINT fk_regra_mvp_modalidade
        FOREIGN KEY (modalidade_id) REFERENCES modalidades(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO regras_pontuacao_mvp
(modalidade_id, tipo_evento, pontos, ativo)
VALUES

-- FUTSAL MASCULINO
(1, 'gol', 2, TRUE),
(1, 'assistencia', 1, TRUE),
(1, 'cartao_amarelo', -2, TRUE),
(1, 'cartao_vermelho', -3, TRUE),

-- FUTSAL FEMININO
(2, 'gol', 2, TRUE),
(2, 'assistencia', 1, TRUE),
(2, 'cartao_amarelo', -2, TRUE),
(2, 'cartao_vermelho', -3, TRUE),

-- BASQUETE MASCULINO
(3, 'cesta_dentro_arco', 2, TRUE),
(3, 'cesta_fora_arco', 2, TRUE),
(3, 'lance_livre', 2, TRUE),
(3, 'assistencia', 1, TRUE),
(3, 'cartao_amarelo', -2, TRUE),
(3, 'cartao_vermelho', -3, TRUE),

-- BASQUETE FEMININO
(4, 'cesta_dentro_arco', 2, TRUE),
(4, 'cesta_fora_arco', 2, TRUE),
(4, 'lance_livre', 2, TRUE),
(4, 'assistencia', 1, TRUE),
(4, 'cartao_amarelo', -2, TRUE),
(4, 'cartao_vermelho', -3, TRUE),

-- HANDEBOL MASCULINO
(5, 'gol', 2, TRUE),
(5, 'assistencia', 1, TRUE),
(5, 'cartao_amarelo', -2, TRUE),
(5, 'cartao_vermelho', -3, TRUE),

-- HANDEBOL FEMININO
(6, 'gol', 2, TRUE),
(6, 'assistencia', 1, TRUE),
(6, 'cartao_amarelo', -2, TRUE),
(6, 'cartao_vermelho', -3, TRUE),

-- TÊNIS DE MESA MASCULINO
(7, 'ponto', 2, TRUE),

-- TÊNIS DE MESA FEMININO
(8, 'ponto', 2, TRUE),

-- VOLEIBOL MISTO
(9, 'ponto', 2, TRUE),
(9, 'ace', 2, TRUE),
(9, 'bloqueio', 2, TRUE),
(9, 'assistencia', 1, TRUE),

-- QUEIMADA MISTA
(10, 'eliminacao', 2, TRUE),
(10, 'defesa', 2, TRUE),

-- XADREZ MISTO
(11, 'vitoria', 3, TRUE),

-- EAFC FIFA 26
(12, 'gol', 2, TRUE),
(12, 'assistencia', 1, TRUE),

-- BRAWL STARS
(13, 'eliminacao', 2, TRUE),

-- FREE FIRE
(14, 'eliminacao', 2, TRUE),

-- CLASH ROYALE
(15, 'coroa', 2, TRUE);

-- =========================================================
-- 15. MVP DECISOES
-- =========================================================

CREATE TABLE mvp_decisoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    modalidade_id INT UNSIGNED NOT NULL,
    atleta_id INT UNSIGNED NOT NULL,
    decidido_por INT UNSIGNED NOT NULL,

    justificativa TEXT NULL,

    decidido_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_mvp_modalidade (modalidade_id),

    INDEX idx_mvp_decisoes_atleta (atleta_id),

    CONSTRAINT fk_mvp_decisao_modalidade
        FOREIGN KEY (modalidade_id) REFERENCES modalidades(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_mvp_decisao_atleta
        FOREIGN KEY (atleta_id) REFERENCES atletas(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,

    CONSTRAINT fk_mvp_decisao_usuario
        FOREIGN KEY (decidido_por) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 16. NOTIFICACOES
-- =========================================================

CREATE TABLE notificacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    usuario_id INT UNSIGNED NOT NULL,

    tipo VARCHAR(50) NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    mensagem TEXT NOT NULL,

    referencia_tipo VARCHAR(50) NULL,
    referencia_id INT UNSIGNED NULL,

    link VARCHAR(255) NULL,

    lida BOOLEAN NOT NULL DEFAULT FALSE,
    lida_em DATETIME NULL,

    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_notificacoes_usuario (
        usuario_id,
        lida,
        criado_em
    ),

    INDEX idx_notificacoes_referencia (
        referencia_tipo,
        referencia_id
    ),

    CONSTRAINT fk_notificacoes_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- 17. CONFERENCIA FINAL
-- =========================================================

SHOW TABLES;

SELECT * FROM turmas ORDER BY periodo, id;

SELECT
    id,
    nome,
    categoria,
    minimo_atletas,
    maximo_atletas,
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
ORDER BY id;

SELECT
    id,
    titulo,
    slug,
    ordem,
    ativo
FROM regulamentos
ORDER BY ordem;

SELECT
    id,
    nome,
    usuario_login,
    perfil,
    turma_id,
    ativo
FROM usuarios;
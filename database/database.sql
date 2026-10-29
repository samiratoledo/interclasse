CREATE DATABASE IF NOT EXISTS interclasse_etec
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE interclasse_etec;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS
    inscricao_atletas,
    eventos_partida,
    partidas_arbitros,
    auditoria,
    partidas,
    inscricoes,
    atletas,
    usuarios,
    modalidades,
    turmas;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. Turmas
CREATE TABLE turmas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(30) NOT NULL UNIQUE,
    periodo ENUM('manha', 'tarde') NOT NULL,
    imagem VARCHAR(255) NULL,
    ativa BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Modalidades
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
) ENGINE=InnoDB;

-- 3. Usuários (sem contas cadastradas)
CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,

    perfil ENUM(
        'admin',
        'arbitro',
        'representante'
    ) NOT NULL,

    turma_id INT UNSIGNED NULL,
    ativo BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_usuarios_turma_representante (turma_id),

    CONSTRAINT fk_usuarios_turma
        FOREIGN KEY (turma_id)
        REFERENCES turmas(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 4. Cadastro das turmas
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

-- 5. Cadastro das modalidades
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

-- 6. Conferência
SHOW TABLES;

SELECT * FROM usuarios;
SELECT * FROM turmas ORDER BY periodo, id;
SELECT * FROM modalidades ORDER BY id;

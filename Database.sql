CREATE DATABASE pre_inscricao_impen
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE pre_inscricao_impen;

CREATE TABLE inscricoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    apelido VARCHAR(100) NOT NULL,
    outros_nomes VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    contacto VARCHAR(30) NOT NULL,
    telefone_opcional VARCHAR(30) NOT NULL DEFAULT '',
    curso VARCHAR(100) NOT NULL,
    turno VARCHAR(30) NOT NULL,
    classe_anterior VARCHAR(150) NOT NULL,
    escola_anterior VARCHAR(200) NULL,
    nivel_anterior VARCHAR(50) NOT NULL,
    ano_conclusao SMALLINT UNSIGNED NULL,
    genero VARCHAR(20) NOT NULL,
    estado_civil VARCHAR(30) NULL,
    data_nascimento DATE NOT NULL,
    tipo_documento VARCHAR(50) NOT NULL,
    numero_documento VARCHAR(100) NOT NULL,
    validade_documento DATE NOT NULL,
    local_emissao VARCHAR(150) NOT NULL,
    provincia VARCHAR(80) NOT NULL,
    cidade_distrito VARCHAR(120) NOT NULL,
    morada TEXT NULL,
    estado ENUM('Pendente', 'Aprovada', 'Rejeitada', 'Concluída') NOT NULL DEFAULT 'Pendente',
    data_inscricao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS senha (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    senha VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO senha (senha)
SELECT '$2y$10$P2tFkw030dEX9EZAEw.nbufPIJDLoM7Yxjx7cOJntM1CthX5p1OLy'
WHERE NOT EXISTS (SELECT 1 FROM senha);
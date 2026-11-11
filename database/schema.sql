-- Atlas Turismo. Selecione um banco vazio MySQL 8+ antes de importar.
SET NAMES utf8mb4;

CREATE TABLE usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    perfil ENUM('administrador','funcionario') NOT NULL,
    ativo TINYINT UNSIGNED NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_email (email),
    KEY ix_usuarios_perfil_ativo (perfil, ativo, id),
    CONSTRAINT ck_usuarios_ativo CHECK (ativo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE clientes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(140) NOT NULL,
    email VARCHAR(190) NULL,
    telefone VARCHAR(30) NULL,
    documento VARCHAR(40) NULL,
    observacoes VARCHAR(1000) NULL,
    ativo TINYINT UNSIGNED NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_clientes_documento (documento),
    KEY ix_clientes_nome (nome, id),
    CONSTRAINT ck_clientes_ativo CHECK (ativo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE destinos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cidade VARCHAR(100) NOT NULL,
    estado VARCHAR(100) NULL,
    pais VARCHAR(100) NOT NULL,
    descricao VARCHAR(1000) NULL,
    ativo TINYINT UNSIGNED NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_destinos_cidade (cidade, pais, id),
    CONSTRAINT ck_destinos_ativo CHECK (ativo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE pacotes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    destino_id BIGINT UNSIGNED NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descricao VARCHAR(1500) NULL,
    duracao_dias SMALLINT UNSIGNED NOT NULL,
    preco_base DECIMAL(12,2) NOT NULL,
    ativo TINYINT UNSIGNED NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_pacotes_titulo (titulo, id),
    CONSTRAINT fk_pacotes_destino FOREIGN KEY (destino_id) REFERENCES destinos(id),
    CONSTRAINT ck_pacotes_duracao CHECK (duracao_dias BETWEEN 1 AND 365),
    CONSTRAINT ck_pacotes_preco CHECK (preco_base > 0),
    CONSTRAINT ck_pacotes_ativo CHECK (ativo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE viagens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pacote_id BIGINT UNSIGNED NOT NULL,
    partida DATE NOT NULL,
    retorno DATE NOT NULL,
    capacidade INT UNSIGNED NOT NULL,
    preco DECIMAL(12,2) NOT NULL,
    status ENUM('planejada','aberta','em_andamento','concluida','cancelada') NOT NULL DEFAULT 'planejada',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_viagens_partida (partida, id),
    KEY ix_viagens_status_partida (status, partida, id),
    CONSTRAINT fk_viagens_pacote FOREIGN KEY (pacote_id) REFERENCES pacotes(id),
    CONSTRAINT ck_viagens_datas CHECK (retorno >= partida),
    CONSTRAINT ck_viagens_capacidade CHECK (capacidade > 0),
    CONSTRAINT ck_viagens_preco CHECK (preco > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE reservas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cliente_id BIGINT UNSIGNED NOT NULL,
    viagem_id BIGINT UNSIGNED NOT NULL,
    passageiros INT UNSIGNED NOT NULL,
    preco_unitario DECIMAL(12,2) NOT NULL,
    valor_total DECIMAL(12,2) NOT NULL,
    status ENUM('confirmada','cancelada') NOT NULL DEFAULT 'confirmada',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_reservas_viagem_status (viagem_id, status),
    KEY ix_reservas_cliente_data (cliente_id, criado_em),
    KEY ix_reservas_data_status (criado_em, status),
    CONSTRAINT fk_reservas_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    CONSTRAINT fk_reservas_viagem FOREIGN KEY (viagem_id) REFERENCES viagens(id),
    CONSTRAINT ck_reservas_passageiros CHECK (passageiros > 0),
    CONSTRAINT ck_reservas_total CHECK (preco_unitario > 0 AND valor_total = preco_unitario * passageiros)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE pagamentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reserva_id BIGINT UNSIGNED NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    metodo ENUM('pix','cartao','dinheiro','transferencia') NOT NULL,
    data_pagamento DATE NOT NULL,
    status ENUM('recebido') NOT NULL DEFAULT 'recebido',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_pagamentos_reserva_status (reserva_id, status),
    KEY ix_pagamentos_data (data_pagamento, id),
    CONSTRAINT fk_pagamentos_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id),
    CONSTRAINT ck_pagamentos_valor CHECK (valor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE auditoria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NULL,
    operacao VARCHAR(60) NOT NULL,
    entidade VARCHAR(60) NOT NULL,
    registro_id BIGINT UNSIGNED NOT NULL,
    detalhes VARCHAR(1000) NOT NULL DEFAULT '',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_auditoria_data (criado_em, id),
    KEY ix_auditoria_entidade (entidade, registro_id),
    CONSTRAINT fk_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

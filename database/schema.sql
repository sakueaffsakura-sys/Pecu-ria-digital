-- Pecuária Digital — esquema do banco (MySQL 8 / MariaDB 10.5+)
-- Aplicar:  mysql -u root pecuaria < database/schema.sql
-- Todas as tabelas de dados têm propriedade_id: cada pessoa só vê as propriedades de que é membro
-- (a regra é aplicada pelo PHP em todas as consultas).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  senha_hash VARCHAR(255) NOT NULL,
  admin TINYINT(1) NOT NULL DEFAULT 0,
  bloqueado TINYINT(1) NOT NULL DEFAULT 0,
  propriedade_ativa_id INT UNSIGNED NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_login DATETIME NULL,
  UNIQUE KEY usuarios_email_uk (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tentativas_login (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  chave VARCHAR(190) NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY tentativas_chave_idx (chave, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS propriedades (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  cidade VARCHAR(120) NULL,
  estado CHAR(2) NULL,
  area_ha DECIMAL(12,2) NULL,
  periodo_gestacao_dias INT NOT NULL DEFAULT 283,
  proprietario_id INT UNSIGNED NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY propriedades_dono_idx (proprietario_id),
  CONSTRAINT propriedades_dono_fk FOREIGN KEY (proprietario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- papel: dono (tudo), editor (lança e altera), leitor (só vê)
CREATE TABLE IF NOT EXISTS propriedade_membros (
  propriedade_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  papel VARCHAR(10) NOT NULL DEFAULT 'editor',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (propriedade_id, usuario_id),
  KEY membros_usuario_idx (usuario_id),
  CONSTRAINT membros_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  CONSTRAINT membros_usuario_fk FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lotes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  propriedade_id INT UNSIGNED NOT NULL,
  nome VARCHAR(80) NOT NULL,
  descricao VARCHAR(255) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY lotes_nome_uk (propriedade_id, nome),
  CONSTRAINT lotes_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS animais (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  propriedade_id INT UNSIGNED NOT NULL,
  brinco VARCHAR(40) NOT NULL,
  nome VARCHAR(80) NULL,
  sexo CHAR(1) NOT NULL,
  raca VARCHAR(60) NULL,
  categoria VARCHAR(30) NULL,
  data_nascimento DATE NULL,
  lote_id INT UNSIGNED NULL,
  mae_id INT UNSIGNED NULL,
  pai VARCHAR(80) NULL,
  origem VARCHAR(20) NOT NULL DEFAULT 'nascido',
  situacao VARCHAR(20) NOT NULL DEFAULT 'ativo',
  observacoes TEXT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY animais_brinco_uk (propriedade_id, brinco),
  KEY animais_situacao_idx (propriedade_id, situacao),
  KEY animais_lote_idx (lote_id),
  KEY animais_mae_idx (mae_id),
  CONSTRAINT animais_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  CONSTRAINT animais_lote_fk FOREIGN KEY (lote_id) REFERENCES lotes(id) ON DELETE SET NULL,
  CONSTRAINT animais_mae_fk FOREIGN KEY (mae_id) REFERENCES animais(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tipos_vacinas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  propriedade_id INT UNSIGNED NOT NULL,
  nome VARCHAR(80) NOT NULL,
  intervalo_dias INT NULL,
  observacoes VARCHAR(255) NULL,
  UNIQUE KEY tipos_vacinas_nome_uk (propriedade_id, nome),
  CONSTRAINT tipos_vacinas_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vacinacoes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  propriedade_id INT UNSIGNED NOT NULL,
  tipo_vacina_id INT UNSIGNED NOT NULL,
  animal_id INT UNSIGNED NULL,
  lote_id INT UNSIGNED NULL,
  data DATE NOT NULL,
  dose VARCHAR(40) NULL,
  proxima_dose DATE NULL,
  responsavel VARCHAR(80) NULL,
  observacoes VARCHAR(255) NULL,
  KEY vacinacoes_data_idx (propriedade_id, data),
  KEY vacinacoes_proxima_idx (propriedade_id, proxima_dose),
  KEY vacinacoes_tipo_idx (tipo_vacina_id),
  KEY vacinacoes_animal_idx (animal_id),
  KEY vacinacoes_lote_idx (lote_id),
  CONSTRAINT vacinacoes_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  CONSTRAINT vacinacoes_tipo_fk FOREIGN KEY (tipo_vacina_id) REFERENCES tipos_vacinas(id) ON DELETE RESTRICT,
  CONSTRAINT vacinacoes_animal_fk FOREIGN KEY (animal_id) REFERENCES animais(id) ON DELETE CASCADE,
  CONSTRAINT vacinacoes_lote_fk FOREIGN KEY (lote_id) REFERENCES lotes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inseminacoes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  propriedade_id INT UNSIGNED NOT NULL,
  animal_id INT UNSIGNED NOT NULL,
  data DATE NOT NULL,
  tipo VARCHAR(10) NOT NULL DEFAULT 'IA',
  touro_semen VARCHAR(80) NULL,
  tecnico VARCHAR(80) NULL,
  resultado VARCHAR(10) NOT NULL DEFAULT 'pendente',
  previsao_parto DATE NULL,
  observacoes VARCHAR(255) NULL,
  KEY inseminacoes_data_idx (propriedade_id, data),
  KEY inseminacoes_parto_idx (propriedade_id, previsao_parto),
  KEY inseminacoes_animal_idx (animal_id),
  CONSTRAINT inseminacoes_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  CONSTRAINT inseminacoes_animal_fk FOREIGN KEY (animal_id) REFERENCES animais(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nascimentos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  propriedade_id INT UNSIGNED NOT NULL,
  mae_id INT UNSIGNED NOT NULL,
  data DATE NOT NULL,
  sexo CHAR(1) NOT NULL,
  peso_kg DECIMAL(8,2) NULL,
  bezerro_id INT UNSIGNED NULL,
  observacoes VARCHAR(255) NULL,
  KEY nascimentos_data_idx (propriedade_id, data),
  KEY nascimentos_mae_idx (mae_id),
  KEY nascimentos_bezerro_idx (bezerro_id),
  CONSTRAINT nascimentos_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  CONSTRAINT nascimentos_mae_fk FOREIGN KEY (mae_id) REFERENCES animais(id) ON DELETE CASCADE,
  CONSTRAINT nascimentos_bezerro_fk FOREIGN KEY (bezerro_id) REFERENCES animais(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pesagens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  propriedade_id INT UNSIGNED NOT NULL,
  animal_id INT UNSIGNED NOT NULL,
  data DATE NOT NULL,
  peso_kg DECIMAL(8,2) NOT NULL,
  observacoes VARCHAR(255) NULL,
  UNIQUE KEY pesagens_dia_uk (animal_id, data),
  KEY pesagens_data_idx (propriedade_id, data),
  CONSTRAINT pesagens_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  CONSTRAINT pesagens_animal_fk FOREIGN KEY (animal_id) REFERENCES animais(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS producao_leite (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  propriedade_id INT UNSIGNED NOT NULL,
  animal_id INT UNSIGNED NULL,
  data DATE NOT NULL,
  turno VARCHAR(10) NOT NULL DEFAULT 'dia',
  litros DECIMAL(8,2) NOT NULL,
  observacoes VARCHAR(255) NULL,
  KEY leite_data_idx (propriedade_id, data),
  KEY leite_animal_idx (animal_id),
  CONSTRAINT leite_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  CONSTRAINT leite_animal_fk FOREIGN KEY (animal_id) REFERENCES animais(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS anotacoes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  propriedade_id INT UNSIGNED NOT NULL,
  data DATE NOT NULL,
  titulo VARCHAR(120) NOT NULL,
  texto TEXT NULL,
  autor_id INT UNSIGNED NULL,
  KEY anotacoes_data_idx (propriedade_id, data),
  KEY anotacoes_autor_idx (autor_id),
  CONSTRAINT anotacoes_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  CONSTRAINT anotacoes_autor_fk FOREIGN KEY (autor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS compras (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  propriedade_id INT UNSIGNED NOT NULL,
  data DATE NOT NULL,
  descricao VARCHAR(160) NOT NULL,
  quantidade INT NOT NULL DEFAULT 1,
  valor_total DECIMAL(12,2) NOT NULL,
  fornecedor VARCHAR(120) NULL,
  animal_id INT UNSIGNED NULL,
  observacoes VARCHAR(255) NULL,
  KEY compras_data_idx (propriedade_id, data),
  KEY compras_animal_idx (animal_id),
  CONSTRAINT compras_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  CONSTRAINT compras_animal_fk FOREIGN KEY (animal_id) REFERENCES animais(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vendas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  propriedade_id INT UNSIGNED NOT NULL,
  data DATE NOT NULL,
  descricao VARCHAR(160) NOT NULL,
  quantidade INT NOT NULL DEFAULT 1,
  valor_total DECIMAL(12,2) NOT NULL,
  comprador VARCHAR(120) NULL,
  animal_id INT UNSIGNED NULL,
  observacoes VARCHAR(255) NULL,
  KEY vendas_data_idx (propriedade_id, data),
  KEY vendas_animal_idx (animal_id),
  CONSTRAINT vendas_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  CONSTRAINT vendas_animal_fk FOREIGN KEY (animal_id) REFERENCES animais(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- origem: manual | compra | venda. Lançamentos de compra/venda são mantidos pelo sistema.
CREATE TABLE IF NOT EXISTS movimentacoes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  propriedade_id INT UNSIGNED NOT NULL,
  data DATE NOT NULL,
  tipo VARCHAR(10) NOT NULL,
  categoria VARCHAR(60) NOT NULL,
  descricao VARCHAR(160) NULL,
  valor DECIMAL(12,2) NOT NULL,
  origem VARCHAR(10) NOT NULL DEFAULT 'manual',
  origem_id INT UNSIGNED NULL,
  KEY movimentacoes_data_idx (propriedade_id, data),
  KEY movimentacoes_origem_idx (origem, origem_id),
  CONSTRAINT movimentacoes_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registro_atividades (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  propriedade_id INT UNSIGNED NULL,
  usuario_id INT UNSIGNED NULL,
  acao VARCHAR(20) NOT NULL,
  tabela VARCHAR(40) NOT NULL,
  registro_id INT UNSIGNED NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY atividades_prop_idx (propriedade_id, criado_em),
  CONSTRAINT atividades_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  CONSTRAINT atividades_usuario_fk FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

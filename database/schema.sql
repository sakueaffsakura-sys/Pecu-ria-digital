-- Pecuária Digital — banco MySQL 8 / MariaDB 10.5+.
-- Mesmos nomes de tabelas e colunas do sistema original (Supabase), para dar para trazer os dados de lá.
-- Os identificadores são UUID (texto de 36 caracteres), gerados pelo PHP.
-- Aplicar:  mysql -u root pecuaria < database/schema.sql   (pode rodar de novo: só cria o que falta)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------- Contas
CREATE TABLE IF NOT EXISTS usuarios (
  id CHAR(36) NOT NULL PRIMARY KEY,
  email VARCHAR(254) NOT NULL,
  senha_hash VARCHAR(255) NOT NULL,
  email_confirmado_em DATETIME NULL,
  totp_segredo VARCHAR(64) NULL,
  totp_ativo TINYINT(1) NOT NULL DEFAULT 0,
  totp_recuperacao TEXT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_login DATETIME NULL,
  UNIQUE KEY usuarios_email_uk (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS profiles (
  id CHAR(36) NOT NULL PRIMARY KEY,
  nome_completo VARCHAR(120) NOT NULL,
  nome_exibicao VARCHAR(60) NULL,
  telefone VARCHAR(30) NULL,
  cargo VARCHAR(80) NULL,
  biografia TEXT NULL,
  avatar_url VARCHAR(255) NULL,
  preferencias TEXT NULL,
  bloqueado TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT profiles_usuario_fk FOREIGN KEY (id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- role: administrador (do site inteiro)
CREATE TABLE IF NOT EXISTS user_roles (
  id CHAR(36) NOT NULL PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  role VARCHAR(20) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY user_roles_uk (user_id, role),
  CONSTRAINT user_roles_usuario_fk FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aparelhos conectados (cada login é uma sessão; dá para sair de um ou de todos).
CREATE TABLE IF NOT EXISTS sessoes (
  id CHAR(64) NOT NULL PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  aparelho VARCHAR(160) NULL,
  ip VARCHAR(45) NULL,
  duas_etapas_ok TINYINT(1) NOT NULL DEFAULT 0,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  visto_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  encerrada_em DATETIME NULL,
  KEY sessoes_usuario_idx (user_id, encerrada_em),
  CONSTRAINT sessoes_usuario_fk FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Links de confirmação de e-mail e de nova senha (só o hash do código fica guardado).
CREATE TABLE IF NOT EXISTS tokens_conta (
  id CHAR(36) NOT NULL PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  tipo VARCHAR(20) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira_em DATETIME NOT NULL,
  usado_em DATETIME NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY tokens_hash_uk (token_hash),
  KEY tokens_usuario_idx (user_id),
  CONSTRAINT tokens_usuario_fk FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contador de tentativas (login, cadastro, Yui…), compartilhado por todos os processos do PHP.
CREATE TABLE IF NOT EXISTS rate_limits (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  chave VARCHAR(190) NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY rate_limits_chave_idx (chave, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Propriedades
CREATE TABLE IF NOT EXISTS propriedades (
  id CHAR(36) NOT NULL PRIMARY KEY,
  proprietario_id CHAR(36) NOT NULL,
  nome VARCHAR(120) NOT NULL,
  cidade VARCHAR(80) NULL,
  estado VARCHAR(2) NULL,
  periodo_gestacao_dias INT NOT NULL DEFAULT 283,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY propriedades_dono_idx (proprietario_id),
  CONSTRAINT propriedades_dono_fk FOREIGN KEY (proprietario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- role: proprietario | administrador | colaborador | visualizador
CREATE TABLE IF NOT EXISTS propriedade_membros (
  id CHAR(36) NOT NULL PRIMARY KEY,
  propriedade_id CHAR(36) NOT NULL,
  user_id CHAR(36) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'colaborador',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY membros_uk (propriedade_id, user_id),
  KEY membros_usuario_idx (user_id),
  CONSTRAINT membros_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  CONSTRAINT membros_usuario_fk FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS propriedade_perfil (
  id CHAR(36) NOT NULL PRIMARY KEY,
  propriedade_id CHAR(36) NOT NULL,
  descricao TEXT NULL,
  texto_apresentacao TEXT NULL,
  endereco VARCHAR(255) NULL,
  telefone VARCHAR(30) NULL,
  email VARCHAR(254) NULL,
  website VARCHAR(255) NULL,
  logo_url VARCHAR(255) NULL,
  banner_url VARCHAR(255) NULL,
  imagem_url VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY perfil_prop_uk (propriedade_id),
  CONSTRAINT perfil_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracoes (
  id CHAR(36) NOT NULL PRIMARY KEY,
  propriedade_id CHAR(36) NOT NULL,
  chave VARCHAR(80) NOT NULL,
  valor TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY configuracoes_uk (propriedade_id, chave),
  CONSTRAINT configuracoes_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Rebanho
CREATE TABLE IF NOT EXISTS lotes (
  id CHAR(36) NOT NULL PRIMARY KEY,
  propriedade_id CHAR(36) NOT NULL,
  nome VARCHAR(120) NOT NULL,
  descricao TEXT NULL,
  finalidade VARCHAR(120) NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY lotes_nome_uk (propriedade_id, nome),
  CONSTRAINT lotes_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- situacao: ativo | vendido | perdido | falecido | transferido
CREATE TABLE IF NOT EXISTS animais (
  id CHAR(36) NOT NULL PRIMARY KEY,
  propriedade_id CHAR(36) NOT NULL,
  lote_id CHAR(36) NULL,
  numero_brinco VARCHAR(60) NOT NULL,
  nome VARCHAR(120) NULL,
  sexo CHAR(1) NOT NULL,
  raca VARCHAR(80) NULL,
  data_nascimento DATE NULL,
  mae_id CHAR(36) NULL,
  pai_id CHAR(36) NULL,
  situacao VARCHAR(20) NOT NULL DEFAULT 'ativo',
  observacoes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY animais_brinco_uk (propriedade_id, numero_brinco),
  KEY animais_situacao_idx (propriedade_id, situacao),
  KEY animais_lote_idx (lote_id),
  KEY animais_mae_idx (mae_id),
  KEY animais_pai_idx (pai_id),
  CONSTRAINT animais_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  CONSTRAINT animais_lote_fk FOREIGN KEY (lote_id) REFERENCES lotes(id) ON DELETE SET NULL,
  CONSTRAINT animais_mae_fk FOREIGN KEY (mae_id) REFERENCES animais(id) ON DELETE SET NULL,
  CONSTRAINT animais_pai_fk FOREIGN KEY (pai_id) REFERENCES animais(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pesagens (
  id CHAR(36) NOT NULL PRIMARY KEY,
  propriedade_id CHAR(36) NOT NULL,
  animal_id CHAR(36) NOT NULL,
  data DATE NOT NULL,
  peso_kg DECIMAL(10,2) NOT NULL,
  observacoes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY pesagens_dia_uk (animal_id, data),
  KEY pesagens_data_idx (propriedade_id, data),
  CONSTRAINT pesagens_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  CONSTRAINT pesagens_animal_fk FOREIGN KEY (animal_id) REFERENCES animais(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS nascimentos (
  id CHAR(36) NOT NULL PRIMARY KEY,
  propriedade_id CHAR(36) NOT NULL,
  vaca_id CHAR(36) NOT NULL,
  bezerro_id CHAR(36) NOT NULL,
  data_nascimento DATE NOT NULL,
  sexo CHAR(1) NOT NULL,
  lote_id CHAR(36) NULL,
  observacoes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY nascimentos_bezerro_uk (bezerro_id),
  KEY nascimentos_data_idx (propriedade_id, data_nascimento),
  KEY nascimentos_vaca_idx (vaca_id),
  KEY nascimentos_lote_idx (lote_id),
  CONSTRAINT nascimentos_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  CONSTRAINT nascimentos_vaca_fk FOREIGN KEY (vaca_id) REFERENCES animais(id) ON DELETE RESTRICT,
  CONSTRAINT nascimentos_bezerro_fk FOREIGN KEY (bezerro_id) REFERENCES animais(id) ON DELETE RESTRICT,
  CONSTRAINT nascimentos_lote_fk FOREIGN KEY (lote_id) REFERENCES lotes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Sanidade
CREATE TABLE IF NOT EXISTS tipos_vacinas (
  id CHAR(36) NOT NULL PRIMARY KEY,
  propriedade_id CHAR(36) NOT NULL,
  nome VARCHAR(120) NOT NULL,
  intervalo_dias INT NULL,
  descricao TEXT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY tipos_vacinas_nome_uk (propriedade_id, nome),
  CONSTRAINT tipos_vacinas_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Uma aplicação é de um animal OU de um lote inteiro.
CREATE TABLE IF NOT EXISTS vacinacoes (
  id CHAR(36) NOT NULL PRIMARY KEY,
  propriedade_id CHAR(36) NOT NULL,
  animal_id CHAR(36) NULL,
  lote_id CHAR(36) NULL,
  tipo_vacina_id CHAR(36) NOT NULL,
  data_aplicacao DATE NOT NULL,
  proxima_aplicacao DATE NULL,
  responsavel VARCHAR(120) NULL,
  observacao TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY vacinacoes_data_idx (propriedade_id, data_aplicacao),
  KEY vacinacoes_proxima_idx (propriedade_id, proxima_aplicacao),
  KEY vacinacoes_alvo_idx (propriedade_id, tipo_vacina_id, animal_id, lote_id, data_aplicacao),
  KEY vacinacoes_animal_idx (animal_id),
  KEY vacinacoes_lote_idx (lote_id),
  KEY vacinacoes_tipo_idx (tipo_vacina_id),
  CONSTRAINT vacinacoes_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  CONSTRAINT vacinacoes_animal_fk FOREIGN KEY (animal_id) REFERENCES animais(id) ON DELETE CASCADE,
  CONSTRAINT vacinacoes_lote_fk FOREIGN KEY (lote_id) REFERENCES lotes(id) ON DELETE CASCADE,
  CONSTRAINT vacinacoes_tipo_fk FOREIGN KEY (tipo_vacina_id) REFERENCES tipos_vacinas(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Reprodução
-- situacao: em_andamento | confirmada | falhou | parto_realizado | cancelada
CREATE TABLE IF NOT EXISTS inseminacoes (
  id CHAR(36) NOT NULL PRIMARY KEY,
  propriedade_id CHAR(36) NOT NULL,
  animal_id CHAR(36) NOT NULL,
  data_inseminacao DATE NOT NULL,
  reprodutor_semen VARCHAR(160) NOT NULL,
  previsao_parto DATE NOT NULL,
  situacao VARCHAR(20) NOT NULL DEFAULT 'em_andamento',
  observacao TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY inseminacoes_data_idx (propriedade_id, data_inseminacao),
  KEY inseminacoes_parto_idx (propriedade_id, situacao, previsao_parto),
  KEY inseminacoes_animal_idx (animal_id),
  CONSTRAINT inseminacoes_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  CONSTRAINT inseminacoes_animal_fk FOREIGN KEY (animal_id) REFERENCES animais(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Produção
CREATE TABLE IF NOT EXISTS producao_leite (
  id CHAR(36) NOT NULL PRIMARY KEY,
  propriedade_id CHAR(36) NOT NULL,
  animal_id CHAR(36) NOT NULL,
  lote_id CHAR(36) NULL,
  data DATE NOT NULL,
  litros DECIMAL(10,2) NOT NULL,
  valor_litro DECIMAL(14,2) NOT NULL DEFAULT 0,
  valor_total DECIMAL(18,2) GENERATED ALWAYS AS (litros * valor_litro) STORED,
  observacao TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY leite_data_idx (propriedade_id, data),
  KEY leite_animal_idx (animal_id, data),
  KEY leite_lote_idx (lote_id),
  CONSTRAINT leite_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  CONSTRAINT leite_animal_fk FOREIGN KEY (animal_id) REFERENCES animais(id) ON DELETE CASCADE,
  CONSTRAINT leite_lote_fk FOREIGN KEY (lote_id) REFERENCES lotes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS anotacoes_producao (
  id CHAR(36) NOT NULL PRIMARY KEY,
  propriedade_id CHAR(36) NOT NULL,
  animal_id CHAR(36) NULL,
  lote_id CHAR(36) NULL,
  data DATE NOT NULL,
  producao DECIMAL(10,2) NULL,
  observacao TEXT NOT NULL,
  comportamento TEXT NULL,
  alimentacao TEXT NULL,
  informacoes_adicionais TEXT NULL,
  marcada TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY anotacoes_data_idx (propriedade_id, data),
  KEY anotacoes_animal_idx (animal_id),
  KEY anotacoes_lote_idx (lote_id),
  CONSTRAINT anotacoes_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  CONSTRAINT anotacoes_animal_fk FOREIGN KEY (animal_id) REFERENCES animais(id) ON DELETE SET NULL,
  CONSTRAINT anotacoes_lote_fk FOREIGN KEY (lote_id) REFERENCES lotes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Dinheiro
-- tipo: receita | despesa | investimento.  origem_tipo: compra | venda (lançamentos automáticos)
CREATE TABLE IF NOT EXISTS movimentacoes_financeiras (
  id CHAR(36) NOT NULL PRIMARY KEY,
  propriedade_id CHAR(36) NOT NULL,
  tipo VARCHAR(20) NOT NULL,
  categoria VARCHAR(120) NOT NULL,
  descricao VARCHAR(255) NOT NULL,
  valor DECIMAL(18,2) NOT NULL,
  data DATE NOT NULL,
  origem_tipo VARCHAR(10) NULL,
  origem_id CHAR(36) NULL,
  observacao TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY movimentacoes_origem_uk (origem_tipo, origem_id),
  KEY movimentacoes_data_idx (propriedade_id, data),
  KEY movimentacoes_tipo_idx (propriedade_id, tipo, data),
  CONSTRAINT movimentacoes_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investimentos (
  id CHAR(36) NOT NULL PRIMARY KEY,
  propriedade_id CHAR(36) NOT NULL,
  movimentacao_id CHAR(36) NULL,
  descricao VARCHAR(255) NOT NULL,
  categoria VARCHAR(120) NOT NULL,
  valor DECIMAL(18,2) NOT NULL,
  data DATE NOT NULL,
  observacao TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY investimentos_data_idx (propriedade_id, data),
  CONSTRAINT investimentos_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS compras (
  id CHAR(36) NOT NULL PRIMARY KEY,
  propriedade_id CHAR(36) NOT NULL,
  data DATE NOT NULL,
  produto_animal VARCHAR(255) NOT NULL,
  quantidade DECIMAL(12,2) NOT NULL,
  valor DECIMAL(18,2) NOT NULL,
  fornecedor VARCHAR(160) NULL,
  categoria VARCHAR(120) NOT NULL,
  observacao TEXT NULL,
  movimentacao_id CHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY compras_data_idx (propriedade_id, data),
  CONSTRAINT compras_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vendas (
  id CHAR(36) NOT NULL PRIMARY KEY,
  propriedade_id CHAR(36) NOT NULL,
  data DATE NOT NULL,
  animal_id CHAR(36) NULL,
  item VARCHAR(255) NOT NULL,
  quantidade DECIMAL(12,2) NOT NULL,
  valor DECIMAL(18,2) NOT NULL,
  comprador VARCHAR(160) NULL,
  categoria VARCHAR(120) NOT NULL,
  observacao TEXT NULL,
  movimentacao_id CHAR(36) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY vendas_data_idx (propriedade_id, data),
  KEY vendas_animal_idx (animal_id),
  CONSTRAINT vendas_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  CONSTRAINT vendas_animal_fk FOREIGN KEY (animal_id) REFERENCES animais(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Avisos e histórico
-- "Marcar como visto" de um aviso calculado guarda a chave dele aqui (lida = 1).
CREATE TABLE IF NOT EXISTS notificacoes (
  id CHAR(36) NOT NULL PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  propriedade_id CHAR(36) NULL,
  chave VARCHAR(190) NULL,
  titulo VARCHAR(200) NOT NULL,
  mensagem TEXT NOT NULL,
  lida TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY notificacoes_chave_uk (user_id, chave),
  KEY notificacoes_usuario_idx (user_id, lida),
  CONSTRAINT notificacoes_usuario_fk FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exclusões, para o aplicativo apagar o que foi apagado no site.
CREATE TABLE IF NOT EXISTS registros_excluidos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  propriedade_id CHAR(36) NOT NULL,
  tabela VARCHAR(40) NOT NULL,
  registro_id CHAR(36) NOT NULL,
  excluido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY excluidos_cursor_idx (propriedade_id, tabela, excluido_em, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS importacoes (
  id CHAR(36) NOT NULL PRIMARY KEY,
  propriedade_id CHAR(36) NOT NULL,
  tabela VARCHAR(40) NOT NULL,
  arquivo VARCHAR(255) NOT NULL,
  status VARCHAR(20) NOT NULL,
  total_linhas INT NOT NULL DEFAULT 0,
  processadas INT NOT NULL DEFAULT 0,
  aceitas INT NOT NULL DEFAULT 0,
  rejeitadas INT NOT NULL DEFAULT 0,
  rejeitadas_detalhe TEXT NULL,
  erro TEXT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY importacoes_prop_idx (propriedade_id, criado_em),
  CONSTRAINT importacoes_prop_fk FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- Sistema
CREATE TABLE IF NOT EXISTS configuracoes_sistema (
  chave VARCHAR(80) NOT NULL PRIMARY KEY,
  valor TEXT NULL,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_por CHAR(36) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS eventos_seguranca (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(40) NOT NULL,
  severidade VARCHAR(10) NOT NULL,
  ip VARCHAR(45) NULL,
  caminho VARCHAR(160) NULL,
  detalhe VARCHAR(300) NULL,
  quantidade INT NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY eventos_data_idx (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

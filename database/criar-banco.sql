-- =============================================================================
-- Pecuária Digital — criar o banco e o usuário (só para instalação MANUAL).
-- O deploy/instalar.sh já faz isto sozinho, com uma senha aleatória.
--
--   1. Troque TROQUE-ESTA-SENHA abaixo por uma senha forte
--      (gere uma com:  openssl rand -hex 24)
--   2. Rode como root do MySQL/MariaDB:
--        mysql -u root < database/criar-banco.sql
--   3. Crie as tabelas:
--        mysql -u root pecuaria < database/schema.sql
--   4. Coloque a MESMA senha em DB_SENHA no arquivo .env
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `pecuaria`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'pecuaria'@'localhost' IDENTIFIED BY 'TROQUE-ESTA-SENHA';
ALTER USER 'pecuaria'@'localhost' IDENTIFIED BY 'TROQUE-ESTA-SENHA';

-- Só o necessário para o site funcionar e para as tabelas novas das próximas
-- versões serem criadas (CREATE/ALTER/INDEX). Nada de DROP nem acesso a outros bancos.
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES, LOCK TABLES
  ON `pecuaria`.* TO 'pecuaria'@'localhost';

FLUSH PRIVILEGES;

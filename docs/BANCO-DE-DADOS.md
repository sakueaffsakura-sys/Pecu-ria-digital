# Banco de dados (MySQL / MariaDB)

- **Motor:** MySQL 8.0+ ou MariaDB 10.5+, com `utf8mb4` (aceita acentos e emojis).
- **Estrutura:** [`database/schema.sql`](../database/schema.sql). Pode rodar de novo sem perder
  nada: só cria o que falta (`CREATE TABLE IF NOT EXISTS`).
- **Criar banco e usuário à mão:** [`database/criar-banco.sql`](../database/criar-banco.sql).
- **Conexão:** bloco *Banco de dados* do `.env` (veja [CONFIGURACAO.md](CONFIGURACAO.md#banco-de-dados)).
- **Mesmos nomes de tabelas e colunas do sistema original (Supabase)**, com identificadores UUID
  (texto de 36 caracteres gerado pelo PHP). Assim dá para trazer os dados de lá.

## Criar do zero

O `deploy/instalar.sh` faz tudo. À mão:

```bash
openssl rand -hex 24                                   # gera uma senha; copie
nano database/criar-banco.sql                          # troque TROQUE-ESTA-SENHA pela senha
mysql -u root < database/criar-banco.sql               # cria o banco "pecuaria" e o usuário "pecuaria"
mysql -u root pecuaria < database/schema.sql           # cria as 28 tabelas
nano .env                                              # DB_SENHA= a mesma senha
php scripts/verificar.php                              # deve mostrar "Todas as 28 tabelas existem"
```

O usuário do site só tem permissão de ler, gravar e criar ou alterar tabelas **no banco `pecuaria`**.
Ele não consegue apagar tabelas nem ver outros bancos. Para tarefas de manutenção, use o `root` do
MySQL, que na VPS só entra pelo próprio servidor (`mysql -u root`).

## Tabelas

| Grupo | Tabela | O que guarda |
| --- | --- | --- |
| **Contas** | `usuarios` | E-mail, senha (bcrypt), verificação em duas etapas |
| | `profiles` | Nome, foto, telefone, cargo, biografia |
| | `user_roles` | Quem é administrador do site |
| | `sessoes` | Aparelhos conectados (cada login); dá para encerrar um ou todos |
| | `tokens_conta` | Links de "nova senha" e confirmação de e-mail (só o hash) |
| | `rate_limits` | Contador de tentativas (login, cadastro…) contra abuso |
| **Propriedades** | `propriedades` | Fazendas: nome, cidade, estado, período de gestação, dono |
| | `propriedade_membros` | Quem mais acessa a fazenda e com qual papel (administrador, colaborador, só consulta) |
| | `propriedade_perfil` | Logo, banner, foto, contato e apresentação da fazenda |
| | `configuracoes` | Preferências da fazenda |
| **Rebanho** | `animais` | Brinco, nome, sexo, raça, nascimento, lote, pai e mãe, situação |
| | `lotes` | Lotes ou pastos |
| | `pesagens` | Pesos (um por animal por dia) |
| | `nascimentos` | Partos: vaca, bezerro, peso ao nascer |
| **Sanidade** | `tipos_vacinas` | Vacinas usadas e intervalo entre doses |
| | `vacinacoes` | Aplicações (num animal ou num lote inteiro) e a próxima dose |
| **Reprodução** | `inseminacoes` | Inseminações, touro, previsão de parto, situação |
| **Produção** | `producao_leite` | Litros por dia, valor do litro e total (calculado pelo banco) |
| | `anotacoes_producao` | Mural de anotações |
| **Dinheiro** | `movimentacoes_financeiras` | Receitas, despesas e investimentos (compras e vendas entram sozinhas) |
| | `investimentos` | Investimentos detalhados |
| | `compras` / `vendas` | Compra e venda de animais e insumos |
| **Avisos** | `notificacoes` | Avisos e os "marcados como vistos" |
| | `registros_excluidos` | Histórico do que foi apagado (para sincronizar o aplicativo) |
| | `importacoes` | Histórico de planilhas importadas |
| **Sistema** | `configuracoes_sistema` | Ajustes gerais do site |
| | `eventos_seguranca` | Registro de entradas, trocas de senha e ações sensíveis |

As próximas etapas acrescentam tabelas novas (assinaturas, eBooks, suporte…). Ao atualizar, o
instalador roda o `schema.sql` de novo e elas são criadas sem mexer nos dados.

## Consultas úteis

```bash
mysql -u root pecuaria
```

```sql
-- Contas e quando entraram pela última vez
SELECT u.email, p.nome_completo, u.criado_em, u.ultimo_login FROM usuarios u LEFT JOIN profiles p ON p.id = u.id ORDER BY u.criado_em DESC;

-- Tornar alguém administrador do site
INSERT INTO user_roles (id, user_id, role) SELECT UUID(), id, 'administrador' FROM usuarios WHERE email = 'pessoa@exemplo.com';

-- Fazendas e quantos animais ativos cada uma tem
SELECT pr.nome, COUNT(a.id) AS animais FROM propriedades pr LEFT JOIN animais a ON a.propriedade_id = pr.id AND a.situacao = 'ativo' GROUP BY pr.id ORDER BY animais DESC;

-- Tamanho do banco
SELECT table_name, ROUND((data_length + index_length) / 1024 / 1024, 2) AS mb FROM information_schema.tables WHERE table_schema = 'pecuaria' ORDER BY mb DESC;
```

Saia com `exit`.

## Backup e restauração

O backup roda **todo dia às 03:17** (`deploy/backup.sh`) e guarda 14 dias em `/var/backups/pecuaria/`:

| Arquivo | Conteúdo |
| --- | --- |
| `pecuaria-AAAA-MM-DD.sql.gz` | Banco inteiro |
| `arquivos-AAAA-MM-DD.tar.gz` | Fotos, logos, banners e planilhas enviadas (`storage/`) |
| `env-AAAA-MM-DD` | Cópia do `.env` |

```bash
# Fazer um backup agora
/usr/local/bin/pecuaria-backup

# Restaurar o banco de um dia
gunzip -c /var/backups/pecuaria/pecuaria-2026-11-13.sql.gz | mysql -u root pecuaria

# Restaurar os arquivos
tar -xzf /var/backups/pecuaria/arquivos-2026-11-13.tar.gz -C /var/www/pecuaria

# Levar os backups para o seu computador (rode NO SEU computador)
scp -r root@IP-DA-VPS:/var/backups/pecuaria ./backups-pecuaria
```

> Os backups ficam **na mesma VPS**. Se a VPS for perdida, eles também se perdem. Copie para fora
> de vez em quando (com `scp`, ou peça à hospedagem o backup/snapshot da VPS).

## Trazer os dados do sistema original (Supabase)

As tabelas e colunas têm os mesmos nomes, então a migração é tabela por tabela:

1. No Supabase: *Table editor* → cada tabela → **Export to CSV** (ou `pg_dump --data-only --column-inserts`).
2. Na ordem abaixo, para respeitar as ligações entre tabelas:
   `propriedades` → `propriedade_membros` → `propriedade_perfil` → `lotes` → `animais` → `pesagens` →
   `nascimentos` → `tipos_vacinas` → `vacinacoes` → `inseminacoes` → `producao_leite` →
   `anotacoes_producao` → `compras` → `vendas` → `movimentacoes_financeiras` → `investimentos`.
3. Importe cada CSV:
   ```sql
   LOAD DATA LOCAL INFILE '/root/animais.csv' INTO TABLE animais
     CHARACTER SET utf8mb4 FIELDS TERMINATED BY ',' ENCLOSURE BY '"' IGNORE 1 LINES;
   ```
   (precisa de `mysql --local-infile=1 -u root pecuaria`). Colunas que não existem aqui podem ser
   descartadas antes, numa planilha.
4. **Contas:** as senhas do Supabase não podem ser copiadas (ficam em outro formato, que não sai de lá).
   Crie as contas com o mesmo e-mail e use "Esqueci a senha", ou crie e ligue às fazendas pelo SQL.
5. Para quem só quer trazer o rebanho, há um caminho mais simples: dentro do site, cada cadastro tem o botão
   **Importar planilha**, que aceita CSV com prévia e sem duplicar brincos.

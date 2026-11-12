# Pecuária Digital (PHP + MySQL)

Sistema web de gestão pecuária: rebanho, lotes, pesagens com ganho por dia, vacinas com próxima dose,
inseminações com previsão de parto, nascimentos, produção de leite, compras, vendas, entradas e saídas,
agenda, relatórios, planilhas e equipe com níveis de acesso. Funciona no celular e no computador.

- **PHP 8.1+** sem framework e sem Composer: é só copiar e rodar.
- **MySQL 8 ou MariaDB 10.5+**.
- **Nginx + PHP-FPM**, feito para **VPS Linux com acesso root** (ex.: VPS1 da Combinário: 4 vCores, 8 GB, Linux, só SSH).

## Instalar na VPS (uns 10 minutos)

Você vai precisar do **IP** e da **senha root** que a hospedagem manda por e-mail. Peça **Ubuntu 24.04**
(ou 22.04, ou Debian 12) como sistema.

1. **(Opcional) Domínio:** no painel onde você registrou o domínio, crie um registro **A** apontando para o IP da VPS.
   Sem domínio, o site funciona pelo IP, só que sem HTTPS.
2. **Entre na VPS pelo SSH.** No computador: `ssh root@IP-DA-VPS`. No celular Android, use um app de SSH
   (Termius ou JuiceSSH) com o mesmo IP, usuário `root` e a senha.
3. **Baixe o site e instale:**

   ```bash
   apt-get update && apt-get install -y git
   git clone https://github.com/sakueaffsakura-sys/pecuaria-digital.git /opt/pecuaria-digital
   cd /opt/pecuaria-digital
   bash deploy/instalar.sh seudominio.com.br seu-email@exemplo.com
   ```

   Sem domínio: `bash deploy/instalar.sh IP-DA-VPS`.
   Repositório privado: o `git clone` pede usuário e um *token* do GitHub
   (github.com → Settings → Developer settings → Personal access tokens) no lugar da senha.

4. Abra o endereço no navegador e clique em **Criar conta**. **A primeira conta criada vira a administradora do site.**

O script instala e configura sozinho: Nginx, PHP-FPM, MariaDB (com usuário e senha aleatória só para o site),
firewall (só SSH, HTTP e HTTPS), certificado HTTPS grátis, atualizações automáticas de segurança e backup diário.

## Dia a dia do servidor

| O quê | Como |
| --- | --- |
| Atualizar o site | `cd /opt/pecuaria-digital && git pull && bash deploy/instalar.sh seudominio.com.br seu-email` |
| Backups | Todo dia às 03:17 em `/var/backups/pecuaria/` (guarda 14 dias). **Copie para fora da VPS de vez em quando** — o backup da hospedagem é contratado à parte. |
| Restaurar um backup | `gunzip -c /var/backups/pecuaria/pecuaria-AAAA-MM-DD.sql.gz \| mysql -u root pecuaria` |
| Senha esquecida | Um administrador abre **Administração → Nova senha** e passa a senha provisória para a pessoa. |
| Fechar o cadastro | Em `/var/www/pecuaria/config/config.php`, mude `'cadastro_aberto' => false`. |
| Ver erros | `tail -f /var/log/nginx/error.log` |

## O que tem no sistema

- **Início:** animais ativos, vacinas vencidas, partos previstos, leite dos últimos 30 dias, saldo do mês e gráficos.
- **Rebanho:** animais (ficha com peso, ganho por dia, vacinas, reprodução e crias), lotes, pesagens e importação de planilha CSV.
- **Sanidade:** vacinação por animal ou por lote, com a próxima dose calculada pelo intervalo da vacina.
- **Reprodução:** inseminação (IA, IATF, monta, TE), diagnóstico, previsão de parto pelo período de gestação da propriedade,
  e parto que já cadastra a cria com a mãe, o lote e o peso ao nascer.
- **Leite:** por ordenha ou por vaca.
- **Financeiro:** entradas e saídas; compras e vendas viram lançamentos sozinhas (e uma venda de animal muda a situação dele para "vendido").
- **Agenda:** vacinas a aplicar, partos previstos e diagnósticos de prenhez atrasados.
- **Relatórios por período**, para imprimir ou salvar em PDF; toda lista pode ser baixada como planilha.
- **Equipe:** o dono dá acesso a outras contas como "pode lançar e editar" ou "só consulta".
- **Administração:** contas, bloqueio, administradores e senha provisória.

## Segurança

- Senhas com `password_hash` (bcrypt); sessão com cookie `HttpOnly`, `SameSite=Lax` e `Secure` no HTTPS.
- Todo formulário tem token contra CSRF; toda consulta usa parâmetros (sem SQL montado com texto do usuário).
- Toda leitura e gravação filtra pela propriedade em uso **e** confere se a pessoa é membro dela.
- Limite de tentativas de senha no banco (por IP e por e-mail) e campo-armadilha contra robôs no cadastro.
- Cabeçalhos de segurança (CSP sem script de fora, `X-Frame-Options`, `nosniff`); só o `public/index.php` é executável;
  `config/config.php` fica fora da pasta pública; limite de pedidos por IP no Nginx.

## Estrutura

```
public/            pasta pública (index.php, assets/)
app/               núcleo (banco, sessão, acesso), módulos, CRUD, gráficos, rotas/
views/             telas
database/schema.sql
deploy/            instalar.sh, nginx.conf, backup.sh
tests/e2e.php      teste de ponta a ponta
config/config.example.php
```

Para criar um cadastro novo, acrescente um item em `app/modulos.php` (tabela, campos e colunas), crie a tabela em
`database/schema.sql` e o link em `menu()` de `app/ui.php`. Lista, filtros, formulário, validação, exclusão e planilha saem prontos.

## Rodar no seu computador e testar

```bash
php tests/e2e.php      # 61 verificações; sobe o site com um banco SQLite temporário, não precisa de MySQL
```

Para usar de verdade no computador: crie um banco MySQL, aplique `database/schema.sql`, copie
`config/config.example.php` para `config/config.php`, preencha e rode `php -S localhost:8000 -t public public/index.php`.

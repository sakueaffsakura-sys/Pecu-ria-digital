# Configuração: o arquivo `.env`

Toda a configuração do site fica num único arquivo, **`.env`**, na raiz do projeto
(na VPS: `/var/www/pecuaria/.env`). O modelo com todos os itens é o **[`.env.example`](../.env.example)**.

- O `deploy/instalar.sh` **cria o `.env` sozinho**, com uma senha de banco aleatória.
- Instalação manual: `cp .env.example .env` e preencha.
- O `.env` **não vai para o GitHub** (está no `.gitignore`). Guarde uma cópia em lugar seguro.
  O backup diário também guarda uma cópia em `/var/backups/pecuaria/env-AAAA-MM-DD`.
- Fica **fora da pasta `public/`**, então o navegador nunca o alcança.
- Mudou algo? Vale no próximo acesso, sem reiniciar nada.
- Para conferir depois de editar, rode `php scripts/verificar.php`.

Editar na VPS:

```bash
nano /var/www/pecuaria/.env      # Ctrl+O, Enter para salvar; Ctrl+X para sair
```

## Formato

```ini
# linha de comentário
CHAVE=valor
CHAVE="valor com espaços"
CHAVE='senha#com#cerquilha'      # use aspas se o valor tiver # ou espaços
```

Se o servidor tiver uma **variável de ambiente** com o mesmo nome de uma chave do arquivo,
ela tem prioridade. Isso é útil em Docker e não é necessário na VPS.

---

## Site

| Chave | Exemplo | Para que serve |
| --- | --- | --- |
| `APP_NOME` | `"Pecuária Digital"` | Nome que aparece no título das páginas e nos e-mails |
| `APP_URL` | `https://pecuariadigital.com.br` | Endereço público, **sem barra no fim**. Usado nos links dos e-mails, no cookie seguro e no Mercado Pago. Com HTTPS precisa começar com `https://` |
| `APP_DEBUG` | `false` | `true` mostra detalhes técnicos dos erros na tela. **Em produção, sempre `false`** |
| `APP_FUSO` | `America/Sao_Paulo` | Fuso do "hoje", de vencimentos e relatórios. Ex.: `America/Manaus`, `America/Cuiaba`, `America/Belem` |

## Banco de dados

| Chave | Padrão | Para que serve |
| --- | --- | --- |
| `DB_CONEXAO` | `mysql` | `mysql` (MySQL ou MariaDB). `sqlite` só para testes no computador |
| `DB_HOST` | `127.0.0.1` | Endereço do servidor do banco. Na mesma VPS: `localhost` ou `127.0.0.1` |
| `DB_PORTA` | `3306` | Porta do MySQL/MariaDB |
| `DB_BANCO` | `pecuaria` | Nome do banco (com `sqlite`, o caminho do arquivo) |
| `DB_USUARIO` | `pecuaria` | Usuário do banco **só do site** (não use o root) |
| `DB_SENHA` | — | Senha desse usuário |
| `DB_SOCKET` | — | Opcional: caminho do socket (ex.: `/run/mysqld/mysqld.sock`) no lugar de host e porta |

Esqueceu a senha do banco? Gere outra e grave nos dois lugares:

```bash
NOVA=$(openssl rand -hex 24)
mysql -u root -e "ALTER USER 'pecuaria'@'localhost' IDENTIFIED BY '$NOVA'; FLUSH PRIVILEGES;"
sed -i "s#^DB_SENHA=.*#DB_SENHA=$NOVA#" /var/www/pecuaria/.env
php /var/www/pecuaria/scripts/verificar.php
```

Mais sobre o banco em [BANCO-DE-DADOS.md](BANCO-DE-DADOS.md).

## E-mail

Usado para **recuperar a senha** e para avisos da conta. Sem ele o site funciona, mas o
"Esqueci a senha" não consegue mandar o link. Nesse caso, um administrador redefine a senha.

| Chave | Exemplo | Para que serve |
| --- | --- | --- |
| `MAIL_HOST` | `smtp.gmail.com` | Servidor SMTP. **Vazio = e-mail desligado** |
| `MAIL_PORTA` | `587` | `587` com `tls` ou `465` com `ssl` |
| `MAIL_SEGURANCA` | `tls` | `tls` ou `ssl` |
| `MAIL_USUARIO` | `voce@gmail.com` | Login do SMTP |
| `MAIL_SENHA` | — | Senha do SMTP (no Gmail, uma **senha de app**) |
| `MAIL_REMETENTE` | `nao-responda@seudominio.com.br` | Quem aparece como remetente |
| `MAIL_NOME_REMETENTE` | `"Pecuária Digital"` | Nome do remetente |

**Exemplos:**

```ini
# Gmail: conta com verificação em duas etapas ligada →
# myaccount.google.com → Segurança → Senhas de app → gere uma de 16 letras
MAIL_HOST=smtp.gmail.com
MAIL_PORTA=587
MAIL_SEGURANCA=tls
MAIL_USUARIO=voce@gmail.com
MAIL_SENHA="abcd efgh ijkl mnop"
MAIL_REMETENTE=voce@gmail.com

# Zoho Mail (e-mail grátis com seu domínio)
MAIL_HOST=smtp.zoho.com
MAIL_PORTA=465
MAIL_SEGURANCA=ssl

# Brevo (antigo Sendinblue; 300 e-mails/dia grátis)
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORTA=587
MAIL_SEGURANCA=tls
```

> Muitas VPS bloqueiam a porta **25**. Use 587 ou 465.
> Para os e-mails não caírem no spam, configure SPF e DKIM do domínio no painel do provedor de e-mail.

## Mercado Pago (assinatura Premium, etapa 4)

| Chave | Onde pegar |
| --- | --- |
| `MERCADOPAGO_ACCESS_TOKEN` | mercadopago.com.br/developers → *Suas integrações* → sua aplicação → **Credenciais de produção** → *Access Token* |
| `MERCADOPAGO_PUBLIC_KEY` | Mesma tela → *Public Key* |
| `MERCADOPAGO_WEBHOOK_SECRET` | Sua aplicação → **Webhooks** → URL `https://seudominio/api/assinatura/webhook` → *Assinatura secreta* |

Vazio = assinatura só no modo de demonstração (o administrador libera o Premium à mão).

## Yui, a assistente com IA (etapa 5)

| Chave | Para que serve |
| --- | --- |
| `IA_CHAVE_API` | Chave da API do provedor de IA. **Vazio = Yui desligada** |
| `IA_MODELO` | Nome do modelo a usar |
| `IA_URL` | Endereço da API (deixe vazio para o padrão do provedor) |

---

## Arquivo completo de exemplo (produção)

```ini
APP_NOME="Pecuária Digital"
APP_URL=https://pecuariadigital.com.br
APP_DEBUG=false
APP_FUSO=America/Sao_Paulo

DB_CONEXAO=mysql
DB_HOST=localhost
DB_PORTA=3306
DB_BANCO=pecuaria
DB_USUARIO=pecuaria
DB_SENHA=5f0c2b7e9a1d4c38b6e0f2a7c9d1e3b5a7c9e1f3a5b7c9d1

MAIL_HOST=smtp.zoho.com
MAIL_PORTA=465
MAIL_SEGURANCA=ssl
MAIL_USUARIO=nao-responda@pecuariadigital.com.br
MAIL_SENHA="senha-do-email"
MAIL_REMETENTE=nao-responda@pecuariadigital.com.br
MAIL_NOME_REMETENTE="Pecuária Digital"

MERCADOPAGO_ACCESS_TOKEN=
MERCADOPAGO_PUBLIC_KEY=
MERCADOPAGO_WEBHOOK_SECRET=

IA_CHAVE_API=
IA_MODELO=
IA_URL=
```

## Formato antigo

Instalações feitas antes do `.env` usavam `config/config.php`. Ele **continua funcionando**: o site
usa o `.env` se existir e, se não, o `config/config.php`. Para migrar, copie os dados do banco para
um `.env` novo e apague o `config/config.php`.

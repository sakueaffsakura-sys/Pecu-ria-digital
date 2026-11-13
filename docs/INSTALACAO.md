# Instalação na VPS

Guia para colocar o Pecuária Digital no ar numa **VPS Linux com acesso root**, como a
**VPS1 da Combinário** (4 vCores, 8 GB de RAM, 75 GB NVMe, AntiDDoS, acesso só por SSH, sem painel).
O site é **PHP + MySQL** e não precisa de painel (cPanel, Plesk etc.).

> Resumo: entrar por SSH → baixar o projeto → rodar **um** comando → criar a primeira conta.
> O instalador cuida de Nginx, PHP, banco, arquivo `.env`, HTTPS, firewall e backup.

---

## 1. O que você precisa

| Item | Onde conseguir |
| --- | --- |
| VPS com **Ubuntu 24.04** (ou 22.04, ou Debian 12) | Peça essa distribuição na contratação ou no suporte da hospedagem |
| **IP** e **senha root** da VPS | E-mail de boas-vindas da hospedagem |
| Um programa de SSH | Computador: Terminal (Mac/Linux) ou PowerShell/PuTTY (Windows). Celular Android: **Termius** ou **JuiceSSH** |
| (Opcional) um **domínio** | Registro.br, Hostinger, GoDaddy… Sem domínio o site funciona pelo IP, mas sem HTTPS |
| (Opcional) um **e-mail SMTP** | Para o "Esqueci a senha". Veja [CONFIGURACAO.md](CONFIGURACAO.md#e-mail) |

## 2. Apontar o domínio (se tiver)

No painel de quem vende o domínio, abra a **zona DNS** e crie:

| Tipo | Nome | Valor |
| --- | --- | --- |
| A | `@` | IP da VPS |
| A | `www` | IP da VPS |

A mudança pode levar de minutos até algumas horas. Para conferir, rode `ping seudominio.com.br`;
a resposta deve mostrar o IP da VPS. Faça isso **antes** do passo 4, senão o HTTPS não é emitido
(dá para emitir depois, veja [MANUTENCAO.md](MANUTENCAO.md#https)).

## 3. Entrar na VPS

```bash
ssh root@IP-DA-VPS
```

Na primeira vez ele pergunta se confia no servidor: digite `yes`. Depois, cole a senha root
(ela não aparece enquanto você digita, é normal).

No **Termius** (Android): *Hosts → +*, coloque o IP em *Hostname*, `root` em *Username*, a senha
e toque em *Connect*.

> Recomendado: troque a senha root na primeira entrada com o comando `passwd`.

## 4. Baixar e instalar

```bash
apt-get update && apt-get install -y git
git clone https://github.com/sakueaffsakura-sys/Pecu-ria-digital.git /opt/pecuaria-digital
cd /opt/pecuaria-digital

# Com domínio e e-mail (ativa o HTTPS grátis da Let's Encrypt):
bash deploy/instalar.sh seudominio.com.br seu-email@exemplo.com

# Ainda sem domínio? Use o IP:
bash deploy/instalar.sh IP-DA-VPS
```

**Repositório privado?** O `git clone` pede usuário e senha: use seu usuário do GitHub e, no lugar da
senha, um **token** (github.com → *Settings → Developer settings → Personal access tokens →
Fine-grained*, com permissão de leitura em *Contents* deste repositório).

O instalador leva uns 3 a 5 minutos e faz isto:

1. Instala Nginx, PHP-FPM (8.1+), MariaDB, firewall (UFW), Certbot, cron e atualizações automáticas.
2. Ajusta o PHP (envio de até 3 MB, fuso de Brasília, OPcache).
3. Copia o site para `/var/www/pecuaria`.
4. Cria o banco `pecuaria` e um usuário só para o site, com **senha aleatória**.
5. **Cria o arquivo `/var/www/pecuaria/.env`** com essa senha e o endereço do site.
6. Cria todas as tabelas (`database/schema.sql`).
7. Configura o Nginx (`deploy/nginx.conf`) e libera no firewall só SSH, HTTP e HTTPS.
8. Emite o certificado HTTPS (se você passou domínio e e-mail).
9. Agenda o backup diário (03:17) em `/var/backups/pecuaria`.
10. Roda `scripts/verificar.php` e mostra se está tudo certo.

## 5. Primeiro acesso

1. Abra `https://seudominio.com.br` (ou `http://IP-DA-VPS`).
2. Clique em **Criar conta**. **A primeira conta criada vira a administradora do site**, então crie a sua agora.
3. Cadastre a propriedade e comece a usar.

Quer ver o sistema cheio de exemplos?

```bash
php /var/www/pecuaria/scripts/demonstracao.php seu-email@exemplo.com "Fazenda Demonstração"
```

## 6. Configurar o e-mail (recomendado)

Abra o `.env` e preencha o bloco **E-mail**:

```bash
nano /var/www/pecuaria/.env      # salve com Ctrl+O, Enter; saia com Ctrl+X
php /var/www/pecuaria/scripts/verificar.php
```

Detalhes e exemplos (Gmail, Zoho, Brevo) em [CONFIGURACAO.md](CONFIGURACAO.md#e-mail).

## 7. Conferir

```bash
php /var/www/pecuaria/scripts/verificar.php
```

Cada linha mostra ✔ (certo), **!** (atenção) ou ✘ (corrigir), já com a dica do comando.

---

## Instalação manual (sem o script)

Use só se não puder rodar o `instalar.sh`, por exemplo num servidor que já tem outros sites.

```bash
# 1. Pacotes
apt-get install -y nginx mariadb-server php-fpm php-cli php-mysql php-mbstring php-curl php-gd php-zip php-xml

# 2. Código
git clone https://github.com/sakueaffsakura-sys/Pecu-ria-digital.git /var/www/pecuaria
cd /var/www/pecuaria
mkdir -p storage

# 3. Banco: edite a senha dentro do arquivo antes de rodar
nano database/criar-banco.sql
mysql -u root < database/criar-banco.sql
mysql -u root pecuaria < database/schema.sql

# 4. Configuração
cp .env.example .env
nano .env                         # APP_URL e DB_SENHA (a mesma do passo 3)

# 5. Permissões
chown -R root:www-data /var/www/pecuaria
find /var/www/pecuaria -type d -exec chmod 750 {} \;
find /var/www/pecuaria -type f -exec chmod 640 {} \;
chmod 770 storage

# 6. Nginx: copie deploy/nginx.conf para /etc/nginx/sites-available/pecuaria,
#    troque __DOMINIO__, __PASTA__ (/var/www/pecuaria) e __PHP_SOCK__ (ex.: /run/php/php8.3-fpm.sock),
#    e crie /etc/nginx/conf.d/pecuaria-limite.conf com:
#      limit_req_zone $binary_remote_addr zone=pecuaria:10m rate=15r/s;
ln -s /etc/nginx/sites-available/pecuaria /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

# 7. Conferir
php scripts/verificar.php
```

**Apache em vez de Nginx?** Aponte o `DocumentRoot` para a pasta `public/` e ative o `mod_rewrite`.
O `public/.htaccess` já faz o resto.

**A pasta pública é sempre `public/`.** Nunca aponte o servidor para a raiz do projeto: é lá que ficam
o `.env` e o código. Se isso acontecer por engano, o `.htaccess` da raiz bloqueia tudo no Apache, e o
`nginx.conf` nega arquivos que começam com ponto.

## Requisitos mínimos

| Item | Mínimo | Recomendado |
| --- | --- | --- |
| Sistema | Ubuntu 22.04 / Debian 12 | Ubuntu 24.04 LTS |
| PHP | 8.1 com `pdo_mysql`, `mbstring`, `curl`, `openssl`, `fileinfo` | 8.3 + `gd`, `zip` |
| Banco | MySQL 8.0 ou MariaDB 10.5 | MariaDB 10.11 (vem no Ubuntu 24.04) |
| Servidor web | Nginx ou Apache 2.4 | Nginx + PHP-FPM |
| Memória | 1 GB | 2 GB+ (a VPS1 tem 8 GB: sobra bastante) |
| Disco | 2 GB livres | 10 GB+ para fotos e backups |

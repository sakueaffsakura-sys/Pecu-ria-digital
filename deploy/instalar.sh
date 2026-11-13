#!/usr/bin/env bash
# Pecuária Digital — instalação numa VPS Linux limpa (Ubuntu 22.04/24.04 ou Debian 12), com acesso root.
#
#   Uso (dentro da pasta do projeto, como root):
#     bash deploy/instalar.sh seu-dominio.com.br seu-email@exemplo.com
#   Sem domínio ainda? Use o IP da VPS:
#     bash deploy/instalar.sh 203.0.113.10
#
# Instala Nginx, PHP-FPM, MariaDB, firewall e (com domínio + e-mail) o certificado HTTPS grátis.
# Pode rodar de novo para atualizar o código: o banco e o arquivo .env são preservados.
set -euo pipefail

DOMINIO="${1:-}"
EMAIL="${2:-}"
PASTA="/var/www/pecuaria"
BANCO="pecuaria"
USUARIO_BANCO="pecuaria"
ORIGEM="$(cd "$(dirname "$0")/.." && pwd)"

passo() { printf '\n\033[1;32m==> %s\033[0m\n' "$*"; }
erro() { printf '\n\033[1;31mERRO: %s\033[0m\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || erro "rode como root (sudo bash deploy/instalar.sh ...)."
[ -n "$DOMINIO" ] || erro "informe o domínio ou o IP. Ex.: bash deploy/instalar.sh pecuaria.com.br voce@email.com"
[[ "$DOMINIO" =~ ^[A-Za-z0-9.-]+$ ]] || erro "domínio inválido: $DOMINIO"
command -v apt-get >/dev/null || erro "este script é para Ubuntu ou Debian (apt). Peça essa distribuição à hospedagem."

eh_ip=0
[[ "$DOMINIO" =~ ^[0-9.]+$ ]] && eh_ip=1

passo "Instalando pacotes (Nginx, PHP, MariaDB, firewall)"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y nginx mariadb-server php-fpm php-cli php-mysql php-mbstring php-curl php-gd php-zip php-xml \
  ufw rsync openssl cron unattended-upgrades
if [ "$eh_ip" -eq 0 ] && [ -n "$EMAIL" ]; then apt-get install -y certbot python3-certbot-nginx; fi

PHPV="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
php -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);' || erro "precisa de PHP 8.1 ou mais novo (encontrado $PHPV). Use Ubuntu 22.04+ ou Debian 12."
PHP_SOCK="/run/php/php${PHPV}-fpm.sock"
systemctl enable --now "php${PHPV}-fpm" mariadb nginx

passo "Ajustando o PHP"
cat > "/etc/php/${PHPV}/fpm/conf.d/90-pecuaria.ini" <<INI
expose_php = Off
upload_max_filesize = 3M
post_max_size = 4M
memory_limit = 256M
max_execution_time = 60
date.timezone = America/Sao_Paulo
session.use_strict_mode = 1
opcache.enable = 1
opcache.validate_timestamps = 1
opcache.revalidate_freq = 10
INI

passo "Copiando o site para $PASTA"
mkdir -p "$PASTA"
rsync -a --delete --exclude '.git' --exclude 'tests' --exclude 'config/config.php' --exclude '.env' --exclude 'storage' "$ORIGEM/" "$PASTA/"
mkdir -p "$PASTA/storage"

passo "Banco de dados e arquivo .env"
if [ ! -f "$PASTA/.env" ] && [ ! -f "$PASTA/config/config.php" ]; then
  SENHA_BANCO="$(openssl rand -hex 24)"
  mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`$BANCO\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$USUARIO_BANCO'@'localhost' IDENTIFIED BY '$SENHA_BANCO';
ALTER USER '$USUARIO_BANCO'@'localhost' IDENTIFIED BY '$SENHA_BANCO';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES, LOCK TABLES ON \`$BANCO\`.* TO '$USUARIO_BANCO'@'localhost';
FLUSH PRIVILEGES;
SQL
  if [ "$eh_ip" -eq 0 ] && [ -n "$EMAIL" ]; then URL="https://$DOMINIO"; else URL="http://$DOMINIO"; fi
  # Parte do .env.example e troca só o que a instalação sabe.
  sed -e "s#^APP_URL=.*#APP_URL=$URL#" \
      -e "s#^DB_HOST=.*#DB_HOST=localhost#" \
      -e "s#^DB_BANCO=.*#DB_BANCO=$BANCO#" \
      -e "s#^DB_USUARIO=.*#DB_USUARIO=$USUARIO_BANCO#" \
      -e "s#^DB_SENHA=.*#DB_SENHA=$SENHA_BANCO#" \
      -e "s#^MAIL_REMETENTE=.*#MAIL_REMETENTE=nao-responda@$DOMINIO#" \
      "$PASTA/.env.example" > "$PASTA/.env"
  echo ".env criado com uma senha de banco aleatória (veja docs/CONFIGURACAO.md para o e-mail)."
else
  echo "Configuração já existe (.env ou config/config.php): mantida."
fi
# Tabelas novas são criadas; as que já existem não são tocadas.
mysql -u root "$BANCO" < "$PASTA/database/schema.sql"

chown -R root:www-data "$PASTA"
find "$PASTA" -type d -exec chmod 750 {} \;
find "$PASTA" -type f -exec chmod 640 {} \;
chmod 755 "$PASTA/deploy/"*.sh
chmod 770 "$PASTA/storage"
[ -f "$PASTA/.env" ] && chmod 640 "$PASTA/.env"

passo "Configurando o Nginx"
cat > /etc/nginx/conf.d/pecuaria-limite.conf <<'NGX'
limit_req_zone $binary_remote_addr zone=pecuaria:10m rate=15r/s;
NGX
sed -e "s#__DOMINIO__#$DOMINIO#g" -e "s#__PASTA__#$PASTA#g" -e "s#__PHP_SOCK__#$PHP_SOCK#g" \
  "$PASTA/deploy/nginx.conf" > /etc/nginx/sites-available/pecuaria
ln -sf /etc/nginx/sites-available/pecuaria /etc/nginx/sites-enabled/pecuaria
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx
systemctl restart "php${PHPV}-fpm"

passo "Firewall (libera só SSH, HTTP e HTTPS)"
ufw allow OpenSSH >/dev/null
ufw allow 'Nginx Full' >/dev/null
ufw --force enable

if [ "$eh_ip" -eq 0 ] && [ -n "$EMAIL" ]; then
  passo "Certificado HTTPS (Let's Encrypt)"
  if certbot --nginx -d "$DOMINIO" --non-interactive --agree-tos -m "$EMAIL" --redirect --keep-until-expiring; then
    [ -f "$PASTA/.env" ] && sed -i "s#^APP_URL=http://#APP_URL=https://#" "$PASTA/.env"
    [ -f "$PASTA/config/config.php" ] && sed -i "s#'url' => 'http://#'url' => 'https://#" "$PASTA/config/config.php"
  else
    echo "Não deu para emitir o certificado agora. Confira se o domínio já aponta para o IP desta VPS e rode de novo:"
    echo "  certbot --nginx -d $DOMINIO -m $EMAIL --agree-tos --redirect"
  fi
fi

passo "Backup automático do banco (todo dia às 03:17, guarda 14 dias)"
install -m 750 "$PASTA/deploy/backup.sh" /usr/local/bin/pecuaria-backup
echo "17 3 * * * root /usr/local/bin/pecuaria-backup >/dev/null 2>&1" > /etc/cron.d/pecuaria-backup
chmod 644 /etc/cron.d/pecuaria-backup

passo "Atualizações de segurança automáticas do sistema"
dpkg-reconfigure -f noninteractive unattended-upgrades || true

if [ "$eh_ip" -eq 0 ] && [ -n "$EMAIL" ]; then ENDERECO="https://$DOMINIO"; else ENDERECO="http://$DOMINIO"; fi
passo "Conferindo a instalação"
php "$PASTA/scripts/verificar.php" || true

passo "Pronto!"
cat <<FIM
Abra $ENDERECO e clique em "Criar conta".
A PRIMEIRA conta criada vira a administradora do site — crie a sua agora.
Quer ver o sistema com dados de exemplo? Depois de criar a conta:
  php $PASTA/scripts/demonstracao.php seu-email@exemplo.com "Fazenda Demonstração"

Backups ficam em /var/backups/pecuaria (copie para fora da VPS de vez em quando).
Para atualizar depois: cd $ORIGEM && git pull && bash deploy/instalar.sh $DOMINIO $EMAIL
FIM

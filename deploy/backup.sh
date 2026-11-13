#!/usr/bin/env bash
# Cópia diária do banco e dos arquivos enviados (fotos, logos, banners, importações).
# Guarda as dos últimos 14 dias em /var/backups/pecuaria.
#
# Restaurar o banco:
#   gunzip -c /var/backups/pecuaria/pecuaria-AAAA-MM-DD.sql.gz | mysql -u root pecuaria
# Restaurar os arquivos:
#   tar -xzf /var/backups/pecuaria/arquivos-AAAA-MM-DD.tar.gz -C /var/www/pecuaria
set -euo pipefail
PASTA="${PASTA_PECUARIA:-/var/www/pecuaria}"
BANCO="${BANCO_PECUARIA:-pecuaria}"
DESTINO="/var/backups/pecuaria"
DIA="$(date +%F)"
mkdir -p "$DESTINO"
chmod 700 "$DESTINO"

ARQ="$DESTINO/$BANCO-$DIA.sql.gz"
mysqldump -u root --single-transaction --routines --triggers "$BANCO" | gzip -9 > "$ARQ.tmp"
mv "$ARQ.tmp" "$ARQ"

if [ -d "$PASTA/storage" ]; then
  tar --exclude='storage/cache' --exclude='storage/sessoes' -czf "$DESTINO/arquivos-$DIA.tar.gz.tmp" -C "$PASTA" storage
  mv "$DESTINO/arquivos-$DIA.tar.gz.tmp" "$DESTINO/arquivos-$DIA.tar.gz"
fi
# A configuração (.env) também entra, porque sem ela o banco restaurado não abre.
[ -f "$PASTA/.env" ] && install -m 600 "$PASTA/.env" "$DESTINO/env-$DIA"

find "$DESTINO" \( -name "$BANCO-*.sql.gz" -o -name 'arquivos-*.tar.gz' -o -name 'env-*' \) -mtime +14 -delete

#!/usr/bin/env bash
# Cópia do banco, compactada, uma por dia. Guarda as dos últimos 14 dias.
# Restaurar:  gunzip -c /var/backups/pecuaria/pecuaria-AAAA-MM-DD.sql.gz | mysql -u root pecuaria
set -euo pipefail
DESTINO="/var/backups/pecuaria"
mkdir -p "$DESTINO"
chmod 700 "$DESTINO"
ARQ="$DESTINO/pecuaria-$(date +%F).sql.gz"
mysqldump -u root --single-transaction --routines --triggers pecuaria | gzip -9 > "$ARQ.tmp"
mv "$ARQ.tmp" "$ARQ"
find "$DESTINO" -name 'pecuaria-*.sql.gz' -mtime +14 -delete

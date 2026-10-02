#!/bin/sh
set -eu
cd /opt/journal
umask 077
mkdir -p backups
stamp=$(date -u +%Y%m%d-%H%M%S)
docker compose exec -T postgres pg_dump -U journal -d journal --no-owner --no-acl -Fc > "backups/journal-$stamp.dump"
tar -czf "backups/uploads-$stamp.tar.gz" uploads
find backups -maxdepth 1 -type f \( -name 'journal-*.dump' -o -name 'uploads-*.tar.gz' \) -mtime +7 -delete

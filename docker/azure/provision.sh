#!/bin/sh
set -eu
cd /opt/journal
umask 077
mkdir -p source uploads
# Credentials are generated on the destination and never logged.
python3 - <<'PY'
from pathlib import Path
import secrets,base64
if Path('.env').exists(): raise SystemExit('Production environment already exists; refusing to replace secrets.')
values={'APP_NAME':'"Singapore Journal of Cardiology"','APP_ENV':'production','APP_DEBUG':'false','APP_KEY':'base64:'+base64.b64encode(secrets.token_bytes(32)).decode(),'APP_URL':'https://journal-sjc-bb469c5c.centralindia.cloudapp.azure.com','APP_FORCE_HTTPS':'true','TRUSTED_PROXIES':'*','DB_CONNECTION':'pgsql','DB_HOST':'postgres','DB_PORT':'5432','DB_DATABASE':'journal','DB_USERNAME':'journal','DB_PASSWORD':secrets.token_urlsafe(36),'CACHE_STORE':'database','SESSION_DRIVER':'database','SESSION_SECURE_COOKIE':'true','QUEUE_CONNECTION':'database','MAIL_MAILER':'log','LOG_CHANNEL':'stderr','RUN_MIGRATIONS':'false','LOCAL_ADMIN_BYPASS_ENABLED':'false','LOCAL_AUTHOR_BYPASS_ENABLED':'false','LOCAL_EDITOR_BYPASS_ENABLED':'false','LOCAL_REVIEWER_BYPASS_ENABLED':'false','GOOGLE_ENABLED':'false'}
Path('.env').write_text(''.join(f'{k}={v}\n' for k,v in values.items()))
Path('admin-password').write_text(secrets.token_urlsafe(24))
PY
(umask 022; tar -xzf journal-source.tar.gz -C source)
mkdir -p source/storage/framework/cache source/storage/framework/sessions source/storage/framework/views source/storage/logs source/storage/app
cp source/docker/azure/compose.yml compose.yml
cp source/docker/azure/prepare-production.php prepare-production.php
printf '%s\n' 'journal-sjc-bb469c5c.centralindia.cloudapp.azure.com {' ' reverse_proxy app:8080' '}' > Caddyfile
tar -xzf uploads.tar.gz -C uploads
sudo chown -R 82:82 uploads
sudo docker build --target azure -t journal-azure:production source > build.log 2>&1

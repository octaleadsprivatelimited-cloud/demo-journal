#!/bin/sh
set -eu
cd /opt/journal
sudo docker compose up -d postgres
until sudo docker compose exec -T postgres pg_isready -U journal -d journal >/dev/null 2>&1; do sleep 2; done
sudo docker compose exec -T postgres pg_restore -U journal -d journal --no-owner --no-acl --exit-on-error < journal.dump
# Prepare records before the web service is exposed.
sudo docker compose run --rm --no-deps -v /opt/journal/admin-password:/run/journal-admin-password:ro -v /opt/journal/prepare-production.php:/tmp/prepare-production.php:ro --user root app php /tmp/prepare-production.php
sudo docker compose run --rm --no-deps app php artisan migrate --force
sudo docker compose up -d
printf '%s\n' '* * * * * root cd /opt/journal && docker compose exec -T app php artisan schedule:run >> /var/log/journal-scheduler.log 2>&1' | sudo tee /etc/cron.d/journal-scheduler >/dev/null
sudo chmod 644 /etc/cron.d/journal-scheduler

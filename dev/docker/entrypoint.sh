#!/bin/bash
# Bootstraps a Craft 5 + Commerce project in /app on first start (create-project,
# install, require the bind-mounted plugin from /plugin, seed test content),
# then execs CMD.
set -euo pipefail
cd /app

STAMP=/app/storage/.slug-to-title-dev-ready

if [ ! -f composer.json ]; then
    echo "==> Creating Craft 5 project in /app"
    composer create-project "craftcms/craft:^5" /tmp/craft-proj --no-interaction --no-scripts --no-install
    cp -a /tmp/craft-proj/. /app/ && rm -rf /tmp/craft-proj
    if [ -f composer.json.default ]; then mv composer.json.default composer.json; fi
    cp -n .env.example.dev .env 2>/dev/null || true
    composer config repositories.slug-to-title '{"type":"path","url":"/plugin","options":{"symlink":true}}'
    composer config --no-plugins allow-plugins.craftcms/plugin-installer true
    composer config --no-plugins allow-plugins.yiisoft/yii2-composer true
    composer config minimum-stability dev
    composer config prefer-stable true
    composer require "craftcms/cms:^5.0" "craftcms/commerce:^5.0" "arifje/craft-slug-to-title:*@dev" --no-interaction --no-progress
fi

cp -f /scripts/app.web.php /app/config/app.web.php

if [ ! -d vendor ]; then
    echo "==> composer install"
    composer install --no-interaction --no-progress
fi

echo "==> Waiting for database ${CRAFT_DB_SERVER}"
for i in $(seq 1 60); do
    if mysqladmin ping --skip-ssl -h"${CRAFT_DB_SERVER}" -u"${CRAFT_DB_USER}" -p"${CRAFT_DB_PASSWORD}" --silent 2>/dev/null; then break; fi
    sleep 2
done

if [ ! -f "$STAMP" ]; then
    if ! php craft install/check >/dev/null 2>&1; then
        echo "==> Installing Craft 5"
        php craft install \
            --interactive=0 \
            --email="${DEV_ADMIN_EMAIL:-admin@example.com}" \
            --username="${DEV_ADMIN_USERNAME:-admin}" \
            --password="${DEV_ADMIN_PASSWORD:-password}" \
            --siteName="Slug to Title Dev" \
            --siteUrl="${PRIMARY_SITE_URL}" \
            --language="en-US"
    fi
    echo "==> Installing plugins"
    php craft plugin/install commerce || true
    php craft plugin/install slug-to-title || true
    echo "==> Seeding test content"
    php /scripts/seed.php
    # The seed script never ends a request, so write the project config YAML explicitly
    php craft project-config/write
    mkdir -p /app/storage && touch "$STAMP"
fi

mkdir -p /app/storage /app/web/cpresources
exec "$@"

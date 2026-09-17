#!/usr/bin/env bash
set -euo pipefail

mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache database
[ -f .env ] || cp .env.example .env
[ -f database/database.sqlite ] || touch database/database.sqlite

composer install
php artisan key:generate
php artisan migrate

echo "BasKarwaDo API ready. Run: php artisan serve"

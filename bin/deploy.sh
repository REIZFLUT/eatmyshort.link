#!/usr/bin/env bash
# Update an existing installation in place. Run as the user that owns the files.
set -euo pipefail
cd "$(dirname "$0")/.."

git pull --ff-only
composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
php bin/console asset-map:compile
php bin/console cache:clear
echo "Deployed $(git rev-parse --short HEAD)."

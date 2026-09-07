#!/bin/sh
set -e

# Migrations auto sur MySQL Aiven
php bin/console doctrine:migrations:migrate --no-interaction || true

# Préchauffage du cache Symfony
php bin/console cache:clear --no-interaction || true
php bin/console cache:warmup --no-interaction || true

# Lancer PHP-FPM en tâche de fond + Nginx au premier plan
php-fpm -D
exec nginx -g "daemon off;"
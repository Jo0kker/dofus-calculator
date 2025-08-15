#!/bin/sh
set -e

# Attendre que la base de données soit prête
echo "Waiting for database..."
while ! nc -z ${DB_HOST:-localhost} ${DB_PORT:-5432}; do
    sleep 1
done
echo "Database is ready!"

# Générer la clé d'application si elle n'existe pas
if [ -z "$APP_KEY" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Lancer les migrations
echo "Running migrations..."
php artisan migrate --force

# Clear and cache configurations
echo "Optimizing application..."
php artisan config:clear

# Debug AVANT cache
echo "Variables d'env avant cache:"
php artisan tinker --execute="echo config('app.url');"

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Debug APRÈS cache
echo "Variables d'env après cache:"
php artisan tinker --execute="echo config('app.url');"

# Storage link
php artisan storage:link 2>/dev/null || true

# Set permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

echo "Application ready!"

# Start supervisord
exec "$@"

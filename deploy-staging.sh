#!/bin/sh
set -e

echo "🎉 Deploying application..."

# Pull the latest changes from GitHub
echo "Pulling latest changes from GitHub..."
git pull origin main || { echo "Git pull failed!"; exit 1; }

# Create log file if doesn't exists
if [ ! -f storage/logs/laravel.log ]; then
    touch storage/logs/laravel.log
    chmod 777 storage/logs/laravel.log
fi
# Create bootstrap/cache directory if doesn't exists
if [ ! -d "bootstrap/cache" ]; then
    mkdir bootstrap/cache
    chmod -R 777 bootstrap/cache
fi

# Install dependencies
composer install --optimize-autoloader --no-dev -q

# Migrate database
php artisan migrate --force

# node build
npm run build

# Clear and cache configuration, routes, and views
echo "Clearing and caching configurations..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

#queue restart
php artisan queue:restart

echo "Deployment complete!"

exit 0
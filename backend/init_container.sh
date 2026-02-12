#!/bin/bash

# Navigate to app root
cd /home/site/wwwroot

# Create storage directories if they don't exist
mkdir -p storage/logs storage/framework/{cache,sessions,views}

# Set permissions
chmod -R 777 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Install dependencies if needed
if [ ! -d "vendor" ]; then
    composer install --no-dev --optimize-autoloader
fi

# Clear caches
php artisan config:clear || true
php artisan cache:clear || true
php artisan view:clear || true

# Final permissions
chmod -R 755 storage bootstrap/cache

echo "Application ready"

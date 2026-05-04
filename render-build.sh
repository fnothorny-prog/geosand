#!/usr/bin/env bash
# Install Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Install dependencies
composer install --no-dev --optimize-autoloader

# Generate app key
php artisan key:generate --force

echo "Build completed successfully!"

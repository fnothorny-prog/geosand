#!/bin/bash

# Clear config cache so Laravel reads fresh environment variables
php /var/www/html/artisan config:clear
php /var/www/html/artisan cache:clear

# Create storage symlink
php /var/www/html/artisan storage:link || true

# Run migrations
php /var/www/html/artisan migrate --force

# Start Apache
apache2-foreground

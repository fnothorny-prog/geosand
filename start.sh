#!/bin/bash

# Clear config cache so Laravel reads fresh environment variables
php /var/www/html/artisan config:clear
php /var/www/html/artisan cache:clear

# Start Apache
apache2-foreground

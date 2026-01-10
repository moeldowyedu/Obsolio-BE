#!/bin/bash
cd /home/obsolio/htdocs/api.obsolio.com
composer install --no-dev --optimize-autoloader
chown -R obsolio:obsolio storage bootstrap/cache
su - obsolio -c "cd /home/obsolio/htdocs/api.obsolio.com && php artisan optimize"
systemctl restart php8.4-fpm
echo "Deployment complete!"

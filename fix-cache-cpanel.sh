#!/bin/bash

#######################################################
# Fix Laravel Cache Issue on cPanel
#######################################################

echo "Fixing Laravel configuration cache issue..."

# Clear all cached configs (don't use artisan cache:clear as it tries to access DB)
rm -f bootstrap/cache/config.php
rm -f bootstrap/cache/routes.php
rm -f bootstrap/cache/packages.php
rm -f bootstrap/cache/services.php

# Clear view cache
rm -rf storage/framework/views/*

# Clear cache files
rm -rf storage/framework/cache/data/*

echo "Cache files cleared manually!"
echo ""
echo "Now run these commands:"
echo "  php artisan config:cache"
echo "  php artisan route:cache"
echo "  php artisan view:cache"

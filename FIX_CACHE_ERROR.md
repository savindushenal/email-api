# 🔧 Fixing Cache Error on cPanel

## Problem
```
SQLSTATE[28000] [1045] Access denied for user 'forge'@'localhost'
```

This happens because Laravel cached the default config before you set up your `.env` file.

## Quick Fix (Run on cPanel via SSH)

### Option 1: Manual Cache Clear
```bash
cd ~/public_html/email-api

# Remove cached config files
rm -f bootstrap/cache/config.php
rm -f bootstrap/cache/routes.php
rm -f bootstrap/cache/packages.php
rm -f bootstrap/cache/services.php

# Clear other caches
rm -rf storage/framework/views/*
rm -rf storage/framework/cache/data/*

# Rebuild cache
php artisan config:cache
```

### Option 2: Use the Fix Script
```bash
cd ~/public_html/email-api
chmod +x fix-cache-cpanel.sh
./fix-cache-cpanel.sh
php artisan config:cache
```

## Verify .env Configuration

Make sure your `.env` file has:
```env
CACHE_STORE=file
# NOT: CACHE_DRIVER=database
```

If you want to use database cache, first create the cache table:
```bash
php artisan cache:table
php artisan migrate
```

## Prevention

After deployment, always run in this order:
```bash
# 1. Clear old cache first
rm -f bootstrap/cache/config.php

# 2. Then rebuild
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Alternative: Change Cache Driver

Edit `.env`:
```env
CACHE_STORE=file  # Use file cache instead of database
```

Then:
```bash
php artisan config:clear
php artisan config:cache
```

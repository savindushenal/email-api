#!/bin/bash

#######################################################
# Manual Deployment Script for cPanel
# Run this script on your cPanel server via SSH
#######################################################

set -e

echo "======================================"
echo "Starting Laravel Email API Deployment"
echo "======================================"

# Configuration - Update these values
APP_DIR="/home/username/public_html/email-api"
REPO_URL="https://github.com/savindushenal/email-api.git"
BRANCH="main"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}Step 1: Checking if git repository exists...${NC}"
if [ -d "$APP_DIR/.git" ]; then
    echo -e "${GREEN}Git repository found. Pulling latest changes...${NC}"
    cd "$APP_DIR"
    
    # Enable maintenance mode
    php artisan down || echo "Already in maintenance mode"
    
    # Stash any local changes
    git stash
    
    # Pull latest changes
    git pull origin $BRANCH
    
    # Pop stashed changes if any
    git stash pop || echo "No stashed changes"
else
    echo -e "${YELLOW}Git repository not found. Cloning...${NC}"
    git clone -b $BRANCH $REPO_URL $APP_DIR
    cd "$APP_DIR"
fi

echo -e "${YELLOW}Step 2: Installing Composer dependencies...${NC}"
composer install --no-dev --optimize-autoloader --no-interaction

echo -e "${YELLOW}Step 3: Checking .env file...${NC}"
if [ ! -f ".env" ]; then
    echo -e "${RED}.env file not found. Copying from .env.example...${NC}"
    cp .env.example .env
    echo -e "${YELLOW}Please edit .env file with your configuration${NC}"
    php artisan key:generate
fi

echo -e "${YELLOW}Step 4: Setting permissions...${NC}"
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage/logs
chmod -R 775 storage/framework

echo -e "${YELLOW}Step 5: Running database migrations...${NC}"
php artisan migrate --force

echo -e "${YELLOW}Step 6: Clearing and caching configuration...${NC}"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

echo -e "${YELLOW}Step 7: Disabling maintenance mode...${NC}"
php artisan up

echo -e "${GREEN}======================================"
echo "Deployment completed successfully!"
echo "======================================${NC}"
echo ""
echo "Next steps:"
echo "1. Verify .env configuration"
echo "2. Test API: curl https://yourdomain.com/api/health"
echo "3. Seed database if needed: php artisan db:seed --class=EmailApiSeeder"

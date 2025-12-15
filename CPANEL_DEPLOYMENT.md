# 🚀 cPanel Deployment Guide

This guide covers **3 methods** to deploy your Laravel Email API to cPanel hosting.

---

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Method 1: GitHub Actions (Automated - Recommended)](#method-1-github-actions-automated)
3. [Method 2: Manual Git Pull via SSH](#method-2-manual-git-pull-via-ssh)
4. [Method 3: Manual FTP Upload](#method-3-manual-ftp-upload)
5. [Post-Deployment Setup](#post-deployment-setup)
6. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### cPanel Requirements
- ✅ PHP 8.2+ with required extensions
- ✅ MySQL/MariaDB database
- ✅ Composer installed
- ✅ SSH access (for automated deployments)
- ✅ Git installed (for git-based deployments)

### Your Local Setup
- ✅ GitHub repository created
- ✅ Code pushed to GitHub

---

## Method 1: GitHub Actions (Automated)

**Best for:** Automatic deployments on every push

### Step 1: Enable SSH Access on cPanel

1. Log into cPanel
2. Go to **Security → SSH Access**
3. Click **Manage SSH Keys**
4. Generate or import SSH key
5. Authorize the key

### Step 2: Setup GitHub Secrets

Go to your GitHub repository → **Settings → Secrets and variables → Actions**

Add these secrets:

| Secret Name | Description | Example |
|-------------|-------------|---------|
| `CPANEL_SSH_HOST` | Your cPanel server | `yourserver.com` or `123.45.67.89` |
| `CPANEL_SSH_USERNAME` | cPanel username | `cpanelusername` |
| `CPANEL_SSH_PASSWORD` | cPanel password | `your_password` |
| `CPANEL_SSH_PORT` | SSH port (usually 22) | `22` |
| `CPANEL_APP_PATH` | Full path to app | `/home/username/public_html/email-api` |

**For FTP deployment (alternative):**

| Secret Name | Description |
|-------------|-------------|
| `CPANEL_FTP_SERVER` | FTP server | `ftp.yourdomain.com` |
| `CPANEL_FTP_USERNAME` | FTP username |
| `CPANEL_FTP_PASSWORD` | FTP password |
| `CPANEL_FTP_PATH` | FTP path | `/public_html/email-api/` |

### Step 3: Choose Workflow

We provide 2 GitHub Actions workflows:

#### Option A: Git Pull Method (Recommended)
**File:** `.github/workflows/deploy-cpanel-git.yml`

✅ **Pros:**
- Faster deployments
- Smaller bandwidth usage
- Maintains git history on server

❌ **Requires:**
- Git installed on cPanel
- Repository cloned on server first

#### Option B: FTP Upload Method
**File:** `.github/workflows/deploy-cpanel.yml`

✅ **Pros:**
- Works without git on server
- No initial setup needed

❌ **Cons:**
- Slower for large files
- Uploads entire project

### Step 4: Initial Server Setup (For Git Pull Method)

SSH into your cPanel server:

```bash
ssh username@yourserver.com
```

Clone the repository:

```bash
cd ~/public_html
git clone https://github.com/savindushenal/email-api.git
cd email-api
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
chmod -R 755 storage bootstrap/cache
```

### Step 5: Deploy

Push to GitHub and watch it deploy automatically:

```bash
git add .
git commit -m "Deploy to cPanel"
git push origin main
```

Monitor deployment:
- Go to **Actions** tab in GitHub
- Watch the deployment progress
- Check logs if any errors occur

---

## Method 2: Manual Git Pull via SSH

**Best for:** Manual control, no GitHub Actions

### Step 1: Initial Setup

```bash
# SSH into cPanel
ssh username@yourserver.com

# Clone repository
cd ~/public_html
git clone https://github.com/savindushenal/email-api.git
cd email-api

# Install dependencies
composer install --no-dev --optimize-autoloader

# Setup environment
cp .env.example .env
nano .env  # Edit with your settings
php artisan key:generate

# Set permissions
chmod -R 755 storage bootstrap/cache

# Run migrations
php artisan migrate --force

# Seed database
php artisan db:seed --class=EmailApiSeeder
```

### Step 2: Deploy Updates

Use the deployment script:

```bash
cd ~/public_html/email-api
chmod +x deploy-cpanel.sh
./deploy-cpanel.sh
```

Or manually:

```bash
cd ~/public_html/email-api
php artisan down
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

---

## Method 3: Manual FTP Upload

**Best for:** No SSH access available

### Step 1: Prepare Files Locally

```bash
# Install production dependencies
composer install --no-dev --optimize-autoloader

# Clear development caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### Step 2: Upload via FTP

1. Connect to cPanel FTP (use FileZilla or similar)
2. Navigate to `public_html/email-api`
3. Upload these directories/files:
   - `app/`
   - `bootstrap/`
   - `config/`
   - `database/`
   - `public/`
   - `routes/`
   - `storage/` (empty structure only)
   - `vendor/` (if composer not available on server)
   - `artisan`
   - `composer.json`
   - `.env.example`
   - All `.md` documentation files

4. **DO NOT upload:**
   - `.env` (create on server)
   - `node_modules/`
   - `.git/`
   - `tests/`

### Step 3: Setup via cPanel Terminal

```bash
cd ~/public_html/email-api
cp .env.example .env
nano .env  # Edit configuration
php artisan key:generate
php artisan migrate --force
chmod -R 755 storage bootstrap/cache
```

---

## Post-Deployment Setup

### 1. Configure .env File

```bash
nano .env
```

Update these values:

```env
APP_NAME="Email API"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_HOST=localhost
DB_DATABASE=your_database_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

MAIL_MAILER=sendmail
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
```

### 2. Point Domain to Application

#### Option A: Main Domain
Edit `.htaccess` in `public_html`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ email-api/public/$1 [L]
</IfModule>
```

#### Option B: Subdomain
Create subdomain in cPanel pointing to `/home/username/public_html/email-api/public`

### 3. Set File Permissions

```bash
cd ~/public_html/email-api
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage/logs
```

### 4. Run Migrations & Seed

```bash
php artisan migrate --force
php artisan db:seed --class=EmailApiSeeder
```

### 5. Cache Configuration

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 6. Test API

```bash
curl https://yourdomain.com/api/health
```

Expected response:
```json
{
    "status": "healthy",
    "timestamp": "2025-12-15T10:30:00Z",
    "database": "connected",
    "laravel_version": "11.47.0"
}
```

---

## Troubleshooting

### 500 Internal Server Error

**Check Laravel logs:**
```bash
tail -f storage/logs/laravel.log
```

**Common fixes:**
```bash
# Fix permissions
chmod -R 755 storage bootstrap/cache

# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Regenerate key
php artisan key:generate
```

### Database Connection Issues

**Check database credentials:**
```bash
php artisan tinker
>>> DB::connection()->getPdo();
```

**Verify MySQL connection from cPanel:**
- Remote MySQL must allow connections
- Database user has proper privileges

### Composer Not Found

**Use full path:**
```bash
/usr/local/bin/composer install --no-dev --optimize-autoloader
```

Or install in user directory:
```bash
cd ~
curl -sS https://getcomposer.org/installer | php
alias composer='php ~/composer.phar'
```

### Git Not Available

**Request Git installation from hosting provider** or use FTP method.

### SSH Connection Failed

**Check SSH access:**
- Verify SSH is enabled in cPanel
- Check port (usually 22, sometimes 2222)
- Verify username/password
- Try key-based authentication

### File Permission Errors

**Reset all permissions:**
```bash
cd ~/public_html/email-api
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod -R 775 storage bootstrap/cache
```

### GitHub Actions Failing

**Check workflow logs:**
1. Go to Actions tab
2. Click failed workflow
3. Expand steps to see errors

**Common issues:**
- Wrong secrets configuration
- SSH connection timeout
- Path doesn't exist on server

---

## Automated Deployment Checklist

- [ ] SSH access enabled on cPanel
- [ ] Git installed on server
- [ ] Repository cloned to cPanel
- [ ] GitHub secrets configured
- [ ] `.env` file created and configured
- [ ] Database created and configured
- [ ] File permissions set correctly
- [ ] Initial migration run
- [ ] API health check passes
- [ ] Workflow file committed to `.github/workflows/`
- [ ] Test deployment successful

---

## Security Recommendations

1. **Use SSH keys instead of passwords**
2. **Set `APP_DEBUG=false` in production**
3. **Use strong `APP_KEY`**
4. **Restrict database access**
5. **Use HTTPS (SSL certificate)**
6. **Enable rate limiting**
7. **Keep dependencies updated**
8. **Monitor error logs regularly**

---

## Quick Commands Reference

```bash
# Deploy manually
./deploy-cpanel.sh

# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader

# Run migrations
php artisan migrate --force

# Cache everything
php artisan optimize

# Clear everything
php artisan optimize:clear

# View logs
tail -f storage/logs/laravel.log

# Check artisan version
php artisan --version

# Test database
php artisan tinker
>>> DB::connection()->getPdo();
```

---

## Need Help?

**Check logs:**
- Laravel: `storage/logs/laravel.log`
- cPanel: Error logs in cPanel dashboard
- GitHub: Actions tab for workflow logs

**Resources:**
- [Laravel Deployment Docs](https://laravel.com/docs/11.x/deployment)
- [cPanel Documentation](https://docs.cpanel.net/)
- Project README: `README.md`

---

**Your API should now be deployed and running on cPanel!** 🎉

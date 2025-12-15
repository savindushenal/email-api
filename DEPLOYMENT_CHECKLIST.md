# 📋 Production Deployment Checklist

## Pre-Deployment

### Code Preparation
- [ ] All dependencies installed: `composer install --optimize-autoloader --no-dev`
- [ ] All migrations tested
- [ ] All routes tested
- [ ] API documentation reviewed
- [ ] Security middleware configured

### Environment Configuration
- [ ] `.env` file created from `.env.example`
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` generated: `php artisan key:generate`
- [ ] `APP_URL` set to production URL

### Database Setup
- [ ] Database created
- [ ] Database credentials configured in `.env`
- [ ] Connection tested
- [ ] Migrations run: `php artisan migrate --force`
- [ ] Initial data seeded (if needed)

### Mail Configuration
- [ ] Mail driver configured (sendmail/exim for cPanel)
- [ ] `MAIL_FROM_ADDRESS` set to valid email
- [ ] `MAIL_FROM_NAME` set
- [ ] Test email sent and received
- [ ] SPF/DKIM records configured (for better deliverability)

## Server Setup (cPanel)

### File Upload
- [ ] All files uploaded via FTP/SFTP
- [ ] `.git` folder excluded (if applicable)
- [ ] `vendor` folder uploaded or installed via SSH

### Permissions
- [ ] `storage/` directory: `chmod -R 775`
- [ ] `bootstrap/cache/` directory: `chmod -R 775`
- [ ] Owner set correctly: `chown -R username:username storage bootstrap/cache`

### Document Root
- [ ] Domain points to `/public` folder
- [ ] `.htaccess` file present in `/public`
- [ ] URL rewriting works

### PHP Configuration
- [ ] PHP version 8.1 or higher
- [ ] Required extensions enabled:
  - [ ] OpenSSL
  - [ ] PDO
  - [ ] Mbstring
  - [ ] Tokenizer
  - [ ] XML
  - [ ] Ctype
  - [ ] JSON
  - [ ] BCMath

## Security

### Laravel Security
- [ ] `APP_DEBUG=false` in production
- [ ] API rate limiting configured
- [ ] CSRF protection enabled (if needed)
- [ ] XSS protection in templates
- [ ] SQL injection protection (using Eloquent)

### API Security
- [ ] API keys stored securely
- [ ] HTTPS/SSL certificate installed
- [ ] CORS configured (if needed)
- [ ] Rate limiting tested: 60 requests/minute
- [ ] Per-domain rate limits configured

### Access Control
- [ ] Domain validation working
- [ ] API key authentication tested
- [ ] Unauthorized access blocked
- [ ] Error messages don't expose sensitive info

## Performance Optimization

### Laravel Optimization
- [ ] Config cached: `php artisan config:cache`
- [ ] Routes cached: `php artisan route:cache`
- [ ] Views cached: `php artisan view:cache`
- [ ] Composer optimized: `composer install --optimize-autoloader --no-dev`

### Database Optimization
- [ ] Database indexes created (migrations handle this)
- [ ] Query performance tested
- [ ] Connection pooling configured (if applicable)

### Caching (Optional)
- [ ] Redis/Memcached configured (if using)
- [ ] Cache driver set in `.env`
- [ ] Cache tested

## Testing

### Functional Testing
- [ ] Health check endpoint: `GET /api/health`
- [ ] Send email with valid API key
- [ ] Send email with invalid API key (should fail)
- [ ] Send email to invalid domain (should fail)
- [ ] Send email with missing template (should fail)
- [ ] Rate limiting tested (send 61 requests in 1 minute)

### Email Delivery Testing
- [ ] OTP template sent successfully
- [ ] Welcome template sent successfully
- [ ] Invoice template sent successfully
- [ ] Email received in inbox (not spam)
- [ ] From address displays correctly
- [ ] Subject line renders correctly
- [ ] HTML renders correctly in email clients

### Error Handling
- [ ] Missing API key returns 401
- [ ] Invalid API key returns 401
- [ ] Domain mismatch returns 403
- [ ] Validation errors return 422
- [ ] Template errors logged and return 500

## Monitoring & Logging

### Logging Setup
- [ ] Log files writable: `storage/logs/laravel.log`
- [ ] Log rotation configured
- [ ] Error logging tested
- [ ] Email sending logged to database

### Monitoring
- [ ] Email logs table populated correctly
- [ ] Failed emails logged with error messages
- [ ] Statistics endpoint works
- [ ] Disk space monitoring (for logs)

## Backup Strategy

### Database Backup
- [ ] Automated database backup configured
- [ ] Backup tested and restorable
- [ ] Backup schedule: daily/weekly
- [ ] Backup retention policy set

### File Backup
- [ ] Application files backed up
- [ ] `.env` file backed up securely
- [ ] Email templates backed up
- [ ] Backup location secured

## Documentation

### Internal Documentation
- [ ] README.md reviewed
- [ ] QUICKSTART.md available
- [ ] API_RESPONSES.md available
- [ ] Environment variables documented

### API Documentation
- [ ] Postman collection available
- [ ] API endpoints documented
- [ ] Request/response examples provided
- [ ] Error codes documented

### Operational Documentation
- [ ] Deployment process documented
- [ ] Rollback process documented
- [ ] Common issues and solutions documented
- [ ] Support contact information available

## Post-Deployment

### Initial Setup
- [ ] Create production domains
- [ ] Generate and store API keys securely
- [ ] Create email templates for all domains
- [ ] Configure rate limits per domain

### Verification
- [ ] All domains active
- [ ] All templates active
- [ ] API keys working
- [ ] Email delivery confirmed

### Communication
- [ ] API endpoint URL shared with clients
- [ ] API keys distributed securely
- [ ] Documentation shared
- [ ] Support channels established

## Maintenance Plan

### Regular Tasks
- [ ] Monitor email logs daily
- [ ] Check error logs weekly
- [ ] Review rate limit usage
- [ ] Backup verification monthly

### Updates
- [ ] Laravel security updates plan
- [ ] Dependency updates schedule
- [ ] PHP version upgrade plan
- [ ] Database maintenance schedule

### Scaling Considerations
- [ ] Queue workers for async sending
- [ ] Load balancer configuration (if needed)
- [ ] Database replication (if needed)
- [ ] CDN for static assets (if applicable)

## Emergency Procedures

### Incident Response
- [ ] Emergency contact list prepared
- [ ] Rollback procedure documented
- [ ] Service status page (if applicable)
- [ ] Communication template for outages

### Recovery
- [ ] Database restore procedure tested
- [ ] Application restore procedure tested
- [ ] Configuration backup accessible
- [ ] RTO/RPO defined

## Compliance & Legal

### Data Protection
- [ ] Email logs retention policy
- [ ] GDPR compliance (if applicable)
- [ ] Data encryption for sensitive fields
- [ ] Privacy policy updated

### Email Compliance
- [ ] CAN-SPAM compliance (US)
- [ ] GDPR compliance (EU)
- [ ] Unsubscribe mechanism (if required)
- [ ] Terms of service updated

## Final Verification

### Pre-Launch Checklist
- [ ] All tests passing
- [ ] No critical errors in logs
- [ ] Performance benchmarks met
- [ ] Security scan completed
- [ ] Load testing completed (if required)

### Launch Checklist
- [ ] DNS configured correctly
- [ ] SSL certificate valid
- [ ] All services running
- [ ] Monitoring active
- [ ] Team notified

### Post-Launch Checklist (First 24 Hours)
- [ ] Monitor error logs continuously
- [ ] Check email delivery rates
- [ ] Verify API response times
- [ ] Monitor server resources
- [ ] Be ready for quick fixes

## Success Criteria

- [ ] ✅ API responding with < 200ms average
- [ ] ✅ Email delivery rate > 95%
- [ ] ✅ Zero security vulnerabilities
- [ ] ✅ Error rate < 1%
- [ ] ✅ Uptime > 99.9%

---

## Notes

**Date Deployed:** _______________  
**Deployed By:** _______________  
**Version:** 1.0.0  
**Environment:** Production  

**API Endpoint:** _______________  
**Database Server:** _______________  
**Backup Location:** _______________  

**Emergency Contacts:**
- Technical Lead: _______________
- Database Admin: _______________
- System Admin: _______________

---

## Sign-Off

- [ ] Development Team Lead: _______________ Date: _______
- [ ] Security Review: _______________ Date: _______
- [ ] Operations Team: _______________ Date: _______
- [ ] Project Manager: _______________ Date: _______

**Status:** ⬜ Ready for Deployment | ⬜ Deployed | ⬜ Issues Found

**Notes:**
_____________________________________
_____________________________________
_____________________________________

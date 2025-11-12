# FILE 09: DEPLOYMENT & CI/CD PIPELINE

## VQ CHECKOUT FOR WOO - CONTINUOUS INTEGRATION & DELIVERY

**Version:** 3.0.0-OPTIMIZED  
**Date:** November 5, 2025  
**Status:** ✅ PRODUCTION-READY

---

## I. OVERVIEW - TỔNG QUAN

Pipeline tự động hóa:
- ✅ **Code Quality** checks (PHPCS, PHPStan, ESLint)
- ✅ **Automated Testing** (Unit, Integration, E2E)
- ✅ **Security Scanning** (SAST, Dependency check)
- ✅ **Build & Package** (.zip artifact)
- ✅ **Deployment** (Staging → Production)
- ✅ **Monitoring** (Sentry, New Relic)

---

## II. CI/CD ARCHITECTURE

```
┌─────────────────────────────────────────────────────────────┐
│ DEVELOPER                                                    │
│ - git commit                                                 │
│ - git push                                                   │
└────────────┬────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────┐
│ GITHUB ACTIONS (Triggered)                                  │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ Stage 1: Code Quality (2-3 min)                         │ │
│ │ - PHPCS (WordPress Coding Standards)                    │ │
│ │ - PHPStan (Static Analysis Level 5)                    │ │
│ │ - ESLint (JavaScript)                                   │ │
│ └─────────────────────────────────────────────────────────┘ │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ Stage 2: Security Scan (3-5 min)                        │ │
│ │ - Composer dependency check                             │ │
│ │ - npm audit                                             │ │
│ │ - SAST (SonarQube/Snyk)                                │ │
│ └─────────────────────────────────────────────────────────┘ │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ Stage 3: Tests (5-8 min)                                │ │
│ │ - PHPUnit (Unit + Integration)                          │ │
│ │ - Playwright (E2E)                                      │ │
│ │ - Coverage report → Codecov                             │ │
│ └─────────────────────────────────────────────────────────┘ │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ Stage 4: Build (1-2 min)                                │ │
│ │ - npm run build (if needed)                             │ │
│ │ - Generate .pot file                                    │ │
│ │ - Create .zip artifact                                  │ │
│ └─────────────────────────────────────────────────────────┘ │
└────────────┬────────────────────────────────────────────────────┘
             │ ✅ ALL PASS
             ▼
┌─────────────────────────────────────────────────────────────┐
│ STAGING DEPLOYMENT (Auto on develop branch)                 │
│ - Deploy to staging.site.com                                │
│ - Run smoke tests                                            │
│ - Notify Slack channel                                       │
└────────────┬────────────────────────────────────────────────────┘
             │ ✅ SMOKE TESTS PASS
             ▼
┌─────────────────────────────────────────────────────────────┐
│ MANUAL APPROVAL (Production Gate)                           │
│ - Tech Lead reviews                                          │
│ - Approves deployment                                        │
└────────────┬────────────────────────────────────────────────────┘
             │ ✅ APPROVED
             ▼
┌─────────────────────────────────────────────────────────────┐
│ PRODUCTION DEPLOYMENT                                        │
│ - Backup database                                            │
│ - Enable maintenance mode                                    │
│ - Deploy new version                                         │
│ - Run migrations                                             │
│ - Smoke tests                                                │
│ - Disable maintenance mode                                   │
│ - Send notifications                                         │
└──────────────────────────────────────────────────────────────┘
```

---

## III. GITHUB ACTIONS WORKFLOWS

### 3.1. Main CI Workflow

```yaml
# .github/workflows/ci.yml
name: CI Pipeline

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main, develop]

jobs:
  code-quality:
    name: Code Quality Checks
    runs-on: ubuntu-latest
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v3
        
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          tools: composer
          
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
        
      - name: PHPCS
        run: |
          vendor/bin/phpcs --standard=WordPress \
            --ignore=vendor,tests,node_modules \
            --extensions=php \
            src/
          
      - name: PHPStan
        run: vendor/bin/phpstan analyse src/ --level 5
        
      - name: Setup Node
        uses: actions/setup-node@v3
        with:
          node-version: '16'
          
      - name: Install npm dependencies
        run: npm ci
        
      - name: ESLint
        run: npm run lint
  
  security-scan:
    name: Security Scanning
    runs-on: ubuntu-latest
    needs: code-quality
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v3
        
      - name: Composer Security Check
        run: |
          composer require --dev enlightn/security-checker
          vendor/bin/security-checker security:check
          
      - name: npm Audit
        run: npm audit --production
        
      - name: SonarQube Scan
        uses: sonarsource/sonarqube-scan-action@master
        env:
          SONAR_TOKEN: ${{ secrets.SONAR_TOKEN }}
          SONAR_HOST_URL: ${{ secrets.SONAR_HOST_URL }}
  
  tests:
    name: Automated Tests
    runs-on: ubuntu-latest
    needs: security-scan
    
    services:
      mysql:
        image: mysql:5.7
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: wordpress_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v3
        
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          extensions: mbstring, xml, mysqli
          coverage: xdebug
          
      - name: Install dependencies
        run: composer install
        
      - name: Install WordPress Test Suite
        run: bash bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest
        
      - name: Run PHPUnit
        run: |
          vendor/bin/phpunit \
            --coverage-text \
            --coverage-clover=coverage.xml \
            --log-junit=test-results.xml
          
      - name: Upload Coverage to Codecov
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage.xml
          flags: phpunit
          
      - name: Setup Node
        uses: actions/setup-node@v3
        with:
          node-version: '16'
          
      - name: Install Playwright
        run: |
          npm ci
          npx playwright install --with-deps chromium
          
      - name: Run E2E Tests
        run: npm run test:e2e
        env:
          BASE_URL: http://localhost:8080
          
      - name: Upload E2E Results
        if: always()
        uses: actions/upload-artifact@v3
        with:
          name: playwright-results
          path: test-results/
          retention-days: 7
  
  build:
    name: Build Plugin
    runs-on: ubuntu-latest
    needs: tests
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v3
        
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          
      - name: Setup Node
        uses: actions/setup-node@v3
        with:
          node-version: '16'
          
      - name: Install dependencies
        run: |
          composer install --no-dev --optimize-autoloader
          npm ci
          
      - name: Build assets
        run: npm run build
        
      - name: Generate POT file
        run: wp i18n make-pot . languages/vq-checkout.pot
        
      - name: Create plugin package
        run: |
          mkdir -p build
          rsync -av --exclude-from='.distignore' . build/vq-checkout/
          cd build
          zip -r vq-checkout-${{ github.sha }}.zip vq-checkout/
          
      - name: Upload artifact
        uses: actions/upload-artifact@v3
        with:
          name: plugin-package
          path: build/vq-checkout-*.zip
          retention-days: 30
          
      - name: Create GitHub Release
        if: github.ref == 'refs/heads/main' && startsWith(github.ref, 'refs/tags/')
        uses: softprops/action-gh-release@v1
        with:
          files: build/vq-checkout-*.zip
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
```

### 3.2. Deployment Workflow

```yaml
# .github/workflows/deploy.yml
name: Deploy to Staging/Production

on:
  push:
    branches:
      - develop  # Auto-deploy to staging
  workflow_dispatch:
    inputs:
      environment:
        description: 'Environment to deploy to'
        required: true
        type: choice
        options:
          - staging
          - production

jobs:
  deploy-staging:
    name: Deploy to Staging
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/develop'
    environment:
      name: staging
      url: https://staging.site.com
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v3
        
      - name: Setup SSH
        uses: webfactory/ssh-agent@v0.7.0
        with:
          ssh-private-key: ${{ secrets.STAGING_SSH_KEY }}
          
      - name: Deploy to Staging
        run: |
          ssh ${{ secrets.STAGING_USER }}@${{ secrets.STAGING_HOST }} << 'EOF'
            cd /var/www/staging/wp-content/plugins
            
            # Backup current version
            if [ -d "vq-checkout" ]; then
              sudo tar -czf vq-checkout-backup-$(date +%Y%m%d%H%M%S).tar.gz vq-checkout/
            fi
            
            # Pull latest code
            cd vq-checkout
            git pull origin develop
            
            # Install dependencies
            composer install --no-dev --optimize-autoloader
            npm ci && npm run build
            
            # Run migrations
            wp vq migrate --allow-root
            
            # Clear caches
            wp cache flush --allow-root
            wp transient delete --all --allow-root
          EOF
          
      - name: Run Smoke Tests
        run: |
          curl -f https://staging.site.com/wp-json/vqcheckout/v1/health || exit 1
          
      - name: Notify Slack
        uses: slackapi/slack-github-action@v1.24.0
        with:
          payload: |
            {
              "text": "✅ VQ Checkout deployed to Staging",
              "blocks": [
                {
                  "type": "section",
                  "text": {
                    "type": "mrkdwn",
                    "text": "*Deployment to Staging Successful*\n\nCommit: `${{ github.sha }}`\nBranch: `develop`\nURL: https://staging.site.com"
                  }
                }
              ]
            }
        env:
          SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK_URL }}
  
  deploy-production:
    name: Deploy to Production
    runs-on: ubuntu-latest
    if: github.event.inputs.environment == 'production' || (github.ref == 'refs/heads/main' && startsWith(github.ref, 'refs/tags/'))
    environment:
      name: production
      url: https://site.com
    
    steps:
      - name: Checkout code
        uses: actions/checkout@v3
        
      - name: Setup SSH
        uses: webfactory/ssh-agent@v0.7.0
        with:
          ssh-private-key: ${{ secrets.PROD_SSH_KEY }}
          
      - name: Pre-deployment Checks
        run: |
          # Check if production is healthy
          curl -f https://site.com/wp-json/vqcheckout/v1/health || exit 1
          
      - name: Backup Production
        run: |
          ssh ${{ secrets.PROD_USER }}@${{ secrets.PROD_HOST }} << 'EOF'
            # Backup database
            wp db export /backups/db-backup-$(date +%Y%m%d%H%M%S).sql --allow-root
            
            # Backup plugin files
            cd /var/www/production/wp-content/plugins
            sudo tar -czf /backups/vq-checkout-$(date +%Y%m%d%H%M%S).tar.gz vq-checkout/
          EOF
          
      - name: Enable Maintenance Mode
        run: |
          ssh ${{ secrets.PROD_USER }}@${{ secrets.PROD_HOST }} \
            "wp maintenance-mode activate --allow-root"
          
      - name: Deploy to Production
        run: |
          ssh ${{ secrets.PROD_USER }}@${{ secrets.PROD_HOST }} << 'EOF'
            cd /var/www/production/wp-content/plugins/vq-checkout
            
            # Pull latest code (tagged release)
            git fetch --tags
            git checkout ${{ github.ref_name }}
            
            # Install dependencies
            composer install --no-dev --optimize-autoloader
            npm ci && npm run build
            
            # Run migrations
            wp vq migrate --allow-root
            
            # Clear caches
            wp cache flush --allow-root
            wp transient delete --all --allow-root
            
            # Warm cache
            wp vq cache warm --allow-root
          EOF
          
      - name: Run Post-deployment Tests
        run: |
          # Health check
          curl -f https://site.com/wp-json/vqcheckout/v1/health || exit 1
          
          # Test rate resolution
          curl -f https://site.com/wp-json/vqcheckout/v1/rates/test || exit 1
          
      - name: Disable Maintenance Mode
        if: always()
        run: |
          ssh ${{ secrets.PROD_USER }}@${{ secrets.PROD_HOST }} \
            "wp maintenance-mode deactivate --allow-root"
          
      - name: Rollback on Failure
        if: failure()
        run: |
          ssh ${{ secrets.PROD_USER }}@${{ secrets.PROD_HOST }} << 'EOF'
            # Restore from latest backup
            cd /backups
            LATEST_BACKUP=$(ls -t vq-checkout-*.tar.gz | head -1)
            cd /var/www/production/wp-content/plugins
            sudo rm -rf vq-checkout
            sudo tar -xzf /backups/$LATEST_BACKUP
            
            # Restore database if needed
            # wp db import /backups/db-backup-latest.sql --allow-root
            
            wp cache flush --allow-root
          EOF
          
      - name: Notify Team
        if: always()
        uses: slackapi/slack-github-action@v1.24.0
        with:
          payload: |
            {
              "text": "${{ job.status == 'success' && '✅' || '❌' }} VQ Checkout Production Deployment",
              "blocks": [
                {
                  "type": "section",
                  "text": {
                    "type": "mrkdwn",
                    "text": "*Deployment Status: ${{ job.status }}*\n\nVersion: `${{ github.ref_name }}`\nURL: https://site.com\nDeployed by: @${{ github.actor }}"
                  }
                }
              ]
            }
        env:
          SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK_URL }}
          
      - name: Create Sentry Release
        if: success()
        run: |
          curl -X POST https://sentry.io/api/0/organizations/${{ secrets.SENTRY_ORG }}/releases/ \
            -H "Authorization: Bearer ${{ secrets.SENTRY_AUTH_TOKEN }}" \
            -H "Content-Type: application/json" \
            -d '{"version":"${{ github.ref_name }}","projects":["vq-checkout"]}'
```

---

## IV. ENVIRONMENTS

### 4.1. Environment Configuration

```bash
# .env.development
WP_ENV=development
WP_DEBUG=true
VQ_DEBUG=true
SCRIPT_DEBUG=true

# .env.staging
WP_ENV=staging
WP_DEBUG=false
VQ_DEBUG=true
CACHE_ENABLED=true

# .env.production
WP_ENV=production
WP_DEBUG=false
VQ_DEBUG=false
CACHE_ENABLED=true
SENTRY_DSN=https://xxx@sentry.io/xxx
```

### 4.2. Environment Variables

```php
// wp-config.php
define('VQ_ENV', getenv('WP_ENV') ?: 'production');
define('VQ_DEBUG', getenv('VQ_DEBUG') === 'true');
define('VQ_CACHE_ENABLED', getenv('CACHE_ENABLED') === 'true');
define('VQ_SENTRY_DSN', getenv('SENTRY_DSN'));
```

---

## V. DEPLOYMENT CHECKLIST

### 5.1. Pre-deployment

- [ ] All tests pass (Unit, Integration, E2E)
- [ ] Code review completed (2+ approvers)
- [ ] Security scan clean (no vulnerabilities)
- [ ] Performance benchmarks met (p95 ≤ 20ms)
- [ ] Database migration tested on staging
- [ ] Rollback plan documented
- [ ] Monitoring dashboards configured
- [ ] Team notified (Slack announcement)

### 5.2. During Deployment

- [ ] Backup created (database + files)
- [ ] Maintenance mode enabled
- [ ] Code deployed
- [ ] Migrations run
- [ ] Caches cleared
- [ ] Smoke tests pass
- [ ] Maintenance mode disabled

### 5.3. Post-deployment

- [ ] Health check passes
- [ ] Error rate normal (< 0.1%)
- [ ] Performance metrics normal
- [ ] No critical logs
- [ ] User acceptance testing (sample checkouts)
- [ ] Team notified (deployment complete)
- [ ] Documentation updated (changelog)

---

## VI. ROLLBACK PROCEDURE

### 6.1. Automatic Rollback

```bash
#!/bin/bash
# scripts/rollback.sh

set -e

BACKUP_DIR="/backups"
PLUGIN_DIR="/var/www/production/wp-content/plugins/vq-checkout"

echo "🔄 Starting rollback..."

# 1. Enable maintenance mode
wp maintenance-mode activate --allow-root

# 2. Restore plugin files
LATEST_BACKUP=$(ls -t $BACKUP_DIR/vq-checkout-*.tar.gz | head -1)
echo "Restoring from: $LATEST_BACKUP"

rm -rf $PLUGIN_DIR
tar -xzf $LATEST_BACKUP -C $(dirname $PLUGIN_DIR)

# 3. Restore database (if needed)
# LATEST_DB_BACKUP=$(ls -t $BACKUP_DIR/db-backup-*.sql | head -1)
# wp db import $LATEST_DB_BACKUP --allow-root

# 4. Clear caches
wp cache flush --allow-root
wp transient delete --all --allow-root

# 5. Verify health
if curl -f https://site.com/wp-json/vqcheckout/v1/health; then
    echo "✅ Rollback successful"
else
    echo "❌ Rollback failed - manual intervention required"
    exit 1
fi

# 6. Disable maintenance mode
wp maintenance-mode deactivate --allow-root

echo "✅ Rollback complete"
```

---

## VII. MONITORING INTEGRATION

### 7.1. Sentry Error Tracking

```php
<?php
// Initialize Sentry
if (defined('VQ_SENTRY_DSN') && VQ_SENTRY_DSN) {
    \Sentry\init([
        'dsn' => VQ_SENTRY_DSN,
        'environment' => VQ_ENV,
        'release' => VQCHECKOUT_VERSION,
        'traces_sample_rate' => 0.1,
        'profiles_sample_rate' => 0.1
    ]);
    
    // Capture errors
    add_action('wp_error', function($error) {
        \Sentry\captureException($error);
    });
}
```

### 7.2. New Relic APM

```php
<?php
// New Relic integration
if (extension_loaded('newrelic')) {
    newrelic_set_appname('VQ Checkout - ' . VQ_ENV);
    
    // Custom transaction name
    add_action('woocommerce_calculate_totals', function() {
        newrelic_name_transaction('VQ/Checkout/CalculateTotals');
    });
    
    // Track rate resolution
    add_action('vq_rate_resolved', function($duration) {
        newrelic_custom_metric('Custom/VQ/RateResolution', $duration);
    });
}
```

---

## VIII. SUMMARY - TÓM TẮT

### ✅ CI/CD Pipeline

**Automated:**
- Code quality checks
- Security scanning
- Test execution
- Build & packaging
- Deployment to staging
- Smoke testing

**Manual Gates:**
- Production deployment approval
- Rollback decision

**Performance:**
- Full pipeline: ~15-20 minutes
- Deploy staging: ~5 minutes
- Deploy production: ~10 minutes

### ✅ Environments

- Development (local)
- Staging (auto-deploy from develop)
- Production (manual approval)

### ✅ Monitoring

- Sentry (errors)
- New Relic (performance)
- Custom metrics
- Slack notifications

---

**Document Owner:** DevOps Team  
**Last Updated:** 2025-11-05

---

**END OF DEPLOYMENT & CI/CD DOCUMENT**

*Automate everything, deploy confidently, monitor continuously.*

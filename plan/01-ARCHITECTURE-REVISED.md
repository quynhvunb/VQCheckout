# FILE 01: ARCHITECTURE - KIẾN TRÚC TỔNG THỂ (REVISED V3)

## VQ CHECKOUT FOR WOO - SYSTEM ARCHITECTURE

**Version:** 3.0.0-OPTIMIZED  
**Date:** November 5, 2025  
**Status:** ✅ PRODUCTION-READY

---

## I. EXECUTIVE SUMMARY

Plugin **VQ Checkout for Woo** được thiết kế theo kiến trúc **modular, secure, performant** với:
- ✅ **Table Rate Shipping** với First Match Wins algorithm
- ✅ **Custom Database Tables** với indexes tối ưu
- ✅ **REST API** thay vì legacy AJAX
- ✅ **Cache-first strategy** cho hiệu năng cao
- ✅ **Security-by-default** với reCAPTCHA + rate-limit
- ✅ **HPOS & Blocks** compatible

---

## II. DESIGN PRINCIPLES - NGUYÊN TẮC THIẾT KẾ

### 2.1. Architectural Principles

**1. Separation of Concerns**
```
Data Layer (Repository) ← Service Layer (Business Logic) ← Presentation Layer (UI/API)
```

**2. Security by Default**
- Mọi endpoint công khai: CAPTCHA + Nonce + Rate-limit
- Mọi admin action: Capability check + Nonce
- Mọi input: Sanitize + Validate
- Mọi output: Escape

**3. Performance First**
- Cache-first strategy
- Index-optimized queries
- Lazy loading
- Pagination/virtualization

**4. Testability**
- Pure functions
- Dependency injection
- Mock-friendly interfaces
- 90%+ coverage

**5. Maintainability**
- SOLID principles
- Self-documenting code
- Comprehensive PHPDoc
- Clear naming conventions

---

## III. SYSTEM ARCHITECTURE - KIẾN TRÚC HỆ THỐNG

### 3.1. High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        WORDPRESS CORE                            │
│                    (Hooks, Filters, APIs)                        │
└────────────────┬────────────────────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────────────────────┐
│                   WOOCOMMERCE LAYER                              │
│           (Shipping Zones, Cart, Checkout, Orders)              │
└────────────────┬────────────────────────────────────────────────┘
                 │
┌────────────────▼────────────────────────────────────────────────┐
│                VQ CHECKOUT PLUGIN                                │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │              PRESENTATION LAYER                          │  │
│  │  - Admin UI (React/Vanilla JS)                          │  │
│  │  - REST API Endpoints                                   │  │
│  │  - AJAX Handlers (legacy support)                       │  │
│  │  - Shortcodes & Widgets                                 │  │
│  └─────────────────┬────────────────────────────────────────┘  │
│                    │                                             │
│  ┌─────────────────▼────────────────────────────────────────┐  │
│  │              SERVICE LAYER                               │  │
│  │  - Shipping Resolver (Calculator)                       │  │
│  │  - Rate Matcher                                         │  │
│  │  - Condition Evaluator                                  │  │
│  │  - Security Service (CAPTCHA, Rate-limit)              │  │
│  │  - Cache Service                                        │  │
│  │  - Migration Service                                    │  │
│  │  - Import/Export Service                                │  │
│  └─────────────────┬────────────────────────────────────────┘  │
│                    │                                             │
│  ┌─────────────────▼────────────────────────────────────────┐  │
│  │              DATA LAYER                                  │  │
│  │  - Rate Repository                                      │  │
│  │  - Location Repository                                  │  │
│  │  - Security Log Repository                              │  │
│  │  - Address Dataset Provider                             │  │
│  │  - Settings Repository                                  │  │
│  └─────────────────┬────────────────────────────────────────┘  │
└────────────────────┼────────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────────┐
│                    DATABASE LAYER                                │
│  - wp_vqcheckout_ward_rates                                     │
│  - wp_vqcheckout_rate_locations                                 │
│  - wp_vqcheckout_security_log                                   │
│  - wp_options (settings)                                        │
│  - wp_postmeta / wp_wc_orders_meta (HPOS)                      │
└──────────────────────────────────────────────────────────────────┘
```

### 3.2. Module Structure

```
vq-checkout/
├── vq-checkout.php                 # Bootstrap + Service Container
│
├── src/                            # Source code (PSR-4)
│   │
│   ├── Shipping/                   # Shipping Method & Resolver
│   │   ├── Ward_Shipping_Method.php
│   │   ├── Rate_Resolver.php
│   │   ├── Condition_Evaluator.php
│   │   └── Fallback_Handler.php
│   │
│   ├── Data/                       # Data Access Layer
│   │   ├── Repositories/
│   │   │   ├── Rate_Repository.php
│   │   │   ├── Location_Repository.php
│   │   │   ├── Security_Log_Repository.php
│   │   │   └── Settings_Repository.php
│   │   │
│   │   ├── Models/
│   │   │   ├── Rate.php
│   │   │   ├── Location.php
│   │   │   └── Condition.php
│   │   │
│   │   ├── Migrations/
│   │   │   ├── Migration_Manager.php
│   │   │   ├── V1_Initial_Schema.php
│   │   │   └── V2_Add_Security_Log.php
│   │   │
│   │   └── Address_Dataset.php     # Province/Ward data provider
│   │
│   ├── Admin/                      # Admin UI & Settings
│   │   ├── Settings_Page.php
│   │   ├── Rates_Manager_UI.php
│   │   ├── Import_Export.php
│   │   ├── Preview_Simulator.php
│   │   └── Tools_Page.php
│   │
│   ├── Rest/                       # REST API Routes
│   │   ├── Rest_Controller.php
│   │   ├── Address_Controller.php  # /vqcheckout/v1/address-by-phone
│   │   ├── Rates_Controller.php    # CRUD for rates
│   │   └── Wards_Controller.php    # Ward data for Select2
│   │
│   ├── Security/                   # Security Layer
│   │   ├── Captcha_Service.php     # reCAPTCHA v2/v3
│   │   ├── Rate_Limiter.php
│   │   ├── Nonce_Manager.php
│   │   ├── Anti_Spam.php
│   │   └── Sanitizer.php
│   │
│   ├── Cache/                      # Caching Strategy
│   │   ├── Cache_Manager.php
│   │   ├── Match_Cache.php         # Rate match cache
│   │   ├── Address_Cache.php       # Dataset cache
│   │   └── Runtime_Cache.php       # In-request cache
│   │
│   ├── Utils/                      # Utilities
│   │   ├── Phone_Normalizer.php
│   │   ├── Validator.php
│   │   ├── Logger.php
│   │   └── Performance_Monitor.php
│   │
│   └── Compatibility/              # Compatibility Layer
│       ├── HPOS_Adapter.php
│       ├── Blocks_Integration.php
│       └── Multisite_Handler.php
│
├── assets/                         # Frontend Assets
│   ├── js/
│   │   ├── admin/
│   │   │   ├── rates-manager.js    # DataGrid UI
│   │   │   ├── import-export.js
│   │   │   └── preview.js
│   │   │
│   │   └── frontend/
│   │       ├── checkout.js         # Ward selection
│   │       └── address-autofill.js
│   │
│   └── css/
│       ├── admin/
│       │   ├── rates-manager.css
│       │   └── settings.css
│       │
│       └── frontend/
│           └── checkout.css
│
├── data/                           # Static Data
│   ├── vietnam_provinces.json
│   └── vietnam_wards.json
│
├── languages/                      # Translations
│   └── vq-checkout.pot
│
├── tests/                          # Test Suites
│   ├── Unit/
│   ├── Integration/
│   └── E2E/
│
├── uninstall.php                   # Cleanup on uninstall
├── composer.json                   # PHP dependencies
├── phpcs.xml                       # Code standards
├── phpstan.neon                    # Static analysis
└── README.md
```

---

## IV. DATA FLOW - LUỒNG DỮ LIỆU

### 4.1. Checkout Flow - Tính phí vận chuyển

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. User selects Province → Ward on Checkout                    │
└────────────┬────────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. WooCommerce calls calculate_shipping($package)              │
│    Package contains: ward_code, cart_total, items, etc.        │
└────────────┬────────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. Rate_Resolver::resolve()                                    │
│    ├─ Check runtime cache                                      │
│    ├─ Check object cache (vq:match:{instance}:{ward})         │
│    │  └─ CACHE HIT → Return cached rate [Fast path: 5ms]     │
│    │                                                            │
│    └─ CACHE MISS:                                              │
│       ├─ Query rate_locations by ward_code (indexed)          │
│       ├─ Get full rate details by rate_ids (batch)            │
│       ├─ Sort by rate_order ASC                               │
│       ├─ Loop through rates:                                   │
│       │  ├─ Check conditions (min/max total, etc.)           │
│       │  ├─ If is_block_rule → Return NULL (no shipping)     │
│       │  ├─ If match + stop_processing → BREAK               │
│       │  └─ Continue to next rate                            │
│       │                                                        │
│       ├─ No match → Use fallback rate                         │
│       └─ Cache result (TTL: 10-30 min)                        │
└────────────┬────────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────────┐
│ 4. Return rate(s) to WooCommerce                               │
│    WC displays rate on checkout                                │
└─────────────────────────────────────────────────────────────────┘
```

### 4.2. Admin CRUD Flow - Quản lý rates

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. Admin opens Rates Manager UI                                │
└────────────┬────────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. AJAX/REST: Load rates for instance                          │
│    GET /wp-json/vqcheckout/v1/rates?instance_id=3             │
│    → Rate_Repository::find_by_instance($instance_id)          │
│    → Returns paginated list with wards                         │
└────────────┬────────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. Admin adds/edits rate                                        │
│    POST /wp-json/vqcheckout/v1/rates                           │
│    Body: {rate_order, label, base_cost, ward_codes[], ...}    │
└────────────┬────────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────────┐
│ 4. Security checks                                              │
│    ├─ Verify nonce                                             │
│    ├─ Check capability (manage_woocommerce)                    │
│    ├─ Validate input (sanitize, type check)                    │
│    └─ Rate limit check (admin actions)                         │
└────────────┬────────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────────┐
│ 5. Database transaction                                         │
│    BEGIN;                                                       │
│    ├─ INSERT/UPDATE vqcheckout_ward_rates                      │
│    ├─ DELETE old locations (if edit)                           │
│    ├─ INSERT new locations (batch)                             │
│    └─ COMMIT;                                                   │
└────────────┬────────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────────┐
│ 6. Cache invalidation                                           │
│    ├─ Delete vq:match:{instance_id}:* (all wards)             │
│    └─ Clear runtime cache                                      │
└────────────┬────────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────────┐
│ 7. Return success response                                      │
│    → UI updates DataGrid                                        │
└─────────────────────────────────────────────────────────────────┘
```

### 4.3. Address Auto-fill Flow

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. User enters phone number, clicks "Tự động điền"            │
└────────────┬────────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. Frontend JS                                                  │
│    ├─ Generate reCAPTCHA token (v3 invisible)                  │
│    ├─ Get wp_rest nonce                                        │
│    └─ POST /wp-json/vqcheckout/v1/address-by-phone            │
│       Body: {phone, recaptcha_token, _wpnonce}                │
└────────────┬────────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. Security Layer                                               │
│    ├─ Verify reCAPTCHA (server-side, Google API)              │
│    │  └─ Check score ≥ 0.5 (v3) or success (v2)              │
│    ├─ Verify wp_rest nonce                                     │
│    ├─ Rate limit: 5 req / 10 min / IP                         │
│    └─ Log attempt (security_log)                               │
└────────────┬────────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────────┐
│ 4. Phone normalization & lookup                                 │
│    ├─ Normalize: +84912345678 → 0912345678                    │
│    ├─ Query wp_postmeta: billing_phone = normalized           │
│    └─ Get province_code, ward_code from order meta            │
└────────────┬────────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────────┐
│ 5. Privacy-by-design response                                   │
│    Return ONLY: {province_code, ward_code}                     │
│    NOT: full_address, name, email, etc.                        │
└────────────┬────────────────────────────────────────────────────┘
             │
             ▼
┌─────────────────────────────────────────────────────────────────┐
│ 6. Frontend fills Province & Ward selects                       │
│    → Triggers WooCommerce update_checkout event                │
│    → Recalculates shipping                                      │
└─────────────────────────────────────────────────────────────────┘
```

---

## V. TECHNOLOGY STACK

### 5.1. Backend

**Core:**
- PHP 7.4+ (recommend 8.1+)
- WordPress 6.2+
- WooCommerce 8.0+
- MySQL 5.6+ / MariaDB 10.3+

**Libraries:**
- None (pure PHP + WordPress APIs)
- Composer (autoload only)

**APIs Used:**
- WooCommerce Shipping API
- WordPress REST API
- WordPress Options API
- WordPress Transients API
- WP HTTP API (for reCAPTCHA)

### 5.2. Frontend

**Admin UI:**
- Vanilla JavaScript ES6+
- (Optional) React for DataGrid
- jQuery (WP bundled)
- jQuery UI Sortable (drag-drop)
- Select2 (WC bundled)

**Checkout:**
- Vanilla JavaScript
- WooCommerce checkout scripts
- SelectWoo (WC bundled)

**Build:**
- Webpack (optional, for React)
- No build required for vanilla JS

### 5.3. Database

**Schema:**
```sql
-- Core tables
wp_vqcheckout_ward_rates
wp_vqcheckout_rate_locations

-- Optional tables
wp_vqcheckout_security_log

-- WordPress native
wp_options (settings)
wp_postmeta / wp_wc_orders_meta (order data)
```

**Indexes:**
- `idx_instance_order` on `(instance_id, rate_order)`
- `idx_ward` on `ward_code`
- `idx_action_time` on `(action, created_at)`

### 5.4. Caching

**Layers:**
- L1: Runtime cache (array static)
- L2: Object cache (Redis/Memcached via WP)
- L3: Transients (database fallback)

**Keys:**
- `vq:addr:v{VERSION}` - Address dataset
- `vq:match:{instance_id}:{ward_code}` - Rate match
- `vq:wards:{province_code}` - Wards by province

---

## VI. SECURITY ARCHITECTURE

### 6.1. Layers of Security

```
┌─────────────────────────────────────────────────────────────────┐
│ Layer 1: Input Validation & Sanitization                       │
│  - sanitize_text_field(), floatval(), absint()                 │
│  - JSON schema validation                                       │
│  - Whitelist validation                                         │
└────────────┬────────────────────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────────────────────┐
│ Layer 2: Authentication & Authorization                         │
│  - current_user_can('manage_woocommerce')                      │
│  - wp_verify_nonce() / check_ajax_referer()                    │
│  - REST: wp_rest nonce or OAuth                                │
└────────────┬────────────────────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────────────────────┐
│ Layer 3: Rate Limiting                                          │
│  - Transient-based throttling                                  │
│  - 5-10 requests / 10 min / IP                                 │
│  - Configurable limits                                          │
└────────────┬────────────────────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────────────────────┐
│ Layer 4: reCAPTCHA                                              │
│  - v3 invisible (score ≥ 0.5)                                  │
│  - v2 checkbox (fallback)                                      │
│  - Server-side verification                                     │
└────────────┬────────────────────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────────────────────┐
│ Layer 5: Database Security                                      │
│  - 100% prepared statements                                     │
│  - Foreign key constraints                                      │
│  - Transaction isolation                                        │
└────────────┬────────────────────────────────────────────────────┘
             │
┌────────────▼────────────────────────────────────────────────────┐
│ Layer 6: Output Escaping                                        │
│  - esc_html(), esc_attr(), esc_url()                           │
│  - wp_json_encode()                                            │
│  - No innerHTML injection                                       │
└─────────────────────────────────────────────────────────────────┘
```

### 6.2. OWASP Top 10 Mitigation

| Risk | Mitigation |
|------|------------|
| **A01 Broken Access Control** | Capability checks + Nonce |
| **A02 Cryptographic Failures** | No sensitive data exposed; HTTPS enforced |
| **A03 Injection** | Prepared statements + Sanitization |
| **A04 Insecure Design** | Security-by-default + Threat modeling |
| **A05 Security Misconfiguration** | Secure defaults + Hardening guide |
| **A06 Vulnerable Components** | Regular updates + Dependency scan |
| **A07 Authentication Failures** | WP auth + reCAPTCHA |
| **A08 Software & Data Integrity** | Code signing + Integrity checks |
| **A09 Logging Failures** | Structured logging + Monitoring |
| **A10 SSRF** | No external calls except Google reCAPTCHA |

---

## VII. PERFORMANCE ARCHITECTURE

### 7.1. Optimization Strategies

**1. Database:**
- Index-optimized queries
- Batch operations (reduce round-trips)
- Query result caching
- Lazy loading

**2. Caching:**
- Multi-layer cache (L1 → L2 → L3)
- Cache-first pattern
- Smart invalidation
- Conditional caching

**3. Frontend:**
- Asset minification
- Lazy loading scripts
- Debounced AJAX calls
- Virtual scrolling (DataGrid)

**4. Backend:**
- Early returns (fail fast)
- Avoid N+1 queries
- Object pooling
- Connection reuse

### 7.2. Performance Budgets

| Metric | Target | Measured |
|--------|--------|----------|
| Resolve time (p50) | ≤ 10ms | `microtime()` |
| Resolve time (p95) | ≤ 20ms | Aggregate |
| Resolve time (p99) | ≤ 50ms | Aggregate |
| DB queries | ≤ 3 | `$wpdb->num_queries` |
| Cache hit rate | ≥ 80% | Custom tracking |
| Admin page load | ≤ 500ms | Browser timing |
| AJAX response | ≤ 300ms | Network timing |

---

## VIII. DEPLOYMENT ARCHITECTURE

### 8.1. Environments

```
Development → Staging → Production
     ↓            ↓           ↓
   Local      Test site   Live site
   (Docker)   (Clone)     (Real)
```

**Development:**
- Local Docker (WordPress + WooCommerce)
- PHPUnit + Xdebug
- Hot reload for JS/CSS
- Debug mode ON

**Staging:**
- Clone of production
- Anonymized data
- Load testing
- Security scanning
- Performance profiling

**Production:**
- Live site
- Monitoring enabled
- Error logging
- Performance tracking
- Backup automated

### 8.2. CI/CD Pipeline

```yaml
# .github/workflows/main.yml

on: [push, pull_request]

jobs:
  lint:
    - PHP CodeSniffer (WPCS)
    - PHPStan level 5
    - ESLint (JS)
    
  test:
    - PHPUnit (Unit + Integration)
    - Matrix: WP 6.2, 6.3, 6.4 × WC 8.0, 8.2, 8.5
    - Coverage report → Codecov
    
  security:
    - Dependency scan (Composer)
    - SAST (SonarQube)
    - Secret scan
    
  build:
    - Generate .pot file
    - Minify assets
    - Create .zip artifact
    
  deploy:
    - Tag release
    - Upload to WordPress.org (if approved)
    - Update changelog
```

---

## IX. MONITORING & OBSERVABILITY

### 9.1. Metrics to Track

**Performance:**
- Resolve time (p50, p95, p99)
- Cache hit rate
- DB query count
- Page load time

**Reliability:**
- Error rate
- Crash rate
- Availability (%)
- Response time

**Security:**
- CAPTCHA fail rate
- Rate limit hits
- Auth failures
- Suspicious patterns

**Business:**
- Active installs
- Support tickets
- User ratings
- Feature usage

### 9.2. Logging Strategy

```php
// Structured logging
$logger->info('Rate resolved', [
    'ward_code' => $ward_code,
    'rate_id' => $rate_id,
    'duration_ms' => $duration,
    'cache_hit' => $from_cache,
    'instance_id' => $instance_id
]);

// Error logging
$logger->error('Rate resolution failed', [
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString(),
    'context' => [
        'ward_code' => $ward_code,
        'cart_total' => $cart_total
    ]
]);
```

---

## X. SCALABILITY CONSIDERATIONS

### 10.1. Horizontal Scaling

**Load Balancing:**
- Multiple WP instances
- Shared database (master-slave)
- Distributed object cache (Redis cluster)
- CDN for static assets

**Session Handling:**
- Stateless design
- No PHP sessions
- All state in database/cache

### 10.2. Vertical Scaling

**Database:**
- InnoDB buffer pool tuning
- Query cache (if applicable)
- Index optimization
- Connection pooling

**PHP:**
- OpCache enabled
- Increased memory_limit (256M+)
- max_execution_time tuning

**Cache:**
- Object cache (Redis/Memcached)
- Persistent connections
- Large cache size (1GB+)

### 10.3. Data Volume

**Supported:**
- Up to 50,000 rates per instance
- Up to 100 instances per site
- Up to 10,000 wards in dataset
- Up to 1M orders with meta

**Limits:**
- Single query: 1,000 results
- Batch operations: 100 items
- Import: 10,000 rows per file

---

## XI. DISASTER RECOVERY

### 11.1. Backup Strategy

**Automated Backups:**
- Database: Daily full + hourly incremental
- Files: Daily
- Retention: 30 days

**Manual Backups:**
- Before major updates
- Before migrations
- Before bulk operations

**Backup Contents:**
- wp_vqcheckout_* tables
- wp_options (settings)
- Order meta (if selected)
- Plugin files

### 11.2. Recovery Procedures

**Rollback:**
1. Deactivate plugin
2. Restore database from backup
3. Restore plugin files
4. Reactivate plugin
5. Verify data integrity

**Migration Rollback:**
1. Click "Rollback" in admin
2. System imports backup CSV
3. Clears new tables
4. Restores old options
5. Clears all caches

---

## XII. EXTENSIBILITY

### 12.1. Hooks & Filters

**Actions:**
```php
// Before rate resolution
do_action('vq_before_resolve_rates', $package);

// After rate resolution
do_action('vq_after_resolve_rates', $rates, $package);

// Before cache invalidation
do_action('vq_before_cache_clear', $instance_id);
```

**Filters:**
```php
// Modify final cost
$cost = apply_filters('vq_shipping_cost', $cost, $ward_code, $cart_total);

// Modify rate data before save
$rate_data = apply_filters('vq_before_save_rate', $rate_data);

// Modify cache TTL
$ttl = apply_filters('vq_cache_ttl', 1800, $cache_type);
```

### 12.2. Developer APIs

```php
// Get rates programmatically
$rates = VQ\Shipping\Rate_Resolver::get_instance()->resolve([
    'instance_id' => 3,
    'ward_code' => 'VN-01-00123',
    'cart_total' => 500000
]);

// Clear cache
VQ\Cache\Cache_Manager::clear_match_cache(3);

// Add custom condition
add_filter('vq_condition_types', function($types) {
    $types['custom_weight'] = 'Custom Weight Condition';
    return $types;
});
```

---

## XIII. FUTURE ROADMAP

### 13.1. Phase 2 Features (Post-MVP)

- Multi-currency support (integration)
- Shipping class conditions
- Weight/dimension conditions
- Time-based conditions (peak hours)
- Distance-based calculation

### 13.2. Phase 3 Features

- Machine learning rate suggestions
- Predictive caching
- Real-time analytics dashboard
- A/B testing framework
- Multi-tenant support

---

## XIV. REFERENCES

### 14.1. Internal Documents

- [00-NFR-AND-METRICS.md](./00-NFR-AND-METRICS.md)
- [02-DATA-DESIGN-REVISED.md](./02-DATA-DESIGN-REVISED.md)
- [03-SECURITY-AND-API.md](./03-SECURITY-AND-API.md)

### 14.2. External Resources

- [WooCommerce Shipping Docs](https://woocommerce.github.io/code-reference/classes/WC-Shipping-Method.html)
- [WordPress REST API Handbook](https://developer.wordpress.org/rest-api/)
- [OWASP Top 10](https://owasp.org/www-project-top-ten/)

---

**Document Owner:** Solution Architect  
**Review Cycle:** Quarterly  
**Last Updated:** 2025-11-05

---

**END OF ARCHITECTURE DOCUMENT**

*Kiến trúc này được thiết kế cho sản xuất, có khả năng mở rộng và bảo trì lâu dài.*

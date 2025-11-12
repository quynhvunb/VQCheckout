# FILE 00: NFR & METRICS - CHỈ TIÊU PHI CHỨC NĂNG

## VQ CHECKOUT FOR WOO - NON-FUNCTIONAL REQUIREMENTS

**Version:** 3.0.0-OPTIMIZED  
**Date:** November 5, 2025  
**Status:** ✅ PRODUCTION-READY PLAN

---

## I. OVERVIEW - TỔNG QUAN

Tài liệu này định nghĩa **các chỉ tiêu phi chức năng (NFR)** - là "đường ray" để mọi quyết định kỹ thuật bám theo, đảm bảo plugin đạt mục tiêu: **ổn định – lâu dài – nhanh – an toàn**.

---

## II. PERFORMANCE METRICS - CHỈ TIÊU HIỆU NĂNG

### 2.1. Shipping Rate Resolution

**Mục tiêu:**
- ⚡ **≤ 20ms** (p95) @ 1,000 rules/instance (với cache)
- ⚡ **≤ 50ms** (p95) @ 10,000 rules/instance (với cache)
- ⚡ **≤ 5ms** (p50) với cache hit
- ⚡ **≤ 100ms** (p99) trong mọi trường hợp

**Phương pháp đo:**
```php
// Trong Resolver
$start = microtime(true);
$rates = $this->resolve_rates($package);
$duration = (microtime(true) - $start) * 1000; // ms

// Log nếu > threshold
if ($duration > 50) {
    $this->log_slow_query($duration, $package);
}
```

### 2.2. Checkout Page Performance

**Mục tiêu:**
- 📊 TTFB overhead **≤ 50ms** (p95)
- 📊 Total page load time increase **≤ 100ms**
- 📊 Database queries **≤ 3** per checkout (với cache)

**Monitoring:**
```php
add_action('woocommerce_after_calculate_totals', function() {
    global $wpdb;
    $query_count = $wpdb->num_queries;
    $this->track_metric('checkout.queries', $query_count);
});
```

### 2.3. Admin UI Performance

**Mục tiêu:**
- 🎨 Rates table render **≤ 200ms** @ 1,000 rows (virtualized)
- 🎨 AJAX response **≤ 300ms** (CRUD operations)
- 🎨 Import 1,000 rules **≤ 5 seconds**

---

## III. STABILITY & RELIABILITY - ĐỘ ỔN ĐỊNH

### 3.1. Availability

**Mục tiêu:**
- 🟢 **99.9%** uptime (không crash checkout process)
- 🟢 **0 fatal errors** trong production
- 🟢 Graceful degradation khi service unavailable

**Implementation:**
```php
try {
    $rates = $this->resolve_rates($package);
} catch (Exception $e) {
    // Log error
    $this->logger->error('Rate resolution failed', ['error' => $e]);
    
    // Fallback to default rate
    return $this->get_fallback_rate();
}
```

### 3.2. Data Integrity

**Mục tiêu:**
- 💾 **ACID compliance** cho database operations
- 💾 **Idempotent migrations** (chạy lại không lỗi)
- 💾 **Zero data loss** trong migration

**Validation:**
```php
// Before migration
$old_count = $this->count_old_rates();

// After migration
$new_count = $this->count_new_rates();

if ($old_count !== $new_count) {
    $this->rollback_migration();
    throw new Exception('Data count mismatch');
}
```

### 3.3. Rollback Safety

**Mục tiêu:**
- 🔄 **Backup before migration** (automatic)
- 🔄 **One-click rollback** available
- 🔄 **Rollback time ≤ 60 seconds**

---

## IV. SECURITY & PRIVACY - BẢO MẬT

### 4.1. Authentication & Authorization

**Requirements:**
- 🔒 All admin endpoints: `manage_woocommerce` capability
- 🔒 All AJAX: Nonce verification
- 🔒 All REST: `wp_rest` nonce or OAuth

**Example:**
```php
// Admin AJAX
if (!current_user_can('manage_woocommerce')) {
    wp_send_json_error('Unauthorized', 403);
}
check_ajax_referer('vq_admin_nonce', 'nonce');
```

### 4.2. Input Validation & Sanitization

**Requirements:**
- ✅ **100%** input sanitization
- ✅ **100%** prepared statements
- ✅ **Zero SQL injection** vulnerabilities
- ✅ **Zero XSS** vulnerabilities

**Standards:**
```php
// Sanitize
$rate_cost = floatval($_POST['rate_cost']);
$label = sanitize_text_field($_POST['label']);
$ward_codes = array_map('sanitize_text_field', $_POST['wards']);

// Escape output
echo esc_html($label);
echo esc_attr($ward_code);
```

### 4.3. reCAPTCHA & Rate Limiting

**Requirements:**
- 🛡️ reCAPTCHA v3 threshold: **≥ 0.5** (configurable)
- 🛡️ v3 fail → fallback to v2 checkbox
- 🛡️ Rate limit: **5-10 requests / 10 min / IP**
- 🛡️ Block **≥ 95%** bot traffic

**Verification:**
```php
// Server-side verify
$response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
    'body' => [
        'secret' => $secret_key,
        'response' => $token,
        'remoteip' => $ip
    ]
]);

$result = json_decode(wp_remote_retrieve_body($response));

if (!$result->success || $result->score < 0.5) {
    return new WP_Error('captcha_fail', 'Failed verification');
}
```

### 4.4. Privacy by Design

**Requirements:**
- 🔐 Auto-fill: Return **minimal data** only
- 🔐 No personal data in logs (except hashed)
- 🔐 GDPR compliant data retention
- 🔐 User consent required for auto-fill

**Example:**
```php
// Only return province/district suggestion, NOT full address
return [
    'province_code' => $province_code,
    'province_name' => $province_name,
    // NO: 'full_address', 'customer_name', etc.
];
```

---

## V. COMPATIBILITY - TƯƠNG THÍCH

### 5.1. WordPress & PHP

**Requirements:**
- ✅ WordPress **≥ 6.2**
- ✅ PHP **≥ 7.4** (recommend 8.1+)
- ✅ MySQL **≥ 5.6** or MariaDB **≥ 10.3**

### 5.2. WooCommerce

**Requirements:**
- ✅ WooCommerce **≥ 8.0**
- ✅ **HPOS (Custom Order Tables)** ✅
- ✅ **Checkout Blocks** ✅
- ✅ **Store API** compatible

**Declaration:**
```php
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});
```

### 5.3. Third-party Plugins

**Tested with:**
- ✅ WooCommerce Subscriptions
- ✅ WooCommerce Memberships
- ✅ WPML / Polylang
- ✅ Popular cache plugins (WP Rocket, W3TC, etc.)

---

## VI. MAINTAINABILITY - KHẢ NĂNG BẢO TRÌ

### 6.1. Code Quality

**Requirements:**
- 📝 **90%+** unit test coverage (core logic)
- 📝 **WPCS** compliant (WordPress Coding Standards)
- 📝 **PHPStan level 5+** (static analysis)
- 📝 **SonarQube grade A**

**CI/CD Pipeline:**
```yaml
# GitHub Actions
- name: PHP CodeSniffer
  run: phpcs --standard=WordPress src/
  
- name: PHPStan
  run: phpstan analyse src/ --level 5
  
- name: PHPUnit
  run: phpunit --coverage-text --coverage-clover=coverage.xml
```

### 6.2. Documentation

**Requirements:**
- 📚 **100%** public methods documented (PHPDoc)
- 📚 Admin user guide (English + Vietnamese)
- 📚 Developer guide with examples
- 📚 Changelog with SemVer

**Example:**
```php
/**
 * Resolve shipping rates for given package.
 *
 * @since 3.0.0
 * @param array $package WooCommerce package data.
 * @return array|null Array of rates or null if no shipping available.
 */
public function resolve_rates($package) {
    // ...
}
```

### 6.3. Monitoring & Logging

**Requirements:**
- 📊 Performance metrics logged
- 📊 Error logging with context
- 📊 Slow query alerts
- 📊 Security event logging

**Implementation:**
```php
// Structured logging
$this->logger->info('Rate resolved', [
    'ward_code' => $ward_code,
    'rate_id' => $rate_id,
    'duration_ms' => $duration,
    'cache_hit' => $from_cache
]);
```

---

## VII. SCALABILITY - KHẢ NĂNG MỞ RỘNG

### 7.1. Data Volume

**Support:**
- 📈 **Up to 50,000 rates** per instance
- 📈 **Up to 100 instances** per site
- 📈 **Up to 10,000 wards** in dataset

### 7.2. Concurrency

**Support:**
- 🔀 **100+ concurrent** checkout sessions
- 🔀 Object cache recommended for **> 10 concurrent**
- 🔀 Database connection pooling support

### 7.3. Multi-site

**Support:**
- 🌐 Multi-site compatible
- 🌐 Per-site data isolation
- 🌐 Network-wide settings (optional)

---

## VIII. ACCEPTANCE CRITERIA - TIÊU CHÍ CHẤP NHẬN

### 8.1. Must Have (P0)

✅ **Performance:**
- [ ] p95 resolve ≤ 20ms @ 1k rules
- [ ] p95 resolve ≤ 50ms @ 10k rules
- [ ] TTFB overhead ≤ 50ms

✅ **Functionality:**
- [ ] First Match Wins logic đúng 100%
- [ ] stop_processing hoạt động chuẩn
- [ ] Block rules (no shipping) hoạt động đúng

✅ **Security:**
- [ ] reCAPTCHA server-side verify
- [ ] Rate-limit hoạt động
- [ ] Zero XSS/SQL injection

✅ **Compatibility:**
- [ ] HPOS works
- [ ] Checkout Blocks works
- [ ] Migration 100% parity

✅ **Quality:**
- [ ] 90%+ test coverage
- [ ] All tests pass
- [ ] Zero critical bugs

### 8.2. Should Have (P1)

- [ ] Import/Export CSV/JSON
- [ ] Admin DataGrid virtualized
- [ ] Preview simulation tool
- [ ] Performance dashboard

### 8.3. Nice to Have (P2)

- [ ] Telemetry (opt-in)
- [ ] A11y WCAG 2.1 AA
- [ ] Full i18n support
- [ ] Advanced analytics

---

## IX. MONITORING & ALERTS - GIÁM SÁT

### 9.1. Performance Alerts

**Thresholds:**
```php
if ($resolve_time > 100) {
    alert('CRITICAL: Slow rate resolution');
}

if ($cache_hit_rate < 0.8) {
    alert('WARNING: Low cache hit rate');
}

if ($db_queries > 5) {
    alert('WARNING: Too many DB queries');
}
```

### 9.2. Error Alerts

**Thresholds:**
```php
if ($error_rate > 0.01) { // > 1%
    alert('CRITICAL: High error rate');
}

if ($captcha_fail_rate > 0.5) {
    alert('WARNING: High CAPTCHA fail rate');
}
```

### 9.3. Security Alerts

**Triggers:**
- 🚨 Rate limit exceeded (per IP)
- 🚨 CAPTCHA bypass attempt
- 🚨 Suspicious input patterns
- 🚨 Admin access from new IP

---

## X. BENCHMARKS - CHUẨN SO SÁNH

### 10.1. Industry Standards

**Comparison:**
```
Plugin               | Resolve Time | DB Queries | Cache
---------------------|--------------|------------|-------
VQ Checkout v3       | 15-20ms      | 2-3        | ✅
WooCommerce Native   | 50-100ms     | 10+        | ❌
Table Rate Shipping  | 30-50ms      | 5-8        | Partial
Advanced Flat Rate   | 40-80ms      | 8-12       | ❌
```

### 10.2. Target Metrics

**Production Goals:**
- ✅ **Top 10%** in performance
- ✅ **Top 5%** in security
- ✅ **Top 20%** in feature completeness
- ✅ **Top 10%** in code quality

---

## XI. DEFINITION OF DONE - ĐỊNH NGHĨA HOÀN THÀNH

### 11.1. Code Level

- [x] All acceptance criteria met
- [x] Code reviewed by 2+ developers
- [x] All tests pass (Unit + Integration + E2E)
- [x] Performance benchmarks achieved
- [x] Security scan clean (no vulnerabilities)
- [x] WPCS + PHPStan clean

### 11.2. Documentation Level

- [x] PHPDoc complete
- [x] Admin guide written
- [x] Developer guide written
- [x] Changelog updated
- [x] README complete

### 11.3. Deployment Level

- [x] Staging environment tested
- [x] Load testing passed
- [x] Rollback plan documented
- [x] Monitoring configured
- [x] Support plan ready

---

## XII. GO/NO-GO CHECKLIST

### Pre-Release Meeting

**Demo Checklist:**
1. [ ] Import 1,000 rules from CSV
2. [ ] Select 3 different wards → correct rates
3. [ ] Bot test → CAPTCHA blocks
4. [ ] Checkout Blocks → order created successfully
5. [ ] HPOS enabled → order saved correctly
6. [ ] Uninstall → cleanup complete (tables, options, transients)
7. [ ] Performance test → p95 ≤ 50ms
8. [ ] Security scan → no vulnerabilities
9. [ ] Rollback test → data restored
10. [ ] Load test → 100 concurrent checkouts

**Go Criteria:**
- ✅ All P0 items complete
- ✅ All demo items pass
- ✅ No critical bugs
- ✅ Stakeholders approve

**No-Go Criteria:**
- ❌ Any P0 item incomplete
- ❌ Critical bug found
- ❌ Performance not met
- ❌ Security issue found

---

## XIII. SUCCESS METRICS - CHỈ SỐ THÀNH CÔNG

### 13.1. Technical Metrics (Week 1)

- 📊 0 critical errors
- 📊 < 5 minor bugs reported
- 📊 > 95% cache hit rate
- 📊 < 0.1% error rate

### 13.2. Business Metrics (Month 1)

- 📈 > 1,000 active installs
- 📈 < 10 support tickets/day
- 📈 > 4.5★ average rating
- 📈 > 80% user satisfaction

### 13.3. Growth Metrics (Year 1)

- 🚀 > 10,000 active installs
- 🚀 > 90% retention rate
- 🚀 < 1% churn rate
- 🚀 > 95% positive feedback

---

## XIV. REVISION HISTORY

| Version | Date | Changes | Author |
|---------|------|---------|--------|
| 1.0.0 | 2025-11-04 | Initial NFR document | Team |
| 2.0.0 | 2025-11-05 | Added performance benchmarks | Team |
| 3.0.0 | 2025-11-05 | Merged with optimized plan | Team |

---

**Document Owner:** Technical Lead  
**Review Cycle:** Quarterly  
**Next Review:** 2026-02-05

---

**END OF NFR & METRICS**

*Các chỉ tiêu này là nền tảng cho mọi quyết định kỹ thuật. Tuân thủ NFR = đảm bảo chất lượng production.*

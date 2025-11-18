# MISSING FEATURES ANALYSIS

**Generated:** 2025-11-15
**Branch:** claude/fix-woocommerce-plugin-compatibility-015MyteosiiUBfrGeUuEmgni

## ✅ COMPLETED (Current State)

### Core
- [x] Bootstrap (VQ-woo-checkout.php) with HPOS compatibility
- [x] Fallback PSR-4 autoloader
- [x] Plugin.php with Service Container
- [x] Hooks.php registration
- [x] Service_Container (DI)

### Admin
- [x] Settings_Page (5 tabs: General, Checkout, Display, Security, Advanced)
- [x] WooCommerce submenu integration
- [x] Basic settings options

### Shipping
- [x] WC_Method (shipping method registration)
- [x] Basic Rate_Repository structure
- [x] Basic Location_Repository structure
- [x] Basic Resolver structure (but incomplete)

### Data
- [x] **Schema.php - CORRECTED** (matches plan now)
- [x] Migrations.php (basic)
- [x] Seeder.php (basic)
- [x] Importer.php (basic)

### API
- [x] Address_Controller (basic)
- [x] Rate_Controller (basic)

### Cache
- [x] Cache.php (basic implementation)
- [x] Keys.php (key generation)

### Assets
- [x] admin.css (basic)
- [x] admin.js (minimal)

---

## ❌ MISSING / INCOMPLETE (Critical P0)

### 1. Data Layer - HIGH PRIORITY ⚠️
- [ ] **Seeder.php** - Import Vietnam provinces/districts/wards JSON
  - Load `/data/vietnam_provinces.json`
  - Load `/data/vietnam_wards.json`
  - Batch import to avoid timeout
  - Status: **SKELETON ONLY**

- [ ] **Importer.php** - Admin UI for data import
  - WP-CLI command support
  - Progress indicator
  - Status: **SKELETON ONLY**

- [ ] **Migrations.php** - Run schema creation
  - Use dbDelta correctly
  - Version tracking
  - Idempotent migrations
  - Status: **INCOMPLETE**

### 2. Shipping Logic - CRITICAL ⚠️⚠️
- [ ] **Resolver.php** - First Match Wins algorithm
  - Cache-first pipeline (L1/L2/L3)
  - Query rates by ward_code
  - Sort by rate_order (priority)
  - Evaluate conditions_json
  - Stop on first match
  - Handle is_block_rule
  - Performance: ≤20ms
  - Status: **SKELETON - NEEDS COMPLETE REWRITE** per `05-SHIPPING-RESOLVER.md`

- [ ] **Rate_Repository.php** - Database queries
  - `get_rates_for_ward($ward_code, $instance_id)`
  - `create_rate($data)`
  - `update_rate($rate_id, $data)`
  - `delete_rate($rate_id)`
  - `update_rate_locations($rate_id, $ward_codes[])`
  - Status: **INCOMPLETE - Missing key methods**

- [ ] **Location_Repository.php** - Address data
  - `get_provinces()`
  - `get_districts($province_code)`
  - `get_wards($district_code)`
  - Cache with versioning
  - Status: **INCOMPLETE**

### 3. Security Layer - CRITICAL ⚠️
**ALL MISSING** - per `03-SECURITY-AND-API.md`:

- [ ] **src/Security/Recaptcha_Service.php**
  - reCAPTCHA v3 verify (score ≥ 0.5)
  - reCAPTCHA v2 fallback
  - Server-side validation
  - ~280 lines code in plan

- [ ] **src/Security/RateLimiter.php**
  - Transient-based tracking
  - 5-10 req/10min per IP
  - ~200 lines code in plan

- [ ] **src/Security/Nonce.php**
  - REST API nonce management
  - ~100 lines

- [ ] **src/Security/Sanitizer.php**
  - Input sanitization
  - Output escaping
  - OWASP Top 10 mitigation
  - ~150 lines

### 4. Utils - ALL MISSING
**ALL MISSING** - Basic helper classes:

- [ ] **src/Utils/Arr.php** - Array helpers
- [ ] **src/Utils/Str.php** - String helpers
- [ ] **src/Utils/Phone.php** - Phone validation/formatting
- [ ] **src/Utils/Validation.php** - Input validation

### 5. Checkout Integration - MISSING
**ALL MISSING** - Frontend checkout customization:

- [ ] **src/Checkout/Fields.php**
  - Add Province/District/Ward selects
  - Dependent dropdowns
  - WooCommerce checkout hooks
  - ~300 lines per plan

- [ ] **src/Checkout/Validation.php**
  - Validate required ward selection
  - ~100 lines

- [ ] **assets/js/checkout.js**
  - AJAX load provinces/districts/wards
  - Dependent select logic
  - Select2 integration
  - debounce + localStorage cache
  - ~400 lines per plan

- [ ] **assets/css/checkout.css**
  - Checkout field styling
  - ~100 lines

### 6. Admin UI - Shipping Rates Management
**CRITICAL MISSING** - per `06-ADMIN-UI-REVISED.md` & screenshot `xa-phuong-setting.png`:

- [ ] **src/Admin/Rates_Table.php**
  - DataGrid for rates listing
  - Search/sort/paging
  - Drag-drop priority
  - ~300 lines

- [ ] **src/Admin/Rate_Editor.php**
  - Modal add/edit rate
  - Multi-select wards (Select2)
  - Conditions editor (subtotal ranges)
  - AJAX save
  - ~400 lines

- [ ] **assets/js/admin.js** - NEEDS EXPANSION
  - Currently: ~10 lines
  - Required: ~800 lines per plan
  - DataGrid implementation
  - jQuery UI Sortable (drag-drop)
  - Modal dialogs
  - AJAX handlers

- [ ] **assets/css/admin.css** - NEEDS EXPANSION
  - Currently: ~30 lines
  - Required: ~200 lines per plan
  - DataGrid styling
  - Modal styling

### 7. REST API - INCOMPLETE
**INCOMPLETE** - per `03-SECURITY-AND-API.md`:

- [ ] **Address_Controller.php** - EXPAND
  - Currently: basic structure
  - Add: `/vqcheckout/v1/address/provinces`
  - Add: `/vqcheckout/v1/address/districts?province=XX`
  - Add: `/vqcheckout/v1/address/wards?district=XX`

- [ ] **Rate_Controller.php** - EXPAND
  - Currently: basic structure
  - Add: `POST /vqcheckout/v1/rates/resolve` (calculate shipping)
  - Add: Rate CRUD endpoints for admin

- [ ] **src/API/Phone_Controller.php** - CREATE
  - `POST /vqcheckout/v1/phone/lookup`
  - Auto-fill address by phone
  - Privacy-by-design
  - ~350 lines per plan

### 8. Additional Modules (P1/P2)
**ALL MISSING** - per `07-SETTINGS-MODULES.md`:

- [ ] Currency converter (₫ → VNĐ)
- [ ] Hide shipping title option
- [ ] Free-shipping hide other methods
- [ ] Admin order display enhancements
- [ ] Performance monitor

---

## 📊 CODE GAP ANALYSIS

### Current Code
```
PHP files:       16
Total lines:     ~2,500
Code complete:   ~20%
```

### Plan Requirements
```
PHP code:        ~3,200+ lines (per plan)
JavaScript:      ~1,200+ lines (admin + checkout)
CSS:             ~300+ lines
```

### Missing Code
```
PHP:             ~700+ lines critical code
JavaScript:      ~1,100+ lines
CSS:             ~250+ lines
```

---

## 🎯 PRIORITY ROADMAP

### P0 - MUST HAVE (Before ANY deployment)
1. ✅ Fix Database Schema (DONE)
2. **Implement Migrations properly**
3. **Import Vietnam data (Seeder)**
4. **Implement Resolver (First Match Wins)**
5. **Fix Rate_Repository queries**
6. **Implement Security layer (reCAPTCHA + Rate Limit)**
7. **Add Province/District/Ward checkout fields**
8. **Implement checkout.js for dependent selects**
9. **Create Shipping Rates Admin UI**

### P1 - SHOULD HAVE (First release)
10. Phone lookup API
11. Auto-fill address by phone
12. Admin UI polish (DataGrid)
13. Import/Export rates UI
14. Tests (Unit + Integration)

### P2 - NICE TO HAVE (Later)
15. Performance monitor
16. Advanced modules
17. WooCommerce Blocks support
18. E2E tests

---

## 📝 NOTES

- **Schema** is now CORRECT (matches plan)
- **Settings Page** is functional but needs expansion
- **Resolver** is CRITICAL - current version is skeleton only
- **Security** is completely missing - HIGH RISK
- **Admin UI** for rates management is completely missing
- **Checkout fields** integration is completely missing
- **Tests** are completely missing

**Estimated remaining work:** 40-60 hours for P0 features

---

**Next steps:** Start with P0 items in order listed above.

# VQ Checkout for WooCommerce

Tối ưu trang thanh toán WooCommerce cho thị trường Việt Nam với phí vận chuyển tới cấp xã/phường.

## Tính năng

### P0 (Core Features)
- ✅ Thêm trường Tỉnh/Thành → Quận/Huyện → Xã/Phường vào checkout
- ✅ Tính phí vận chuyển theo xã/phường với First Match Wins algorithm
- ✅ Điều kiện phí vận chuyển theo tổng giá đơn hàng
- ✅ Block shipping cho khu vực cụ thể
- ✅ 3-tier caching (L1 runtime → L2 object cache → L3 transient/Redis)
- ✅ REST API cho địa chỉ & resolve shipping rate
- ✅ HPOS compatible
- ✅ reCAPTCHA v3 & rate limiting (P0.5)
- ✅ Admin UI quản lý rates (P0.5)

### P1 (Upcoming)
- ⏳ Woo Blocks support
- ⏳ Tự điền địa chỉ theo SĐT
- ⏳ Export/Import rates UI
- ⏳ E2E tests

### P2 (Future)
- 📋 Performance monitoring
- 📋 Cache preheating
- 📋 Multi-currency support

## Yêu cầu

- WordPress: ≥ 5.8
- WooCommerce: ≥ 6.0
- PHP: ≥ 7.4

## Cài đặt

### Cài đặt từ ZIP

1. Tải file `vq-checkout.zip`
2. Vào **WordPress Admin → Plugins → Add New → Upload Plugin**
3. Chọn file ZIP và nhấn **Install Now**
4. Nhấn **Activate Plugin**

### Cài đặt từ source

```bash
git clone https://github.com/quynhvunb/vq-checkout.git
cd vq-checkout
composer install --no-dev --optimize-autoloader
```

## Thiết lập ban đầu

### 1. Import dữ liệu địa chỉ

Sau khi kích hoạt plugin, dữ liệu tỉnh/thành, xã/phường sẽ tự động được import từ `data/vietnam_*.json`.

Nếu cần import lại:

```bash
wp eval "VQCheckout\Data\Seeder::seed();"
```

### 2. Tạo Shipping Zone & Method

1. Vào **WooCommerce → Settings → Shipping → Add shipping zone**
2. Đặt tên zone (ví dụ: "Hà Nội")
3. Chọn khu vực: **Việt Nam → Thành phố Hà Nội**
4. Nhấn **Add shipping method → Phí vận chuyển tới Xã/Phường**
5. Nhấn **Save changes**

### 3. Cấu hình Shipping Rates

1. Trong bảng **Shipping methods**, chọn **Edit**
2. Tại màn hình cấu hình, bạn có thể:
   - Đặt **Tiêu đề phương thức**
   - Đặt **Phí vận chuyển mặc định**
   - Thêm quy tắc cho từng xã/phường

## REST API

### Endpoints

#### GET `/wp-json/vqcheckout/v1/address/provinces`
Lấy danh sách tỉnh/thành.

**Response:**
```json
[
  {
    "code": "01",
    "name": "Hà Nội",
    "name_with_type": "Thành phố Hà Nội"
  }
]
```

#### GET `/wp-json/vqcheckout/v1/address/districts?province=01`
Lấy danh sách quận/huyện theo tỉnh.

#### GET `/wp-json/vqcheckout/v1/address/wards?district=010`
Lấy danh sách xã/phường theo quận.

#### POST `/wp-json/vqcheckout/v1/rates/resolve`
Tính phí vận chuyển.

**Request:**
```json
{
  "instance_id": 1,
  "ward_code": "00001",
  "cart_subtotal": 500000
}
```

**Response:**
```json
{
  "rate_id": 123,
  "label": "Giao hàng nhanh",
  "cost": 30000,
  "blocked": false,
  "cache_hit": true
}
```

## Development

### Setup

```bash
composer install
npm install
```

### Tests

```bash
# Unit & Integration tests
composer test

# Với coverage
composer test:coverage

# Lint
composer phpcs
composer phpstan
```

### CI/CD

GitHub Actions tự động chạy:
- PHPCS (WordPress Coding Standards)
- PHPStan (Level 5)
- PHPUnit (PHP 7.4 - 8.2, WordPress 6.0+)
- Build distribution ZIP

## Kiến trúc

```
VQ-woo-checkout.php          # Bootstrap
├── src/
│   ├── Core/                # Plugin, Service Container, Hooks
│   ├── Data/                # Migrations, Schema, Seeder, Importer
│   ├── Shipping/            # Resolver, Repositories, WC_Method
│   ├── API/                 # REST Controllers
│   ├── Cache/               # 3-tier Cache service
│   └── Utils/               # Helpers
├── data/                    # JSON data (provinces, wards)
├── assets/                  # JS, CSS
└── tests/                   # PHPUnit tests
```

## Performance

- **Resolve time:** ≤ 20ms (với cache hit: ~1ms)
- **Cache strategy:** L1 (runtime) → L2 (object cache) → L3 (transient/Redis)
- **DB indexes:** Optimized trên `ward_code`, `priority`, `instance_id`

## Bảo mật

- reCAPTCHA v3 (threshold ≥ 0.5)
- Rate limiting: 5-10 req/10'/IP
- Nonce validation cho REST
- Sanitize/Escape đầy đủ
- Prepared statements

## License

GPL v2 or later

## Tác giả

**Vũ Quynh** - [https://quynhvu.com](https://quynhvu.com)

## Changelog

### 1.0.0 (2025-xx-xx)
- Initial release
- Core shipping resolver với First Match Wins
- 3-tier caching
- REST API for address & rates
- HPOS compatible
- PHPUnit tests & CI/CD

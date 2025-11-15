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

### P1 (Enhanced Features)
- ✅ WooCommerce Blocks support (Store API integration)
- ✅ Tự điền địa chỉ theo SĐT (privacy-by-design)
- ✅ Export/Import rates (JSON format)
- ✅ Bulk operations (delete, block, unblock)
- ✅ E2E tests (Playwright)

### P2 (Advanced Features)
- ✅ Performance monitoring (tracking & metrics)
- ✅ Cache preheating (tự động warm cache theo wards phổ biến)
- ✅ Multi-currency support (hỗ trợ đa tiền tệ)
- ✅ Advanced analytics (thống kê chi tiết & dashboard)

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

## WooCommerce Blocks Support

Plugin hỗ trợ đầy đủ **WooCommerce Checkout Block** (Gutenberg blocks).

### Kích hoạt Blocks

1. Vào **Pages → Checkout**
2. Chuyển sang **Block Editor** (nếu đang dùng Classic Editor)
3. Các trường Tỉnh/Thành, Quận/Huyện, Xã/Phường sẽ tự động hiển thị trong Checkout Block

### Tính năng Blocks

- ✅ Tích hợp Store API
- ✅ Dependent selects (Province → District → Ward)
- ✅ Validation tự động
- ✅ Tương thích với Checkout Block settings
- ✅ Responsive design

## P2 Advanced Features

### Performance Monitoring

Theo dõi và phân tích hiệu suất plugin:

- **Metrics Tracking**: Ghi lại thời gian xử lý cho các operations
- **Memory Monitoring**: Theo dõi memory usage
- **Slow Operations**: Tự động detect operations chậm (> 100ms)
- **Dashboard**: Xem summary metrics trong Analytics dashboard

**Kích hoạt:** VQ Checkout → Settings → Advanced Features (P2) → Performance Monitor

### Cache Preheating

Tự động warm cache cho wards phổ biến:

- **Auto-preheat**: Chạy hàng ngày qua WP-Cron
- **Top 50 Wards**: Cache wards được đặt hàng nhiều nhất
- **Multiple Subtotals**: Preheat cho các mức giá phổ biến
- **Manual Trigger**: Chạy thủ công từ Analytics dashboard

**Lợi ích:** Giảm cache miss rate, tăng tốc checkout cho khách hàng phổ biến

### Multi-Currency Support

Hỗ trợ shipping cost cho nhiều loại tiền tệ:

- **Supported Currencies**: VND, USD, EUR, JPY, KRW, THB
- **Auto Convert**: Tự động quy đổi shipping cost theo currency hiện tại
- **Smart Rounding**: Làm tròn phù hợp với từng loại tiền
- **Rate Updates**: Cập nhật tỷ giá 2 lần/ngày

**Kích hoạt:** VQ Checkout → Settings → Advanced Features (P2) → Multi-Currency

### Advanced Analytics

Thống kê và phân tích chi tiết:

- **Checkout Stats**: Tổng orders, revenue, avg shipping
- **Popular Wards**: Top 10 wards theo orders và revenue
- **Cache Performance**: Hit rate, cache hits/misses
- **Daily Charts**: Biểu đồ orders và revenue theo ngày
- **Province Distribution**: Phân bố orders theo tỉnh/thành
- **Performance Summary**: Tổng hợp metrics theo operations

**Truy cập:** WP Admin → VQ Checkout → Analytics

**Database:** Analytics data được lưu trong bảng `wp_vqcheckout_analytics`

**Cleanup:** Tự động xóa data cũ hơn 90 ngày (configurable)

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

# E2E tests (Playwright)
npm install
npx playwright install
npm run test:e2e

# E2E với UI mode
npm run test:e2e:ui

# Lint
composer phpcs
composer phpstan
```

See `tests/e2e/README.md` for detailed E2E testing documentation.

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

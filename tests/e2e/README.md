# E2E Tests for VQ Checkout

End-to-end tests using Playwright to validate the complete checkout flow and plugin functionality.

## Prerequisites

- Node.js ≥ 16
- WordPress test environment running
- WooCommerce installed and activated
- VQ Checkout plugin installed and activated
- At least one product created
- Shipping zones and methods configured

## Setup

### 1. Install Dependencies

```bash
npm install
npx playwright install
```

### 2. Configure Environment

Create a `.env` file in the project root:

```bash
BASE_URL=http://localhost:8080
WP_ADMIN_USER=admin
WP_ADMIN_PASSWORD=password
```

### 3. Prepare Test Data

Before running tests, ensure:

1. **Products**: At least one simple product named "Simple Product"
2. **Shipping Zones**: At least one zone configured for Vietnam
3. **Shipping Methods**: VQ Checkout shipping method enabled
4. **Rates**: At least one rate configured for testing
5. **Pages**: Checkout page exists (classic and/or blocks)

## Running Tests

### Run All Tests

```bash
npm run test:e2e
```

### Run Specific Test Suite

```bash
npx playwright test checkout-classic.spec.js
npx playwright test shipping-calculation.spec.js
npx playwright test phone-autofill.spec.js
npx playwright test admin-rates.spec.js
npx playwright test checkout-blocks.spec.js
```

### Run in Headed Mode (See Browser)

```bash
npm run test:e2e:headed
```

### Run with UI Mode (Interactive)

```bash
npm run test:e2e:ui
```

### Debug Tests

```bash
npm run test:e2e:debug
```

### Run Specific Browser

```bash
npx playwright test --project=chromium
npx playwright test --project=firefox
npx playwright test --project=webkit
npx playwright test --project=mobile-chrome
```

## Test Suites

### 1. Classic Checkout (`checkout-classic.spec.js`)

Tests the traditional WooCommerce checkout with VN address fields:

- Display of province/district/ward fields
- Dependent select loading (province → district → ward)
- Field clearing when parent changes
- Shipping update on ward selection
- Required field validation
- localStorage caching
- Gender field (if enabled)

### 2. Shipping Calculation (`shipping-calculation.spec.js`)

Tests shipping rate calculation:

- Ward-based shipping calculation
- Shipping updates when ward changes
- Free shipping conditions
- Method title hiding (if configured)
- Blocked shipping areas
- Performance benchmarks (< 3 seconds)

### 3. Phone Autofill (`phone-autofill.spec.js`)

Tests phone lookup and address autofill:

- Autofill on phone blur
- Notification display
- Disabled state handling
- Manual data preservation
- Sequential VN address autofill
- Email masking

### 4. Admin Rates (`admin-rates.spec.js`)

Tests admin UI for rate management:

- Rates table display
- Add/edit/delete rates
- Modal interactions
- Export to JSON
- Import from JSON
- Bulk operations (delete, block, unblock)
- Drag-drop reordering
- Settings page

### 5. Checkout Blocks (`checkout-blocks.spec.js`)

Tests WooCommerce Blocks integration:

- VN fields in checkout block
- Province/district/ward loading
- Disabled state management
- Shipping updates
- Form validation
- Complete checkout flow
- Mobile responsiveness

## Test Data

Tests assume the following data exists:

### Products
- **Simple Product** - Any simple product

### Locations (Examples)
- Province: `79` (Ho Chi Minh City)
- District: `760` (District 1)
- Ward: `26734` (Ben Nghe)

### Phone Number for Testing
- `0987654321` - Used for phone autofill tests

## Continuous Integration

Tests are designed to run in CI environments:

```yaml
# .github/workflows/e2e.yml
- name: Install dependencies
  run: npm install

- name: Install Playwright Browsers
  run: npx playwright install --with-deps

- name: Run E2E tests
  run: npm run test:e2e
  env:
    BASE_URL: ${{ secrets.TEST_SITE_URL }}
    WP_ADMIN_USER: ${{ secrets.WP_ADMIN_USER }}
    WP_ADMIN_PASSWORD: ${{ secrets.WP_ADMIN_PASSWORD }}

- name: Upload test results
  uses: actions/upload-artifact@v3
  if: always()
  with:
    name: playwright-report
    path: playwright-report/
```

## Viewing Reports

After tests run, view the HTML report:

```bash
npm run test:e2e:report
```

## Troubleshooting

### Tests Fail Due to Timeout

Increase timeout in individual tests or globally in `playwright.config.js`:

```javascript
use: {
  timeout: 30000, // 30 seconds
}
```

### Elements Not Found

- Ensure selectors match your theme's HTML structure
- Check that plugins are activated
- Verify test data exists

### Flaky Tests

- Add appropriate `waitForTimeout` or `waitForSelector` calls
- Use `retry` option in playwright config
- Check for race conditions in async operations

### Authentication Issues

- Verify `WP_ADMIN_USER` and `WP_ADMIN_PASSWORD` are correct
- Check WordPress login page structure
- Ensure no security plugins block automated logins

## Best Practices

1. **Always clean up**: Tests should create and remove their own data
2. **Use page objects**: Extract common operations to helper functions
3. **Wait appropriately**: Use smart waits instead of fixed timeouts
4. **Test isolation**: Each test should be independent
5. **Meaningful assertions**: Assert on user-visible behavior, not implementation
6. **Coverage over redundancy**: Don't duplicate tests across suites

## Contributing

When adding new E2E tests:

1. Place tests in appropriate spec file (or create new one)
2. Add helper functions to `helpers/` directory
3. Update this README with new test descriptions
4. Ensure tests run in CI
5. Follow existing naming conventions

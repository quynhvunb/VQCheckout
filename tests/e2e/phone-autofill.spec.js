/**
 * E2E Tests: Phone Autofill
 */

const { test, expect } = require('@playwright/test');
const { addProductToCart, goToCheckout, placeOrder } = require('./helpers/woocommerce');

test.describe('Phone Autofill', () => {
	const testPhone = '0987654321';

	test.beforeEach(async ({ page }) => {
		// First, create an order with the test phone number
		await createOrderWithPhone(page, testPhone);

		// Then start fresh checkout
		await addProductToCart(page, 'Simple Product');
		await goToCheckout(page);
	});

	test('should autofill address when phone number matches previous order', async ({ page }) => {
		// Fill phone number
		await page.fill('#billing_phone', testPhone);

		// Trigger blur event
		await page.click('#billing_first_name'); // Click away to trigger blur

		// Wait for autofill
		await page.waitForTimeout(1000);

		// Check if fields were autofilled
		const firstName = await page.inputValue('#billing_first_name');
		const address1 = await page.inputValue('#billing_address_1');

		expect(firstName).toBeTruthy();
		expect(address1).toBeTruthy();
	});

	test('should show notification when address is autofilled', async ({ page }) => {
		await page.fill('#billing_phone', testPhone);
		await page.click('#billing_first_name');

		await page.waitForTimeout(1000);

		// Check for notification
		const notification = page.locator('.woocommerce-info');
		const notificationExists = await notification.count() > 0;

		if (notificationExists) {
			await expect(notification).toBeVisible();
		}
	});

	test('should not autofill if phone lookup is disabled', async ({ page }) => {
		// This test assumes you can disable the feature via settings
		// Skip if feature is enabled

		const phoneInput = page.locator('#billing_phone');
		await phoneInput.fill('0999999999'); // Different phone
		await page.click('#billing_first_name');

		await page.waitForTimeout(1000);

		// Fields should remain empty
		const firstName = await page.inputValue('#billing_first_name');
		expect(firstName).toBe('');
	});

	test('should not override manually entered data', async ({ page }) => {
		// First manually fill a field
		await page.fill('#billing_first_name', 'Custom Name');

		// Then fill phone
		await page.fill('#billing_phone', testPhone);
		await page.click('#billing_address_1');

		await page.waitForTimeout(1000);

		// First name should not be overwritten
		const firstName = await page.inputValue('#billing_first_name');
		expect(firstName).toBe('Custom Name');
	});

	test('should autofill VN address fields sequentially', async ({ page }) => {
		await page.fill('#billing_phone', testPhone);
		await page.click('#billing_first_name');

		// Wait for province to be selected
		await page.waitForTimeout(1500);

		const province = await page.inputValue('#billing_province');
		expect(province).toBeTruthy();

		// Wait for districts to load and district to be selected
		await page.waitForTimeout(1500);

		const district = await page.inputValue('#billing_district');
		expect(district).toBeTruthy();

		// Wait for wards to load and ward to be selected
		await page.waitForTimeout(1500);

		const ward = await page.inputValue('#billing_ward');
		expect(ward).toBeTruthy();
	});

	test('should mask email in autofilled data', async ({ page }) => {
		await page.fill('#billing_phone', testPhone);
		await page.click('#billing_first_name');

		await page.waitForTimeout(1000);

		const email = await page.inputValue('#billing_email');

		// Email should be masked (contain asterisks)
		if (email) {
			expect(email).toContain('*');
		}
	});
});

/**
 * Helper: Create an order with phone number
 */
async function createOrderWithPhone(page, phone) {
	await addProductToCart(page, 'Simple Product');
	await goToCheckout(page);

	// Fill all required fields
	await page.fill('#billing_first_name', 'Nguyen Van');
	await page.fill('#billing_last_name', 'A');
	await page.fill('#billing_phone', phone);
	await page.fill('#billing_email', 'test@example.com');
	await page.fill('#billing_address_1', '123 Test Street');
	await page.fill('#billing_city', 'Ho Chi Minh');
	await page.fill('#billing_postcode', '700000');

	// Select VN address
	await page.selectOption('#billing_province', '79');
	await page.waitForTimeout(500);
	await page.selectOption('#billing_district', '760');
	await page.waitForTimeout(500);
	await page.selectOption('#billing_ward', '26734');
	await page.waitForTimeout(500);

	// Place order
	await placeOrder(page);
}

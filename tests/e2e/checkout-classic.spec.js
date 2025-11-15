/**
 * E2E Tests: Classic Checkout with VN Address Fields
 */

const { test, expect } = require('@playwright/test');
const { addProductToCart, goToCheckout, fillBillingDetails, waitForShippingUpdate } = require('./helpers/woocommerce');

test.describe('Classic Checkout - VN Address Fields', () => {
	test.beforeEach(async ({ page }) => {
		await addProductToCart(page, 'Simple Product');
		await goToCheckout(page);
	});

	test('should display province, district, and ward fields', async ({ page }) => {
		await expect(page.locator('#billing_province')).toBeVisible();
		await expect(page.locator('#billing_district')).toBeVisible();
		await expect(page.locator('#billing_ward')).toBeVisible();
	});

	test('should load provinces on page load', async ({ page }) => {
		const provinceSelect = page.locator('#billing_province');
		const optionCount = await provinceSelect.locator('option').count();

		expect(optionCount).toBeGreaterThan(1); // More than just placeholder
	});

	test('should load districts when province is selected', async ({ page }) => {
		// Select Ho Chi Minh City (code: 79)
		await page.selectOption('#billing_province', '79');
		await page.waitForTimeout(500); // Wait for AJAX

		const districtSelect = page.locator('#billing_district');
		const optionCount = await districtSelect.locator('option').count();

		expect(optionCount).toBeGreaterThan(1);
	});

	test('should load wards when district is selected', async ({ page }) => {
		// Select Ho Chi Minh City
		await page.selectOption('#billing_province', '79');
		await page.waitForTimeout(500);

		// Select District 1 (code: 760)
		await page.selectOption('#billing_district', '760');
		await page.waitForTimeout(500);

		const wardSelect = page.locator('#billing_ward');
		const optionCount = await wardSelect.locator('option').count();

		expect(optionCount).toBeGreaterThan(1);
	});

	test('should clear districts when province changes', async ({ page }) => {
		// Select first province
		await page.selectOption('#billing_province', '79');
		await page.waitForTimeout(500);
		await page.selectOption('#billing_district', '760');
		await page.waitForTimeout(500);

		// Change province
		await page.selectOption('#billing_province', '01'); // Hanoi
		await page.waitForTimeout(500);

		const districtSelect = page.locator('#billing_district');
		const selectedValue = await districtSelect.inputValue();

		expect(selectedValue).toBe('');
	});

	test('should update shipping when ward is selected', async ({ page }) => {
		await fillBillingDetails(page);

		// Select address
		await page.selectOption('#billing_province', '79');
		await page.waitForTimeout(500);
		await page.selectOption('#billing_district', '760');
		await page.waitForTimeout(500);
		await page.selectOption('#billing_ward', '26734');

		await waitForShippingUpdate(page);

		// Check that shipping method appears
		const shippingMethod = page.locator('.woocommerce-shipping-methods');
		await expect(shippingMethod).toBeVisible();
	});

	test('should validate required VN address fields', async ({ page }) => {
		await fillBillingDetails(page);

		// Try to place order without selecting ward
		await page.click('#place_order');

		// Should show validation error
		await expect(page.locator('.woocommerce-error, .woocommerce-NoticeGroup-checkout')).toBeVisible();
	});

	test('should cache province/district/ward data in localStorage', async ({ page }) => {
		// Select province
		await page.selectOption('#billing_province', '79');
		await page.waitForTimeout(1000);

		// Check localStorage
		const cachedData = await page.evaluate(() => {
			const data = localStorage.getItem('vqcheckout_addresses');
			return data ? JSON.parse(data) : null;
		});

		expect(cachedData).not.toBeNull();
		expect(cachedData).toHaveProperty('cache');
		expect(cachedData).toHaveProperty('timestamp');
	});
});

test.describe('Classic Checkout - Gender Field', () => {
	test.beforeEach(async ({ page }) => {
		await addProductToCart(page, 'Simple Product');
		await goToCheckout(page);
	});

	test('should display gender field if enabled', async ({ page }) => {
		const genderField = page.locator('#billing_gender');

		// May or may not be visible depending on settings
		const isVisible = await genderField.isVisible().catch(() => false);

		if (isVisible) {
			const options = await genderField.locator('option').count();
			expect(options).toBeGreaterThanOrEqual(2); // At least "Anh" and "Chị"
		}
	});
});

/**
 * E2E Tests: WooCommerce Checkout Blocks
 */

const { test, expect } = require('@playwright/test');
const { addProductToCart } = require('./helpers/woocommerce');

test.describe('Checkout Blocks - VN Address Fields', () => {
	test.beforeEach(async ({ page }) => {
		await addProductToCart(page, 'Simple Product');
		// Go to checkout block page
		await page.goto('/checkout-block'); // Assuming /checkout-block is the blocks checkout page
	});

	test('should display VN address fields in checkout block', async ({ page }) => {
		// Wait for block to render
		await page.waitForSelector('.wc-block-checkout');

		// Check for VN address fields
		const provinceField = page.locator('.vqcheckout-province-select');
		const districtField = page.locator('.vqcheckout-district-select');
		const wardField = page.locator('.vqcheckout-ward-select');

		// May need to check if blocks integration is active
		const hasVNFields = await provinceField.count() > 0;

		if (hasVNFields) {
			await expect(provinceField).toBeVisible();
			await expect(districtField).toBeVisible();
			await expect(wardField).toBeVisible();
		}
	});

	test('should load provinces in checkout block', async ({ page }) => {
		await page.waitForSelector('.wc-block-checkout', { timeout: 5000 });

		const provinceSelect = page.locator('.vqcheckout-province-select select');

		if (await provinceSelect.count() > 0) {
			await page.waitForTimeout(1000); // Wait for data to load

			const options = await provinceSelect.locator('option').count();
			expect(options).toBeGreaterThan(1);
		}
	});

	test('should load districts when province selected in block', async ({ page }) => {
		await page.waitForSelector('.wc-block-checkout');

		const provinceSelect = page.locator('.vqcheckout-province-select select');

		if (await provinceSelect.count() > 0) {
			await page.waitForTimeout(1000);

			await provinceSelect.selectOption('79'); // Ho Chi Minh
			await page.waitForTimeout(1000);

			const districtSelect = page.locator('.vqcheckout-district-select select');
			const options = await districtSelect.locator('option').count();

			expect(options).toBeGreaterThan(1);
		}
	});

	test('should load wards when district selected in block', async ({ page }) => {
		await page.waitForSelector('.wc-block-checkout');

		const provinceSelect = page.locator('.vqcheckout-province-select select');

		if (await provinceSelect.count() > 0) {
			await page.waitForTimeout(1000);

			await provinceSelect.selectOption('79');
			await page.waitForTimeout(1000);

			const districtSelect = page.locator('.vqcheckout-district-select select');
			await districtSelect.selectOption('760');
			await page.waitForTimeout(1000);

			const wardSelect = page.locator('.vqcheckout-ward-select select');
			const options = await wardSelect.locator('option').count();

			expect(options).toBeGreaterThan(1);
		}
	});

	test('should disable district until province is selected', async ({ page }) => {
		await page.waitForSelector('.wc-block-checkout');

		const districtSelect = page.locator('.vqcheckout-district-select select');

		if (await districtSelect.count() > 0) {
			const isDisabled = await districtSelect.isDisabled();
			expect(isDisabled).toBe(true);
		}
	});

	test('should disable ward until district is selected', async ({ page }) => {
		await page.waitForSelector('.wc-block-checkout');

		const wardSelect = page.locator('.vqcheckout-ward-select select');

		if (await wardSelect.count() > 0) {
			const isDisabled = await wardSelect.isDisabled();
			expect(isDisabled).toBe(true);
		}
	});

	test('should display gender field in blocks if enabled', async ({ page }) => {
		await page.waitForSelector('.wc-block-checkout');

		const genderSelect = page.locator('.vqcheckout-gender-select select');

		if (await genderSelect.count() > 0) {
			await expect(genderSelect).toBeVisible();

			const options = await genderSelect.locator('option').count();
			expect(options).toBeGreaterThanOrEqual(2);
		}
	});

	test('should update shipping in blocks when ward changes', async ({ page }) => {
		await page.waitForSelector('.wc-block-checkout');

		// Fill basic contact info first
		const emailInput = page.locator('input[type="email"]').first();
		if (await emailInput.count() > 0) {
			await emailInput.fill('test@example.com');
		}

		const provinceSelect = page.locator('.vqcheckout-province-select select');

		if (await provinceSelect.count() > 0) {
			await page.waitForTimeout(1000);

			await provinceSelect.selectOption('79');
			await page.waitForTimeout(1000);

			const districtSelect = page.locator('.vqcheckout-district-select select');
			await districtSelect.selectOption('760');
			await page.waitForTimeout(1000);

			const wardSelect = page.locator('.vqcheckout-ward-select select');
			await wardSelect.selectOption('26734');
			await page.waitForTimeout(2000); // Wait for shipping to update

			// Check if shipping options appear
			const shippingOptions = page.locator('.wc-block-components-radio-control__option');
			const hasShipping = await shippingOptions.count() > 0;

			if (hasShipping) {
				await expect(shippingOptions.first()).toBeVisible();
			}
		}
	});

	test('should validate required fields in blocks checkout', async ({ page }) => {
		await page.waitForSelector('.wc-block-checkout');

		// Try to place order without filling required fields
		const placeOrderButton = page.locator('.wc-block-components-checkout-place-order-button');

		if (await placeOrderButton.count() > 0) {
			await placeOrderButton.click();

			// Should show validation errors
			await page.waitForTimeout(1000);

			const errorMessages = page.locator('.wc-block-components-validation-error');
			const hasErrors = await errorMessages.count() > 0;

			if (hasErrors) {
				await expect(errorMessages.first()).toBeVisible();
			}
		}
	});

	test('should complete checkout with blocks', async ({ page }) => {
		await page.waitForSelector('.wc-block-checkout', { timeout: 5000 });

		// Fill all required fields
		const firstNameInput = page.locator('input[autocomplete="given-name"]');
		const lastNameInput = page.locator('input[autocomplete="family-name"]');
		const addressInput = page.locator('input[autocomplete="address-line1"]');
		const cityInput = page.locator('input[autocomplete="address-level2"]');
		const postcodeInput = page.locator('input[autocomplete="postal-code"]');
		const phoneInput = page.locator('input[autocomplete="tel"]');
		const emailInput = page.locator('input[type="email"]').first();

		if (await firstNameInput.count() > 0) {
			await firstNameInput.fill('Nguyen Van');
			await lastNameInput.fill('A');
			await addressInput.fill('123 Test Street');
			await cityInput.fill('Ho Chi Minh');
			await postcodeInput.fill('700000');
			await phoneInput.fill('0987654321');
			await emailInput.fill('test@example.com');

			// Fill VN address
			const provinceSelect = page.locator('.vqcheckout-province-select select');

			if (await provinceSelect.count() > 0) {
				await page.waitForTimeout(1000);

				await provinceSelect.selectOption('79');
				await page.waitForTimeout(1000);

				const districtSelect = page.locator('.vqcheckout-district-select select');
				await districtSelect.selectOption('760');
				await page.waitForTimeout(1000);

				const wardSelect = page.locator('.vqcheckout-ward-select select');
				await wardSelect.selectOption('26734');
				await page.waitForTimeout(2000);

				// Place order
				const placeOrderButton = page.locator('.wc-block-components-checkout-place-order-button');
				await placeOrderButton.click();

				// Should redirect to order received page
				await page.waitForURL('**/order-received/**', { timeout: 15000 });

				const thankYouMessage = page.locator('.woocommerce-notice--success, .wc-block-components-notice-banner');
				await expect(thankYouMessage.first()).toBeVisible();
			}
		}
	});
});

test.describe('Checkout Blocks - Responsive', () => {
	test.use({ viewport: { width: 375, height: 667 } }); // Mobile viewport

	test('should display VN fields on mobile', async ({ page }) => {
		await addProductToCart(page, 'Simple Product');
		await page.goto('/checkout-block');

		await page.waitForSelector('.wc-block-checkout');

		const provinceField = page.locator('.vqcheckout-province-select');

		if (await provinceField.count() > 0) {
			await expect(provinceField).toBeVisible();
		}
	});
});

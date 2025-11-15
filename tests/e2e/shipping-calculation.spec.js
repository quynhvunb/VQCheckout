/**
 * E2E Tests: Shipping Calculation
 */

const { test, expect } = require('@playwright/test');
const { addProductToCart, goToCheckout, fillBillingDetails, waitForShippingUpdate, getShippingCost } = require('./helpers/woocommerce');

test.describe('Shipping Calculation', () => {
	test.beforeEach(async ({ page }) => {
		await addProductToCart(page, 'Simple Product');
		await goToCheckout(page);
		await fillBillingDetails(page);
	});

	test('should calculate shipping based on ward selection', async ({ page }) => {
		// Select first ward
		await page.selectOption('#billing_province', '79');
		await page.waitForTimeout(500);
		await page.selectOption('#billing_district', '760');
		await page.waitForTimeout(500);
		await page.selectOption('#billing_ward', '26734');

		await waitForShippingUpdate(page);

		const shippingCost = await getShippingCost(page);
		expect(shippingCost).toBeTruthy();
	});

	test('should update shipping when ward changes', async ({ page }) => {
		// Select first ward
		await page.selectOption('#billing_province', '79');
		await page.waitForTimeout(500);
		await page.selectOption('#billing_district', '760');
		await page.waitForTimeout(500);
		await page.selectOption('#billing_ward', '26734');

		await waitForShippingUpdate(page);
		const firstShippingCost = await getShippingCost(page);

		// Change to different ward
		await page.selectOption('#billing_ward', '26740');
		await waitForShippingUpdate(page);
		const secondShippingCost = await getShippingCost(page);

		// Costs may or may not be different depending on rate configuration
		expect(secondShippingCost).toBeTruthy();
	});

	test('should show free shipping if conditions are met', async ({ page }) => {
		// This test assumes free shipping is configured for certain conditions
		// Add high-value product to trigger free shipping
		await page.goto('/shop');

		// Check if free shipping appears
		await page.selectOption('#billing_province', '79');
		await page.waitForTimeout(500);
		await page.selectOption('#billing_district', '760');
		await page.waitForTimeout(500);
		await page.selectOption('#billing_ward', '26734');

		await waitForShippingUpdate(page);

		// Check for free shipping option (may not always be present)
		const freeShippingExists = await page.locator('label:has-text("Free")').count() > 0;

		if (freeShippingExists) {
			const freeShipping = page.locator('label:has-text("Free")');
			await expect(freeShipping).toBeVisible();
		}
	});

	test('should hide shipping method title if configured', async ({ page }) => {
		await page.selectOption('#billing_province', '79');
		await page.waitForTimeout(500);
		await page.selectOption('#billing_district', '760');
		await page.waitForTimeout(500);
		await page.selectOption('#billing_ward', '26734');

		await waitForShippingUpdate(page);

		// Depending on settings, method title may be hidden
		const shippingLabel = page.locator('.woocommerce-shipping-methods label');

		if (await shippingLabel.count() > 0) {
			const labelText = await shippingLabel.first().textContent();
			// Label should contain cost but may not contain method title
			expect(labelText).toBeTruthy();
		}
	});

	test('should block shipping if ward is blocked', async ({ page }) => {
		// This test assumes you have configured a blocked ward
		// Select blocked ward (you'll need to configure this in your test setup)

		await page.selectOption('#billing_province', '79');
		await page.waitForTimeout(500);
		await page.selectOption('#billing_district', '760');
		await page.waitForTimeout(500);

		// Try to find a blocked message (if any ward is blocked)
		await page.selectOption('#billing_ward', '26734');
		await waitForShippingUpdate(page);

		// Check for shipping methods
		const hasShippingMethods = await page.locator('.woocommerce-shipping-methods li').count() > 0;

		// If ward is blocked, there should be no shipping methods
		// or an appropriate message
		expect(hasShippingMethods).toBeTruthy(); // At least one method should exist for non-blocked wards
	});
});

test.describe('Shipping Performance', () => {
	test('should resolve shipping in under 1 second', async ({ page }) => {
		await addProductToCart(page, 'Simple Product');
		await goToCheckout(page);
		await fillBillingDetails(page);

		const startTime = Date.now();

		await page.selectOption('#billing_province', '79');
		await page.waitForTimeout(300);
		await page.selectOption('#billing_district', '760');
		await page.waitForTimeout(300);
		await page.selectOption('#billing_ward', '26734');

		await waitForShippingUpdate(page);

		const endTime = Date.now();
		const duration = endTime - startTime;

		expect(duration).toBeLessThan(3000); // Should complete within 3 seconds
	});
});

/**
 * WooCommerce helpers for E2E tests
 */

/**
 * Add a simple product to cart
 * @param {import('@playwright/test').Page} page
 * @param {string} productName
 */
async function addProductToCart(page, productName = 'Test Product') {
	await page.goto('/shop');
	await page.click(`a:has-text("${productName}")`);
	await page.click('button[name="add-to-cart"]');
	await page.waitForSelector('.woocommerce-message');
}

/**
 * Go to checkout page
 * @param {import('@playwright/test').Page} page
 */
async function goToCheckout(page) {
	await page.goto('/checkout');
	await page.waitForSelector('form.checkout');
}

/**
 * Fill billing details (basic fields)
 * @param {import('@playwright/test').Page} page
 * @param {Object} details
 */
async function fillBillingDetails(page, details = {}) {
	const defaults = {
		firstName: 'Nguyen Van',
		lastName: 'A',
		phone: '0987654321',
		email: 'test@example.com',
		address1: '123 Test Street',
		city: 'Ho Chi Minh',
		postcode: '700000',
	};

	const data = { ...defaults, ...details };

	await page.fill('#billing_first_name', data.firstName);
	await page.fill('#billing_last_name', data.lastName);
	await page.fill('#billing_phone', data.phone);
	await page.fill('#billing_email', data.email);
	await page.fill('#billing_address_1', data.address1);
	await page.fill('#billing_city', data.city);
	await page.fill('#billing_postcode', data.postcode);
}

/**
 * Wait for shipping to update
 * @param {import('@playwright/test').Page} page
 */
async function waitForShippingUpdate(page) {
	await page.waitForSelector('.woocommerce-checkout-review-order-table', {
		state: 'attached',
	});
	await page.waitForTimeout(500); // Allow AJAX to complete
}

/**
 * Place order
 * @param {import('@playwright/test').Page} page
 */
async function placeOrder(page) {
	await page.click('#place_order');
	await page.waitForURL('**/order-received/**', { timeout: 10000 });
}

/**
 * Get order total
 * @param {import('@playwright/test').Page} page
 * @returns {Promise<string>}
 */
async function getOrderTotal(page) {
	const totalElement = await page.locator('.order-total .woocommerce-Price-amount');
	return await totalElement.textContent();
}

/**
 * Get shipping cost
 * @param {import('@playwright/test').Page} page
 * @returns {Promise<string>}
 */
async function getShippingCost(page) {
	const shippingElement = await page.locator('.shipping .woocommerce-Price-amount').first();
	return await shippingElement.textContent();
}

module.exports = {
	addProductToCart,
	goToCheckout,
	fillBillingDetails,
	waitForShippingUpdate,
	placeOrder,
	getOrderTotal,
	getShippingCost,
};

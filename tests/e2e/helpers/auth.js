/**
 * Authentication helpers for E2E tests
 */

const ADMIN_USER = process.env.WP_ADMIN_USER || 'admin';
const ADMIN_PASSWORD = process.env.WP_ADMIN_PASSWORD || 'password';

/**
 * Login to WordPress admin
 * @param {import('@playwright/test').Page} page
 */
async function loginAsAdmin(page) {
	await page.goto('/wp-login.php');
	await page.fill('#user_login', ADMIN_USER);
	await page.fill('#user_pass', ADMIN_PASSWORD);
	await page.click('#wp-submit');
	await page.waitForURL('**/wp-admin/**');
}

/**
 * Logout from WordPress
 * @param {import('@playwright/test').Page} page
 */
async function logout(page) {
	await page.goto('/wp-login.php?action=logout');
	await page.click('a:has-text("log out")');
}

module.exports = {
	loginAsAdmin,
	logout,
	ADMIN_USER,
	ADMIN_PASSWORD,
};

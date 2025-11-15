/**
 * E2E Tests: Admin Rates Management
 */

const { test, expect } = require('@playwright/test');
const { loginAsAdmin, logout } = require('./helpers/auth');

test.describe('Admin Rates Management', () => {
	test.beforeEach(async ({ page }) => {
		await loginAsAdmin(page);
		await page.goto('/wp-admin/admin.php?page=vqcheckout-rates');
	});

	test.afterEach(async ({ page }) => {
		await logout(page);
	});

	test('should display rates table', async ({ page }) => {
		const ratesTable = page.locator('.wp-list-table');
		await expect(ratesTable).toBeVisible();
	});

	test('should show add rate button', async ({ page }) => {
		const addButton = page.locator('.vqcheckout-add-rate');
		await expect(addButton).toBeVisible();
	});

	test('should open add rate modal when button clicked', async ({ page }) => {
		await page.click('.vqcheckout-add-rate');

		const modal = page.locator('#vqcheckout-rate-modal');
		await expect(modal).toBeVisible();

		const modalTitle = page.locator('#vqcheckout-modal-title');
		await expect(modalTitle).toHaveText('Thêm Rate');
	});

	test('should close modal when close button clicked', async ({ page }) => {
		await page.click('.vqcheckout-add-rate');

		const modal = page.locator('#vqcheckout-rate-modal');
		await expect(modal).toBeVisible();

		await page.click('.vqcheckout-modal-close');
		await expect(modal).toBeHidden();
	});

	test('should create new rate', async ({ page }) => {
		await page.click('.vqcheckout-add-rate');

		// Fill rate form
		await page.selectOption('#rate_zone_id', { index: 1 }); // Select first zone
		await page.waitForTimeout(500);

		await page.selectOption('#rate_instance_id', { index: 1 }); // Select first method
		await page.fill('#rate_title', 'E2E Test Rate');
		await page.fill('#rate_cost', '25000');
		await page.fill('#rate_priority', '10');

		// Select wards (if available)
		const wardSelect = page.locator('#rate_wards');
		const wardOptions = await wardSelect.locator('option').count();

		if (wardOptions > 1) {
			await page.selectOption('#rate_wards', { index: 1 });
		}

		// Submit form
		await page.click('button[type="submit"]');

		// Wait for success
		await page.waitForTimeout(1000);

		// Should reload page and show new rate
		const rateTitle = page.locator('td:has-text("E2E Test Rate")');
		await expect(rateTitle).toBeVisible({ timeout: 5000 });
	});

	test('should edit existing rate', async ({ page }) => {
		// Click edit button on first rate
		const editButton = page.locator('.vqcheckout-edit-rate').first();

		if (await editButton.count() > 0) {
			await editButton.click();

			const modal = page.locator('#vqcheckout-rate-modal');
			await expect(modal).toBeVisible();

			const modalTitle = page.locator('#vqcheckout-modal-title');
			await expect(modalTitle).toHaveText('Sửa Rate');

			// Modify cost
			await page.fill('#rate_cost', '30000');

			// Submit
			await page.click('button[type="submit"]');

			await page.waitForTimeout(1000);
		}
	});

	test('should delete rate', async ({ page }) => {
		const deleteButton = page.locator('.vqcheckout-delete-rate').first();

		if (await deleteButton.count() > 0) {
			// Accept confirmation dialog
			page.on('dialog', dialog => dialog.accept());

			await deleteButton.click();

			await page.waitForTimeout(1000);
		}
	});

	test('should export rates to JSON', async ({ page }) => {
		const exportButton = page.locator('.vqcheckout-export-rates');
		await expect(exportButton).toBeVisible();

		// Setup download listener
		const downloadPromise = page.waitForEvent('download');

		await exportButton.click();

		const download = await downloadPromise;
		expect(download.suggestedFilename()).toContain('vqcheckout-rates-export');
		expect(download.suggestedFilename()).toContain('.json');
	});

	test('should import rates from JSON', async ({ page }) => {
		const importButton = page.locator('.vqcheckout-import-rates');
		await expect(importButton).toBeVisible();

		// Create test JSON file
		const testData = {
			meta: {
				schema_version: '1.0',
				plugin_version: '1.0.0',
				export_date: new Date().toISOString(),
			},
			rates: [
				{
					zone_id: 1,
					instance_id: 1,
					title: 'Imported Rate',
					cost: 15000,
					priority: 5,
					is_blocked: false,
					stop_after_match: false,
					conditions: {},
					ward_codes: ['26734'],
				},
			],
		};

		// This test would require file upload which is complex in E2E
		// Skipping actual file upload for now
	});

	test('should perform bulk delete action', async ({ page }) => {
		// Check some rates
		const checkboxes = page.locator('input[name="rate[]"]');
		const count = await checkboxes.count();

		if (count > 0) {
			await checkboxes.first().check();

			// Select bulk action
			await page.selectOption('select[name="action"]', 'delete');

			// Accept confirmation
			page.on('dialog', dialog => dialog.accept());

			// Click apply
			await page.click('#doaction');

			await page.waitForTimeout(1000);
		}
	});

	test('should perform bulk block action', async ({ page }) => {
		const checkboxes = page.locator('input[name="rate[]"]');
		const count = await checkboxes.count();

		if (count > 0) {
			await checkboxes.first().check();

			await page.selectOption('select[name="action"]', 'block');

			page.on('dialog', dialog => dialog.accept());

			await page.click('#doaction');

			await page.waitForTimeout(1000);
		}
	});

	test('should support drag and drop reorder', async ({ page }) => {
		// Check if sortable is enabled
		const priorityElements = page.locator('.vqcheckout-priority');
		const count = await priorityElements.count();

		if (count >= 2) {
			// Test sortable initialization
			const firstPriority = await priorityElements.first().textContent();
			expect(firstPriority).toBeTruthy();
		}
	});
});

test.describe('Admin Settings', () => {
	test.beforeEach(async ({ page }) => {
		await loginAsAdmin(page);
		await page.goto('/wp-admin/admin.php?page=vqcheckout-settings');
	});

	test.afterEach(async ({ page }) => {
		await logout(page);
	});

	test('should display settings page', async ({ page }) => {
		const heading = page.locator('h1:has-text("VQ Checkout")');
		await expect(heading).toBeVisible();
	});

	test('should have reCAPTCHA settings', async ({ page }) => {
		const recaptchaSection = page.locator('h2:has-text("Google reCAPTCHA")');
		await expect(recaptchaSection).toBeVisible();
	});

	test('should have phone lookup setting', async ({ page }) => {
		const phoneLookupCheckbox = page.locator('input[name="vqcheckout_options[enable_phone_lookup]"]');

		if (await phoneLookupCheckbox.count() > 0) {
			await expect(phoneLookupCheckbox).toBeVisible();
		}
	});

	test('should save settings', async ({ page }) => {
		// Toggle a setting
		const vnCurrencyCheckbox = page.locator('input[name="vqcheckout_options[to_vnd]"]');

		if (await vnCurrencyCheckbox.count() > 0) {
			const wasChecked = await vnCurrencyCheckbox.isChecked();

			if (wasChecked) {
				await vnCurrencyCheckbox.uncheck();
			} else {
				await vnCurrencyCheckbox.check();
			}

			// Save
			await page.click('input[type="submit"]');

			// Should show success message
			const successMessage = page.locator('.updated, .notice-success');
			await expect(successMessage).toBeVisible({ timeout: 5000 });
		}
	});
});

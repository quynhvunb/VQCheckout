/**
 * VQ Checkout Frontend JS
 */
(function($) {
	'use strict';

	const VQCheckout = {
		cache: {},
		cacheTTL: 900000, // 15 minutes

		init() {
			this.loadProvinces();
			this.bindEvents();
			this.initRecaptcha();
			this.loadCachedData();
		},

		bindEvents() {
			$(document.body).on('change', '#billing_province', this.onProvinceChange.bind(this));
			$(document.body).on('change', '#billing_district', this.onDistrictChange.bind(this));
			$(document.body).on('change', '#billing_ward', this.onWardChange.bind(this));
			$(document.body).on('updated_checkout', this.onCheckoutUpdated.bind(this));
			$(document.body).on('blur', '#billing_phone', this.onPhoneBlur.bind(this));
		},

		loadCachedData() {
			const cached = localStorage.getItem('vqcheckout_addresses');
			if (cached) {
				const data = JSON.parse(cached);
				if (data.timestamp && (Date.now() - data.timestamp) < this.cacheTTL) {
					this.cache = data.cache || {};
				}
			}
		},

		saveCachedData() {
			localStorage.setItem('vqcheckout_addresses', JSON.stringify({
				timestamp: Date.now(),
				cache: this.cache
			}));
		},

		async loadProvinces() {
			const cacheKey = 'provinces';

			if (this.cache[cacheKey]) {
				this.renderProvinces(this.cache[cacheKey]);
				return;
			}

			try {
				const response = await $.ajax({
					url: `${window.vqCheckout.restUrl}/address/provinces`,
					method: 'GET'
				});

				this.cache[cacheKey] = response;
				this.saveCachedData();
				this.renderProvinces(response);
			} catch (error) {
				console.error('Load provinces error:', error);
			}
		},

		renderProvinces(provinces) {
			let options = `<option value="">${window.vqCheckout.i18n.selectProvince}</option>`;
			provinces.forEach(province => {
				options += `<option value="${province.code}">${province.name_with_type}</option>`;
			});
			$('#billing_province').html(options);
		},

		async onProvinceChange(e) {
			const provinceCode = $(e.target).val();

			$('#billing_district').html(`<option value="">${window.vqCheckout.i18n.selectDistrict}</option>`);
			$('#billing_ward').html(`<option value="">${window.vqCheckout.i18n.selectWard}</option>`);

			if (!provinceCode) {
				return;
			}

			const cacheKey = `districts_${provinceCode}`;

			if (this.cache[cacheKey]) {
				this.renderDistricts(this.cache[cacheKey]);
				return;
			}

			try {
				const response = await $.ajax({
					url: `${window.vqCheckout.restUrl}/address/districts`,
					method: 'GET',
					data: { province: provinceCode }
				});

				this.cache[cacheKey] = response;
				this.saveCachedData();
				this.renderDistricts(response);
			} catch (error) {
				console.error('Load districts error:', error);
			}
		},

		renderDistricts(districts) {
			let options = `<option value="">${window.vqCheckout.i18n.selectDistrict}</option>`;
			districts.forEach(district => {
				options += `<option value="${district.code}">${district.name_with_type}</option>`;
			});
			$('#billing_district').html(options);
		},

		async onDistrictChange(e) {
			const districtCode = $(e.target).val();

			$('#billing_ward').html(`<option value="">${window.vqCheckout.i18n.selectWard}</option>`);

			if (!districtCode) {
				return;
			}

			const cacheKey = `wards_${districtCode}`;

			if (this.cache[cacheKey]) {
				this.renderWards(this.cache[cacheKey]);
				return;
			}

			try {
				const response = await $.ajax({
					url: `${window.vqCheckout.restUrl}/address/wards`,
					method: 'GET',
					data: { district: districtCode }
				});

				this.cache[cacheKey] = response;
				this.saveCachedData();
				this.renderWards(response);
			} catch (error) {
				console.error('Load wards error:', error);
			}
		},

		renderWards(wards) {
			let options = `<option value="">${window.vqCheckout.i18n.selectWard}</option>`;
			wards.forEach(ward => {
				options += `<option value="${ward.code}">${ward.name_with_type}</option>`;
			});
			$('#billing_ward').html(options);
		},

		onWardChange() {
			$(document.body).trigger('update_checkout');
		},

		onCheckoutUpdated() {
			// Refresh shipping if ward changed
		},

		async onPhoneBlur(e) {
			if (!window.vqCheckout.enablePhoneLookup) {
				return;
			}

			const phone = $(e.target).val().trim();
			if (!phone || phone.length < 10) {
				return;
			}

			// Check if address fields are already filled
			const hasAddress = $('#billing_first_name').val() || $('#billing_address_1').val();
			if (hasAddress) {
				return;
			}

			// Debounce to avoid multiple calls
			if (this.phoneLookupTimeout) {
				clearTimeout(this.phoneLookupTimeout);
			}

			this.phoneLookupTimeout = setTimeout(async () => {
				await this.lookupPhone(phone);
			}, 500);
		},

		async lookupPhone(phone) {
			try {
				const response = await $.ajax({
					url: `${window.vqCheckout.restUrl}/phone/lookup`,
					method: 'POST',
					data: JSON.stringify({ phone }),
					contentType: 'application/json'
				});

				if (response.found && response.address) {
					this.autofillAddress(response.address);
				}
			} catch (error) {
				console.error('Phone lookup error:', error);
			}
		},

		async autofillAddress(address) {
			// Only autofill if fields are empty
			const fieldMap = {
				'first_name': '#billing_first_name',
				'last_name': '#billing_last_name',
				'company': '#billing_company',
				'address_1': '#billing_address_1',
				'address_2': '#billing_address_2',
				'city': '#billing_city',
				'postcode': '#billing_postcode',
				'email': '#billing_email'
			};

			for (const [key, selector] of Object.entries(fieldMap)) {
				if (address[key] && !$(selector).val()) {
					$(selector).val(address[key]).trigger('change');
				}
			}

			// Handle VN address fields
			if (address.province) {
				$('#billing_province').val(address.province).trigger('change');

				// Wait for districts to load
				await this.waitForDistricts();

				if (address.district) {
					$('#billing_district').val(address.district).trigger('change');

					// Wait for wards to load
					await this.waitForWards();

					if (address.ward) {
						$('#billing_ward').val(address.ward).trigger('change');
					}
				}
			}

			// Show notification
			if (window.vqCheckout.i18n.addressAutofilled) {
				this.showNotification(window.vqCheckout.i18n.addressAutofilled);
			}
		},

		waitForDistricts() {
			return new Promise((resolve) => {
				const checkInterval = setInterval(() => {
					if ($('#billing_district option').length > 1) {
						clearInterval(checkInterval);
						resolve();
					}
				}, 100);

				setTimeout(() => {
					clearInterval(checkInterval);
					resolve();
				}, 3000);
			});
		},

		waitForWards() {
			return new Promise((resolve) => {
				const checkInterval = setInterval(() => {
					if ($('#billing_ward option').length > 1) {
						clearInterval(checkInterval);
						resolve();
					}
				}, 100);

				setTimeout(() => {
					clearInterval(checkInterval);
					resolve();
				}, 3000);
			});
		},

		showNotification(message) {
			const $notice = $(`<div class="woocommerce-info" style="margin: 1em 0;">${message}</div>`);
			$('.woocommerce-billing-fields').prepend($notice);

			setTimeout(() => {
				$notice.fadeOut(300, function() {
					$(this).remove();
				});
			}, 5000);
		},

		async initRecaptcha() {
			const config = window.vqCheckout.recaptcha;

			if (!config.enabled) {
				return;
			}

			if (config.version === 'v3') {
				this.initRecaptchaV3(config.siteKey);
			} else if (config.version === 'v2') {
				this.initRecaptchaV2(config.siteKey);
			}
		},

		initRecaptchaV3(siteKey) {
			$(document.body).on('checkout_place_order', async function() {
				if ($('#vqcheckout_recaptcha_token').length === 0) {
					$('<input>').attr({
						type: 'hidden',
						id: 'vqcheckout_recaptcha_token',
						name: 'vqcheckout_recaptcha_token'
					}).appendTo('form.checkout');
				}

				try {
					const token = await grecaptcha.execute(siteKey, { action: 'checkout' });
					$('#vqcheckout_recaptcha_token').val(token);
					return true;
				} catch (error) {
					console.error('reCAPTCHA error:', error);
					return false;
				}
			});
		},

		initRecaptchaV2(siteKey) {
			if ($('.vqcheckout-recaptcha-v2').length === 0) {
				$('<div class="vqcheckout-recaptcha-v2"></div>')
					.insertBefore('.woocommerce-checkout-payment');

				grecaptcha.render('.vqcheckout-recaptcha-v2', {
					sitekey: siteKey,
					callback: function(token) {
						if ($('#vqcheckout_recaptcha_token').length === 0) {
							$('<input>').attr({
								type: 'hidden',
								id: 'vqcheckout_recaptcha_token',
								name: 'vqcheckout_recaptcha_token'
							}).appendTo('form.checkout');
						}
						$('#vqcheckout_recaptcha_token').val(token);
					}
				});
			}
		}
	};

	$(document).ready(() => {
		VQCheckout.init();
	});

})(jQuery);

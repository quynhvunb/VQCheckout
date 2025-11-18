/**
 * VQ Checkout - Frontend Checkout Script
 * Handles Province/District/Ward dependent selects with AJAX
 */

(function($) {
	'use strict';

	const VQCheckoutFields = {
		cache: {},
		cacheTTL: 15 * 60 * 1000, // 15 minutes

		init: function() {
			this.bindEvents();
			this.loadProvinces();
			this.maybeRestoreFromCache();
		},

		bindEvents: function() {
			const self = this;

			// Province change - load districts
			$(document.body).on('change', '[data-vqcheckout-field="province"]', function() {
				const provinceCode = $(this).val();
				const type = $(this).attr('id').replace('_vqcheckout_province', '');

				self.clearDistrictsAndWards(type);

				if (provinceCode) {
					self.loadDistricts(provinceCode, type);
				}
			});

			// District change - load wards
			$(document.body).on('change', '[data-vqcheckout-field="district"]', function() {
				const districtCode = $(this).val();
				const type = $(this).attr('id').replace('_vqcheckout_district', '');

				self.clearWards(type);

				if (districtCode) {
					self.loadWards(districtCode, type);
				}
			});

			// Ward change - trigger checkout update
			$(document.body).on('change', '[data-vqcheckout-field="ward"]', function() {
				$(document.body).trigger('update_checkout');
			});

			// Auto-fill from phone lookup
			if (vqCheckout.enablePhoneLookup) {
				$(document.body).on('blur', '#billing_phone', function() {
					self.lookupAddressByPhone($(this).val());
				});
			}
		},

		/**
		 * Load provinces on page load
		 */
		loadProvinces: function() {
			const self = this;
			const cached = this.getCached('provinces');

			if (cached) {
				this.populateProvinces(cached);
				return;
			}

			this.showLoading('province');

			$.ajax({
				url: vqCheckout.restUrl + '/address/provinces',
				method: 'GET',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-VQCheckout-Nonce', vqCheckout.nonce);
				},
				success: function(response) {
					self.setCached('provinces', response);
					self.populateProvinces(response);
				},
				error: function() {
					self.showError('province');
				},
				complete: function() {
					self.hideLoading('province');
				}
			});
		},

		/**
		 * Load districts for province
		 */
		loadDistricts: function(provinceCode, type) {
			const self = this;
			const cacheKey = 'districts_' + provinceCode;
			const cached = this.getCached(cacheKey);

			if (cached) {
				this.populateDistricts(cached, type);
				return;
			}

			this.showLoading('district', type);

			$.ajax({
				url: vqCheckout.restUrl + '/address/districts',
				method: 'GET',
				data: { province: provinceCode },
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-VQCheckout-Nonce', vqCheckout.nonce);
				},
				success: function(response) {
					self.setCached(cacheKey, response);
					self.populateDistricts(response, type);
				},
				error: function() {
					self.showError('district', type);
				},
				complete: function() {
					self.hideLoading('district', type);
				}
			});
		},

		/**
		 * Load wards for district
		 */
		loadWards: function(districtCode, type) {
			const self = this;
			const cacheKey = 'wards_' + districtCode;
			const cached = this.getCached(cacheKey);

			if (cached) {
				this.populateWards(cached, type);
				return;
			}

			this.showLoading('ward', type);

			$.ajax({
				url: vqCheckout.restUrl + '/address/wards',
				method: 'GET',
				data: { district: districtCode },
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-VQCheckout-Nonce', vqCheckout.nonce);
				},
				success: function(response) {
					self.setCached(cacheKey, response);
					self.populateWards(response, type);
				},
				error: function() {
					self.showError('ward', type);
				},
				complete: function() {
					self.hideLoading('ward', type);
				}
			});
		},

		/**
		 * Populate province selects
		 */
		populateProvinces: function(provinces) {
			const $selects = $('[data-vqcheckout-field="province"]');

			$selects.each(function() {
				const $select = $(this);
				const currentValue = $select.val();

				$select.empty().append(
					$('<option>', {
						value: '',
						text: vqCheckout.i18n.selectProvince
					})
				);

				$.each(provinces, function(i, province) {
					$select.append(
						$('<option>', {
							value: province.code,
							text: province.name
						})
					);
				});

				if (currentValue) {
					$select.val(currentValue);
				}
			});
		},

		/**
		 * Populate district selects
		 */
		populateDistricts: function(districts, type) {
			const $select = $('#' + type + '_vqcheckout_district');

			$select.empty().append(
				$('<option>', {
					value: '',
					text: vqCheckout.i18n.selectDistrict
				})
			);

			$.each(districts, function(i, district) {
				$select.append(
					$('<option>', {
						value: district.code,
						text: district.name
					})
				);
			});

			$select.prop('disabled', false);
		},

		/**
		 * Populate ward selects
		 */
		populateWards: function(wards, type) {
			const $select = $('#' + type + '_vqcheckout_ward');

			$select.empty().append(
				$('<option>', {
					value: '',
					text: vqCheckout.i18n.selectWard
				})
			);

			$.each(wards, function(i, ward) {
				$select.append(
					$('<option>', {
						value: ward.code,
						text: ward.name
					})
				);
			});

			$select.prop('disabled', false);
		},

		/**
		 * Clear districts and wards
		 */
		clearDistrictsAndWards: function(type) {
			this.clearDistricts(type);
			this.clearWards(type);
		},

		/**
		 * Clear districts
		 */
		clearDistricts: function(type) {
			const $select = $('#' + type + '_vqcheckout_district');
			$select.empty().append(
				$('<option>', {
					value: '',
					text: vqCheckout.i18n.selectDistrict
				})
			).prop('disabled', true);
		},

		/**
		 * Clear wards
		 */
		clearWards: function(type) {
			const $select = $('#' + type + '_vqcheckout_ward');
			$select.empty().append(
				$('<option>', {
					value: '',
					text: vqCheckout.i18n.selectWard
				})
			).prop('disabled', true);
		},

		/**
		 * Show loading state
		 */
		showLoading: function(field, type) {
			let selector;

			if (type) {
				selector = '#' + type + '_vqcheckout_' + field;
			} else {
				selector = '[data-vqcheckout-field="' + field + '"]';
			}

			$(selector).addClass('vqcheckout-loading').prop('disabled', true);
		},

		/**
		 * Hide loading state
		 */
		hideLoading: function(field, type) {
			let selector;

			if (type) {
				selector = '#' + type + '_vqcheckout_' + field;
			} else {
				selector = '[data-vqcheckout-field="' + field + '"]';
			}

			$(selector).removeClass('vqcheckout-loading');
		},

		/**
		 * Show error message
		 */
		showError: function(field, type) {
			console.error('VQCheckout: Error loading ' + field + ' data');
			// Could add visual error indicator here
		},

		/**
		 * Cache management - Get cached data
		 */
		getCached: function(key) {
			const item = this.cache[key];

			if (!item) {
				return null;
			}

			const now = Date.now();

			if (now - item.timestamp > this.cacheTTL) {
				delete this.cache[key];
				return null;
			}

			return item.data;
		},

		/**
		 * Cache management - Set cached data
		 */
		setCached: function(key, data) {
			this.cache[key] = {
				data: data,
				timestamp: Date.now()
			};

			// Also save to localStorage for persistence
			try {
				const cacheData = {
					data: data,
					timestamp: Date.now()
				};
				localStorage.setItem('vqcheckout_' + key, JSON.stringify(cacheData));
			} catch (e) {
				// localStorage might be disabled
			}
		},

		/**
		 * Restore cache from localStorage
		 */
		maybeRestoreFromCache: function() {
			const self = this;
			const keys = ['provinces'];

			keys.forEach(function(key) {
				try {
					const stored = localStorage.getItem('vqcheckout_' + key);

					if (stored) {
						const item = JSON.parse(stored);
						const now = Date.now();

						if (now - item.timestamp <= self.cacheTTL) {
							self.cache[key] = item;
						} else {
							localStorage.removeItem('vqcheckout_' + key);
						}
					}
				} catch (e) {
					// Ignore parse errors
				}
			});
		},

		/**
		 * Auto-fill address from phone lookup
		 */
		lookupAddressByPhone: function(phone) {
			if (!phone || phone.length < 10) {
				return;
			}

			const self = this;

			$.ajax({
				url: vqCheckout.restUrl + '/phone/lookup',
				method: 'POST',
				data: JSON.stringify({ phone: phone }),
				contentType: 'application/json',
				beforeSend: function(xhr) {
					xhr.setRequestHeader('X-VQCheckout-Nonce', vqCheckout.nonce);
				},
				success: function(response) {
					if (response.address) {
						self.fillAddress(response.address);
					}
				},
				error: function() {
					// Silently fail - not critical
				}
			});
		},

		/**
		 * Fill address fields from lookup
		 */
		fillAddress: function(address) {
			if (address.province) {
				$('#billing_vqcheckout_province').val(address.province).trigger('change');
			}

			// Set district and ward after province loads
			setTimeout(function() {
				if (address.district) {
					$('#billing_vqcheckout_district').val(address.district).trigger('change');
				}

				setTimeout(function() {
					if (address.ward) {
						$('#billing_vqcheckout_ward').val(address.ward).trigger('change');
					}
				}, 300);
			}, 300);
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		VQCheckoutFields.init();
	});

	// Expose to global scope for debugging
	window.VQCheckoutFields = VQCheckoutFields;

})(jQuery);

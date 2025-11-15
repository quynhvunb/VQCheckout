/**
 * VQ Checkout Admin JS
 */
(function($) {
	'use strict';

	const VQCheckoutAdmin = {
		init() {
			this.bindEvents();
			this.initSortable();
		},

		bindEvents() {
			$(document).on('click', '.vqcheckout-add-rate', this.openAddModal.bind(this));
			$(document).on('click', '.vqcheckout-edit-rate', this.openEditModal.bind(this));
			$(document).on('click', '.vqcheckout-delete-rate', this.deleteRate.bind(this));
			$(document).on('click', '.vqcheckout-modal-close', this.closeModal.bind(this));
			$(document).on('click', '.vqcheckout-modal-overlay', this.closeModal.bind(this));
			$(document).on('submit', '#vqcheckout-rate-form', this.saveRate.bind(this));
			$(document).on('change', '#rate_zone_id', this.loadShippingMethods.bind(this));
			$(document).on('click', '.vqcheckout-export-rates', this.exportRates.bind(this));
			$(document).on('click', '.vqcheckout-import-rates', this.openImportDialog.bind(this));
			$(document).on('click', '#doaction, #doaction2', this.handleBulkAction.bind(this));
		},

		initSortable() {
			if (typeof $.fn.sortable === 'undefined') {
				return;
			}

			$('#the-list').sortable({
				handle: '.vqcheckout-priority',
				update: (event, ui) => {
					const order = [];
					$('#the-list tr').each(function(index) {
						const rateId = $(this).find('input[type="checkbox"]').val();
						if (rateId) {
							order.push(rateId);
						}
					});

					this.reorderRates(order);
				}
			});
		},

		openAddModal(e) {
			e.preventDefault();
			$('#vqcheckout-modal-title').text('Thêm Rate');
			$('#vqcheckout-rate-form')[0].reset();
			$('#rate_id').val('');
			$('#vqcheckout-rate-modal').fadeIn(200);
			this.loadWards();
		},

		async openEditModal(e) {
			e.preventDefault();
			const rateId = $(e.currentTarget).data('rate-id');

			try {
				const response = await $.ajax({
					url: `${window.vqCheckoutAdmin.restUrl}/admin/rates/${rateId}`,
					method: 'GET',
					beforeSend: (xhr) => {
						xhr.setRequestHeader('X-WP-Nonce', window.vqCheckoutAdmin.nonce);
					}
				});

				$('#vqcheckout-modal-title').text('Sửa Rate');
				$('#rate_id').val(response.id);
				$('#rate_zone_id').val(response.zone_id).trigger('change');

				setTimeout(() => {
					$('#rate_instance_id').val(response.instance_id);
				}, 300);

				$('#rate_title').val(response.title);
				$('#rate_cost').val(response.cost);
				$('#rate_priority').val(response.priority);
				$('#rate_is_blocked').prop('checked', response.is_blocked == 1);
				$('#rate_stop_after_match').prop('checked', response.stop_after_match == 1);

				if (response.conditions) {
					$('#condition_min').val(response.conditions.min || '');
					$('#condition_max').val(response.conditions.max || '');
					$('#condition_free').val(response.conditions.free_shipping_min || '');
				}

				this.loadWards(response.ward_codes);

				$('#vqcheckout-rate-modal').fadeIn(200);
			} catch (error) {
				console.error('Load rate error:', error);
				alert(window.vqCheckoutAdmin.i18n.error);
			}
		},

		closeModal(e) {
			e.preventDefault();
			$('#vqcheckout-rate-modal').fadeOut(200);
		},

		async saveRate(e) {
			e.preventDefault();

			const formData = new FormData(e.target);
			const rateId = formData.get('rate_id');
			const data = {
				zone_id: parseInt(formData.get('zone_id')),
				instance_id: parseInt(formData.get('instance_id')),
				title: formData.get('title'),
				cost: parseFloat(formData.get('cost')),
				priority: parseInt(formData.get('priority')) || 0,
				is_blocked: formData.get('is_blocked') ? 1 : 0,
				stop_after_match: formData.get('stop_after_match') ? 1 : 0,
				conditions: {
					min: formData.get('conditions[min]') || null,
					max: formData.get('conditions[max]') || null,
					free_shipping_min: formData.get('conditions[free_shipping_min]') || null
				},
				ward_codes: formData.getAll('ward_codes[]')
			};

			const isEdit = rateId && rateId !== '';
			const url = isEdit
				? `${window.vqCheckoutAdmin.restUrl}/admin/rates/${rateId}`
				: `${window.vqCheckoutAdmin.restUrl}/admin/rates`;
			const method = isEdit ? 'PUT' : 'POST';

			try {
				await $.ajax({
					url,
					method,
					data: JSON.stringify(data),
					contentType: 'application/json',
					beforeSend: (xhr) => {
						xhr.setRequestHeader('X-WP-Nonce', window.vqCheckoutAdmin.nonce);
					}
				});

				this.closeModal(e);
				alert(window.vqCheckoutAdmin.i18n.saved);
				location.reload();
			} catch (error) {
				console.error('Save rate error:', error);
				alert(window.vqCheckoutAdmin.i18n.error);
			}
		},

		async deleteRate(e) {
			e.preventDefault();

			if (!confirm(window.vqCheckoutAdmin.i18n.confirmDelete)) {
				return;
			}

			const rateId = $(e.currentTarget).data('rate-id');

			try {
				await $.ajax({
					url: `${window.vqCheckoutAdmin.restUrl}/admin/rates/${rateId}`,
					method: 'DELETE',
					beforeSend: (xhr) => {
						xhr.setRequestHeader('X-WP-Nonce', window.vqCheckoutAdmin.nonce);
					}
				});

				alert(window.vqCheckoutAdmin.i18n.saved);
				location.reload();
			} catch (error) {
				console.error('Delete rate error:', error);
				alert(window.vqCheckoutAdmin.i18n.error);
			}
		},

		async loadShippingMethods(e) {
			const zoneId = $(e.target).val();

			if (!zoneId) {
				$('#rate_instance_id').html('<option value="">-- Chọn Method --</option>');
				return;
			}

			try {
				const methods = await $.ajax({
					url: `${window.vqCheckoutAdmin.restUrl}/admin/shipping-methods`,
					method: 'GET',
					data: { zone_id: zoneId },
					beforeSend: (xhr) => {
						xhr.setRequestHeader('X-WP-Nonce', window.vqCheckoutAdmin.nonce);
					}
				});

				let options = '<option value="">-- Chọn Method --</option>';
				methods.forEach(method => {
					if (method.enabled) {
						options += `<option value="${method.instance_id}">${method.title}</option>`;
					}
				});

				$('#rate_instance_id').html(options);
			} catch (error) {
				console.error('Load methods error:', error);
			}
		},

		async loadWards(selectedCodes = []) {
			try {
				const provinces = await $.ajax({
					url: `${window.vqCheckoutAdmin.restUrl}/address/provinces`,
					method: 'GET'
				});

				let options = '';

				for (const province of provinces) {
					const districts = await $.ajax({
						url: `${window.vqCheckoutAdmin.restUrl}/address/districts`,
						method: 'GET',
						data: { province: province.code }
					});

					for (const district of districts) {
						const wards = await $.ajax({
							url: `${window.vqCheckoutAdmin.restUrl}/address/wards`,
							method: 'GET',
							data: { district: district.code }
						});

						if (wards.length > 0) {
							options += `<optgroup label="${province.name} - ${district.name}">`;
							wards.forEach(ward => {
								const selected = selectedCodes.includes(ward.code) ? ' selected' : '';
								options += `<option value="${ward.code}"${selected}>${ward.name}</option>`;
							});
							options += '</optgroup>';
						}
					}
				}

				$('#rate_wards').html(options);
			} catch (error) {
				console.error('Load wards error:', error);
			}
		},

		async reorderRates(order) {
			try {
				await $.ajax({
					url: `${window.vqCheckoutAdmin.restUrl}/admin/rates/reorder`,
					method: 'POST',
					data: JSON.stringify({ order }),
					contentType: 'application/json',
					beforeSend: (xhr) => {
						xhr.setRequestHeader('X-WP-Nonce', window.vqCheckoutAdmin.nonce);
					}
				});
			} catch (error) {
				console.error('Reorder error:', error);
			}
		},

		async exportRates() {
			const selectedIds = this.getSelectedRateIds();

			try {
				const data = await $.ajax({
					url: `${window.vqCheckoutAdmin.restUrl}/admin/rates/export`,
					method: 'POST',
					data: JSON.stringify({ rate_ids: selectedIds }),
					contentType: 'application/json',
					beforeSend: (xhr) => {
						xhr.setRequestHeader('X-WP-Nonce', window.vqCheckoutAdmin.nonce);
					}
				});

				const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
				const url = URL.createObjectURL(blob);
				const a = document.createElement('a');
				a.href = url;
				a.download = `vqcheckout-rates-export-${Date.now()}.json`;
				a.click();
				URL.revokeObjectURL(url);

				const count = selectedIds.length || data.meta.rates_count;
				alert(`Exported ${count} rate(s) successfully`);
			} catch (error) {
				console.error('Export error:', error);
				alert(window.vqCheckoutAdmin.i18n.error);
			}
		},

		openImportDialog() {
			const input = document.createElement('input');
			input.type = 'file';
			input.accept = 'application/json';
			input.onchange = (e) => {
				const file = e.target.files[0];
				if (file) {
					this.importRates(file);
				}
			};
			input.click();
		},

		async importRates(file) {
			try {
				const text = await file.text();
				const data = JSON.parse(text);

				const options = {
					overwrite: confirm('Overwrite existing rates with same title?'),
					skip_existing: true,
					validate_only: false
				};

				const result = await $.ajax({
					url: `${window.vqCheckoutAdmin.restUrl}/admin/rates/import`,
					method: 'POST',
					data: JSON.stringify({ data, options }),
					contentType: 'application/json',
					beforeSend: (xhr) => {
						xhr.setRequestHeader('X-WP-Nonce', window.vqCheckoutAdmin.nonce);
					}
				});

				if (result.success) {
					const msg = `Import completed!\nImported: ${result.counts.imported}\nSkipped: ${result.counts.skipped}\nErrors: ${result.counts.errors}`;
					alert(msg);
					if (result.counts.imported > 0) {
						location.reload();
					}
				}
			} catch (error) {
				console.error('Import error:', error);
				const errorMsg = error.responseJSON?.message || error.message || 'Import failed';
				alert(`Import error: ${errorMsg}`);
			}
		},

		async handleBulkAction(e) {
			e.preventDefault();

			const actionSelect = $(e.currentTarget).siblings('select');
			const action = actionSelect.val();

			if (!action || action === '-1') {
				return;
			}

			const selectedIds = this.getSelectedRateIds();
			if (selectedIds.length === 0) {
				alert('Please select at least one rate');
				return;
			}

			let confirmMsg = `Are you sure you want to ${action} ${selectedIds.length} rate(s)?`;
			if (action === 'delete') {
				confirmMsg = `Are you sure you want to delete ${selectedIds.length} rate(s)? This cannot be undone.`;
			}

			if (!confirm(confirmMsg)) {
				return;
			}

			try {
				const result = await $.ajax({
					url: `${window.vqCheckoutAdmin.restUrl}/admin/rates/bulk`,
					method: 'POST',
					data: JSON.stringify({
						action: action,
						rate_ids: selectedIds
					}),
					contentType: 'application/json',
					beforeSend: (xhr) => {
						xhr.setRequestHeader('X-WP-Nonce', window.vqCheckoutAdmin.nonce);
					}
				});

				alert(result.message);
				location.reload();
			} catch (error) {
				console.error('Bulk action error:', error);
				alert(window.vqCheckoutAdmin.i18n.error);
			}
		},

		getSelectedRateIds() {
			const ids = [];
			$('input[name="rate[]"]:checked').each(function() {
				ids.push(parseInt($(this).val()));
			});
			return ids;
		}
	};

	$(document).ready(() => {
		VQCheckoutAdmin.init();
	});

})(jQuery);

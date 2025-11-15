<?php
/**
 * Rate Editor Modal
 *
 * @package VQCheckout\Admin
 */

namespace VQCheckout\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Modal for add/edit rate
 */
class Rate_Editor {
	public static function render_modal() {
		?>
		<div id="vqcheckout-rate-modal" class="vqcheckout-modal" style="display:none;">
			<div class="vqcheckout-modal-overlay"></div>
			<div class="vqcheckout-modal-content">
				<div class="vqcheckout-modal-header">
					<h2 id="vqcheckout-modal-title"><?php esc_html_e( 'Thêm Rate', 'vq-checkout' ); ?></h2>
					<button type="button" class="vqcheckout-modal-close">&times;</button>
				</div>

				<div class="vqcheckout-modal-body">
					<form id="vqcheckout-rate-form">
						<input type="hidden" name="rate_id" id="rate_id" value="">

						<div class="form-field">
							<label for="rate_zone_id"><?php esc_html_e( 'Shipping Zone', 'vq-checkout' ); ?></label>
							<select name="zone_id" id="rate_zone_id" required>
								<option value=""><?php esc_html_e( '-- Chọn Zone --', 'vq-checkout' ); ?></option>
								<?php self::render_zone_options(); ?>
							</select>
						</div>

						<div class="form-field">
							<label for="rate_instance_id"><?php esc_html_e( 'Shipping Method', 'vq-checkout' ); ?></label>
							<select name="instance_id" id="rate_instance_id" required>
								<option value=""><?php esc_html_e( '-- Chọn Method --', 'vq-checkout' ); ?></option>
							</select>
						</div>

						<div class="form-field">
							<label for="rate_title"><?php esc_html_e( 'Tiêu đề', 'vq-checkout' ); ?></label>
							<input type="text" name="title" id="rate_title" required>
						</div>

						<div class="form-field">
							<label for="rate_cost"><?php esc_html_e( 'Phí vận chuyển', 'vq-checkout' ); ?></label>
							<input type="number" name="cost" id="rate_cost" step="0.01" min="0" required>
						</div>

						<div class="form-field">
							<label for="rate_priority"><?php esc_html_e( 'Ưu tiên', 'vq-checkout' ); ?></label>
							<input type="number" name="priority" id="rate_priority" value="0">
							<p class="description"><?php esc_html_e( 'Số nhỏ hơn = ưu tiên cao hơn', 'vq-checkout' ); ?></p>
						</div>

						<div class="form-field">
							<label>
								<input type="checkbox" name="is_blocked" id="rate_is_blocked" value="1">
								<?php esc_html_e( 'Block shipping (không giao tới khu vực này)', 'vq-checkout' ); ?>
							</label>
						</div>

						<div class="form-field">
							<label>
								<input type="checkbox" name="stop_after_match" id="rate_stop_after_match" value="1">
								<?php esc_html_e( 'Dừng khi khớp (First Match Wins)', 'vq-checkout' ); ?>
							</label>
						</div>

						<div class="form-field">
							<label for="rate_wards"><?php esc_html_e( 'Chọn Xã/Phường', 'vq-checkout' ); ?></label>
							<select name="ward_codes[]" id="rate_wards" multiple size="10" style="width:100%;height:200px;">
							</select>
							<p class="description"><?php esc_html_e( 'Giữ Ctrl/Cmd để chọn nhiều', 'vq-checkout' ); ?></p>
						</div>

						<div class="form-field">
							<h4><?php esc_html_e( 'Điều kiện theo tổng đơn hàng', 'vq-checkout' ); ?></h4>

							<label for="condition_min"><?php esc_html_e( 'Tổng tối thiểu', 'vq-checkout' ); ?></label>
							<input type="number" name="conditions[min]" id="condition_min" step="1000" min="0">

							<label for="condition_max"><?php esc_html_e( 'Tổng tối đa', 'vq-checkout' ); ?></label>
							<input type="number" name="conditions[max]" id="condition_max" step="1000" min="0">

							<label for="condition_free"><?php esc_html_e( 'Free ship khi ≥', 'vq-checkout' ); ?></label>
							<input type="number" name="conditions[free_shipping_min]" id="condition_free" step="1000" min="0">
						</div>
					</form>
				</div>

				<div class="vqcheckout-modal-footer">
					<button type="button" class="button button-secondary vqcheckout-modal-close">
						<?php esc_html_e( 'Hủy', 'vq-checkout' ); ?>
					</button>
					<button type="submit" form="vqcheckout-rate-form" class="button button-primary">
						<?php esc_html_e( 'Lưu', 'vq-checkout' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	private static function render_zone_options() {
		$zones = \WC_Shipping_Zones::get_zones();

		foreach ( $zones as $zone ) {
			printf(
				'<option value="%d">%s</option>',
				esc_attr( $zone['id'] ),
				esc_html( $zone['zone_name'] )
			);
		}
	}
}

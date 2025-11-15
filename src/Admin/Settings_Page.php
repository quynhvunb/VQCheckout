<?php
/**
 * Admin Settings Page
 *
 * @package VQCheckout\Admin
 */

namespace VQCheckout\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Main admin settings page with tabs
 */
class Settings_Page {
	const OPTION_GROUP = 'vqcheckout_settings';
	const OPTION_NAME  = 'vqcheckout_options';

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_menu() {
		add_menu_page(
			__( 'VQ Checkout', 'vq-checkout' ),
			__( 'VQ Checkout', 'vq-checkout' ),
			'manage_woocommerce',
			'vqcheckout-settings',
			array( $this, 'render_page' ),
			'dashicons-location-alt',
			56
		);

		add_submenu_page(
			'vqcheckout-settings',
			__( 'Cài đặt', 'vq-checkout' ),
			__( 'Cài đặt', 'vq-checkout' ),
			'manage_woocommerce',
			'vqcheckout-settings'
		);

		add_submenu_page(
			'vqcheckout-settings',
			__( 'Shipping Rates', 'vq-checkout' ),
			__( 'Shipping Rates', 'vq-checkout' ),
			'manage_woocommerce',
			'vqcheckout-rates',
			array( $this, 'render_rates_page' )
		);
	}

	public function register_settings() {
		register_setting( self::OPTION_GROUP, self::OPTION_NAME, array( $this, 'sanitize_options' ) );

		add_settings_section(
			'vqcheckout_checkout_section',
			__( 'Checkout Fields', 'vq-checkout' ),
			null,
			'vqcheckout-settings'
		);

		add_settings_field(
			'phone_vn',
			__( 'Định dạng SĐT ở VN', 'vq-checkout' ),
			array( $this, 'render_checkbox_field' ),
			'vqcheckout-settings',
			'vqcheckout_checkout_section',
			array(
				'name'        => 'phone_vn',
				'label'       => __( 'Bắt buộc SĐT có định dạng ở VN (+84xxx hoặc 0xxx)', 'vq-checkout' ),
				'default'     => '1',
			)
		);

		add_settings_field(
			'enable_postcode',
			__( 'Hiện trường postcode', 'vq-checkout' ),
			array( $this, 'render_checkbox_field' ),
			'vqcheckout-settings',
			'vqcheckout_checkout_section',
			array(
				'name'  => 'enable_postcode',
				'label' => __( 'Hiện trường postcode cho Việt Nam', 'vq-checkout' ),
			)
		);

		add_settings_field(
			'enable_gender',
			__( 'Xưng hô', 'vq-checkout' ),
			array( $this, 'render_checkbox_field' ),
			'vqcheckout-settings',
			'vqcheckout_checkout_section',
			array(
				'name'    => 'enable_gender',
				'label'   => __( 'Hiển thị mục chọn cách xưng hô Anh/Chị', 'vq-checkout' ),
				'default' => '1',
			)
		);

		add_settings_section(
			'vqcheckout_recaptcha_section',
			__( 'Google reCAPTCHA', 'vq-checkout' ),
			null,
			'vqcheckout-settings'
		);

		add_settings_field(
			'enable_recaptcha',
			__( 'Kích hoạt reCAPTCHA', 'vq-checkout' ),
			array( $this, 'render_recaptcha_field' ),
			'vqcheckout-settings',
			'vqcheckout_recaptcha_section'
		);

		add_settings_section(
			'vqcheckout_general_section',
			__( 'Cài đặt chung', 'vq-checkout' ),
			null,
			'vqcheckout-settings'
		);

		add_settings_field(
			'to_vnd',
			__( 'Chuyển ₫ sang VNĐ', 'vq-checkout' ),
			array( $this, 'render_checkbox_field' ),
			'vqcheckout-settings',
			'vqcheckout_general_section',
			array(
				'name'  => 'to_vnd',
				'label' => __( 'Cho phép chuyển ký hiệu tiền tệ sang VNĐ', 'vq-checkout' ),
			)
		);

		add_settings_field(
			'remove_method_title',
			__( 'Loại bỏ tiêu đề vận chuyển', 'vq-checkout' ),
			array( $this, 'render_checkbox_field' ),
			'vqcheckout-settings',
			'vqcheckout_general_section',
			array(
				'name'    => 'remove_method_title',
				'label'   => __( 'Loại bỏ hoàn toàn tiêu đề của phương thức vận chuyển', 'vq-checkout' ),
				'default' => '1',
			)
		);

		add_settings_field(
			'enable_phone_lookup',
			__( 'Tự động điền địa chỉ theo SĐT', 'vq-checkout' ),
			array( $this, 'render_checkbox_field' ),
			'vqcheckout-settings',
			'vqcheckout_general_section',
			array(
				'name'  => 'enable_phone_lookup',
				'label' => __( 'Cho phép tự động điền địa chỉ dựa trên SĐT đã đặt hàng', 'vq-checkout' ),
			)
		);

		// P2 Features Section
		add_settings_section(
			'vqcheckout_p2_section',
			__( 'Tính năng nâng cao (P2)', 'vq-checkout' ),
			null,
			'vqcheckout-settings'
		);

		add_settings_field(
			'enable_performance_monitor',
			__( 'Performance Monitor', 'vq-checkout' ),
			array( $this, 'render_checkbox_field' ),
			'vqcheckout-settings',
			'vqcheckout_p2_section',
			array(
				'name'  => 'enable_performance_monitor',
				'label' => __( 'Bật giám sát hiệu suất và tracking metrics', 'vq-checkout' ),
			)
		);

		add_settings_field(
			'enable_analytics',
			__( 'Analytics', 'vq-checkout' ),
			array( $this, 'render_checkbox_field' ),
			'vqcheckout-settings',
			'vqcheckout_p2_section',
			array(
				'name'  => 'enable_analytics',
				'label' => __( 'Bật analytics và thống kê nâng cao', 'vq-checkout' ),
			)
		);

		add_settings_field(
			'enable_multi_currency',
			__( 'Multi-Currency', 'vq-checkout' ),
			array( $this, 'render_checkbox_field' ),
			'vqcheckout-settings',
			'vqcheckout_p2_section',
			array(
				'name'  => 'enable_multi_currency',
				'label' => __( 'Bật hỗ trợ đa tiền tệ cho shipping', 'vq-checkout' ),
			)
		);
	}

	public function sanitize_options( $input ) {
		$output = array();

		$checkboxes = array(
			'phone_vn',
			'enable_postcode',
			'enable_gender',
			'to_vnd',
			'remove_method_title',
			'not_required_email',
			'freeship_remove_other_methob',
			'enable_recaptcha_create_order',
			'enable_recaptcha_get_address',
			'enable_phone_lookup',
			'enable_performance_monitor',
			'enable_analytics',
			'enable_multi_currency',
		);

		foreach ( $checkboxes as $field ) {
			$output[ $field ] = isset( $input[ $field ] ) ? '1' : '0';
		}

		$text_fields = array(
			'recaptcha_sitekey',
			'recaptcha_secretkey',
			'recaptcha_sitekey_v3',
			'recaptcha_secretkey_v3',
			'block_order_ip',
			'block_order_name',
		);

		foreach ( $text_fields as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$output[ $field ] = sanitize_textarea_field( $input[ $field ] );
			}
		}

		if ( isset( $input['enable_recaptcha'] ) ) {
			$output['enable_recaptcha'] = in_array( $input['enable_recaptcha'], array( '0', '1', '2' ), true )
				? $input['enable_recaptcha']
				: '0';
		}

		return $output;
	}

	public function render_checkbox_field( $args ) {
		$options = get_option( self::OPTION_NAME, array() );
		$name    = $args['name'];
		$value   = isset( $options[ $name ] ) ? $options[ $name ] : ( $args['default'] ?? '0' );
		$checked = checked( '1', $value, false );

		printf(
			'<label><input type="checkbox" name="%s[%s]" value="1" %s> %s</label>',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $name ),
			$checked,
			esc_html( $args['label'] )
		);
	}

	public function render_recaptcha_field() {
		$options = get_option( self::OPTION_NAME, array() );
		$version = $options['enable_recaptcha'] ?? '0';
		?>
		<p>
			<label>
				<input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enable_recaptcha]" value="0" <?php checked( '0', $version ); ?>>
				<?php esc_html_e( 'KHÔNG kích hoạt', 'vq-checkout' ); ?>
			</label><br>
			<label>
				<input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enable_recaptcha]" value="1" <?php checked( '1', $version ); ?>>
				<?php esc_html_e( 'Sử dụng reCAPTCHA V2', 'vq-checkout' ); ?>
			</label><br>
			<label>
				<input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enable_recaptcha]" value="2" <?php checked( '2', $version ); ?>>
				<?php esc_html_e( 'Sử dụng reCAPTCHA V3 (Khuyên dùng)', 'vq-checkout' ); ?>
			</label>
		</p>

		<table class="form-table">
			<tr class="recaptcha-v2-fields" style="display: <?php echo $version === '1' ? 'table-row' : 'none'; ?>">
				<th><?php esc_html_e( 'V2 Site Key', 'vq-checkout' ); ?></th>
				<td>
					<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[recaptcha_sitekey]"
						   value="<?php echo esc_attr( $options['recaptcha_sitekey'] ?? '' ); ?>" class="regular-text">
				</td>
			</tr>
			<tr class="recaptcha-v2-fields" style="display: <?php echo $version === '1' ? 'table-row' : 'none'; ?>">
				<th><?php esc_html_e( 'V2 Secret Key', 'vq-checkout' ); ?></th>
				<td>
					<input type="password" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[recaptcha_secretkey]"
						   value="<?php echo esc_attr( $options['recaptcha_secretkey'] ?? '' ); ?>" class="regular-text">
				</td>
			</tr>
			<tr class="recaptcha-v3-fields" style="display: <?php echo $version === '2' ? 'table-row' : 'none'; ?>">
				<th><?php esc_html_e( 'V3 Site Key', 'vq-checkout' ); ?></th>
				<td>
					<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[recaptcha_sitekey_v3]"
						   value="<?php echo esc_attr( $options['recaptcha_sitekey_v3'] ?? '' ); ?>" class="regular-text">
				</td>
			</tr>
			<tr class="recaptcha-v3-fields" style="display: <?php echo $version === '2' ? 'table-row' : 'none'; ?>">
				<th><?php esc_html_e( 'V3 Secret Key', 'vq-checkout' ); ?></th>
				<td>
					<input type="password" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[recaptcha_secretkey_v3]"
						   value="<?php echo esc_attr( $options['recaptcha_secretkey_v3'] ?? '' ); ?>" class="regular-text">
				</td>
			</tr>
		</table>

		<p>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enable_recaptcha_create_order]"
					   value="1" <?php checked( '1', $options['enable_recaptcha_create_order'] ?? '0' ); ?>>
				<?php esc_html_e( 'Kích hoạt cho tạo đơn hàng', 'vq-checkout' ); ?>
			</label>
		</p>

		<script>
		jQuery(document).ready(function($) {
			$('input[name="<?php echo esc_js( self::OPTION_NAME ); ?>[enable_recaptcha]"]').on('change', function() {
				var version = $(this).val();
				$('.recaptcha-v2-fields, .recaptcha-v3-fields').hide();
				if (version === '1') {
					$('.recaptcha-v2-fields').show();
				} else if (version === '2') {
					$('.recaptcha-v3-fields').show();
				}
			});
		});
		</script>
		<?php
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<a href="?page=vqcheckout-settings&tab=general"
				   class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Cài đặt chung', 'vq-checkout' ); ?>
				</a>
				<a href="?page=vqcheckout-settings&tab=security"
				   class="nav-tab <?php echo $active_tab === 'security' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Bảo mật', 'vq-checkout' ); ?>
				</a>
			</h2>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( 'vqcheckout-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function render_rates_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$rates_table = new Rates_Table();
		$rates_table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Quản lý Shipping Rates', 'vq-checkout' ); ?></h1>
			<div id="vqcheckout-rates-app">
				<?php $rates_table->display(); ?>
			</div>
		</div>
		<?php
	}

	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'vqcheckout' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'vqcheckout-admin',
			VQCHECKOUT_URL . 'assets/css/admin.css',
			array(),
			VQCHECKOUT_VERSION
		);

		wp_enqueue_script(
			'vqcheckout-admin',
			VQCHECKOUT_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			VQCHECKOUT_VERSION,
			true
		);

		wp_localize_script(
			'vqcheckout-admin',
			'vqCheckoutAdmin',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'restUrl'   => rest_url( 'vqcheckout/v1' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'i18n'      => array(
					'confirmDelete' => __( 'Bạn có chắc muốn xóa rate này?', 'vq-checkout' ),
					'saved'         => __( 'Đã lưu', 'vq-checkout' ),
					'error'         => __( 'Có lỗi xảy ra', 'vq-checkout' ),
				),
			)
		);
	}
}

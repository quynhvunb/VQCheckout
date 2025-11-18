<?php
/**
 * Admin Settings Page
 *
 * @package VQCheckout\Admin
 */

namespace VQCheckout\Admin;

defined( 'ABSPATH' ) || exit;

class Settings_Page {
	private $container;

	public function __construct( $container ) {
		$this->container = $container;
	}

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_menu() {
		// Add as WooCommerce submenu
		add_submenu_page(
			'woocommerce',
			__( 'VQ Checkout Settings', 'vq-checkout' ),
			__( 'VQ Checkout', 'vq-checkout' ),
			'manage_woocommerce',
			'vq-checkout-settings',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'woocommerce_page_vq-checkout-settings' !== $hook ) {
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
			array( 'jquery' ),
			VQCHECKOUT_VERSION,
			true
		);
	}

	public function register_settings() {
		// General tab
		register_setting( 'vqcheckout_general', 'vqcheckout_general_settings' );

		// Checkout tab
		register_setting( 'vqcheckout_checkout', 'vqcheckout_checkout_settings' );

		// Security tab
		register_setting( 'vqcheckout_security', 'vqcheckout_security_settings' );

		// Display tab
		register_setting( 'vqcheckout_display', 'vqcheckout_display_settings' );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		?>
		<div class="wrap vqcheckout-settings-wrap">
			<h1><?php esc_html_e( 'VQ Checkout Settings', 'vq-checkout' ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<a href="?page=vq-checkout-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Cài đặt chung', 'vq-checkout' ); ?>
				</a>
				<a href="?page=vq-checkout-settings&tab=checkout" class="nav-tab <?php echo $active_tab === 'checkout' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Checkout Field', 'vq-checkout' ); ?>
				</a>
				<a href="?page=vq-checkout-settings&tab=display" class="nav-tab <?php echo $active_tab === 'display' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Hiển thị', 'vq-checkout' ); ?>
				</a>
				<a href="?page=vq-checkout-settings&tab=security" class="nav-tab <?php echo $active_tab === 'security' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Bảo mật', 'vq-checkout' ); ?>
				</a>
				<a href="?page=vq-checkout-settings&tab=advanced" class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Nâng cao', 'vq-checkout' ); ?>
				</a>
			</h2>

			<form method="post" action="options.php">
				<?php
				switch ( $active_tab ) {
					case 'checkout':
						$this->render_checkout_tab();
						break;
					case 'display':
						$this->render_display_tab();
						break;
					case 'security':
						$this->render_security_tab();
						break;
					case 'advanced':
						$this->render_advanced_tab();
						break;
					case 'general':
					default:
						$this->render_general_tab();
						break;
				}
				?>
			</form>
		</div>
		<?php
	}

	private function render_general_tab() {
		settings_fields( 'vqcheckout_general' );
		$options = get_option( 'vqcheckout_general_settings', array() );
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Kích hoạt VQ Checkout', 'vq-checkout' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="vqcheckout_general_settings[enabled]" value="1" <?php checked( isset( $options['enabled'] ) ? $options['enabled'] : 0, 1 ); ?> />
						<?php esc_html_e( 'Bật tất cả tính năng VQ Checkout', 'vq-checkout' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
		<?php
	}

	private function render_checkout_tab() {
		settings_fields( 'vqcheckout_checkout' );
		$options = get_option( 'vqcheckout_checkout_settings', array() );
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Định dạng SDT ở VN', 'vq-checkout' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="vqcheckout_checkout_settings[vn_phone_format]" value="1" <?php checked( isset( $options['vn_phone_format'] ) ? $options['vn_phone_format'] : 1, 1 ); ?> />
						<?php esc_html_e( 'Bắt buộc SĐT sử dụng định dạng ở VN', 'vq-checkout' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Định dạng: +84xxxxxxxxx hoặc 0xxxxxxxxx', 'vq-checkout' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Hiển thị trường country và last name', 'vq-checkout' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="vqcheckout_checkout_settings[show_country_lastname]" value="1" <?php checked( isset( $options['show_country_lastname'] ) ? $options['show_country_lastname'] : 0, 1 ); ?> />
						<?php esc_html_e( 'Hiển thị trường country và last name', 'vq-checkout' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Hiển thị postcode cho Việt Nam', 'vq-checkout' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="vqcheckout_checkout_settings[show_postcode]" value="1" <?php checked( isset( $options['show_postcode'] ) ? $options['show_postcode'] : 0, 1 ); ?> />
						<?php esc_html_e( 'Hiển thị trường postcode cho Việt Nam', 'vq-checkout' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Xung hô', 'vq-checkout' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="vqcheckout_checkout_settings[enable_salutation]" value="1" <?php checked( isset( $options['enable_salutation'] ) ? $options['enable_salutation'] : 0, 1 ); ?> />
						<?php esc_html_e( 'Hiển thị mục chọn cách xưng hô Anh/Chị', 'vq-checkout' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Tự điền địa chỉ theo SĐT', 'vq-checkout' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="vqcheckout_checkout_settings[autofill_by_phone]" value="1" <?php checked( isset( $options['autofill_by_phone'] ) ? $options['autofill_by_phone'] : 0, 1 ); ?> />
						<?php esc_html_e( 'Tự động điền địa chỉ dựa trên số điện thoại từ đơn hàng cũ', 'vq-checkout' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
		<?php
	}

	private function render_display_tab() {
		settings_fields( 'vqcheckout_display' );
		$options = get_option( 'vqcheckout_display_settings', array() );
		?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Chuyển giá sang dạng chữ', 'vq-checkout' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="vqcheckout_display_settings[convert_currency_text]" value="1" <?php checked( isset( $options['convert_currency_text'] ) ? $options['convert_currency_text'] : 0, 1 ); ?> />
						<?php esc_html_e( 'Chuyển ký hiệu ₫ thành VNĐ', 'vq-checkout' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Loại bỏ tiêu đề vận chuyển', 'vq-checkout' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="vqcheckout_display_settings[hide_shipping_title]" value="1" <?php checked( isset( $options['hide_shipping_title'] ) ? $options['hide_shipping_title'] : 0, 1 ); ?> />
						<?php esc_html_e( 'Loại bỏ hoàn toàn tiêu đề của phương thức vận chuyển', 'vq-checkout' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Ẩn phương thức khi có free-shipping', 'vq-checkout' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="vqcheckout_display_settings[hide_when_freeship]" value="1" <?php checked( isset( $options['hide_when_freeship'] ) ? $options['hide_when_freeship'] : 0, 1 ); ?> />
						<?php esc_html_e( 'Ẩn tất cả phương thức khi có miễn phí vận chuyển', 'vq-checkout' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
		<?php
	}

	private function render_security_tab() {
		settings_fields( 'vqcheckout_security' );
		$options = get_option( 'vqcheckout_security_settings', array() );
		?>
		<h2><?php esc_html_e( 'Cấu hình Google reCAPTCHA', 'vq-checkout' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Kích hoạt', 'vq-checkout' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="radio" name="vqcheckout_security_settings[recaptcha_version]" value="disabled" <?php checked( isset( $options['recaptcha_version'] ) ? $options['recaptcha_version'] : 'disabled', 'disabled' ); ?> />
							<?php esc_html_e( 'KHÔNG kích hoạt', 'vq-checkout' ); ?>
						</label><br>
						<label>
							<input type="radio" name="vqcheckout_security_settings[recaptcha_version]" value="v2" <?php checked( isset( $options['recaptcha_version'] ) ? $options['recaptcha_version'] : '', 'v2' ); ?> />
							<?php esc_html_e( 'Sử dụng Google reCAPTCHA V2', 'vq-checkout' ); ?>
						</label><br>
						<label>
							<input type="radio" name="vqcheckout_security_settings[recaptcha_version]" value="v3" <?php checked( isset( $options['recaptcha_version'] ) ? $options['recaptcha_version'] : '', 'v3' ); ?> />
							<?php esc_html_e( 'Sử dụng Google reCAPTCHA V3 (khuyến dùng)', 'vq-checkout' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Site Key', 'vq-checkout' ); ?></th>
				<td>
					<input type="text" name="vqcheckout_security_settings[recaptcha_site_key]" value="<?php echo esc_attr( isset( $options['recaptcha_site_key'] ) ? $options['recaptcha_site_key'] : '' ); ?>" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Secret Key', 'vq-checkout' ); ?></th>
				<td>
					<input type="password" name="vqcheckout_security_settings[recaptcha_secret_key]" value="<?php echo esc_attr( isset( $options['recaptcha_secret_key'] ) ? $options['recaptcha_secret_key'] : '' ); ?>" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Kích hoạt cho', 'vq-checkout' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="vqcheckout_security_settings[recaptcha_on_checkout]" value="1" <?php checked( isset( $options['recaptcha_on_checkout'] ) ? $options['recaptcha_on_checkout'] : 1, 1 ); ?> />
						<?php esc_html_e( 'Kích hoạt cho lúc đặt hàng', 'vq-checkout' ); ?>
					</label><br>
					<label>
						<input type="checkbox" name="vqcheckout_security_settings[recaptcha_on_payment]" value="1" <?php checked( isset( $options['recaptcha_on_payment'] ) ? $options['recaptcha_on_payment'] : 0, 1 ); ?> />
						<?php esc_html_e( 'Kích hoạt cho lúc thanh toán', 'vq-checkout' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Nâng cao', 'vq-checkout' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Chặn order với các IP', 'vq-checkout' ); ?></th>
				<td>
					<textarea name="vqcheckout_security_settings[blocked_ips]" rows="5" class="large-text"><?php echo esc_textarea( isset( $options['blocked_ips'] ) ? $options['blocked_ips'] : '' ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Mỗi địa chỉ IP trên một dòng. Nếu có IP này thêm dấng "*" vào đây, ví dụ: 192.168.1.*', 'vq-checkout' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Chặn order với các từ khóa', 'vq-checkout' ); ?></th>
				<td>
					<textarea name="vqcheckout_security_settings[blocked_keywords]" rows="5" class="large-text"><?php echo esc_textarea( isset( $options['blocked_keywords'] ) ? $options['blocked_keywords'] : '' ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Từ khóa cần theo dõi, đặc biệt email và số điện thoại. Mỗi từ khóa trên một dòng.', 'vq-checkout' ); ?></p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
		<?php
	}

	private function render_advanced_tab() {
		?>
		<h2><?php esc_html_e( 'Nâng cao', 'vq-checkout' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Import / Export Settings', 'vq-checkout' ); ?></th>
				<td>
					<p><?php esc_html_e( 'Coming soon: Import và export cấu hình', 'vq-checkout' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}
}

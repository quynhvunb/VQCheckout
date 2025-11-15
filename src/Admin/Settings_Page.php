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
	}

	public function add_menu() {
		add_menu_page(
			__( 'VQ Checkout', 'vq-checkout' ),
			__( 'VQ Checkout', 'vq-checkout' ),
			'manage_woocommerce',
			'vq-checkout',
			array( $this, 'render_page' ),
			'dashicons-cart',
			56
		);
	}

	public function register_settings() {
		register_setting( 'vqcheckout_general', 'vqcheckout_settings' );

		add_settings_section(
			'vqcheckout_general_section',
			__( 'General Settings', 'vq-checkout' ),
			null,
			'vqcheckout_general'
		);

		add_settings_field(
			'vqcheckout_enable',
			__( 'Enable VQ Checkout', 'vq-checkout' ),
			array( $this, 'render_enable_field' ),
			'vqcheckout_general',
			'vqcheckout_general_section'
		);
	}

	public function render_enable_field() {
		$options = get_option( 'vqcheckout_settings', array() );
		$enabled = isset( $options['enable'] ) ? $options['enable'] : 1;
		?>
		<label>
			<input type="checkbox" name="vqcheckout_settings[enable]" value="1" <?php checked( $enabled, 1 ); ?> />
			<?php esc_html_e( 'Enable VQ Checkout features', 'vq-checkout' ); ?>
		</label>
		<?php
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<a href="?page=vq-checkout&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'General', 'vq-checkout' ); ?>
				</a>
				<a href="?page=vq-checkout&tab=shipping" class="nav-tab <?php echo $active_tab === 'shipping' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Shipping Rates', 'vq-checkout' ); ?>
				</a>
				<a href="?page=vq-checkout&tab=checkout" class="nav-tab <?php echo $active_tab === 'checkout' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Checkout', 'vq-checkout' ); ?>
				</a>
				<a href="?page=vq-checkout&tab=security" class="nav-tab <?php echo $active_tab === 'security' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Security', 'vq-checkout' ); ?>
				</a>
			</h2>

			<form method="post" action="options.php">
				<?php
				if ( $active_tab === 'general' ) {
					settings_fields( 'vqcheckout_general' );
					do_settings_sections( 'vqcheckout_general' );
					submit_button();
				} elseif ( $active_tab === 'shipping' ) {
					$this->render_shipping_tab();
				} elseif ( $active_tab === 'checkout' ) {
					$this->render_checkout_tab();
				} elseif ( $active_tab === 'security' ) {
					$this->render_security_tab();
				}
				?>
			</form>
		</div>
		<?php
	}

	private function render_shipping_tab() {
		?>
		<div class="vqcheckout-shipping-rates">
			<h2><?php esc_html_e( 'Ward-based Shipping Rates', 'vq-checkout' ); ?></h2>
			<p><?php esc_html_e( 'Manage shipping rates by ward/commune. Coming soon.', 'vq-checkout' ); ?></p>
		</div>
		<?php
	}

	private function render_checkout_tab() {
		?>
		<h2><?php esc_html_e( 'Checkout Settings', 'vq-checkout' ); ?></h2>
		<p><?php esc_html_e( 'Configure checkout page options. Coming soon.', 'vq-checkout' ); ?></p>
		<?php
	}

	private function render_security_tab() {
		?>
		<h2><?php esc_html_e( 'Security Settings', 'vq-checkout' ); ?></h2>
		<p><?php esc_html_e( 'Configure reCAPTCHA and rate limiting. Coming soon.', 'vq-checkout' ); ?></p>
		<?php
	}
}

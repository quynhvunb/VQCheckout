<?php
/**
 * Plugin Name: VQ Checkout for Woo
 * Plugin URI: https://github.com/quynhvunb/vq-checkout
 * Description: Tối ưu trang thanh toán WooCommerce cho thị trường Việt Nam với phí vận chuyển tới cấp xã/phường
 * Version: 1.0.0
 * Author: Vũ Quynh
 * Author URI: https://quynhvu.com
 * Text Domain: vq-checkout
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'VQCHECKOUT_VERSION', '1.0.0' );
define( 'VQCHECKOUT_FILE', __FILE__ );
define( 'VQCHECKOUT_PATH', plugin_dir_path( __FILE__ ) );
define( 'VQCHECKOUT_URL', plugin_dir_url( __FILE__ ) );
define( 'VQCHECKOUT_BASENAME', plugin_basename( __FILE__ ) );

if ( ! class_exists( 'VQCheckout_Bootstrap' ) ) {
	/**
	 * Bootstrap class
	 */
	final class VQCheckout_Bootstrap {
		private static $instance = null;

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			$this->check_requirements();
			$this->load_autoloader();
			$this->init();
		}

		private function check_requirements() {
			if ( ! class_exists( 'WooCommerce' ) ) {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
				return;
			}

			if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
				add_action( 'admin_notices', array( $this, 'php_version_notice' ) );
				return;
			}
		}

		public function woocommerce_missing_notice() {
			echo '<div class="error"><p>';
			echo esc_html__( 'VQ Checkout for Woo requires WooCommerce to be installed and active.', 'vq-checkout' );
			echo '</p></div>';
		}

		public function php_version_notice() {
			echo '<div class="error"><p>';
			printf(
				/* translators: %s: PHP version */
				esc_html__( 'VQ Checkout for Woo requires PHP 7.4 or higher. You are running version %s.', 'vq-checkout' ),
				esc_html( PHP_VERSION )
			);
			echo '</p></div>';
		}

		private function load_autoloader() {
			$autoload = VQCHECKOUT_PATH . 'vendor/autoload.php';
			if ( file_exists( $autoload ) ) {
				require_once $autoload;
			}
		}

		private function init() {
			if ( ! class_exists( 'WooCommerce' ) ) {
				return;
			}

			add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
			add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );

			// Initialize plugin core after plugins are loaded
			add_action( 'plugins_loaded', array( $this, 'init_plugin' ), 20 );
		}

		public function init_plugin() {
			if ( ! class_exists( 'WooCommerce' ) ) {
				return;
			}

			if ( class_exists( 'VQCheckout\\Core\\Plugin' ) ) {
				try {
					\VQCheckout\Core\Plugin::instance();
				} catch ( \Exception $e ) {
					add_action( 'admin_notices', function() use ( $e ) {
						echo '<div class="error"><p>';
						echo esc_html__( 'VQ Checkout error: ', 'vq-checkout' ) . esc_html( $e->getMessage() );
						echo '</p></div>';
					} );

					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'VQ Checkout init error: ' . $e->getMessage() );
						error_log( $e->getTraceAsString() );
					}
				}
			} else {
				add_action( 'admin_notices', array( $this, 'plugin_class_missing_notice' ) );
			}
		}

		public function plugin_class_missing_notice() {
			echo '<div class="error"><p>';
			echo esc_html__( 'VQ Checkout error: Core plugin class not found. Please run "composer install" or reinstall the plugin.', 'vq-checkout' );
			echo '</p></div>';
		}

		public function load_textdomain() {
			load_plugin_textdomain(
				'vq-checkout',
				false,
				dirname( VQCHECKOUT_BASENAME ) . '/languages'
			);
		}

		public function declare_hpos_compatibility() {
			if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				return;
			}

			// Declare HPOS (High-Performance Order Storage) compatibility
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				VQCHECKOUT_FILE,
				true
			);

			// Declare Cart and Checkout Blocks compatibility
			// Only declare if blocks package exists
			if ( class_exists( 'Automattic\WooCommerce\Blocks\Package' ) ||
			     class_exists( 'Automattic\WooCommerce\StoreApi\StoreApi' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
					'cart_checkout_blocks',
					VQCHECKOUT_FILE,
					true
				);
			}
		}
	}
}

VQCheckout_Bootstrap::instance();

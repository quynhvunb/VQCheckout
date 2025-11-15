<?php
/**
 * Hooks Registration
 *
 * @package VQCheckout\Core
 */

namespace VQCheckout\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Central hooks manager
 */
class Hooks {
	private $container;

	public function __construct( Service_Container $container ) {
		$this->container = $container;
	}

	public function init() {
		add_action( 'init', array( $this, 'init_migrations' ) );
		add_action( 'init', array( $this, 'init_currency' ) );
		add_action( 'init', array( $this, 'init_blocks' ) );
		add_action( 'init', array( $this, 'init_p2_features' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_filter( 'woocommerce_shipping_methods', array( $this, 'register_shipping_methods' ) );
		add_action( 'admin_menu', array( $this, 'init_admin' ) );
		add_action( 'wp', array( $this, 'init_checkout' ) );
	}

	public function init_migrations() {
		if ( ! get_option( 'vqcheckout_db_version' ) || get_option( 'vqcheckout_db_version' ) !== VQCHECKOUT_VERSION ) {
			$migrations = new \VQCheckout\Data\Migrations();
			$migrations->run();
		}
	}

	public function register_rest_routes() {
		$address_controller = new \VQCheckout\API\Address_Controller();
		$address_controller->register_routes();

		$rate_controller = new \VQCheckout\API\Rate_Controller( $this->container );
		$rate_controller->register_routes();

		$admin_controller = new \VQCheckout\API\Admin_Controller( $this->container );
		$admin_controller->register_routes();

		$phone_controller = new \VQCheckout\API\Phone_Controller();
		$phone_controller->register_routes();
	}

	public function register_shipping_methods( $methods ) {
		$methods['vqcheckout_ward_rate'] = 'VQCheckout\\Shipping\\WC_Method';
		return $methods;
	}

	public function init_currency() {
		$currency = new \VQCheckout\Checkout\Currency();
		$currency->init();
	}

	public function init_blocks() {
		$blocks = new \VQCheckout\Checkout\Blocks_Integration();
		$blocks->init();
	}

	public function init_p2_features() {
		// Performance Monitor
		$monitor = new \VQCheckout\Performance\Monitor();
		$monitor->init();

		// Cache Preheater
		$preheater = new \VQCheckout\Performance\Cache_Preheater();
		$preheater->init();

		// Multi-Currency
		$multi_currency = new \VQCheckout\Checkout\Multi_Currency();
		$multi_currency->init();

		// Analytics Tracker
		$tracker = new \VQCheckout\Analytics\Tracker();
		$tracker->init();
	}

	public function init_admin() {
		if ( ! is_admin() ) {
			return;
		}

		$settings_page = new \VQCheckout\Admin\Settings_Page();
		$settings_page->init();

		$assets = new \VQCheckout\Admin\Assets();
		$assets->init();

		$order_meta = new \VQCheckout\Admin\Order_Meta();
		$order_meta->init();

		$analytics_dashboard = new \VQCheckout\Admin\Analytics_Dashboard();
		$analytics_dashboard->init();
	}

	public function init_checkout() {
		if ( is_admin() ) {
			return;
		}

		$fields = new \VQCheckout\Checkout\Fields();
		$fields->init();

		$session = new \VQCheckout\Checkout\Session();
		$session->init();

		$handler = new \VQCheckout\Checkout\Handler();
		$handler->init();
	}
}

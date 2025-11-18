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
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_filter( 'woocommerce_shipping_methods', array( $this, 'register_shipping_methods' ) );

		// Init admin early
		$this->init_admin();
	}

	public function init_admin() {
		$settings_page = $this->container->get( 'settings_page' );
		$settings_page->init();
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
	}

	public function register_shipping_methods( $methods ) {
		$methods['vqcheckout_ward_rate'] = 'VQCheckout\\Shipping\\WC_Method';
		return $methods;
	}
}

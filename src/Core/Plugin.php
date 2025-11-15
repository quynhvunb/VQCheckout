<?php
/**
 * Main Plugin Class
 *
 * @package VQCheckout\Core
 */

namespace VQCheckout\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin singleton
 */
final class Plugin {
	private static $instance = null;
	private $container;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->container = new Service_Container();
		$this->register_services();
		$this->init_hooks();
	}

	private function register_services() {
		$this->container->register( 'cache', function() {
			return new \VQCheckout\Cache\Cache();
		} );

		$this->container->register( 'location_repository', function( $c ) {
			return new \VQCheckout\Shipping\Location_Repository( $c->get( 'cache' ) );
		} );

		$this->container->register( 'rate_repository', function( $c ) {
			return new \VQCheckout\Shipping\Rate_Repository( $c->get( 'cache' ) );
		} );
	}

	private function init_hooks() {
		$hooks = new Hooks( $this->container );
		$hooks->init();
	}

	public function get_container() {
		return $this->container;
	}

	public function get( $service ) {
		return $this->container->get( $service );
	}
}

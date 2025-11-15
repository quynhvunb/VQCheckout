<?php
/**
 * Service Container
 *
 * @package VQCheckout\Core
 */

namespace VQCheckout\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Simple DI container
 */
class Service_Container {
	private $services = array();
	private $instances = array();

	public function register( $name, $resolver ) {
		$this->services[ $name ] = $resolver;
	}

	public function get( $name ) {
		if ( ! isset( $this->services[ $name ] ) ) {
			throw new \Exception( sprintf( 'Service "%s" not found', $name ) );
		}

		if ( ! isset( $this->instances[ $name ] ) ) {
			$this->instances[ $name ] = call_user_func( $this->services[ $name ], $this );
		}

		return $this->instances[ $name ];
	}

	public function has( $name ) {
		return isset( $this->services[ $name ] );
	}
}

<?php
/**
 * Rate REST Controller
 *
 * @package VQCheckout\API
 */

namespace VQCheckout\API;

use VQCheckout\Core\Service_Container;
use VQCheckout\Shipping\Resolver;

defined( 'ABSPATH' ) || exit;

/**
 * Endpoint for resolving shipping rates
 */
class Rate_Controller extends \WP_REST_Controller {
	protected $namespace = 'vqcheckout/v1';
	private $container;

	public function __construct( Service_Container $container ) {
		$this->container = $container;
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/rates/resolve',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'resolve_rate' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'instance_id'   => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'ward_code'     => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'cart_subtotal' => array(
						'required'          => true,
						'sanitize_callback' => 'floatval',
					),
				),
			)
		);
	}

	public function resolve_rate( $request ) {
		$instance_id   = $request->get_param( 'instance_id' );
		$ward_code     = $request->get_param( 'ward_code' );
		$cart_subtotal = $request->get_param( 'cart_subtotal' );

		$cache     = $this->container->get( 'cache' );
		$rate_repo = $this->container->get( 'rate_repository' );
		$resolver  = new Resolver( $cache, $rate_repo );

		$result = $resolver->resolve( $instance_id, $ward_code, $cart_subtotal );

		return rest_ensure_response( $result );
	}
}

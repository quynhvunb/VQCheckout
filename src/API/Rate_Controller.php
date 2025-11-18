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
		// POST /rates/resolve - Resolve shipping rate (public)
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

		// GET /rates - List rates for instance (admin)
		register_rest_route(
			$this->namespace,
			'/rates',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_rates' ),
				'permission_callback' => array( $this, 'admin_permission' ),
				'args'                => array(
					'instance_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// POST /rates - Create rate (admin)
		register_rest_route(
			$this->namespace,
			'/rates',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_rate' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);

		// GET /rates/{id} - Get single rate (admin)
		register_rest_route(
			$this->namespace,
			'/rates/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_rate' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);

		// PUT /rates/{id} - Update rate (admin)
		register_rest_route(
			$this->namespace,
			'/rates/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_rate' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);

		// DELETE /rates/{id} - Delete rate (admin)
		register_rest_route(
			$this->namespace,
			'/rates/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_rate' ),
				'permission_callback' => array( $this, 'admin_permission' ),
			)
		);

		// POST /rates/bulk - Bulk update order (admin)
		register_rest_route(
			$this->namespace,
			'/rates/bulk',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'bulk_update' ),
				'permission_callback' => array( $this, 'admin_permission' ),
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

	public function list_rates( $request ) {
		$instance_id = $request->get_param( 'instance_id' );

		$rate_repo = $this->container->get( 'rate_repository' );
		$rates     = $rate_repo->get_rates_by_instance( $instance_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $rates,
				'count'   => count( $rates ),
			)
		);
	}

	public function create_rate( $request ) {
		$data = $request->get_json_params();

		if ( empty( $data ) ) {
			return new \WP_Error(
				'missing_data',
				__( 'Rate data is required.', 'vq-checkout' ),
				array( 'status' => 400 )
			);
		}

		$sanitizer = $this->container->get( 'sanitizer' );
		$validator = $this->container->get( 'validator' );

		$data = $sanitizer->sanitize_rate_data( $data );

		$validation = $validator->validate_rate_data( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$rate_repo = $this->container->get( 'rate_repository' );
		$rate_id   = $rate_repo->create_rate( $data );

		if ( ! $rate_id ) {
			return new \WP_Error(
				'create_failed',
				__( 'Failed to create rate.', 'vq-checkout' ),
				array( 'status' => 500 )
			);
		}

		$rate = $rate_repo->get_rate( $rate_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $rate,
			)
		);
	}

	public function get_rate( $request ) {
		$rate_id = $request->get_param( 'id' );

		$rate_repo = $this->container->get( 'rate_repository' );
		$rate      = $rate_repo->get_rate( $rate_id );

		if ( ! $rate ) {
			return new \WP_Error(
				'not_found',
				__( 'Rate not found.', 'vq-checkout' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $rate,
			)
		);
	}

	public function update_rate( $request ) {
		$rate_id = $request->get_param( 'id' );
		$data    = $request->get_json_params();

		if ( empty( $data ) ) {
			return new \WP_Error(
				'missing_data',
				__( 'Rate data is required.', 'vq-checkout' ),
				array( 'status' => 400 )
			);
		}

		$rate_repo = $this->container->get( 'rate_repository' );
		$existing  = $rate_repo->get_rate( $rate_id );

		if ( ! $existing ) {
			return new \WP_Error(
				'not_found',
				__( 'Rate not found.', 'vq-checkout' ),
				array( 'status' => 404 )
			);
		}

		$sanitizer = $this->container->get( 'sanitizer' );
		$validator = $this->container->get( 'validator' );

		$data = $sanitizer->sanitize_rate_data( $data );

		$validation = $validator->validate_rate_data( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$rate_repo->update_rate( $rate_id, $data );

		$rate = $rate_repo->get_rate( $rate_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $rate,
			)
		);
	}

	public function delete_rate( $request ) {
		$rate_id = $request->get_param( 'id' );

		$rate_repo = $this->container->get( 'rate_repository' );
		$existing  = $rate_repo->get_rate( $rate_id );

		if ( ! $existing ) {
			return new \WP_Error(
				'not_found',
				__( 'Rate not found.', 'vq-checkout' ),
				array( 'status' => 404 )
			);
		}

		$rate_repo->delete_rate( $rate_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Rate deleted successfully.', 'vq-checkout' ),
			)
		);
	}

	public function bulk_update( $request ) {
		$data = $request->get_json_params();

		if ( empty( $data['rates'] ) || ! is_array( $data['rates'] ) ) {
			return new \WP_Error(
				'missing_rates',
				__( 'Rates array is required.', 'vq-checkout' ),
				array( 'status' => 400 )
			);
		}

		$rate_repo = $this->container->get( 'rate_repository' );

		foreach ( $data['rates'] as $update ) {
			if ( ! isset( $update['rate_id'], $update['rate_order'] ) ) {
				continue;
			}

			$rate_repo->update_rate(
				$update['rate_id'],
				array( 'rate_order' => absint( $update['rate_order'] ) )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Rates updated successfully.', 'vq-checkout' ),
			)
		);
	}

	public function admin_permission( $request ) {
		return current_user_can( 'manage_woocommerce' );
	}
}

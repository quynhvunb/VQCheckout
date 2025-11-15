<?php
/**
 * Address REST Controller
 *
 * @package VQCheckout\API
 */

namespace VQCheckout\API;

use VQCheckout\Core\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Endpoints for provinces/districts/wards
 */
class Address_Controller extends \WP_REST_Controller {
	protected $namespace = 'vqcheckout/v1';

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/address/provinces',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_provinces' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/address/districts',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_districts' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'province' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/address/wards',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_wards' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'district' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	public function get_provinces( $request ) {
		$plugin = Plugin::instance();
		$repo   = $plugin->get( 'location_repository' );

		$provinces = $repo->get_provinces();

		return rest_ensure_response( $provinces );
	}

	public function get_districts( $request ) {
		$province_code = $request->get_param( 'province' );

		$plugin = Plugin::instance();
		$repo   = $plugin->get( 'location_repository' );

		$districts = $repo->get_districts( $province_code );

		return rest_ensure_response( $districts );
	}

	public function get_wards( $request ) {
		$district_code = $request->get_param( 'district' );

		$plugin = Plugin::instance();
		$repo   = $plugin->get( 'location_repository' );

		$wards = $repo->get_wards( $district_code );

		return rest_ensure_response( $wards );
	}
}

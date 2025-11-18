<?php
/**
 * Address REST Controller
 *
 * @package VQCheckout\API
 */

namespace VQCheckout\API;

use VQCheckout\Core\Plugin;
use VQCheckout\Shipping\Location_Repository;
use VQCheckout\Security\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Endpoints for provinces/districts/wards
 */
class Address_Controller extends \WP_REST_Controller {
	protected $namespace = 'vqcheckout/v1';
	private $location_repo;
	private $sanitizer;

	public function __construct( Location_Repository $location_repo, Sanitizer $sanitizer ) {
		$this->location_repo = $location_repo;
		$this->sanitizer     = $sanitizer;
	}

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
						'sanitize_callback' => array( $this->sanitizer, 'sanitize_location_code' ),
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
						'sanitize_callback' => array( $this->sanitizer, 'sanitize_location_code' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/address/search',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search_wards' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'q' => array(
						'required'          => true,
						'sanitize_callback' => array( $this->sanitizer, 'sanitize_search' ),
					),
					'limit' => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public function get_provinces( $request ) {
		try {
			$provinces = $this->location_repo->get_provinces();

			return rest_ensure_response(
				array(
					'success' => true,
					'data'    => $provinces,
					'count'   => count( $provinces ),
				)
			);
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'vqcheckout_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	public function get_districts( $request ) {
		$province_code = $request->get_param( 'province' );

		if ( empty( $province_code ) ) {
			return new \WP_Error(
				'missing_province',
				__( 'Province code is required.', 'vq-checkout' ),
				array( 'status' => 400 )
			);
		}

		try {
			$districts = $this->location_repo->get_districts( $province_code );

			return rest_ensure_response(
				array(
					'success' => true,
					'data'    => $districts,
					'count'   => count( $districts ),
				)
			);
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'vqcheckout_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	public function get_wards( $request ) {
		$district_code = $request->get_param( 'district' );

		if ( empty( $district_code ) ) {
			return new \WP_Error(
				'missing_district',
				__( 'District code is required.', 'vq-checkout' ),
				array( 'status' => 400 )
			);
		}

		try {
			$wards = $this->location_repo->get_wards( $district_code );

			return rest_ensure_response(
				array(
					'success' => true,
					'data'    => $wards,
					'count'   => count( $wards ),
				)
			);
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'vqcheckout_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	public function search_wards( $request ) {
		$query = $request->get_param( 'q' );
		$limit = $request->get_param( 'limit' );

		if ( empty( $query ) ) {
			return new \WP_Error(
				'missing_query',
				__( 'Search query is required.', 'vq-checkout' ),
				array( 'status' => 400 )
			);
		}

		try {
			$results = $this->location_repo->search_wards( $query, $limit );

			return rest_ensure_response(
				array(
					'success' => true,
					'data'    => $results,
					'count'   => count( $results ),
				)
			);
		} catch ( \Exception $e ) {
			return new \WP_Error(
				'vqcheckout_error',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}
}

<?php
/**
 * Nonce - REST API nonce management
 *
 * @package VQCheckout\Security
 */

namespace VQCheckout\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Centralized nonce verification for REST API
 */
class Nonce {
	const NONCE_ACTION = 'vqcheckout_rest';
	const NONCE_FIELD  = 'vqcheckout_nonce';

	/**
	 * Generate nonce for REST API
	 *
	 * @return string
	 */
	public function create() {
		return wp_create_nonce( self::NONCE_ACTION );
	}

	/**
	 * Verify nonce from request
	 *
	 * @param \WP_REST_Request $request REST request object
	 * @return bool
	 */
	public function verify( $request ) {
		$nonce = $this->get_nonce_from_request( $request );

		if ( empty( $nonce ) ) {
			return false;
		}

		return (bool) wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}

	/**
	 * Get nonce from REST request (header or parameter)
	 *
	 * @param \WP_REST_Request $request REST request
	 * @return string|null
	 */
	private function get_nonce_from_request( $request ) {
		// Try header first (preferred)
		$nonce = $request->get_header( 'X-VQCheckout-Nonce' );

		if ( ! empty( $nonce ) ) {
			return sanitize_text_field( $nonce );
		}

		// Fallback to query parameter or body parameter
		$nonce = $request->get_param( self::NONCE_FIELD );

		if ( ! empty( $nonce ) ) {
			return sanitize_text_field( $nonce );
		}

		return null;
	}

	/**
	 * Check if user is logged in (for protected endpoints)
	 *
	 * @return bool
	 */
	public function is_user_logged_in() {
		return is_user_logged_in();
	}

	/**
	 * Check if current user has admin capability
	 *
	 * @return bool
	 */
	public function user_can_manage() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Permission callback for public REST endpoints
	 *
	 * @param \WP_REST_Request $request REST request
	 * @return bool|\WP_Error
	 */
	public function public_permission_callback( $request ) {
		// Public endpoints don't require nonce for GET
		if ( 'GET' === $request->get_method() ) {
			return true;
		}

		// POST/PUT/DELETE require nonce
		if ( ! $this->verify( $request ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Invalid security token.', 'vq-checkout' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Permission callback for admin-only REST endpoints
	 *
	 * @param \WP_REST_Request $request REST request
	 * @return bool|\WP_Error
	 */
	public function admin_permission_callback( $request ) {
		// Check user capability
		if ( ! $this->user_can_manage() ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this resource.', 'vq-checkout' ),
				array( 'status' => 403 )
			);
		}

		// Verify nonce
		if ( ! $this->verify( $request ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Invalid security token.', 'vq-checkout' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Localize nonce for JavaScript
	 *
	 * @return array
	 */
	public function get_nonce_data() {
		return array(
			'nonce'  => $this->create(),
			'action' => self::NONCE_ACTION,
			'field'  => self::NONCE_FIELD,
		);
	}

	/**
	 * Add nonce to AJAX response headers
	 *
	 * @param mixed $response Response data
	 * @return mixed
	 */
	public function add_nonce_header( $response ) {
		if ( $response instanceof \WP_REST_Response ) {
			$response->header( 'X-VQCheckout-Nonce', $this->create() );
		}

		return $response;
	}
}

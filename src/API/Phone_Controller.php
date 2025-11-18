<?php
/**
 * Phone REST API Controller
 *
 * @package VQCheckout\API
 */

namespace VQCheckout\API;

use VQCheckout\Utils\Phone;
use VQCheckout\Utils\Validation;
use VQCheckout\Security\Sanitizer;
use VQCheckout\Security\RateLimiter;

defined( 'ABSPATH' ) || exit;

/**
 * Phone lookup API for address auto-fill (privacy-by-design)
 */
class Phone_Controller extends \WP_REST_Controller {
	protected $namespace = 'vqcheckout/v1';
	private $sanitizer;
	private $rate_limiter;

	public function __construct( Sanitizer $sanitizer, RateLimiter $rate_limiter ) {
		$this->sanitizer    = $sanitizer;
		$this->rate_limiter = $rate_limiter;
	}

	public function register_routes() {
		// POST /phone/lookup - Look up address by phone
		register_rest_route(
			$this->namespace,
			'/phone/lookup',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'lookup_address' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'phone' => array(
						'required'          => true,
						'sanitize_callback' => array( $this->sanitizer, 'sanitize_phone' ),
						'validate_callback' => array( $this, 'validate_phone' ),
					),
				),
			)
		);
	}

	/**
	 * Validate phone number
	 *
	 * @param string $phone Phone number
	 * @return bool
	 */
	public function validate_phone( $phone ) {
		return Phone::validate( $phone );
	}

	/**
	 * Lookup address by phone number
	 * Privacy-by-design: Only return address if user has previous orders
	 *
	 * @param \WP_REST_Request $request Request object
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function lookup_address( $request ) {
		// Rate limiting
		if ( ! $this->rate_limiter->is_allowed( 'phone_lookup', null, 5, 600 ) ) {
			return new \WP_Error(
				'rate_limit_exceeded',
				__( 'Too many requests. Please try again later.', 'vq-checkout' ),
				array( 'status' => 429 )
			);
		}

		$phone = $request->get_param( 'phone' );

		if ( empty( $phone ) ) {
			return new \WP_Error(
				'missing_phone',
				__( 'Phone number is required.', 'vq-checkout' ),
				array( 'status' => 400 )
			);
		}

		// Normalize phone
		$phone = Phone::normalize( $phone );

		if ( ! Phone::validate( $phone ) ) {
			return new \WP_Error(
				'invalid_phone',
				__( 'Invalid phone number.', 'vq-checkout' ),
				array( 'status' => 400 )
			);
		}

		// Get settings - check if feature is enabled
		$settings = get_option( 'vqcheckout_checkout_settings', array() );
		$enabled  = $settings['enable_phone_lookup'] ?? false;

		if ( ! $enabled ) {
			return new \WP_Error(
				'feature_disabled',
				__( 'Phone lookup is not enabled.', 'vq-checkout' ),
				array( 'status' => 403 )
			);
		}

		// Find most recent order with this phone number
		$address = $this->find_address_by_phone( $phone );

		if ( ! $address ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'found'   => false,
					'address' => null,
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'found'   => true,
				'address' => $address,
			)
		);
	}

	/**
	 * Find address by phone number (privacy-safe)
	 *
	 * @param string $phone Phone number
	 * @return array|null Address data or null
	 */
	private function find_address_by_phone( $phone ) {
		global $wpdb;

		// Search in orders (HPOS compatible)
		if ( function_exists( 'wc_get_orders' ) ) {
			$orders = wc_get_orders(
				array(
					'limit'        => 1,
					'orderby'      => 'date',
					'order'        => 'DESC',
					'meta_key'     => '_billing_phone',
					'meta_value'   => $phone,
					'meta_compare' => '=',
					'return'       => 'ids',
				)
			);

			if ( empty( $orders ) ) {
				// Try with different phone formats
				$alt_phone = $this->get_alternative_phone_formats( $phone );

				foreach ( $alt_phone as $alt ) {
					$orders = wc_get_orders(
						array(
							'limit'        => 1,
							'orderby'      => 'date',
							'order'        => 'DESC',
							'meta_key'     => '_billing_phone',
							'meta_value'   => $alt,
							'meta_compare' => '=',
							'return'       => 'ids',
						)
					);

					if ( ! empty( $orders ) ) {
						break;
					}
				}
			}

			if ( ! empty( $orders ) ) {
				$order = wc_get_order( $orders[0] );

				if ( $order ) {
					return $this->extract_address_from_order( $order );
				}
			}
		}

		return null;
	}

	/**
	 * Get alternative phone number formats
	 *
	 * @param string $phone Phone number
	 * @return array
	 */
	private function get_alternative_phone_formats( $phone ) {
		$formats = array();

		// International format
		$formats[] = Phone::to_international( $phone );

		// With/without leading zero
		if ( substr( $phone, 0, 1 ) === '0' ) {
			$formats[] = substr( $phone, 1 );
		} else {
			$formats[] = '0' . $phone;
		}

		// Formatted with spaces/dashes
		$formats[] = Phone::format( $phone, ' ' );
		$formats[] = Phone::format( $phone, '-' );

		return array_unique( $formats );
	}

	/**
	 * Extract address data from order (minimal, privacy-safe)
	 *
	 * @param \WC_Order $order Order object
	 * @return array|null
	 */
	private function extract_address_from_order( $order ) {
		$province = $order->get_meta( '_billing_vqcheckout_province' );
		$district = $order->get_meta( '_billing_vqcheckout_district' );
		$ward     = $order->get_meta( '_billing_vqcheckout_ward' );

		// Only return if we have Vietnam address data
		if ( empty( $province ) || empty( $district ) || empty( $ward ) ) {
			return null;
		}

		// Return minimal data - only codes, not full address
		return array(
			'province' => $province,
			'district' => $district,
			'ward'     => $ward,
		);
	}
}

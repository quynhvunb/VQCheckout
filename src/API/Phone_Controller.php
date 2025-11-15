<?php
/**
 * Phone Lookup REST Controller
 *
 * @package VQCheckout\API
 */

namespace VQCheckout\API;

use VQCheckout\Utils\Sanitizer;
use VQCheckout\Core\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Phone lookup endpoint (privacy-by-design)
 */
class Phone_Controller extends \WP_REST_Controller {
	protected $namespace = 'vqcheckout/v1';

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/phone/lookup',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'lookup_phone' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'phone' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => array( 'VQCheckout\\Utils\\Sanitizer', 'phone' ),
						'validate_callback' => array( $this, 'validate_phone' ),
					),
				),
			)
		);
	}

	public function validate_phone( $phone ) {
		$sanitized = Sanitizer::phone( $phone );

		if ( empty( $sanitized ) ) {
			return new \WP_Error( 'invalid_phone', __( 'Invalid phone number', 'vq-checkout' ), array( 'status' => 400 ) );
		}

		if ( ! preg_match( '/^0\d{9}$/', $sanitized ) ) {
			return new \WP_Error( 'invalid_phone_format', __( 'Phone must be 10 digits starting with 0', 'vq-checkout' ), array( 'status' => 400 ) );
		}

		return true;
	}

	public function lookup_phone( $request ) {
		$phone = $request->get_param( 'phone' );

		// Rate limiting.
		$plugin       = Plugin::instance();
		$rate_limiter = new \VQCheckout\Security\Rate_Limiter();

		if ( ! $rate_limiter->check( 'phone_lookup' ) ) {
			return new \WP_Error(
				'rate_limit_exceeded',
				__( 'Too many requests. Please try again later.', 'vq-checkout' ),
				array( 'status' => 429 )
			);
		}

		// Check if phone lookup is enabled.
		$options = get_option( 'vqcheckout_options', array() );
		if ( empty( $options['enable_phone_lookup'] ) ) {
			return new \WP_Error(
				'feature_disabled',
				__( 'Phone lookup is disabled', 'vq-checkout' ),
				array( 'status' => 403 )
			);
		}

		// Find most recent order with this phone.
		$order_id = $this->find_order_by_phone( $phone );

		if ( ! $order_id ) {
			return rest_ensure_response(
				array(
					'found' => false,
				)
			);
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return rest_ensure_response(
				array(
					'found' => false,
				)
			);
		}

		// Extract minimal address data (privacy-by-design).
		$address_data = $this->extract_minimal_address( $order );

		return rest_ensure_response(
			array(
				'found'   => true,
				'address' => $address_data,
			)
		);
	}

	/**
	 * Find most recent order by phone
	 *
	 * @param string $phone Phone number
	 * @return int|null Order ID or null
	 */
	private function find_order_by_phone( $phone ) {
		global $wpdb;

		// HPOS support.
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$orders_table = $wpdb->prefix . 'wc_orders';
			$meta_table   = $wpdb->prefix . 'wc_orders_meta';

			$order_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT o.id FROM {$orders_table} o
					INNER JOIN {$meta_table} m ON o.id = m.order_id
					WHERE m.meta_key = '_billing_phone'
					AND m.meta_value = %s
					AND o.type = 'shop_order'
					ORDER BY o.date_created_gmt DESC
					LIMIT 1",
					$phone
				)
			);
		} else {
			// Classic posts.
			$order_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
					WHERE pm.meta_key = '_billing_phone'
					AND pm.meta_value = %s
					AND p.post_type = 'shop_order'
					AND p.post_status != 'trash'
					ORDER BY p.post_date DESC
					LIMIT 1",
					$phone
				)
			);
		}

		return $order_id ? (int) $order_id : null;
	}

	/**
	 * Extract minimal address data (privacy-by-design)
	 *
	 * Only returns fields needed for autofill, no sensitive data
	 *
	 * @param \WC_Order $order Order object
	 * @return array Minimal address data
	 */
	private function extract_minimal_address( $order ) {
		$data = array(
			'first_name' => $order->get_billing_first_name(),
			'last_name'  => $order->get_billing_last_name(),
			'company'    => $order->get_billing_company(),
			'address_1'  => $order->get_billing_address_1(),
			'address_2'  => $order->get_billing_address_2(),
			'city'       => $order->get_billing_city(),
			'postcode'   => $order->get_billing_postcode(),
			'country'    => $order->get_billing_country(),
			'state'      => $order->get_billing_state(),
			'email'      => $this->mask_email( $order->get_billing_email() ),
		);

		// Add VN custom fields.
		$province = $order->get_meta( '_billing_province' );
		$district = $order->get_meta( '_billing_district' );
		$ward     = $order->get_meta( '_billing_ward' );
		$gender   = $order->get_meta( '_billing_gender' );

		if ( $province ) {
			$data['province'] = $province;
		}
		if ( $district ) {
			$data['district'] = $district;
		}
		if ( $ward ) {
			$data['ward'] = $ward;
		}
		if ( $gender ) {
			$data['gender'] = $gender;
		}

		// Filter out empty values.
		return array_filter( $data );
	}

	/**
	 * Mask email for privacy
	 *
	 * @param string $email Email address
	 * @return string Masked email
	 */
	private function mask_email( $email ) {
		if ( empty( $email ) ) {
			return '';
		}

		$parts = explode( '@', $email );
		if ( count( $parts ) !== 2 ) {
			return '';
		}

		$local  = $parts[0];
		$domain = $parts[1];

		if ( strlen( $local ) <= 2 ) {
			$masked_local = str_repeat( '*', strlen( $local ) );
		} else {
			$masked_local = substr( $local, 0, 2 ) . str_repeat( '*', strlen( $local ) - 2 );
		}

		return $masked_local . '@' . $domain;
	}
}

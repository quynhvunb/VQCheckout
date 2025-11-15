<?php
/**
 * Session Handler
 *
 * @package VQCheckout\Checkout
 */

namespace VQCheckout\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * Manage customer session data
 */
class Session {
	public function init() {
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'update_session' ) );
		add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'add_ward_to_package' ) );
	}

	public function update_session( $post_data ) {
		if ( ! WC()->session ) {
			return;
		}

		parse_str( $post_data, $data );

		if ( ! empty( $data['billing_ward'] ) ) {
			WC()->session->set( 'billing_ward', sanitize_text_field( $data['billing_ward'] ) );
		}

		if ( ! empty( $data['billing_district'] ) ) {
			WC()->session->set( 'billing_district', sanitize_text_field( $data['billing_district'] ) );
		}

		if ( ! empty( $data['billing_province'] ) ) {
			WC()->session->set( 'billing_province', sanitize_text_field( $data['billing_province'] ) );
		}
	}

	public function add_ward_to_package( $packages ) {
		if ( ! WC()->session ) {
			return $packages;
		}

		$ward_code = WC()->session->get( 'billing_ward' );

		if ( ! empty( $ward_code ) ) {
			foreach ( $packages as $key => $package ) {
				$packages[ $key ]['destination']['ward_code'] = $ward_code;
			}
		}

		return $packages;
	}
}

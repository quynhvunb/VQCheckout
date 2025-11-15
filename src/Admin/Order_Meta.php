<?php
/**
 * Order Meta Display
 *
 * @package VQCheckout\Admin
 */

namespace VQCheckout\Admin;

use VQCheckout\Core\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Display custom fields in admin order page
 */
class Order_Meta {
	public function init() {
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_vn_address' ) );
		add_filter( 'woocommerce_order_formatted_billing_address', array( $this, 'format_billing_address' ), 10, 2 );
	}

	public function display_vn_address( $order ) {
		$province_code = $order->get_meta( '_billing_province' );
		$district_code = $order->get_meta( '_billing_district' );
		$ward_code     = $order->get_meta( '_billing_ward' );
		$gender        = $order->get_meta( '_billing_gender' );

		if ( empty( $province_code ) && empty( $ward_code ) ) {
			return;
		}

		echo '<div class="vqcheckout-order-meta" style="margin-top:10px;padding-top:10px;border-top:1px solid #ddd;">';

		if ( $gender ) {
			echo '<p><strong>' . esc_html__( 'Xưng hô:', 'vq-checkout' ) . '</strong> ';
			echo esc_html( ucfirst( $gender ) ) . '</p>';
		}

		if ( $province_code || $district_code || $ward_code ) {
			$plugin = Plugin::instance();
			$repo   = $plugin->get( 'location_repository' );

			echo '<p><strong>' . esc_html__( 'Địa chỉ hành chính:', 'vq-checkout' ) . '</strong><br>';

			if ( $ward_code ) {
				$ward = $repo->get_location( $ward_code );
				if ( $ward ) {
					echo esc_html( $ward['name_with_type'] ) . '<br>';
				}
			}

			if ( $district_code ) {
				$district = $repo->get_location( $district_code );
				if ( $district ) {
					echo esc_html( $district['name_with_type'] ) . '<br>';
				}
			}

			if ( $province_code ) {
				$province = $repo->get_location( $province_code );
				if ( $province ) {
					echo esc_html( $province['name_with_type'] );
				}
			}

			echo '</p>';
		}

		echo '</div>';
	}

	public function format_billing_address( $address, $order ) {
		$province_code = $order->get_meta( '_billing_province' );
		$district_code = $order->get_meta( '_billing_district' );
		$ward_code     = $order->get_meta( '_billing_ward' );

		if ( empty( $ward_code ) ) {
			return $address;
		}

		$plugin = Plugin::instance();
		$repo   = $plugin->get( 'location_repository' );

		$parts = array();

		if ( $ward_code ) {
			$ward = $repo->get_location( $ward_code );
			if ( $ward ) {
				$parts[] = $ward['name_with_type'];
			}
		}

		if ( $district_code ) {
			$district = $repo->get_location( $district_code );
			if ( $district ) {
				$parts[] = $district['name_with_type'];
			}
		}

		if ( $province_code ) {
			$province = $repo->get_location( $province_code );
			if ( $province ) {
				$parts[] = $province['name_with_type'];
			}
		}

		if ( ! empty( $parts ) ) {
			$address['city']  = implode( ', ', $parts );
			$address['state'] = '';
		}

		return $address;
	}
}

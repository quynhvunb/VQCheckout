<?php
/**
 * WooCommerce Shipping Method
 *
 * @package VQCheckout\Shipping
 */

namespace VQCheckout\Shipping;

use VQCheckout\Core\Plugin;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Shipping_Method' ) ) {
	return;
}

/**
 * VQ Ward Rate Shipping Method
 */
class WC_Method extends \WC_Shipping_Method {
	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'vqcheckout_ward_rate';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Phí vận chuyển tới Xã/Phường', 'vq-checkout' );
		$this->method_description = __( 'Tính phí vận chuyển theo xã/phường với điều kiện linh hoạt', 'vq-checkout' );
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
		);

		$this->init();
	}

	private function init() {
		$this->init_form_fields();
		$this->init_settings();

		$this->title = $this->get_option( 'title', $this->method_title );
		$this->enabled = $this->get_option( 'enabled', 'yes' );

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields() {
		$this->instance_form_fields = array(
			'title'    => array(
				'title'       => __( 'Tiêu đề phương thức', 'vq-checkout' ),
				'type'        => 'text',
				'description' => __( 'Tiêu đề hiển thị khi khách hàng chọn', 'vq-checkout' ),
				'default'     => $this->method_title,
				'desc_tip'    => true,
			),
			'enabled'  => array(
				'title'   => __( 'Kích hoạt', 'vq-checkout' ),
				'type'    => 'checkbox',
				'default' => 'yes',
			),
		);
	}

	public function calculate_shipping( $package = array() ) {
		$ward_code = $this->get_ward_code_from_package( $package );

		if ( ! $ward_code ) {
			return;
		}

		$cart_subtotal = WC()->cart->get_subtotal();

		$plugin = Plugin::instance();
		$cache = $plugin->get( 'cache' );
		$rate_repo = $plugin->get( 'rate_repository' );
		$resolver = new Resolver( $cache, $rate_repo );

		$result = $resolver->resolve( $this->instance_id, $ward_code, $cart_subtotal );

		if ( $result['blocked'] ) {
			return;
		}

		if ( $result['cost'] > 0 || $result['rate_id'] > 0 ) {
			$rate = array(
				'id'    => $this->id . ':' . $this->instance_id,
				'label' => $result['label'] ?: $this->title,
				'cost'  => $result['cost'],
				'meta_data' => $result['meta'],
			);

			$this->add_rate( $rate );
		}
	}

	private function get_ward_code_from_package( $package ) {
		if ( isset( $package['destination']['ward_code'] ) ) {
			return $package['destination']['ward_code'];
		}

		if ( isset( $_POST['post_data'] ) ) {
			parse_str( $_POST['post_data'], $post_data );
			if ( ! empty( $post_data['billing_ward'] ) ) {
				return sanitize_text_field( $post_data['billing_ward'] );
			}
		}

		return WC()->session->get( 'billing_ward' );
	}
}

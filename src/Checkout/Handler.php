<?php
/**
 * Checkout Handler
 *
 * @package VQCheckout\Checkout
 */

namespace VQCheckout\Checkout;

use VQCheckout\Security\Recaptcha_Service;
use VQCheckout\Security\Rate_Limiter;

defined( 'ABSPATH' ) || exit;

/**
 * Handle checkout process & validation
 */
class Handler {
	private $recaptcha;

	public function __construct() {
		$this->recaptcha = new Recaptcha_Service();
	}

	public function init() {
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_checkout' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_custom_fields' ) );
		add_filter( 'woocommerce_package_rates', array( $this, 'filter_shipping_rates' ), 100, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function validate_checkout() {
		$options = get_option( 'vqcheckout_options', array() );

		if ( ! Rate_Limiter::check( 'checkout' ) ) {
			wc_add_notice( __( 'Quá nhiều yêu cầu. Vui lòng thử lại sau.', 'vq-checkout' ), 'error' );
			return;
		}

		if ( ! empty( $options['enable_recaptcha_create_order'] ) && $this->recaptcha->is_enabled() ) {
			$token = isset( $_POST['vqcheckout_recaptcha_token'] ) ? sanitize_text_field( $_POST['vqcheckout_recaptcha_token'] ) : '';

			$result = $this->recaptcha->verify( $token, 'checkout' );

			if ( ! $result['success'] ) {
				wc_add_notice( __( 'Xác thực bảo mật không thành công. Vui lòng thử lại.', 'vq-checkout' ), 'error' );
			}
		}

		if ( empty( $_POST['billing_ward'] ) ) {
			wc_add_notice( __( 'Vui lòng chọn Xã/Phường.', 'vq-checkout' ), 'error' );
		}

		$this->check_blocked_keywords();
		$this->check_blocked_ip();
	}

	private function check_blocked_keywords() {
		$options  = get_option( 'vqcheckout_options', array() );
		$keywords = ! empty( $options['block_order_name'] ) ? explode( "\n", $options['block_order_name'] ) : array();

		if ( empty( $keywords ) ) {
			return;
		}

		$fields_to_check = array(
			'billing_first_name',
			'billing_last_name',
			'billing_email',
			'billing_phone',
			'billing_address_1',
		);

		foreach ( $keywords as $keyword ) {
			$keyword = trim( $keyword );
			if ( empty( $keyword ) ) {
				continue;
			}

			foreach ( $fields_to_check as $field ) {
				if ( ! empty( $_POST[ $field ] ) && stripos( $_POST[ $field ], $keyword ) !== false ) {
					wc_add_notice( __( 'Đơn hàng không được chấp nhận.', 'vq-checkout' ), 'error' );
					return;
				}
			}
		}
	}

	private function check_blocked_ip() {
		$options     = get_option( 'vqcheckout_options', array() );
		$blocked_ips = ! empty( $options['block_order_ip'] ) ? explode( "\n", $options['block_order_ip'] ) : array();

		if ( empty( $blocked_ips ) ) {
			return;
		}

		$current_ip = Rate_Limiter::get_ip();

		foreach ( $blocked_ips as $blocked_ip ) {
			$blocked_ip = trim( $blocked_ip );
			if ( empty( $blocked_ip ) ) {
				continue;
			}

			if ( strpos( $blocked_ip, '*' ) !== false ) {
				$pattern = str_replace( '.', '\.', $blocked_ip );
				$pattern = str_replace( '*', '.*', $pattern );
				if ( preg_match( "/^{$pattern}$/", $current_ip ) ) {
					wc_add_notice( __( 'Đơn hàng không được chấp nhận.', 'vq-checkout' ), 'error' );
					return;
				}
			} elseif ( $blocked_ip === $current_ip ) {
				wc_add_notice( __( 'Đơn hàng không được chấp nhận.', 'vq-checkout' ), 'error' );
				return;
			}
		}
	}

	public function save_custom_fields( $order_id ) {
		if ( ! empty( $_POST['billing_province'] ) ) {
			update_post_meta( $order_id, '_billing_province', sanitize_text_field( $_POST['billing_province'] ) );
		}

		if ( ! empty( $_POST['billing_district'] ) ) {
			update_post_meta( $order_id, '_billing_district', sanitize_text_field( $_POST['billing_district'] ) );
		}

		if ( ! empty( $_POST['billing_ward'] ) ) {
			update_post_meta( $order_id, '_billing_ward', sanitize_text_field( $_POST['billing_ward'] ) );
		}

		if ( ! empty( $_POST['billing_gender'] ) ) {
			update_post_meta( $order_id, '_billing_gender', sanitize_text_field( $_POST['billing_gender'] ) );
		}
	}

	public function filter_shipping_rates( $rates, $package ) {
		$options = get_option( 'vqcheckout_options', array() );

		if ( ! empty( $options['freeship_remove_other_methob'] ) ) {
			$free_shipping = array_filter(
				$rates,
				function( $rate ) {
					return $rate->method_id === 'free_shipping';
				}
			);

			if ( ! empty( $free_shipping ) ) {
				return $free_shipping;
			}
		}

		if ( ! empty( $options['remove_method_title'] ) ) {
			foreach ( $rates as $rate ) {
				$rate->label = '';
			}
		}

		return $rates;
	}

	public function enqueue_scripts() {
		if ( ! is_checkout() ) {
			return;
		}

		wp_enqueue_style(
			'vqcheckout-checkout',
			VQCHECKOUT_URL . 'assets/css/checkout.css',
			array(),
			VQCHECKOUT_VERSION
		);

		wp_enqueue_script(
			'vqcheckout-checkout',
			VQCHECKOUT_URL . 'assets/js/checkout.js',
			array( 'jquery', 'wc-checkout' ),
			VQCHECKOUT_VERSION,
			true
		);

		$options = get_option( 'vqcheckout_options', array() );

		wp_localize_script(
			'vqcheckout-checkout',
			'vqCheckout',
			array(
				'restUrl'           => rest_url( 'vqcheckout/v1' ),
				'enablePhoneLookup' => ! empty( $options['enable_phone_lookup'] ),
				'recaptcha'         => array(
					'enabled'  => $this->recaptcha->is_enabled(),
					'version'  => $this->recaptcha->get_version(),
					'siteKey'  => $this->recaptcha->get_site_key(),
				),
				'i18n'              => array(
					'selectProvince'    => __( '-- Chọn Tỉnh/Thành phố --', 'vq-checkout' ),
					'selectDistrict'    => __( '-- Chọn Quận/Huyện --', 'vq-checkout' ),
					'selectWard'        => __( '-- Chọn Xã/Phường --', 'vq-checkout' ),
					'addressAutofilled' => __( 'Địa chỉ đã được tự động điền dựa trên số điện thoại.', 'vq-checkout' ),
				),
			)
		);

		$this->recaptcha->enqueue_script();
	}
}

<?php
/**
 * Checkout Fields Customization
 *
 * @package VQCheckout\Checkout
 */

namespace VQCheckout\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * Modify WooCommerce checkout fields for Vietnam
 */
class Fields {
	public function init() {
		add_filter( 'woocommerce_checkout_fields', array( $this, 'customize_fields' ), 20 );
		add_filter( 'woocommerce_default_address_fields', array( $this, 'customize_default_fields' ), 20 );
		add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'add_custom_fields' ) );
	}

	public function customize_default_fields( $fields ) {
		$options = get_option( 'vqcheckout_options', array() );

		if ( isset( $fields['country'] ) && empty( $options['alepay_support'] ) ) {
			$fields['country']['required'] = false;
			$fields['country']['class']    = array( 'form-row-wide', 'hidden' );
		}

		if ( isset( $fields['postcode'] ) ) {
			if ( empty( $options['enable_postcode'] ) ) {
				$fields['postcode']['required'] = false;
				$fields['postcode']['class']    = array( 'form-row-wide', 'hidden' );
			}
		}

		if ( isset( $fields['state'] ) ) {
			unset( $fields['state'] );
		}

		if ( isset( $fields['city'] ) ) {
			unset( $fields['city'] );
		}

		return $fields;
	}

	public function customize_fields( $fields ) {
		$options = get_option( 'vqcheckout_options', array() );

		if ( isset( $fields['billing']['billing_last_name'] ) && empty( $options['alepay_support'] ) ) {
			$fields['billing']['billing_last_name']['required'] = false;
			$fields['billing']['billing_last_name']['class']    = array( 'form-row-wide', 'hidden' );
		}

		if ( isset( $fields['billing']['billing_first_name'] ) ) {
			$fields['billing']['billing_first_name']['label']       = __( 'Họ và tên', 'vq-checkout' );
			$fields['billing']['billing_first_name']['placeholder'] = __( 'Nhập họ và tên', 'vq-checkout' );
			$fields['billing']['billing_first_name']['class']       = array( 'form-row-wide' );
			$fields['billing']['billing_first_name']['priority']    = 20;
		}

		if ( isset( $fields['billing']['billing_email'] ) && ! empty( $options['not_required_email'] ) ) {
			$fields['billing']['billing_email']['required'] = false;
		}

		if ( isset( $fields['billing']['billing_phone'] ) ) {
			$fields['billing']['billing_phone']['priority'] = 25;

			if ( ! empty( $options['phone_vn'] ) ) {
				add_filter( 'woocommerce_checkout_posted_data', array( $this, 'validate_phone_vn' ) );
			}
		}

		$fields['billing']['billing_province'] = array(
			'type'        => 'select',
			'label'       => __( 'Tỉnh/Thành phố', 'vq-checkout' ),
			'required'    => true,
			'class'       => array( 'form-row-wide', 'vqcheckout-province' ),
			'priority'    => 70,
			'options'     => array( '' => __( '-- Chọn Tỉnh/Thành phố --', 'vq-checkout' ) ),
		);

		$fields['billing']['billing_district'] = array(
			'type'        => 'select',
			'label'       => __( 'Quận/Huyện', 'vq-checkout' ),
			'required'    => true,
			'class'       => array( 'form-row-wide', 'vqcheckout-district' ),
			'priority'    => 75,
			'options'     => array( '' => __( '-- Chọn Quận/Huyện --', 'vq-checkout' ) ),
		);

		$fields['billing']['billing_ward'] = array(
			'type'        => 'select',
			'label'       => __( 'Xã/Phường/Thị trấn', 'vq-checkout' ),
			'required'    => true,
			'class'       => array( 'form-row-wide', 'vqcheckout-ward' ),
			'priority'    => 80,
			'options'     => array( '' => __( '-- Chọn Xã/Phường --', 'vq-checkout' ) ),
		);

		if ( isset( $fields['billing']['billing_address_1'] ) ) {
			$fields['billing']['billing_address_1']['label']       = __( 'Số nhà, tên đường', 'vq-checkout' );
			$fields['billing']['billing_address_1']['placeholder'] = __( 'Ví dụ: Số 123, Đường ABC', 'vq-checkout' );
			$fields['billing']['billing_address_1']['priority']    = 85;
		}

		if ( isset( $fields['billing']['billing_address_2'] ) ) {
			$fields['billing']['billing_address_2']['priority'] = 90;
		}

		return $fields;
	}

	public function add_custom_fields( $checkout ) {
		$options = get_option( 'vqcheckout_options', array() );

		if ( ! empty( $options['enable_gender'] ) ) {
			woocommerce_form_field(
				'billing_gender',
				array(
					'type'     => 'select',
					'label'    => __( 'Xưng hô', 'vq-checkout' ),
					'required' => false,
					'class'    => array( 'form-row-wide', 'vqcheckout-gender' ),
					'options'  => array(
						''    => __( '-- Chọn --', 'vq-checkout' ),
						'anh' => __( 'Anh', 'vq-checkout' ),
						'chi' => __( 'Chị', 'vq-checkout' ),
					),
				),
				$checkout->get_value( 'billing_gender' )
			);
		}
	}

	public function validate_phone_vn( $data ) {
		if ( ! empty( $data['billing_phone'] ) ) {
			$phone = \VQCheckout\Utils\Sanitizer::phone( $data['billing_phone'] );

			if ( ! \VQCheckout\Utils\Validator::phone_vn( $phone ) ) {
				wc_add_notice(
					__( 'Số điện thoại không hợp lệ. Vui lòng nhập số điện thoại 10 số bắt đầu bằng 03, 05, 07, 08, 09.', 'vq-checkout' ),
					'error'
				);
			}
		}

		return $data;
	}
}

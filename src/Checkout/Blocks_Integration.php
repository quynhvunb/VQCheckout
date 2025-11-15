<?php
/**
 * WooCommerce Blocks Integration
 *
 * @package VQCheckout\Checkout
 */

namespace VQCheckout\Checkout;

use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\StoreApi;

defined( 'ABSPATH' ) || exit;

/**
 * Integrate VN address fields with WooCommerce Blocks
 */
class Blocks_Integration {
	/**
	 * Initialize blocks integration
	 */
	public function init() {
		if ( ! $this->is_blocks_enabled() ) {
			return;
		}

		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_blocks_integration' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( $this, 'save_blocks_checkout_fields' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
		add_action( 'woocommerce_blocks_checkout_enqueue_data', array( $this, 'enqueue_checkout_block_data' ) );
	}

	/**
	 * Check if WooCommerce Blocks is enabled
	 *
	 * @return bool
	 */
	private function is_blocks_enabled() {
		return class_exists( 'Automattic\WooCommerce\StoreApi\StoreApi' );
	}

	/**
	 * Register Store API extensions
	 */
	public function register_blocks_integration() {
		// Modern WooCommerce 8.x uses __experimental_woocommerce_blocks_checkout_update_order_meta
		// and woocommerce_store_api_checkout_update_order_from_request
		// The hooks are already registered in init(), so this is just for legacy compatibility

		// Check if old function exists (WC < 7.0)
		if ( function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			woocommerce_store_api_register_endpoint_data(
				array(
					'endpoint'        => 'checkout',
					'namespace'       => 'vqcheckout',
					'data_callback'   => array( $this, 'extend_checkout_data' ),
					'schema_callback' => array( $this, 'extend_checkout_schema' ),
					'schema_type'     => ARRAY_A,
				)
			);
		}

		// For WC 8.x, we use the checkout block registration directly
		// This is handled via the woocommerce_blocks_checkout_enqueue_data action
	}

	/**
	 * Extend checkout data
	 *
	 * @return array
	 */
	public function extend_checkout_data() {
		return array(
			'province' => WC()->session ? WC()->session->get( 'billing_province', '' ) : '',
			'district' => WC()->session ? WC()->session->get( 'billing_district', '' ) : '',
			'ward'     => WC()->session ? WC()->session->get( 'billing_ward', '' ) : '',
			'gender'   => WC()->session ? WC()->session->get( 'billing_gender', '' ) : '',
		);
	}

	/**
	 * Extend checkout schema
	 *
	 * @return array
	 */
	public function extend_checkout_schema() {
		return array(
			'province' => array(
				'description' => __( 'Tỉnh/Thành phố', 'vq-checkout' ),
				'type'        => 'string',
				'required'    => true,
			),
			'district' => array(
				'description' => __( 'Quận/Huyện', 'vq-checkout' ),
				'type'        => 'string',
				'required'    => true,
			),
			'ward'     => array(
				'description' => __( 'Xã/Phường', 'vq-checkout' ),
				'type'        => 'string',
				'required'    => true,
			),
			'gender'   => array(
				'description' => __( 'Xưng hô', 'vq-checkout' ),
				'type'        => 'string',
				'enum'        => array( '', 'anh', 'chi' ),
			),
		);
	}

	/**
	 * Save checkout fields from blocks
	 *
	 * @param \WC_Order $order Order object
	 */
	public function save_blocks_checkout_fields( $order ) {
		$request_data = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! empty( $request_data['extensions']['vqcheckout'] ) ) {
			$vq_data = $request_data['extensions']['vqcheckout'];

			if ( ! empty( $vq_data['province'] ) ) {
				$order->update_meta_data( '_billing_province', sanitize_text_field( $vq_data['province'] ) );
			}

			if ( ! empty( $vq_data['district'] ) ) {
				$order->update_meta_data( '_billing_district', sanitize_text_field( $vq_data['district'] ) );
			}

			if ( ! empty( $vq_data['ward'] ) ) {
				$order->update_meta_data( '_billing_ward', sanitize_text_field( $vq_data['ward'] ) );
			}

			if ( ! empty( $vq_data['gender'] ) ) {
				$order->update_meta_data( '_billing_gender', sanitize_text_field( $vq_data['gender'] ) );
			}

			$order->save();
		}
	}

	/**
	 * Enqueue block editor assets
	 */
	public function enqueue_block_editor_assets() {
		if ( ! function_exists( 'wc_get_block_template' ) ) {
			return;
		}

		wp_enqueue_script(
			'vqcheckout-blocks-editor',
			VQCHECKOUT_URL . 'assets/js/blocks-editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wc-blocks-checkout' ),
			VQCHECKOUT_VERSION,
			true
		);
	}

	/**
	 * Enqueue checkout block data
	 */
	public function enqueue_checkout_block_data() {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return;
		}

		wp_register_script(
			'vqcheckout-blocks-frontend',
			VQCHECKOUT_URL . 'assets/js/blocks-frontend.js',
			array( 'wc-blocks-checkout' ),
			VQCHECKOUT_VERSION,
			true
		);

		wp_localize_script(
			'vqcheckout-blocks-frontend',
			'vqCheckoutBlocks',
			array(
				'restUrl' => rest_url( 'vqcheckout/v1' ),
				'i18n'    => array(
					'province'       => __( 'Tỉnh/Thành phố', 'vq-checkout' ),
					'district'       => __( 'Quận/Huyện', 'vq-checkout' ),
					'ward'           => __( 'Xã/Phường', 'vq-checkout' ),
					'gender'         => __( 'Xưng hô', 'vq-checkout' ),
					'selectProvince' => __( '-- Chọn Tỉnh/Thành phố --', 'vq-checkout' ),
					'selectDistrict' => __( '-- Chọn Quận/Huyện --', 'vq-checkout' ),
					'selectWard'     => __( '-- Chọn Xã/Phường --', 'vq-checkout' ),
					'anh'            => __( 'Anh', 'vq-checkout' ),
					'chi'            => __( 'Chị', 'vq-checkout' ),
				),
			)
		);

		wp_enqueue_script( 'vqcheckout-blocks-frontend' );

		wp_enqueue_style(
			'vqcheckout-blocks',
			VQCHECKOUT_URL . 'assets/css/blocks.css',
			array(),
			VQCHECKOUT_VERSION
		);
	}
}

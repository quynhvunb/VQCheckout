<?php
/**
 * Checkout Fields - Province/District/Ward fields for WooCommerce
 *
 * @package VQCheckout\Checkout
 */

namespace VQCheckout\Checkout;

use VQCheckout\Shipping\Location_Repository;
use VQCheckout\Utils\Validation;

defined( 'ABSPATH' ) || exit;

/**
 * Add Vietnam address fields to checkout
 */
class Fields {
	private $location_repo;

	public function __construct( Location_Repository $location_repo ) {
		$this->location_repo = $location_repo;
	}

	/**
	 * Register hooks
	 */
	public function register() {
		// Add custom fields to checkout
		add_filter( 'woocommerce_checkout_fields', array( $this, 'add_checkout_fields' ), 20 );

		// Validate fields on checkout
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout_fields' ), 10, 2 );

		// Save custom fields to order
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_order_fields' ), 10, 2 );

		// Display fields in order details
		add_action( 'woocommerce_order_details_after_customer_details', array( $this, 'display_order_fields' ) );

		// Add fields to admin order page
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_admin_order_fields' ) );

		// Enqueue scripts for checkout
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add Province/District/Ward fields to checkout
	 *
	 * @param array $fields Checkout fields
	 * @return array
	 */
	public function add_checkout_fields( $fields ) {
		// Get settings
		$settings = get_option( 'vqcheckout_checkout_settings', array() );
		$enabled = $settings['enable_checkout_fields'] ?? true;

		if ( ! $enabled ) {
			return $fields;
		}

		// Position to insert (after state/city)
		$position = $settings['field_position'] ?? 'after_city';

		// Province field
		$province_field = array(
			'type'        => 'select',
			'label'       => __( 'Province/City', 'vq-checkout' ),
			'required'    => true,
			'class'       => array( 'form-row-wide', 'vqcheckout-province' ),
			'priority'    => $this->get_field_priority( $position, 'province' ),
			'options'     => array( '' => __( 'Select province...', 'vq-checkout' ) ),
			'custom_attributes' => array(
				'data-vqcheckout-field' => 'province',
			),
		);

		// District field
		$district_field = array(
			'type'        => 'select',
			'label'       => __( 'District', 'vq-checkout' ),
			'required'    => true,
			'class'       => array( 'form-row-wide', 'vqcheckout-district' ),
			'priority'    => $this->get_field_priority( $position, 'district' ),
			'options'     => array( '' => __( 'Select district...', 'vq-checkout' ) ),
			'custom_attributes' => array(
				'data-vqcheckout-field' => 'district',
			),
		);

		// Ward field
		$ward_field = array(
			'type'        => 'select',
			'label'       => __( 'Ward/Commune', 'vq-checkout' ),
			'required'    => true,
			'class'       => array( 'form-row-wide', 'vqcheckout-ward' ),
			'priority'    => $this->get_field_priority( $position, 'ward' ),
			'options'     => array( '' => __( 'Select ward...', 'vq-checkout' ) ),
			'custom_attributes' => array(
				'data-vqcheckout-field' => 'ward',
			),
		);

		// Add to billing fields
		$fields['billing']['billing_vqcheckout_province'] = $province_field;
		$fields['billing']['billing_vqcheckout_district'] = $district_field;
		$fields['billing']['billing_vqcheckout_ward'] = $ward_field;

		// Add to shipping fields if separate shipping is enabled
		if ( isset( $fields['shipping'] ) ) {
			$fields['shipping']['shipping_vqcheckout_province'] = $province_field;
			$fields['shipping']['shipping_vqcheckout_district'] = $district_field;
			$fields['shipping']['shipping_vqcheckout_ward'] = $ward_field;
		}

		// Hide default state/city fields
		if ( $settings['hide_default_fields'] ?? false ) {
			if ( isset( $fields['billing']['billing_state'] ) ) {
				$fields['billing']['billing_state']['required'] = false;
				$fields['billing']['billing_state']['class'][] = 'vqcheckout-hidden';
			}
			if ( isset( $fields['billing']['billing_city'] ) ) {
				$fields['billing']['billing_city']['required'] = false;
				$fields['billing']['billing_city']['class'][] = 'vqcheckout-hidden';
			}
		}

		return $fields;
	}

	/**
	 * Get field priority based on position
	 *
	 * @param string $position Position setting
	 * @param string $field    Field name
	 * @return int
	 */
	private function get_field_priority( $position, $field ) {
		$base_priorities = array(
			'province' => 80,
			'district' => 81,
			'ward'     => 82,
		);

		if ( 'after_city' === $position ) {
			// After city (default ~70)
			return $base_priorities[ $field ];
		}

		// After address (default ~50)
		return $base_priorities[ $field ] - 30;
	}

	/**
	 * Validate checkout fields
	 *
	 * @param array    $data   Posted data
	 * @param \WP_Error $errors Errors object
	 */
	public function validate_checkout_fields( $data, $errors ) {
		// Validate billing address
		$this->validate_address_fields( 'billing', $data, $errors );

		// Validate shipping address if different
		if ( ! empty( $data['ship_to_different_address'] ) ) {
			$this->validate_address_fields( 'shipping', $data, $errors );
		}
	}

	/**
	 * Validate address fields (billing or shipping)
	 *
	 * @param string    $type   'billing' or 'shipping'
	 * @param array     $data   Posted data
	 * @param \WP_Error $errors Errors object
	 */
	private function validate_address_fields( $type, $data, $errors ) {
		$province = $data[ "{$type}_vqcheckout_province" ] ?? '';
		$district = $data[ "{$type}_vqcheckout_district" ] ?? '';
		$ward = $data[ "{$type}_vqcheckout_ward" ] ?? '';

		// Validate province
		if ( empty( $province ) ) {
			$errors->add(
				'vqcheckout_province',
				__( 'Please select a province/city.', 'vq-checkout' )
			);
			return;
		}

		// Validate district
		if ( empty( $district ) ) {
			$errors->add(
				'vqcheckout_district',
				__( 'Please select a district.', 'vq-checkout' )
			);
			return;
		}

		// Validate ward
		if ( empty( $ward ) ) {
			$errors->add(
				'vqcheckout_ward',
				__( 'Please select a ward/commune.', 'vq-checkout' )
			);
			return;
		}

		// Validate ward code format
		if ( ! Validation::is_valid_ward_code( $ward ) ) {
			$errors->add(
				'vqcheckout_ward',
				__( 'Invalid ward code.', 'vq-checkout' )
			);
		}
	}

	/**
	 * Save custom fields to order
	 *
	 * @param \WC_Order $order Order object
	 * @param array     $data  Posted data
	 */
	public function save_order_fields( $order, $data ) {
		// Save billing fields
		$this->save_address_to_order( 'billing', $order, $data );

		// Save shipping fields if different
		if ( ! empty( $data['ship_to_different_address'] ) ) {
			$this->save_address_to_order( 'shipping', $order, $data );
		}
	}

	/**
	 * Save address fields to order
	 *
	 * @param string    $type  'billing' or 'shipping'
	 * @param \WC_Order $order Order object
	 * @param array     $data  Posted data
	 */
	private function save_address_to_order( $type, $order, $data ) {
		$province = sanitize_text_field( $data[ "{$type}_vqcheckout_province" ] ?? '' );
		$district = sanitize_text_field( $data[ "{$type}_vqcheckout_district" ] ?? '' );
		$ward = sanitize_text_field( $data[ "{$type}_vqcheckout_ward" ] ?? '' );

		if ( ! empty( $province ) ) {
			$order->update_meta_data( "_{$type}_vqcheckout_province", $province );
		}

		if ( ! empty( $district ) ) {
			$order->update_meta_data( "_{$type}_vqcheckout_district", $district );
		}

		if ( ! empty( $ward ) ) {
			$order->update_meta_data( "_{$type}_vqcheckout_ward", $ward );

			// Also save ward code to dedicated meta for shipping calculation
			if ( 'shipping' === $type || 'billing' === $type ) {
				$order->update_meta_data( '_vqcheckout_ward_code', $ward );
			}
		}

		// Get location names and save for display
		$province_name = $this->get_location_name( $province, 'province' );
		$district_name = $this->get_location_name( $district, 'district' );
		$ward_name = $this->get_location_name( $ward, 'ward' );

		if ( $province_name ) {
			$order->update_meta_data( "_{$type}_vqcheckout_province_name", $province_name );
		}

		if ( $district_name ) {
			$order->update_meta_data( "_{$type}_vqcheckout_district_name", $district_name );
		}

		if ( $ward_name ) {
			$order->update_meta_data( "_{$type}_vqcheckout_ward_name", $ward_name );
		}
	}

	/**
	 * Get location name from code
	 *
	 * @param string $code Location code
	 * @param string $type Location type
	 * @return string|null
	 */
	private function get_location_name( $code, $type ) {
		if ( empty( $code ) ) {
			return null;
		}

		switch ( $type ) {
			case 'province':
				$provinces = $this->location_repo->get_provinces();
				foreach ( $provinces as $province ) {
					if ( $province['code'] === $code ) {
						return $province['name'];
					}
				}
				break;

			case 'district':
				$districts = $this->location_repo->get_districts( null );
				foreach ( $districts as $district ) {
					if ( $district['code'] === $code ) {
						return $district['name'];
					}
				}
				break;

			case 'ward':
				$wards = $this->location_repo->get_wards( null );
				foreach ( $wards as $ward ) {
					if ( $ward['code'] === $code ) {
						return $ward['name'];
					}
				}
				break;
		}

		return null;
	}

	/**
	 * Display fields in order details (frontend)
	 *
	 * @param \WC_Order $order Order object
	 */
	public function display_order_fields( $order ) {
		$province = $order->get_meta( '_billing_vqcheckout_province_name' );
		$district = $order->get_meta( '_billing_vqcheckout_district_name' );
		$ward = $order->get_meta( '_billing_vqcheckout_ward_name' );

		if ( $province || $district || $ward ) {
			?>
			<div class="vqcheckout-order-details">
				<h2><?php esc_html_e( 'Vietnam Address', 'vq-checkout' ); ?></h2>
				<?php if ( $province ) : ?>
					<p><strong><?php esc_html_e( 'Province/City:', 'vq-checkout' ); ?></strong> <?php echo esc_html( $province ); ?></p>
				<?php endif; ?>
				<?php if ( $district ) : ?>
					<p><strong><?php esc_html_e( 'District:', 'vq-checkout' ); ?></strong> <?php echo esc_html( $district ); ?></p>
				<?php endif; ?>
				<?php if ( $ward ) : ?>
					<p><strong><?php esc_html_e( 'Ward/Commune:', 'vq-checkout' ); ?></strong> <?php echo esc_html( $ward ); ?></p>
				<?php endif; ?>
			</div>
			<?php
		}
	}

	/**
	 * Display fields in admin order page
	 *
	 * @param \WC_Order $order Order object
	 */
	public function display_admin_order_fields( $order ) {
		$province = $order->get_meta( '_billing_vqcheckout_province_name' );
		$district = $order->get_meta( '_billing_vqcheckout_district_name' );
		$ward = $order->get_meta( '_billing_vqcheckout_ward_name' );

		if ( $province || $district || $ward ) {
			?>
			<div class="order_data_column">
				<h3><?php esc_html_e( 'Vietnam Address', 'vq-checkout' ); ?></h3>
				<div class="address">
					<?php if ( $province ) : ?>
						<p><strong><?php esc_html_e( 'Province/City:', 'vq-checkout' ); ?></strong> <?php echo esc_html( $province ); ?></p>
					<?php endif; ?>
					<?php if ( $district ) : ?>
						<p><strong><?php esc_html_e( 'District:', 'vq-checkout' ); ?></strong> <?php echo esc_html( $district ); ?></p>
					<?php endif; ?>
					<?php if ( $ward ) : ?>
						<p><strong><?php esc_html_e( 'Ward/Commune:', 'vq-checkout' ); ?></strong> <?php echo esc_html( $ward ); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Enqueue checkout scripts
	 */
	public function enqueue_scripts() {
		if ( ! is_checkout() ) {
			return;
		}

		wp_enqueue_script(
			'vqcheckout-checkout',
			VQCHECKOUT_URL . 'assets/js/checkout.js',
			array( 'jquery', 'wc-checkout' ),
			VQCHECKOUT_VERSION,
			true
		);

		wp_enqueue_style(
			'vqcheckout-checkout',
			VQCHECKOUT_URL . 'assets/css/checkout.css',
			array(),
			VQCHECKOUT_VERSION
		);

		// Localize script
		wp_localize_script(
			'vqcheckout-checkout',
			'vqCheckout',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'restUrl'    => rest_url( 'vqcheckout/v1' ),
				'nonce'      => wp_create_nonce( 'vqcheckout_rest' ),
				'i18n'       => array(
					'selectProvince' => __( 'Select province...', 'vq-checkout' ),
					'selectDistrict' => __( 'Select district...', 'vq-checkout' ),
					'selectWard'     => __( 'Select ward...', 'vq-checkout' ),
					'loading'        => __( 'Loading...', 'vq-checkout' ),
					'error'          => __( 'Error loading data', 'vq-checkout' ),
				),
			)
		);
	}
}

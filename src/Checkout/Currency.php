<?php
/**
 * Currency Customization
 *
 * @package VQCheckout\Checkout
 */

namespace VQCheckout\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * Handle currency symbol conversion
 */
class Currency {
	public function init() {
		$options = get_option( 'vqcheckout_options', array() );

		if ( ! empty( $options['to_vnd'] ) ) {
			add_filter( 'woocommerce_currency_symbol', array( $this, 'change_currency_symbol' ), 10, 2 );
		}
	}

	public function change_currency_symbol( $currency_symbol, $currency ) {
		if ( $currency === 'VND' || $currency_symbol === '₫' ) {
			return 'VNĐ';
		}

		return $currency_symbol;
	}
}

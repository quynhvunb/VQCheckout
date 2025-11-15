<?php
/**
 * Multi-Currency Support
 *
 * @package VQCheckout\Checkout
 */

namespace VQCheckout\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * Handle multi-currency shipping costs
 */
class Multi_Currency {
	const RATES_OPTION    = 'vqcheckout_exchange_rates';
	const RATES_CACHE_TTL = 12 * HOUR_IN_SECONDS;

	private $enabled = false;
	private $base_currency = 'VND';
	private $supported_currencies = array( 'VND', 'USD', 'EUR', 'JPY', 'KRW', 'THB' );

	/**
	 * Initialize multi-currency
	 */
	public function init() {
		$options       = get_option( 'vqcheckout_options', array() );
		$this->enabled = ! empty( $options['enable_multi_currency'] );

		if ( ! $this->enabled ) {
			return;
		}

		add_filter( 'vqcheckout_shipping_cost', array( $this, 'convert_shipping_cost' ), 10, 2 );
		add_action( 'vqcheckout_update_exchange_rates', array( $this, 'update_exchange_rates' ) );

		// Schedule rate updates twice daily
		if ( ! wp_next_scheduled( 'vqcheckout_update_exchange_rates' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'vqcheckout_update_exchange_rates' );
		}
	}

	/**
	 * Convert shipping cost to current currency
	 *
	 * @param float  $cost Shipping cost in VND
	 * @param string $ward_code Ward code (unused, for filter compatibility)
	 * @return float Converted cost
	 */
	public function convert_shipping_cost( $cost, $ward_code = '' ) {
		$current_currency = get_woocommerce_currency();

		if ( $current_currency === $this->base_currency || ! $this->enabled ) {
			return $cost;
		}

		$rate = $this->get_exchange_rate( $this->base_currency, $current_currency );

		if ( ! $rate ) {
			return $cost;
		}

		$converted = $cost * $rate;

		// Round based on currency
		return $this->round_for_currency( $converted, $current_currency );
	}

	/**
	 * Get exchange rate between currencies
	 *
	 * @param string $from From currency
	 * @param string $to To currency
	 * @return float|null
	 */
	public function get_exchange_rate( $from, $to ) {
		if ( $from === $to ) {
			return 1.0;
		}

		$rates = $this->get_exchange_rates();

		$key = $from . '_' . $to;

		return isset( $rates[ $key ] ) ? (float) $rates[ $key ] : null;
	}

	/**
	 * Get all exchange rates
	 *
	 * @return array
	 */
	public function get_exchange_rates() {
		$rates = get_option( self::RATES_OPTION, array() );

		// If empty or expired, try to update
		if ( empty( $rates ) || $this->are_rates_expired() ) {
			$this->update_exchange_rates();
			$rates = get_option( self::RATES_OPTION, array() );
		}

		return $rates;
	}

	/**
	 * Update exchange rates from external API
	 */
	public function update_exchange_rates() {
		// Use a free exchange rate API (example: exchangerate-api.com)
		// In production, you'd use a paid API for better reliability

		$rates = array();

		foreach ( $this->supported_currencies as $currency ) {
			if ( $currency === $this->base_currency ) {
				continue;
			}

			$rate = $this->fetch_rate( $this->base_currency, $currency );

			if ( $rate ) {
				$rates[ $this->base_currency . '_' . $currency ] = $rate;
			}
		}

		if ( ! empty( $rates ) ) {
			update_option( self::RATES_OPTION, $rates );
			update_option( self::RATES_OPTION . '_updated', current_time( 'timestamp' ) );
		}
	}

	/**
	 * Fetch exchange rate from API
	 *
	 * @param string $from From currency
	 * @param string $to To currency
	 * @return float|null
	 */
	private function fetch_rate( $from, $to ) {
		// Static fallback rates (for when API is unavailable)
		$fallback_rates = array(
			'VND_USD' => 0.000041, // 1 VND = 0.000041 USD (approx 24,500 VND = 1 USD)
			'VND_EUR' => 0.000038, // 1 VND = 0.000038 EUR
			'VND_JPY' => 0.0059,   // 1 VND = 0.0059 JPY
			'VND_KRW' => 0.054,    // 1 VND = 0.054 KRW
			'VND_THB' => 0.0014,   // 1 VND = 0.0014 THB
		);

		$key = $from . '_' . $to;

		// Try to fetch from API
		// For now, return fallback rates
		// In production, implement actual API call:
		/*
		$api_url = sprintf(
			'https://api.exchangerate-api.com/v4/latest/%s',
			$from
		);

		$response = wp_remote_get( $api_url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return isset( $fallback_rates[ $key ] ) ? $fallback_rates[ $key ] : null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['rates'][ $to ] ) ) {
			return (float) $data['rates'][ $to ];
		}
		*/

		return isset( $fallback_rates[ $key ] ) ? $fallback_rates[ $key ] : null;
	}

	/**
	 * Check if rates are expired
	 *
	 * @return bool
	 */
	private function are_rates_expired() {
		$last_update = get_option( self::RATES_OPTION . '_updated', 0 );

		return ( current_time( 'timestamp' ) - $last_update ) > self::RATES_CACHE_TTL;
	}

	/**
	 * Round cost for currency
	 *
	 * @param float  $cost Cost to round
	 * @param string $currency Currency code
	 * @return float
	 */
	private function round_for_currency( $cost, $currency ) {
		// Different currencies have different rounding conventions
		switch ( $currency ) {
			case 'JPY':
			case 'KRW':
				// No decimal places
				return round( $cost, 0 );

			case 'VND':
				// Round to nearest 1000
				return round( $cost / 1000 ) * 1000;

			default:
				// 2 decimal places
				return round( $cost, 2 );
		}
	}

	/**
	 * Get supported currencies
	 *
	 * @return array
	 */
	public function get_supported_currencies() {
		return $this->supported_currencies;
	}

	/**
	 * Add supported currency
	 *
	 * @param string $currency Currency code
	 */
	public function add_supported_currency( $currency ) {
		if ( ! in_array( $currency, $this->supported_currencies, true ) ) {
			$this->supported_currencies[] = $currency;
		}
	}

	/**
	 * Convert amount between currencies
	 *
	 * @param float  $amount Amount to convert
	 * @param string $from From currency
	 * @param string $to To currency
	 * @return float|null
	 */
	public function convert( $amount, $from, $to ) {
		$rate = $this->get_exchange_rate( $from, $to );

		if ( ! $rate ) {
			return null;
		}

		$converted = $amount * $rate;

		return $this->round_for_currency( $converted, $to );
	}

	/**
	 * Get exchange rates status
	 *
	 * @return array
	 */
	public function get_status() {
		$last_update = get_option( self::RATES_OPTION . '_updated', 0 );
		$rates       = $this->get_exchange_rates();

		return array(
			'enabled'             => $this->enabled,
			'base_currency'       => $this->base_currency,
			'supported_currencies' => $this->supported_currencies,
			'rates_count'         => count( $rates ),
			'last_update'         => $last_update ? date( 'Y-m-d H:i:s', $last_update ) : __( 'Never', 'vq-checkout' ),
			'is_expired'          => $this->are_rates_expired(),
		);
	}
}

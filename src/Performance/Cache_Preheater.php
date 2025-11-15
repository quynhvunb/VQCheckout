<?php
/**
 * Cache Preheater
 *
 * @package VQCheckout\Performance
 */

namespace VQCheckout\Performance;

use VQCheckout\Core\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Preheat cache for popular wards and shipping zones
 */
class Cache_Preheater {
	const POPULAR_WARDS_OPTION = 'vqcheckout_popular_wards';
	const TOP_WARDS_COUNT      = 50;

	/**
	 * Initialize preheater
	 */
	public function init() {
		add_action( 'vqcheckout_daily_preheat', array( $this, 'preheat_cache' ) );

		// Schedule daily preheat
		if ( ! wp_next_scheduled( 'vqcheckout_daily_preheat' ) ) {
			wp_schedule_event( time(), 'daily', 'vqcheckout_daily_preheat' );
		}
	}

	/**
	 * Preheat cache for popular wards
	 */
	public function preheat_cache() {
		$popular_wards = $this->get_popular_wards();

		if ( empty( $popular_wards ) ) {
			return;
		}

		$plugin   = Plugin::instance();
		$resolver = new \VQCheckout\Shipping\Resolver( $plugin->get( 'cache' ), $plugin->get( 'rate_repository' ) );

		// Common subtotal buckets
		$subtotals = array( 100000, 250000, 500000, 1000000, 2000000 );

		foreach ( $popular_wards as $ward_data ) {
			$ward_code = $ward_data['ward_code'];

			// Get shipping methods for this ward's zone
			$zone_id = $this->get_zone_id_for_ward( $ward_code );

			if ( ! $zone_id ) {
				continue;
			}

			$zone    = \WC_Shipping_Zones::get_zone( $zone_id );
			$methods = $zone->get_shipping_methods();

			foreach ( $methods as $method ) {
				if ( $method->id !== 'vqcheckout_ward_rate' ) {
					continue;
				}

				$instance_id = $method->get_instance_id();

				// Preheat for different subtotals
				foreach ( $subtotals as $subtotal ) {
					try {
						$resolver->resolve( $instance_id, $ward_code, $subtotal );
					} catch ( \Exception $e ) {
						error_log( sprintf( 'Cache preheat error for ward %s: %s', $ward_code, $e->getMessage() ) );
					}
				}
			}
		}

		update_option( 'vqcheckout_last_preheat', current_time( 'mysql' ) );
	}

	/**
	 * Get popular wards based on order history
	 *
	 * @return array
	 */
	public function get_popular_wards() {
		global $wpdb;

		// Try to get from cache
		$cached = get_transient( self::POPULAR_WARDS_OPTION );
		if ( false !== $cached ) {
			return $cached;
		}

		// HPOS support
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$meta_table = $wpdb->prefix . 'wc_orders_meta';

			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_value as ward_code, COUNT(*) as order_count
					FROM {$meta_table}
					WHERE meta_key = '_billing_ward'
					AND meta_value != ''
					GROUP BY meta_value
					ORDER BY order_count DESC
					LIMIT %d",
					self::TOP_WARDS_COUNT
				),
				ARRAY_A
			);
		} else {
			// Classic posts
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_value as ward_code, COUNT(*) as order_count
					FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
					WHERE pm.meta_key = '_billing_ward'
					AND pm.meta_value != ''
					AND p.post_type = 'shop_order'
					AND p.post_status != 'trash'
					GROUP BY pm.meta_value
					ORDER BY order_count DESC
					LIMIT %d",
					self::TOP_WARDS_COUNT
				),
				ARRAY_A
			);
		}

		// Cache for 24 hours
		set_transient( self::POPULAR_WARDS_OPTION, $results, DAY_IN_SECONDS );

		return $results;
	}

	/**
	 * Get zone ID for a ward
	 *
	 * @param string $ward_code Ward code
	 * @return int|null
	 */
	private function get_zone_id_for_ward( $ward_code ) {
		// Get province and district from ward code
		$location_repo = Plugin::instance()->get( 'location_repository' );
		$ward          = $location_repo->get_ward( $ward_code );

		if ( ! $ward ) {
			return null;
		}

		// Find matching zone
		$zones = \WC_Shipping_Zones::get_zones();

		foreach ( $zones as $zone_data ) {
			$zone      = \WC_Shipping_Zones::get_zone( $zone_data['id'] );
			$locations = $zone->get_zone_locations();

			foreach ( $locations as $location ) {
				// Check if ward's province/district matches zone
				if ( $location->type === 'state' && strpos( $location->code, 'VN:' . $ward['province_code'] ) !== false ) {
					return $zone_data['id'];
				}
			}
		}

		return null;
	}

	/**
	 * Manually trigger preheat
	 */
	public function trigger_preheat() {
		$this->preheat_cache();
	}

	/**
	 * Clear popular wards cache
	 */
	public function clear_popular_wards() {
		delete_transient( self::POPULAR_WARDS_OPTION );
	}

	/**
	 * Get preheat status
	 *
	 * @return array
	 */
	public function get_status() {
		$last_preheat  = get_option( 'vqcheckout_last_preheat' );
		$next_schedule = wp_next_scheduled( 'vqcheckout_daily_preheat' );
		$popular_wards = $this->get_popular_wards();

		return array(
			'last_preheat'        => $last_preheat ? $last_preheat : __( 'Never', 'vq-checkout' ),
			'next_scheduled'      => $next_schedule ? date( 'Y-m-d H:i:s', $next_schedule ) : __( 'Not scheduled', 'vq-checkout' ),
			'popular_wards_count' => count( $popular_wards ),
			'is_scheduled'        => (bool) $next_schedule,
		);
	}
}

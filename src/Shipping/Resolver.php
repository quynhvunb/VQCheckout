<?php
/**
 * Shipping Rate Resolver - First Match Wins Algorithm
 *
 * @package VQCheckout\Shipping
 */

namespace VQCheckout\Shipping;

use VQCheckout\Cache\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Core shipping calculation engine
 * Implements First Match Wins with multi-layer caching
 */
class Resolver {
	private $cache;
	private $rate_repo;
	private $debug = false;

	public function __construct( Cache $cache, Rate_Repository $rate_repo ) {
		$this->cache     = $cache;
		$this->rate_repo = $rate_repo;
		$this->debug     = defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * Resolve shipping rate for given parameters
	 *
	 * @param int    $instance_id WC shipping instance ID
	 * @param string $ward_code   Ward code (VN-XX-XXXXX)
	 * @param float  $cart_total  Cart subtotal
	 * @return array {rate_id, label, cost, blocked, meta, cache_hit}
	 */
	public function resolve( $instance_id, $ward_code, $cart_total ) {
		$start_time = microtime( true );

		// Validate input
		if ( empty( $ward_code ) || $cart_total < 0 ) {
			return $this->no_rate_result();
		}

		// Check cache first (L1 → L2 → L3)
		$cache_key = $this->get_cache_key( $instance_id, $ward_code, $cart_total );
		$cached    = $this->cache->get( $cache_key );

		if ( false !== $cached ) {
			$cached['cache_hit'] = true;
			$cached['time_ms']   = round( ( microtime( true ) - $start_time ) * 1000, 2 );
			return $cached;
		}

		// Get applicable rates for this ward
		$rates = $this->rate_repo->get_rates_for_ward( $ward_code, $instance_id );

		if ( empty( $rates ) ) {
			$result = $this->no_rate_result();
			$this->cache->set( $cache_key, $result, 10 * MINUTE_IN_SECONDS );
			return $result;
		}

		// First Match Wins algorithm
		$result = $this->apply_first_match_wins( $rates, $cart_total );

		// Add metadata
		$result['cache_hit'] = false;
		$result['time_ms']   = round( ( microtime( true ) - $start_time ) * 1000, 2 );

		// Cache result
		$this->cache->set( $cache_key, $result, 10 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * Apply First Match Wins logic
	 *
	 * @param array $rates      Rates sorted by rate_order ASC
	 * @param float $cart_total Current cart total
	 * @return array
	 */
	private function apply_first_match_wins( $rates, $cart_total ) {
		foreach ( $rates as $rate ) {
			// Check if this is a blocking rule
			if ( ! empty( $rate['is_block_rule'] ) ) {
				return array(
					'rate_id' => $rate['rate_id'],
					'label'   => $rate['label'],
					'cost'    => 0,
					'blocked' => true,
					'meta'    => array( 'reason' => 'blocked_by_rule' ),
				);
			}

			// Evaluate conditions
			$cost = $this->evaluate_conditions( $rate, $cart_total );

			if ( null !== $cost ) {
				// Match found! Return immediately (First Match Wins)
				$result = array(
					'rate_id' => $rate['rate_id'],
					'label'   => $rate['label'],
					'cost'    => max( 0, $cost ),
					'blocked' => false,
					'meta'    => array(
						'rate_order'      => $rate['rate_order'],
						'base_cost'       => $rate['base_cost'],
						'condition_match' => true,
					),
				);

				// Stop processing if flag is set
				if ( ! empty( $rate['stop_processing'] ) ) {
					return $result;
				}

				// Continue to next rule if stop_processing = 0
				// (allows for "best match" instead of "first match")
				// But typically stop_processing = 1 for First Match Wins
			}
		}

		// No match found
		return $this->no_rate_result();
	}

	/**
	 * Evaluate rate conditions and calculate cost
	 *
	 * @param array $rate       Rate data
	 * @param float $cart_total Cart subtotal
	 * @return float|null Cost if conditions match, null otherwise
	 */
	private function evaluate_conditions( $rate, $cart_total ) {
		$base_cost = (float) $rate['base_cost'];

		// If no conditions, return base cost
		if ( empty( $rate['conditions_json'] ) ) {
			return $base_cost;
		}

		$conditions = json_decode( $rate['conditions_json'], true );
		if ( ! is_array( $conditions ) ) {
			return $base_cost;
		}

		// Evaluate each condition
		foreach ( $conditions as $condition ) {
			$min_total = isset( $condition['min_total'] ) ? (float) $condition['min_total'] : 0;
			$max_total = isset( $condition['max_total'] ) ? (float) $condition['max_total'] : PHP_FLOAT_MAX;
			$cost      = isset( $condition['cost'] ) ? (float) $condition['cost'] : $base_cost;

			// Check if cart total is within range
			if ( $cart_total >= $min_total && $cart_total <= $max_total ) {
				return $cost;
			}
		}

		// No condition matched
		return null;
	}

	/**
	 * Generate cache key
	 *
	 * @param int    $instance_id
	 * @param string $ward_code
	 * @param float  $cart_total
	 * @return string
	 */
	private function get_cache_key( $instance_id, $ward_code, $cart_total ) {
		// Bucket cart total to reduce cache misses (10k increments)
		$bucket = floor( $cart_total / 10000 ) * 10000;
		return "vqcheckout:match:{$instance_id}:{$ward_code}:{$bucket}";
	}

	/**
	 * Return no-rate result
	 *
	 * @return array
	 */
	private function no_rate_result() {
		return array(
			'rate_id' => 0,
			'label'   => '',
			'cost'    => 0,
			'blocked' => false,
			'meta'    => array( 'reason' => 'no_match' ),
		);
	}

	/**
	 * Invalidate cache for specific ward or all
	 *
	 * @param string $ward_code Optional ward code to invalidate
	 */
	public function invalidate_cache( $ward_code = '' ) {
		if ( empty( $ward_code ) ) {
			// Invalidate all match cache
			$this->cache->flush_group( 'vqcheckout:match:' );
		} else {
			// Invalidate specific ward (all instances and buckets)
			$this->cache->delete_pattern( "vqcheckout:match:*:{$ward_code}:*" );
		}
	}
}

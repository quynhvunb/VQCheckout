<?php
/**
 * Shipping Rate Resolver (First Match Wins)
 *
 * @package VQCheckout\Shipping
 */

namespace VQCheckout\Shipping;

use VQCheckout\Cache\Cache;
use VQCheckout\Cache\Keys;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves shipping rate based on ward & subtotal
 */
class Resolver {
	private $cache;
	private $rate_repository;

	public function __construct( Cache $cache, Rate_Repository $rate_repository ) {
		$this->cache = $cache;
		$this->rate_repository = $rate_repository;
	}

	public function resolve( $instance_id, $ward_code, $cart_subtotal ) {
		$key = Keys::rate_match( $instance_id, $ward_code, $cart_subtotal );
		$cached = $this->cache->get( $key );

		if ( false !== $cached ) {
			$cached['cache_hit'] = true;
			return $cached;
		}

		$rates = $this->rate_repository->get_rates_for_ward( $instance_id, $ward_code );

		$result = null;

		foreach ( $rates as $rate ) {
			if ( $rate['is_blocked'] ) {
				$result = array(
					'rate_id'   => $rate['id'],
					'label'     => $rate['title'],
					'cost'      => 0,
					'blocked'   => true,
					'meta'      => array(),
					'cache_hit' => false,
				);
				break;
			}

			$matched_cost = $this->match_conditions( $rate, $cart_subtotal );

			if ( null !== $matched_cost ) {
				$result = array(
					'rate_id'   => $rate['id'],
					'label'     => $rate['title'],
					'cost'      => $matched_cost,
					'blocked'   => false,
					'meta'      => array(),
					'cache_hit' => false,
				);

				if ( $rate['stop_after_match'] ) {
					break;
				}
			}
		}

		if ( null === $result ) {
			$result = array(
				'rate_id'   => 0,
				'label'     => '',
				'cost'      => 0,
				'blocked'   => false,
				'meta'      => array( 'fallback' => true ),
				'cache_hit' => false,
			);
		}

		$this->cache->set( $key, $result, 'vqcheckout', Cache::TTL_MEDIUM );

		return $result;
	}

	private function match_conditions( $rate, $cart_subtotal ) {
		$cost = (float) $rate['cost'];

		if ( empty( $rate['conditions'] ) ) {
			return $cost;
		}

		$conditions = $rate['conditions'];

		if ( isset( $conditions['min'] ) && $cart_subtotal < (float) $conditions['min'] ) {
			return null;
		}

		if ( isset( $conditions['max'] ) && $cart_subtotal > (float) $conditions['max'] ) {
			return null;
		}

		if ( isset( $conditions['free_shipping_min'] ) && $cart_subtotal >= (float) $conditions['free_shipping_min'] ) {
			return 0;
		}

		return $cost;
	}
}

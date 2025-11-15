<?php
/**
 * Cache Key Generator
 *
 * @package VQCheckout\Cache
 */

namespace VQCheckout\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Normalized cache key generation
 */
class Keys {
	public static function rate_match( $instance_id, $ward_code, $subtotal ) {
		$bucket = self::subtotal_bucket( $subtotal );
		return sprintf( 'match:%d:%s:%d', $instance_id, $ward_code, $bucket );
	}

	public static function rates_for_ward( $instance_id, $ward_code ) {
		return sprintf( 'rates:%d:ward:%s', $instance_id, $ward_code );
	}

	public static function location( $code, $level = null ) {
		if ( $level ) {
			return sprintf( 'loc:%d:%s', $level, $code );
		}
		return sprintf( 'loc:%s', $code );
	}

	public static function locations_by_parent( $parent_code, $level ) {
		return sprintf( 'locs:%s:lvl:%d', $parent_code, $level );
	}

	private static function subtotal_bucket( $subtotal ) {
		return (int) floor( $subtotal / 100000 ) * 100000;
	}
}

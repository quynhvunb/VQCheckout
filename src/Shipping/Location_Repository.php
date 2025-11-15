<?php
/**
 * Location Repository
 *
 * @package VQCheckout\Shipping
 */

namespace VQCheckout\Shipping;

use VQCheckout\Cache\Cache;
use VQCheckout\Cache\Keys;

defined( 'ABSPATH' ) || exit;

/**
 * Manages location data (provinces, districts, wards)
 */
class Location_Repository {
	private $cache;

	public function __construct( Cache $cache ) {
		$this->cache = $cache;
	}

	public function get_provinces() {
		$key = Keys::locations_by_parent( 'root', 1 );
		$cached = $this->cache->get( $key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_locations';

		$results = $wpdb->get_results(
			"SELECT code, name, name_with_type, slug, type
			FROM {$table}
			WHERE level = 1
			ORDER BY name ASC",
			ARRAY_A
		);

		$this->cache->set( $key, $results, 'vqcheckout', Cache::TTL_LONG );

		return $results;
	}

	public function get_districts( $province_code ) {
		$key = Keys::locations_by_parent( $province_code, 2 );
		$cached = $this->cache->get( $key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_locations';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT code, name, name_with_type
				FROM {$table}
				WHERE level = 2 AND parent_code = %s
				ORDER BY name ASC",
				$province_code
			),
			ARRAY_A
		);

		$this->cache->set( $key, $results, 'vqcheckout', Cache::TTL_LONG );

		return $results;
	}

	public function get_wards( $district_code ) {
		$key = Keys::locations_by_parent( $district_code, 3 );
		$cached = $this->cache->get( $key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_locations';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT code, name, name_with_type, path
				FROM {$table}
				WHERE level = 3 AND parent_code = %s
				ORDER BY name ASC",
				$district_code
			),
			ARRAY_A
		);

		$this->cache->set( $key, $results, 'vqcheckout', Cache::TTL_LONG );

		return $results;
	}

	public function get_location( $code ) {
		$key = Keys::location( $code );
		$cached = $this->cache->get( $key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_locations';

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE code = %s",
				$code
			),
			ARRAY_A
		);

		if ( $result ) {
			$this->cache->set( $key, $result, 'vqcheckout', Cache::TTL_LONG );
		}

		return $result;
	}
}

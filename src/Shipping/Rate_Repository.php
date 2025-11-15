<?php
/**
 * Rate Repository
 *
 * @package VQCheckout\Shipping
 */

namespace VQCheckout\Shipping;

use VQCheckout\Cache\Cache;
use VQCheckout\Cache\Keys;

defined( 'ABSPATH' ) || exit;

/**
 * Manages shipping rates
 */
class Rate_Repository {
	private $cache;

	public function __construct( Cache $cache ) {
		$this->cache = $cache;
	}

	public function get_rates_for_ward( $instance_id, $ward_code ) {
		$key = Keys::rates_for_ward( $instance_id, $ward_code );
		$cached = $this->cache->get( $key );

		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$rates_table = $wpdb->prefix . 'vqcheckout_ward_rates';
		$locations_table = $wpdb->prefix . 'vqcheckout_rate_locations';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*
				FROM {$rates_table} r
				INNER JOIN {$locations_table} l ON r.id = l.rate_id
				WHERE r.instance_id = %d AND l.ward_code = %s
				ORDER BY r.priority ASC",
				$instance_id,
				$ward_code
			),
			ARRAY_A
		);

		foreach ( $results as &$rate ) {
			if ( ! empty( $rate['conditions'] ) ) {
				$rate['conditions'] = json_decode( $rate['conditions'], true );
			}
		}

		$this->cache->set( $key, $results, 'vqcheckout', Cache::TTL_MEDIUM );

		return $results;
	}

	public function create_rate( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_ward_rates';

		$wpdb->insert(
			$table,
			array(
				'zone_id'          => $data['zone_id'] ?? 0,
				'instance_id'      => $data['instance_id'] ?? 0,
				'title'            => $data['title'] ?? '',
				'cost'             => $data['cost'] ?? 0,
				'priority'         => $data['priority'] ?? 0,
				'is_blocked'       => $data['is_blocked'] ?? 0,
				'stop_after_match' => $data['stop_after_match'] ?? 0,
				'conditions'       => isset( $data['conditions'] ) ? wp_json_encode( $data['conditions'] ) : null,
			),
			array( '%d', '%d', '%s', '%f', '%d', '%d', '%d', '%s' )
		);

		$rate_id = $wpdb->insert_id;

		if ( ! empty( $data['ward_codes'] ) ) {
			$this->attach_wards( $rate_id, $data['ward_codes'] );
		}

		$this->cache->invalidate_rates( $data['zone_id'], $data['instance_id'] );

		return $rate_id;
	}

	public function update_rate( $rate_id, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_ward_rates';

		$update_data = array();
		$format = array();

		foreach ( array( 'title', 'cost', 'priority', 'is_blocked', 'stop_after_match' ) as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$update_data[ $field ] = $data[ $field ];
				$format[] = in_array( $field, array( 'cost' ), true ) ? '%f' : ( in_array( $field, array( 'title' ), true ) ? '%s' : '%d' );
			}
		}

		if ( isset( $data['conditions'] ) ) {
			$update_data['conditions'] = wp_json_encode( $data['conditions'] );
			$format[] = '%s';
		}

		if ( ! empty( $update_data ) ) {
			$wpdb->update(
				$table,
				$update_data,
				array( 'id' => $rate_id ),
				$format,
				array( '%d' )
			);
		}

		if ( isset( $data['ward_codes'] ) ) {
			$this->detach_all_wards( $rate_id );
			$this->attach_wards( $rate_id, $data['ward_codes'] );
		}

		$rate = $this->get_rate( $rate_id );
		$this->cache->invalidate_rates( $rate['zone_id'], $rate['instance_id'] );

		return true;
	}

	public function delete_rate( $rate_id ) {
		$rate = $this->get_rate( $rate_id );

		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_ward_rates';

		$wpdb->delete( $table, array( 'id' => $rate_id ), array( '%d' ) );

		if ( $rate ) {
			$this->cache->invalidate_rates( $rate['zone_id'], $rate['instance_id'] );
		}

		return true;
	}

	public function get_rate( $rate_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_ward_rates';

		$result = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $rate_id ),
			ARRAY_A
		);

		if ( $result && ! empty( $result['conditions'] ) ) {
			$result['conditions'] = json_decode( $result['conditions'], true );
		}

		return $result;
	}

	private function attach_wards( $rate_id, $ward_codes ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_rate_locations';

		foreach ( $ward_codes as $ward_code ) {
			$wpdb->replace(
				$table,
				array(
					'rate_id'   => $rate_id,
					'ward_code' => $ward_code,
				),
				array( '%d', '%s' )
			);
		}
	}

	private function detach_all_wards( $rate_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_rate_locations';

		$wpdb->delete( $table, array( 'rate_id' => $rate_id ), array( '%d' ) );
	}
}

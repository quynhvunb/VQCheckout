<?php
/**
 * Rate Repository - Database operations for shipping rates
 *
 * @package VQCheckout\Shipping
 */

namespace VQCheckout\Shipping;

use VQCheckout\Cache\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Manages shipping rates CRUD operations
 */
class Rate_Repository {
	private $cache;

	public function __construct( Cache $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Get rates for specific ward
	 * Returns rates sorted by rate_order ASC (priority)
	 *
	 * @param string $ward_code   Ward code
	 * @param int    $instance_id Shipping instance ID
	 * @return array
	 */
	public function get_rates_for_ward( $ward_code, $instance_id ) {
		global $wpdb;

		$rates_table = $wpdb->prefix . 'vqcheckout_ward_rates';
		$locations_table = $wpdb->prefix . 'vqcheckout_rate_locations';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*
				FROM {$rates_table} r
				INNER JOIN {$locations_table} l ON r.rate_id = l.rate_id
				WHERE l.ward_code = %s AND r.instance_id = %d
				ORDER BY r.rate_order ASC",
				$ward_code,
				$instance_id
			),
			ARRAY_A
		);

		return $results;
	}

	/**
	 * Create new rate
	 *
	 * @param array $data Rate data
	 * @return int Rate ID
	 */
	public function create_rate( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_ward_rates';

		$result = $wpdb->insert(
			$table,
			array(
				'instance_id'     => $data['instance_id'] ?? 0,
				'rate_order'      => $data['rate_order'] ?? 0,
				'label'           => $data['label'] ?? '',
				'base_cost'       => $data['base_cost'] ?? 0,
				'is_block_rule'   => $data['is_block_rule'] ?? 0,
				'stop_processing' => $data['stop_processing'] ?? 1,
				'conditions_json' => isset( $data['conditions'] ) ? wp_json_encode( $data['conditions'] ) : null,
				'created_by'      => get_current_user_id(),
			),
			array( '%d', '%d', '%s', '%f', '%d', '%d', '%s', '%d' )
		);

		if ( ! $result ) {
			return 0;
		}

		$rate_id = $wpdb->insert_id;

		if ( ! empty( $data['ward_codes'] ) && is_array( $data['ward_codes'] ) ) {
			$this->update_rate_locations( $rate_id, $data['ward_codes'] );
		}

		$this->invalidate_cache( $instance_id ?? 0 );

		return $rate_id;
	}

	/**
	 * Update existing rate
	 *
	 * @param int   $rate_id Rate ID
	 * @param array $data    Updated data
	 * @return bool
	 */
	public function update_rate( $rate_id, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_ward_rates';

		$update_data = array();
		$format = array();

		$fields = array(
			'rate_order'      => '%d',
			'label'           => '%s',
			'base_cost'       => '%f',
			'is_block_rule'   => '%d',
			'stop_processing' => '%d',
		);

		foreach ( $fields as $field => $field_format ) {
			if ( isset( $data[ $field ] ) ) {
				$update_data[ $field ] = $data[ $field ];
				$format[] = $field_format;
			}
		}

		if ( isset( $data['conditions'] ) ) {
			$update_data['conditions_json'] = wp_json_encode( $data['conditions'] );
			$format[] = '%s';
		}

		$update_data['modified_by'] = get_current_user_id();
		$format[] = '%d';

		if ( ! empty( $update_data ) ) {
			$wpdb->update(
				$table,
				$update_data,
				array( 'rate_id' => $rate_id ),
				$format,
				array( '%d' )
			);
		}

		if ( isset( $data['ward_codes'] ) && is_array( $data['ward_codes'] ) ) {
			$this->update_rate_locations( $rate_id, $data['ward_codes'] );
		}

		$rate = $this->get_rate( $rate_id );
		if ( $rate ) {
			$this->invalidate_cache( $rate['instance_id'] );
		}

		return true;
	}

	/**
	 * Delete rate
	 *
	 * @param int $rate_id Rate ID
	 * @return bool
	 */
	public function delete_rate( $rate_id ) {
		$rate = $this->get_rate( $rate_id );

		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_ward_rates';

		$wpdb->delete( $table, array( 'rate_id' => $rate_id ), array( '%d' ) );

		if ( $rate ) {
			$this->invalidate_cache( $rate['instance_id'] );
		}

		return true;
	}

	/**
	 * Get single rate by ID
	 *
	 * @param int $rate_id Rate ID
	 * @return array|null
	 */
	public function get_rate( $rate_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_ward_rates';

		$result = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE rate_id = %d", $rate_id ),
			ARRAY_A
		);

		if ( ! $result ) {
			return null;
		}

		// Decode JSON conditions
		if ( ! empty( $result['conditions_json'] ) ) {
			$result['conditions'] = json_decode( $result['conditions_json'], true );
		}

		// Get associated ward codes
		$result['ward_codes'] = $this->get_rate_ward_codes( $rate_id );

		return $result;
	}

	/**
	 * Get all rates for an instance
	 *
	 * @param int $instance_id Instance ID
	 * @return array
	 */
	public function get_rates_by_instance( $instance_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_ward_rates';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE instance_id = %d ORDER BY rate_order ASC",
				$instance_id
			),
			ARRAY_A
		);

		foreach ( $results as &$rate ) {
			if ( ! empty( $rate['conditions_json'] ) ) {
				$rate['conditions'] = json_decode( $rate['conditions_json'], true );
			}
			$rate['ward_codes'] = $this->get_rate_ward_codes( $rate['rate_id'] );
		}

		return $results;
	}

	/**
	 * Update rate-to-ward mappings
	 *
	 * @param int   $rate_id    Rate ID
	 * @param array $ward_codes Array of ward codes
	 */
	public function update_rate_locations( $rate_id, $ward_codes ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_rate_locations';

		// Delete existing mappings
		$wpdb->delete( $table, array( 'rate_id' => $rate_id ), array( '%d' ) );

		// Insert new mappings
		if ( empty( $ward_codes ) ) {
			return;
		}

		$values = array();
		foreach ( array_unique( $ward_codes ) as $ward_code ) {
			$values[] = $wpdb->prepare( '(%d, %s)', $rate_id, $ward_code );
		}

		if ( ! empty( $values ) ) {
			$wpdb->query(
				"INSERT INTO {$table} (rate_id, ward_code) VALUES " . implode( ', ', $values )
			);
		}
	}

	/**
	 * Get ward codes for a rate
	 *
	 * @param int $rate_id Rate ID
	 * @return array
	 */
	public function get_rate_ward_codes( $rate_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_rate_locations';

		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ward_code FROM {$table} WHERE rate_id = %d",
				$rate_id
			)
		);

		return $results ?: array();
	}

	/**
	 * Invalidate cache for instance
	 *
	 * @param int $instance_id Instance ID
	 */
	private function invalidate_cache( $instance_id ) {
		// Delete all cached results for this instance
		wp_cache_delete( "vqcheckout:match:{$instance_id}:*", 'vqcheckout' );
	}
}

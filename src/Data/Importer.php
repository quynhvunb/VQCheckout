<?php
/**
 * Data Importer (WP-CLI / Admin UI)
 *
 * @package VQCheckout\Data
 */

namespace VQCheckout\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Import data via CLI or Admin
 */
class Importer {
	public function import_locations() {
		$seeder = new Seeder();
		$seeder->seed();

		return array(
			'success' => true,
			'message' => __( 'Locations imported successfully', 'vq-checkout' ),
		);
	}

	public function import_rates_json( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'File not found', 'vq-checkout' ),
			);
		}

		$json = file_get_contents( $file_path );
		$data = json_decode( $json, true );

		if ( ! is_array( $data ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid JSON format', 'vq-checkout' ),
			);
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		try {
			foreach ( $data as $rate_data ) {
				$this->insert_rate( $rate_data );
			}

			$wpdb->query( 'COMMIT' );

			return array(
				'success' => true,
				'message' => sprintf( __( '%d rates imported', 'vq-checkout' ), count( $data ) ),
			);
		} catch ( \Exception $e ) {
			$wpdb->query( 'ROLLBACK' );

			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	private function insert_rate( $data ) {
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
			$this->insert_rate_locations( $rate_id, $data['ward_codes'] );
		}
	}

	private function insert_rate_locations( $rate_id, $ward_codes ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_rate_locations';

		foreach ( $ward_codes as $ward_code ) {
			$wpdb->insert(
				$table,
				array(
					'rate_id'   => $rate_id,
					'ward_code' => $ward_code,
				),
				array( '%d', '%s' )
			);
		}
	}
}

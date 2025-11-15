<?php
/**
 * Database Seeder
 *
 * @package VQCheckout\Data
 */

namespace VQCheckout\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Seed location data from JSON
 */
class Seeder {
	const BATCH_SIZE = 500;

	public function seed() {
		$this->seed_provinces();
		$this->seed_wards();
	}

	private function seed_provinces() {
		$file = VQCHECKOUT_PATH . 'data/vietnam_provinces.json';

		if ( ! file_exists( $file ) ) {
			error_log( 'VQCheckout: vietnam_provinces.json not found' );
			return;
		}

		$json = file_get_contents( $file );
		$data = json_decode( $json, true );

		if ( ! is_array( $data ) ) {
			error_log( 'VQCheckout: Invalid JSON in vietnam_provinces.json' );
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_locations';

		foreach ( array_chunk( $data, self::BATCH_SIZE ) as $batch ) {
			$values = array();
			$placeholders = array();

			foreach ( $batch as $item ) {
				$values[] = $item['code'];
				$values[] = $item['name'];
				$values[] = $item['name_with_type'];
				$values[] = 1;
				$values[] = $item['slug'] ?? '';
				$values[] = $item['type'] ?? '';

				$placeholders[] = "(%s, %s, %s, %d, %s, %s)";
			}

			$sql = "INSERT IGNORE INTO {$table} (code, name, name_with_type, level, slug, type) VALUES ";
			$sql .= implode( ', ', $placeholders );

			$wpdb->query( $wpdb->prepare( $sql, $values ) );
		}
	}

	private function seed_wards() {
		$file = VQCHECKOUT_PATH . 'data/vietnam_wards.json';

		if ( ! file_exists( $file ) ) {
			error_log( 'VQCheckout: vietnam_wards.json not found' );
			return;
		}

		$json = file_get_contents( $file );
		$data = json_decode( $json, true );

		if ( ! is_array( $data ) ) {
			error_log( 'VQCheckout: Invalid JSON in vietnam_wards.json' );
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_locations';

		foreach ( array_chunk( $data, self::BATCH_SIZE ) as $batch ) {
			$values = array();
			$placeholders = array();

			foreach ( $batch as $item ) {
				$values[] = $item['code'];
				$values[] = $item['name'];
				$values[] = $item['name_with_type'];
				$values[] = $item['parent_code'] ?? null;
				$values[] = 3;
				$values[] = $item['path'] ?? '';

				$placeholders[] = "(%s, %s, %s, %s, %d, %s)";
			}

			$sql = "INSERT IGNORE INTO {$table} (code, name, name_with_type, parent_code, level, path) VALUES ";
			$sql .= implode( ', ', $placeholders );

			$wpdb->query( $wpdb->prepare( $sql, $values ) );
		}

		$this->extract_and_seed_districts();
	}

	private function extract_and_seed_districts() {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_locations';

		$sql = "INSERT IGNORE INTO {$table} (code, name, name_with_type, level)
				SELECT DISTINCT parent_code, parent_code, parent_code, 2
				FROM {$table}
				WHERE level = 3 AND parent_code IS NOT NULL
				AND parent_code NOT IN (SELECT code FROM {$table} WHERE level = 2)";

		$wpdb->query( $sql );

		$sql = "UPDATE {$table} w
				INNER JOIN {$table} d ON w.parent_code = d.code AND d.level = 2
				SET d.parent_code = SUBSTRING(d.code, 1, 2)
				WHERE w.level = 3";

		$wpdb->query( $sql );
	}
}

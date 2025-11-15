<?php
/**
 * Import/Export Rates
 *
 * @package VQCheckout\Admin
 */

namespace VQCheckout\Admin;

use VQCheckout\Core\Plugin;
use VQCheckout\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Handle rates import/export
 */
class Import_Export {
	const SCHEMA_VERSION = '1.0';

	/**
	 * Export rates to JSON
	 *
	 * @param array $rate_ids Optional array of rate IDs to export. If empty, export all.
	 * @return array Export data structure
	 */
	public function export_rates( $rate_ids = array() ) {
		global $wpdb;

		$rates_table     = $wpdb->prefix . 'vqcheckout_ward_rates';
		$locations_table = $wpdb->prefix . 'vqcheckout_rate_locations';

		$where = '';
		if ( ! empty( $rate_ids ) ) {
			$ids_placeholder = implode( ',', array_fill( 0, count( $rate_ids ), '%d' ) );
			$where           = $wpdb->prepare( "WHERE id IN ($ids_placeholder)", $rate_ids );
		}

		$rates = $wpdb->get_results(
			"SELECT * FROM {$rates_table} {$where} ORDER BY zone_id, instance_id, priority ASC",
			ARRAY_A
		);

		$export_data = array(
			'meta'    => array(
				'schema_version'  => self::SCHEMA_VERSION,
				'plugin_version'  => VQCHECKOUT_VERSION,
				'export_date'     => current_time( 'mysql' ),
				'export_date_gmt' => current_time( 'mysql', true ),
				'rates_count'     => count( $rates ),
			),
			'rates'   => array(),
			'summary' => array(
				'zones'     => array(),
				'instances' => array(),
			),
		);

		foreach ( $rates as $rate ) {
			$rate_id = (int) $rate['id'];

			// Get ward codes for this rate.
			$ward_codes = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ward_code FROM {$locations_table} WHERE rate_id = %d ORDER BY ward_code",
					$rate_id
				)
			);

			// Decode conditions.
			$conditions = ! empty( $rate['conditions'] ) ? json_decode( $rate['conditions'], true ) : array();

			$export_data['rates'][] = array(
				'zone_id'           => (int) $rate['zone_id'],
				'instance_id'       => (int) $rate['instance_id'],
				'title'             => $rate['title'],
				'cost'              => (float) $rate['cost'],
				'priority'          => (int) $rate['priority'],
				'is_blocked'        => (bool) $rate['is_blocked'],
				'stop_after_match'  => (bool) $rate['stop_after_match'],
				'conditions'        => $conditions,
				'ward_codes'        => $ward_codes,
				'created_at'        => $rate['created_at'],
			);

			// Track zones and instances.
			$export_data['summary']['zones'][ $rate['zone_id'] ]         = true;
			$export_data['summary']['instances'][ $rate['instance_id'] ] = true;
		}

		$export_data['summary']['zones']     = array_keys( $export_data['summary']['zones'] );
		$export_data['summary']['instances'] = array_keys( $export_data['summary']['instances'] );

		return $export_data;
	}

	/**
	 * Import rates from JSON
	 *
	 * @param array $import_data Import data structure
	 * @param array $options Import options (overwrite, skip_existing, etc.)
	 * @return array Result with success/error counts
	 */
	public function import_rates( $import_data, $options = array() ) {
		$defaults = array(
			'overwrite'     => false,
			'skip_existing' => true,
			'validate_only' => false,
		);
		$options  = wp_parse_args( $options, $defaults );

		// Validate schema.
		$validation = $this->validate_import_data( $import_data );
		if ( is_wp_error( $validation ) ) {
			return array(
				'success' => false,
				'error'   => $validation->get_error_message(),
			);
		}

		if ( $options['validate_only'] ) {
			return array(
				'success' => true,
				'message' => __( 'Validation passed', 'vq-checkout' ),
				'counts'  => array(
					'total'    => count( $import_data['rates'] ),
					'valid'    => count( $import_data['rates'] ),
					'invalid'  => 0,
				),
			);
		}

		global $wpdb;

		$rates_table     = $wpdb->prefix . 'vqcheckout_ward_rates';
		$locations_table = $wpdb->prefix . 'vqcheckout_rate_locations';

		$imported = 0;
		$skipped  = 0;
		$errors   = 0;
		$messages = array();

		$wpdb->query( 'START TRANSACTION' );

		try {
			foreach ( $import_data['rates'] as $index => $rate_data ) {
				$rate_validation = $this->validate_rate_data( $rate_data );
				if ( is_wp_error( $rate_validation ) ) {
					$errors++;
					$messages[] = sprintf(
						__( 'Rate #%d: %s', 'vq-checkout' ),
						$index + 1,
						$rate_validation->get_error_message()
					);
					continue;
				}

				// Check if similar rate exists.
				$existing = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$rates_table}
						WHERE zone_id = %d AND instance_id = %d AND title = %s",
						$rate_data['zone_id'],
						$rate_data['instance_id'],
						$rate_data['title']
					)
				);

				if ( $existing && $options['skip_existing'] && ! $options['overwrite'] ) {
					$skipped++;
					continue;
				}

				if ( $existing && $options['overwrite'] ) {
					// Delete existing and reimport.
					$wpdb->delete( $rates_table, array( 'id' => $existing ), array( '%d' ) );
					$wpdb->delete( $locations_table, array( 'rate_id' => $existing ), array( '%d' ) );
				}

				// Insert rate.
				$insert_data = array(
					'zone_id'          => (int) $rate_data['zone_id'],
					'instance_id'      => (int) $rate_data['instance_id'],
					'title'            => sanitize_text_field( $rate_data['title'] ),
					'cost'             => (float) $rate_data['cost'],
					'priority'         => (int) $rate_data['priority'],
					'is_blocked'       => ! empty( $rate_data['is_blocked'] ) ? 1 : 0,
					'stop_after_match' => ! empty( $rate_data['stop_after_match'] ) ? 1 : 0,
					'conditions'       => ! empty( $rate_data['conditions'] ) ? wp_json_encode( $rate_data['conditions'] ) : null,
					'created_at'       => current_time( 'mysql' ),
					'updated_at'       => current_time( 'mysql' ),
				);

				$inserted = $wpdb->insert( $rates_table, $insert_data );

				if ( ! $inserted ) {
					$errors++;
					$messages[] = sprintf(
						__( 'Rate #%d: Database insert failed', 'vq-checkout' ),
						$index + 1
					);
					continue;
				}

				$rate_id = $wpdb->insert_id;

				// Insert ward codes.
				if ( ! empty( $rate_data['ward_codes'] ) ) {
					foreach ( $rate_data['ward_codes'] as $ward_code ) {
						$wpdb->insert(
							$locations_table,
							array(
								'rate_id'   => $rate_id,
								'ward_code' => sanitize_text_field( $ward_code ),
							),
							array( '%d', '%s' )
						);
					}
				}

				$imported++;
			}

			$wpdb->query( 'COMMIT' );

			// Invalidate cache.
			$plugin = Plugin::instance();
			$cache  = $plugin->get( 'cache' );
			$cache->flush_group( 'vqcheckout_rates' );

		} catch ( \Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}

		return array(
			'success'  => true,
			'counts'   => array(
				'imported' => $imported,
				'skipped'  => $skipped,
				'errors'   => $errors,
			),
			'messages' => $messages,
		);
	}

	/**
	 * Validate import data structure
	 *
	 * @param array $data Import data
	 * @return true|\WP_Error
	 */
	private function validate_import_data( $data ) {
		if ( empty( $data ) || ! is_array( $data ) ) {
			return new \WP_Error( 'invalid_data', __( 'Invalid import data', 'vq-checkout' ) );
		}

		if ( empty( $data['meta'] ) || empty( $data['meta']['schema_version'] ) ) {
			return new \WP_Error( 'missing_schema', __( 'Missing schema version', 'vq-checkout' ) );
		}

		if ( version_compare( $data['meta']['schema_version'], self::SCHEMA_VERSION, '>' ) ) {
			return new \WP_Error(
				'schema_version_mismatch',
				sprintf(
					__( 'Schema version %s is not supported. Current version: %s', 'vq-checkout' ),
					$data['meta']['schema_version'],
					self::SCHEMA_VERSION
				)
			);
		}

		if ( empty( $data['rates'] ) || ! is_array( $data['rates'] ) ) {
			return new \WP_Error( 'no_rates', __( 'No rates to import', 'vq-checkout' ) );
		}

		return true;
	}

	/**
	 * Validate single rate data
	 *
	 * @param array $rate_data Rate data
	 * @return true|\WP_Error
	 */
	private function validate_rate_data( $rate_data ) {
		$required = array( 'zone_id', 'instance_id', 'title' );

		foreach ( $required as $field ) {
			if ( ! isset( $rate_data[ $field ] ) ) {
				return new \WP_Error(
					'missing_field',
					sprintf( __( 'Missing required field: %s', 'vq-checkout' ), $field )
				);
			}
		}

		if ( ! is_numeric( $rate_data['zone_id'] ) || $rate_data['zone_id'] < 0 ) {
			return new \WP_Error( 'invalid_zone_id', __( 'Invalid zone_id', 'vq-checkout' ) );
		}

		if ( ! is_numeric( $rate_data['instance_id'] ) || $rate_data['instance_id'] < 0 ) {
			return new \WP_Error( 'invalid_instance_id', __( 'Invalid instance_id', 'vq-checkout' ) );
		}

		if ( empty( $rate_data['title'] ) ) {
			return new \WP_Error( 'empty_title', __( 'Title cannot be empty', 'vq-checkout' ) );
		}

		return true;
	}
}

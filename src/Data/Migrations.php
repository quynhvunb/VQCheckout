<?php
/**
 * Database Migrations
 *
 * @package VQCheckout\Data
 */

namespace VQCheckout\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Idempotent database migrations
 */
class Migrations {
	public function run() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$tables = Schema::get_tables();

		foreach ( $tables as $table_name => $sql ) {
			dbDelta( $sql );
		}

		update_option( 'vqcheckout_db_version', VQCHECKOUT_VERSION );

		$this->maybe_seed_locations();
	}

	private function maybe_seed_locations() {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_locations';

		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		if ( $count == 0 ) {
			$seeder = new Seeder();
			$seeder->seed();
		}
	}

	public static function drop_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'vqcheckout_rate_locations',
			$wpdb->prefix . 'vqcheckout_ward_rates',
			$wpdb->prefix . 'vqcheckout_security_log',
			$wpdb->prefix . 'vqcheckout_locations',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( 'vqcheckout_db_version' );
	}
}

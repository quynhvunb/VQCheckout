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
	/**
	 * Run migrations
	 */
	public function run() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$tables = Schema::get_tables();

		foreach ( $tables as $table_name => $sql ) {
			dbDelta( $sql );
		}

		$this->update_version();
		$this->maybe_seed_sample_data();
	}

	/**
	 * Update database version
	 */
	private function update_version() {
		update_option( 'vqcheckout_db_version', VQCHECKOUT_VERSION );
	}

	/**
	 * Seed sample data on first install (optional)
	 */
	private function maybe_seed_sample_data() {
		if ( get_option( 'vqcheckout_seeded' ) ) {
			return;
		}

		// Optionally seed sample rates here
		// For now, admin will add rates manually or import

		update_option( 'vqcheckout_seeded', '1' );
	}

	/**
	 * Drop all plugin tables (uninstall)
	 */
	public static function drop_tables() {
		global $wpdb;

		// Disable foreign key checks
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' );

		$tables = array(
			$wpdb->prefix . 'vqcheckout_rate_locations',
			$wpdb->prefix . 'vqcheckout_ward_rates',
			$wpdb->prefix . 'vqcheckout_security_log',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		// Re-enable foreign key checks
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' );

		delete_option( 'vqcheckout_db_version' );
		delete_option( 'vqcheckout_seeded' );
	}
}

<?php
/**
 * Migrations Integration Tests
 *
 * @package VQCheckout\Tests\Integration
 */

namespace VQCheckout\Tests\Integration;

use VQCheckout\Data\Migrations;
use VQCheckout\Data\Schema;

/**
 * Test database migrations
 */
class Test_Migrations extends \WP_UnitTestCase {
	public function test_migrations_create_tables() {
		global $wpdb;

		Migrations::drop_tables();

		$migrations = new Migrations();
		$migrations->run();

		$tables = array(
			$wpdb->prefix . 'vqcheckout_ward_rates',
			$wpdb->prefix . 'vqcheckout_rate_locations',
			$wpdb->prefix . 'vqcheckout_security_log',
			$wpdb->prefix . 'vqcheckout_locations',
		);

		foreach ( $tables as $table ) {
			$result = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
			$this->assertEquals( $table, $result, "Table {$table} should exist" );
		}
	}

	public function test_db_version_option() {
		$migrations = new Migrations();
		$migrations->run();

		$version = get_option( 'vqcheckout_db_version' );

		$this->assertEquals( VQCHECKOUT_VERSION, $version );
	}

	public function tearDown(): void {
		Migrations::drop_tables();
		parent::tearDown();
	}
}

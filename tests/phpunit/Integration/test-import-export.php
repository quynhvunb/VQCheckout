<?php
/**
 * Import/Export Integration Tests
 *
 * @package VQCheckout\Tests\Integration
 */

namespace VQCheckout\Tests\Integration;

use VQCheckout\Admin\Import_Export;

/**
 * Test import/export functionality
 */
class Test_Import_Export extends \WP_UnitTestCase {
	private $exporter;
	private $sample_data;

	public function setUp(): void {
		parent::setUp();
		$this->exporter = new Import_Export();

		global $wpdb;
		$rates_table     = $wpdb->prefix . 'vqcheckout_ward_rates';
		$locations_table = $wpdb->prefix . 'vqcheckout_rate_locations';

		// Clean tables.
		$wpdb->query( "TRUNCATE TABLE {$rates_table}" );
		$wpdb->query( "TRUNCATE TABLE {$locations_table}" );

		// Create sample data.
		$this->sample_data = array(
			'meta'  => array(
				'schema_version' => '1.0',
				'plugin_version' => VQCHECKOUT_VERSION,
				'export_date'    => current_time( 'mysql' ),
			),
			'rates' => array(
				array(
					'zone_id'          => 1,
					'instance_id'      => 1,
					'title'            => 'Test Rate 1',
					'cost'             => 25000.00,
					'priority'         => 0,
					'is_blocked'       => false,
					'stop_after_match' => false,
					'conditions'       => array(
						'min' => 100000,
						'max' => 500000,
					),
					'ward_codes'       => array( '00001', '00002' ),
				),
				array(
					'zone_id'          => 1,
					'instance_id'      => 1,
					'title'            => 'Test Rate 2',
					'cost'             => 30000.00,
					'priority'         => 1,
					'is_blocked'       => false,
					'stop_after_match' => true,
					'conditions'       => array(
						'free_shipping_min' => 1000000,
					),
					'ward_codes'       => array( '00003' ),
				),
			),
		);
	}

	public function test_export_empty_database() {
		$result = $this->exporter->export_rates();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'meta', $result );
		$this->assertArrayHasKey( 'rates', $result );
		$this->assertEquals( 0, count( $result['rates'] ) );
		$this->assertEquals( '1.0', $result['meta']['schema_version'] );
	}

	public function test_import_valid_data() {
		$result = $this->exporter->import_rates( $this->sample_data );

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 2, $result['counts']['imported'] );
		$this->assertEquals( 0, $result['counts']['errors'] );

		global $wpdb;
		$rates_table = $wpdb->prefix . 'vqcheckout_ward_rates';
		$count       = $wpdb->get_var( "SELECT COUNT(*) FROM {$rates_table}" );
		$this->assertEquals( 2, $count );
	}

	public function test_import_creates_ward_codes() {
		$this->exporter->import_rates( $this->sample_data );

		global $wpdb;
		$locations_table = $wpdb->prefix . 'vqcheckout_rate_locations';
		$count           = $wpdb->get_var( "SELECT COUNT(*) FROM {$locations_table}" );

		$this->assertEquals( 3, $count );
	}

	public function test_import_invalid_schema() {
		$invalid_data = array(
			'meta'  => array(),
			'rates' => array(),
		);

		$result = $this->exporter->import_rates( $invalid_data );

		$this->assertFalse( $result['success'] );
		$this->assertArrayHasKey( 'error', $result );
	}

	public function test_import_missing_required_fields() {
		$invalid_data = array(
			'meta'  => array(
				'schema_version' => '1.0',
			),
			'rates' => array(
				array(
					'title' => 'Missing Fields',
				),
			),
		);

		$result = $this->exporter->import_rates( $invalid_data );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 0, $result['counts']['imported'] );
		$this->assertEquals( 1, $result['counts']['errors'] );
	}

	public function test_import_skip_existing() {
		$this->exporter->import_rates( $this->sample_data );

		$result = $this->exporter->import_rates(
			$this->sample_data,
			array( 'skip_existing' => true )
		);

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 0, $result['counts']['imported'] );
		$this->assertEquals( 2, $result['counts']['skipped'] );
	}

	public function test_import_overwrite_existing() {
		$this->exporter->import_rates( $this->sample_data );

		$modified_data                         = $this->sample_data;
		$modified_data['rates'][0]['cost']     = 35000.00;
		$modified_data['rates'][0]['priority'] = 10;

		$result = $this->exporter->import_rates(
			$modified_data,
			array( 'overwrite' => true )
		);

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 2, $result['counts']['imported'] );

		global $wpdb;
		$rates_table = $wpdb->prefix . 'vqcheckout_ward_rates';
		$cost        = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT cost FROM {$rates_table} WHERE title = %s",
				'Test Rate 1'
			)
		);

		$this->assertEquals( 35000.00, (float) $cost );
	}

	public function test_export_after_import() {
		$this->exporter->import_rates( $this->sample_data );

		$exported = $this->exporter->export_rates();

		$this->assertIsArray( $exported );
		$this->assertEquals( 2, count( $exported['rates'] ) );
		$this->assertEquals( 2, $exported['meta']['rates_count'] );
		$this->assertArrayHasKey( 'export_date', $exported['meta'] );
	}

	public function test_export_specific_rates() {
		$this->exporter->import_rates( $this->sample_data );

		global $wpdb;
		$rates_table = $wpdb->prefix . 'vqcheckout_ward_rates';
		$first_id    = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$rates_table} WHERE title = %s",
				'Test Rate 1'
			)
		);

		$exported = $this->exporter->export_rates( array( $first_id ) );

		$this->assertEquals( 1, count( $exported['rates'] ) );
		$this->assertEquals( 'Test Rate 1', $exported['rates'][0]['title'] );
	}

	public function test_import_validate_only() {
		$result = $this->exporter->import_rates(
			$this->sample_data,
			array( 'validate_only' => true )
		);

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 2, $result['counts']['total'] );

		global $wpdb;
		$rates_table = $wpdb->prefix . 'vqcheckout_ward_rates';
		$count       = $wpdb->get_var( "SELECT COUNT(*) FROM {$rates_table}" );

		$this->assertEquals( 0, $count );
	}

	public function test_export_preserves_conditions() {
		$this->exporter->import_rates( $this->sample_data );

		$exported = $this->exporter->export_rates();

		$rate1 = $exported['rates'][0];
		$this->assertArrayHasKey( 'conditions', $rate1 );
		$this->assertEquals( 100000, $rate1['conditions']['min'] );
		$this->assertEquals( 500000, $rate1['conditions']['max'] );
	}

	public function test_export_includes_summary() {
		$this->exporter->import_rates( $this->sample_data );

		$exported = $this->exporter->export_rates();

		$this->assertArrayHasKey( 'summary', $exported );
		$this->assertArrayHasKey( 'zones', $exported['summary'] );
		$this->assertArrayHasKey( 'instances', $exported['summary'] );
		$this->assertContains( 1, $exported['summary']['zones'] );
		$this->assertContains( 1, $exported['summary']['instances'] );
	}

	public function tearDown(): void {
		global $wpdb;
		$rates_table     = $wpdb->prefix . 'vqcheckout_ward_rates';
		$locations_table = $wpdb->prefix . 'vqcheckout_rate_locations';

		$wpdb->query( "TRUNCATE TABLE {$rates_table}" );
		$wpdb->query( "TRUNCATE TABLE {$locations_table}" );

		parent::tearDown();
	}
}

<?php
/**
 * Database Schema - CORRECTED to match plan
 *
 * @package VQCheckout\Data
 */

namespace VQCheckout\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Database schema definitions
 */
class Schema {
	public static function get_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		return array(
			'ward_rates'      => self::ward_rates_schema( $charset_collate ),
			'rate_locations'  => self::rate_locations_schema( $charset_collate ),
			'security_log'    => self::security_log_schema( $charset_collate ),
		);
	}

	/**
	 * Table 1: Ward Rates - Main rates definition
	 */
	private static function ward_rates_schema( $charset_collate ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_ward_rates';

		return "CREATE TABLE {$table} (
			rate_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
			instance_id bigint(20) UNSIGNED NOT NULL COMMENT 'WC Shipping Method instance ID',
			rate_order int(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Priority (0 = highest)',
			label varchar(190) NOT NULL DEFAULT '' COMMENT 'Display label for rate',
			base_cost decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Base shipping cost',
			is_block_rule tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = No shipping allowed',
			conditions_json longtext NULL COMMENT 'JSON: min/max cart total, etc.',
			stop_processing tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = Stop on match (First Match Wins)',
			date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Creation time',
			date_modified datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last modified',
			created_by bigint(20) UNSIGNED NULL COMMENT 'User ID who created',
			modified_by bigint(20) UNSIGNED NULL COMMENT 'User ID who last modified',
			PRIMARY KEY (rate_id),
			KEY idx_instance_order (instance_id, rate_order),
			KEY idx_modified (date_modified)
		) {$charset_collate} COMMENT='Main rates table';";
	}

	/**
	 * Table 2: Rate Locations - Mapping rate to ward codes
	 */
	private static function rate_locations_schema( $charset_collate ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_rate_locations';
		$rates_table = $wpdb->prefix . 'vqcheckout_ward_rates';

		return "CREATE TABLE {$table} (
			rate_id bigint(20) UNSIGNED NOT NULL COMMENT 'FK to ward_rates',
			ward_code varchar(16) NOT NULL COMMENT 'Ward code: VN-{PROV}-{WARD}',
			PRIMARY KEY (rate_id, ward_code),
			KEY idx_ward (ward_code),
			CONSTRAINT fk_rate_loc_rate
				FOREIGN KEY (rate_id)
				REFERENCES {$rates_table}(rate_id)
				ON DELETE CASCADE
		) {$charset_collate} COMMENT='Rate-to-ward mapping';";
	}

	/**
	 * Table 3: Security Log - Audit trail
	 */
	private static function security_log_schema( $charset_collate ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_security_log';

		return "CREATE TABLE {$table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Log entry ID',
			ip varbinary(16) NOT NULL COMMENT 'Client IP (IPv4/IPv6 binary)',
			action varchar(50) NOT NULL COMMENT 'Action type',
			ctx varchar(100) NULL COMMENT 'Context (endpoint/route)',
			score decimal(3,2) NULL COMMENT 'reCAPTCHA score (0.0-1.0)',
			decision enum('allow','deny','challenge') NOT NULL DEFAULT 'allow' COMMENT 'Decision made',
			metadata longtext NULL COMMENT 'Additional JSON data',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Log time',
			PRIMARY KEY (id),
			KEY idx_ip_action (ip, action),
			KEY idx_created (created_at)
		) {$charset_collate} COMMENT='Security audit log';";
	}
}

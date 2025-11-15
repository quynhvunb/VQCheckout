<?php
/**
 * Database Schema
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
			'locations'       => self::locations_schema( $charset_collate ),
			'analytics'       => self::analytics_schema( $charset_collate ),
		);
	}

	private static function ward_rates_schema( $charset_collate ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_ward_rates';

		return "CREATE TABLE {$table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			zone_id bigint(20) UNSIGNED NOT NULL,
			instance_id bigint(20) UNSIGNED NOT NULL,
			title varchar(255) NOT NULL,
			cost decimal(10,2) NOT NULL DEFAULT '0.00',
			priority int(11) NOT NULL DEFAULT '0',
			is_blocked tinyint(1) NOT NULL DEFAULT '0',
			stop_after_match tinyint(1) NOT NULL DEFAULT '0',
			conditions longtext,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY zone_id (zone_id),
			KEY instance_id (instance_id),
			KEY priority (priority)
		) $charset_collate;";
	}

	private static function rate_locations_schema( $charset_collate ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_rate_locations';

		return "CREATE TABLE {$table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			rate_id bigint(20) UNSIGNED NOT NULL,
			ward_code varchar(10) NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY rate_ward (rate_id, ward_code),
			KEY ward_code (ward_code),
			CONSTRAINT fk_rate_locations_rate FOREIGN KEY (rate_id) REFERENCES {$wpdb->prefix}vqcheckout_ward_rates(id) ON DELETE CASCADE
		) $charset_collate;";
	}

	private static function security_log_schema( $charset_collate ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_security_log';

		return "CREATE TABLE {$table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			ip_address varchar(45) NOT NULL,
			action varchar(50) NOT NULL,
			score decimal(3,2) DEFAULT NULL,
			decision varchar(20) NOT NULL,
			metadata longtext,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY ip_address (ip_address),
			KEY action (action),
			KEY created_at (created_at)
		) $charset_collate;";
	}

	private static function locations_schema( $charset_collate ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_locations';

		return "CREATE TABLE {$table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			code varchar(10) NOT NULL,
			name varchar(255) NOT NULL,
			name_with_type varchar(255) NOT NULL,
			parent_code varchar(10) DEFAULT NULL,
			level tinyint(1) NOT NULL COMMENT '1=province, 2=district, 3=ward',
			slug varchar(255) DEFAULT NULL,
			type varchar(50) DEFAULT NULL,
			path text,
			PRIMARY KEY (id),
			UNIQUE KEY code (code),
			KEY parent_code (parent_code),
			KEY level (level),
			KEY slug (slug)
		) $charset_collate;";
	}

	private static function analytics_schema( $charset_collate ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_analytics';

		return "CREATE TABLE {$table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			event_type varchar(50) NOT NULL,
			ward_code varchar(10) DEFAULT NULL,
			province varchar(10) DEFAULT NULL,
			district varchar(10) DEFAULT NULL,
			order_id bigint(20) UNSIGNED DEFAULT NULL,
			order_total decimal(10,2) DEFAULT NULL,
			shipping_cost decimal(10,2) DEFAULT NULL,
			cost decimal(10,2) DEFAULT NULL,
			cache_hit tinyint(1) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY event_type (event_type),
			KEY ward_code (ward_code),
			KEY province (province),
			KEY created_at (created_at),
			KEY event_date (event_type, created_at)
		) $charset_collate;";
	}
}

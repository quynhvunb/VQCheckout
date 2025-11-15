<?php
/**
 * Rate Limiter
 *
 * @package VQCheckout\Security
 */

namespace VQCheckout\Security;

defined( 'ABSPATH' ) || exit;

/**
 * IP-based rate limiting
 */
class Rate_Limiter {
	const LIMIT     = 10;
	const WINDOW    = 600;
	const TRANSIENT_PREFIX = 'vqcheckout_ratelimit_';

	public static function check( $action, $ip = null ) {
		if ( ! $ip ) {
			$ip = self::get_ip();
		}

		$key   = self::TRANSIENT_PREFIX . md5( $action . $ip );
		$count = get_transient( $key );

		if ( false === $count ) {
			set_transient( $key, 1, self::WINDOW );
			return true;
		}

		if ( $count >= self::LIMIT ) {
			self::log_blocked( $action, $ip );
			return false;
		}

		set_transient( $key, $count + 1, self::WINDOW );
		return true;
	}

	public static function get_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$ip  = trim( $ips[0] );
		} elseif ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			$ip = $_SERVER['HTTP_X_REAL_IP'];
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		return sanitize_text_field( $ip );
	}

	private static function log_blocked( $action, $ip ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_security_log';

		$wpdb->insert(
			$table,
			array(
				'ip_address' => $ip,
				'action'     => $action,
				'decision'   => 'blocked',
				'metadata'   => wp_json_encode( array( 'reason' => 'rate_limit' ) ),
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}
}

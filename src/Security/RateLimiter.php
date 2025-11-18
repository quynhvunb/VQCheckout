<?php
/**
 * Rate Limiter - Prevent abuse with IP-based throttling
 *
 * @package VQCheckout\Security
 */

namespace VQCheckout\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Transient-based rate limiting
 */
class RateLimiter {
	const DEFAULT_MAX_REQUESTS = 10;
	const DEFAULT_TIME_WINDOW  = 600; // 10 minutes

	/**
	 * Check if request is allowed
	 *
	 * @param string $action         Action identifier
	 * @param string $identifier     IP or user ID
	 * @param int    $max_requests   Max requests allowed
	 * @param int    $time_window    Time window in seconds
	 * @return bool
	 */
	public function is_allowed( $action, $identifier = null, $max_requests = null, $time_window = null ) {
		if ( null === $identifier ) {
			$identifier = $this->get_client_ip();
		}

		if ( null === $max_requests ) {
			$max_requests = self::DEFAULT_MAX_REQUESTS;
		}

		if ( null === $time_window ) {
			$time_window = self::DEFAULT_TIME_WINDOW;
		}

		$key = $this->get_key( $action, $identifier );
		$count = get_transient( $key );

		if ( false === $count ) {
			$count = 0;
		}

		if ( $count >= $max_requests ) {
			$this->log_blocked( $action, $identifier, $count );
			return false;
		}

		$count++;
		set_transient( $key, $count, $time_window );

		return true;
	}

	/**
	 * Get current count for identifier
	 *
	 * @param string $action     Action identifier
	 * @param string $identifier IP or user ID
	 * @return int
	 */
	public function get_count( $action, $identifier = null ) {
		if ( null === $identifier ) {
			$identifier = $this->get_client_ip();
		}

		$key = $this->get_key( $action, $identifier );
		$count = get_transient( $key );

		return $count !== false ? (int) $count : 0;
	}

	/**
	 * Reset count for identifier
	 *
	 * @param string $action     Action identifier
	 * @param string $identifier IP or user ID
	 */
	public function reset( $action, $identifier = null ) {
		if ( null === $identifier ) {
			$identifier = $this->get_client_ip();
		}

		$key = $this->get_key( $action, $identifier );
		delete_transient( $key );
	}

	/**
	 * Generate transient key
	 *
	 * @param string $action     Action identifier
	 * @param string $identifier IP or user ID
	 * @return string
	 */
	private function get_key( $action, $identifier ) {
		return 'vqcheckout_ratelimit_' . md5( $action . ':' . $identifier );
	}

	/**
	 * Get client IP
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$headers = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Log blocked request
	 *
	 * @param string $action     Action identifier
	 * @param string $identifier IP or user ID
	 * @param int    $count      Request count
	 */
	private function log_blocked( $action, $identifier, $count ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_security_log';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
			return;
		}

		$wpdb->insert(
			$table,
			array(
				'ip'       => inet_pton( $identifier ),
				'action'   => 'rate_limit_exceeded',
				'ctx'      => $action,
				'decision' => 'deny',
				'metadata' => wp_json_encode( array( 'count' => $count ) ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}
}

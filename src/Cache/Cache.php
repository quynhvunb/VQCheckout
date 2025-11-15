<?php
/**
 * 3-tier Cache Service
 *
 * @package VQCheckout\Cache
 */

namespace VQCheckout\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * L1 (runtime) → L2 (object cache) → L3 (transient/Redis)
 */
class Cache {
	private $runtime = array();

	const TTL_SHORT = 300;
	const TTL_MEDIUM = 900;
	const TTL_LONG = 3600;

	public function get( $key, $group = 'vqcheckout' ) {
		$full_key = $this->build_key( $key, $group );

		if ( isset( $this->runtime[ $full_key ] ) ) {
			return $this->runtime[ $full_key ];
		}

		$value = wp_cache_get( $key, $group );

		if ( false !== $value ) {
			$this->runtime[ $full_key ] = $value;
			return $value;
		}

		$value = get_transient( $full_key );

		if ( false !== $value ) {
			wp_cache_set( $key, $value, $group, self::TTL_MEDIUM );
			$this->runtime[ $full_key ] = $value;
			return $value;
		}

		return false;
	}

	public function set( $key, $value, $group = 'vqcheckout', $ttl = self::TTL_MEDIUM ) {
		$full_key = $this->build_key( $key, $group );

		$this->runtime[ $full_key ] = $value;

		wp_cache_set( $key, $value, $group, $ttl );

		set_transient( $full_key, $value, $ttl );

		return $value;
	}

	public function delete( $key, $group = 'vqcheckout' ) {
		$full_key = $this->build_key( $key, $group );

		unset( $this->runtime[ $full_key ] );

		wp_cache_delete( $key, $group );

		delete_transient( $full_key );

		return true;
	}

	public function flush( $group = 'vqcheckout' ) {
		$this->runtime = array();

		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_' . $group . '%'
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_timeout_' . $group . '%'
			)
		);

		return true;
	}

	public function invalidate_rates( $zone_id = null, $instance_id = null ) {
		if ( $zone_id ) {
			$this->delete( "rates:zone:{$zone_id}" );
		}

		if ( $instance_id ) {
			$this->delete( "rates:instance:{$instance_id}" );
		}

		$this->flush( 'vqcheckout' );
	}

	private function build_key( $key, $group ) {
		return $group . ':' . $key;
	}
}

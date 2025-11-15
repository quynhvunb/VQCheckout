<?php
/**
 * Analytics Tracker
 *
 * @package VQCheckout\Analytics
 */

namespace VQCheckout\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * Track checkout and shipping analytics
 */
class Tracker {
	const STATS_TABLE = 'vqcheckout_analytics';

	private $enabled = false;

	/**
	 * Initialize tracker
	 */
	public function init() {
		$options       = get_option( 'vqcheckout_options', array() );
		$this->enabled = ! empty( $options['enable_analytics'] );

		if ( ! $this->enabled ) {
			return;
		}

		add_action( 'woocommerce_checkout_order_processed', array( $this, 'track_order' ), 10, 1 );
		add_action( 'vqcheckout_shipping_resolved', array( $this, 'track_shipping_resolution' ), 10, 3 );
	}

	/**
	 * Track completed order
	 *
	 * @param int $order_id Order ID
	 */
	public function track_order( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$ward_code = $order->get_meta( '_billing_ward' );
		$province  = $order->get_meta( '_billing_province' );
		$district  = $order->get_meta( '_billing_district' );

		if ( ! $ward_code ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . self::STATS_TABLE;

		$wpdb->insert(
			$table,
			array(
				'event_type'   => 'order_completed',
				'ward_code'    => $ward_code,
				'province'     => $province,
				'district'     => $district,
				'order_id'     => $order_id,
				'order_total'  => $order->get_total(),
				'shipping_cost' => $order->get_shipping_total(),
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%s' )
		);
	}

	/**
	 * Track shipping rate resolution
	 *
	 * @param string $ward_code Ward code
	 * @param float  $cost Resolved shipping cost
	 * @param bool   $cache_hit Whether result was from cache
	 */
	public function track_shipping_resolution( $ward_code, $cost, $cache_hit ) {
		global $wpdb;
		$table = $wpdb->prefix . self::STATS_TABLE;

		$wpdb->insert(
			$table,
			array(
				'event_type' => 'shipping_resolved',
				'ward_code'  => $ward_code,
				'cost'       => $cost,
				'cache_hit'  => $cache_hit ? 1 : 0,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%f', '%d', '%s' )
		);
	}

	/**
	 * Get checkout statistics
	 *
	 * @param string $start_date Start date (Y-m-d)
	 * @param string $end_date End date (Y-m-d)
	 * @return array
	 */
	public function get_checkout_stats( $start_date = null, $end_date = null ) {
		global $wpdb;
		$table = $wpdb->prefix . self::STATS_TABLE;

		if ( ! $start_date ) {
			$start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
		}

		if ( ! $end_date ) {
			$end_date = date( 'Y-m-d' );
		}

		$total_orders = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE event_type = 'order_completed'
				AND DATE(created_at) BETWEEN %s AND %s",
				$start_date,
				$end_date
			)
		);

		$total_revenue = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(order_total) FROM {$table}
				WHERE event_type = 'order_completed'
				AND DATE(created_at) BETWEEN %s AND %s",
				$start_date,
				$end_date
			)
		);

		$total_shipping = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(shipping_cost) FROM {$table}
				WHERE event_type = 'order_completed'
				AND DATE(created_at) BETWEEN %s AND %s",
				$start_date,
				$end_date
			)
		);

		$avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;

		return array(
			'period'           => array(
				'start' => $start_date,
				'end'   => $end_date,
			),
			'total_orders'     => (int) $total_orders,
			'total_revenue'    => (float) $total_revenue,
			'total_shipping'   => (float) $total_shipping,
			'avg_order_value'  => round( $avg_order_value, 2 ),
			'avg_shipping'     => $total_orders > 0 ? round( $total_shipping / $total_orders, 2 ) : 0,
		);
	}

	/**
	 * Get popular wards
	 *
	 * @param int    $limit Number of results
	 * @param string $start_date Start date
	 * @param string $end_date End date
	 * @return array
	 */
	public function get_popular_wards( $limit = 10, $start_date = null, $end_date = null ) {
		global $wpdb;
		$table = $wpdb->prefix . self::STATS_TABLE;

		if ( ! $start_date ) {
			$start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
		}

		if ( ! $end_date ) {
			$end_date = date( 'Y-m-d' );
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ward_code, COUNT(*) as order_count, SUM(order_total) as total_revenue
				FROM {$table}
				WHERE event_type = 'order_completed'
				AND DATE(created_at) BETWEEN %s AND %s
				GROUP BY ward_code
				ORDER BY order_count DESC
				LIMIT %d",
				$start_date,
				$end_date,
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Get cache performance stats
	 *
	 * @param string $start_date Start date
	 * @param string $end_date End date
	 * @return array
	 */
	public function get_cache_stats( $start_date = null, $end_date = null ) {
		global $wpdb;
		$table = $wpdb->prefix . self::STATS_TABLE;

		if ( ! $start_date ) {
			$start_date = date( 'Y-m-d', strtotime( '-7 days' ) );
		}

		if ( ! $end_date ) {
			$end_date = date( 'Y-m-d' );
		}

		$total_resolutions = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE event_type = 'shipping_resolved'
				AND DATE(created_at) BETWEEN %s AND %s",
				$start_date,
				$end_date
			)
		);

		$cache_hits = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE event_type = 'shipping_resolved'
				AND cache_hit = 1
				AND DATE(created_at) BETWEEN %s AND %s",
				$start_date,
				$end_date
			)
		);

		$hit_rate = $total_resolutions > 0 ? ( $cache_hits / $total_resolutions ) * 100 : 0;

		return array(
			'period'            => array(
				'start' => $start_date,
				'end'   => $end_date,
			),
			'total_resolutions' => (int) $total_resolutions,
			'cache_hits'        => (int) $cache_hits,
			'cache_misses'      => (int) ( $total_resolutions - $cache_hits ),
			'hit_rate'          => round( $hit_rate, 2 ),
		);
	}

	/**
	 * Get orders by province
	 *
	 * @param string $start_date Start date
	 * @param string $end_date End date
	 * @return array
	 */
	public function get_orders_by_province( $start_date = null, $end_date = null ) {
		global $wpdb;
		$table = $wpdb->prefix . self::STATS_TABLE;

		if ( ! $start_date ) {
			$start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
		}

		if ( ! $end_date ) {
			$end_date = date( 'Y-m-d' );
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT province, COUNT(*) as order_count, SUM(order_total) as total_revenue
				FROM {$table}
				WHERE event_type = 'order_completed'
				AND DATE(created_at) BETWEEN %s AND %s
				GROUP BY province
				ORDER BY order_count DESC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);
	}

	/**
	 * Get daily statistics
	 *
	 * @param int $days Number of days
	 * @return array
	 */
	public function get_daily_stats( $days = 30 ) {
		global $wpdb;
		$table = $wpdb->prefix . self::STATS_TABLE;

		$start_date = date( 'Y-m-d', strtotime( "-{$days} days" ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) as date,
					COUNT(*) as orders,
					SUM(order_total) as revenue,
					SUM(shipping_cost) as shipping
				FROM {$table}
				WHERE event_type = 'order_completed'
				AND DATE(created_at) >= %s
				GROUP BY DATE(created_at)
				ORDER BY date ASC",
				$start_date
			),
			ARRAY_A
		);
	}

	/**
	 * Clear old analytics data
	 *
	 * @param int $days Keep data for this many days
	 */
	public function cleanup_old_data( $days = 90 ) {
		global $wpdb;
		$table = $wpdb->prefix . self::STATS_TABLE;

		$cutoff_date = date( 'Y-m-d', strtotime( "-{$days} days" ) );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE DATE(created_at) < %s",
				$cutoff_date
			)
		);
	}
}

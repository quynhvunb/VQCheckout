<?php
/**
 * Performance Monitor
 *
 * @package VQCheckout\Performance
 */

namespace VQCheckout\Performance;

defined( 'ABSPATH' ) || exit;

/**
 * Monitor and track plugin performance metrics
 */
class Monitor {
	const METRICS_OPTION = 'vqcheckout_performance_metrics';
	const MAX_SAMPLES    = 1000;

	private $enabled = false;
	private $start_time;
	private $metrics = array();

	/**
	 * Initialize monitor
	 */
	public function init() {
		$options       = get_option( 'vqcheckout_options', array() );
		$this->enabled = ! empty( $options['enable_performance_monitor'] );

		if ( ! $this->enabled ) {
			return;
		}

		add_action( 'shutdown', array( $this, 'record_page_metrics' ), 999 );
	}

	/**
	 * Start timing an operation
	 *
	 * @param string $operation Operation name
	 * @return int Timer ID
	 */
	public function start( $operation ) {
		if ( ! $this->enabled ) {
			return 0;
		}

		$timer_id = uniqid( $operation . '_', true );

		$this->metrics[ $timer_id ] = array(
			'operation'  => $operation,
			'start_time' => microtime( true ),
			'memory'     => memory_get_usage(),
		);

		return $timer_id;
	}

	/**
	 * Stop timing an operation
	 *
	 * @param int $timer_id Timer ID from start()
	 */
	public function stop( $timer_id ) {
		if ( ! $this->enabled || ! isset( $this->metrics[ $timer_id ] ) ) {
			return;
		}

		$metric = &$this->metrics[ $timer_id ];

		$metric['end_time']    = microtime( true );
		$metric['duration']    = ( $metric['end_time'] - $metric['start_time'] ) * 1000; // ms
		$metric['memory_peak'] = memory_get_peak_usage();
		$metric['memory_used'] = $metric['memory_peak'] - $metric['memory'];

		$this->record_metric( $metric );
	}

	/**
	 * Record a metric sample
	 *
	 * @param array $metric Metric data
	 */
	private function record_metric( $metric ) {
		$stored_metrics = get_option( self::METRICS_OPTION, array() );

		$operation = $metric['operation'];

		if ( ! isset( $stored_metrics[ $operation ] ) ) {
			$stored_metrics[ $operation ] = array(
				'samples'     => array(),
				'count'       => 0,
				'total_time'  => 0,
				'min_time'    => PHP_FLOAT_MAX,
				'max_time'    => 0,
				'avg_time'    => 0,
				'last_update' => current_time( 'timestamp' ),
			);
		}

		$op_metrics = &$stored_metrics[ $operation ];

		// Add sample
		$op_metrics['samples'][] = array(
			'duration' => $metric['duration'],
			'memory'   => $metric['memory_used'],
			'time'     => current_time( 'timestamp' ),
		);

		// Limit samples
		if ( count( $op_metrics['samples'] ) > self::MAX_SAMPLES ) {
			array_shift( $op_metrics['samples'] );
		}

		// Update aggregates
		$op_metrics['count']++;
		$op_metrics['total_time'] += $metric['duration'];
		$op_metrics['min_time']    = min( $op_metrics['min_time'], $metric['duration'] );
		$op_metrics['max_time']    = max( $op_metrics['max_time'], $metric['duration'] );
		$op_metrics['avg_time']    = $op_metrics['total_time'] / $op_metrics['count'];
		$op_metrics['last_update'] = current_time( 'timestamp' );

		update_option( self::METRICS_OPTION, $stored_metrics );
	}

	/**
	 * Record page-level metrics
	 */
	public function record_page_metrics() {
		if ( ! $this->enabled ) {
			return;
		}

		global $wpdb;

		$page_metrics = array(
			'page_load_time' => ( microtime( true ) - $_SERVER['REQUEST_TIME_FLOAT'] ) * 1000,
			'memory_peak'    => memory_get_peak_usage() / 1024 / 1024, // MB
			'db_queries'     => $wpdb->num_queries,
		);

		// Record as a metric
		$this->record_metric(
			array(
				'operation'    => 'page_load',
				'start_time'   => $_SERVER['REQUEST_TIME_FLOAT'],
				'end_time'     => microtime( true ),
				'duration'     => $page_metrics['page_load_time'],
				'memory_used'  => $page_metrics['memory_peak'],
				'memory_peak'  => memory_get_peak_usage(),
			)
		);
	}

	/**
	 * Get metrics for an operation
	 *
	 * @param string $operation Operation name
	 * @return array|null
	 */
	public function get_metrics( $operation ) {
		$metrics = get_option( self::METRICS_OPTION, array() );

		return isset( $metrics[ $operation ] ) ? $metrics[ $operation ] : null;
	}

	/**
	 * Get all metrics
	 *
	 * @return array
	 */
	public function get_all_metrics() {
		return get_option( self::METRICS_OPTION, array() );
	}

	/**
	 * Clear all metrics
	 */
	public function clear_metrics() {
		delete_option( self::METRICS_OPTION );
	}

	/**
	 * Get performance summary
	 *
	 * @return array
	 */
	public function get_summary() {
		$metrics = $this->get_all_metrics();
		$summary = array();

		foreach ( $metrics as $operation => $data ) {
			$summary[ $operation ] = array(
				'count'       => $data['count'],
				'avg_time'    => round( $data['avg_time'], 2 ),
				'min_time'    => round( $data['min_time'], 2 ),
				'max_time'    => round( $data['max_time'], 2 ),
				'last_update' => $data['last_update'],
			);
		}

		return $summary;
	}

	/**
	 * Check if operation is slow
	 *
	 * @param string $operation Operation name
	 * @param float  $threshold Threshold in ms
	 * @return bool
	 */
	public function is_slow( $operation, $threshold = 100 ) {
		$metrics = $this->get_metrics( $operation );

		if ( ! $metrics ) {
			return false;
		}

		return $metrics['avg_time'] > $threshold;
	}

	/**
	 * Get slow operations
	 *
	 * @param float $threshold Threshold in ms
	 * @return array
	 */
	public function get_slow_operations( $threshold = 100 ) {
		$metrics = $this->get_all_metrics();
		$slow    = array();

		foreach ( $metrics as $operation => $data ) {
			if ( $data['avg_time'] > $threshold ) {
				$slow[ $operation ] = $data;
			}
		}

		// Sort by avg_time descending
		uasort(
			$slow,
			function ( $a, $b ) {
				return $b['avg_time'] <=> $a['avg_time'];
			}
		);

		return $slow;
	}
}

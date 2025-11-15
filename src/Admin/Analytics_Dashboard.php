<?php
/**
 * Analytics Dashboard
 *
 * @package VQCheckout\Admin
 */

namespace VQCheckout\Admin;

use VQCheckout\Analytics\Tracker;
use VQCheckout\Performance\Monitor;
use VQCheckout\Performance\Cache_Preheater;
use VQCheckout\Checkout\Multi_Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Analytics dashboard page
 */
class Analytics_Dashboard {
	private $tracker;
	private $monitor;
	private $preheater;
	private $multi_currency;

	public function __construct() {
		$this->tracker        = new Tracker();
		$this->monitor        = new Monitor();
		$this->preheater      = new Cache_Preheater();
		$this->multi_currency = new Multi_Currency();
	}

	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_menu() {
		add_submenu_page(
			'vqcheckout-settings',
			__( 'Analytics', 'vq-checkout' ),
			__( 'Analytics', 'vq-checkout' ),
			'manage_woocommerce',
			'vqcheckout-analytics',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( $hook ) {
		if ( $hook !== 'vq-checkout_page_vqcheckout-analytics' ) {
			return;
		}

		wp_enqueue_style( 'vqcheckout-analytics', VQCHECKOUT_URL . 'assets/css/analytics.css', array(), VQCHECKOUT_VERSION );
		wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', array(), '4.4.0', true );
	}

	public function render_page() {
		$period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : '30';

		$end_date   = date( 'Y-m-d' );
		$start_date = date( 'Y-m-d', strtotime( "-{$period} days" ) );

		$checkout_stats  = $this->tracker->get_checkout_stats( $start_date, $end_date );
		$popular_wards   = $this->tracker->get_popular_wards( 10, $start_date, $end_date );
		$cache_stats     = $this->tracker->get_cache_stats( $start_date, $end_date );
		$daily_stats     = $this->tracker->get_daily_stats( $period );
		$perf_summary    = $this->monitor->get_summary();
		$preheat_status  = $this->preheater->get_status();
		$currency_status = $this->multi_currency->get_status();
		?>
		<div class="wrap vqcheckout-analytics">
			<h1><?php esc_html_e( 'VQ Checkout Analytics', 'vq-checkout' ); ?></h1>

			<div class="vqcheckout-analytics-header">
				<form method="get">
					<input type="hidden" name="page" value="vqcheckout-analytics" />
					<select name="period" onchange="this.form.submit()">
						<option value="7" <?php selected( $period, '7' ); ?>><?php esc_html_e( 'Last 7 days', 'vq-checkout' ); ?></option>
						<option value="30" <?php selected( $period, '30' ); ?>><?php esc_html_e( 'Last 30 days', 'vq-checkout' ); ?></option>
						<option value="90" <?php selected( $period, '90' ); ?>><?php esc_html_e( 'Last 90 days', 'vq-checkout' ); ?></option>
					</select>
				</form>
			</div>

			<!-- Summary Cards -->
			<div class="vqcheckout-stats-grid">
				<div class="vqcheckout-stat-card">
					<h3><?php esc_html_e( 'Total Orders', 'vq-checkout' ); ?></h3>
					<p class="stat-value"><?php echo number_format( $checkout_stats['total_orders'] ); ?></p>
				</div>

				<div class="vqcheckout-stat-card">
					<h3><?php esc_html_e( 'Total Revenue', 'vq-checkout' ); ?></h3>
					<p class="stat-value"><?php echo wc_price( $checkout_stats['total_revenue'] ); ?></p>
				</div>

				<div class="vqcheckout-stat-card">
					<h3><?php esc_html_e( 'Avg Order Value', 'vq-checkout' ); ?></h3>
					<p class="stat-value"><?php echo wc_price( $checkout_stats['avg_order_value'] ); ?></p>
				</div>

				<div class="vqcheckout-stat-card">
					<h3><?php esc_html_e( 'Avg Shipping', 'vq-checkout' ); ?></h3>
					<p class="stat-value"><?php echo wc_price( $checkout_stats['avg_shipping'] ); ?></p>
				</div>
			</div>

			<!-- Charts Section -->
			<div class="vqcheckout-charts-section">
				<div class="vqcheckout-chart-card">
					<h2><?php esc_html_e( 'Daily Orders', 'vq-checkout' ); ?></h2>
					<canvas id="daily-orders-chart"></canvas>
				</div>

				<div class="vqcheckout-chart-card">
					<h2><?php esc_html_e( 'Cache Performance', 'vq-checkout' ); ?></h2>
					<div class="cache-stats">
						<p><?php esc_html_e( 'Hit Rate:', 'vq-checkout' ); ?> <strong><?php echo $cache_stats['hit_rate']; ?>%</strong></p>
						<p><?php esc_html_e( 'Cache Hits:', 'vq-checkout' ); ?> <strong><?php echo number_format( $cache_stats['cache_hits'] ); ?></strong></p>
						<p><?php esc_html_e( 'Cache Misses:', 'vq-checkout' ); ?> <strong><?php echo number_format( $cache_stats['cache_misses'] ); ?></strong></p>
					</div>
					<canvas id="cache-performance-chart"></canvas>
				</div>
			</div>

			<!-- Popular Wards Table -->
			<div class="vqcheckout-table-section">
				<h2><?php esc_html_e( 'Top 10 Popular Wards', 'vq-checkout' ); ?></h2>
				<table class="widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Ward Code', 'vq-checkout' ); ?></th>
							<th><?php esc_html_e( 'Orders', 'vq-checkout' ); ?></th>
							<th><?php esc_html_e( 'Revenue', 'vq-checkout' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $popular_wards as $ward ) : ?>
							<tr>
								<td><?php echo esc_html( $ward['ward_code'] ); ?></td>
								<td><?php echo number_format( $ward['order_count'] ); ?></td>
								<td><?php echo wc_price( $ward['total_revenue'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- Performance Summary -->
			<div class="vqcheckout-table-section">
				<h2><?php esc_html_e( 'Performance Summary', 'vq-checkout' ); ?></h2>
				<table class="widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Operation', 'vq-checkout' ); ?></th>
							<th><?php esc_html_e( 'Count', 'vq-checkout' ); ?></th>
							<th><?php esc_html_e( 'Avg Time (ms)', 'vq-checkout' ); ?></th>
							<th><?php esc_html_e( 'Min (ms)', 'vq-checkout' ); ?></th>
							<th><?php esc_html_e( 'Max (ms)', 'vq-checkout' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $perf_summary as $operation => $stats ) : ?>
							<tr>
								<td><?php echo esc_html( $operation ); ?></td>
								<td><?php echo number_format( $stats['count'] ); ?></td>
								<td><?php echo number_format( $stats['avg_time'], 2 ); ?></td>
								<td><?php echo number_format( $stats['min_time'], 2 ); ?></td>
								<td><?php echo number_format( $stats['max_time'], 2 ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- System Status -->
			<div class="vqcheckout-stats-grid">
				<div class="vqcheckout-stat-card">
					<h3><?php esc_html_e( 'Cache Preheat', 'vq-checkout' ); ?></h3>
					<p><?php esc_html_e( 'Last Run:', 'vq-checkout' ); ?> <strong><?php echo esc_html( $preheat_status['last_preheat'] ); ?></strong></p>
					<p><?php esc_html_e( 'Popular Wards:', 'vq-checkout' ); ?> <strong><?php echo number_format( $preheat_status['popular_wards_count'] ); ?></strong></p>
				</div>

				<div class="vqcheckout-stat-card">
					<h3><?php esc_html_e( 'Multi-Currency', 'vq-checkout' ); ?></h3>
					<p><?php esc_html_e( 'Status:', 'vq-checkout' ); ?> <strong><?php echo $currency_status['enabled'] ? __( 'Enabled', 'vq-checkout' ) : __( 'Disabled', 'vq-checkout' ); ?></strong></p>
					<p><?php esc_html_e( 'Base:', 'vq-checkout' ); ?> <strong><?php echo esc_html( $currency_status['base_currency'] ); ?></strong></p>
				</div>
			</div>

			<script>
				// Daily orders chart
				const dailyData = <?php echo wp_json_encode( $daily_stats ); ?>;
				new Chart(document.getElementById('daily-orders-chart'), {
					type: 'line',
					data: {
						labels: dailyData.map(d => d.date),
						datasets: [{
							label: '<?php esc_html_e( 'Orders', 'vq-checkout' ); ?>',
							data: dailyData.map(d => d.orders),
							borderColor: '#2271b1',
							backgroundColor: 'rgba(34, 113, 177, 0.1)',
							tension: 0.4
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false
					}
				});

				// Cache performance chart
				new Chart(document.getElementById('cache-performance-chart'), {
					type: 'doughnut',
					data: {
						labels: ['<?php esc_html_e( 'Cache Hits', 'vq-checkout' ); ?>', '<?php esc_html_e( 'Cache Misses', 'vq-checkout' ); ?>'],
						datasets: [{
							data: [<?php echo $cache_stats['cache_hits']; ?>, <?php echo $cache_stats['cache_misses']; ?>],
							backgroundColor: ['#46b450', '#dc3232']
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false
					}
				});
			</script>
		</div>
		<?php
	}
}

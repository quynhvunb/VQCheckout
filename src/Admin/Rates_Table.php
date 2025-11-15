<?php
/**
 * Rates Table (WP_List_Table)
 *
 * @package VQCheckout\Admin
 */

namespace VQCheckout\Admin;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * DataGrid for shipping rates
 */
class Rates_Table extends \WP_List_Table {
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'rate',
				'plural'   => 'rates',
				'ajax'     => true,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'title'      => __( 'Tiêu đề', 'vq-checkout' ),
			'zone'       => __( 'Zone', 'vq-checkout' ),
			'cost'       => __( 'Phí vận chuyển', 'vq-checkout' ),
			'priority'   => __( 'Ưu tiên', 'vq-checkout' ),
			'wards'      => __( 'Số xã/phường', 'vq-checkout' ),
			'conditions' => __( 'Điều kiện', 'vq-checkout' ),
			'actions'    => __( 'Thao tác', 'vq-checkout' ),
		);
	}

	public function get_sortable_columns() {
		return array(
			'title'    => array( 'title', false ),
			'cost'     => array( 'cost', false ),
			'priority' => array( 'priority', true ),
		);
	}

	public function get_bulk_actions() {
		return array(
			'delete'  => __( 'Xóa', 'vq-checkout' ),
			'block'   => __( 'Chặn giao hàng', 'vq-checkout' ),
			'unblock' => __( 'Bỏ chặn', 'vq-checkout' ),
		);
	}

	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="rate[]" value="%d" />', $item['id'] );
	}

	public function column_title( $item ) {
		return '<strong>' . esc_html( $item['title'] ) . '</strong>';
	}

	public function column_zone( $item ) {
		$zone = \WC_Shipping_Zones::get_zone( $item['zone_id'] );
		return $zone ? esc_html( $zone->get_zone_name() ) : '—';
	}

	public function column_cost( $item ) {
		return wc_price( $item['cost'] );
	}

	public function column_priority( $item ) {
		return sprintf(
			'<span class="vqcheckout-priority" data-rate-id="%d">%d</span>',
			$item['id'],
			$item['priority']
		);
	}

	public function column_wards( $item ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_rate_locations';
		$count = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE rate_id = %d", $item['id'] )
		);
		return sprintf( '<span class="badge">%d</span>', $count );
	}

	public function column_conditions( $item ) {
		if ( empty( $item['conditions'] ) ) {
			return '—';
		}

		$conditions = json_decode( $item['conditions'], true );
		$parts      = array();

		if ( isset( $conditions['min'] ) ) {
			$parts[] = sprintf( 'Min: %s', wc_price( $conditions['min'] ) );
		}

		if ( isset( $conditions['max'] ) ) {
			$parts[] = sprintf( 'Max: %s', wc_price( $conditions['max'] ) );
		}

		if ( isset( $conditions['free_shipping_min'] ) ) {
			$parts[] = sprintf( 'Free ≥ %s', wc_price( $conditions['free_shipping_min'] ) );
		}

		return implode( '<br>', $parts );
	}

	public function column_actions( $item ) {
		$edit_url   = add_query_arg(
			array(
				'action'  => 'edit',
				'rate_id' => $item['id'],
			)
		);
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'delete',
					'rate_id' => $item['id'],
				)
			),
			'delete_rate_' . $item['id']
		);

		return sprintf(
			'<button type="button" class="button button-small vqcheckout-edit-rate" data-rate-id="%d">%s</button>
			<button type="button" class="button button-small button-link-delete vqcheckout-delete-rate" data-rate-id="%d">%s</button>',
			$item['id'],
			__( 'Sửa', 'vq-checkout' ),
			$item['id'],
			__( 'Xóa', 'vq-checkout' )
		);
	}

	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'priority';
		$order   = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'ASC';

		global $wpdb;
		$table = $wpdb->prefix . 'vqcheckout_ward_rates';

		$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$this->items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	public function display() {
		?>
		<div class="vqcheckout-rates-header">
			<button type="button" class="button button-primary vqcheckout-add-rate">
				<?php esc_html_e( '+ Thêm Rate', 'vq-checkout' ); ?>
			</button>
			<button type="button" class="button vqcheckout-import-rates">
				<?php esc_html_e( 'Import JSON', 'vq-checkout' ); ?>
			</button>
			<button type="button" class="button vqcheckout-export-rates">
				<?php esc_html_e( 'Export JSON', 'vq-checkout' ); ?>
			</button>
		</div>
		<?php
		parent::display();
	}
}

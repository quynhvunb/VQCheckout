<?php
/**
 * Admin Assets Manager
 *
 * @package VQCheckout\Admin
 */

namespace VQCheckout\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Manage admin scripts and styles
 */
class Assets {
	public function init() {
		add_action( 'admin_footer', array( $this, 'render_modals' ) );
	}

	public function render_modals() {
		$screen = get_current_screen();

		if ( ! $screen || strpos( $screen->id, 'vqcheckout' ) === false ) {
			return;
		}

		Rate_Editor::render_modal();
	}
}

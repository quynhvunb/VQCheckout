<?php
/**
 * Debug script - Upload to WordPress root and access via browser
 * URL: http://yoursite.com/debug-check.php
 */

// Load WordPress
require_once __DIR__ . '/../../wp-load.php';

if ( ! current_user_can( 'manage_options' ) ) {
	die( 'Access denied' );
}

echo '<h1>VQ Checkout Debug Report</h1>';

// 1. Check if WooCommerce is active
echo '<h2>1. WooCommerce Status</h2>';
if ( class_exists( 'WooCommerce' ) ) {
	echo '✓ WooCommerce is active<br>';
	echo '  Version: ' . WC_VERSION . '<br>';
} else {
	echo '✗ WooCommerce is NOT active<br>';
}

// 2. Check if plugin is active
echo '<h2>2. VQ Checkout Plugin Status</h2>';
if ( defined( 'VQCHECKOUT_VERSION' ) ) {
	echo '✓ VQ Checkout is active<br>';
	echo '  Version: ' . VQCHECKOUT_VERSION . '<br>';
	echo '  Path: ' . VQCHECKOUT_PATH . '<br>';
} else {
	echo '✗ VQ Checkout is NOT active<br>';
}

// 3. Check autoloader
echo '<h2>3. Autoloader Status</h2>';
$autoload_file = VQCHECKOUT_PATH . 'vendor/autoload.php';
if ( file_exists( $autoload_file ) ) {
	echo '✓ Autoloader file exists<br>';
} else {
	echo '✗ Autoloader file NOT found - Run: composer install<br>';
}

// 4. Check if main classes exist
echo '<h2>4. Core Classes</h2>';
$classes_to_check = array(
	'VQCheckout\\Core\\Plugin',
	'VQCheckout\\Core\\Hooks',
	'VQCheckout\\Admin\\Settings_Page',
	'VQCheckout\\Admin\\Assets',
	'VQCheckout\\Checkout\\Fields',
);

foreach ( $classes_to_check as $class ) {
	if ( class_exists( $class ) ) {
		echo "✓ $class exists<br>";
	} else {
		echo "✗ $class NOT found<br>";
	}
}

// 5. Check compatibility declarations
echo '<h2>5. WooCommerce Compatibility</h2>';
if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
	echo '✓ FeaturesUtil class exists<br>';

	// Check HPOS
	$hpos_compatible = \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
		'custom_order_tables',
		VQCHECKOUT_FILE,
		true
	);
	echo '  HPOS declared: Yes<br>';

	// Check Blocks
	$blocks_compatible = \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
		'cart_checkout_blocks',
		VQCHECKOUT_FILE,
		true
	);
	echo '  Blocks declared: Yes<br>';
} else {
	echo '✗ FeaturesUtil class NOT found<br>';
}

// 6. Check for PHP errors
echo '<h2>6. PHP Error Log (last 20 lines)</h2>';
if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
	$log_file = WP_CONTENT_DIR . '/debug.log';
	if ( file_exists( $log_file ) ) {
		$lines = file( $log_file );
		$last_lines = array_slice( $lines, -20 );
		echo '<pre style="background:#f5f5f5;padding:10px;overflow:auto;max-height:300px;">';
		foreach ( $last_lines as $line ) {
			if ( stripos( $line, 'vqcheckout' ) !== false || stripos( $line, 'vq-checkout' ) !== false ) {
				echo '<strong style="color:red;">' . esc_html( $line ) . '</strong>';
			} else {
				echo esc_html( $line );
			}
		}
		echo '</pre>';
	} else {
		echo 'Debug log file not found. Enable WP_DEBUG_LOG in wp-config.php<br>';
	}
} else {
	echo 'WP_DEBUG_LOG is not enabled. Add to wp-config.php:<br>';
	echo '<code>define("WP_DEBUG", true);<br>define("WP_DEBUG_LOG", true);</code><br>';
}

// 7. Check database tables
echo '<h2>7. Database Tables</h2>';
global $wpdb;
$tables = array(
	$wpdb->prefix . 'vqcheckout_ward_rates',
	$wpdb->prefix . 'vqcheckout_rate_locations',
	$wpdb->prefix . 'vqcheckout_security_log',
	$wpdb->prefix . 'vqcheckout_analytics',
);

foreach ( $tables as $table ) {
	$exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" );
	if ( $exists ) {
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM `$table`" );
		echo "✓ $table exists ($count rows)<br>";
	} else {
		echo "✗ $table NOT found<br>";
	}
}

// 8. Check menu registration
echo '<h2>8. Admin Menu</h2>';
global $menu, $submenu;
$found = false;
foreach ( $menu as $item ) {
	if ( isset( $item[2] ) && strpos( $item[2], 'vqcheckout' ) !== false ) {
		echo '✓ Main menu found: ' . esc_html( $item[0] ) . '<br>';
		$found = true;

		// Check submenus
		if ( isset( $submenu[ $item[2] ] ) ) {
			foreach ( $submenu[ $item[2] ] as $sub ) {
				echo '  └─ ' . esc_html( $sub[0] ) . '<br>';
			}
		}
	}
}

if ( ! $found ) {
	echo '✗ No menu items found<br>';
	echo 'Debug: Total menu items = ' . count( $menu ) . '<br>';
}

echo '<h2>9. Plugin Hooks</h2>';
echo 'Checking if hooks are registered...<br>';
$hooks = array(
	'init' => 'Should have 4 callbacks from VQ Checkout',
	'rest_api_init' => 'Should register REST routes',
	'admin_menu' => 'Should register admin menu',
	'woocommerce_shipping_methods' => 'Should register shipping method',
);

foreach ( $hooks as $hook => $desc ) {
	global $wp_filter;
	if ( isset( $wp_filter[ $hook ] ) ) {
		$count = 0;
		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) && is_object( $callback['function'][0] ) ) {
					$class = get_class( $callback['function'][0] );
					if ( strpos( $class, 'VQCheckout' ) !== false ) {
						echo "✓ $hook: " . $class . '::' . $callback['function'][1] . '<br>';
						$count++;
					}
				}
			}
		}
		if ( $count === 0 ) {
			echo "⚠ $hook: No VQ Checkout callbacks found<br>";
		}
	} else {
		echo "✗ $hook: Hook not registered<br>";
	}
}

echo '<hr>';
echo '<p><strong>Delete this file after debugging!</strong></p>';

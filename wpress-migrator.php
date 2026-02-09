<?php
/**
 * Plugin Name: WPRESS Migrator
 * Description: Loads bundled All-in-One WP Migration plugins from the includes folder.
 * Version: 1.0.0
 * Author: Local
 * Text Domain: wpress-migrator
 * Network: True
 * License: Proprietary
 * License URI: LICENSE.txt
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPRESS_MIGRATOR_PATH', dirname( __FILE__ ) );
define( 'WPRESS_MIGRATOR_FILE', __FILE__ );
define( 'WPRESS_MIGRATOR_VERSION', '1.0.0' );
define( 'WPRESS_MIGRATOR_INCLUDES', WPRESS_MIGRATOR_PATH . DIRECTORY_SEPARATOR . 'includes' );

require_once WPRESS_MIGRATOR_PATH . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'class-wpress-migrator-settings.php';
require_once WPRESS_MIGRATOR_PATH . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'class-wpress-migrator-updater.php';

WPRESS_Migrator_Settings::init();
WPRESS_Migrator_Updater::init();

register_activation_hook( __FILE__, array( 'WPRESS_Migrator_Settings', 'ensure_default_license' ) );

function wpress_migrator_load_bundled_plugins() {
	if ( ! WPRESS_Migrator_Settings::is_license_valid() ) {
		add_action(
			'admin_notices',
			function() {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}

				echo '<div class="notice notice-warning"><p>Serial/license key is required to enable WPRESS Migrator.</p></div>';
			}
		);
		return;
	}

	$core_plugin = WPRESS_MIGRATOR_INCLUDES . DIRECTORY_SEPARATOR . 'wpm' . DIRECTORY_SEPARATOR . 'all-in-one-wp-migration.php';
	$gdrive_plugin = WPRESS_MIGRATOR_INCLUDES . DIRECTORY_SEPARATOR . 'wpmgd' . DIRECTORY_SEPARATOR . 'all-in-one-wp-migration-gdrive-extension.php';

	$missing = array();
	if ( ! file_exists( $core_plugin ) ) {
		$missing[] = 'All-in-One WP Migration';
	}
	if ( ! file_exists( $gdrive_plugin ) ) {
		$missing[] = 'Google Drive Extension';
	}

	if ( ! empty( $missing ) ) {
		add_action(
			'admin_notices',
			function() use ( $missing ) {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}

				$safe_names = array_map( 'esc_html', $missing );
				$list = implode( ', ', $safe_names );
				echo '<div class="notice notice-error"><p>WPRESS Migrator: missing bundled plugin(s): ' . $list . '.</p></div>';
			}
		);
		return;
	}

	if ( ! defined( 'AI1WM_PATH' ) ) {
		require_once $core_plugin;
	}

	if ( ! defined( 'AI1WMGE_PATH' ) ) {
		require_once $gdrive_plugin;
	}
}
add_action( 'plugins_loaded', 'wpress_migrator_load_bundled_plugins', 1 );

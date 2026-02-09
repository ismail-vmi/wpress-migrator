<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPRESS_Migrator_Settings {
	const OPTION_GROUP = 'wpress_migrator_settings';
	const OPTION_NAME = 'wpress_migrator_options';
	const DEFAULT_LICENSE_OPTION = 'wpress_migrator_default_license';
	const LICENSE_OPTION = 'wpress_migrator_license_key';
	const LICENSE_MASK = '************';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_wpress_migrator_check_updates', array( __CLASS__, 'handle_check_updates' ) );
		add_action( 'admin_post_wpress_migrator_test_connection', array( __CLASS__, 'handle_test_connection' ) );
		self::ensure_default_license();
	}

	public static function get_options() {
		$defaults = array(
			'debug_logging' => '0',
		);

		$options = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		return array_merge( $defaults, $options );
	}

	public static function add_menu() {
		add_options_page(
			'WPRESS Migrator',
			'WPRESS Migrator',
			'manage_options',
			'wpress-migrator',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		register_setting( self::OPTION_GROUP, self::OPTION_NAME, array( __CLASS__, 'sanitize' ) );

		add_settings_section(
			'wpress_migrator_updates',
			'Update Settings',
			'__return_false',
			'wpress-migrator'
		);

		self::add_field( 'license_key', 'License Key', 'Enter your license key to enable the plugin.' );
		self::add_field( 'debug_logging', 'Debug Logging', 'Log update checks to the PHP error log.' );
	}

	private static function add_field( $key, $label, $help ) {
		add_settings_field(
			$key,
			$label,
			array( __CLASS__, 'render_field' ),
			'wpress-migrator',
			'wpress_migrator_updates',
			array(
				'key' => $key,
				'help' => $help,
			)
		);
	}

	public static function render_field( $args ) {
		$options = self::get_options();
		$key = $args['key'];
		$value = isset( $options[ $key ] ) ? $options[ $key ] : '';

		if ( $key === 'license_key' ) {
			$value = self::is_license_valid() ? self::LICENSE_MASK : '';
		}

		if ( $key === 'debug_logging' ) {
			$checked = ! empty( $value ) ? 'checked' : '';
			echo '<label><input type="checkbox" name="' . esc_attr( self::OPTION_NAME ) . '[' . esc_attr( $key ) . ']" value="1" ' . $checked . ' /> Enable debug logging</label>';

			if ( ! empty( $args['help'] ) ) {
				printf( '<p class="description">%s</p>', esc_html( $args['help'] ) );
			}
			return;
		}


		$input_type = 'text';
		$extra = '';

		printf(
			'<input class="regular-text" type="%s" name="%s[%s]" value="%s"%s />',
			esc_attr( $input_type ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $key ),
			esc_attr( $value ),
			$extra
		);

		if ( $key === 'license_key' ) {
			self::render_license_status();
		}

		if ( ! empty( $args['help'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['help'] ) );
		}
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>WPRESS Migrator</h1>
			<?php self::render_update_notice(); ?>
			<?php self::render_license_notice(); ?>
			<?php self::render_version_status(); ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( 'wpress-migrator' );
				submit_button();
				?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wpress_migrator_check_updates' ); ?>
				<input type="hidden" name="action" value="wpress_migrator_check_updates" />
				<?php submit_button( 'Check for updates now', 'secondary' ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wpress_migrator_test_connection' ); ?>
				<input type="hidden" name="action" value="wpress_migrator_test_connection" />
				<?php submit_button( 'Test GitHub connection', 'secondary' ); ?>
			</form>
			<p class="description">Auto-updates use GitHub releases for public repositories.</p>
		</div>
		<?php
	}

	public static function sanitize( $input ) {
		$sanitized = array();
		$fields = array( 'debug_logging' );
		$license_input = isset( $input['license_key'] ) ? trim( $input['license_key'] ) : '';

		if ( $license_input !== '' && $license_input !== self::LICENSE_MASK ) {
			if ( self::matches_default_license( $license_input ) ) {
				update_option( self::LICENSE_OPTION, base64_encode( $license_input ), false );
				set_transient(
					'wpress_migrator_update_notice',
					array(
						'type' => 'notice-success',
						'message' => 'License key accepted.',
					),
					30
				);
			} else {
				delete_option( self::LICENSE_OPTION );
				set_transient(
					'wpress_migrator_update_notice',
					array(
						'type' => 'notice-error',
						'message' => 'License key is invalid. Please enter a valid key.',
					),
					30
				);
			}
		}
		foreach ( $fields as $field ) {
			if ( $field === 'debug_logging' ) {
				$sanitized[ $field ] = empty( $input[ $field ] ) ? '0' : '1';
				continue;
			}
			$sanitized[ $field ] = isset( $input[ $field ] ) ? sanitize_text_field( $input[ $field ] ) : '';
		}

		return $sanitized;
	}

	public static function handle_check_updates() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized', 403 );
		}

		check_admin_referer( 'wpress_migrator_check_updates' );

		delete_site_transient( 'update_plugins' );
		wp_update_plugins();

		set_transient(
			'wpress_migrator_update_notice',
			array(
				'type' => 'updated',
				'message' => 'Update check completed. Visit the Plugins page to see available updates.',
			),
			30
		);

		wp_safe_redirect( admin_url( 'options-general.php?page=wpress-migrator' ) );
		exit;
	}

	public static function handle_test_connection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized', 403 );
		}

		check_admin_referer( 'wpress_migrator_test_connection' );

		$options = self::get_options();
		$result = WPRESS_Migrator_Updater::test_github_connection( $options );

		$type = $result['ok'] ? 'notice-success' : 'notice-error';
		$message = $result['message'];
		set_transient(
			'wpress_migrator_update_notice',
			array(
				'type' => $type,
				'message' => $message,
			),
			30
		);

		wp_safe_redirect( admin_url( 'options-general.php?page=wpress-migrator' ) );
		exit;
	}

	private static function render_update_notice() {
		$notice = get_transient( 'wpress_migrator_update_notice' );
		if ( empty( $notice ) || ! is_array( $notice ) ) {
			return;
		}

		delete_transient( 'wpress_migrator_update_notice' );

		$type = ! empty( $notice['type'] ) ? $notice['type'] : 'updated';
		$message = ! empty( $notice['message'] ) ? $notice['message'] : '';
		if ( empty( $message ) ) {
			return;
		}

		printf(
			'<div class="notice %s"><p>%s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	private static function render_version_status() {
		$latest = WPRESS_Migrator_Updater::get_latest_version_info();
		$current = WPRESS_MIGRATOR_VERSION;
		$latest_version = isset( $latest['version'] ) ? $latest['version'] : '';
		$latest_url = isset( $latest['url'] ) ? $latest['url'] : '';

		if ( empty( $latest_version ) ) {
			echo '<p><strong>Latest version:</strong> Unknown (release not found).</p>';
			return;
		}

		$status = version_compare( $latest_version, $current, '>' ) ? 'Update available' : 'Up to date';
		$link = '';
		if ( ! empty( $latest_url ) ) {
			$link = sprintf( ' (<a href="%s" target="_blank" rel="noopener noreferrer">view release</a>)', esc_url( $latest_url ) );
		}

		printf(
			'<p><strong>Current version:</strong> %s</p><p><strong>Latest version:</strong> %s - %s%s</p>',
			esc_html( $current ),
			esc_html( $latest_version ),
			esc_html( $status ),
			$link
		);
	}


	private static function render_license_status() {
		if ( self::is_license_valid() ) {
			echo '<div style="margin-top:6px; color:#2e7d32; font-weight:600;">License key is valid</div>';
			return;
		}

		echo '<div style="margin-top:6px; color:#a00; font-weight:600;">Serial/license key is required</div>';
	}

	private static function render_license_notice() {
		if ( self::is_license_valid() ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>Serial/license key is required to enable WPRESS Migrator.</p></div>';
	}

	private static function matches_default_license( $license ) {
		$default = self::get_default_license();
		if ( $default === '' ) {
			return false;
		}

		return hash_equals( $default, $license );
	}

	public static function is_license_valid() {
		$stored = get_option( self::LICENSE_OPTION, '' );
		if ( $stored === '' ) {
			return false;
		}

		$stored_plain = base64_decode( $stored, true );
		if ( $stored_plain === false ) {
			return false;
		}

		$default = self::get_default_license();
		if ( $default === '' ) {
			return false;
		}

		return hash_equals( $default, $stored_plain );
	}

	public static function ensure_default_license() {
		if ( get_option( self::DEFAULT_LICENSE_OPTION, '' ) === '' ) {
			add_option( self::DEFAULT_LICENSE_OPTION, base64_encode( 'ismailamin03061600937' ), '', false );
		}
	}

	private static function get_default_license() {
		$encoded = get_option( self::DEFAULT_LICENSE_OPTION, '' );
		if ( $encoded === '' ) {
			return '';
		}

		$decoded = base64_decode( $encoded, true );
		return $decoded === false ? '' : $decoded;
	}
}

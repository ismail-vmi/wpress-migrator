<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPRESS_Migrator_Settings {
	const OPTION_GROUP = 'wpress_migrator_settings';
	const OPTION_NAME = 'wpress_migrator_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_wpress_migrator_check_updates', array( __CLASS__, 'handle_check_updates' ) );
		add_action( 'admin_post_wpress_migrator_remove_token', array( __CLASS__, 'handle_remove_token' ) );
		add_action( 'admin_post_wpress_migrator_test_connection', array( __CLASS__, 'handle_test_connection' ) );
	}

	public static function get_options() {
		$defaults = array(
			'access_key' => '',
			'github_owner' => 'ismail-vmi',
			'github_repo' => 'wpress-migrator',
			'github_token' => '',
			'github_asset' => 'wpress-migrator.zip',
			'release_checksum' => '',
			'update_channel' => 'stable',
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

		self::add_field( 'access_key', 'Access Key', 'Used for your update endpoint or GitHub API token.' );
		self::add_field( 'github_owner', 'GitHub Owner', 'Example: your-user or your-org.' );
		self::add_field( 'github_repo', 'GitHub Repo', 'Example: wpress-migrator.' );
		self::add_field( 'github_token', 'GitHub Token', 'Optional. Needed for private repos or higher rate limits.' );
		self::add_field( 'github_asset', 'Release Asset Name', 'Optional. Defaults to wpress-migrator.zip.' );
		self::add_field( 'release_checksum', 'Release SHA-256', 'Optional. If set, updates verify the ZIP checksum.' );
		self::add_field( 'update_channel', 'Update Channel', 'Choose stable or beta (pre-releases).' );
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

		if ( $key === 'access_key' && self::is_token_set() ) {
			echo '<div class="description">Access Key is disabled because a GitHub token is set in wp-config.php.</div>';
			return;
		}

		if ( $key === 'github_token' ) {
			$value = '';
		}

		if ( $key === 'debug_logging' ) {
			$checked = ! empty( $value ) ? 'checked' : '';
			echo '<label><input type="checkbox" name="' . esc_attr( self::OPTION_NAME ) . '[' . esc_attr( $key ) . ']" value="1" ' . $checked . ' /> Enable debug logging</label>';

			if ( ! empty( $args['help'] ) ) {
				printf( '<p class="description">%s</p>', esc_html( $args['help'] ) );
			}
			return;
		}

		if ( $key === 'update_channel' ) {
			$current = $value !== '' ? $value : 'stable';
			echo '<select name="' . esc_attr( self::OPTION_NAME ) . '[' . esc_attr( $key ) . ']">';
			echo '<option value="stable"' . selected( $current, 'stable', false ) . '>Stable</option>';
			echo '<option value="beta"' . selected( $current, 'beta', false ) . '>Beta (pre-release)</option>';
			echo '</select>';

			if ( ! empty( $args['help'] ) ) {
				printf( '<p class="description">%s</p>', esc_html( $args['help'] ) );
			}
			return;
		}

		$input_type = 'text';
		$extra = '';
		if ( $key === 'github_token' ) {
			$input_type = 'password';
			$extra = ' autocomplete="new-password"';
		}

		printf(
			'<input class="regular-text" type="%s" name="%s[%s]" value="%s"%s />',
			esc_attr( $input_type ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $key ),
			esc_attr( $value ),
			$extra
		);

		if ( $key === 'github_token' ) {
			self::render_token_status();
			echo '<button type="button" class="button" style="margin-left:8px;" onclick="var i=this.previousElementSibling; if(i){ i.type = (i.type === \'password\' ? \'text\' : \'password\'); this.textContent = (i.type === \'password\' ? \'Show\' : \'Hide\'); }">Show</button>';
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
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wpress_migrator_remove_token' ); ?>
				<input type="hidden" name="action" value="wpress_migrator_remove_token" />
				<?php submit_button( 'Remove token', 'delete' ); ?>
			</form>
			<p class="description">For private repositories, auto-updates typically require a proxy that can serve a signed download URL.</p>
		</div>
		<?php
	}

	public static function sanitize( $input ) {
		$sanitized = array();
		$fields = array( 'access_key', 'github_owner', 'github_repo', 'github_token', 'github_asset', 'release_checksum', 'update_channel', 'debug_logging' );
		$existing = self::get_options();
		$token = isset( $input['github_token'] ) ? trim( $input['github_token'] ) : '';

		if ( $token !== '' ) {
			$stored = self::store_token_in_wp_config( $token );
			if ( $stored ) {
				set_transient(
					'wpress_migrator_update_notice',
					array(
						'type' => 'notice-success',
						'message' => 'Token is set in wp-config.php.',
					),
					30
				);
			} else {
				set_transient(
					'wpress_migrator_update_notice',
					array(
						'type' => 'notice-error',
						'message' => 'Token could not be saved to wp-config.php. Check file permissions.',
					),
					30
				);
			}
		}
		foreach ( $fields as $field ) {
			if ( $field === 'github_token' ) {
				$sanitized[ $field ] = ( $token === '' ) ? $existing['github_token'] : '';
				continue;
			}
			if ( $field === 'debug_logging' ) {
				$sanitized[ $field ] = empty( $input[ $field ] ) ? '0' : '1';
				continue;
			}
			$sanitized[ $field ] = isset( $input[ $field ] ) ? sanitize_text_field( $input[ $field ] ) : '';
			if ( $field === 'update_channel' && ! in_array( $sanitized[ $field ], array( 'stable', 'beta' ), true ) ) {
				$sanitized[ $field ] = 'stable';
			}
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

	public static function handle_remove_token() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized', 403 );
		}

		check_admin_referer( 'wpress_migrator_remove_token' );

		$removed = self::remove_token_from_wp_config();
		if ( $removed ) {
			set_transient(
				'wpress_migrator_update_notice',
				array(
					'type' => 'notice-success',
					'message' => 'Token removed from wp-config.php.',
					),
				30
			);
		} else {
			set_transient(
				'wpress_migrator_update_notice',
				array(
					'type' => 'notice-error',
					'message' => 'Token could not be removed from wp-config.php. Check file permissions.',
					),
				30
			);
		}

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
			echo '<p><strong>Latest version:</strong> Unknown (set GitHub owner/repo and token to check).</p>';
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

	private static function render_token_status() {
		if ( defined( 'WPRESS_MIGRATOR_GITHUB_TOKEN' ) && WPRESS_MIGRATOR_GITHUB_TOKEN !== '' ) {
			echo '<div style="margin-top:6px; color:#2e7d32; font-weight:600;">Token is set</div>';
			return;
		}

		echo '<div style="margin-top:6px; color:#a00; font-weight:600;">Token is not set</div>';
	}

	private static function is_token_set() {
		return defined( 'WPRESS_MIGRATOR_GITHUB_TOKEN' ) && WPRESS_MIGRATOR_GITHUB_TOKEN !== '';
	}

	private static function store_token_in_wp_config( $token ) {
		$config = self::locate_wp_config();
		if ( empty( $config ) || ! file_exists( $config ) || ! is_writable( $config ) ) {
			return false;
		}

		$contents = file_get_contents( $config );
		if ( $contents === false ) {
			return false;
		}

		$define = "define( 'WPRESS_MIGRATOR_GITHUB_TOKEN', '" . addslashes( $token ) . "' );";

		if ( preg_match( "/define\\s*\\(\\s*'WPRESS_MIGRATOR_GITHUB_TOKEN'\\s*,\\s*'.*?'\\s*\\)\\s*;?/", $contents ) ) {
			$contents = preg_replace(
				"/define\\s*\\(\\s*'WPRESS_MIGRATOR_GITHUB_TOKEN'\\s*,\\s*'.*?'\\s*\\)\\s*;?/",
				$define,
				$contents
			);
		} else {
			$marker = "/* That's all, stop editing! Happy publishing. */";
			if ( strpos( $contents, $marker ) !== false ) {
				$contents = str_replace( $marker, $define . "\n\n" . $marker, $contents );
			} else {
				$contents .= "\n\n" . $define . "\n";
			}
		}

		return file_put_contents( $config, $contents ) !== false;
	}

	private static function remove_token_from_wp_config() {
		$config = self::locate_wp_config();
		if ( empty( $config ) || ! file_exists( $config ) || ! is_writable( $config ) ) {
			return false;
		}

		$contents = file_get_contents( $config );
		if ( $contents === false ) {
			return false;
		}

		$pattern = "/^\s*define\s*\(\s*'WPRESS_MIGRATOR_GITHUB_TOKEN'\s*,\s*'.*?'\s*\)\s*;\s*\n?/m";
		if ( ! preg_match( $pattern, $contents ) ) {
			return false;
		}

		$contents = preg_replace( $pattern, '', $contents );

		return file_put_contents( $config, $contents ) !== false;
	}

	private static function locate_wp_config() {
		$path = ABSPATH . 'wp-config.php';
		if ( file_exists( $path ) ) {
			return $path;
		}

		$path = dirname( ABSPATH ) . '/wp-config.php';
		if ( file_exists( $path ) ) {
			return $path;
		}

		return '';
	}
}

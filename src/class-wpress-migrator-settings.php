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
	}

	public static function get_options() {
		$defaults = array(
			'access_key' => '',
			'github_owner' => '',
			'github_repo' => '',
			'github_token' => '',
			'github_asset' => 'wpress-migrator.zip',
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

		printf(
			'<input class="regular-text" type="text" name="%s[%s]" value="%s" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $key ),
			esc_attr( $value )
		);

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
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( 'wpress-migrator' );
				submit_button();
				?>
			</form>
			<p class="description">For private repositories, auto-updates typically require a proxy that can serve a signed download URL.</p>
		</div>
		<?php
	}

	public static function sanitize( $input ) {
		$sanitized = array();
		$fields = array( 'access_key', 'github_owner', 'github_repo', 'github_token', 'github_asset' );
		foreach ( $fields as $field ) {
			$sanitized[ $field ] = isset( $input[ $field ] ) ? sanitize_text_field( $input[ $field ] ) : '';
		}

		return $sanitized;
	}
}

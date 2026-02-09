<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPRESS_Migrator_Updater {
	const RELEASE_TRANSIENT = 'wpress_migrator_latest_release';
	const GITHUB_OWNER = 'ismail-vmi';
	const GITHUB_REPO = 'wpress-migrator';
	const GITHUB_ASSET = 'wpress-migrator.zip';

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_updates' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_information' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'verify_package_checksum' ), 10, 3 );
	}

	public static function get_latest_version_info() {
		$cached = get_transient( self::RELEASE_TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$owner = self::GITHUB_OWNER;
		$repo = self::GITHUB_REPO;

		if ( empty( $owner ) || empty( $repo ) ) {
			return array();
		}

		$release = self::get_release_for_channel( $owner, $repo, 'stable' );
		if ( empty( $release['tag_name'] ) ) {
			return array();
		}

		$info = array(
			'version' => self::normalize_version( $release['tag_name'] ),
			'url' => isset( $release['html_url'] ) ? $release['html_url'] : '',
		);

		set_transient( self::RELEASE_TRANSIENT, $info, 5 * MINUTE_IN_SECONDS );

		return $info;
	}

	public static function test_github_connection( $options ) {
		$owner = self::GITHUB_OWNER;
		$repo = self::GITHUB_REPO;
		$asset = self::GITHUB_ASSET;
		if ( empty( $owner ) || empty( $repo ) ) {
			return array(
				'ok' => false,
				'message' => 'GitHub owner and repo are required to test the connection.',
			);
		}

		$release = self::get_release_for_channel( $owner, $repo, 'stable' );
		if ( empty( $release['tag_name'] ) ) {
			return array(
				'ok' => false,
				'message' => 'Could not fetch latest release. Check owner/repo or repo visibility.',
			);
		}

		$version = self::normalize_version( $release['tag_name'] );
		$asset_found = false;
		if ( ! empty( $asset ) && ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $item ) {
				if ( isset( $item['name'] ) && $item['name'] === $asset ) {
					$asset_found = true;
					break;
				}
			}
		}

		if ( ! empty( $asset ) && ! $asset_found ) {
			return array(
				'ok' => false,
				'message' => 'Latest release found (v' . $version . '), but asset "' . $asset . '" is missing.',
			);
		}

		return array(
			'ok' => true,
			'message' => 'Connection OK. Latest release: v' . $version . '.',
		);
	}

	public static function check_for_updates( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$owner = self::GITHUB_OWNER;
		$repo = self::GITHUB_REPO;
		self::log( 'Checking updates.' );

		if ( empty( $owner ) || empty( $repo ) ) {
			self::log( 'Owner or repo missing.' );
			return $transient;
		}

		$release = self::get_release_for_channel( $owner, $repo, 'stable' );
		if ( empty( $release['tag_name'] ) ) {
			self::log( 'No release tag found.' );
			return $transient;
		}

		$current_version = WPRESS_MIGRATOR_VERSION;
		$latest_version = self::normalize_version( $release['tag_name'] );
		if ( version_compare( $latest_version, $current_version, '<=' ) ) {
			self::log( 'No update available.' );
			return $transient;
		}

		$download_url = self::get_download_url( $release, self::GITHUB_ASSET );
		if ( empty( $download_url ) ) {
			self::log( 'Download URL missing.' );
			return $transient;
		}

		$plugin_file = plugin_basename( WPRESS_MIGRATOR_FILE );
		$transient->response[ $plugin_file ] = (object) array(
			'slug' => 'wpress-migrator',
			'plugin' => $plugin_file,
			'new_version' => $latest_version,
			'package' => $download_url,
			'url' => isset( $release['html_url'] ) ? $release['html_url'] : '',
		);

		return $transient;
	}

	public static function plugin_information( $result, $action, $args ) {
		if ( $action !== 'plugin_information' ) {
			return $result;
		}

		if ( empty( $args->slug ) || $args->slug !== 'wpress-migrator' ) {
			return $result;
		}

		$info = new stdClass();
		$info->name = 'WPRESS Migrator';
		$info->slug = 'wpress-migrator';
		$info->version = WPRESS_MIGRATOR_VERSION;
		$info->author = 'Local';
		$info->homepage = '';

		$info->homepage = sprintf( 'https://github.com/%s/%s', self::GITHUB_OWNER, self::GITHUB_REPO );

		$info->sections = array(
			'description' => 'Wrapper plugin that loads bundled migration plugins.',
		);

		return $info;
	}

	private static function get_latest_release( $owner, $repo ) {
		$url = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', rawurlencode( $owner ), rawurlencode( $repo ) );
		$args = array(
			'headers' => array(
				'Accept' => 'application/vnd.github+json',
				'User-Agent' => 'WPRESS-Migrator',
			),
			'timeout' => 15,
		);

		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return array();
		}

		$data = json_decode( $body, true );
		return is_array( $data ) ? $data : array();
	}

	private static function get_release_for_channel( $owner, $repo, $channel ) {
		if ( $channel !== 'beta' ) {
			return self::get_latest_release( $owner, $repo );
		}

		$url = sprintf( 'https://api.github.com/repos/%s/%s/releases?per_page=10', rawurlencode( $owner ), rawurlencode( $repo ) );
		$args = array(
			'headers' => array(
				'Accept' => 'application/vnd.github+json',
				'User-Agent' => 'WPRESS-Migrator',
			),
			'timeout' => 15,
		);


		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return array();
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return array();
		}

		foreach ( $data as $release ) {
			if ( ! empty( $release['prerelease'] ) ) {
				return $release;
			}
		}

		return self::get_latest_release( $owner, $repo );
	}

	private static function get_download_url( $release, $asset_name ) {
		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( isset( $asset['name'], $asset['browser_download_url'] ) && $asset['name'] === $asset_name ) {
					return $asset['browser_download_url'];
				}
			}
		}

		if ( ! empty( $release['zipball_url'] ) ) {
			return $release['zipball_url'];
		}

		return '';
	}

	private static function normalize_version( $version ) {
		return ltrim( $version, "vV" );
	}

	public static function verify_package_checksum( $reply, $package, $upgrader ) {
		if ( ! self::should_verify_package( $package ) ) {
			return $reply;
		}

		$checksum = '';
		if ( $checksum === '' ) {
			return $reply;
		}

		self::log( 'Verifying package checksum.' );

		$temp = download_url( $package );
		if ( is_wp_error( $temp ) ) {
			return $temp;
		}

		$hash = hash_file( 'sha256', $temp );
		if ( ! hash_equals( strtolower( $checksum ), strtolower( $hash ) ) ) {
			self::log( 'Checksum mismatch.' );
			@unlink( $temp );
			return new WP_Error( 'wpress_migrator_bad_checksum', 'Update package checksum mismatch.' );
		}

		self::log( 'Checksum verified.' );

		return $temp;
	}

	private static function should_verify_package( $package ) {
		$transient = get_site_transient( 'update_plugins' );
		if ( empty( $transient ) || empty( $transient->response ) ) {
			return false;
		}

		$plugin_file = plugin_basename( WPRESS_MIGRATOR_FILE );
		if ( empty( $transient->response[ $plugin_file ]->package ) ) {
			return false;
		}

		return $transient->response[ $plugin_file ]->package === $package;
	}

	private static function log( $message ) {
		$options = WPRESS_Migrator_Settings::get_options();
		if ( empty( $options['debug_logging'] ) ) {
			return;
		}

		error_log( '[WPRESS Migrator] ' . $message );
	}
}

<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPRESS_Migrator_Updater {
	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_updates' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_information' ), 10, 3 );
	}

	public static function check_for_updates( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$options = WPRESS_Migrator_Settings::get_options();
		$owner = $options['github_owner'];
		$repo = $options['github_repo'];

		if ( empty( $owner ) || empty( $repo ) ) {
			return $transient;
		}

		$release = self::get_latest_release( $owner, $repo, $options['github_token'], $options['access_key'] );
		if ( empty( $release['tag_name'] ) ) {
			return $transient;
		}

		$current_version = WPRESS_MIGRATOR_VERSION;
		$latest_version = self::normalize_version( $release['tag_name'] );
		if ( version_compare( $latest_version, $current_version, '<=' ) ) {
			return $transient;
		}

		$download_url = self::get_download_url( $release, $options['github_asset'] );
		if ( empty( $download_url ) ) {
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

		$options = WPRESS_Migrator_Settings::get_options();
		$owner = $options['github_owner'];
		$repo = $options['github_repo'];

		$info = new stdClass();
		$info->name = 'WPRESS Migrator';
		$info->slug = 'wpress-migrator';
		$info->version = WPRESS_MIGRATOR_VERSION;
		$info->author = 'Local';
		$info->homepage = '';

		if ( ! empty( $owner ) && ! empty( $repo ) ) {
			$info->homepage = sprintf( 'https://github.com/%s/%s', $owner, $repo );
		}

		$info->sections = array(
			'description' => 'Wrapper plugin that loads bundled migration plugins.',
		);

		return $info;
	}

	private static function get_latest_release( $owner, $repo, $token, $access_key ) {
		$url = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', rawurlencode( $owner ), rawurlencode( $repo ) );
		$args = array(
			'headers' => array(
				'Accept' => 'application/vnd.github+json',
				'User-Agent' => 'WPRESS-Migrator',
			),
			'timeout' => 15,
		);

		if ( ! empty( $token ) ) {
			$args['headers']['Authorization'] = 'token ' . $token;
		} elseif ( ! empty( $access_key ) ) {
			$args['headers']['Authorization'] = 'token ' . $access_key;
		}

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
}

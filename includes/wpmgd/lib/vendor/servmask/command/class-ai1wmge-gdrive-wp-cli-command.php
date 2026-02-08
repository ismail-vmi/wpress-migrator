<?php
/**
 * Copyright (C) 2014-2020 ServMask Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * ███████╗███████╗██████╗ ██╗   ██╗███╗   ███╗ █████╗ ███████╗██╗  ██╗
 * ██╔════╝██╔════╝██╔══██╗██║   ██║████╗ ████║██╔══██╗██╔════╝██║ ██╔╝
 * ███████╗█████╗  ██████╔╝██║   ██║██╔████╔██║███████║███████╗█████╔╝
 * ╚════██║██╔══╝  ██╔══██╗╚██╗ ██╔╝██║╚██╔╝██║██╔══██║╚════██║██╔═██╗
 * ███████║███████╗██║  ██║ ╚████╔╝ ██║ ╚═╝ ██║██║  ██║███████║██║  ██╗
 * ╚══════╝╚══════╝╚═╝  ╚═╝  ╚═══╝  ╚═╝     ╚═╝╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

if ( defined( 'WP_CLI' ) && class_exists( 'Ai1wm_Backup_WP_CLI_Base' ) ) {
	class Ai1wmge_Gdrive_WP_CLI_Command extends Ai1wm_Backup_WP_CLI_Base {
		public function __construct() {
			parent::__construct();

			if ( ! get_option( 'ai1wmge_gdrive_token', false ) ) {
				WP_CLI::error_multi_line(
					array(
						__( 'In order to use All-in-One WP Migration Google Drive extension you need to configure it first.', AI1WMGE_PLUGIN_NAME ),
						__( 'Please navigate to WP Admin > All-in-One WP Migration > Google Drive Settings and Sign In to your Google Drive.', AI1WMGE_PLUGIN_NAME ),
					)
				);
				exit;
			}
		}

		/**
		 * Creates a new backup and uploads to Google Drive.
		 *
		 * ## OPTIONS
		 *
		 * [--sites]
		 * : Export sites by id (Multisite only). To list sites use: wp site list --fields=blog_id,url
		 *
		 * [--password[=<password>]]
		 * : Encrypt backup with password
		 *
		 * [--exclude-spam-comments]
		 * : Do not export spam comments
		 *
		 * [--exclude-post-revisions]
		 * : Do not export post revisions
		 *
		 * [--exclude-media]
		 * : Do not export media library (files)
		 *
		 * [--exclude-themes]
		 * : Do not export themes (files)
		 *
		 * [--exclude-inactive-themes]
		 * : Do not export inactive themes (files)
		 *
		 * [--exclude-muplugins]
		 * : Do not export must-use plugins (files)
		 *
		 * [--exclude-plugins]
		 * : Do not export plugins (files)
		 *
		 * [--exclude-inactive-plugins]
		 * : Do not export inactive plugins (files)
		 *
		 * [--exclude-cache]
		 * : Do not export cache (files)
		 *
		 * [--exclude-database]
		 * : Do not export database (sql)
		 *
		 * [--exclude-tables]
		 * : Do not export selected database tables (sql)
		 *
		 * [--exclude-email-replace]
		 * : Do not replace email domain (sql)
		 *
		 * [--replace]
		 * : Find and replace text in the database
		 *
		 * [<find>...]
		 * : A string to search for within the database
		 *
		 * [<replace>...]
		 * : Replace instances of the first string with this new string
		 *
		 * ## EXAMPLES
		 *
		 * $ wp ai1wm gdrive backup --replace "wp" "WordPress"
		 * Backup in progress...
		 * Uploading wordpress-20190509-142156-448.wpress (33 MB) [60% complete]
		 * Uploading wordpress-20190509-142156-448.wpress (33 MB) [100% complete]
		 * Success: Backup complete.
		 * Backup file: wordpress-20190509-142156-448.wpress
		 * Backup location: https://drive.google.com/open?id=1Dc0LJ5R_r30IX-0aObnkLM57F4JfLh-T
		 *
		 * @subcommand backup
		 */
		public function backup( $args = array(), $assoc_args = array() ) {
			$params = $this->run_backup(
				$this->build_export_params( $args, $assoc_args )
			);

			WP_CLI::log( sprintf( __( 'Backup location: %s', AI1WMGE_PLUGIN_NAME ), $this->get_backup_uri( $params ) ) );
		}

		/**
		 * Get a list of Google Drive backup files.
		 *
		 * ## OPTIONS
		 *
		 * [--folder-path=<path>]
		 * : List backups in a specific Google Drive subfolder
		 *
		 * ## EXAMPLES
		 *
		 * $ wp ai1wm gdrive list-backups
		 * +------------------------------------------------+--------------+-----------+
		 * | Backup name                                    | Date created | Size      |
		 * +------------------------------------------------+--------------+-----------+
		 * | migration-wp-20170908-152313-435.wpress        | 4 days ago   | 536.77 MB |
		 * | migration-wp-20170908-152103-603.wpress        | 4 days ago   | 536.77 MB |
		 * | migration-wp-20170908-152036-162.wpress        | 4 days ago   | 536.77 MB |
		 * +------------------------------------------------+--------------+-----------+
		 *
		 * $ wp ai1wm gdrive list-backups --folder-path=/backups/daily
		 * +------------------------------------------------+--------------+-----------+
		 * | Backup name                                    | Date created | Size      |
		 * +------------------------------------------------+--------------+-----------+
		 * | migration-wp-20170908-152313-435.wpress        | 4 days ago   | 536.77 MB |
		 * | migration-wp-20170908-152103-603.wpress        | 4 days ago   | 536.77 MB |
		 * +------------------------------------------------+--------------+-----------+
		 *
		 * @subcommand list-backups
		 */
		public function list_backups( $args = array(), $assoc_args = array() ) {
			$team_drive_id = get_option( 'ai1wmge_gdrive_team_drive_id', null );

			// List backups (lazy loading)
			if ( ( $folder_id = $this->get_folder_id( $assoc_args, $team_drive_id ) ) ) {
				$next_page_token = null;

				do {
					$backups = new cli\Table;

					$backups->setHeaders(
						array(
							'name' => __( 'Backup name', AI1WMGE_PLUGIN_NAME ),
							'date' => __( 'Date created', AI1WMGE_PLUGIN_NAME ),
							'size' => __( 'Size', AI1WMGE_PLUGIN_NAME ),
						)
					);

					$data = $this->list_items( $folder_id, $team_drive_id, $next_page_token );
					if ( isset( $data['items'] ) ) {
						foreach ( $data['items'] as $item ) {
							$backups->addRow(
								array(
									'name' => $item['name'],
									'date' => sprintf( __( '%s ago', AI1WMGE_PLUGIN_NAME ), human_time_diff( $item['date'] ) ),
									'size' => ai1wm_size_format( $item['bytes'], 2 ),
								)
							);
						}
					}

					$backups->display();

					if ( isset( $data['token'] ) ) {
						WP_CLI::log( __( 'Press enter key to continue...', AI1WMGE_PLUGIN_NAME ) );
						fgetc( STDIN );
					}
				} while ( isset( $data['token'] ) && ( $next_page_token = $data['token'] ) );
			}
		}

		/**
		 * Restores a backup from Google Drive.
		 *
		 * ## OPTIONS
		 *
		 * <file>
		 * : Name of the backup file
		 *
		 * [--folder-path=<path>]
		 * : Download a backup from a specific Google Drive subfolder
		 *
		 * [--yes]
		 * : Automatically confirm the restore operation
		 *
		 * ## EXAMPLES
		 *
		 * $ wp ai1wm gdrive restore migration-wp-20170913-095743-931.wpress
		 * Restore in progress...
		 * Restore complete.
		 *
		 * $ wp ai1wm gdrive restore migration-wp-20170913-095743-931.wpress --folder-path=/backups/daily
		 * @subcommand restore
		 */
		public function restore( $args = array(), $assoc_args = array() ) {
			if ( ! isset( $args[0] ) ) {
				WP_CLI::error_multi_line(
					array(
						__( 'A backup name must be provided in order to proceed with the restore process.', AI1WMGE_PLUGIN_NAME ),
						__( 'Example: wp ai1wm gdrive restore migration-wp-20170913-095743-931.wpress', AI1WMGE_PLUGIN_NAME ),
					)
				);
				exit;
			}

			$team_drive_id = get_option( 'ai1wmge_gdrive_team_drive_id', null );

			// Check backup name exists
			if ( ( $folder_id = $this->get_folder_id( $assoc_args, $team_drive_id ) ) ) {
				$next_page_token = null;

				do {
					$data = $this->list_items( $folder_id, $team_drive_id, $next_page_token );
					if ( isset( $data['items'] ) ) {
						foreach ( $data['items'] as $item ) {
							if ( $item['name'] === $args[0] ) {
								$file = $item;
								break 2;
							}
						}
					}
				} while ( isset( $data['token'] ) && ( $next_page_token = $data['token'] ) );
			}

			if ( ! isset( $file ) ) {
				WP_CLI::error_multi_line(
					array(
						__( 'The backup file could not be located in this folder.', AI1WMGE_PLUGIN_NAME ),
						__( 'To list available backups use: wp ai1wm gdrive list-backups', AI1WMGE_PLUGIN_NAME ),
					)
				);
				exit;
			}

			$params = array(
				'archive'    => $args[0],
				'storage'    => ai1wm_storage_folder(),
				'file_id'    => $file['id'],
				'file_size'  => $file['bytes'],
				'cli_args'   => $assoc_args,
				'secret_key' => get_option( AI1WM_SECRET_KEY, false ),
			);

			$this->run_restore( $params );
		}

		/**
		 * Get backup items list
		 *
		 * @param  string $folder_id       Folder ID where backups located
		 * @param  string $team_drive_id   Team Drive ID or null for My Drive
		 * @param  string $next_page_token Page token for files
		 * @return array
		 */
		protected function list_items( $folder_id, $team_drive_id = null, $next_page_token = null ) {
			$gdrive = new Ai1wmge_GDrive_Client(
				get_option( 'ai1wmge_gdrive_token', false ),
				get_option( 'ai1wmge_gdrive_ssl', true )
			);

			try {
				$data = $gdrive->list_folder_by_id( $folder_id, $team_drive_id, $next_page_token, "fileExtension = 'wpress'", array( 'orderBy' => 'folder,createdDate desc' ) );
			} catch ( Exception $e ) {
				WP_CLI::error( $e->getMessage() );
				exit;
			}

			return $data;
		}

		/**
		 * Get folder ID from command-line or WP settings
		 *
		 * @param  array  $assoc_args    CLI params
		 * @param  string $team_drive_id Team Drive ID or null for My Drive
		 * @return string
		 */
		protected function get_folder_id( $assoc_args, $team_drive_id = null ) {
			if ( isset( $assoc_args['folder-path'] ) ) {
				$gdrive = new Ai1wmge_GDrive_Client(
					get_option( 'ai1wmge_gdrive_token', false ),
					get_option( 'ai1wmge_gdrive_ssl', true )
				);

				$folder_path = explode( '/', trim( $assoc_args['folder-path'], '/' ) );

				$parent_id = 'root';
				foreach ( $folder_path as $folder_name ) {
					if ( ! ( $folder_id = $gdrive->get_folder_id_by_name( $folder_name, $parent_id, $team_drive_id ) ) ) {
						WP_CLI::error( sprintf( __( "Folder '%s' not found", AI1WMGE_PLUGIN_NAME ), $folder_name ) );
						exit;
					}

					$parent_id = $folder_id;
				}

				return $folder_id;
			}

			return get_option( 'ai1wmge_gdrive_folder_id', null );
		}

		/**
		 * Get backup location URL
		 *
		 * @param  array  $params Params
		 * @return string
		 */
		protected function get_backup_uri( $params ) {
			$gdrive = new Ai1wmge_GDrive_Client(
				get_option( 'ai1wmge_gdrive_token', false ),
				get_option( 'ai1wmge_gdrive_ssl', true )
			);

			$file_name     = ai1wm_archive_name( $params );
			$folder_id     = isset( $params['folder_id'] ) ? $params['folder_id'] : 'root';
			$team_drive_id = isset( $params['team_drive_id'] ) ? $params['team_drive_id'] : '';
			$file_id       = $gdrive->get_folder_id_by_name( $file_name, $folder_id, $team_drive_id );
			$location      = sprintf( 'https://drive.google.com/open?id=%s', $file_id );

			return $location;
		}
	}
}

<?php
/**
 * GitHub Release based plugin updater.
 *
 * @package Malzknecht_Post_Sidebar
 */

defined( 'ABSPATH' ) || exit;

class MPS_GitHub_Updater {

	const TRANSIENT_KEY = 'mps_github_release';
	const TRANSIENT_TTL = 6 * HOUR_IN_SECONDS;

	private $plugin_file;
	private $plugin_basename;
	private $plugin_slug;
	private $repo;
	private $current_version;

	public function __construct( $plugin_file, $repo ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_basename = plugin_basename( $plugin_file );
		$this->plugin_slug     = dirname( $this->plugin_basename );
		$this->repo            = $repo;

		$data                 = get_file_data( $plugin_file, array( 'Version' => 'Version' ) );
		$this->current_version = isset( $data['Version'] ) && $data['Version'] !== '' ? $data['Version'] : '0.0.0';

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
	}

	public static function clear_cache() {
		delete_transient( self::TRANSIENT_KEY );
		delete_site_transient( 'update_plugins' );
	}

	private function get_latest_release() {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$url = sprintf( 'https://api.github.com/repos/%s/releases/latest', $this->repo );
		$response = wp_remote_get( $url, array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
			),
		) );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_transient( self::TRANSIENT_KEY, array(), 30 * MINUTE_IN_SECONDS );
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			set_transient( self::TRANSIENT_KEY, array(), 30 * MINUTE_IN_SECONDS );
			return array();
		}

		$zip_url = '';
		if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
			foreach ( $body['assets'] as $asset ) {
				if ( isset( $asset['name'] ) && substr( $asset['name'], -4 ) === '.zip' ) {
					$zip_url = isset( $asset['browser_download_url'] ) ? $asset['browser_download_url'] : '';
					break;
				}
			}
		}
		if ( '' === $zip_url && ! empty( $body['zipball_url'] ) ) {
			$zip_url = $body['zipball_url'];
		}

		$data = array(
			'version'      => ltrim( (string) $body['tag_name'], 'v' ),
			'zip_url'      => $zip_url,
			'published_at' => isset( $body['published_at'] ) ? $body['published_at'] : '',
			'changelog'    => isset( $body['body'] ) ? (string) $body['body'] : '',
			'html_url'     => isset( $body['html_url'] ) ? $body['html_url'] : '',
		);

		set_transient( self::TRANSIENT_KEY, $data, self::TRANSIENT_TTL );
		return $data;
	}

	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}
		if ( empty( $transient->checked ) ) {
			return $transient;
		}
		$latest = $this->get_latest_release();
		if ( empty( $latest['version'] ) || empty( $latest['zip_url'] ) ) {
			return $transient;
		}
		if ( version_compare( $latest['version'], $this->current_version, '<=' ) ) {
			return $transient;
		}

		$item = (object) array(
			'id'           => $this->plugin_basename,
			'slug'         => $this->plugin_slug,
			'plugin'       => $this->plugin_basename,
			'new_version'  => $latest['version'],
			'url'          => $latest['html_url'],
			'package'      => $latest['zip_url'],
			'icons'        => array(),
			'banners'      => array(),
			'banners_rtl'  => array(),
			'tested'       => '',
			'requires_php' => '7.4',
		);

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		$transient->response[ $this->plugin_basename ] = $item;
		return $transient;
	}

	public function plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( empty( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}
		$latest = $this->get_latest_release();
		if ( empty( $latest['version'] ) ) {
			return $result;
		}
		return (object) array(
			'name'          => 'Malzknecht Post-Sidebar',
			'slug'          => $this->plugin_slug,
			'version'       => $latest['version'],
			'author'        => '<a href="https://malzknecht.de">Malzknecht</a>',
			'homepage'      => $latest['html_url'],
			'download_link' => $latest['zip_url'],
			'sections'      => array(
				'description' => 'Dynamisches Sidebar-Modul pro Beitrag.',
				'changelog'   => wp_kses_post( wpautop( $latest['changelog'] ) ),
			),
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'last_updated'  => $latest['published_at'],
		);
	}

	public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		if ( ! is_string( $source ) || '' === $source ) {
			return $source;
		}
		if ( ! is_dir( $source ) ) {
			return $source;
		}
		if ( ! empty( $hook_extra['plugin'] ) && $hook_extra['plugin'] !== $this->plugin_basename ) {
			return $source;
		}
		$expected = trailingslashit( $remote_source ) . $this->plugin_slug . '/';
		if ( $source === $expected ) {
			return $source;
		}
		$main = trailingslashit( $source ) . basename( $this->plugin_file );
		if ( ! file_exists( $main ) ) {
			return $source;
		}
		global $wp_filesystem;
		if ( $wp_filesystem && $wp_filesystem->move( untrailingslashit( $source ), untrailingslashit( $expected ), true ) ) {
			return $expected;
		}
		return $source;
	}
}

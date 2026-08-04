<?php
/**
 * Plugin Name: GlotStat
 * Plugin URI: https://github.com/ernilambar/glotstat
 * Description: Displays plugin translation stats.
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Version: 1.0.0
 * Author: Nilambar Sharma
 * Author URI: https://nilambar.net
 * License: GPL-2.0-or-later
 * License URI: https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain: glotstat
 * Domain Path: /languages
 *
 * @package GlotStat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GLOTSTAT_VERSION', '1.0.0' );
define( 'GLOTSTAT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class.
 *
 * @since 1.0.0
 */
class Glotstat {

	/**
	 * Hook into WordPress.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_glotstat_get_translation_status', [ $this, 'ajax_get_translation_status' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_script' ] );
	}

	/**
	 * AJAX handler that fetches (and caches) the translation stats for a plugin.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_translation_status() {
		check_ajax_referer( 'glotstat_nonce', 'nonce' );

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( [ 'message' => __( 'Sorry, you are not allowed to install plugins on this site.', 'glotstat' ) ] );
		}

		$slug   = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
		$locale = get_user_locale();

		if ( empty( $slug ) || 'en_US' === $locale ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'glotstat' ) ] );
		}

		$transient_key = 'glotstat_' . md5( $slug . '_' . $locale );
		$status        = get_transient( $transient_key );

		if ( ! is_array( $status ) ) {
			$status = [
				'percent'  => 'N/A',
				'url'      => '',
				'current'  => 0,
				'total'    => 0,
				'waiting'  => 0,
				'fuzzy'    => 0,
				'warnings' => 0,
			];

			$api_url  = sprintf( 'https://translate.wordpress.org/api/projects/wp-plugins/%s/dev/', $slug );
			$response = wp_remote_get( $api_url, [ 'timeout' => 4 ] );

			if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
				$data = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( ! empty( $data['translation_sets'] ) ) {
					foreach ( $data['translation_sets'] as $set ) {
						if ( 'default' === $set['slug'] && $locale === $set['wp_locale'] ) {
							$gp_locale          = preg_replace( '/[^a-z0-9-]/', '', $set['locale'] );
							$status['percent']  = (int) $set['percent_translated'];
							$status['url']      = esc_url_raw( sprintf( 'https://translate.wordpress.org/projects/wp-plugins/%s/dev/%s/default/', $slug, $gp_locale ) );
							$status['current']  = (int) $set['current_count'];
							$status['total']    = (int) $set['all_count'];
							$status['waiting']  = (int) $set['waiting_count'];
							$status['fuzzy']    = (int) $set['fuzzy_count'];
							$status['warnings'] = (int) $set['warnings_count'];
							break;
						}
					}
				}
			}

			set_transient( $transient_key, $status, 6 * HOUR_IN_SECONDS );
		}

		wp_send_json_success(
			[
				'percent'  => $status['percent'],
				'url'      => $status['url'],
				'locale'   => $locale,
				'current'  => $status['current'],
				'total'    => $status['total'],
				'waiting'  => $status['waiting'],
				'fuzzy'    => $status['fuzzy'],
				'warnings' => $status['warnings'],
			]
		);
	}

	/**
	 * Enqueue the script that fetches and renders translation status on plugin cards.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_script( $hook_suffix ) {
		if ( 'plugin-install.php' !== $hook_suffix ) {
			return;
		}

		if ( 'en_US' === get_user_locale() ) {
			return;
		}

		wp_enqueue_script( 'glotstat', GLOTSTAT_URL . 'build/main.js', [], GLOTSTAT_VERSION, true );
		wp_enqueue_style( 'glotstat', GLOTSTAT_URL . 'build/main.css', [], GLOTSTAT_VERSION );

		$data = [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'glotstat_nonce' ),
			'i18n'    => [
				'checking'    => __( 'Checking translation…', 'glotstat' ),
				'unavailable' => __( 'Translation stats unavailable.', 'glotstat' ),
				'error'       => __( 'Failed to load translation.', 'glotstat' ),
				/* translators: %d: waiting string count. */
				'waiting'     => __( '%d waiting', 'glotstat' ),
				/* translators: %d: fuzzy string count. */
				'fuzzy'       => __( '%d fuzzy', 'glotstat' ),
				/* translators: %d: warning count. */
				'warnings'    => __( '%d warnings', 'glotstat' ),
			],
		];

		wp_add_inline_script( 'glotstat', 'const glotstatData = ' . wp_json_encode( $data ) . ';', 'before' );
	}
}

new Glotstat();

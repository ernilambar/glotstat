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

add_action( 'wp_ajax_glotstat_get_translation_status', 'glotstat_ajax_get_translation_status' );

/**
 * AJAX handler that fetches (and caches) the translation stats for a plugin.
 *
 * @since 1.0.0
 */
function glotstat_ajax_get_translation_status() {
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
		$status   = [
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

		set_transient( $transient_key, $status, 12 * HOUR_IN_SECONDS );
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

add_action( 'admin_footer-plugin-install.php', 'glotstat_enqueue_script' );

/**
 * Print the script that fetches and renders translation status on plugin cards.
 *
 * @since 1.0.0
 */
function glotstat_enqueue_script() {
	if ( 'en_US' === get_user_locale() ) {
		return;
	}

	$nonce   = wp_create_nonce( 'glotstat_nonce' );
	$strings = [
		'checking'    => __( 'Checking translation…', 'glotstat' ),
		'unavailable' => __( 'Translation stats unavailable.', 'glotstat' ),
		'error'       => __( 'Failed to load translation.', 'glotstat' ),
		/* translators: %d: waiting string count. */
		'waiting'     => __( '%d waiting', 'glotstat' ),
		/* translators: %d: fuzzy string count. */
		'fuzzy'       => __( '%d fuzzy', 'glotstat' ),
		/* translators: %d: warning count. */
		'warnings'    => __( '%d warnings', 'glotstat' ),
	];
	?>
	<script type="text/javascript">
	jQuery( document ).ready( function ( $ ) {
		var glotstatNonce   = <?php echo wp_json_encode( $nonce ); ?>;
		var glotstatStrings = <?php echo wp_json_encode( $strings ); ?>;

		function glotstatFetchStatus( container ) {
			var $container = $( container );
			var slug = $container.data( 'slug' );

			if ( $container.hasClass( 'is-loaded' ) || ! slug ) {
				return;
			}
			$container.addClass( 'is-loaded' );

			$.ajax( {
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'glotstat_get_translation_status',
					slug: slug,
					nonce: glotstatNonce
				},
				success: function ( response ) {
					if ( response.success && response.data.percent !== 'N/A' ) {
						var percent = parseInt( response.data.percent, 10 );
						var color   = percent >= 90 ? '#46b450' : ( percent >= 50 ? '#ffb900' : '#dc3232' );
						var label   = response.data.locale + ': ' + percent + '% (' + response.data.current + '/' + response.data.total + ')';
						var labelHtml = response.data.url ?
							'<a href="' + response.data.url + '" target="_blank" rel="noopener noreferrer">' + label + '</a>' :
							label;
						var iconHtml = '<span class="dashicons dashicons-translation" style="font-size:14px; width:14px; height:14px; line-height:14px; vertical-align:text-bottom; margin-right:4px;"></span>';

						var extras = [];
						if ( response.data.waiting > 0 ) {
							extras.push( glotstatStrings.waiting.replace( '%d', response.data.waiting ) );
						}
						if ( response.data.fuzzy > 0 ) {
							extras.push( glotstatStrings.fuzzy.replace( '%d', response.data.fuzzy ) );
						}
						if ( response.data.warnings > 0 ) {
							extras.push( glotstatStrings.warnings.replace( '%d', response.data.warnings ) );
						}
						var extrasHtml = extras.length ?
							' <span style="color:#8c8f94;">(' + extras.join( ', ' ) + ')</span>' :
							'';

						$container.html(
							'<strong>' + iconHtml + labelHtml + '</strong>' + extrasHtml +
							'<div style="background:#e0e0e0; height:5px; border-radius:3px; overflow:hidden; margin-top:3px;">' +
								'<div style="background:' + color + '; width:' + percent + '%; height:100%;"></div>' +
							'</div>'
						);
					} else {
						$container.html( '<span style="color:#8c8f94; font-size:11px;">' + glotstatStrings.unavailable + '</span>' );
					}
				},
				error: function () {
					$container.html( '<span style="color:#dc3232; font-size:11px;">' + glotstatStrings.error + '</span>' );
				}
			} );
		}

		var glotstatObserver = new IntersectionObserver( function ( entries, observer ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					glotstatFetchStatus( entry.target );
					observer.unobserve( entry.target );
				}
			} );
		}, { rootMargin: '0px 0px 50px 0px' } );

		function glotstatCreatePlaceholders() {
			$( '.plugin-card' ).not( '.glotstat-processed' ).each( function () {
				var $card = $( this );
				$card.addClass( 'glotstat-processed' );

				var slug = '';
				var classes = ( $card.attr( 'class' ) || '' ).split( /\s+/ );
				for ( var i = 0; i < classes.length; i++ ) {
					if ( 0 === classes[ i ].indexOf( 'plugin-card-' ) ) {
						slug = classes[ i ].substring( 'plugin-card-'.length );
						break;
					}
				}

				if ( ! slug ) {
					return;
				}

				var $placeholder = $(
					'<div class="plugin-translation-status" data-slug="' + slug + '" style="margin:0 0 10px; font-size:12px; min-height:20px;">' +
						'<span class="status-label" style="color:#646970;">' + glotstatStrings.checking + '</span>' +
					'</div>'
				);

				var $bottom = $card.find( '.plugin-card-bottom' );
				if ( $bottom.length ) {
					$placeholder.prependTo( $bottom );
				} else {
					$card.append( $placeholder );
				}
			} );
		}

		function glotstatObservePlaceholders() {
			glotstatCreatePlaceholders();

			$( '.plugin-translation-status:not(.is-observed)' ).each( function () {
				$( this ).addClass( 'is-observed' );
				glotstatObserver.observe( this );
			} );
		}

		glotstatObservePlaceholders();

		var glotstatDomObserver = new MutationObserver( function () {
			glotstatObservePlaceholders();
		} );

		var glotstatTargetNode = document.getElementById( 'the-list' );
		if ( glotstatTargetNode ) {
			glotstatDomObserver.observe( glotstatTargetNode, { childList: true, subtree: true } );
		}
	} );
	</script>
	<?php
}

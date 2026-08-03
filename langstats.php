<?php
/**
 * Plugin Name: Language Stats
 * Description: Shows plugin translation completion percentage on the Add Plugins screen.
 * Version: 1.0.0
 * Text Domain: langstats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'plugin_install_description', 'langstats_inject_placeholder', 10, 2 );
/**
 * Inject an empty placeholder into the plugin card description.
 *
 * @param string $description Plugin card description.
 * @param array  $plugin      Plugin API data.
 * @return string
 */
function langstats_inject_placeholder( $description, $plugin ) {
	$locale = get_user_locale();

	if ( 'en_US' === $locale || empty( $plugin['slug'] ) ) {
		return $description;
	}

	$placeholder = sprintf(
		'<div class="plugin-translation-status" data-slug="%s" style="margin-top:10px; font-size:12px; min-height:20px;"><span class="status-label" style="color:#646970;">%s</span></div>',
		esc_attr( $plugin['slug'] ),
		esc_html__( 'Checking translation…', 'langstats' )
	);

	return $description . $placeholder;
}

add_action( 'wp_ajax_langstats_get_translation_status', 'langstats_ajax_get_translation_status' );
/**
 * AJAX handler that fetches (and caches) the translation percentage for a plugin.
 */
function langstats_ajax_get_translation_status() {
	check_ajax_referer( 'langstats_nonce', 'nonce' );

	if ( ! current_user_can( 'install_plugins' ) ) {
		wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'langstats' ) ) );
	}

	$slug   = isset( $_POST['slug'] ) ? sanitize_key( wp_unslash( $_POST['slug'] ) ) : '';
	$locale = get_user_locale();

	if ( empty( $slug ) || 'en_US' === $locale ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request', 'langstats' ) ) );
	}

	$transient_key = 'langstats_' . md5( $slug . '_' . $locale );
	$status        = get_transient( $transient_key );

	if ( ! is_array( $status ) ) {
		$status   = array(
			'percent' => 'N/A',
			'url'     => '',
		);
		$api_url  = sprintf( 'https://translate.wordpress.org/api/projects/wp-plugins/%s/stable/', $slug );
		$response = wp_remote_get( $api_url, array( 'timeout' => 4 ) );

		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( ! empty( $data['translation_sets'] ) ) {
				foreach ( $data['translation_sets'] as $set ) {
					if ( 'default' === $set['slug'] && $locale === $set['wp_locale'] ) {
						$gp_locale          = preg_replace( '/[^a-z0-9-]/', '', $set['locale'] );
						$status['percent']  = (int) $set['percent_translated'];
						$status['url']      = esc_url_raw( sprintf( 'https://translate.wordpress.org/projects/wp-plugins/%s/stable/%s/default/', $slug, $gp_locale ) );
						break;
					}
				}
			}
		}

		set_transient( $transient_key, $status, 12 * HOUR_IN_SECONDS );
	}

	wp_send_json_success(
		array(
			'percent' => $status['percent'],
			'url'     => $status['url'],
			'locale'  => $locale,
		)
	);
}

add_action( 'admin_footer-plugin-install.php', 'langstats_enqueue_script' );
/**
 * Print the script that fetches and renders translation status on plugin cards.
 */
function langstats_enqueue_script() {
	$nonce   = wp_create_nonce( 'langstats_nonce' );
	$strings = array(
		/* translators: %1$s: locale code, %2$d: translation percent. */
		'label'       => __( 'Translation (%1$s): %2$d%', 'langstats' ),
		'unavailable' => __( 'Translation stats unavailable', 'langstats' ),
		'error'       => __( 'Failed to load translation', 'langstats' ),
	);
	?>
	<script type="text/javascript">
	jQuery( document ).ready( function ( $ ) {
		var langstatsNonce   = <?php echo wp_json_encode( $nonce ); ?>;
		var langstatsStrings = <?php echo wp_json_encode( $strings ); ?>;

		function langstatsFetchStatus( container ) {
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
					action: 'langstats_get_translation_status',
					slug: slug,
					nonce: langstatsNonce
				},
				success: function ( response ) {
					if ( response.success && response.data.percent !== 'N/A' ) {
						var percent = parseInt( response.data.percent, 10 );
						var color   = percent >= 90 ? '#46b450' : ( percent >= 50 ? '#ffb900' : '#dc3232' );
						var label   = langstatsStrings.label
							.replace( '%1$s', response.data.locale )
							.replace( '%2$d', percent );
						var labelHtml = response.data.url ?
							'<a href="' + response.data.url + '" target="_blank" rel="noopener noreferrer">' + label + '</a>' :
							label;

						$container.html(
							'<strong>' + labelHtml + '</strong>' +
							'<div style="background:#e0e0e0; height:5px; border-radius:3px; overflow:hidden; margin-top:3px;">' +
								'<div style="background:' + color + '; width:' + percent + '%; height:100%;"></div>' +
							'</div>'
						);
					} else {
						$container.html( '<span style="color:#8c8f94; font-size:11px;">' + langstatsStrings.unavailable + '</span>' );
					}
				},
				error: function () {
					$container.html( '<span style="color:#dc3232; font-size:11px;">' + langstatsStrings.error + '</span>' );
				}
			} );
		}

		var langstatsObserver = new IntersectionObserver( function ( entries, observer ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					langstatsFetchStatus( entry.target );
					observer.unobserve( entry.target );
				}
			} );
		}, { rootMargin: '0px 0px 50px 0px' } );

		function langstatsObservePlaceholders() {
			$( '.plugin-translation-status:not(.is-observed)' ).each( function () {
				$( this ).addClass( 'is-observed' );
				langstatsObserver.observe( this );
			} );
		}

		langstatsObservePlaceholders();

		var langstatsDomObserver = new MutationObserver( function () {
			langstatsObservePlaceholders();
		} );

		var langstatsTargetNode = document.getElementById( 'the-list' );
		if ( langstatsTargetNode ) {
			langstatsDomObserver.observe( langstatsTargetNode, { childList: true, subtree: true } );
		}
	} );
	</script>
	<?php
}

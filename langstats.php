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
			'percent'  => 'N/A',
			'url'      => '',
			'current'  => 0,
			'total'    => 0,
			'waiting'  => 0,
			'fuzzy'    => 0,
			'warnings' => 0,
		);
		$api_url  = sprintf( 'https://translate.wordpress.org/api/projects/wp-plugins/%s/dev/', $slug );
		$response = wp_remote_get( $api_url, array( 'timeout' => 4 ) );

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
		array(
			'percent'  => $status['percent'],
			'url'      => $status['url'],
			'locale'   => $locale,
			'current'  => $status['current'],
			'total'    => $status['total'],
			'waiting'  => $status['waiting'],
			'fuzzy'    => $status['fuzzy'],
			'warnings' => $status['warnings'],
		)
	);
}

add_action( 'admin_footer-plugin-install.php', 'langstats_enqueue_script' );
/**
 * Print the script that fetches and renders translation status on plugin cards.
 */
function langstats_enqueue_script() {
	if ( 'en_US' === get_user_locale() ) {
		return;
	}

	$nonce   = wp_create_nonce( 'langstats_nonce' );
	$strings = array(
		'checking'    => __( 'Checking translation…', 'langstats' ),
		/* translators: %1$s: locale code, %2$d: translation percent, %3$d: translated string count, %4$d: total string count. */
		'label'       => __( '%1$s: %2$d% (%3$d/%4$d)', 'langstats' ),
		'unavailable' => __( 'Translation stats unavailable', 'langstats' ),
		'error'       => __( 'Failed to load translation', 'langstats' ),
		/* translators: %d: waiting string count. */
		'waiting'     => __( '%d waiting', 'langstats' ),
		/* translators: %d: fuzzy string count. */
		'fuzzy'       => __( '%d fuzzy', 'langstats' ),
		/* translators: %d: warning count. */
		'warnings'    => __( '%d warnings', 'langstats' ),
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
							.replace( '%2$d', percent )
							.replace( '%3$d', response.data.current )
							.replace( '%4$d', response.data.total );
						var labelHtml = response.data.url ?
							'<a href="' + response.data.url + '" target="_blank" rel="noopener noreferrer">' + label + '</a>' :
							label;
						var iconHtml = '<span class="dashicons dashicons-translation" style="font-size:14px; width:14px; height:14px; line-height:14px; vertical-align:text-bottom; margin-right:4px;"></span>';

						var extras = [];
						if ( response.data.waiting > 0 ) {
							extras.push( langstatsStrings.waiting.replace( '%d', response.data.waiting ) );
						}
						if ( response.data.fuzzy > 0 ) {
							extras.push( langstatsStrings.fuzzy.replace( '%d', response.data.fuzzy ) );
						}
						if ( response.data.warnings > 0 ) {
							extras.push( langstatsStrings.warnings.replace( '%d', response.data.warnings ) );
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

		function langstatsCreatePlaceholders() {
			$( '.plugin-card' ).not( '.langstats-processed' ).each( function () {
				var $card = $( this );
				$card.addClass( 'langstats-processed' );

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
						'<span class="status-label" style="color:#646970;">' + langstatsStrings.checking + '</span>' +
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

		function langstatsObservePlaceholders() {
			langstatsCreatePlaceholders();

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

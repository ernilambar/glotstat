import './main.css';

document.addEventListener( 'DOMContentLoaded', function () {
	function glotstatFetchStatus( container ) {
		const slug = container.dataset.slug;

		if ( container.classList.contains( 'is-loaded' ) || ! slug ) {
			return;
		}
		container.classList.add( 'is-loaded' );

		const formData = new FormData();
		formData.append( 'action', 'glotstat_get_translation_status' );
		formData.append( 'slug', slug );
		formData.append( 'nonce', glotstatData.nonce );

		fetch( glotstatData.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( response ) {
				if ( response.success && 'N/A' !== response.data.percent ) {
					const percent = parseInt( response.data.percent, 10 );
					const colorClass =
						percent >= 90 ? 'is-high' : percent >= 50 ? 'is-medium' : 'is-low';
					const label =
						response.data.locale +
						': ' +
						percent +
						'% (' +
						response.data.current +
						'/' +
						response.data.total +
						')';
					const labelHtml = response.data.url
						? '<a href="' +
						  response.data.url +
						  '" target="_blank" rel="noopener noreferrer">' +
						  label +
						  '</a>'
						: label;
					const iconHtml =
						'<span class="dashicons dashicons-translation glotstat-icon"></span>';

					const extras = [];
					if ( response.data.waiting > 0 ) {
						extras.push(
							glotstatData.i18n.waiting.replace( '%d', response.data.waiting )
						);
					}
					if ( response.data.fuzzy > 0 ) {
						extras.push( glotstatData.i18n.fuzzy.replace( '%d', response.data.fuzzy ) );
					}
					if ( response.data.warnings > 0 ) {
						extras.push(
							glotstatData.i18n.warnings.replace( '%d', response.data.warnings )
						);
					}
					const extrasHtml = extras.length
						? ' <span class="glotstat-extras">(' + extras.join( ', ' ) + ')</span>'
						: '';

					container.innerHTML =
						'<strong>' +
						iconHtml +
						labelHtml +
						'</strong>' +
						extrasHtml +
						'<div class="glotstat-progress">' +
						'<div class="glotstat-progress-bar ' +
						colorClass +
						'" style="width:' +
						percent +
						'%"></div>' +
						'</div>';
				} else {
					container.innerHTML =
						'<span class="glotstat-unavailable">' +
						glotstatData.i18n.unavailable +
						'</span>';
				}
			} )
			.catch( function () {
				container.innerHTML =
					'<span class="glotstat-error">' + glotstatData.i18n.error + '</span>';
			} );
	}

	const glotstatObserver = new IntersectionObserver(
		function ( entries, observer ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					glotstatFetchStatus( entry.target );
					observer.unobserve( entry.target );
				}
			} );
		},
		{ rootMargin: '0px 0px 50px 0px' }
	);

	function glotstatCreatePlaceholders() {
		document.querySelectorAll( '.plugin-card' ).forEach( function ( card ) {
			if ( card.classList.contains( 'glotstat-processed' ) ) {
				return;
			}
			card.classList.add( 'glotstat-processed' );

			let slug = '';
			card.classList.forEach( function ( className ) {
				if ( 0 === className.indexOf( 'plugin-card-' ) ) {
					slug = className.substring( 'plugin-card-'.length );
				}
			} );

			if ( ! slug ) {
				return;
			}

			const placeholder = document.createElement( 'div' );
			placeholder.className = 'plugin-translation-status glotstat-status';
			placeholder.dataset.slug = slug;
			placeholder.innerHTML =
				'<span class="status-label glotstat-status-label">' +
				glotstatData.i18n.checking +
				'</span>';

			const bottom = card.querySelector( '.plugin-card-bottom' );
			if ( bottom ) {
				bottom.prepend( placeholder );
			} else {
				card.append( placeholder );
			}
		} );
	}

	function glotstatObservePlaceholders() {
		glotstatCreatePlaceholders();

		document
			.querySelectorAll( '.plugin-translation-status:not(.is-observed)' )
			.forEach( function ( el ) {
				el.classList.add( 'is-observed' );
				glotstatObserver.observe( el );
			} );
	}

	glotstatObservePlaceholders();

	const glotstatDomObserver = new MutationObserver( function () {
		glotstatObservePlaceholders();
	} );

	const glotstatTargetNode = document.getElementById( 'the-list' );
	if ( glotstatTargetNode ) {
		glotstatDomObserver.observe( glotstatTargetNode, { childList: true, subtree: true } );
	}
} );

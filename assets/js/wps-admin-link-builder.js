( function () {
	'use strict';

	var urlInput = document.getElementById( 'wps-link-url' );
	var sourceSelect = document.getElementById( 'wps-link-source' );
	var mediumInput = document.getElementById( 'wps-link-medium' );
	var campaignInput = document.getElementById( 'wps-link-campaign' );
	var output = document.getElementById( 'wps-link-output' );
	var copyBtn = document.getElementById( 'wps-link-copy' );
	var copiedLabel = document.getElementById( 'wps-link-copied' );

	if ( ! urlInput || ! output ) {
		return;
	}

	function build() {
		var raw = urlInput.value.trim();

		if ( ! raw ) {
			output.value = '';
			return;
		}

		var url;
		try {
			url = new URL( raw, window.location.origin );
		} catch ( e ) {
			output.value = '';
			return;
		}

		url.searchParams.set( 'utm_source', sourceSelect.value );

		if ( mediumInput.value.trim() ) {
			url.searchParams.set( 'utm_medium', mediumInput.value.trim() );
		} else {
			url.searchParams.delete( 'utm_medium' );
		}

		if ( campaignInput.value.trim() ) {
			url.searchParams.set( 'utm_campaign', campaignInput.value.trim() );
		} else {
			url.searchParams.delete( 'utm_campaign' );
		}

		output.value = url.toString();
	}

	[ urlInput, sourceSelect, mediumInput, campaignInput ].forEach( function ( el ) {
		el.addEventListener( 'input', build );
		el.addEventListener( 'change', build );
	} );

	function showCopied() {
		if ( ! copiedLabel ) {
			return;
		}
		copiedLabel.classList.add( 'is-visible' );
		setTimeout( function () {
			copiedLabel.classList.remove( 'is-visible' );
		}, 1800 );
	}

	if ( copyBtn ) {
		copyBtn.addEventListener( 'click', function () {
			output.select();

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( output.value ).then( showCopied );
			} else {
				document.execCommand( 'copy' );
				showCopied();
			}
		} );
	}

	build();
} )();

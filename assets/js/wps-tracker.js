( function () {
	'use strict';

	if ( typeof wpsTrackerData === 'undefined' ) {
		return;
	}

	var trackUrl = wpsTrackerData.restUrl + '?_wpnonce=' + encodeURIComponent( wpsTrackerData.nonce );

	function send( type ) {
		var payload = {
			type: type,
			object_type: wpsTrackerData.objectType,
			object_id: wpsTrackerData.objectId,
			referrer: document.referrer || ''
		};

		if ( wpsTrackerData.requestPath ) {
			payload.request_path = wpsTrackerData.requestPath;
		}

		try {
			var params = new URLSearchParams( window.location.search );
			[ 'utm_source', 'utm_medium', 'utm_campaign' ].forEach( function ( key ) {
				if ( params.has( key ) ) {
					payload[ key ] = params.get( key );
				}
			} );
		} catch ( e ) {
			// URLSearchParams unsupported — beacon still fires without UTM data.
		}

		var body = JSON.stringify( payload );

		if ( navigator.sendBeacon ) {
			navigator.sendBeacon( trackUrl, new Blob( [ body ], { type: 'application/json' } ) );
			return;
		}

		if ( window.fetch ) {
			fetch( trackUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: body,
				keepalive: true
			} ).catch( function () {} );
		}
	}

	send( 'view' );

	if ( wpsTrackerData.heartbeatInterval > 0 ) {
		setInterval( function () {
			if ( document.visibilityState === 'visible' ) {
				send( 'heartbeat' );
			}
		}, wpsTrackerData.heartbeatInterval );
	}
} )();

( function () {
	'use strict';

	if ( typeof wpsOnlineData === 'undefined' ) {
		return;
	}

	var countEl = document.getElementById( 'wps-online-count' );
	var listEl = document.getElementById( 'wps-online-items' );

	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.textContent = str;
		return div.innerHTML;
	}

	// The colour comes from the server's fixed channel map; still, only ever
	// let a plain hex value into a style attribute.
	function safeColor( color ) {
		return /^#[0-9a-f]{6}$/i.test( color ) ? color : '#adb5bd';
	}

	function renderItems( items ) {
		if ( ! listEl ) {
			return;
		}

		if ( ! items || ! items.length ) {
			listEl.innerHTML = '<tr><td colspan="2" class="wps-table__empty"><div class="wps-empty"><strong>کاربر آنلاینی وجود ندارد.</strong></div></td></tr>';
			return;
		}

		listEl.innerHTML = items.map( function ( item ) {
			return '<tr><td>' + escapeHtml( item.title ) + '</td>' +
				'<td><span class="wps-chan" style="--c:' + safeColor( item.color ) + '">' + escapeHtml( item.channel ) + '</span></td></tr>';
		} ).join( '' );
	}

	function poll() {
		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', ajaxurl );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
		xhr.onload = function () {
			if ( xhr.status !== 200 ) {
				return;
			}
			try {
				var res = JSON.parse( xhr.responseText );
				if ( res.success && res.data ) {
					if ( countEl && typeof res.data.count !== 'undefined' ) {
						countEl.textContent = res.data.count;
					}
					renderItems( res.data.items );
				}
			} catch ( e ) {}
		};
		xhr.send( 'action=wps_online_count&_wpnonce=' + encodeURIComponent( wpsOnlineData.nonce ) );
	}

	poll();
	setInterval( poll, wpsOnlineData.pollInterval );
} )();

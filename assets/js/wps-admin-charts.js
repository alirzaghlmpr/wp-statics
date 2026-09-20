( function () {
	'use strict';

	if ( typeof wpsChartsData === 'undefined' || typeof Chart === 'undefined' ) {
		return;
	}

	var FONT = '"WPS Vazirmatn", Tahoma, "Segoe UI", sans-serif';
	var INK = '#17212f';
	var MUTED = '#5b6675';
	var GRID = '#eef0f3';
	var ACCENT = '#0b7a85';
	// Devices are a different dimension from channels, so they use a single-hue
	// ramp instead of the channel colours.
	var DEVICE_COLORS = [ '#0b7a85', '#5fb4bc', '#b5dde0', '#dcedee' ];

	Chart.defaults.font.family = FONT;
	Chart.defaults.font.size = 12;
	Chart.defaults.color = MUTED;
	Chart.defaults.plugins.tooltip.backgroundColor = INK;
	Chart.defaults.plugins.tooltip.padding = 10;
	Chart.defaults.plugins.tooltip.cornerRadius = 8;
	Chart.defaults.plugins.tooltip.displayColors = false;
	Chart.defaults.plugins.tooltip.rtl = true;
	Chart.defaults.plugins.tooltip.textDirection = 'rtl';
	Chart.defaults.plugins.tooltip.titleFont = { family: FONT, weight: '600' };
	Chart.defaults.plugins.tooltip.bodyFont = { family: FONT };

	function showEmpty( canvas ) {
		var box = canvas.parentNode;
		box.style.height = 'auto';
		box.innerHTML = '<div class="wps-empty"><strong>داده‌ای برای این بازه یافت نشد.</strong><span>بازه زمانی دیگری را امتحان کنید.</span></div>';
	}

	function areaFill( context ) {
		var area = context.chart.chartArea;

		if ( ! area ) {
			return 'rgba(11, 122, 133, 0.12)';
		}

		var gradient = context.chart.ctx.createLinearGradient( 0, area.top, 0, area.bottom );
		gradient.addColorStop( 0, 'rgba(11, 122, 133, 0.22)' );
		gradient.addColorStop( 1, 'rgba(11, 122, 133, 0)' );

		return gradient;
	}

	function drawViews( canvas ) {
		var rows = wpsChartsData.dailyViews;

		if ( ! rows || ! rows.length ) {
			showEmpty( canvas );
			return;
		}

		new Chart( canvas, {
			type: 'line',
			data: {
				labels: rows.map( function ( row ) { return row.date; } ),
				datasets: [ {
					label: 'بازدید',
					data: rows.map( function ( row ) { return row.views; } ),
					borderColor: ACCENT,
					borderWidth: 2,
					backgroundColor: areaFill,
					fill: true,
					tension: 0.35,
					pointRadius: 0,
					pointHoverRadius: 5,
					pointHoverBackgroundColor: ACCENT,
					pointHoverBorderColor: '#fff',
					pointHoverBorderWidth: 2
				} ]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				plugins: { legend: { display: false } },
				scales: {
					// Short "MM-DD" on the axis keeps labels from colliding on narrow screens;
					// the tooltip title still shows the full date.
					x: {
						grid: { display: false },
						border: { display: false },
						ticks: {
							maxTicksLimit: 6,
							maxRotation: 0,
							callback: function ( value ) {
								var label = String( this.getLabelForValue( value ) );
								return label.length === 10 ? label.slice( 5 ) : label;
							}
						}
					},
					y: { beginAtZero: true, border: { display: false }, grid: { color: GRID }, ticks: { precision: 0 } }
				}
			}
		} );
	}

	function drawChannels( canvas ) {
		var rows = wpsChartsData.channels;

		if ( ! rows || ! rows.length ) {
			showEmpty( canvas );
			return;
		}

		new Chart( canvas, {
			type: 'bar',
			data: {
				labels: rows.map( function ( row ) { return row.label; } ),
				datasets: [ {
					label: 'بازدید',
					data: rows.map( function ( row ) { return row.views; } ),
					backgroundColor: rows.map( function ( row ) { return row.color; } ),
					borderRadius: 4,
					borderSkipped: false,
					maxBarThickness: 22
				} ]
			},
			options: {
				indexAxis: 'y',
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { display: false } },
				scales: {
					x: { beginAtZero: true, border: { display: false }, grid: { color: GRID }, ticks: { precision: 0 } },
					y: { grid: { display: false }, border: { display: false } }
				}
			}
		} );
	}

	function drawDevices( canvas ) {
		var rows = wpsChartsData.devices;

		if ( ! rows || ! rows.length ) {
			showEmpty( canvas );
			return;
		}

		new Chart( canvas, {
			type: 'doughnut',
			data: {
				labels: rows.map( function ( row ) { return row.label; } ),
				datasets: [ {
					data: rows.map( function ( row ) { return row.visitors; } ),
					backgroundColor: rows.map( function ( row, i ) { return DEVICE_COLORS[ i % DEVICE_COLORS.length ]; } ),
					borderColor: '#fff',
					borderWidth: 2
				} ]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				cutout: '68%',
				plugins: {
					legend: {
						position: 'bottom',
						rtl: true,
						textDirection: 'rtl',
						labels: { usePointStyle: true, boxWidth: 8, padding: 16 }
					}
				}
			}
		} );
	}

	function init() {
		var views = document.getElementById( 'wps-chart-views' );
		var channels = document.getElementById( 'wps-chart-channels' );
		var devices = document.getElementById( 'wps-chart-devices' );

		if ( views ) { drawViews( views ); }
		if ( channels ) { drawChannels( channels ); }
		if ( devices ) { drawDevices( devices ); }
	}

	// Canvas text only uses the bundled font once it has loaded, so wait for it
	// (falling through to the fallback stack if loading fails).
	if ( document.fonts && document.fonts.load ) {
		document.fonts.load( '500 12px "WPS Vazirmatn"' ).then( init, init );
	} else {
		init();
	}
} )();

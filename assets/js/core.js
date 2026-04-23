(function () {
	'use strict';

	window.UmamiCharts = {
		modules: [],
		register: function (module) {
			this.modules.push(module);
		},
		getModules: function () {
			return this.modules.sort(function (a, b) { return a.position - b.position; });
		}
	};
})();

jQuery(document).ready(function ($) {
	'use strict';

	var H = window.UmamiHelpers;
	var currentRange = '24h';
	var charts = {};
	var currentAjax = null;

	// Range Selector
	$('.umami-range-btn').on('click', function () {
		$('.umami-range-btn').removeClass('active');
		$(this).addClass('active');
		currentRange = $(this).data('range');
		loadStats();
	});

	function destroyAllCharts() {
		Object.keys(charts).forEach(function (key) {
			if (charts[key] && typeof charts[key].destroy === 'function') {
				try { charts[key].destroy(); } catch (e) {}
			}
		});
		charts = {};
	}

	function loadStats() {
		if (currentAjax) {
			currentAjax.abort();
		}

		$('#umami-stats-container').html(
			'<div class="umami-loading">' +
			'<span class="spinner is-active"></span>' +
			'<p>Lade Statistiken...</p>' +
			'</div>'
		);

		destroyAllCharts();

		currentAjax = $.ajax({
			url: umamiData.ajaxurl,
			type: 'POST',
			data: {
				action: 'umami_get_stats',
				range: currentRange,
				nonce: umamiData.nonce
			},
			success: function (response) {
				currentAjax = null;
				if (response.success) {
					displayStats(response.data);
				} else {
					$('#umami-stats-container').html(
						'<div class="umami-error">' +
						'<span class="dashicons dashicons-warning"></span>' +
						'<p>' + (response.data || 'Fehler beim Laden der Statistiken') + '</p>' +
						'</div>'
					);
				}
			},
			error: function (xhr) {
				currentAjax = null;
				if (xhr.statusText !== 'abort') {
					$('#umami-stats-container').html(
						'<div class="umami-error">' +
						'<span class="dashicons dashicons-warning"></span>' +
						'<p>Verbindungsfehler zur Umami API</p>' +
						'</div>'
					);
				}
			}
		});
	}

	function displayStats(data) {
		var html = '';
		var modules = UmamiCharts.getModules();

		// Metriken Header
		var visitors = data.visitors || 0;
		var pageviews = data.pageviews || 0;
		var visits = data.visits || 0;
		var bounces = data.bounces || 0;
		var totaltime = data.totaltime || 0;
		var bounceRate = visits > 0 ? (bounces / visits * 100) : 0;
		var avgDuration = visits > 0 ? Math.round(totaltime / visits) : 0;

		html += '<div class="umami-metrics-header">';
		html += '<div class="umami-metric-card"><div class="umami-metric-label">Besucher</div><div class="umami-metric-value">' + visitors.toLocaleString() + '</div></div>';
		html += '<div class="umami-metric-card"><div class="umami-metric-label">Aufrufe</div><div class="umami-metric-value">' + pageviews.toLocaleString() + '</div></div>';
		html += '<div class="umami-metric-card"><div class="umami-metric-label">Besuche</div><div class="umami-metric-value">' + visits.toLocaleString() + '</div></div>';
		html += '<div class="umami-metric-card"><div class="umami-metric-label">Absprungrate</div><div class="umami-metric-value">' + bounceRate.toFixed(0) + '%</div></div>';
		html += '<div class="umami-metric-card"><div class="umami-metric-label">Ø Besuchsdauer</div><div class="umami-metric-value">' + H.formatDuration(avgDuration) + '</div></div>';
		html += '</div>';

		// Full-width modules (position < 25)
		modules.forEach(function (mod) {
			if (mod.layout === 'full' && mod.position < 25) {
				var moduleHtml = mod.render(data, charts);
				if (moduleHtml) html += moduleHtml;
			}
		});

		// Two-column layout
		var leftModules = modules.filter(function (m) { return m.layout === 'left'; });
		var rightModules = modules.filter(function (m) { return m.layout === 'right'; });

		if (leftModules.length > 0 || rightModules.length > 0) {
			html += '<div class="umami-two-column">';

			html += '<div class="umami-column">';
			leftModules.forEach(function (mod) {
				var moduleHtml = mod.render(data, charts);
				if (moduleHtml) html += moduleHtml;
			});
			html += '</div>';

			html += '<div class="umami-column">';
			rightModules.forEach(function (mod) {
				var moduleHtml = mod.render(data, charts);
				if (moduleHtml) html += moduleHtml;
			});
			html += '</div>';

			html += '</div>';
		}

		// Full-width modules (position >= 75)
		modules.forEach(function (mod) {
			if (mod.layout === 'full' && mod.position >= 75) {
				var moduleHtml = mod.render(data, charts);
				if (moduleHtml) html += moduleHtml;
			}
		});

		$('#umami-stats-container').html(html);

		// Warte auf Chart.js, dann initialisiere Module
		function waitForChart(callback) {
			if (typeof Chart !== 'undefined') {
				callback();
			} else {
				setTimeout(function () { waitForChart(callback); }, 100);
			}
		}

		waitForChart(function () {
			modules.forEach(function (mod) {
				if (typeof mod.initInteractions === 'function') {
					setTimeout(function () {
						mod.initInteractions(data, charts);
					}, 100);
				}
			});
		});
	}

	// Expose charts object for modules
	window.UmamiChartsInstances = charts;

	loadStats();
});

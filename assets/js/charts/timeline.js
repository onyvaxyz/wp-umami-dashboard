(function ($) {
	'use strict';
	var H = window.UmamiHelpers;

	UmamiCharts.register({
		id: 'timeline',
		position: 10,
		layout: 'full',

		render: function (data) {
			if (!data.timeline || !data.timeline.pageviews || !data.timeline.sessions) {
				return '';
			}
			return '<div class="umami-timeline-chart"><canvas id="umami-timeline-chart"></canvas></div>';
		},

		initInteractions: function (data, charts) {
			if (!data.timeline || !data.timeline.pageviews || !data.timeline.sessions) return;

			var canvas = document.getElementById('umami-timeline-chart');
			if (!canvas || typeof Chart === 'undefined') return;

			if (charts.timeline) {
				try { charts.timeline.destroy(); } catch (e) {}
			}

			var gradient1 = umamiData.gradientStart;
			var hsl = H.rgbToHsl(gradient1);
			var color1 = H.hslToRgb(hsl.h, hsl.s, hsl.l);
			var color2 = H.hslToRgb((hsl.h + 40) % 360, Math.max(60, hsl.s - 10), Math.min(65, hsl.l + 10));

			var labels = data.timeline.pageviews.map(function (item) { return new Date(item.x); });
			var pageviewsData = data.timeline.pageviews.map(function (item) { return item.y; });
			var sessionsData = data.timeline.sessions.map(function (item) { return item.y; });

			charts.timeline = new Chart(canvas, {
				type: 'line',
				data: {
					labels: labels,
					datasets: [
						{
							label: 'Besucher',
							data: sessionsData,
							borderColor: color1,
							backgroundColor: H.hexToRgba(color1, 0.1),
							borderWidth: 3,
							fill: true,
							tension: 0.4,
							pointRadius: 3,
							pointHoverRadius: 5,
							pointBackgroundColor: color1,
							pointBorderColor: '#fff',
							pointBorderWidth: 2
						},
						{
							label: 'Aufrufe',
							data: pageviewsData,
							borderColor: color2,
							backgroundColor: H.hexToRgba(color2, 0.1),
							borderWidth: 3,
							fill: true,
							tension: 0.4,
							pointRadius: 3,
							pointHoverRadius: 5,
							pointBackgroundColor: color2,
							pointBorderColor: '#fff',
							pointBorderWidth: 2
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					interaction: { mode: 'index', intersect: false },
					plugins: {
						legend: {
							display: true,
							position: 'top',
							align: 'end',
							labels: { usePointStyle: true, padding: 15, font: { size: 12, weight: '500' } }
						},
						tooltip: {
							backgroundColor: 'rgba(0, 0, 0, 0.8)',
							padding: 12,
							titleFont: { size: 13, weight: 'bold' },
							bodyFont: { size: 12 },
							bodySpacing: 6,
							callbacks: {
								title: function (context) {
									var date = context[0].parsed.x;
									return new Date(date).toLocaleDateString('de-CH', {
										day: '2-digit', month: 'short', year: 'numeric',
										hour: context[0].dataset.data.length > 48 ? undefined : '2-digit'
									});
								},
								label: function (context) {
									return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
								}
							}
						}
					},
					scales: {
						x: {
							type: 'time',
							time: { displayFormats: { hour: 'HH:mm', day: 'dd MMM', month: 'MMM yyyy' } },
							grid: { display: false },
							ticks: { maxRotation: 0, autoSkipPadding: 20, font: { size: 11 } }
						},
						y: {
							beginAtZero: true,
							grid: { color: '#f0f0f1' },
							ticks: { font: { size: 11 } }
						}
					}
				}
			});
		}
	});
})(jQuery);

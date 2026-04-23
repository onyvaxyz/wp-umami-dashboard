(function ($) {
	'use strict';
	var H = window.UmamiHelpers;

	UmamiCharts.register({
		id: 'pages',
		position: 50,
		layout: 'right',
		dataKey: 'urls',

		render: function (data) {
			var items = data.urls;
			if (!items || !Array.isArray(items) || items.length === 0) return '';

			var topPages = items.slice(0, 10);
			var totalPageviews = topPages.reduce(function (sum, page) { return sum + page.y; }, 0);

			var html = '<div class="umami-section" id="umami-section-pages">';
			html += '<div class="umami-section-header">';
			html += '<h3><i class="bi bi-file-earmark-text"></i> Seiten</h3>';
			html += '</div>';
			html += '<div class="umami-section-content">';
			html += '<div class="umami-pages-list">';

			topPages.forEach(function (page) {
				var percentage = totalPageviews > 0 ? ((page.y / totalPageviews) * 100).toFixed(0) : 0;
				html += '<div class="umami-page-item">';
				html += '<div class="umami-page-path">' + H.escapeHtml(page.x) + '</div>';
				html += '<div class="umami-page-stats">';
				html += '<span class="umami-page-count">' + page.y.toLocaleString() + '</span>';
				html += '<span class="umami-page-percent">' + percentage + '%</span>';
				html += '</div></div>';
			});

			html += '</div></div></div>';
			return html;
		}
	});
})(jQuery);

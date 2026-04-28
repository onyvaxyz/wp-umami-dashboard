(function ($) {
	'use strict';
	var H = window.UmamiHelpers;

	UmamiCharts.register({
		id: 'sources',
		position: 30,
		layout: 'left',
		dataKey: 'referrers',

		render: function (data) {
			var items = data.referrers;
			if (!items || !Array.isArray(items) || items.length === 0) return '';

			var html = '<div class="umami-section" id="umami-section-sources">';
			html += '<div class="umami-section-header">';
			html += '<h3>' + H.icon('link-45deg') + ' Quellen</h3>';
			html += '</div>';
			html += '<div class="umami-section-content">';
			html += this._renderList(items);
			html += '</div></div>';
			return html;
		},

		_renderList: function (items) {
			var html = '<div class="umami-list">';
			var maxValue = Math.max.apply(null, items.slice(0, 10).map(function (i) { return i.y; }));

			items.slice(0, 10).forEach(function (item) {
				var percentage = maxValue > 0 ? (item.y / maxValue) * 100 : 0;
				var domain = item.x || '(Direkt)';

				html += '<div class="umami-list-item"><div class="umami-list-header"><span class="umami-list-label">';
				if (H.isValidDomain(domain)) {
					// Favicon wird serverseitig über den WP-Proxy geladen (DSGVO-konform,
					// damit die Admin-IP nicht an Google übertragen wird).
					var faviconUrl = umamiData.ajaxurl + '?action=umami_favicon&domain=' + encodeURIComponent(domain);
					var referrerUrl = 'https://' + encodeURIComponent(domain);
					html += '<img src="' + faviconUrl + '" class="umami-favicon" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.style.display=\'none\'">';
					html += '<a href="' + referrerUrl + '" target="_blank" rel="noopener noreferrer" class="umami-referrer-link">' + H.escapeHtml(domain) + '</a>';
				} else {
					html += H.escapeHtml(domain);
				}
				html += '</span><span class="umami-list-value">' + item.y.toLocaleString() + '</span></div>';
				html += '<div class="umami-progress-bar"><div class="umami-progress-fill" style="width: ' + percentage + '%"></div></div></div>';
			});

			html += '</div>';
			return html;
		}
	});
})(jQuery);

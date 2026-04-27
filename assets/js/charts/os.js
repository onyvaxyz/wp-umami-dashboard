(function ($) {
	'use strict';
	var H = window.UmamiHelpers;

	UmamiCharts.register({
		id: 'os',
		position: 70,
		layout: 'right',
		dataKey: 'os',

		render: function (data) {
			var items = data.os;
			if (!items || !Array.isArray(items) || items.length === 0) return '';

			var html = '<div class="umami-section" id="umami-section-os">';
			html += '<div class="umami-section-header">';
			html += '<h3>' + H.icon('display') + ' Betriebssysteme</h3>';
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
				html += '<div class="umami-list-item"><div class="umami-list-header"><span class="umami-list-label">';
				html += H.icon(H.getOSIcon(item.x), 'umami-list-icon');
				html += H.escapeHtml(item.x);
				html += '</span><span class="umami-list-value">' + item.y.toLocaleString() + '</span></div>';
				html += '<div class="umami-progress-bar"><div class="umami-progress-fill" style="width: ' + percentage + '%"></div></div></div>';
			});

			html += '</div>';
			return html;
		}
	});
})(jQuery);

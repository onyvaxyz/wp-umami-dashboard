(function ($) {
	'use strict';
	var H = window.UmamiHelpers;

	UmamiCharts.register({
		id: 'devices',
		position: 20,
		layout: 'full',

		render: function (data) {
			if (!data.devices || !Array.isArray(data.devices) || data.devices.length === 0) {
				return '';
			}

			var totalDevices = data.devices.reduce(function (sum, d) { return sum + d.y; }, 0);
			var html = '<div class="umami-device-cards">';

			data.devices.forEach(function (device) {
				var percentage = totalDevices > 0 ? ((device.y / totalDevices) * 100).toFixed(1) : 0;
				var icon = H.getDeviceIcon(device.x);
				var deviceClass = device.x.toLowerCase().replace(' ', '-');

				html += '<div class="umami-device-card ' + deviceClass + '">';
				html += '<div class="umami-device-icon">' + icon + '</div>';
				html += '<div class="umami-device-info">';
				html += '<div class="umami-device-label">' + H.escapeHtml(device.x) + '</div>';
				html += '<div class="umami-device-stats">';
				html += '<span class="umami-device-count">' + device.y.toLocaleString() + '</span>';
				html += '<span class="umami-device-percentage">' + percentage + '%</span>';
				html += '</div></div>';
				html += '<div class="umami-device-bar"><div class="umami-device-bar-fill" style="width: ' + percentage + '%"></div></div>';
				html += '</div>';
			});

			html += '</div>';
			return html;
		}
	});
})(jQuery);

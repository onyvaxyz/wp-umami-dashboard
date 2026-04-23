(function () {
	'use strict';

	window.UmamiHelpers = {

		escapeHtml: function (text) {
			var map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
		},

		formatDuration: function (seconds) {
			if (seconds < 60) return seconds + 's';
			var minutes = Math.floor(seconds / 60);
			var secs = seconds % 60;
			return minutes + 'm ' + secs + 's';
		},

		rgbToHsl: function (hex) {
			hex = hex.replace('#', '');
			var r = parseInt(hex.substr(0, 2), 16) / 255;
			var g = parseInt(hex.substr(2, 2), 16) / 255;
			var b = parseInt(hex.substr(4, 2), 16) / 255;

			var max = Math.max(r, g, b);
			var min = Math.min(r, g, b);
			var h, s, l = (max + min) / 2;

			if (max === min) {
				h = s = 0;
			} else {
				var d = max - min;
				s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
				switch (max) {
					case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
					case g: h = ((b - r) / d + 2) / 6; break;
					case b: h = ((r - g) / d + 4) / 6; break;
				}
			}

			return {
				h: Math.round(h * 360),
				s: Math.round(s * 100),
				l: Math.round(l * 100)
			};
		},

		hslToRgb: function (h, s, l) {
			h = h / 360;
			s = s / 100;
			l = l / 100;
			var r, g, b;

			if (s === 0) {
				r = g = b = l;
			} else {
				var hue2rgb = function (p, q, t) {
					if (t < 0) t += 1;
					if (t > 1) t -= 1;
					if (t < 1 / 6) return p + (q - p) * 6 * t;
					if (t < 1 / 2) return q;
					if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
					return p;
				};
				var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
				var p = 2 * l - q;
				r = hue2rgb(p, q, h + 1 / 3);
				g = hue2rgb(p, q, h);
				b = hue2rgb(p, q, h - 1 / 3);
			}

			return 'rgb(' + Math.round(r * 255) + ', ' + Math.round(g * 255) + ', ' + Math.round(b * 255) + ')';
		},

		hexToRgba: function (color, alpha) {
			if (color.startsWith('rgb')) {
				return color.replace('rgb', 'rgba').replace(')', ', ' + alpha + ')');
			}
			var hex = color.replace('#', '');
			var r = parseInt(hex.substr(0, 2), 16);
			var g = parseInt(hex.substr(2, 2), 16);
			var b = parseInt(hex.substr(4, 2), 16);
			return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
		},

		generateColorHarmony: function (count, baseColor) {
			var hsl = this.rgbToHsl(baseColor);
			var colors = [];

			if (count <= 1) {
				return [baseColor];
			}

			if (count === 2) {
				colors = [
					this.hslToRgb(hsl.h, hsl.s, hsl.l),
					this.hslToRgb((hsl.h + 180) % 360, hsl.s, hsl.l)
				];
			} else if (count === 3) {
				colors = [
					this.hslToRgb(hsl.h, hsl.s, hsl.l),
					this.hslToRgb((hsl.h + 120) % 360, hsl.s, hsl.l),
					this.hslToRgb((hsl.h + 240) % 360, hsl.s, hsl.l)
				];
			} else if (count === 4) {
				colors = [
					this.hslToRgb(hsl.h, hsl.s, hsl.l),
					this.hslToRgb((hsl.h + 90) % 360, hsl.s, hsl.l),
					this.hslToRgb((hsl.h + 180) % 360, hsl.s, hsl.l),
					this.hslToRgb((hsl.h + 270) % 360, hsl.s, hsl.l)
				];
			} else if (count <= 6) {
				var angle = 360 / count;
				for (var i = 0; i < count; i++) {
					colors.push(this.hslToRgb((hsl.h + (angle * i)) % 360, hsl.s, hsl.l));
				}
			} else {
				var baseAngles = [0, 90, 180, 270];
				var lightnessVariations = [0, -15, 15];
				for (var i = 0; i < count; i++) {
					var angleIndex = i % baseAngles.length;
					var lightIndex = Math.floor(i / baseAngles.length) % lightnessVariations.length;
					var newHue = (hsl.h + baseAngles[angleIndex]) % 360;
					var newLight = Math.max(30, Math.min(70, hsl.l + lightnessVariations[lightIndex]));
					colors.push(this.hslToRgb(newHue, hsl.s, newLight));
				}
			}

			return colors.slice(0, count);
		},

		isValidDomain: function (domain) {
			if (!domain || domain === '(Direkt)') {
				return false;
			}
			return /^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.[a-zA-Z]{2,}$/.test(domain);
		},

		getCountryFlag: function (code) {
			var flags = {
				'CH': '🇨🇭', 'DE': '🇩🇪', 'AT': '🇦🇹', 'FR': '🇫🇷', 'IT': '🇮🇹',
				'US': '🇺🇸', 'GB': '🇬🇧', 'NL': '🇳🇱', 'BE': '🇧🇪', 'ES': '🇪🇸',
				'PT': '🇵🇹', 'SE': '🇸🇪', 'NO': '🇳🇴', 'DK': '🇩🇰', 'FI': '🇫🇮',
				'PL': '🇵🇱', 'CZ': '🇨🇿', 'RO': '🇷🇴', 'GR': '🇬🇷', 'HU': '🇭🇺',
				'IE': '🇮🇪', 'SK': '🇸🇰', 'BG': '🇧🇬', 'HR': '🇭🇷', 'SI': '🇸🇮'
			};
			return flags[code] || '🌍';
		},

		getCountryName: function (code) {
			var countries = {
				'CH': 'Schweiz', 'DE': 'Deutschland', 'AT': 'Österreich',
				'FR': 'Frankreich', 'IT': 'Italien', 'US': 'USA',
				'GB': 'Grossbritannien', 'NL': 'Niederlande', 'BE': 'Belgien',
				'ES': 'Spanien', 'PT': 'Portugal', 'SE': 'Schweden',
				'NO': 'Norwegen', 'DK': 'Dänemark', 'FI': 'Finnland',
				'PL': 'Polen', 'CZ': 'Tschechien', 'RO': 'Rumänien',
				'GR': 'Griechenland', 'HU': 'Ungarn', 'IE': 'Irland',
				'SK': 'Slowakei', 'BG': 'Bulgarien', 'HR': 'Kroatien', 'SI': 'Slowenien'
			};
			return countries[code] || code;
		},

		getBrowserIcon: function (name) {
			var icons = {
				'chrome': 'bi-browser-chrome',
				'firefox': 'bi-browser-firefox',
				'safari': 'bi-browser-safari',
				'edge': 'bi-browser-edge',
				'edge-chromium': 'bi-browser-edge',
				'opera': 'bi-browser-chrome',
				'brave': 'bi-browser-chrome',
				'ios': 'bi-phone',
				'samsung': 'bi-phone'
			};
			return icons[name.toLowerCase()] || 'bi-browser-chrome';
		},

		getOSIconClass: function (name) {
			if (name.includes('Windows')) return 'bi-windows';
			if (name.includes('Mac') || name.includes('macOS')) return 'bi-apple';
			if (name.includes('Linux')) return 'bi-ubuntu';
			if (name.includes('Android')) return 'bi-android2';
			if (name.includes('iOS')) return 'bi-apple';
			return 'bi-laptop';
		},

		getDeviceIcon: function (device) {
			var icons = {
				'desktop': '<i class="bi bi-pc-display"></i>',
				'mobile': '<i class="bi bi-phone"></i>',
				'tablet': '<i class="bi bi-tablet"></i>',
				'laptop': '<i class="bi bi-laptop"></i>'
			};
			return icons[device.toLowerCase()] || '<i class="bi bi-question-circle"></i>';
		}
	};
})();

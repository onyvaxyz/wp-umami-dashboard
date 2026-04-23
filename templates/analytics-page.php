<?php
/**
 * Template für die Analytics-Seite
 *
 * @package Jejeresources_Umami
 *
 * Verfügbare Variablen:
 * @var string $username
 * @var string $password
 * @var string $website_id
 * @var string $settings_url
 */

// Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
	exit;
}

if (empty($username) || empty($password) || empty($website_id)) {
	?>
	<div class="wrap umami-analytics-page">
		<div class="umami-analytics-setup">
			<span class="dashicons dashicons-chart-area umami-icon-xlarge"></span>
			<h2>Analytics noch nicht konfiguriert</h2>
			<p>Bitte trage deine Umami API-Zugangsdaten in den Einstellungen ein.</p>
			<a href="<?php echo esc_url($settings_url); ?>" class="button button-primary button-large">
				Zu den Einstellungen
			</a>
		</div>
	</div>
	<?php
	return;
}
?>
<div class="wrap umami-analytics-page">
	<div class="umami-header-bar">
		<h1 class="umami-page-title">
			<span class="dashicons dashicons-chart-area"></span>
			Analytics
		</h1>
		
		<div class="umami-range-selector">
			<button class="umami-range-btn active" data-range="24h">24 Stunden</button>
			<button class="umami-range-btn" data-range="7d">7 Tage</button>
			<button class="umami-range-btn" data-range="30d">30 Tage</button>
			<button class="umami-range-btn" data-range="6m">6 Monate</button>
			<button class="umami-range-btn" data-range="1y">1 Jahr</button>
		</div>
	</div>
	
	<div id="umami-stats-container">
		<div class="umami-loading">
			<span class="spinner is-active"></span>
			<p>Lade Statistiken...</p>
		</div>
	</div>
</div>

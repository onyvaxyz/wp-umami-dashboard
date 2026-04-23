<?php
/**
 * Template für die Einstellungsseite
 *
 * @package Jejeresources_Umami
 */

// Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="wrap">
	<h1>Statistiken - Einstellungen</h1>

	<form method="post" action="options.php">
		<?php
		settings_fields('umami_stats_settings');
		do_settings_sections('umami-stats-settings');
		submit_button();
		?>
	</form>

	<hr>

	<h2>Verbindung testen</h2>
	<form method="post">
		<?php wp_nonce_field('umami_test_connection'); ?>
		<p>Teste die Verbindung zu deinem Umami-Server.</p>
		<button type="submit" name="umami_test_connection" class="button">Verbindung testen</button>
	</form>
</div>

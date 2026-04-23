<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Jejeresources_Umami_Dashboard_Widget {

	/**
	 * Fügt Dashboard Widget hinzu
	 */
	public function add_dashboard_widget() {
		if ( ! Jejeresources_Umami_Permissions::user_can_view_stats() ) {
			return;
		}

		wp_add_dashboard_widget(
			'umami_stats_widget',
			'Website Statistiken',
			array( $this, 'display_dashboard_widget' )
		);
	}

	/**
	 * Zeigt das Dashboard Widget
	 */
	public function display_dashboard_widget() {
		$username     = get_option( 'umami_username', '' );
		$password     = get_option( 'umami_password', '' );
		$website_id   = get_option( 'umami_website_id', '' );
		$settings_url = admin_url( 'options-general.php?page=umami-stats-settings' );
		$analytics_url = admin_url( 'admin.php?page=umami-analytics' );

		?>
		<div class="umami-widget-content">
			<?php if ( empty( $username ) || empty( $password ) || empty( $website_id ) ) : ?>
				<div class="umami-widget-setup">
					<span class="dashicons dashicons-chart-area umami-icon-large"></span>
					<p class="umami-widget-text">Noch nicht konfiguriert</p>
					<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary">
						Einstellungen öffnen
					</a>
				</div>
			<?php else : ?>
				<div class="umami-widget-main">
					<div class="umami-widget-icon">
						<span class="dashicons dashicons-chart-area"></span>
					</div>
					<div class="umami-widget-info">
						<p class="umami-widget-description">
							Schau dir deine Website-Statistiken und Besucheranalysen an
						</p>
					</div>
					<div class="umami-widget-actions">
						<a href="<?php echo esc_url( $analytics_url ); ?>" 
						   class="umami-stats-button">
							<span class="dashicons dashicons-chart-area"></span>
							Statistiken öffnen
						</a>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}

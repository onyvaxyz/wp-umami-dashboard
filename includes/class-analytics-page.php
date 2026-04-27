<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Jejeresources_Umami_Analytics_Page {

	/**
	 * @var Jejeresources_Umami_Api_Client
	 */
	private $api_client;

	/**
	 * @var string
	 */
	private $hook_suffix = '';

	public function __construct( Jejeresources_Umami_Api_Client $api_client ) {
		$this->api_client = $api_client;
	}

	/**
	 * Gibt den hook_suffix der Analytics-Seite zurück
	 */
	public function get_hook_suffix() {
		return $this->hook_suffix;
	}

	/**
	 * Fügt Analytics Menüpunkt hinzu
	 */
	public function add_analytics_page() {
		if ( ! Jejeresources_Umami_Permissions::user_can_view_stats() ) {
			return;
		}

		$this->hook_suffix = add_menu_page(
			'Analytics',
			'Analytics',
			'read',
			'umami-analytics',
			array( $this, 'display_analytics_page' ),
			'dashicons-chart-area',
			3
		);
	}

	/**
	 * Zeigt die Analytics Seite mit API-Daten
	 */
	public function display_analytics_page() {
		$username     = get_option( 'umami_username', '' );
		$password     = get_option( 'umami_password', '' );
		$website_id   = get_option( 'umami_website_id', '' );
		$settings_url = admin_url( 'options-general.php?page=umami-stats-settings' );

		if ( empty( $username ) || empty( $password ) || empty( $website_id ) ) {
			?>
			<div class="wrap umami-analytics-page">
				<div class="umami-analytics-setup">
					<span class="dashicons dashicons-chart-area umami-icon-xlarge"></span>
					<h2>Analytics noch nicht konfiguriert</h2>
					<p>Bitte trage deine Umami API-Zugangsdaten in den Einstellungen ein.</p>
					<a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary button-large">
						Zu den Einstellungen
					</a>
				</div>
			</div>
			<?php
			return;
		}

		$gradient_start = get_option( 'umami_gradient_start', '#667eea' );
		$gradient_end   = get_option( 'umami_gradient_end', '#764ba2' );
		if ( ! preg_match( '/^#[a-fA-F0-9]{6}$/', $gradient_start ) ) {
			$gradient_start = '#667eea';
		}
		if ( ! preg_match( '/^#[a-fA-F0-9]{6}$/', $gradient_end ) ) {
			$gradient_end = '#764ba2';
		}

		?>
		<div class="wrap umami-analytics-page">
			<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
				<defs>
					<linearGradient id="umami-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
						<stop offset="0%" stop-color="<?php echo esc_attr( $gradient_start ); ?>" />
						<stop offset="100%" stop-color="<?php echo esc_attr( $gradient_end ); ?>" />
					</linearGradient>
				</defs>
			</svg>
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

			<?php $this->render_footer(); ?>
		</div>
		<?php
	}

	/**
	 * Rendert den optionalen Footer
	 */
	private function render_footer() {
		$logo_id     = get_option( 'umami_footer_logo', 0 );
		$logo_url    = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
		$text        = wp_kses_post( get_option( 'umami_footer_text', '' ) );
		$button_text = sanitize_text_field( get_option( 'umami_footer_button_text', '' ) );
		$button_url  = esc_url( get_option( 'umami_footer_button_url', '' ) );

		if ( empty( $logo_url ) && empty( $text ) && empty( $button_text ) ) {
			return;
		}

		?>
		<div class="umami-footer">
			<div class="umami-footer-brand">
				<?php if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="Logo" class="umami-footer-logo">
				<?php endif; ?>
				<?php if ( $text ) : ?>
					<div class="umami-footer-text"><?php echo $text; ?></div>
				<?php endif; ?>
			</div>
			<?php if ( $button_text && $button_url ) : ?>
				<a href="<?php echo esc_url( $button_url ); ?>" target="_blank" rel="noopener noreferrer" class="umami-footer-button">
					<span class="dashicons dashicons-external"></span>
					<?php echo esc_html( $button_text ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * AJAX Handler für Stats - MIT NONCE-PRÜFUNG
	 */
	public function ajax_get_stats() {
		// SICHERHEIT: Nonce prüfen (CSRF-Schutz)
		check_ajax_referer( 'umami_stats_nonce', 'nonce' );

		if ( ! Jejeresources_Umami_Permissions::user_can_view_stats() ) {
			wp_send_json_error( 'Keine Berechtigung' );
			return;
		}

		$range = isset( $_POST['range'] ) ? sanitize_text_field( $_POST['range'] ) : '24h';
		$stats = $this->api_client->get_umami_stats( $range );

		if ( $stats ) {
			wp_send_json_success( $stats );
		} else {
			wp_send_json_error( 'Fehler beim Abrufen der Statistiken' );
		}
	}
}

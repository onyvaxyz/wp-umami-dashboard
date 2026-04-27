<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Jejeresources_Umami_Plugin {

	/**
	 * @var Jejeresources_Umami_Api_Client
	 */
	private $api_client;

	/**
	 * @var Jejeresources_Umami_Settings
	 */
	private $settings;

	/**
	 * @var Jejeresources_Umami_Analytics_Page
	 */
	private $analytics_page;

	/**
	 * @var Jejeresources_Umami_Dashboard_Widget
	 */
	private $dashboard_widget;

	public function __construct() {
		$this->api_client       = new Jejeresources_Umami_Api_Client();
		$this->settings         = new Jejeresources_Umami_Settings( $this->api_client );
		$this->analytics_page   = new Jejeresources_Umami_Analytics_Page( $this->api_client );
		$this->dashboard_widget = new Jejeresources_Umami_Dashboard_Widget();

		$this->register_hooks();
	}

	/**
	 * Registriert alle WordPress Hooks
	 */
	private function register_hooks() {
		add_action( 'admin_menu', array( $this->settings, 'add_settings_page' ) );
		add_action( 'admin_menu', array( $this->analytics_page, 'add_analytics_page' ) );
		add_action( 'admin_init', array( $this->settings, 'register_settings' ) );
		add_action( 'wp_dashboard_setup', array( $this->dashboard_widget, 'add_dashboard_widget' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_umami_get_stats', array( $this->analytics_page, 'ajax_get_stats' ) );
	}

	/**
	 * Lädt CSS und JS für Dashboard Widget & Analytics Seite
	 */
	public function enqueue_assets( $hook ) {
		$analytics_hook = $this->analytics_page->get_hook_suffix();

		// Media Uploader auf der Settings-Seite laden
		if ( $hook === 'settings_page_umami-stats-settings' ) {
			wp_enqueue_media();
		}

		// Widget CSS immer im Admin laden
		wp_enqueue_style(
			'umami-widget',
			JEJERESOURCES_UMAMI_PLUGIN_URL . 'assets/css/widget.css',
			array(),
			JEJERESOURCES_UMAMI_VERSION
		);

		// Analytics CSS & JS nur auf der Analytics-Seite
		if ( $analytics_hook && $hook === $analytics_hook ) {
			wp_enqueue_style(
				'umami-analytics',
				JEJERESOURCES_UMAMI_PLUGIN_URL . 'assets/css/analytics.css',
				array(),
				JEJERESOURCES_UMAMI_VERSION
			);

			// Lokal gebündelte Vendor-Ressourcen (eigener Handle-Prefix vermeidet Konflikte mit anderen Plugins)
			wp_enqueue_script(
				'umami-chartjs',
				JEJERESOURCES_UMAMI_PLUGIN_URL . 'assets/vendor/chartjs/chart.umd.min.js',
				array(),
				'4.4.0',
				true
			);

			wp_enqueue_script(
				'umami-chartjs-adapter-date-fns',
				JEJERESOURCES_UMAMI_PLUGIN_URL . 'assets/vendor/chartjs/chartjs-adapter-date-fns.bundle.min.js',
				array( 'umami-chartjs' ),
				'3.0.0',
				true
			);

			// Helpers JS
			wp_enqueue_script(
				'umami-helpers',
				JEJERESOURCES_UMAMI_PLUGIN_URL . 'assets/js/helpers.js',
				array( 'jquery' ),
				JEJERESOURCES_UMAMI_VERSION,
				true
			);

			// Core JS
			wp_enqueue_script(
				'umami-core',
				JEJERESOURCES_UMAMI_PLUGIN_URL . 'assets/js/core.js',
				array( 'jquery', 'umami-chartjs', 'umami-chartjs-adapter-date-fns', 'umami-helpers' ),
				JEJERESOURCES_UMAMI_VERSION,
				true
			);

			// Auto-discover chart modules
			$charts_dir = JEJERESOURCES_UMAMI_PLUGIN_DIR . 'assets/js/charts/';
			if ( is_dir( $charts_dir ) ) {
				$chart_files = glob( $charts_dir . '*.js' );
				if ( $chart_files ) {
					foreach ( $chart_files as $chart_file ) {
						$filename = basename( $chart_file, '.js' );
						wp_enqueue_script(
							'umami-chart-' . $filename,
							JEJERESOURCES_UMAMI_PLUGIN_URL . 'assets/js/charts/' . basename( $chart_file ),
							array( 'umami-core' ),
							JEJERESOURCES_UMAMI_VERSION,
							true
						);
					}
				}
			}

			$gradient_start    = get_option( 'umami_gradient_start', '#667eea' );
			$gradient_end      = get_option( 'umami_gradient_end', '#764ba2' );
			$button_text_color = get_option( 'umami_button_text_color', '#ffffff' );

			// Localize script data
			wp_localize_script( 'umami-core', 'umamiData', array(
				'ajaxurl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'umami_stats_nonce' ),
				'gradientStart'   => $gradient_start,
				'gradientEnd'     => $gradient_end,
				'buttonTextColor' => $button_text_color,
			) );
		}

		// Dynamische CSS Custom Properties
		$gradient_start    = get_option( 'umami_gradient_start', '#667eea' );
		$gradient_end      = get_option( 'umami_gradient_end', '#764ba2' );
		$button_text_color = get_option( 'umami_button_text_color', '#ffffff' );

		if ( ! preg_match( '/^#[a-fA-F0-9]{6}$/', $gradient_start ) ) {
			$gradient_start = '#667eea';
		}
		if ( ! preg_match( '/^#[a-fA-F0-9]{6}$/', $gradient_end ) ) {
			$gradient_end = '#764ba2';
		}
		if ( ! preg_match( '/^#[a-fA-F0-9]{6}$/', $button_text_color ) ) {
			$button_text_color = '#ffffff';
		}

		$shadow_color = esc_attr( $this->api_client->hex_to_rgba( $gradient_start, 0.3 ) );
		$shadow_hover = esc_attr( $this->api_client->hex_to_rgba( $gradient_start, 0.4 ) );

		$inline_css = ":root {
	--umami-gradient-start: {$gradient_start};
	--umami-gradient-end: {$gradient_end};
	--umami-button-text-color: {$button_text_color};
	--umami-shadow-color: {$shadow_color};
	--umami-shadow-hover: {$shadow_hover};
}";

		wp_add_inline_style( 'umami-widget', $inline_css );
	}
}

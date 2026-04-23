<?php
/**
 * Plugin Name: WP Umami Dashboard
 * Description: Zeigt Umami Analytics Statistiken via API im WordPress Dashboard
 * Version: 1.0.0
 * Author: onyva.xyz
 * License: GPL v2 or later
 * Text Domain: wp-umami-dashboard
 * Update URI: https://github.com/jejeresources/wp-umami-dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JEJERESOURCES_UMAMI_VERSION', '1.0.0' );
define( 'JEJERESOURCES_UMAMI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JEJERESOURCES_UMAMI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JEJERESOURCES_UMAMI_PLUGIN_FILE', __FILE__ );

// Includes
require_once JEJERESOURCES_UMAMI_PLUGIN_DIR . 'includes/class-encryption.php';
require_once JEJERESOURCES_UMAMI_PLUGIN_DIR . 'includes/class-permissions.php';
require_once JEJERESOURCES_UMAMI_PLUGIN_DIR . 'includes/class-api-client.php';
require_once JEJERESOURCES_UMAMI_PLUGIN_DIR . 'includes/class-settings.php';
require_once JEJERESOURCES_UMAMI_PLUGIN_DIR . 'includes/class-analytics-page.php';
require_once JEJERESOURCES_UMAMI_PLUGIN_DIR . 'includes/class-dashboard-widget.php';
require_once JEJERESOURCES_UMAMI_PLUGIN_DIR . 'includes/class-plugin.php';

// Plugin initialisieren
new Jejeresources_Umami_Plugin();

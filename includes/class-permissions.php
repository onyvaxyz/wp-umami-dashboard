<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Jejeresources_Umami_Permissions {

	/**
	 * Prüft ob User die Stats sehen darf
	 */
	public static function user_can_view_stats() {
		$user          = wp_get_current_user();
		$allowed_roles = get_option( 'umami_allowed_roles', array( 'administrator', 'editor' ) );

		foreach ( $allowed_roles as $role ) {
			if ( in_array( $role, $user->roles ) ) {
				return true;
			}
		}

		return false;
	}
}

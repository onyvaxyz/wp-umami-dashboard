<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Jejeresources_Umami_Encryption {

	/**
	 * Verschlüsselt ein Passwort mit zufälligem IV
	 */
	public static function encrypt_password( $password ) {
		if ( empty( $password ) ) {
			return '';
		}

		$key = wp_salt( 'auth' );
		// Zufälligen IV generieren
		$iv = openssl_random_pseudo_bytes( 16 );

		$encrypted = openssl_encrypt( $password, 'AES-256-CBC', $key, 0, $iv );

		// IV vorne anhängen für späteres Entschlüsseln
		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Entschlüsselt ein Passwort
	 */
	public static function decrypt_password( $encrypted ) {
		if ( empty( $encrypted ) ) {
			return '';
		}

		$key = wp_salt( 'auth' );
		$data = base64_decode( $encrypted );

		// Die ersten 16 Bytes sind der IV
		$iv             = substr( $data, 0, 16 );
		$encrypted_data = substr( $data, 16 );

		return openssl_decrypt( $encrypted_data, 'AES-256-CBC', $key, 0, $iv );
	}
}

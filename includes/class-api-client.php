<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Jejeresources_Umami_Api_Client {

	/**
	 * @var string
	 */
	private $api_url = 'https://cockpit.digital-barrierefrei.ch/api';

	/**
	 * Holt API Token via Login
	 */
	public function get_api_token() {
		// Check cached token
		$cached_token = get_transient( 'umami_api_token' );
		if ( $cached_token ) {
			return $cached_token;
		}

		$username           = get_option( 'umami_username', '' );
		$password_encrypted = get_option( 'umami_password', '' );

		if ( empty( $username ) || empty( $password_encrypted ) ) {
			return false;
		}

		// Entschlüssele Passwort
		$password = $password_encrypted;
		if ( strpos( $password_encrypted, 'enc:' ) === 0 ) {
			$password = Jejeresources_Umami_Encryption::decrypt_password( substr( $password_encrypted, 4 ) );
		}

		$login_url = $this->api_url . '/auth/login';

		$response = wp_remote_post( $login_url, array(
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body'    => json_encode( array(
				'username' => $username,
				'password' => $password,
			) ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['token'] ) ) {
			// Cache token für 23 Stunden (läuft nach 24h ab)
			set_transient( 'umami_api_token', $data['token'], 23 * HOUR_IN_SECONDS );
			return $data['token'];
		}

		return false;
	}

	/**
	 * Holt Umami Stats via API
	 */
	public function get_umami_stats( $range = '24h' ) {
		$website_id = get_option( 'umami_website_id', '' );

		if ( empty( $website_id ) ) {
			return false;
		}

		$token = $this->get_api_token();
		if ( ! $token ) {
			return false;
		}

		// Zeitraum berechnen
		$end_at = time() * 1000;
		switch ( $range ) {
			case '7d':
				$start_at = $end_at - ( 7 * 24 * 60 * 60 * 1000 );
				break;
			case '30d':
				$start_at = $end_at - ( 30 * 24 * 60 * 60 * 1000 );
				break;
			case '6m':
				$start_at = $end_at - ( 180 * 24 * 60 * 60 * 1000 );
				break;
			case '1y':
				$start_at = $end_at - ( 365 * 24 * 60 * 60 * 1000 );
				break;
			case '24h':
			default:
				$start_at = $end_at - ( 24 * 60 * 60 * 1000 );
				break;
		}

		$headers = array(
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
		);

		$data = array();

		// 1. Stats (pageviews, visits, bounces, totaltime)
		$stats_url = $this->api_url . '/websites/' . $website_id . '/stats?startAt=' . $start_at . '&endAt=' . $end_at;
		$response  = wp_remote_get( $stats_url, array( 'headers' => $headers, 'timeout' => 15 ) );
		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( $body ) {
				$data = array_merge( $data, $body );
			}
		}

		// 1b. Pageviews Timeline (for line chart)
		// Determine unit based on range
		$range_seconds = ( $end_at - $start_at ) / 1000;
		if ( $range_seconds <= 48 * 3600 ) { // <= 48 hours
			$unit = 'hour';
		} elseif ( $range_seconds <= 90 * 86400 ) { // <= 90 days
			$unit = 'day';
		} else {
			$unit = 'month';
		}

		$pageviews_url = $this->api_url . '/websites/' . $website_id . '/pageviews?startAt=' . $start_at . '&endAt=' . $end_at . '&unit=' . $unit;
		$response      = wp_remote_get( $pageviews_url, array( 'headers' => $headers, 'timeout' => 15 ) );
		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( $body && is_array( $body ) && ! isset( $body['error'] ) ) {
				$data['timeline'] = $body;
			}
		}

		// 2. URLs (Top Pages) - Use type=path according to Umami API docs
		$urls_url = $this->api_url . '/websites/' . $website_id . '/metrics?startAt=' . $start_at . '&endAt=' . $end_at . '&type=path';
		$response = wp_remote_get( $urls_url, array( 'headers' => $headers, 'timeout' => 15 ) );
		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			// Only set if it's an array (not an error object)
			if ( $body && is_array( $body ) && ! isset( $body['error'] ) ) {
				$data['urls'] = $body;
			}
		}

		// 3. Referrers (Sources)
		$referrer_url = $this->api_url . '/websites/' . $website_id . '/metrics?startAt=' . $start_at . '&endAt=' . $end_at . '&type=referrer';
		$response     = wp_remote_get( $referrer_url, array( 'headers' => $headers, 'timeout' => 15 ) );
		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( $body ) {
				$data['referrers'] = $body;
			}
		}

		// 4. Browsers
		$browser_url = $this->api_url . '/websites/' . $website_id . '/metrics?startAt=' . $start_at . '&endAt=' . $end_at . '&type=browser';
		$response    = wp_remote_get( $browser_url, array( 'headers' => $headers, 'timeout' => 15 ) );
		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( $body ) {
				$data['browsers'] = $body;
			}
		}

		// 5. OS
		$os_url   = $this->api_url . '/websites/' . $website_id . '/metrics?startAt=' . $start_at . '&endAt=' . $end_at . '&type=os';
		$response = wp_remote_get( $os_url, array( 'headers' => $headers, 'timeout' => 15 ) );
		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( $body ) {
				$data['os'] = $body;
			}
		}

		// 6. Devices
		$device_url = $this->api_url . '/websites/' . $website_id . '/metrics?startAt=' . $start_at . '&endAt=' . $end_at . '&type=device';
		$response   = wp_remote_get( $device_url, array( 'headers' => $headers, 'timeout' => 15 ) );
		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( $body ) {
				$data['devices'] = $body;
			}
		}

		// 7. Countries
		$country_url = $this->api_url . '/websites/' . $website_id . '/metrics?startAt=' . $start_at . '&endAt=' . $end_at . '&type=country';
		$response    = wp_remote_get( $country_url, array( 'headers' => $headers, 'timeout' => 15 ) );
		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( $body ) {
				$data['countries'] = $body;
			}
		}

		// 8. Events
		$events_url = $this->api_url . '/websites/' . $website_id . '/metrics?startAt=' . $start_at . '&endAt=' . $end_at . '&type=event';
		$response   = wp_remote_get( $events_url, array( 'headers' => $headers, 'timeout' => 15 ) );
		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( $body ) {
				$data['events'] = $body;
			}
		}

		return $data;
	}

	/**
	 * Konvertiert HEX zu RGBA
	 */
	public function hex_to_rgba( $hex, $alpha ) {
		$hex = str_replace( '#', '', $hex );

		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		return "rgba({$r}, {$g}, {$b}, {$alpha})";
	}

	/**
	 * Gibt die API-URL zurück
	 */
	public function get_api_url() {
		return $this->api_url;
	}
}

<?php
defined( 'ABSPATH' ) || exit;

/**
 * Cliente HTTP Graph API (Meta). Sin loguear tokens.
 */
final class Vitacare_Crm_Graph {

	public const OPTION_TOKEN_HEALTH = 'vitacare_crm_graph_token_health'; // ok|invalid|unknown

	/**
	 * POST JSON a Graph.
	 *
	 * @param array<string, mixed> $body
	 * @return array{ok: bool, code: int, data: array<string, mixed>|null, error_code: int|null, error_message: string|null, raw: string}
	 */
	public static function post( string $path, array $body ): array {
		$token = Vitacare_Crm_Settings::get( 'access_token' );
		if ( $token === '' ) {
			return array(
				'ok'            => false,
				'code'          => 0,
				'data'          => null,
				'error_code'    => null,
				'error_message' => 'access_token_missing',
				'raw'           => '',
			);
		}

		$version = Vitacare_Crm_Settings::graph_version();
		$path    = ltrim( $path, '/' );
		$url     = 'https://graph.facebook.com/' . rawurlencode( $version ) . '/' . $path;

		$ua = sprintf(
			'VITACARE-CRM/%s; WordPress/%s',
			VITACARE_CRM_VERSION,
			get_bloginfo( 'version' )
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout'     => 15,
				'redirection' => 0,
				'headers'     => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
					'User-Agent'    => $ua,
				),
				'body'        => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			Vitacare_Crm_Logger::error(
				'graph_http_error',
				array(
					'path' => $path,
					'err'  => $response->get_error_message(),
				)
			);
			return array(
				'ok'            => false,
				'code'          => 0,
				'data'          => null,
				'error_code'    => null,
				'error_message' => $response->get_error_message(),
				'raw'           => '',
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$data = null;
		}

		$error_code    = null;
		$error_message = null;
		if ( isset( $data['error'] ) && is_array( $data['error'] ) ) {
			$error_code    = isset( $data['error']['code'] ) ? (int) $data['error']['code'] : null;
			$error_message = isset( $data['error']['message'] ) ? (string) $data['error']['message'] : null;
			// Subcode a veces trae 131047 (ventana).
			if ( isset( $data['error']['error_subcode'] ) ) {
				$error_code = (int) $data['error']['error_subcode'] ?: $error_code;
			}
			// Preferir error_data details.
			if ( isset( $data['error']['error_data']['details'] ) ) {
				$error_message = (string) $data['error']['error_data']['details'];
			}
		}

		if ( in_array( $code, array( 401, 403 ), true ) || in_array( $error_code, array( 190, 102 ), true ) ) {
			update_option( self::OPTION_TOKEN_HEALTH, 'invalid', false );
			Vitacare_Crm_Logger::error(
				'graph_token_invalid',
				array(
					'http' => $code,
					'code' => $error_code,
				)
			);
		} elseif ( $code >= 200 && $code < 300 ) {
			update_option( self::OPTION_TOKEN_HEALTH, 'ok', false );
		}

		return array(
			'ok'            => $code >= 200 && $code < 300,
			'code'          => $code,
			'data'          => $data,
			'error_code'    => $error_code,
			'error_message' => $error_message,
			'raw'           => $raw,
		);
	}

	public static function token_health(): string {
		$h = (string) get_option( self::OPTION_TOKEN_HEALTH, 'unknown' );
		return in_array( $h, array( 'ok', 'invalid', 'unknown' ), true ) ? $h : 'unknown';
	}

	/**
	 * ¿Error de ventana 24h / re-engagement?
	 */
	public static function is_outside_window( ?int $error_code, ?string $message ): bool {
		// 131047 = Re-engagement message; 131026 a veces related.
		if ( in_array( $error_code, array( 131047, 131026 ), true ) ) {
			return true;
		}
		if ( $message && preg_match( '/24\s*hour|re-engagement|outside.*window|ventana/i', $message ) ) {
			return true;
		}
		return false;
	}

	public static function is_rate_limited( int $http_code, ?int $error_code ): bool {
		return 429 === $http_code || 80007 === $error_code || 4 === $error_code;
	}
}

<?php
defined( 'ABSPATH' ) || exit;

/**
 * Webhooks Meta. Rutas siempre registradas. Fail-closed sin secret/flag.
 * PR-3: verify GET + POST HMAC + dispatch WhatsApp inbound/statuses.
 */
final class Vitacare_Crm_Webhook {

	public static function register_routes(): void {
		register_rest_route(
			'vitacare-crm/v1',
			'/webhooks/meta',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'handle_get' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'handle_post' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle_get( WP_REST_Request $request ) {
		if ( ! Vitacare_Crm_Settings::flag( 'whatsapp' ) ) {
			return new WP_REST_Response( array( 'error' => 'whatsapp_disabled' ), 403 );
		}

		$expected = Vitacare_Crm_Settings::get( 'verify_token' );
		if ( $expected === '' ) {
			return new WP_REST_Response( array( 'error' => 'verify_token_missing' ), 403 );
		}

		$query     = $request->get_query_params();
		$mode      = (string) ( $query['hub.mode'] ?? $query['hub_mode'] ?? $request->get_param( 'hub.mode' ) ?? '' );
		$token     = (string) ( $query['hub.verify_token'] ?? $query['hub_verify_token'] ?? $request->get_param( 'hub.verify_token' ) ?? '' );
		$challenge = (string) ( $query['hub.challenge'] ?? $query['hub_challenge'] ?? $request->get_param( 'hub.challenge' ) ?? '' );

		if ( $mode !== 'subscribe' ) {
			return new WP_REST_Response( array( 'error' => 'invalid_mode' ), 403 );
		}

		if ( ! hash_equals( $expected, $token ) ) {
			return new WP_REST_Response( array( 'error' => 'invalid_verify_token' ), 403 );
		}

		$response = new WP_REST_Response( $challenge, 200 );
		$response->header( 'Content-Type', 'text/plain; charset=utf-8' );
		return $response;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle_post( WP_REST_Request $request ): WP_REST_Response {
		if ( ! Vitacare_Crm_Settings::flag( 'whatsapp' )
			&& ! Vitacare_Crm_Settings::flag( 'facebook' )
			&& ! Vitacare_Crm_Settings::flag( 'instagram' )
		) {
			return new WP_REST_Response( array( 'error' => 'channels_disabled' ), 403 );
		}

		$secret = Vitacare_Crm_Settings::get( 'app_secret' );
		if ( $secret === '' ) {
			return new WP_REST_Response( array( 'error' => 'app_secret_missing' ), 403 );
		}

		$raw = $request->get_body();
		if ( $raw === '' || null === $raw ) {
			return new WP_REST_Response( array( 'error' => 'empty_body' ), 403 );
		}

		$sig = (string) $request->get_header( 'x-hub-signature-256' );
		if ( $sig === '' || ! self::valid_signature( $raw, $sig, $secret ) ) {
			return new WP_REST_Response( array( 'error' => 'signature_invalid' ), 403 );
		}

		$payload = json_decode( $raw, true );
		if ( ! is_array( $payload ) ) {
			// Evento basura: 200 sin writes para evitar storm.
			Vitacare_Crm_Logger::debug( 'webhook_invalid_json' );
			return new WP_REST_Response( array( 'ok' => true, 'skipped' => 'invalid_json' ), 200 );
		}

		try {
			$stats = array(
				'messages' => 0,
				'statuses' => 0,
				'skipped'  => 0,
			);

			// WhatsApp (y futuro routing multi-canal).
			if ( Vitacare_Crm_Settings::flag( 'whatsapp' ) ) {
				$wa = Vitacare_Crm_Channel_Whatsapp::handle_payload( $payload );
				$stats['messages'] += $wa['messages'];
				$stats['statuses'] += $wa['statuses'];
				$stats['skipped']  += $wa['skipped'];
			} else {
				// Firma válida pero canal off tras check genérico: no-op 200.
				++$stats['skipped'];
			}

			return new WP_REST_Response(
				array(
					'ok'    => true,
					'stats' => $stats,
				),
				200
			);
		} catch ( Throwable $e ) {
			Vitacare_Crm_Logger::error(
				'webhook_persistence_failed',
				array(
					'error' => $e->getMessage(),
				)
			);
			// Meta reintenta en 5xx.
			return new WP_REST_Response( array( 'error' => 'persistence_failed' ), 500 );
		}
	}

	/**
	 * HMAC SHA-256 timing-safe (testable).
	 */
	public static function valid_signature( string $raw_body, string $header, string $app_secret ): bool {
		if ( $app_secret === '' || $header === '' ) {
			return false;
		}
		$expected = 'sha256=' . hash_hmac( 'sha256', $raw_body, $app_secret );
		return hash_equals( $expected, $header );
	}
}

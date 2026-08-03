<?php
defined( 'ABSPATH' ) || exit;

/**
 * Webhooks Meta (WhatsApp / futuro FB-IG).
 * Rutas siempre registradas. Sin secret o flag off → 403 fail-closed.
 * Ingest completo de mensajes: PR-3. Aquí: verify GET + POST firmado stub 200.
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
	 * Verificación de suscripción Meta (hub.mode=subscribe).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_get( WP_REST_Request $request ) {
		if ( ! Vitacare_Crm_Settings::flag( 'whatsapp' ) ) {
			return new WP_REST_Response( array( 'error' => 'whatsapp_disabled' ), 403 );
		}

		$expected = Vitacare_Crm_Settings::get( 'verify_token' );
		if ( $expected === '' ) {
			return new WP_REST_Response( array( 'error' => 'verify_token_missing' ), 403 );
		}

		// Meta envía hub.mode / hub.verify_token / hub.challenge (con punto).
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

		// Meta exige body = challenge en texto plano.
		$response = new WP_REST_Response( $challenge, 200 );
		$response->header( 'Content-Type', 'text/plain; charset=utf-8' );
		return $response;
	}

	/**
	 * Recepción POST: fail-closed + HMAC. Persistencia de mensajes en PR-3.
	 *
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
		if ( $raw === '' || $raw === null ) {
			return new WP_REST_Response( array( 'error' => 'empty_body' ), 403 );
		}

		$sig = (string) $request->get_header( 'x-hub-signature-256' );
		if ( $sig === '' ) {
			return new WP_REST_Response( array( 'error' => 'signature_missing' ), 403 );
		}

		$expected = 'sha256=' . hash_hmac( 'sha256', $raw, $secret );
		if ( ! hash_equals( $expected, $sig ) ) {
			return new WP_REST_Response( array( 'error' => 'signature_invalid' ), 403 );
		}

		// Firma OK: ack 200 sin writes (ingesta en PR-3).
		return new WP_REST_Response(
			array(
				'ok'        => true,
				'processed' => false,
				'note'      => 'webhook_accepted_pending_inbound_handler',
			),
			200
		);
	}
}

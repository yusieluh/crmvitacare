<?php
defined( 'ABSPATH' ) || exit;

/**
 * Webhooks Meta. Rutas siempre registradas. Fail-closed sin secret/flag.
 * WhatsApp (object=whatsapp_business_account) + Messenger (object=page).
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
	 * @return WP_REST_Response En caso de error (mode/token inválido). En éxito,
	 *                          escribe texto plano y termina la ejecución (exit)
	 *                          porque Meta exige el challenge sin envoltorio JSON.
	 */
	public static function handle_get( WP_REST_Request $request ) {
		// Un solo endpoint verify para WA + Page (Messenger/IG).
		if ( ! Vitacare_Crm_Settings::flag( 'whatsapp' )
			&& ! Vitacare_Crm_Settings::flag( 'facebook' )
			&& ! Vitacare_Crm_Settings::flag( 'instagram' )
		) {
			return new WP_REST_Response( array( 'error' => 'channels_disabled' ), 403 );
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

		// Meta exige el challenge como texto plano exacto (sin comillas ni
		// envoltorio JSON). WP_REST_Response siempre pasa por el serializador
		// JSON del REST server sin importar el header Content-Type, así que
		// hay que salir del pipeline REST y escribir la respuesta a mano.
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=UTF-8' );
		echo $challenge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Meta exige el valor exacto, sin escapar/envolver.
		exit;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle_post( WP_REST_Request $request ): WP_REST_Response {
		// D-31: diagnóstico no sensible de cada POST recibido -- ver
		// includes/class-vitacare-crm-webhook-diagnostics.php. Nunca guarda
		// texto de mensaje, tokens, App Secret, firma completa ni IDs
		// completos; se registra en cada punto de retorno de este método.
		$diag = array(
			'content_type'     => (string) $request->get_header( 'content-type' ),
			'body_size'        => 0,
			'has_signature'    => false,
			'signature_valid'  => false,
			'object'           => '',
			'entry_count'      => 0,
			'messaging_count'  => 0,
			'event_type'       => 'unknown',
			'page_id_masked'   => '',
			'sender_id_masked' => '',
			'contact_created'      => false,
			'conversation_created' => false,
			'message_created'      => false,
			'skip_reason'      => null,
			'http_status'      => 0,
		);

		if ( ! Vitacare_Crm_Settings::flag( 'whatsapp' )
			&& ! Vitacare_Crm_Settings::flag( 'facebook' )
			&& ! Vitacare_Crm_Settings::flag( 'instagram' )
		) {
			$diag['skip_reason'] = 'channels_disabled';
			$diag['http_status'] = 403;
			Vitacare_Crm_Webhook_Diagnostics::record( $diag );
			return new WP_REST_Response( array( 'error' => 'channels_disabled' ), 403 );
		}

		$secret = Vitacare_Crm_Settings::get( 'app_secret' );
		if ( $secret === '' ) {
			$diag['skip_reason'] = 'app_secret_missing';
			$diag['http_status'] = 403;
			Vitacare_Crm_Webhook_Diagnostics::record( $diag );
			return new WP_REST_Response( array( 'error' => 'app_secret_missing' ), 403 );
		}

		$raw                = $request->get_body();
		$diag['body_size']  = is_string( $raw ) ? strlen( $raw ) : 0;
		if ( $raw === '' || null === $raw ) {
			$diag['skip_reason'] = 'empty_body';
			$diag['http_status'] = 403;
			Vitacare_Crm_Webhook_Diagnostics::record( $diag );
			return new WP_REST_Response( array( 'error' => 'empty_body' ), 403 );
		}

		$sig                    = (string) $request->get_header( 'x-hub-signature-256' );
		$diag['has_signature']  = $sig !== '';
		$diag['signature_valid'] = $sig !== '' && self::valid_signature( $raw, $sig, $secret );
		if ( ! $diag['signature_valid'] ) {
			$diag['skip_reason'] = 'signature_invalid';
			$diag['http_status'] = 403;
			Vitacare_Crm_Webhook_Diagnostics::record( $diag );
			return new WP_REST_Response( array( 'error' => 'signature_invalid' ), 403 );
		}

		$payload = json_decode( $raw, true );
		if ( ! is_array( $payload ) ) {
			Vitacare_Crm_Logger::debug( 'webhook_invalid_json' );
			$diag['skip_reason'] = 'invalid_json';
			$diag['http_status'] = 200;
			Vitacare_Crm_Webhook_Diagnostics::record( $diag );
			return new WP_REST_Response( array( 'ok' => true, 'skipped' => 'invalid_json' ), 200 );
		}

		$object              = isset( $payload['object'] ) ? (string) $payload['object'] : '';
		$diag['object']      = $object !== '' ? $object : '(vacío)';
		$entries             = isset( $payload['entry'] ) && is_array( $payload['entry'] ) ? $payload['entry'] : array();
		$diag['entry_count'] = count( $entries );

		try {
			$stats = array(
				'messages' => 0,
				'statuses' => 0,
				'skipped'  => 0,
			);

			if ( $object === 'whatsapp_business_account' || $object === '' ) {
				if ( Vitacare_Crm_Settings::flag( 'whatsapp' ) ) {
					$wa = Vitacare_Crm_Channel_Whatsapp::handle_payload( $payload );
					$stats['messages'] += $wa['messages'];
					$stats['statuses'] += $wa['statuses'];
					$stats['skipped']  += $wa['skipped'];
					$diag['skip_reason'] = 'whatsapp_channel (no auditado en esta tarea)';
				} else {
					++$stats['skipped'];
					$diag['skip_reason'] = 'whatsapp_flag_off';
				}
			}

			if ( $object === 'page' ) {
				if ( Vitacare_Crm_Settings::flag( 'facebook' ) ) {
					$fb = Vitacare_Crm_Channel_Messenger::handle_payload( $payload );
					$stats['messages'] += $fb['messages'];
					$stats['statuses'] += $fb['statuses'];
					$stats['skipped']  += $fb['skipped'];
					if ( isset( $fb['diag'] ) && is_array( $fb['diag'] ) ) {
						$diag = array_merge( $diag, $fb['diag'] );
					}
				} else {
					++$stats['skipped'];
					$diag['skip_reason'] = 'facebook_flag_off';
				}
			}

			if ( $object === 'instagram' ) {
				if ( Vitacare_Crm_Settings::flag( 'instagram' ) ) {
					$ig = Vitacare_Crm_Channel_Instagram::handle_payload( $payload );
					$stats['messages'] += $ig['messages'];
					$stats['statuses'] += $ig['statuses'];
					$stats['skipped']  += $ig['skipped'];
					$diag['skip_reason'] = 'instagram_channel (no auditado en esta tarea)';
				} else {
					++$stats['skipped'];
					$diag['skip_reason'] = 'instagram_flag_off';
				}
			}

			if ( $object !== '' && $object !== 'whatsapp_business_account' && $object !== 'page' && $object !== 'instagram' ) {
				++$stats['skipped'];
				$diag['skip_reason'] = 'object_unrecognized';
			}

			$diag['http_status'] = 200;
			Vitacare_Crm_Webhook_Diagnostics::record( $diag );

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
			$diag['skip_reason'] = 'exception: ' . $e->getMessage();
			$diag['http_status'] = 500;
			Vitacare_Crm_Webhook_Diagnostics::record( $diag );
			return new WP_REST_Response( array( 'error' => 'persistence_failed' ), 500 );
		}
	}

	public static function valid_signature( string $raw_body, string $header, string $app_secret ): bool {
		if ( $app_secret === '' || $header === '' ) {
			return false;
		}
		$expected = 'sha256=' . hash_hmac( 'sha256', $raw_body, $app_secret );
		return hash_equals( $expected, $header );
	}
}

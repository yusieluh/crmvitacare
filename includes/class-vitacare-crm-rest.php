<?php
defined( 'ABSPATH' ) || exit;

/**
 * Namespace REST propio del CRM (vitacare-crm/v1), separado del namespace
 * vitacare/v1 de vitacare-core. En fase 2 aquí se agregan las rutas de
 * verificación/recepción de webhooks de WhatsApp Cloud API.
 */
final class Vitacare_Crm_Rest {

	public static function register_routes(): void {
		register_rest_route(
			'vitacare-crm/v1',
			'/ping',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'ping' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function ping(): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'ok'      => true,
				'plugin'  => 'vitacare-crm',
				'version' => VITACARE_CRM_VERSION,
			),
			200
		);
	}
}

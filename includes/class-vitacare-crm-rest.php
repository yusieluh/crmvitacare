<?php
defined( 'ABSPATH' ) || exit;

/**
 * Namespace REST propio del CRM (vitacare-crm/v1), separado de vitacare/v1 (core).
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

		// Webhooks siempre registrados (URL estable para Meta).
		Vitacare_Crm_Webhook::register_routes();
	}

	public static function ping(): WP_REST_Response {
		$data = array(
			'ok'      => true,
			'plugin'  => 'vitacare-crm',
			'version' => VITACARE_CRM_VERSION,
		);

		// Sin secretos: solo estado booleano de configuración (útil para health checks).
		if ( class_exists( 'Vitacare_Crm_Settings' ) ) {
			$data['whatsapp_flag']   = Vitacare_Crm_Settings::flag( 'whatsapp' );
			$data['webhook_ready']   = Vitacare_Crm_Settings::whatsapp_webhook_ready();
			$data['graph_version']   = Vitacare_Crm_Settings::graph_version();
		}

		return new WP_REST_Response( $data, 200 );
	}
}

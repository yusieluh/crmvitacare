<?php
defined( 'ABSPATH' ) || exit;

/**
 * Namespace REST propio del CRM (vitacare-crm/v1).
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

		register_rest_route(
			'vitacare-crm/v1',
			'/conversations',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_conversations' ),
				'permission_callback' => array( 'Vitacare_Crm_Db', 'require_crm_access' ),
				'args'                => array(
					'channel'     => array( 'type' => 'string', 'required' => false ),
					'status'      => array( 'type' => 'string', 'required' => false, 'default' => 'open' ),
					'assigned_to' => array( 'required' => false ),
					'page'        => array( 'type' => 'integer', 'required' => false, 'default' => 1 ),
					'per_page'    => array( 'type' => 'integer', 'required' => false, 'default' => 20 ),
					'q'           => array( 'type' => 'string', 'required' => false ),
				),
			)
		);

		register_rest_route(
			'vitacare-crm/v1',
			'/conversations/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_conversation' ),
					'permission_callback' => array( 'Vitacare_Crm_Db', 'require_crm_access' ),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( __CLASS__, 'patch_conversation' ),
					'permission_callback' => array( 'Vitacare_Crm_Db', 'require_crm_access' ),
				),
			)
		);

		register_rest_route(
			'vitacare-crm/v1',
			'/conversations/(?P<id>\d+)/messages',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'list_messages' ),
				'permission_callback' => array( 'Vitacare_Crm_Db', 'require_crm_access' ),
				'args'                => array(
					'limit'     => array( 'type' => 'integer', 'required' => false, 'default' => 30 ),
					'before_id' => array( 'type' => 'integer', 'required' => false ),
				),
			)
		);

		Vitacare_Crm_Webhook::register_routes();
	}

	public static function ping(): WP_REST_Response {
		$data = array(
			'ok'      => true,
			'plugin'  => 'vitacare-crm',
			'version' => VITACARE_CRM_VERSION,
			'db'      => (string) get_option( 'vitacare_crm_db_version', '0' ),
		);

		if ( class_exists( 'Vitacare_Crm_Settings' ) ) {
			$data['whatsapp_flag'] = Vitacare_Crm_Settings::flag( 'whatsapp' );
			$data['webhook_ready'] = Vitacare_Crm_Settings::whatsapp_webhook_ready();
			$data['graph_version'] = Vitacare_Crm_Settings::graph_version();
		}

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function list_conversations( WP_REST_Request $request ) {
		$result = Vitacare_Crm_Conversations_Repo::list(
			array(
				'channel'     => $request->get_param( 'channel' ),
				'status'      => $request->get_param( 'status' ),
				'assigned_to' => $request->get_param( 'assigned_to' ),
				'page'        => $request->get_param( 'page' ),
				'per_page'    => $request->get_param( 'per_page' ),
				'q'           => $request->get_param( 'q' ),
			)
		);
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_conversation( WP_REST_Request $request ) {
		$id   = (int) $request['id'];
		$item = Vitacare_Crm_Conversations_Repo::get( $id );
		if ( null === $item ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_not_found', __( 'Conversación no encontrada.', 'vitacare-crm' ), 404 );
		}
		return new WP_REST_Response( $item, 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function patch_conversation( WP_REST_Request $request ) {
		$id   = (int) $request['id'];
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_body_params();
		}
		if ( ! is_array( $body ) ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_invalid_param', __( 'Cuerpo JSON inválido.', 'vitacare-crm' ), 400 );
		}

		$result = Vitacare_Crm_Conversations_Repo::update( $id, $body );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function list_messages( WP_REST_Request $request ) {
		$id        = (int) $request['id'];
		$limit     = (int) $request->get_param( 'limit' );
		$before_id = $request->get_param( 'before_id' );
		$before    = null !== $before_id && '' !== $before_id ? (int) $before_id : null;

		$result = Vitacare_Crm_Messages_Repo::list_for_conversation( $id, $limit > 0 ? $limit : 30, $before );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}
}

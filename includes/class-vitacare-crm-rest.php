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
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'list_messages' ),
					'permission_callback' => array( 'Vitacare_Crm_Db', 'require_crm_access' ),
					'args'                => array(
						'limit'     => array( 'type' => 'integer', 'required' => false, 'default' => 30 ),
						'before_id' => array( 'type' => 'integer', 'required' => false ),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'post_message' ),
					'permission_callback' => array( 'Vitacare_Crm_Db', 'require_crm_access' ),
				),
			)
		);

		register_rest_route(
			'vitacare-crm/v1',
			'/conversations/(?P<id>\d+)/vitacare-contact',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_vitacare_contact' ),
				'permission_callback' => array( 'Vitacare_Crm_Db', 'require_crm_access' ),
			)
		);

		register_rest_route(
			'vitacare-crm/v1',
			'/media/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_media' ),
				'permission_callback' => array( 'Vitacare_Crm_Db', 'require_crm_access' ),
			)
		);

		register_rest_route(
			'vitacare-crm/v1',
			'/media/upload',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'upload_media' ),
				'permission_callback' => array( 'Vitacare_Crm_Db', 'require_crm_access' ),
			)
		);

		register_rest_route(
			'vitacare-crm/v1',
			'/leads',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'list_leads' ),
					'permission_callback' => array( 'Vitacare_Crm_Db', 'require_crm_access' ),
					'args'                => array(
						'source'         => array( 'type' => 'string', 'required' => false ),
						'consent_status' => array( 'type' => 'string', 'required' => false ),
						'tag'            => array( 'type' => 'string', 'required' => false ),
						'q'              => array( 'type' => 'string', 'required' => false ),
						'page'           => array( 'type' => 'integer', 'required' => false, 'default' => 1 ),
						'per_page'       => array( 'type' => 'integer', 'required' => false, 'default' => 20 ),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'create_lead' ),
					'permission_callback' => array( 'Vitacare_Crm_Db', 'require_crm_access' ),
				),
			)
		);

		register_rest_route(
			'vitacare-crm/v1',
			'/leads/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_lead' ),
					'permission_callback' => array( 'Vitacare_Crm_Db', 'require_crm_access' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( __CLASS__, 'update_lead' ),
					'permission_callback' => array( 'Vitacare_Crm_Db', 'require_crm_access' ),
				),
			)
		);

		register_rest_route(
			'vitacare-crm/v1',
			'/leads/(?P<id>\d+)/consent',
			array(
				'methods'             => 'PUT',
				'callback'            => array( __CLASS__, 'set_lead_consent' ),
				'permission_callback' => array( 'Vitacare_Crm_Db', 'require_crm_access' ),
			)
		);

		register_rest_route(
			'vitacare-crm/v1',
			'/leads/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'import_leads' ),
				'permission_callback' => array( 'Vitacare_Crm_Db', 'require_crm_access' ),
			)
		);

		register_rest_route(
			'vitacare-crm/v1',
			'/links',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'list_links' ),
					'permission_callback' => array( 'Vitacare_Crm_Db', 'require_crm_access' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'create_link' ),
					'permission_callback' => array( 'Vitacare_Crm_Db', 'require_crm_access' ),
				),
			)
		);

		// D-25 Fase 3: redirector público de enlaces con seguimiento propio.
		// Sin auth a propósito -- es el enlace que reciben los contactos
		// (WhatsApp/correo/redes); el destino nunca viene del request, solo
		// de lo que el staff guardó al crear el código (ver Links_Repo).
		register_rest_route(
			'vitacare-crm/v1',
			'/go/(?P<code>[A-Za-z0-9]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'go' ),
				'permission_callback' => '__return_true',
			)
		);

		Vitacare_Crm_Webhook::register_routes();
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function list_links( WP_REST_Request $request ) {
		return new WP_REST_Response( Vitacare_Crm_Links_Repo::list_all( 100 ), 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_link( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_body_params();
		}
		if ( ! is_array( $body ) || empty( $body['target_url'] ) ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_invalid_param', __( 'Falta target_url.', 'vitacare-crm' ), 400 );
		}
		$result = Vitacare_Crm_Links_Repo::create(
			(string) $body['target_url'],
			isset( $body['campaign_tag'] ) ? (string) $body['campaign_tag'] : '',
			isset( $body['lead_id'] ) ? (int) $body['lead_id'] : 0
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 201 );
	}

	/**
	 * Redirige al destino guardado y cuenta el clic. 404 "silencioso" (sin
	 * filtrar si el código existió alguna vez) si no hay match.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|void
	 */
	public static function go( WP_REST_Request $request ) {
		$code   = (string) $request['code'];
		$target = Vitacare_Crm_Links_Repo::register_click( $code );
		if ( null === $target ) {
			return new WP_REST_Response( array( 'error' => 'not_found' ), 404 );
		}
		nocache_headers();
		wp_redirect( $target, 302 ); // phpcs:ignore WordPress.Security.SafeRedirect -- destino es un valor de admin (ver Links_Repo), no del request público.
		exit;
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function list_leads( WP_REST_Request $request ) {
		$result = Vitacare_Crm_Leads_Repo::list(
			array(
				'source'         => $request->get_param( 'source' ),
				'consent_status' => $request->get_param( 'consent_status' ),
				'tag'            => $request->get_param( 'tag' ),
				'q'              => $request->get_param( 'q' ),
				'page'           => $request->get_param( 'page' ),
				'per_page'       => $request->get_param( 'per_page' ),
			)
		);
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_lead( WP_REST_Request $request ) {
		$item = Vitacare_Crm_Leads_Repo::get( (int) $request['id'] );
		if ( null === $item ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_not_found', __( 'Lead no encontrado.', 'vitacare-crm' ), 404 );
		}
		return new WP_REST_Response( $item, 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_lead( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_body_params();
		}
		if ( ! is_array( $body ) ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_invalid_param', __( 'Cuerpo JSON inválido.', 'vitacare-crm' ), 400 );
		}
		$result = Vitacare_Crm_Leads_Repo::create( $body );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 201 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_lead( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_body_params();
		}
		if ( ! is_array( $body ) ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_invalid_param', __( 'Cuerpo JSON inválido.', 'vitacare-crm' ), 400 );
		}
		$result = Vitacare_Crm_Leads_Repo::update( (int) $request['id'], $body );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function set_lead_consent( WP_REST_Request $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_body_params();
		}
		$status = is_array( $body ) && isset( $body['status'] ) ? (string) $body['status'] : '';
		$source = is_array( $body ) && isset( $body['source'] ) ? (string) $body['source'] : 'api';

		$result = Vitacare_Crm_Leads_Repo::set_consent( (int) $request['id'], $status, $source );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function import_leads( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		$csv   = '';
		if ( ! empty( $files['file']['tmp_name'] ) && is_uploaded_file( $files['file']['tmp_name'] ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents
			$csv = (string) file_get_contents( $files['file']['tmp_name'] );
		} else {
			$body = $request->get_json_params();
			$csv  = is_array( $body ) && isset( $body['csv'] ) ? (string) $body['csv'] : '';
		}
		if ( $csv === '' ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_invalid_param', __( 'Falta el CSV (archivo o campo "csv").', 'vitacare-crm' ), 400 );
		}
		$result = Vitacare_Crm_Leads_Repo::import_csv( $csv );
		return new WP_REST_Response( $result, 200 );
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
			$data['send_ready']   = Vitacare_Crm_Settings::flag( 'whatsapp' )
				&& Vitacare_Crm_Settings::get( 'access_token' ) !== ''
				&& Vitacare_Crm_Settings::get( 'phone_number_id' ) !== '';
		}
		if ( class_exists( 'Vitacare_Crm_Graph' ) ) {
			$data['graph_token_health'] = Vitacare_Crm_Graph::token_health();
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
	 * Ficha de solo lectura del contacto en VITACARE (si el teléfono/correo
	 * de la conversación coincide con un usuario real) -- ver
	 * Vitacare_Crm_Vitacare_Bridge. `matched: false` es una respuesta
	 * normal, no un error: la mayoría de contactos nuevos no tendrán
	 * cuenta todavía.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_vitacare_contact( WP_REST_Request $request ) {
		$id           = (int) $request['id'];
		$conversation = Vitacare_Crm_Conversations_Repo::get( $id );
		if ( null === $conversation ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_not_found', __( 'Conversación no encontrada.', 'vitacare-crm' ), 404 );
		}
		$profile = Vitacare_Crm_Vitacare_Bridge::lookup_for_conversation( $conversation );
		if ( null === $profile ) {
			return new WP_REST_Response( array( 'matched' => false ), 200 );
		}
		return new WP_REST_Response( array_merge( array( 'matched' => true ), $profile ), 200 );
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
		// Al abrir el hilo se marcan no leídos = 0 (UX bandeja).
		Vitacare_Crm_Conversations_Repo::mark_read( $id );
		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * POST /conversations/{id}/messages — envío saliente WhatsApp (PR-4).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function post_message( WP_REST_Request $request ) {
		$id   = (int) $request['id'];
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_body_params();
		}
		if ( ! is_array( $body ) ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_invalid_param', __( 'Cuerpo JSON inválido.', 'vitacare-crm' ), 400 );
		}

		// Allowlist: texto y/o referencia de adjunto ya subido (PR-6b).
		$allowed = array( 'body', 'media_attachment_id' );
		$unknown = array_diff( array_keys( $body ), $allowed );
		if ( ! empty( $unknown ) ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_invalid_param',
				sprintf(
					/* translators: %s: fields */
					__( 'Campos no permitidos: %s', 'vitacare-crm' ),
					implode( ', ', $unknown )
				),
				400
			);
		}

		$text      = isset( $body['body'] ) ? (string) $body['body'] : '';
		$media_ref = isset( $body['media_attachment_id'] ) ? (string) $body['media_attachment_id'] : '';

		$conv = Vitacare_Crm_Conversations_Repo::get( $id );
		if ( null === $conv ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_not_found', __( 'Conversación no encontrada.', 'vitacare-crm' ), 404 );
		}
		$channel = (string) ( $conv['channel'] ?? '' );

		if ( $media_ref !== '' ) {
			if ( ! Vitacare_Crm_Media::is_local_ref( $media_ref ) || null === Vitacare_Crm_Media::resolve_path( $media_ref ) ) {
				return Vitacare_Crm_Db::error(
					'vitacare_crm_invalid_param',
					__( 'Adjunto inválido o ya no disponible. Vuelve a adjuntarlo.', 'vitacare-crm' ),
					400
				);
			}
			if ( $channel === 'whatsapp' ) {
				$result = Vitacare_Crm_Channel_Whatsapp::send_media( $id, $media_ref, $text );
			} elseif ( $channel === 'facebook' ) {
				$result = Vitacare_Crm_Channel_Messenger::send_media( $id, $media_ref, $text );
			} else {
				return Vitacare_Crm_Db::error(
					'vitacare_crm_invalid_param',
					__( 'El envío de adjuntos no está disponible para este canal todavía.', 'vitacare-crm' ),
					400
				);
			}
		} elseif ( $channel === 'whatsapp' ) {
			$result = Vitacare_Crm_Channel_Whatsapp::send_text( $id, $text );
		} elseif ( $channel === 'facebook' ) {
			$result = Vitacare_Crm_Channel_Messenger::send_text( $id, $text );
		} elseif ( $channel === 'instagram' ) {
			$result = Vitacare_Crm_Channel_Instagram::send_text( $id, $text );
		} elseif ( $channel === 'email' ) {
			// Dos proveedores posibles bajo el mismo canal "email" (ver D-22 en
			// ESTADO_CRM.md); cada conversación recuerda en meta.mail_provider cuál
			// buzón la maneja para responder por el correcto. Zoho Mail es el correo
			// institucional y el proveedor por defecto; Gmail es secundario/opcional
			// y solo se usa si la conversación quedó explícitamente marcada 'gmail'.
			$provider = is_array( $conv['meta'] ?? null ) ? (string) ( $conv['meta']['mail_provider'] ?? 'zoho' ) : 'zoho';
			$result   = ( $provider === 'gmail' )
				? Vitacare_Crm_Gmail::send_text( $id, $text )
				: Vitacare_Crm_Zoho::send_text( $id, $text );
		} else {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_invalid_param',
				__( 'Envío no disponible para este canal todavía.', 'vitacare-crm' ),
				400
			);
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( $result, 201 );
	}

	/**
	 * PR-6b: sube un archivo de staff (multipart) a almacenamiento privado y
	 * devuelve su ref opaco para usarlo luego como media_attachment_id al
	 * enviar el mensaje.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function upload_media( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) || ! is_array( $files['file'] ) ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_invalid_param', __( 'Falta el archivo.', 'vitacare-crm' ), 400 );
		}

		$result = Vitacare_Crm_Media::store_uploaded_file( $files['file'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response(
			array(
				'media_attachment_id' => $result['ref'],
				'mime'                => $result['mime'],
				'size'                => $result['size'],
				'filename'            => $result['filename'],
			),
			201
		);
	}

	/**
	 * PR-6: sirve un adjunto descargado a almacenamiento propio. Nunca
	 * expone rutas de disco ni URLs de Meta; solo IDs de mensaje internos
	 * y requiere la capability del CRM (permission_callback de la ruta).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_media( WP_REST_Request $request ) {
		$id  = (int) $request['id'];
		$row = Vitacare_Crm_Messages_Repo::get( $id );
		if ( null === $row ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_not_found', __( 'Mensaje no encontrado.', 'vitacare-crm' ), 404 );
		}

		$ref = isset( $row->media_url ) ? (string) $row->media_url : '';
		if ( ! Vitacare_Crm_Media::is_local_ref( $ref ) ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_not_found', __( 'Este mensaje no tiene un archivo disponible.', 'vitacare-crm' ), 404 );
		}

		$path = Vitacare_Crm_Media::resolve_path( $ref );
		if ( null === $path || ! is_readable( $path ) ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_not_found', __( 'Archivo no disponible.', 'vitacare-crm' ), 404 );
		}

		$mime = isset( $row->media_mime ) && $row->media_mime !== '' ? (string) $row->media_mime : 'application/octet-stream';

		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'Content-Disposition: inline; filename="' . basename( $path ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
		readfile( $path );
		exit;
	}
}

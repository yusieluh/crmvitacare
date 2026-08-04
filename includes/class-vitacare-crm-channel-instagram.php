<?php
defined( 'ABSPATH' ) || exit;

/**
 * C-3: Instagram Direct (cuenta profesional vinculada a la Página de Facebook
 * ya conectada). Mismo patrón que Messenger: webhook object=instagram,
 * entry[].messaging[], IDs con alcance de Instagram (IGSID) en vez de PSID.
 */
final class Vitacare_Crm_Channel_Instagram {

	/**
	 * @param array<string, mixed> $payload
	 * @return array{messages: int, statuses: int, skipped: int}
	 */
	public static function handle_payload( array $payload ): array {
		$stats = array(
			'messages' => 0,
			'statuses' => 0,
			'skipped'  => 0,
		);

		$object = isset( $payload['object'] ) ? (string) $payload['object'] : '';
		if ( $object !== 'instagram' ) {
			++$stats['skipped'];
			return $stats;
		}

		$configured_ig = class_exists( 'Vitacare_Crm_Facebook_Oauth' )
			? Vitacare_Crm_Facebook_Oauth::get_ig_id()
			: '';

		$entries = isset( $payload['entry'] ) && is_array( $payload['entry'] ) ? $payload['entry'] : array();
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$ig_id = (string) ( $entry['id'] ?? '' );
			// Si hay cuenta Instagram vinculada en el CRM, ignorar eventos de otras cuentas.
			if ( $configured_ig !== '' && $ig_id !== '' && $ig_id !== $configured_ig ) {
				++$stats['skipped'];
				continue;
			}

			$messaging = isset( $entry['messaging'] ) && is_array( $entry['messaging'] ) ? $entry['messaging'] : array();
			foreach ( $messaging as $event ) {
				if ( ! is_array( $event ) ) {
					++$stats['skipped'];
					continue;
				}
				if ( self::handle_messaging_event( $event, $ig_id ) ) {
					if ( isset( $event['message'] ) ) {
						++$stats['messages'];
					} else {
						++$stats['statuses'];
					}
				} else {
					++$stats['skipped'];
				}
			}
		}

		return $stats;
	}

	/**
	 * @param array<string, mixed> $event
	 */
	private static function handle_messaging_event( array $event, string $ig_id ): bool {
		if ( isset( $event['delivery'] ) && is_array( $event['delivery'] ) ) {
			return self::handle_delivery( $event['delivery'] );
		}
		if ( isset( $event['read'] ) ) {
			// Watermark sin mid concreto — skip sin error.
			return false;
		}

		if ( ! isset( $event['message'] ) || ! is_array( $event['message'] ) ) {
			return false;
		}

		$message = $event['message'];
		$mid     = (string) ( $message['mid'] ?? '' );
		if ( $mid === '' ) {
			return false;
		}

		if ( Vitacare_Crm_Messages_Repo::find_by_external_id( $mid ) ) {
			return true; // dedupe
		}

		$sender_id    = (string) ( $event['sender']['id'] ?? '' );
		$recipient_id = (string) ( $event['recipient']['id'] ?? '' );
		$is_echo      = ! empty( $message['is_echo'] );

		if ( $is_echo ) {
			$direction   = 'outbound';
			$sender_type = 'staff';
			$contact_id  = $recipient_id;
		} else {
			// Inbound del usuario: sender = IGSID, recipient = cuenta IG.
			$direction   = 'inbound';
			$sender_type = 'contact';
			$contact_id  = $sender_id;
		}

		if ( $contact_id === '' ) {
			return false;
		}

		$body         = null;
		$message_type = 'text';
		$media_url    = null;
		$media_mime   = null;

		if ( isset( $message['text'] ) ) {
			$body = (string) $message['text'];
		} elseif ( isset( $message['attachments'][0] ) && is_array( $message['attachments'][0] ) ) {
			$att          = $message['attachments'][0];
			$type         = isset( $att['type'] ) ? sanitize_key( (string) $att['type'] ) : 'file';
			$message_type = in_array( $type, array( 'image', 'audio', 'video', 'file' ), true )
				? ( $type === 'file' ? 'document' : $type )
				: 'other';
			$body         = '[' . $type . ']';
			// PR-6: descargar el adjunto a almacenamiento propio; nunca se
			// expone el CDN url de Meta (temporal y sin control de acceso).
			if ( isset( $att['payload']['url'] ) && class_exists( 'Vitacare_Crm_Media' ) ) {
				$downloaded = Vitacare_Crm_Media::download_remote_media( (string) $att['payload']['url'] );
				if ( is_array( $downloaded ) ) {
					$media_url  = $downloaded['ref'];
					$media_mime = $downloaded['mime'] !== '' ? $downloaded['mime'] : $media_mime;
				} else {
					Vitacare_Crm_Logger::error(
						'instagram_media_download_failed',
						array(
							'mid' => $mid,
							'msg' => is_wp_error( $downloaded ) ? $downloaded->get_error_message() : 'unknown',
						)
					);
				}
			}
		} elseif ( ! empty( $message['is_unsupported'] ) ) {
			$message_type = 'other';
			$body         = '[mensaje no soportado en Instagram]';
		} else {
			$body         = '[message]';
			$message_type = 'other';
		}

		$ts = isset( $event['timestamp'] ) ? (int) $event['timestamp'] : 0;
		// Instagram timestamp suele ser ms, igual que Messenger.
		if ( $ts > 20000000000 ) {
			$ts = (int) floor( $ts / 1000 );
		}
		$created = $ts > 0
			? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $ts ) )
			: current_time( 'mysql' );

		$conv_id = Vitacare_Crm_Conversations_Repo::upsert_contact(
			'instagram',
			$contact_id,
			null,
			null,
			array(
				'ig_id' => $ig_id,
				'igsid' => $contact_id,
			)
		);
		if ( $conv_id <= 0 ) {
			throw new RuntimeException( 'instagram_conversation_upsert_failed' );
		}

		$inserted = Vitacare_Crm_Messages_Repo::insert_message(
			array(
				'conversation_id'     => $conv_id,
				'direction'           => $direction,
				'sender_type'         => $sender_type,
				'message_type'        => $message_type,
				'body'                => $body,
				'media_url'           => $media_url,
				'media_mime'          => $media_mime,
				'external_message_id' => $mid,
				'delivery_status'     => $direction === 'outbound' ? 'sent' : null,
				'created_at'          => $created,
			)
		);

		if ( false === $inserted ) {
			return true;
		}
		if ( $inserted < 0 ) {
			throw new RuntimeException( 'instagram_message_insert_failed' );
		}

		Vitacare_Crm_Conversations_Repo::touch_after_message( $conv_id, $created, $direction === 'inbound' );
		return true;
	}

	/**
	 * @param array<string, mixed> $delivery
	 */
	private static function handle_delivery( array $delivery ): bool {
		$mids = isset( $delivery['mids'] ) && is_array( $delivery['mids'] ) ? $delivery['mids'] : array();
		$ok   = false;
		foreach ( $mids as $mid ) {
			$mid = (string) $mid;
			if ( $mid === '' ) {
				continue;
			}
			if ( Vitacare_Crm_Messages_Repo::update_delivery_status( $mid, 'delivered' ) ) {
				$ok = true;
			}
		}
		return $ok;
	}

	/**
	 * Envía texto por Instagram Direct a la conversación instagram.
	 * Usa el mismo Page Access Token de Facebook: la cuenta IG profesional
	 * se administra a través de la Página vinculada.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function send_text( int $conversation_id, string $body ) {
		if ( ! Vitacare_Crm_Settings::flag( 'instagram' ) ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_forbidden',
				__( 'El canal Instagram está desactivado.', 'vitacare-crm' ),
				403
			);
		}
		if ( ! class_exists( 'Vitacare_Crm_Facebook_Oauth' ) || ! Vitacare_Crm_Facebook_Oauth::is_instagram_connected() ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_graph_error',
				__( 'No hay cuenta de Instagram vinculada. Ve a CRM → Facebook.', 'vitacare-crm' ),
				502
			);
		}

		$body = trim( $body );
		if ( $body === '' ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_invalid_param',
				__( 'El mensaje no puede estar vacío.', 'vitacare-crm' ),
				400
			);
		}
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $body ) > 1000 : strlen( $body ) > 1000 ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_invalid_param',
				__( 'El mensaje supera el máximo de Instagram (1000).', 'vitacare-crm' ),
				400
			);
		}

		$conv = Vitacare_Crm_Conversations_Repo::get( $conversation_id );
		if ( null === $conv ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_not_found', __( 'Conversación no encontrada.', 'vitacare-crm' ), 404 );
		}
		if ( ( $conv['channel'] ?? '' ) !== 'instagram' ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_invalid_param',
				__( 'Esta conversación no es de Instagram.', 'vitacare-crm' ),
				400
			);
		}

		$igsid = (string) ( $conv['external_contact_id'] ?? '' );
		if ( $igsid === '' ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_invalid_param',
				__( 'Falta IGSID del contacto.', 'vitacare-crm' ),
				400
			);
		}

		$quota_error = self::check_outbound_quota();
		if ( null !== $quota_error ) {
			return $quota_error;
		}

		$ig_id      = Vitacare_Crm_Facebook_Oauth::get_ig_id();
		$page_token = Vitacare_Crm_Facebook_Oauth::get_page_token();
		$version    = Vitacare_Crm_Settings::graph_version();
		$url        = 'https://graph.facebook.com/' . rawurlencode( $version ) . '/' . rawurlencode( $ig_id ) . '/messages';

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $page_token,
					'User-Agent'    => 'VITACARE-CRM/' . VITACARE_CRM_VERSION,
				),
				'body'    => wp_json_encode(
					array(
						'recipient' => array( 'id' => $igsid ),
						'message'   => array( 'text' => $body ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_graph_error',
				__( 'Error de red al enviar por Instagram.', 'vitacare-crm' ),
				502
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			$err = is_array( $data ) && isset( $data['error']['message'] )
				? (string) $data['error']['message']
				: __( 'No se pudo enviar el mensaje por Instagram.', 'vitacare-crm' );
			// Fuera de ventana 24h de mensajería (mismo código 10 que Messenger).
			if ( is_array( $data ) && isset( $data['error']['code'] ) && (int) $data['error']['code'] === 10 ) {
				return Vitacare_Crm_Db::error(
					'vitacare_crm_outside_window',
					__( 'Fuera de la ventana de mensajería de Instagram (24 h). El usuario debe escribir primero.', 'vitacare-crm' ),
					409
				);
			}
			Vitacare_Crm_Logger::error( 'instagram_send_failed', array( 'http' => $code ) );
			return Vitacare_Crm_Db::error( 'vitacare_crm_graph_error', $err, 502 );
		}

		self::register_outbound_send();

		$mid = isset( $data['message_id'] ) ? (string) $data['message_id'] : '';
		if ( $mid === '' ) {
			$mid = 'ig_local_' . wp_generate_uuid4();
		}

		$existing = Vitacare_Crm_Messages_Repo::find_by_external_id( $mid );
		if ( $existing ) {
			return Vitacare_Crm_Messages_Repo::format( $existing );
		}

		$created = current_time( 'mysql' );
		$insert  = Vitacare_Crm_Messages_Repo::insert_message(
			array(
				'conversation_id'     => $conversation_id,
				'direction'           => 'outbound',
				'sender_type'         => 'staff',
				'message_type'        => 'text',
				'body'                => $body,
				'external_message_id' => $mid,
				'delivery_status'     => 'sent',
				'created_at'          => $created,
			)
		);

		if ( is_int( $insert ) && $insert > 0 ) {
			Vitacare_Crm_Conversations_Repo::touch_after_message( $conversation_id, $created, false );
			$row = Vitacare_Crm_Messages_Repo::find_by_external_id( $mid );
			if ( $row ) {
				return Vitacare_Crm_Messages_Repo::format( $row );
			}
		}

		return array(
			'id'                  => is_int( $insert ) ? $insert : 0,
			'conversation_id'     => $conversation_id,
			'direction'           => 'outbound',
			'sender_type'         => 'staff',
			'message_type'        => 'text',
			'body'                => $body,
			'media_url'           => null,
			'media_mime'          => null,
			'delivery_status'     => 'sent',
			'external_message_id' => $mid,
			'created_at'          => Vitacare_Crm_Db::format_datetime( $created ),
		);
	}

	/**
	 * Cupo mensual de envíos salientes de Instagram Direct (mismo mecanismo
	 * que WhatsApp/Messenger, contador propio de este canal). Bloquea de
	 * verdad al superarse — antes Instagram no tenía ningún control de cupo.
	 */
	private static function check_outbound_quota(): ?WP_Error {
		$month_key = 'vitacare_crm_outbound_count_instagram_' . gmdate( 'Y_m' );
		$count     = (int) get_option( $month_key, 0 );
		$limit     = Vitacare_Crm_Settings::outbound_soft_limit();
		if ( $count < $limit ) {
			return null;
		}
		Vitacare_Crm_Logger::info(
			'outbound_limit_reached',
			array(
				'channel' => 'instagram',
				'count'   => $count,
				'limit'   => $limit,
			)
		);
		return Vitacare_Crm_Db::error(
			'vitacare_crm_quota_exceeded',
			__( 'Se alcanzó el cupo mensual de mensajes salientes de Instagram configurado en Credenciales. Sube el cupo o espera al próximo mes.', 'vitacare-crm' ),
			429
		);
	}

	private static function register_outbound_send(): void {
		$month_key = 'vitacare_crm_outbound_count_instagram_' . gmdate( 'Y_m' );
		update_option( $month_key, (int) get_option( $month_key, 0 ) + 1, false );
	}
}

<?php
defined( 'ABSPATH' ) || exit;

/**
 * C-4: Facebook Messenger (Page) inbound + outbound vía Graph.
 * Payload object=page + entry[].messaging[]
 */
final class Vitacare_Crm_Channel_Messenger {

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
		if ( $object !== 'page' ) {
			++$stats['skipped'];
			return $stats;
		}

		$configured_page = class_exists( 'Vitacare_Crm_Facebook_Oauth' )
			? Vitacare_Crm_Facebook_Oauth::get_page_id()
			: '';

		$entries = isset( $payload['entry'] ) && is_array( $payload['entry'] ) ? $payload['entry'] : array();
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$page_id = (string) ( $entry['id'] ?? '' );
			// Si hay Página conectada en CRM, ignorar eventos de otras páginas.
			if ( $configured_page !== '' && $page_id !== '' && $page_id !== $configured_page ) {
				++$stats['skipped'];
				continue;
			}

			$messaging = isset( $entry['messaging'] ) && is_array( $entry['messaging'] ) ? $entry['messaging'] : array();
			foreach ( $messaging as $event ) {
				if ( ! is_array( $event ) ) {
					++$stats['skipped'];
					continue;
				}
				if ( self::handle_messaging_event( $event, $page_id ) ) {
					// message vs delivery/read.
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
	private static function handle_messaging_event( array $event, string $page_id ): bool {
		// Entregas / lecturas (opcional, por mid).
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
		// Ignorar echos de edición etc. sin mid.
		$mid = (string) ( $message['mid'] ?? '' );
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
			// Inbound del usuario: sender = PSID, recipient = page.
			$direction   = 'inbound';
			$sender_type = 'contact';
			$contact_id  = $sender_id;
		}

		if ( $contact_id === '' || $contact_id === $page_id && ! $is_echo ) {
			// Evitar hilos raros.
			if ( $contact_id === '' ) {
				return false;
			}
		}

		// Texto / adjuntos simples.
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
						'messenger_media_download_failed',
						array(
							'mid' => $mid,
							'msg' => is_wp_error( $downloaded ) ? $downloaded->get_error_message() : 'unknown',
						)
					);
				}
			}
		} elseif ( ! empty( $message['sticker_id'] ) ) {
			$message_type = 'sticker';
			$body         = '[sticker]';
		} else {
			$body = '[message]';
			$message_type = 'other';
		}

		$ts = isset( $event['timestamp'] ) ? (int) $event['timestamp'] : 0;
		// Messenger timestamp suele ser ms.
		if ( $ts > 20000000000 ) {
			$ts = (int) floor( $ts / 1000 );
		}
		$created = $ts > 0
			? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $ts ) )
			: current_time( 'mysql' );

		$contact_name = null;
		// Opcional: no bloquear webhook con Graph extra; nombre se puede enriquecer luego.

		$conv_id = Vitacare_Crm_Conversations_Repo::upsert_contact(
			'facebook',
			$contact_id,
			$contact_name,
			null,
			array(
				'page_id' => $page_id,
				'psid'    => $contact_id,
			)
		);
		if ( $conv_id <= 0 ) {
			throw new RuntimeException( 'messenger_conversation_upsert_failed' );
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
			throw new RuntimeException( 'messenger_message_insert_failed' );
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
	 * Envía texto por Messenger a la conversación facebook.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function send_text( int $conversation_id, string $body ) {
		if ( ! Vitacare_Crm_Settings::flag( 'facebook' ) ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_forbidden',
				__( 'El canal Facebook está desactivado.', 'vitacare-crm' ),
				403
			);
		}
		if ( ! class_exists( 'Vitacare_Crm_Facebook_Oauth' ) || ! Vitacare_Crm_Facebook_Oauth::is_connected() ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_graph_error',
				__( 'No hay Página de Facebook conectada. Ve a CRM → Facebook.', 'vitacare-crm' ),
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
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $body ) > 2000 : strlen( $body ) > 2000 ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_invalid_param',
				__( 'El mensaje supera el máximo de Messenger (2000).', 'vitacare-crm' ),
				400
			);
		}

		$conv = Vitacare_Crm_Conversations_Repo::get( $conversation_id );
		if ( null === $conv ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_not_found', __( 'Conversación no encontrada.', 'vitacare-crm' ), 404 );
		}
		if ( ( $conv['channel'] ?? '' ) !== 'facebook' ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_invalid_param',
				__( 'Esta conversación no es de Facebook Messenger.', 'vitacare-crm' ),
				400
			);
		}

		$psid = (string) ( $conv['external_contact_id'] ?? '' );
		if ( $psid === '' ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_invalid_param',
				__( 'Falta PSID del contacto.', 'vitacare-crm' ),
				400
			);
		}

		$quota_error = self::check_outbound_quota();
		if ( null !== $quota_error ) {
			return $quota_error;
		}

		$page_token = Vitacare_Crm_Facebook_Oauth::get_page_token();
		$version    = Vitacare_Crm_Settings::graph_version();
		$url        = 'https://graph.facebook.com/' . rawurlencode( $version ) . '/me/messages';

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
						'recipient'      => array( 'id' => $psid ),
						'messaging_type' => 'RESPONSE',
						'message'        => array( 'text' => $body ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_graph_error',
				__( 'Error de red al enviar por Messenger.', 'vitacare-crm' ),
				502
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			$err = is_array( $data ) && isset( $data['error']['message'] )
				? (string) $data['error']['message']
				: __( 'No se pudo enviar el mensaje por Messenger.', 'vitacare-crm' );
			// Fuera de ventana 24h Messenger.
			if ( is_array( $data ) && isset( $data['error']['code'] ) && (int) $data['error']['code'] === 10 ) {
				return Vitacare_Crm_Db::error(
					'vitacare_crm_outside_window',
					__( 'Fuera de la ventana de mensajería de Messenger (24 h). El usuario debe escribir primero.', 'vitacare-crm' ),
					409
				);
			}
			Vitacare_Crm_Logger::error( 'messenger_send_failed', array( 'http' => $code ) );
			return Vitacare_Crm_Db::error( 'vitacare_crm_graph_error', $err, 502 );
		}

		self::register_outbound_send();

		$mid = isset( $data['message_id'] ) ? (string) $data['message_id'] : '';
		if ( $mid === '' ) {
			$mid = 'fb_local_' . wp_generate_uuid4();
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
	 * Cupo mensual de envíos salientes de Messenger (mismo mecanismo que
	 * WhatsApp, contador propio de este canal). Bloquea de verdad al
	 * superarse — antes Messenger no tenía ningún control de cupo.
	 */
	private static function check_outbound_quota(): ?WP_Error {
		$month_key = 'vitacare_crm_outbound_count_messenger_' . gmdate( 'Y_m' );
		$count     = (int) get_option( $month_key, 0 );
		$limit     = Vitacare_Crm_Settings::outbound_soft_limit();
		if ( $count < $limit ) {
			return null;
		}
		Vitacare_Crm_Logger::info(
			'outbound_limit_reached',
			array(
				'channel' => 'messenger',
				'count'   => $count,
				'limit'   => $limit,
			)
		);
		return Vitacare_Crm_Db::error(
			'vitacare_crm_quota_exceeded',
			__( 'Se alcanzó el cupo mensual de mensajes salientes de Messenger configurado en Credenciales. Sube el cupo o espera al próximo mes.', 'vitacare-crm' ),
			429
		);
	}

	private static function register_outbound_send(): void {
		$month_key = 'vitacare_crm_outbound_count_messenger_' . gmdate( 'Y_m' );
		update_option( $month_key, (int) get_option( $month_key, 0 ) + 1, false );
	}

	/**
	 * PR-6b: envía un adjunto ya almacenado localmente (ref `local:...`) por
	 * Messenger. Sube el binario vía message_attachments (multipart, sin
	 * exponer URLs propias a Meta) y lo referencia por attachment_id. Si hay
	 * caption, se envía como mensaje de texto de seguimiento (Messenger no
	 * soporta texto + adjunto en un solo mensaje).
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function send_media( int $conversation_id, string $media_ref, string $caption = '' ) {
		if ( ! Vitacare_Crm_Settings::flag( 'facebook' ) ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_forbidden',
				__( 'El canal Facebook está desactivado.', 'vitacare-crm' ),
				403
			);
		}
		if ( ! class_exists( 'Vitacare_Crm_Facebook_Oauth' ) || ! Vitacare_Crm_Facebook_Oauth::is_connected() ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_graph_error',
				__( 'No hay Página de Facebook conectada. Ve a CRM → Facebook.', 'vitacare-crm' ),
				502
			);
		}

		$path = Vitacare_Crm_Media::resolve_path( $media_ref );
		if ( null === $path ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_invalid_param',
				__( 'Adjunto inválido o ya no disponible. Vuelve a adjuntarlo.', 'vitacare-crm' ),
				400
			);
		}

		$conv = Vitacare_Crm_Conversations_Repo::get( $conversation_id );
		if ( null === $conv ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_not_found', __( 'Conversación no encontrada.', 'vitacare-crm' ), 404 );
		}
		if ( ( $conv['channel'] ?? '' ) !== 'facebook' ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_invalid_param',
				__( 'Esta conversación no es de Facebook Messenger.', 'vitacare-crm' ),
				400
			);
		}

		$psid = (string) ( $conv['external_contact_id'] ?? '' );
		if ( $psid === '' ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_invalid_param',
				__( 'Falta PSID del contacto.', 'vitacare-crm' ),
				400
			);
		}

		$quota_error = self::check_outbound_quota();
		if ( null !== $quota_error ) {
			return $quota_error;
		}

		$mime       = Vitacare_Crm_Media::detect_mime_from_path( $path );
		$bucket     = Vitacare_Crm_Media::type_bucket_for_mime( $mime );
		$attach_type = $bucket === 'document' ? 'file' : $bucket; // Messenger no usa "document".
		$page_token = Vitacare_Crm_Facebook_Oauth::get_page_token();
		$version    = Vitacare_Crm_Settings::graph_version();
		$ua         = 'VITACARE-CRM/' . VITACARE_CRM_VERSION;

		// Paso 1: subir el binario como attachment de un solo uso.
		$upload_url   = 'https://graph.facebook.com/' . rawurlencode( $version ) . '/me/message_attachments';
		$message_json = wp_json_encode(
			array(
				'attachment' => array(
					'type'    => $attach_type,
					'payload' => array( 'is_reusable' => false ),
				),
			)
		);
		$mp = Vitacare_Crm_Media::build_multipart_body(
			array( 'message' => (string) $message_json ),
			'filedata',
			$path,
			basename( $path ),
			$mime
		);
		$upload_resp = wp_remote_post(
			$upload_url,
			array(
				'timeout' => 30,
				'headers' => array_merge( $mp['headers'], array( 'Authorization' => 'Bearer ' . $page_token, 'User-Agent' => $ua ) ),
				'body'    => $mp['body'],
			)
		);
		if ( is_wp_error( $upload_resp ) ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_graph_error', __( 'Error de red al subir el adjunto a Messenger.', 'vitacare-crm' ), 502 );
		}
		$upload_code = (int) wp_remote_retrieve_response_code( $upload_resp );
		$upload_data = json_decode( (string) wp_remote_retrieve_body( $upload_resp ), true );
		if ( $upload_code < 200 || $upload_code >= 300 || empty( $upload_data['attachment_id'] ) ) {
			Vitacare_Crm_Logger::error( 'messenger_media_upload_failed', array( 'http' => $upload_code ) );
			return Vitacare_Crm_Db::error( 'vitacare_crm_graph_error', __( 'No se pudo subir el adjunto a Messenger.', 'vitacare-crm' ), 502 );
		}
		$attachment_id = (string) $upload_data['attachment_id'];

		// Paso 2: enviar el mensaje referenciando el attachment_id.
		$send_url  = 'https://graph.facebook.com/' . rawurlencode( $version ) . '/me/messages';
		$send_body = array(
			'recipient'      => array( 'id' => $psid ),
			'messaging_type' => 'RESPONSE',
			'message'        => array(
				'attachment' => array(
					'type'    => $attach_type,
					'payload' => array( 'attachment_id' => $attachment_id ),
				),
			),
		);
		$send_resp = wp_remote_post(
			$send_url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $page_token,
					'User-Agent'    => $ua,
				),
				'body'    => wp_json_encode( $send_body ),
			)
		);
		if ( is_wp_error( $send_resp ) ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_graph_error', __( 'Error de red al enviar el adjunto por Messenger.', 'vitacare-crm' ), 502 );
		}
		$send_code = (int) wp_remote_retrieve_response_code( $send_resp );
		$send_data = json_decode( (string) wp_remote_retrieve_body( $send_resp ), true );
		if ( $send_code < 200 || $send_code >= 300 || ! is_array( $send_data ) ) {
			$err = is_array( $send_data ) && isset( $send_data['error']['message'] )
				? (string) $send_data['error']['message']
				: __( 'No se pudo enviar el adjunto por Messenger.', 'vitacare-crm' );
			if ( is_array( $send_data ) && isset( $send_data['error']['code'] ) && (int) $send_data['error']['code'] === 10 ) {
				return Vitacare_Crm_Db::error(
					'vitacare_crm_outside_window',
					__( 'Fuera de la ventana de mensajería de Messenger (24 h). El usuario debe escribir primero.', 'vitacare-crm' ),
					409
				);
			}
			Vitacare_Crm_Logger::error( 'messenger_media_send_failed', array( 'http' => $send_code ) );
			return Vitacare_Crm_Db::error( 'vitacare_crm_graph_error', $err, 502 );
		}

		$mid = isset( $send_data['message_id'] ) ? (string) $send_data['message_id'] : '';
		if ( $mid === '' ) {
			$mid = 'fb_local_' . wp_generate_uuid4();
		}

		$existing = Vitacare_Crm_Messages_Repo::find_by_external_id( $mid );
		$primary  = null;
		if ( $existing ) {
			$primary = Vitacare_Crm_Messages_Repo::format( $existing );
		} else {
			$created = current_time( 'mysql' );
			$insert  = Vitacare_Crm_Messages_Repo::insert_message(
				array(
					'conversation_id'     => $conversation_id,
					'direction'           => 'outbound',
					'sender_type'         => 'staff',
					'message_type'        => $bucket,
					'body'                => null,
					'media_url'           => $media_ref,
					'media_mime'          => $mime,
					'external_message_id' => $mid,
					'delivery_status'     => 'sent',
					'created_at'          => $created,
				)
			);
			if ( is_int( $insert ) && $insert > 0 ) {
				Vitacare_Crm_Conversations_Repo::touch_after_message( $conversation_id, $created, false );
				$row = Vitacare_Crm_Messages_Repo::find_by_external_id( $mid );
				$primary = $row ? Vitacare_Crm_Messages_Repo::format( $row ) : null;
			}
			if ( null === $primary ) {
				$primary = array(
					'id'                  => 0,
					'conversation_id'     => $conversation_id,
					'direction'           => 'outbound',
					'sender_type'         => 'staff',
					'message_type'        => $bucket,
					'body'                => null,
					'media_url'           => null,
					'media_mime'          => $mime,
					'delivery_status'     => 'sent',
					'external_message_id' => $mid,
					'created_at'          => Vitacare_Crm_Db::format_datetime( $created ),
					'warning'             => 'persisted_partial',
				);
			}
		}

		// Caption opcional: Messenger no admite texto+adjunto en un mensaje;
		// se envía como burbuja de texto aparte. Si falla, no se revierte el envío.
		$caption = trim( $caption );
		if ( $caption !== '' ) {
			$text_result = self::send_text( $conversation_id, $caption );
			if ( is_wp_error( $text_result ) ) {
				Vitacare_Crm_Logger::error(
					'messenger_media_caption_failed',
					array( 'msg' => $text_result->get_error_message() )
				);
			}
		}

		return $primary;
	}

	/**
	 * Suscribe la Página a la app para eventos de mensajería.
	 */
	public static function subscribe_page(): true|WP_Error {
		if ( ! class_exists( 'Vitacare_Crm_Facebook_Oauth' ) || ! Vitacare_Crm_Facebook_Oauth::is_connected() ) {
			return new WP_Error( 'vitacare_crm_fb', __( 'Página no conectada.', 'vitacare-crm' ) );
		}
		$page_id    = Vitacare_Crm_Facebook_Oauth::get_page_id();
		$page_token = Vitacare_Crm_Facebook_Oauth::get_page_token();
		$version    = Vitacare_Crm_Settings::graph_version();
		$url        = 'https://graph.facebook.com/' . rawurlencode( $version ) . '/' . rawurlencode( $page_id ) . '/subscribed_apps';

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $page_token,
					'Content-Type'  => 'application/json',
					'User-Agent'    => 'VITACARE-CRM/' . VITACARE_CRM_VERSION,
				),
				'body'    => wp_json_encode(
					array(
						'subscribed_fields' => array(
							'messages',
							'messaging_postbacks',
							'message_deliveries',
							'message_reads',
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = is_array( $data ) && isset( $data['error']['message'] )
				? (string) $data['error']['message']
				: __( 'No se pudo suscribir la Página a la app.', 'vitacare-crm' );
			return new WP_Error( 'vitacare_crm_fb_subscribe', $msg );
		}
		return true;
	}
}

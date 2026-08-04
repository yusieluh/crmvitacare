<?php
defined( 'ABSPATH' ) || exit;

/**
 * Adapter WhatsApp Cloud API: mensajes inbound/outbound (Coexistence) y statuses.
 */
final class Vitacare_Crm_Channel_Whatsapp {

	/**
	 * Procesa payload ya verificado (JSON array).
	 * Eventos no aplicables / desconocidos: no-op.
	 * Fallos de DB: lanza Exception para que el webhook devuelva 500.
	 *
	 * @param array<string, mixed> $payload Decoded body.
	 * @return array{messages: int, statuses: int, skipped: int}
	 */
	public static function handle_payload( array $payload ): array {
		$stats = array(
			'messages' => 0,
			'statuses' => 0,
			'skipped'  => 0,
		);

		$object = isset( $payload['object'] ) ? (string) $payload['object'] : '';
		if ( $object !== '' && $object !== 'whatsapp_business_account' ) {
			// FB/IG u otros: PR-7. No error.
			++$stats['skipped'];
			return $stats;
		}

		$entries = isset( $payload['entry'] ) && is_array( $payload['entry'] ) ? $payload['entry'] : array();
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$changes = isset( $entry['changes'] ) && is_array( $entry['changes'] ) ? $entry['changes'] : array();
			foreach ( $changes as $change ) {
				if ( ! is_array( $change ) ) {
					continue;
				}
				$value = isset( $change['value'] ) && is_array( $change['value'] ) ? $change['value'] : array();
				self::handle_value( $value, $stats );
			}
		}

		return $stats;
	}

	/**
	 * @param array<string, mixed> $value
	 * @param array{messages: int, statuses: int, skipped: int} $stats
	 */
	private static function handle_value( array $value, array &$stats ): void {
		$metadata = isset( $value['metadata'] ) && is_array( $value['metadata'] ) ? $value['metadata'] : array();
		$business_phone = self::digits( (string) ( $metadata['display_phone_number'] ?? '' ) );
		$phone_number_id = (string) ( $metadata['phone_number_id'] ?? '' );

		// Contacts map wa_id → name.
		$names = array();
		if ( isset( $value['contacts'] ) && is_array( $value['contacts'] ) ) {
			foreach ( $value['contacts'] as $c ) {
				if ( ! is_array( $c ) ) {
					continue;
				}
				$wa = (string) ( $c['wa_id'] ?? '' );
				$nm = isset( $c['profile']['name'] ) ? (string) $c['profile']['name'] : '';
				if ( $wa !== '' ) {
					$names[ $wa ] = $nm;
				}
			}
		}

		if ( isset( $value['messages'] ) && is_array( $value['messages'] ) ) {
			foreach ( $value['messages'] as $msg ) {
				if ( ! is_array( $msg ) ) {
					++$stats['skipped'];
					continue;
				}
				$ok = self::ingest_message( $msg, $names, $business_phone, $phone_number_id );
				if ( $ok ) {
					++$stats['messages'];
				} else {
					++$stats['skipped'];
				}
			}
		}

		if ( isset( $value['statuses'] ) && is_array( $value['statuses'] ) ) {
			foreach ( $value['statuses'] as $st ) {
				if ( ! is_array( $st ) ) {
					++$stats['skipped'];
					continue;
				}
				$ok = self::ingest_status( $st );
				if ( $ok ) {
					++$stats['statuses'];
				} else {
					++$stats['skipped'];
				}
			}
		}
	}

	/**
	 * @param array<string, mixed>  $msg
	 * @param array<string, string> $names
	 */
	private static function ingest_message( array $msg, array $names, string $business_phone, string $phone_number_id ): bool {
		$wamid = (string) ( $msg['id'] ?? '' );
		if ( $wamid === '' ) {
			Vitacare_Crm_Logger::debug( 'wa_message_missing_id' );
			return false;
		}

		// Idempotencia.
		if ( Vitacare_Crm_Messages_Repo::find_by_external_id( $wamid ) ) {
			Vitacare_Crm_Logger::debug( 'wa_message_dedupe', array( 'wamid' => $wamid ) );
			return true; // already stored — count as handled
		}

		$from = self::digits( (string) ( $msg['from'] ?? '' ) );
		$to   = self::digits( (string) ( $msg['to'] ?? '' ) );

		$is_from_business = ( $business_phone !== '' && $from !== '' && self::phones_match( $from, $business_phone ) );

		if ( $is_from_business ) {
			$direction   = 'outbound';
			$sender_type = 'staff';
			$contact_id  = $to !== '' ? $to : '';
			// Si no hay `to`, intentar recipient en contexto (algunos payloads).
			if ( $contact_id === '' && isset( $msg['recipient_id'] ) ) {
				$contact_id = self::digits( (string) $msg['recipient_id'] );
			}
			$origin = 'app';
		} else {
			$direction   = 'inbound';
			$sender_type = 'contact';
			$contact_id  = $from;
			$origin      = 'contact';
		}

		if ( $contact_id === '' ) {
			Vitacare_Crm_Logger::debug( 'wa_message_no_contact', array( 'wamid' => $wamid ) );
			return false;
		}

		$contact_name  = $names[ $contact_id ] ?? ( $names[ (string) ( $msg['from'] ?? '' ) ] ?? null );
		$contact_phone = '+' . ltrim( $contact_id, '+' );

		$type = isset( $msg['type'] ) ? sanitize_key( (string) $msg['type'] ) : 'text';
		$body = self::extract_body( $msg, $type );
		$mime = self::extract_mime( $msg, $type );
		// PR-6: descargar el media a almacenamiento propio (opaco); si falla,
		// no se rompe el mensaje — solo queda sin adjunto reproducible.
		$media_id_ref = self::extract_media_id( $msg, $type );
		$media_ref    = null;
		if ( null !== $media_id_ref && class_exists( 'Vitacare_Crm_Media' ) ) {
			$raw_id     = substr( $media_id_ref, 5 ); // quita el prefijo "meta:"
			$downloaded = Vitacare_Crm_Media::download_whatsapp_media( $raw_id );
			if ( is_array( $downloaded ) ) {
				$media_ref = $downloaded['ref'];
				if ( $downloaded['mime'] !== '' ) {
					$mime = $downloaded['mime'];
				}
			} else {
				Vitacare_Crm_Logger::error(
					'wa_media_download_failed',
					array(
						'wamid' => $wamid,
						'msg'   => is_wp_error( $downloaded ) ? $downloaded->get_error_message() : 'unknown',
					)
				);
			}
		}

		$ts = isset( $msg['timestamp'] ) ? (int) $msg['timestamp'] : 0;
		$created = $ts > 0 ? gmdate( 'Y-m-d H:i:s', $ts ) : current_time( 'mysql', true );
		// Guardar en hora local WP.
		if ( $ts > 0 ) {
			$created = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $ts ) );
		} else {
			$created = current_time( 'mysql' );
		}

		$conv_id = Vitacare_Crm_Conversations_Repo::upsert_whatsapp_contact(
			$contact_id,
			is_string( $contact_name ) ? $contact_name : null,
			$contact_phone,
			array(
				'phone_number_id' => $phone_number_id,
				'last_origin'     => $origin,
			)
		);

		if ( $conv_id <= 0 ) {
			throw new RuntimeException( 'conversation_upsert_failed' );
		}

		$message_type = self::map_message_type( $type );
		$inserted     = Vitacare_Crm_Messages_Repo::insert_message(
			array(
				'conversation_id'     => $conv_id,
				'direction'           => $direction,
				'sender_type'         => $sender_type,
				'message_type'        => $message_type,
				'body'                => $body,
				'media_url'           => $media_ref,
				'media_mime'          => $mime,
				'external_message_id' => $wamid,
				'delivery_status'     => $direction === 'outbound' ? 'sent' : null,
				'created_at'          => $created,
			)
		);

		if ( false === $inserted ) {
			// Dedupe race.
			return true;
		}
		if ( $inserted < 0 ) {
			throw new RuntimeException( 'message_insert_failed' );
		}

		Vitacare_Crm_Conversations_Repo::touch_after_message(
			$conv_id,
			$created,
			$direction === 'inbound'
		);

		return true;
	}

	/**
	 * @param array<string, mixed> $st
	 */
	private static function ingest_status( array $st ): bool {
		$wamid  = (string) ( $st['id'] ?? '' );
		$status = isset( $st['status'] ) ? sanitize_key( (string) $st['status'] ) : '';
		if ( $wamid === '' || $status === '' ) {
			return false;
		}

		// Mapear a delivery_status canónico.
		$map = array(
			'sent'      => 'sent',
			'delivered' => 'delivered',
			'read'      => 'read',
			'failed'    => 'failed',
			'deleted'   => 'failed',
		);
		$delivery = $map[ $status ] ?? $status;

		$updated = Vitacare_Crm_Messages_Repo::update_delivery_status( $wamid, $delivery );
		if ( ! $updated ) {
			Vitacare_Crm_Logger::debug(
				'wa_status_no_message',
				array(
					'wamid'  => $wamid,
					'status' => $delivery,
				)
			);
			// No crear mensaje fantasma — 200 vía caller.
			return false;
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $msg
	 */
	private static function extract_body( array $msg, string $type ): ?string {
		switch ( $type ) {
			case 'text':
				return isset( $msg['text']['body'] ) ? (string) $msg['text']['body'] : null;
			case 'button':
				return isset( $msg['button']['text'] ) ? (string) $msg['button']['text'] : null;
			case 'interactive':
				if ( isset( $msg['interactive']['button_reply']['title'] ) ) {
					return (string) $msg['interactive']['button_reply']['title'];
				}
				if ( isset( $msg['interactive']['list_reply']['title'] ) ) {
					return (string) $msg['interactive']['list_reply']['title'];
				}
				return '[interactive]';
			case 'image':
				$cap = isset( $msg['image']['caption'] ) ? (string) $msg['image']['caption'] : '';
				return $cap !== '' ? $cap : '[image]';
			case 'audio':
			case 'voice':
				return '[audio]';
			case 'video':
				$cap = isset( $msg['video']['caption'] ) ? (string) $msg['video']['caption'] : '';
				return $cap !== '' ? $cap : '[video]';
			case 'document':
				$fn = isset( $msg['document']['filename'] ) ? (string) $msg['document']['filename'] : '';
				return $fn !== '' ? $fn : '[document]';
			case 'sticker':
				return '[sticker]';
			case 'location':
				return '[location]';
			case 'contacts':
				return '[contacts]';
			case 'reaction':
				return isset( $msg['reaction']['emoji'] ) ? (string) $msg['reaction']['emoji'] : '[reaction]';
			default:
				return '[' . $type . ']';
		}
	}

	/**
	 * @param array<string, mixed> $msg
	 */
	private static function extract_mime( array $msg, string $type ): ?string {
		foreach ( array( 'image', 'audio', 'video', 'document', 'sticker' ) as $k ) {
			if ( $type === $k || ( $type === 'voice' && $k === 'audio' ) ) {
				$key = $type === 'voice' ? 'audio' : $k;
				if ( isset( $msg[ $key ]['mime_type'] ) ) {
					return (string) $msg[ $key ]['mime_type'];
				}
			}
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $msg
	 */
	private static function extract_media_id( array $msg, string $type ): ?string {
		$map = array(
			'image'    => 'image',
			'audio'    => 'audio',
			'voice'    => 'audio',
			'video'    => 'video',
			'document' => 'document',
			'sticker'  => 'sticker',
		);
		if ( ! isset( $map[ $type ] ) ) {
			return null;
		}
		$key = $map[ $type ];
		if ( isset( $msg[ $key ]['id'] ) ) {
			return 'meta:' . (string) $msg[ $key ]['id'];
		}
		return null;
	}

	private static function map_message_type( string $type ): string {
		$allowed = array( 'text', 'image', 'audio', 'video', 'document', 'template', 'sticker', 'location', 'reaction', 'interactive', 'button' );
		if ( $type === 'voice' ) {
			return 'audio';
		}
		return in_array( $type, $allowed, true ) ? $type : 'other';
	}

	/**
	 * Envía texto por Cloud API y persiste el mensaje outbound (PR-4).
	 *
	 * @return array<string, mixed>|WP_Error mensaje formateado o error.
	 */
	public static function send_text( int $conversation_id, string $body ) {
		if ( ! Vitacare_Crm_Settings::flag( 'whatsapp' ) ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_forbidden',
				__( 'El canal WhatsApp está desactivado en ajustes.', 'vitacare-crm' ),
				403
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
		if ( function_exists( 'mb_strlen' ) ? mb_strlen( $body ) > 4096 : strlen( $body ) > 4096 ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_invalid_param',
				__( 'El mensaje supera el máximo de 4096 caracteres.', 'vitacare-crm' ),
				400
			);
		}

		$conv = Vitacare_Crm_Conversations_Repo::get( $conversation_id );
		if ( null === $conv ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_not_found', __( 'Conversación no encontrada.', 'vitacare-crm' ), 404 );
		}
		if ( ( $conv['channel'] ?? '' ) !== 'whatsapp' ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_invalid_param',
				__( 'Solo se puede enviar por WhatsApp en esta versión.', 'vitacare-crm' ),
				400
			);
		}

		$to = self::digits( (string) ( $conv['external_contact_id'] ?? '' ) );
		if ( $to === '' ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_invalid_param',
				__( 'La conversación no tiene destinatario WhatsApp.', 'vitacare-crm' ),
				400
			);
		}

		$phone_id = Vitacare_Crm_Settings::get( 'phone_number_id' );
		$token    = Vitacare_Crm_Settings::get( 'access_token' );
		if ( $phone_id === '' || $token === '' ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_graph_error',
				__( 'Faltan Access Token o Phone Number ID en ajustes CRM.', 'vitacare-crm' ),
				502
			);
		}

		// Cupo mensual de envíos salientes: bloquea de verdad al superarse
		// (antes solo se registraba en log) — protege de agotar el límite de
		// mensajería de Meta y arriesgar la calidad del número.
		$month_key = 'vitacare_crm_outbound_count_' . gmdate( 'Y_m' );
		$count     = (int) get_option( $month_key, 0 );
		$limit     = Vitacare_Crm_Settings::outbound_soft_limit();
		if ( $count >= $limit ) {
			Vitacare_Crm_Logger::info(
				'outbound_limit_reached',
				array(
					'channel' => 'whatsapp',
					'count'   => $count,
					'limit'   => $limit,
				)
			);
			return Vitacare_Crm_Db::error(
				'vitacare_crm_quota_exceeded',
				__( 'Se alcanzó el cupo mensual de mensajes salientes de WhatsApp configurado en Credenciales. Sube el cupo o espera al próximo mes.', 'vitacare-crm' ),
				429
			);
		}

		$payload = array(
			'messaging_product' => 'whatsapp',
			'recipient_type'    => 'individual',
			'to'                => $to,
			'type'              => 'text',
			'text'              => array(
				'preview_url' => false,
				'body'        => $body,
			),
		);

		$result = Vitacare_Crm_Graph::post( $phone_id . '/messages', $payload );

		if ( ! $result['ok'] ) {
			if ( Vitacare_Crm_Graph::is_outside_window( $result['error_code'], $result['error_message'] ) ) {
				return Vitacare_Crm_Db::error(
					'vitacare_crm_outside_window',
					__( 'Fuera de la ventana de 24 horas. En el MVP no se envían plantillas HSM; el cliente debe escribir primero.', 'vitacare-crm' ),
					409
				);
			}
			if ( Vitacare_Crm_Graph::is_rate_limited( $result['code'], $result['error_code'] ) ) {
				// Reintento diferido si Action Scheduler está disponible (WooCommerce).
				if ( function_exists( 'as_enqueue_async_action' ) ) {
					as_enqueue_async_action(
						'vitacare_crm_retry_outbound_text',
						array(
							'conversation_id' => $conversation_id,
							'body'            => $body,
						),
						'vitacare-crm'
					);
				}
				return Vitacare_Crm_Db::error(
					'vitacare_crm_rate_limited',
					__( 'Límite de envío de Meta. Intenta de nuevo en unos minutos.', 'vitacare-crm' ),
					429
				);
			}
			return Vitacare_Crm_Db::error(
				'vitacare_crm_graph_error',
				__( 'No se pudo enviar el mensaje por WhatsApp. Revisa el token y la configuración Meta.', 'vitacare-crm' ),
				502
			);
		}

		$wamid = '';
		if ( isset( $result['data']['messages'][0]['id'] ) ) {
			$wamid = (string) $result['data']['messages'][0]['id'];
		}
		if ( $wamid === '' ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_graph_error',
				__( 'Meta no devolvió ID de mensaje.', 'vitacare-crm' ),
				502
			);
		}

		// Dedupe si el webhook Coexistence llegó primero.
		$existing = Vitacare_Crm_Messages_Repo::find_by_external_id( $wamid );
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
				'external_message_id' => $wamid,
				'delivery_status'     => 'sent',
				'created_at'          => $created,
			)
		);

		if ( false === $insert ) {
			$again = Vitacare_Crm_Messages_Repo::find_by_external_id( $wamid );
			if ( $again ) {
				return Vitacare_Crm_Messages_Repo::format( $again );
			}
		}
		if ( ! is_int( $insert ) || $insert <= 0 ) {
			// Graph ya envió; persistencia falló — log crítico pero devolver shape mínimo.
			Vitacare_Crm_Logger::error( 'outbound_persist_failed', array( 'wamid' => $wamid ) );
			return array(
				'id'                  => 0,
				'conversation_id'     => $conversation_id,
				'direction'           => 'outbound',
				'sender_type'         => 'staff',
				'message_type'        => 'text',
				'body'                => $body,
				'media_url'           => null,
				'media_mime'          => null,
				'delivery_status'     => 'sent',
				'external_message_id' => $wamid,
				'created_at'          => Vitacare_Crm_Db::format_datetime( $created ),
				'warning'             => 'persisted_partial',
			);
		}

		Vitacare_Crm_Conversations_Repo::touch_after_message( $conversation_id, $created, false );
		update_option( $month_key, $count + 1, false );

		$row = Vitacare_Crm_Messages_Repo::find_by_external_id( $wamid );
		if ( $row ) {
			$formatted = Vitacare_Crm_Messages_Repo::format( $row );
			if ( $count + 1 >= $limit ) {
				$formatted['soft_limit_warning'] = true;
			}
			return $formatted;
		}

		return Vitacare_Crm_Db::error(
			'vitacare_crm_graph_error',
			__( 'Mensaje enviado pero no se pudo leer de la base de datos.', 'vitacare-crm' ),
			500
		);
	}

	/**
	 * PR-6b: envía un adjunto ya almacenado localmente (ref `local:...`).
	 * Sube el binario a Graph (multipart, sin exponer URLs propias a Meta)
	 * y reutiliza el mismo ref como media_url del mensaje saliente.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function send_media( int $conversation_id, string $media_ref, string $caption = '' ) {
		if ( ! Vitacare_Crm_Settings::flag( 'whatsapp' ) ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_forbidden',
				__( 'El canal WhatsApp está desactivado en ajustes.', 'vitacare-crm' ),
				403
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
		if ( ( $conv['channel'] ?? '' ) !== 'whatsapp' ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_invalid_param',
				__( 'Solo se puede enviar por WhatsApp en esta versión.', 'vitacare-crm' ),
				400
			);
		}

		$to = self::digits( (string) ( $conv['external_contact_id'] ?? '' ) );
		if ( $to === '' ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_invalid_param',
				__( 'La conversación no tiene destinatario WhatsApp.', 'vitacare-crm' ),
				400
			);
		}

		$phone_id = Vitacare_Crm_Settings::get( 'phone_number_id' );
		$token    = Vitacare_Crm_Settings::get( 'access_token' );
		if ( $phone_id === '' || $token === '' ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_graph_error',
				__( 'Faltan Access Token o Phone Number ID en ajustes CRM.', 'vitacare-crm' ),
				502
			);
		}

		$month_key = 'vitacare_crm_outbound_count_' . gmdate( 'Y_m' );
		$count     = (int) get_option( $month_key, 0 );
		$limit     = Vitacare_Crm_Settings::outbound_soft_limit();
		if ( $count >= $limit ) {
			Vitacare_Crm_Logger::info(
				'outbound_limit_reached',
				array(
					'channel' => 'whatsapp',
					'count'   => $count,
					'limit'   => $limit,
				)
			);
			return Vitacare_Crm_Db::error(
				'vitacare_crm_quota_exceeded',
				__( 'Se alcanzó el cupo mensual de mensajes salientes de WhatsApp configurado en Credenciales. Sube el cupo o espera al próximo mes.', 'vitacare-crm' ),
				429
			);
		}

		$mime   = Vitacare_Crm_Media::detect_mime_from_path( $path );
		$bucket = Vitacare_Crm_Media::type_bucket_for_mime( $mime ); // image|audio|video|document
		$version = Vitacare_Crm_Settings::graph_version();
		$ua      = 'VITACARE-CRM/' . VITACARE_CRM_VERSION;

		// Paso 1: subir el binario a Graph (multipart) -> media id de WhatsApp.
		$upload_url = 'https://graph.facebook.com/' . rawurlencode( $version ) . '/' . rawurlencode( $phone_id ) . '/media';
		$mp         = Vitacare_Crm_Media::build_multipart_body(
			array(
				'messaging_product' => 'whatsapp',
				'type'              => $mime,
			),
			'file',
			$path,
			basename( $path ),
			$mime
		);
		$upload_resp = wp_remote_post(
			$upload_url,
			array(
				'timeout' => 30,
				'headers' => array_merge( $mp['headers'], array( 'Authorization' => 'Bearer ' . $token, 'User-Agent' => $ua ) ),
				'body'    => $mp['body'],
			)
		);
		if ( is_wp_error( $upload_resp ) ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_graph_error', __( 'Error de red al subir el adjunto a WhatsApp.', 'vitacare-crm' ), 502 );
		}
		$upload_code = (int) wp_remote_retrieve_response_code( $upload_resp );
		$upload_data = json_decode( (string) wp_remote_retrieve_body( $upload_resp ), true );
		if ( $upload_code < 200 || $upload_code >= 300 || empty( $upload_data['id'] ) ) {
			Vitacare_Crm_Logger::error( 'wa_media_upload_failed', array( 'http' => $upload_code ) );
			return Vitacare_Crm_Db::error( 'vitacare_crm_graph_error', __( 'No se pudo subir el adjunto a WhatsApp.', 'vitacare-crm' ), 502 );
		}
		$wa_media_id = (string) $upload_data['id'];

		// Paso 2: enviar el mensaje referenciando el media id subido.
		$caption   = trim( $caption );
		$media_obj = array( 'id' => $wa_media_id );
		if ( $caption !== '' && in_array( $bucket, array( 'image', 'video', 'document' ), true ) ) {
			$media_obj['caption'] = $caption;
		}
		if ( $bucket === 'document' ) {
			$media_obj['filename'] = basename( $path );
		}

		$payload = array(
			'messaging_product' => 'whatsapp',
			'recipient_type'    => 'individual',
			'to'                => $to,
			'type'              => $bucket,
			$bucket             => $media_obj,
		);

		$result = Vitacare_Crm_Graph::post( $phone_id . '/messages', $payload );

		if ( ! $result['ok'] ) {
			if ( Vitacare_Crm_Graph::is_outside_window( $result['error_code'], $result['error_message'] ) ) {
				return Vitacare_Crm_Db::error(
					'vitacare_crm_outside_window',
					__( 'Fuera de la ventana de 24 horas. El cliente debe escribir primero.', 'vitacare-crm' ),
					409
				);
			}
			if ( Vitacare_Crm_Graph::is_rate_limited( $result['code'], $result['error_code'] ) ) {
				return Vitacare_Crm_Db::error(
					'vitacare_crm_rate_limited',
					__( 'Límite de envío de Meta. Intenta de nuevo en unos minutos.', 'vitacare-crm' ),
					429
				);
			}
			return Vitacare_Crm_Db::error(
				'vitacare_crm_graph_error',
				__( 'No se pudo enviar el adjunto por WhatsApp. Revisa el token y la configuración Meta.', 'vitacare-crm' ),
				502
			);
		}

		$wamid = isset( $result['data']['messages'][0]['id'] ) ? (string) $result['data']['messages'][0]['id'] : '';
		if ( $wamid === '' ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_graph_error', __( 'Meta no devolvió ID de mensaje.', 'vitacare-crm' ), 502 );
		}

		$existing = Vitacare_Crm_Messages_Repo::find_by_external_id( $wamid );
		if ( $existing ) {
			return Vitacare_Crm_Messages_Repo::format( $existing );
		}

		$created = current_time( 'mysql' );
		$insert  = Vitacare_Crm_Messages_Repo::insert_message(
			array(
				'conversation_id'     => $conversation_id,
				'direction'           => 'outbound',
				'sender_type'         => 'staff',
				'message_type'        => $bucket,
				'body'                => $caption !== '' ? $caption : null,
				'media_url'           => $media_ref,
				'media_mime'          => $mime,
				'external_message_id' => $wamid,
				'delivery_status'     => 'sent',
				'created_at'          => $created,
			)
		);

		update_option( $month_key, $count + 1, false );

		if ( is_int( $insert ) && $insert > 0 ) {
			Vitacare_Crm_Conversations_Repo::touch_after_message( $conversation_id, $created, false );
			$row = Vitacare_Crm_Messages_Repo::find_by_external_id( $wamid );
			if ( $row ) {
				return Vitacare_Crm_Messages_Repo::format( $row );
			}
		}

		return array(
			'id'                  => 0,
			'conversation_id'     => $conversation_id,
			'direction'           => 'outbound',
			'sender_type'         => 'staff',
			'message_type'        => $bucket,
			'body'                => $caption !== '' ? $caption : null,
			'media_url'           => null,
			'media_mime'          => $mime,
			'delivery_status'     => 'sent',
			'external_message_id' => $wamid,
			'created_at'          => Vitacare_Crm_Db::format_datetime( $created ),
			'warning'             => 'persisted_partial',
		);
	}

	private const OPTION_HEALTH_CACHE = 'vitacare_crm_wa_health_cache';

	/**
	 * Salud del número (quality_rating + tier de límite de mensajería) vía
	 * GET de solo lectura a Graph. Cacheada 15 min para no golpear la API en
	 * cada carga de pantalla admin.
	 *
	 * @return array{available: bool, quality_rating: string|null, messaging_limit: string|null}
	 */
	public static function health(): array {
		$cached = get_transient( self::OPTION_HEALTH_CACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$phone_id = Vitacare_Crm_Settings::get( 'phone_number_id' );
		$token    = Vitacare_Crm_Settings::get( 'access_token' );
		if ( $phone_id === '' || $token === '' || ! class_exists( 'Vitacare_Crm_Graph' ) ) {
			return array(
				'available'       => false,
				'quality_rating'  => null,
				'messaging_limit' => null,
			);
		}

		$result = Vitacare_Crm_Graph::get(
			$phone_id,
			array( 'fields' => 'quality_rating,whatsapp_business_manager_messaging_limit' )
		);

		if ( ! $result['ok'] || ! is_array( $result['data'] ) ) {
			$health = array(
				'available'       => false,
				'quality_rating'  => null,
				'messaging_limit' => null,
			);
			set_transient( self::OPTION_HEALTH_CACHE, $health, 5 * MINUTE_IN_SECONDS );
			return $health;
		}

		$health = array(
			'available'       => true,
			'quality_rating'  => isset( $result['data']['quality_rating'] ) ? (string) $result['data']['quality_rating'] : null,
			'messaging_limit' => isset( $result['data']['whatsapp_business_manager_messaging_limit'] ) ? (string) $result['data']['whatsapp_business_manager_messaging_limit'] : null,
		);
		set_transient( self::OPTION_HEALTH_CACHE, $health, 15 * MINUTE_IN_SECONDS );
		return $health;
	}

	private static function digits( string $s ): string {
		return preg_replace( '/\D+/', '', $s ) ?? '';
	}

	private static function phones_match( string $a, string $b ): bool {
		if ( $a === '' || $b === '' ) {
			return false;
		}
		if ( $a === $b ) {
			return true;
		}
		// Sufijo común (códigos país).
		$min = min( strlen( $a ), strlen( $b ) );
		if ( $min < 8 ) {
			return false;
		}
		return substr( $a, -8 ) === substr( $b, -8 );
	}
}

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
		// media id de Meta se guarda como referencia; descarga en PR-6.
		$media_ref = self::extract_media_id( $msg, $type );

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

<?php
defined( 'ABSPATH' ) || exit;

/**
 * C-5: Gmail OAuth + sync entrante (cron) + envío desde bandeja.
 * Canal conversations.channel = email.
 */
final class Vitacare_Crm_Gmail {

	public const OPTION_CLIENT_ID     = 'vitacare_crm_google_client_id';
	public const OPTION_CLIENT_SECRET = 'vitacare_crm_google_client_secret';
	public const OPTION_ACCESS        = 'vitacare_crm_gmail_access_token';
	public const OPTION_REFRESH       = 'vitacare_crm_gmail_refresh_token';
	public const OPTION_EXPIRES       = 'vitacare_crm_gmail_token_expires';
	public const OPTION_EMAIL         = 'vitacare_crm_gmail_email';
	public const OPTION_CONNECTED_AT  = 'vitacare_crm_gmail_connected_at';
	public const OPTION_LAST_SYNC     = 'vitacare_crm_gmail_last_sync';
	public const TRANSIENT_STATE      = 'vitacare_crm_gmail_oauth_state';
	public const CRON_HOOK            = 'vitacare_crm_gmail_sync';

	public const SCOPES = 'https://www.googleapis.com/auth/gmail.readonly https://www.googleapis.com/auth/gmail.send https://www.googleapis.com/auth/gmail.modify https://www.googleapis.com/auth/userinfo.email';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 26 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_handle_oauth_return' ), 1 );
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_sync' ) );
		add_action( 'init', array( __CLASS__, 'ensure_cron' ) );
	}

	public static function ensure_cron(): void {
		if ( ! self::is_connected() ) {
			return;
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 120, 'vitacare_crm_five_minutes', self::CRON_HOOK );
		}
	}

	public static function register_menu(): void {
		add_submenu_page(
			'vitacare-crm-accounts',
			__( 'Gmail', 'vitacare-crm' ),
			__( 'Gmail', 'vitacare-crm' ),
			'manage_options',
			'vitacare-crm-gmail',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function redirect_uri(): string {
		return admin_url( 'admin.php?page=vitacare-crm-gmail' );
	}

	public static function get_client_id(): string {
		if ( defined( 'VITACARE_CRM_GOOGLE_CLIENT_ID' ) && (string) VITACARE_CRM_GOOGLE_CLIENT_ID !== '' ) {
			return (string) VITACARE_CRM_GOOGLE_CLIENT_ID;
		}
		return (string) get_option( self::OPTION_CLIENT_ID, '' );
	}

	public static function get_client_secret(): string {
		if ( defined( 'VITACARE_CRM_GOOGLE_CLIENT_SECRET' ) && (string) VITACARE_CRM_GOOGLE_CLIENT_SECRET !== '' ) {
			return (string) VITACARE_CRM_GOOGLE_CLIENT_SECRET;
		}
		$raw = (string) get_option( self::OPTION_CLIENT_SECRET, '' );
		return Vitacare_Crm_Settings::read_secret( $raw );
	}

	public static function is_connected(): bool {
		$refresh = Vitacare_Crm_Settings::read_secret( (string) get_option( self::OPTION_REFRESH, '' ) );
		$email   = (string) get_option( self::OPTION_EMAIL, '' );
		return $refresh !== '' && $email !== '';
	}

	public static function get_email(): string {
		return (string) get_option( self::OPTION_EMAIL, '' );
	}

	/**
	 * Access token válido (refresca si hace falta).
	 */
	public static function get_access_token(): string|WP_Error {
		$access  = Vitacare_Crm_Settings::read_secret( (string) get_option( self::OPTION_ACCESS, '' ) );
		$expires = (int) get_option( self::OPTION_EXPIRES, 0 );
		if ( $access !== '' && $expires > time() + 60 ) {
			return $access;
		}
		$refresh = Vitacare_Crm_Settings::read_secret( (string) get_option( self::OPTION_REFRESH, '' ) );
		if ( $refresh === '' ) {
			return new WP_Error( 'vitacare_crm_gmail', __( 'Gmail no conectado.', 'vitacare-crm' ) );
		}
		return self::refresh_access_token( $refresh );
	}

	/**
	 * @return string|WP_Error access token
	 */
	private static function refresh_access_token( string $refresh_token ) {
		$client_id     = self::get_client_id();
		$client_secret = self::get_client_secret();
		if ( $client_id === '' || $client_secret === '' ) {
			return new WP_Error( 'vitacare_crm_gmail', __( 'Faltan Client ID/Secret de Google.', 'vitacare-crm' ) );
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 20,
				'body'    => array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'refresh_token' => $refresh_token,
					'grant_type'    => 'refresh_token',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['access_token'] ) ) {
			$msg = is_array( $data ) && isset( $data['error_description'] )
				? (string) $data['error_description']
				: __( 'No se pudo renovar el token de Gmail.', 'vitacare-crm' );
			return new WP_Error( 'vitacare_crm_gmail_token', $msg );
		}
		$access = (string) $data['access_token'];
		$exp_in = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 3600;
		update_option( self::OPTION_ACCESS, Vitacare_Crm_Settings::store_secret( $access ), false );
		update_option( self::OPTION_EXPIRES, time() + $exp_in, false );
		return $access;
	}

	public static function start_oauth_url(): string|WP_Error {
		$client_id = self::get_client_id();
		if ( $client_id === '' || self::get_client_secret() === '' ) {
			return new WP_Error(
				'vitacare_crm_gmail',
				__( 'Configura Google Client ID y Client Secret (Credenciales o wp-config).', 'vitacare-crm' )
			);
		}
		$state = wp_generate_password( 32, false, false );
		set_transient( self::TRANSIENT_STATE, $state, 600 );

		return add_query_arg(
			array(
				'client_id'     => $client_id,
				'redirect_uri'  => self::redirect_uri(),
				'response_type' => 'code',
				'scope'         => self::SCOPES,
				'access_type'   => 'offline',
				'prompt'        => 'consent',
				'state'         => $state,
			),
			'https://accounts.google.com/o/oauth2/v2/auth'
		);
	}

	public static function maybe_handle_oauth_return(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( $page !== 'vitacare-crm-gmail' ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['error'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$err = sanitize_text_field( wp_unslash( $_GET['error'] ) );
			set_transient(
				'vitacare_crm_gmail_flash',
				array(
					'type' => 'error',
					'msg'  => $err,
				),
				60
			);
			wp_safe_redirect( self::redirect_uri() );
			exit;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['code'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$saved = (string) get_transient( self::TRANSIENT_STATE );
		delete_transient( self::TRANSIENT_STATE );
		if ( $saved === '' || ! hash_equals( $saved, $state ) ) {
			set_transient(
				'vitacare_crm_gmail_flash',
				array(
					'type' => 'error',
					'msg'  => __( 'Estado OAuth inválido. Reintenta conectar Gmail.', 'vitacare-crm' ),
				),
				60
			);
			wp_safe_redirect( self::redirect_uri() );
			exit;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code = sanitize_text_field( wp_unslash( $_GET['code'] ) );
		$r    = self::exchange_code( $code );
		if ( is_wp_error( $r ) ) {
			set_transient(
				'vitacare_crm_gmail_flash',
				array(
					'type' => 'error',
					'msg'  => $r->get_error_message(),
				),
				60
			);
		} else {
			set_transient(
				'vitacare_crm_gmail_flash',
				array(
					'type' => 'success',
					'msg'  => __( 'Gmail conectado. Se sincronizará el buzón en unos minutos.', 'vitacare-crm' ),
				),
				60
			);
			self::ensure_cron();
			// Sync inmediato.
			self::cron_sync();
		}
		wp_safe_redirect( self::redirect_uri() );
		exit;
	}

	/**
	 * @return true|WP_Error
	 */
	private static function exchange_code( string $code ) {
		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 20,
				'body'    => array(
					'code'          => $code,
					'client_id'     => self::get_client_id(),
					'client_secret' => self::get_client_secret(),
					'redirect_uri'  => self::redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['access_token'] ) ) {
			$msg = is_array( $data ) && isset( $data['error_description'] )
				? (string) $data['error_description']
				: __( 'No se obtuvo token de Google.', 'vitacare-crm' );
			return new WP_Error( 'vitacare_crm_gmail_token', $msg );
		}

		$access  = (string) $data['access_token'];
		$refresh = isset( $data['refresh_token'] ) ? (string) $data['refresh_token'] : '';
		$exp_in  = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 3600;

		update_option( self::OPTION_ACCESS, Vitacare_Crm_Settings::store_secret( $access ), false );
		update_option( self::OPTION_EXPIRES, time() + $exp_in, false );
		if ( $refresh !== '' ) {
			update_option( self::OPTION_REFRESH, Vitacare_Crm_Settings::store_secret( $refresh ), false );
		} elseif ( Vitacare_Crm_Settings::read_secret( (string) get_option( self::OPTION_REFRESH, '' ) ) === '' ) {
			return new WP_Error(
				'vitacare_crm_gmail_token',
				__( 'Google no devolvió refresh_token. Revoca el acceso de la app en tu cuenta Google y vuelve a conectar con consent.', 'vitacare-crm' )
			);
		}

		$email = self::fetch_profile_email( $access );
		if ( is_wp_error( $email ) ) {
			return $email;
		}
		update_option( self::OPTION_EMAIL, $email, false );
		update_option( self::OPTION_CONNECTED_AT, current_time( 'mysql' ), false );
		update_option( 'vitacare_crm_feature_email', '1', false );
		return true;
	}

	/**
	 * @return string|WP_Error
	 */
	private static function fetch_profile_email( string $access_token ) {
		// Gmail profile.
		$res = wp_remote_get(
			'https://gmail.googleapis.com/gmail/v1/users/me/profile',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( is_array( $data ) && ! empty( $data['emailAddress'] ) ) {
			return strtolower( (string) $data['emailAddress'] );
		}
		return new WP_Error( 'vitacare_crm_gmail', __( 'No se pudo obtener el email del perfil Gmail.', 'vitacare-crm' ) );
	}

	public static function disconnect(): void {
		delete_option( self::OPTION_ACCESS );
		delete_option( self::OPTION_REFRESH );
		delete_option( self::OPTION_EXPIRES );
		delete_option( self::OPTION_EMAIL );
		delete_option( self::OPTION_CONNECTED_AT );
		// El flag "email" es compartido con Zoho Mail -- solo se apaga si ninguno de los dos sigue conectado.
		$zoho_connected = class_exists( 'Vitacare_Crm_Zoho' ) && Vitacare_Crm_Zoho::is_connected();
		update_option( 'vitacare_crm_feature_email', $zoho_connected ? '1' : '', false );
		$ts = wp_next_scheduled( self::CRON_HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
		}
	}

	public static function cron_sync(): void {
		if ( ! self::is_connected() || ! Vitacare_Crm_Settings::flag( 'email' ) ) {
			return;
		}
		try {
			self::sync_inbox( 30 );
		} catch ( Throwable $e ) {
			Vitacare_Crm_Logger::error( 'gmail_sync_failed', array( 'err' => $e->getMessage() ) );
		}
	}

	/**
	 * Importa mensajes recientes del INBOX.
	 *
	 * @return array{imported: int, skipped: int}|WP_Error
	 */
	public static function sync_inbox( int $max = 25 ) {
		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$list_url = add_query_arg(
			array(
				'maxResults' => min( 50, max( 1, $max ) ),
				'q'          => 'in:inbox newer_than:14d',
			),
			'https://gmail.googleapis.com/gmail/v1/users/me/messages'
		);

		$list_res = wp_remote_get(
			$list_url,
			array(
				'timeout' => 25,
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			)
		);
		if ( is_wp_error( $list_res ) ) {
			return $list_res;
		}
		$list = json_decode( (string) wp_remote_retrieve_body( $list_res ), true );
		if ( ! is_array( $list ) ) {
			return new WP_Error( 'vitacare_crm_gmail', __( 'Respuesta Gmail inválida.', 'vitacare-crm' ) );
		}
		if ( isset( $list['error']['message'] ) ) {
			return new WP_Error( 'vitacare_crm_gmail', (string) $list['error']['message'] );
		}

		$messages = isset( $list['messages'] ) && is_array( $list['messages'] ) ? $list['messages'] : array();
		$imported = 0;
		$skipped  = 0;

		foreach ( $messages as $ref ) {
			if ( ! is_array( $ref ) || empty( $ref['id'] ) ) {
				continue;
			}
			$mid = (string) $ref['id'];
			if ( Vitacare_Crm_Messages_Repo::find_by_external_id( 'gmail:' . $mid ) ) {
				++$skipped;
				continue;
			}
			$ok = self::import_message( $token, $mid );
			if ( $ok ) {
				++$imported;
			} else {
				++$skipped;
			}
		}

		update_option( self::OPTION_LAST_SYNC, current_time( 'mysql' ), false );
		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
		);
	}

	private static function import_message( string $access_token, string $message_id ): bool {
		$url = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/' . rawurlencode( $message_id ) . '?format=full';
		$res = wp_remote_get(
			$url,
			array(
				'timeout' => 25,
				'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return false;
		}
		$msg = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $msg ) || empty( $msg['id'] ) ) {
			return false;
		}

		$headers = self::parse_headers( $msg );
		$from    = $headers['from'] ?? '';
		$to      = $headers['to'] ?? '';
		$subject = $headers['subject'] ?? '';
		$date    = $headers['date'] ?? '';

		$our_email = self::get_email();
		$from_addr = self::extract_email( $from );
		$to_addr   = self::extract_email( $to );

		// Dirección: si From es nuestro buzón → outbound (enviado); si no → inbound.
		$is_from_us = $our_email !== '' && strtolower( $from_addr ) === strtolower( $our_email );
		if ( $is_from_us ) {
			$direction   = 'outbound';
			$sender_type = 'staff';
			$contact     = $to_addr !== '' ? $to_addr : 'unknown';
			$contact_name = self::extract_name( $to );
		} else {
			$direction    = 'inbound';
			$sender_type  = 'contact';
			$contact      = $from_addr !== '' ? $from_addr : 'unknown';
			$contact_name = self::extract_name( $from );
		}

		$body = self::extract_body_text( $msg );
		if ( $subject !== '' ) {
			$body = ( $body !== '' ) ? ( $subject . "\n\n" . $body ) : $subject;
		}

		$created = current_time( 'mysql' );
		if ( $date !== '' ) {
			$ts = strtotime( $date );
			if ( $ts ) {
				$created = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $ts ) );
			}
		} elseif ( ! empty( $msg['internalDate'] ) ) {
			$ms = (int) $msg['internalDate'];
			$created = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) floor( $ms / 1000 ) ) );
		}

		$ext_id = 'gmail:' . (string) $msg['id'];
		if ( Vitacare_Crm_Messages_Repo::find_by_external_id( $ext_id ) ) {
			return false;
		}

		$conv_id = Vitacare_Crm_Conversations_Repo::upsert_contact(
			'email',
			strtolower( $contact ),
			$contact_name !== '' ? $contact_name : null,
			null,
			array(
				'mail_provider' => 'gmail',
				'gmail_thread'  => (string) ( $msg['threadId'] ?? '' ),
				'mailbox'       => $our_email,
			)
		);
		if ( $conv_id <= 0 ) {
			return false;
		}

		$inserted = Vitacare_Crm_Messages_Repo::insert_message(
			array(
				'conversation_id'     => $conv_id,
				'direction'           => $direction,
				'sender_type'         => $sender_type,
				'message_type'        => 'text',
				'body'                => $body,
				'external_message_id' => $ext_id,
				'delivery_status'     => $direction === 'outbound' ? 'sent' : null,
				'created_at'          => $created,
			)
		);
		if ( is_int( $inserted ) && $inserted > 0 ) {
			Vitacare_Crm_Conversations_Repo::touch_after_message( $conv_id, $created, $direction === 'inbound' );
			return true;
		}
		return false === $inserted; // dedupe counts as handled but not imported
	}

	/**
	 * @param array<string, mixed> $msg
	 * @return array<string, string>
	 */
	private static function parse_headers( array $msg ): array {
		$out = array();
		$payload = isset( $msg['payload'] ) && is_array( $msg['payload'] ) ? $msg['payload'] : array();
		$headers = isset( $payload['headers'] ) && is_array( $payload['headers'] ) ? $payload['headers'] : array();
		foreach ( $headers as $h ) {
			if ( ! is_array( $h ) || empty( $h['name'] ) ) {
				continue;
			}
			$name = strtolower( (string) $h['name'] );
			$out[ $name ] = (string) ( $h['value'] ?? '' );
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $msg
	 */
	private static function extract_body_text( array $msg ): string {
		$payload = isset( $msg['payload'] ) && is_array( $msg['payload'] ) ? $msg['payload'] : array();
		$text    = self::walk_parts_for_text( $payload );
		if ( $text !== '' ) {
			return $text;
		}
		// snippet fallback.
		return isset( $msg['snippet'] ) ? html_entity_decode( (string) $msg['snippet'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : '';
	}

	/**
	 * @param array<string, mixed> $part
	 */
	private static function walk_parts_for_text( array $part ): string {
		$mime = isset( $part['mimeType'] ) ? (string) $part['mimeType'] : '';
		if ( $mime === 'text/plain' && ! empty( $part['body']['data'] ) ) {
			return self::b64url_decode( (string) $part['body']['data'] );
		}
		if ( ! empty( $part['parts'] ) && is_array( $part['parts'] ) ) {
			$plain = '';
			$html  = '';
			foreach ( $part['parts'] as $p ) {
				if ( ! is_array( $p ) ) {
					continue;
				}
				$m = isset( $p['mimeType'] ) ? (string) $p['mimeType'] : '';
				if ( $m === 'text/plain' && ! empty( $p['body']['data'] ) ) {
					$plain = self::b64url_decode( (string) $p['body']['data'] );
				} elseif ( $m === 'text/html' && ! empty( $p['body']['data'] ) ) {
					$html = self::b64url_decode( (string) $p['body']['data'] );
				} elseif ( ! empty( $p['parts'] ) ) {
					$nested = self::walk_parts_for_text( $p );
					if ( $nested !== '' && $plain === '' ) {
						$plain = $nested;
					}
				}
			}
			if ( $plain !== '' ) {
				return $plain;
			}
			if ( $html !== '' ) {
				return wp_strip_all_tags( $html );
			}
		}
		if ( ! empty( $part['body']['data'] ) && str_starts_with( $mime, 'text/' ) ) {
			$raw = self::b64url_decode( (string) $part['body']['data'] );
			return $mime === 'text/html' ? wp_strip_all_tags( $raw ) : $raw;
		}
		return '';
	}

	private static function b64url_decode( string $data ): string {
		$b64 = strtr( $data, '-_', '+/' );
		$pad = strlen( $b64 ) % 4;
		if ( $pad ) {
			$b64 .= str_repeat( '=', 4 - $pad );
		}
		$out = base64_decode( $b64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return false === $out ? '' : $out;
	}

	private static function extract_email( string $header_value ): string {
		if ( preg_match( '/<([^>]+)>/', $header_value, $m ) ) {
			return strtolower( trim( $m[1] ) );
		}
		if ( preg_match( '/[\w.+-]+@[\w.-]+\.\w+/', $header_value, $m ) ) {
			return strtolower( $m[0] );
		}
		return strtolower( trim( $header_value ) );
	}

	private static function extract_name( string $header_value ): string {
		if ( preg_match( '/^"?([^"<]+)"?\s*</', $header_value, $m ) ) {
			return trim( $m[1], " \t\"" );
		}
		return '';
	}

	/**
	 * Envío saliente Gmail.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function send_text( int $conversation_id, string $body ) {
		if ( ! Vitacare_Crm_Settings::flag( 'email' ) || ! self::is_connected() ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_forbidden',
				__( 'Gmail no está conectado o el canal email está off.', 'vitacare-crm' ),
				403
			);
		}
		$body = trim( $body );
		if ( $body === '' ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_invalid_param', __( 'El mensaje no puede estar vacío.', 'vitacare-crm' ), 400 );
		}

		$conv = Vitacare_Crm_Conversations_Repo::get( $conversation_id );
		if ( null === $conv || ( $conv['channel'] ?? '' ) !== 'email' ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_not_found', __( 'Conversación de email no encontrada.', 'vitacare-crm' ), 404 );
		}

		$to = (string) ( $conv['external_contact_id'] ?? '' );
		if ( $to === '' || ! is_email( $to ) ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_invalid_param', __( 'Destinatario de email inválido.', 'vitacare-crm' ), 400 );
		}

		$from = self::get_email();
		$subject = 'Re: VITACARE';
		// Subject line from last preview if looks like subject\n\n
		if ( ! empty( $conv['last_message_preview'] ) ) {
			$prev = (string) $conv['last_message_preview'];
			$line = strtok( $prev, "\n" );
			if ( is_string( $line ) && $line !== '' && strlen( $line ) < 200 ) {
				$subject = str_starts_with( strtolower( $line ), 're:' ) ? $line : ( 'Re: ' . $line );
			}
		}

		$raw_mime  = 'From: ' . $from . "\r\n";
		$raw_mime .= 'To: ' . $to . "\r\n";
		$raw_mime .= 'Subject: ' . self::encode_subject( $subject ) . "\r\n";
		$raw_mime .= "MIME-Version: 1.0\r\n";
		$raw_mime .= "Content-Type: text/plain; charset=UTF-8\r\n";
		$raw_mime .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
		$raw_mime .= $body;

		$raw_b64 = rtrim( strtr( base64_encode( $raw_mime ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_post(
			'https://gmail.googleapis.com/gmail/v1/users/me/messages/send',
			array(
				'timeout' => 25,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'raw' => $raw_b64 ) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_graph_error', __( 'Error de red al enviar email.', 'vitacare-crm' ), 502 );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) || empty( $data['id'] ) ) {
			$msg = is_array( $data ) && isset( $data['error']['message'] )
				? (string) $data['error']['message']
				: __( 'Gmail rechazó el envío.', 'vitacare-crm' );
			return Vitacare_Crm_Db::error( 'vitacare_crm_graph_error', $msg, 502 );
		}

		$ext_id  = 'gmail:' . (string) $data['id'];
		$created = current_time( 'mysql' );
		$insert  = Vitacare_Crm_Messages_Repo::insert_message(
			array(
				'conversation_id'     => $conversation_id,
				'direction'           => 'outbound',
				'sender_type'         => 'staff',
				'message_type'        => 'text',
				'body'                => $body,
				'external_message_id' => $ext_id,
				'delivery_status'     => 'sent',
				'created_at'          => $created,
			)
		);
		Vitacare_Crm_Conversations_Repo::touch_after_message( $conversation_id, $created, false );

		if ( is_int( $insert ) && $insert > 0 ) {
			$row = Vitacare_Crm_Messages_Repo::find_by_external_id( $ext_id );
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
			'external_message_id' => $ext_id,
			'created_at'          => Vitacare_Crm_Db::format_datetime( $created ),
		);
	}

	/**
	 * D-26 Fase 4: envío suelto de campaña (sin conversación/hilo de
	 * soporte asociado, a diferencia de send_text()). El llamador es
	 * responsable de verificar opt-in antes de invocar esto.
	 *
	 * @return true|WP_Error
	 */
	public static function send_campaign_email( string $to_email, string $subject, string $body_plain ) {
		if ( ! self::is_connected() ) {
			return new WP_Error( 'vitacare_crm_forbidden', __( 'Gmail no está conectado.', 'vitacare-crm' ) );
		}
		if ( ! is_email( $to_email ) ) {
			return new WP_Error( 'vitacare_crm_invalid_param', __( 'Destinatario de email inválido.', 'vitacare-crm' ) );
		}

		$raw_mime  = 'From: ' . self::get_email() . "\r\n";
		$raw_mime .= 'To: ' . $to_email . "\r\n";
		$raw_mime .= 'Subject: ' . self::encode_subject( $subject ) . "\r\n";
		$raw_mime .= "MIME-Version: 1.0\r\n";
		$raw_mime .= "Content-Type: text/plain; charset=UTF-8\r\n";
		$raw_mime .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
		$raw_mime .= $body_plain;

		$raw_b64 = rtrim( strtr( base64_encode( $raw_mime ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_post(
			'https://gmail.googleapis.com/gmail/v1/users/me/messages/send',
			array(
				'timeout' => 25,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'raw' => $raw_b64 ) ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'vitacare_crm_graph_error', __( 'Error de red al enviar el correo.', 'vitacare-crm' ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) || empty( $data['id'] ) ) {
			$msg = is_array( $data ) && isset( $data['error']['message'] )
				? (string) $data['error']['message']
				: __( 'Gmail rechazó el envío.', 'vitacare-crm' );
			return new WP_Error( 'vitacare_crm_graph_error', $msg );
		}
		return true;
	}

	private static function encode_subject( string $subject ): string {
		if ( preg_match( '/[^\x20-\x7E]/', $subject ) ) {
			return '=?UTF-8?B?' . base64_encode( $subject ) . '?='; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}
		return $subject;
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso.', 'vitacare-crm' ) );
		}

		if ( isset( $_POST['vitacare_crm_gmail_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vitacare_crm_gmail_nonce'] ) ), 'vitacare_crm_gmail' )
		) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$action = isset( $_POST['gmail_action'] ) ? sanitize_key( wp_unslash( $_POST['gmail_action'] ) ) : '';
			if ( $action === 'save_client' ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$cid = isset( $_POST['google_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['google_client_id'] ) ) : '';
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$sec = isset( $_POST['google_client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['google_client_secret'] ) ) : '';
				if ( ! defined( 'VITACARE_CRM_GOOGLE_CLIENT_ID' ) ) {
					update_option( self::OPTION_CLIENT_ID, $cid, false );
				}
				if ( $sec !== '' && ! defined( 'VITACARE_CRM_GOOGLE_CLIENT_SECRET' ) ) {
					update_option( self::OPTION_CLIENT_SECRET, Vitacare_Crm_Settings::store_secret( $sec ), false );
				}
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Credenciales Google guardadas.', 'vitacare-crm' ) . '</p></div>';
			} elseif ( $action === 'connect' ) {
				$url = self::start_oauth_url();
				if ( is_wp_error( $url ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html( $url->get_error_message() ) . '</p></div>';
				} else {
					wp_safe_redirect( $url );
					exit;
				}
			} elseif ( $action === 'disconnect' ) {
				self::disconnect();
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Gmail desconectado.', 'vitacare-crm' ) . '</p></div>';
			} elseif ( $action === 'sync' ) {
				$r = self::sync_inbox( 40 );
				if ( is_wp_error( $r ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html( $r->get_error_message() ) . '</p></div>';
				} else {
					printf(
						'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
						esc_html(
							sprintf(
								/* translators: 1: imported 2: skipped */
								__( 'Sync: %1$d importados, %2$d omitidos.', 'vitacare-crm' ),
								(int) $r['imported'],
								(int) $r['skipped']
							)
						)
					);
				}
			}
		}

		$flash = get_transient( 'vitacare_crm_gmail_flash' );
		if ( is_array( $flash ) ) {
			delete_transient( 'vitacare_crm_gmail_flash' );
			$class = ( $flash['type'] ?? '' ) === 'error' ? 'notice-error' : 'notice-success';
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( (string) ( $flash['msg'] ?? '' ) ) . '</p></div>';
		}

		$connected = self::is_connected();
		$redirect  = self::redirect_uri();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Gmail — Conectar y sincronizar (opcional, secundario)', 'vitacare-crm' ); ?></h1>
			<div class="vcrm-callout" style="background:#f0f6fc;border-left:4px solid #2271b1;padding:12px 14px;margin:12px 0">
				<p style="margin:0">
					<?php
					echo esc_html__(
						'OAuth oficial de Google. El correo institucional principal es Zoho Mail; Gmail es un buzón secundario/opcional bajo el mismo canal «Correo» de la bandeja /crm. Los correos del INBOX se importan igual y se puede responder desde el compositor.',
						'vitacare-crm'
					);
					?>
				</p>
			</div>

			<h2><?php echo esc_html__( '1. Google Cloud', 'vitacare-crm' ); ?></h2>
			<ol>
				<li><?php echo esc_html__( 'Crear proyecto en Google Cloud Console y activar Gmail API.', 'vitacare-crm' ); ?></li>
				<li><?php echo esc_html__( 'Credenciales → OAuth 2.0 Client ID (aplicación web).', 'vitacare-crm' ); ?></li>
				<li>
					<?php echo esc_html__( 'URI de redirección autorizada:', 'vitacare-crm' ); ?>
					<br /><code><?php echo esc_html( $redirect ); ?></code>
				</li>
				<li><?php echo esc_html__( 'Pantalla de consentimiento OAuth (tipo Interno o Externo en prueba).', 'vitacare-crm' ); ?></li>
			</ol>

			<form method="post" style="max-width:640px;background:#fff;border:1px solid #c3c4c7;padding:16px;border-radius:8px">
				<?php wp_nonce_field( 'vitacare_crm_gmail', 'vitacare_crm_gmail_nonce' ); ?>
				<input type="hidden" name="gmail_action" value="save_client" />
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="google_client_id"><?php echo esc_html__( 'Client ID', 'vitacare-crm' ); ?></label></th>
						<td>
							<?php if ( defined( 'VITACARE_CRM_GOOGLE_CLIENT_ID' ) ) : ?>
								<code><?php echo esc_html__( 'wp-config constant', 'vitacare-crm' ); ?></code>
							<?php else : ?>
								<input class="regular-text" type="text" name="google_client_id" id="google_client_id" value="<?php echo esc_attr( self::get_client_id() ); ?>" autocomplete="off" />
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><label for="google_client_secret"><?php echo esc_html__( 'Client Secret', 'vitacare-crm' ); ?></label></th>
						<td>
							<?php if ( defined( 'VITACARE_CRM_GOOGLE_CLIENT_SECRET' ) ) : ?>
								<code><?php echo esc_html__( 'wp-config constant', 'vitacare-crm' ); ?></code>
							<?php else : ?>
								<input class="regular-text" type="password" name="google_client_secret" id="google_client_secret" value="" placeholder="<?php echo self::get_client_secret() !== '' ? esc_attr__( '•••• (dejar vacío para no cambiar)', 'vitacare-crm' ) : ''; ?>" autocomplete="new-password" />
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Guardar Client ID/Secret', 'vitacare-crm' ), 'secondary' ); ?>
			</form>

			<h2><?php echo esc_html__( '2. Conexión', 'vitacare-crm' ); ?></h2>
			<?php if ( $connected ) : ?>
				<p>
					<span class="vcrm-status" style="background:#d5f5e3;color:#0a6b2d;padding:2px 8px;border-radius:999px;font-weight:600">
						<?php echo esc_html__( 'Conectado', 'vitacare-crm' ); ?>
					</span>
					<strong><?php echo esc_html( self::get_email() ); ?></strong>
				</p>
				<p class="description">
					<?php
					echo esc_html__( 'Último sync:', 'vitacare-crm' ) . ' ';
					echo esc_html( (string) get_option( self::OPTION_LAST_SYNC, '—' ) );
					?>
				</p>
				<form method="post" style="display:inline-block;margin-right:8px">
					<?php wp_nonce_field( 'vitacare_crm_gmail', 'vitacare_crm_gmail_nonce' ); ?>
					<input type="hidden" name="gmail_action" value="sync" />
					<?php submit_button( __( 'Sincronizar ahora', 'vitacare-crm' ), 'primary', 'submit', false ); ?>
				</form>
				<form method="post" style="display:inline-block">
					<?php wp_nonce_field( 'vitacare_crm_gmail', 'vitacare_crm_gmail_nonce' ); ?>
					<input type="hidden" name="gmail_action" value="disconnect" />
					<?php submit_button( __( 'Desconectar Gmail', 'vitacare-crm' ), 'delete', 'submit', false ); ?>
				</form>
			<?php else : ?>
				<form method="post">
					<?php wp_nonce_field( 'vitacare_crm_gmail', 'vitacare_crm_gmail_nonce' ); ?>
					<input type="hidden" name="gmail_action" value="connect" />
					<?php submit_button( __( 'Conectar con Google (Gmail)', 'vitacare-crm' ), 'primary' ); ?>
				</form>
			<?php endif; ?>

			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=vitacare-crm-accounts' ) ); ?>"><?php echo esc_html__( '← Cuentas conectadas', 'vitacare-crm' ); ?></a>
				|
				<a href="<?php echo esc_url( home_url( '/' . VITACARE_CRM_PAGE_SLUG . '/' ) ); ?>"><?php echo esc_html__( 'Bandeja /crm', 'vitacare-crm' ); ?></a>
			</p>
		</div>
		<?php
	}
}

// Intervalo cron 5 minutos.
add_filter(
	'cron_schedules',
	static function ( array $schedules ): array {
		if ( ! isset( $schedules['vitacare_crm_five_minutes'] ) ) {
			$schedules['vitacare_crm_five_minutes'] = array(
				'interval' => 300,
				'display'  => 'VITACARE CRM every 5 minutes',
			);
		}
		return $schedules;
	}
);

<?php
defined( 'ABSPATH' ) || exit;

/**
 * Zoho Mail OAuth + sync entrante (cron) + envío desde bandeja.
 * Segundo proveedor de correo, mismo canal conversations.channel = email
 * que Gmail (D-22 en ESTADO_CRM.md) -- desde la bandeja "Correo" es un solo
 * filtro sin importar cuál de los dos buzones recibió el mensaje. Cada
 * conversación guarda en `meta.mail_provider` cuál buzón la maneja, para que
 * la respuesta salga por el proveedor correcto (ver post_message() en
 * class-vitacare-crm-rest.php).
 */
final class Vitacare_Crm_Zoho {

	public const OPTION_CLIENT_ID     = 'vitacare_crm_zoho_client_id';
	public const OPTION_CLIENT_SECRET = 'vitacare_crm_zoho_client_secret';
	public const OPTION_DOMAIN        = 'vitacare_crm_zoho_domain';
	public const OPTION_ACCESS        = 'vitacare_crm_zoho_access_token';
	public const OPTION_REFRESH       = 'vitacare_crm_zoho_refresh_token';
	public const OPTION_EXPIRES       = 'vitacare_crm_zoho_token_expires';
	public const OPTION_ACCOUNT_ID    = 'vitacare_crm_zoho_account_id';
	public const OPTION_INBOX_FOLDER  = 'vitacare_crm_zoho_inbox_folder_id';
	public const OPTION_EMAIL         = 'vitacare_crm_zoho_email';
	public const OPTION_CONNECTED_AT  = 'vitacare_crm_zoho_connected_at';
	public const OPTION_LAST_SYNC     = 'vitacare_crm_zoho_last_sync';
	public const TRANSIENT_STATE      = 'vitacare_crm_zoho_oauth_state';
	public const CRON_HOOK            = 'vitacare_crm_zoho_sync';

	public const SCOPES = 'ZohoMail.accounts.READ,ZohoMail.folders.READ,ZohoMail.messages.ALL';

	/** Data centers oficiales de Zoho -- el dominio debe coincidir con el de la cuenta. */
	private const DOMAINS = array(
		'com'    => 'Estados Unidos / global (.com)',
		'eu'     => 'Europa (.eu)',
		'in'     => 'India (.in)',
		'com.au' => 'Australia (.com.au)',
		'jp'     => 'Japón (.jp)',
	);

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 27 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_handle_oauth_return' ), 1 );
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_sync' ) );
		add_action( 'init', array( __CLASS__, 'ensure_cron' ) );
	}

	public static function ensure_cron(): void {
		if ( ! self::is_connected() ) {
			return;
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			// Mismo intervalo 'vitacare_crm_five_minutes' que registra class-vitacare-crm-gmail.php.
			wp_schedule_event( time() + 150, 'vitacare_crm_five_minutes', self::CRON_HOOK );
		}
	}

	public static function register_menu(): void {
		add_submenu_page(
			'vitacare-crm-accounts',
			__( 'Zoho Mail', 'vitacare-crm' ),
			__( 'Zoho Mail', 'vitacare-crm' ),
			'manage_options',
			'vitacare-crm-zoho',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function redirect_uri(): string {
		return admin_url( 'admin.php?page=vitacare-crm-zoho' );
	}

	public static function get_domain(): string {
		$d = (string) get_option( self::OPTION_DOMAIN, 'com' );
		return isset( self::DOMAINS[ $d ] ) ? $d : 'com';
	}

	private static function accounts_base(): string {
		return 'https://accounts.zoho.' . self::get_domain();
	}

	private static function api_base(): string {
		return 'https://mail.zoho.' . self::get_domain();
	}

	public static function get_client_id(): string {
		return (string) get_option( self::OPTION_CLIENT_ID, '' );
	}

	public static function get_client_secret(): string {
		$raw = (string) get_option( self::OPTION_CLIENT_SECRET, '' );
		return Vitacare_Crm_Settings::read_secret( $raw );
	}

	public static function is_connected(): bool {
		$refresh = Vitacare_Crm_Settings::read_secret( (string) get_option( self::OPTION_REFRESH, '' ) );
		return $refresh !== '' && self::get_email() !== '' && self::get_account_id() !== '';
	}

	public static function get_email(): string {
		return (string) get_option( self::OPTION_EMAIL, '' );
	}

	public static function get_account_id(): string {
		return (string) get_option( self::OPTION_ACCOUNT_ID, '' );
	}

	/**
	 * @return string|WP_Error access token vigente (renueva si hace falta)
	 */
	public static function get_access_token() {
		$access  = Vitacare_Crm_Settings::read_secret( (string) get_option( self::OPTION_ACCESS, '' ) );
		$expires = (int) get_option( self::OPTION_EXPIRES, 0 );
		if ( $access !== '' && $expires > time() + 60 ) {
			return $access;
		}
		$refresh = Vitacare_Crm_Settings::read_secret( (string) get_option( self::OPTION_REFRESH, '' ) );
		if ( $refresh === '' ) {
			return new WP_Error( 'vitacare_crm_zoho', __( 'Zoho Mail no conectado.', 'vitacare-crm' ) );
		}
		return self::refresh_access_token( $refresh );
	}

	/**
	 * @return string|WP_Error
	 */
	private static function refresh_access_token( string $refresh_token ) {
		$client_id     = self::get_client_id();
		$client_secret = self::get_client_secret();
		if ( $client_id === '' || $client_secret === '' ) {
			return new WP_Error( 'vitacare_crm_zoho', __( 'Faltan Client ID/Secret de Zoho.', 'vitacare-crm' ) );
		}
		$response = wp_remote_post(
			self::accounts_base() . '/oauth/v2/token',
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
			$msg = is_array( $data ) && isset( $data['error'] ) ? (string) $data['error'] : __( 'No se pudo renovar el token de Zoho.', 'vitacare-crm' );
			return new WP_Error( 'vitacare_crm_zoho_token', $msg );
		}
		$access = (string) $data['access_token'];
		$exp_in = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 3600;
		update_option( self::OPTION_ACCESS, Vitacare_Crm_Settings::store_secret( $access ), false );
		update_option( self::OPTION_EXPIRES, time() + $exp_in, false );
		return $access;
	}

	public static function start_oauth_url() {
		$client_id = self::get_client_id();
		if ( $client_id === '' || self::get_client_secret() === '' ) {
			return new WP_Error(
				'vitacare_crm_zoho',
				__( 'Configura Client ID y Client Secret de Zoho (abajo en esta página) antes de conectar.', 'vitacare-crm' )
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
			self::accounts_base() . '/oauth/v2/auth'
		);
	}

	public static function maybe_handle_oauth_return(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( $page !== 'vitacare-crm-zoho' ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['error'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$err = sanitize_text_field( wp_unslash( $_GET['error'] ) );
			self::flash( 'error', $err !== '' ? $err : __( 'Zoho denegó el acceso.', 'vitacare-crm' ) );
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
			self::flash( 'error', __( 'Estado OAuth inválido. Reintenta conectar Zoho Mail.', 'vitacare-crm' ) );
			wp_safe_redirect( self::redirect_uri() );
			exit;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code = sanitize_text_field( wp_unslash( $_GET['code'] ) );
		$r    = self::exchange_code( $code );
		if ( is_wp_error( $r ) ) {
			self::flash( 'error', $r->get_error_message() );
		} else {
			self::flash( 'success', __( 'Zoho Mail conectado. Se sincronizará el buzón en unos minutos.', 'vitacare-crm' ) );
			self::ensure_cron();
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
			self::accounts_base() . '/oauth/v2/token',
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
			$msg = is_array( $data ) && isset( $data['error'] ) ? (string) $data['error'] : __( 'No se obtuvo token de Zoho.', 'vitacare-crm' );
			return new WP_Error( 'vitacare_crm_zoho_token', $msg );
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
				'vitacare_crm_zoho_token',
				__( 'Zoho no devolvió refresh_token. Revoca el acceso de la app en tu cuenta Zoho y vuelve a conectar.', 'vitacare-crm' )
			);
		}

		$account = self::fetch_primary_account( $access );
		if ( is_wp_error( $account ) ) {
			return $account;
		}
		update_option( self::OPTION_ACCOUNT_ID, (string) $account['accountId'], false );
		update_option( self::OPTION_EMAIL, strtolower( (string) $account['email'] ), false );

		$folder_id = self::fetch_inbox_folder_id( $access, (string) $account['accountId'] );
		if ( is_wp_error( $folder_id ) ) {
			return $folder_id;
		}
		update_option( self::OPTION_INBOX_FOLDER, $folder_id, false );

		update_option( self::OPTION_CONNECTED_AT, current_time( 'mysql' ), false );
		update_option( 'vitacare_crm_feature_email', '1', false );
		return true;
	}

	/**
	 * @return array{accountId: string, email: string}|WP_Error
	 */
	private static function fetch_primary_account( string $access_token ) {
		$res = wp_remote_get(
			self::api_base() . '/api/accounts',
			array(
				'timeout' => 20,
				'headers' => array( 'Authorization' => 'Zoho-oauthtoken ' . $access_token ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$list = is_array( $data ) && isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array();
		if ( empty( $list ) || empty( $list[0]['accountId'] ) ) {
			return new WP_Error( 'vitacare_crm_zoho', __( 'No se encontró ninguna cuenta de Zoho Mail para este usuario.', 'vitacare-crm' ) );
		}
		return array(
			'accountId' => (string) $list[0]['accountId'],
			'email'     => (string) ( $list[0]['primaryEmailAddress'] ?? '' ),
		);
	}

	/**
	 * @return string|WP_Error
	 */
	private static function fetch_inbox_folder_id( string $access_token, string $account_id ) {
		$res = wp_remote_get(
			self::api_base() . '/api/accounts/' . rawurlencode( $account_id ) . '/folders',
			array(
				'timeout' => 20,
				'headers' => array( 'Authorization' => 'Zoho-oauthtoken ' . $access_token ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$data    = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$folders = is_array( $data ) && isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array();
		foreach ( $folders as $f ) {
			if ( is_array( $f ) && ( $f['folderType'] ?? '' ) === 'Inbox' && ! empty( $f['folderId'] ) ) {
				return (string) $f['folderId'];
			}
		}
		return new WP_Error( 'vitacare_crm_zoho', __( 'No se encontró la carpeta Inbox en la cuenta de Zoho Mail.', 'vitacare-crm' ) );
	}

	public static function disconnect(): void {
		delete_option( self::OPTION_ACCESS );
		delete_option( self::OPTION_REFRESH );
		delete_option( self::OPTION_EXPIRES );
		delete_option( self::OPTION_ACCOUNT_ID );
		delete_option( self::OPTION_INBOX_FOLDER );
		delete_option( self::OPTION_EMAIL );
		delete_option( self::OPTION_CONNECTED_AT );
		// El flag "email" es compartido con Gmail -- solo se apaga si ninguno de los dos sigue conectado.
		$gmail_connected = class_exists( 'Vitacare_Crm_Gmail' ) && Vitacare_Crm_Gmail::is_connected();
		update_option( 'vitacare_crm_feature_email', $gmail_connected ? '1' : '', false );
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
			self::sync_inbox( 25 );
		} catch ( Throwable $e ) {
			Vitacare_Crm_Logger::error( 'zoho_sync_failed', array( 'err' => $e->getMessage() ) );
		}
	}

	/**
	 * Importa mensajes recientes del Inbox.
	 *
	 * @return array{imported: int, skipped: int}|WP_Error
	 */
	public static function sync_inbox( int $max = 25 ) {
		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$account_id = self::get_account_id();
		$folder_id  = (string) get_option( self::OPTION_INBOX_FOLDER, '' );
		if ( $folder_id === '' ) {
			$folder_id = self::fetch_inbox_folder_id( $token, $account_id );
			if ( is_wp_error( $folder_id ) ) {
				return $folder_id;
			}
			update_option( self::OPTION_INBOX_FOLDER, $folder_id, false );
		}

		$list_url = add_query_arg(
			array(
				'folderId' => $folder_id,
				'start'    => 1,
				'limit'    => min( 50, max( 1, $max ) ),
				'status'   => 'all',
			),
			self::api_base() . '/api/accounts/' . rawurlencode( $account_id ) . '/messages/view'
		);
		$list_res = wp_remote_get(
			$list_url,
			array(
				'timeout' => 25,
				'headers' => array( 'Authorization' => 'Zoho-oauthtoken ' . $token ),
			)
		);
		if ( is_wp_error( $list_res ) ) {
			return $list_res;
		}
		$list = json_decode( (string) wp_remote_retrieve_body( $list_res ), true );
		if ( ! is_array( $list ) || ! isset( $list['data'] ) || ! is_array( $list['data'] ) ) {
			$msg = is_array( $list ) && isset( $list['status']['description'] ) ? (string) $list['status']['description'] : __( 'Respuesta Zoho Mail inválida.', 'vitacare-crm' );
			return new WP_Error( 'vitacare_crm_zoho', $msg );
		}

		$imported = 0;
		$skipped  = 0;
		foreach ( $list['data'] as $row ) {
			if ( ! is_array( $row ) || empty( $row['messageId'] ) ) {
				continue;
			}
			$mid    = (string) $row['messageId'];
			$ext_id = 'zoho:' . $mid;
			if ( Vitacare_Crm_Messages_Repo::find_by_external_id( $ext_id ) ) {
				++$skipped;
				continue;
			}
			$ok = self::import_message( $token, $account_id, $folder_id, $row );
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

	/**
	 * @param array<string, mixed> $summary Fila de la lista (messages/view).
	 */
	private static function import_message( string $access_token, string $account_id, string $folder_id, array $summary ): bool {
		$mid    = (string) $summary['messageId'];
		$ext_id = 'zoho:' . $mid;

		$content_res = wp_remote_get(
			self::api_base() . '/api/accounts/' . rawurlencode( $account_id ) . '/folders/' . rawurlencode( $folder_id ) . '/messages/' . rawurlencode( $mid ) . '/content',
			array(
				'timeout' => 25,
				'headers' => array( 'Authorization' => 'Zoho-oauthtoken ' . $access_token ),
			)
		);
		$body = '';
		if ( ! is_wp_error( $content_res ) ) {
			$content_data = json_decode( (string) wp_remote_retrieve_body( $content_res ), true );
			$html         = is_array( $content_data ) && isset( $content_data['data']['content'] ) ? (string) $content_data['data']['content'] : '';
			if ( $html !== '' ) {
				$body = wp_strip_all_tags( html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			}
		}
		if ( $body === '' ) {
			$body = isset( $summary['summary'] ) ? (string) $summary['summary'] : '';
		}

		$subject = isset( $summary['subject'] ) ? (string) $summary['subject'] : '';
		if ( $subject !== '' ) {
			$body = ( $body !== '' ) ? ( $subject . "\n\n" . $body ) : $subject;
		}

		$from_addr = self::extract_email( (string) ( $summary['fromAddress'] ?? '' ) );
		$to_addr   = self::extract_email( (string) ( $summary['toAddress'] ?? '' ) );
		$our_email = self::get_email();
		$is_from_us = $our_email !== '' && strtolower( $from_addr ) === strtolower( $our_email );

		if ( $is_from_us ) {
			$direction    = 'outbound';
			$sender_type  = 'staff';
			$contact      = $to_addr !== '' ? $to_addr : 'unknown';
			$contact_name = self::extract_name( (string) ( $summary['toAddress'] ?? '' ) );
		} else {
			$direction    = 'inbound';
			$sender_type  = 'contact';
			$contact      = $from_addr !== '' ? $from_addr : 'unknown';
			$contact_name = self::extract_name( (string) ( $summary['fromAddress'] ?? '' ) );
		}

		$created = current_time( 'mysql' );
		if ( ! empty( $summary['receivedTime'] ) ) {
			$ms = (int) $summary['receivedTime'];
			if ( $ms > 0 ) {
				$created = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) floor( $ms / 1000 ) ) );
			}
		}

		if ( Vitacare_Crm_Messages_Repo::find_by_external_id( $ext_id ) ) {
			return false;
		}

		$conv_id = Vitacare_Crm_Conversations_Repo::upsert_contact(
			'email',
			strtolower( $contact ),
			$contact_name !== '' ? $contact_name : null,
			null,
			array(
				'mail_provider' => 'zoho',
				'mailbox'       => $our_email,
				'zoho_thread'   => (string) ( $summary['threadId'] ?? '' ),
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
		return false === $inserted; // dedupe cuenta como manejado, no como importado
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
	 * Envío saliente Zoho Mail.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function send_text( int $conversation_id, string $body ) {
		if ( ! Vitacare_Crm_Settings::flag( 'email' ) || ! self::is_connected() ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_forbidden',
				__( 'Zoho Mail no está conectado o el canal email está off.', 'vitacare-crm' ),
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

		$from    = self::get_email();
		$subject = 'Re: VITACARE';
		if ( ! empty( $conv['last_message_preview'] ) ) {
			$prev = (string) $conv['last_message_preview'];
			$line = strtok( $prev, "\n" );
			if ( is_string( $line ) && $line !== '' && strlen( $line ) < 200 ) {
				$subject = str_starts_with( strtolower( $line ), 're:' ) ? $line : ( 'Re: ' . $line );
			}
		}

		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_post(
			self::api_base() . '/api/accounts/' . rawurlencode( self::get_account_id() ) . '/messages',
			array(
				'timeout' => 25,
				'headers' => array(
					'Authorization' => 'Zoho-oauthtoken ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'fromAddress' => $from,
						'toAddress'   => $to,
						'subject'     => $subject,
						'content'     => $body,
						'mailFormat'  => 'plaintext',
					)
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_graph_error', __( 'Error de red al enviar email por Zoho.', 'vitacare-crm' ), 502 );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			$msg = is_array( $data ) && isset( $data['status']['description'] )
				? (string) $data['status']['description']
				: __( 'Zoho Mail rechazó el envío.', 'vitacare-crm' );
			return Vitacare_Crm_Db::error( 'vitacare_crm_graph_error', $msg, 502 );
		}

		$msg_id  = is_array( $data['data'] ?? null ) ? (string) ( $data['data']['messageId'] ?? '' ) : '';
		$ext_id  = 'zoho:' . ( $msg_id !== '' ? $msg_id : uniqid( 'sent_', true ) );
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

	private static function flash( string $type, string $msg ): void {
		set_transient( 'vitacare_crm_zoho_flash', array( 'type' => $type, 'msg' => $msg ), 60 );
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso.', 'vitacare-crm' ) );
		}

		if ( isset( $_POST['vitacare_crm_zoho_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vitacare_crm_zoho_nonce'] ) ), 'vitacare_crm_zoho' )
		) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$action = isset( $_POST['zoho_action'] ) ? sanitize_key( wp_unslash( $_POST['zoho_action'] ) ) : '';
			if ( $action === 'save_client' ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$cid = isset( $_POST['zoho_client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['zoho_client_id'] ) ) : '';
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$sec = isset( $_POST['zoho_client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['zoho_client_secret'] ) ) : '';
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$dom = isset( $_POST['zoho_domain'] ) ? sanitize_key( wp_unslash( $_POST['zoho_domain'] ) ) : 'com';
				update_option( self::OPTION_CLIENT_ID, $cid, false );
				if ( $sec !== '' ) {
					update_option( self::OPTION_CLIENT_SECRET, Vitacare_Crm_Settings::store_secret( $sec ), false );
				}
				update_option( self::OPTION_DOMAIN, isset( self::DOMAINS[ $dom ] ) ? $dom : 'com', false );
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Credenciales Zoho guardadas.', 'vitacare-crm' ) . '</p></div>';
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
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Zoho Mail desconectado.', 'vitacare-crm' ) . '</p></div>';
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

		$flash = get_transient( 'vitacare_crm_zoho_flash' );
		if ( is_array( $flash ) ) {
			delete_transient( 'vitacare_crm_zoho_flash' );
			$class = ( $flash['type'] ?? '' ) === 'error' ? 'notice-error' : 'notice-success';
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( (string) ( $flash['msg'] ?? '' ) ) . '</p></div>';
		}

		$connected = self::is_connected();
		$redirect  = self::redirect_uri();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Zoho Mail — Conectar y sincronizar', 'vitacare-crm' ); ?></h1>
			<div class="vcrm-callout" style="background:#f0f6fc;border-left:4px solid #2271b1;padding:12px 14px;margin:12px 0">
				<p style="margin:0">
					<?php
					echo esc_html__(
						'OAuth oficial de Zoho Mail API. Los correos del Inbox se importan al mismo canal «Correo» de la bandeja /crm que usa Gmail -- cada conversación recuerda cuál buzón la maneja para responder por el correcto. Envío de respuestas desde el compositor.',
						'vitacare-crm'
					);
					?>
				</p>
			</div>

			<h2><?php echo esc_html__( '1. Zoho API Console', 'vitacare-crm' ); ?></h2>
			<ol>
				<li><?php echo esc_html__( 'Crear una app "Server-based Applications" en api-console.zoho.com (o el data center que corresponda).', 'vitacare-crm' ); ?></li>
				<li>
					<?php echo esc_html__( 'URL de redirección autorizada:', 'vitacare-crm' ); ?>
					<br /><code><?php echo esc_html( $redirect ); ?></code>
				</li>
				<li><?php echo esc_html__( 'Copiar el Client ID y Client Secret de esa app.', 'vitacare-crm' ); ?></li>
				<li><?php echo esc_html__( 'Confirmar el data center (dominio) donde vive la cuenta de correo -- si no coincide, la conexión falla.', 'vitacare-crm' ); ?></li>
			</ol>

			<form method="post" style="max-width:640px;background:#fff;border:1px solid #c3c4c7;padding:16px;border-radius:8px">
				<?php wp_nonce_field( 'vitacare_crm_zoho', 'vitacare_crm_zoho_nonce' ); ?>
				<input type="hidden" name="zoho_action" value="save_client" />
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="zoho_client_id"><?php echo esc_html__( 'Client ID', 'vitacare-crm' ); ?></label></th>
						<td><input class="regular-text" type="text" name="zoho_client_id" id="zoho_client_id" value="<?php echo esc_attr( self::get_client_id() ); ?>" autocomplete="off" /></td>
					</tr>
					<tr>
						<th><label for="zoho_client_secret"><?php echo esc_html__( 'Client Secret', 'vitacare-crm' ); ?></label></th>
						<td><input class="regular-text" type="password" name="zoho_client_secret" id="zoho_client_secret" value="" placeholder="<?php echo self::get_client_secret() !== '' ? esc_attr__( '•••• (dejar vacío para no cambiar)', 'vitacare-crm' ) : ''; ?>" autocomplete="new-password" /></td>
					</tr>
					<tr>
						<th><label for="zoho_domain"><?php echo esc_html__( 'Data center', 'vitacare-crm' ); ?></label></th>
						<td>
							<select name="zoho_domain" id="zoho_domain">
								<?php foreach ( self::DOMAINS as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( self::get_domain(), $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Guardar Client ID/Secret/Data center', 'vitacare-crm' ), 'secondary' ); ?>
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
					<?php wp_nonce_field( 'vitacare_crm_zoho', 'vitacare_crm_zoho_nonce' ); ?>
					<input type="hidden" name="zoho_action" value="sync" />
					<?php submit_button( __( 'Sincronizar ahora', 'vitacare-crm' ), 'primary', 'submit', false ); ?>
				</form>
				<form method="post" style="display:inline-block">
					<?php wp_nonce_field( 'vitacare_crm_zoho', 'vitacare_crm_zoho_nonce' ); ?>
					<input type="hidden" name="zoho_action" value="disconnect" />
					<?php submit_button( __( 'Desconectar Zoho Mail', 'vitacare-crm' ), 'delete', 'submit', false ); ?>
				</form>
			<?php else : ?>
				<form method="post">
					<?php wp_nonce_field( 'vitacare_crm_zoho', 'vitacare_crm_zoho_nonce' ); ?>
					<input type="hidden" name="zoho_action" value="connect" />
					<?php submit_button( __( 'Conectar con Zoho Mail', 'vitacare-crm' ), 'primary' ); ?>
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

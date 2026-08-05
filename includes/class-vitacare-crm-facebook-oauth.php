<?php
defined( 'ABSPATH' ) || exit;

/**
 * C-2: Facebook Login (OAuth) + selector de Página administrada.
 * Oficial Graph API. No modifica vitacare-core ni la raíz del sitio.
 */
final class Vitacare_Crm_Facebook_Oauth {

	public const OPTION_PAGE_ID      = 'vitacare_crm_fb_page_id';
	public const OPTION_PAGE_NAME    = 'vitacare_crm_fb_page_name';
	public const OPTION_PAGE_TOKEN   = 'vitacare_crm_fb_page_token';
	public const OPTION_USER_TOKEN   = 'vitacare_crm_fb_user_token';
	public const OPTION_CONNECTED_AT = 'vitacare_crm_fb_connected_at';
	public const OPTION_PAGES_CACHE  = 'vitacare_crm_fb_pages_cache';
	public const TRANSIENT_STATE     = 'vitacare_crm_fb_oauth_state';

	/** C-3: cuenta profesional de Instagram vinculada a la Página seleccionada. */
	public const OPTION_IG_ID       = 'vitacare_crm_ig_id';
	public const OPTION_IG_USERNAME = 'vitacare_crm_ig_username';

	/**
	 * Scopes para listar páginas, mensajería Messenger e Instagram (la cuenta IG
	 * profesional se administra a través del mismo token de Página).
	 * D-27 Fase 5: read_insights/instagram_manage_insights agregados para leer
	 * alcance/impresiones gratis (sin gasto en anuncios) en Reportes. Cuentas ya
	 * conectadas antes de este cambio deben pulsar «Reconectar / cambiar cuenta»
	 * para obtener el nuevo permiso -- Meta no lo agrega solo.
	 */
	public const SCOPES = 'pages_show_list,pages_messaging,pages_manage_metadata,pages_read_engagement,business_management,instagram_basic,instagram_manage_messages,read_insights,instagram_manage_insights';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 25 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_handle_oauth_return' ), 1 );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'vitacare-crm-accounts',
			__( 'Facebook', 'vitacare-crm' ),
			__( 'Facebook', 'vitacare-crm' ),
			'manage_options',
			'vitacare-crm-facebook',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function redirect_uri(): string {
		return admin_url( 'admin.php?page=vitacare-crm-facebook' );
	}

	public static function is_connected(): bool {
		return self::get_page_id() !== '' && self::get_page_token() !== '';
	}

	public static function get_page_id(): string {
		if ( defined( 'VITACARE_CRM_MESSENGER_PAGE_ID' ) && (string) VITACARE_CRM_MESSENGER_PAGE_ID !== '' ) {
			return (string) VITACARE_CRM_MESSENGER_PAGE_ID;
		}
		return (string) get_option( self::OPTION_PAGE_ID, '' );
	}

	public static function get_page_name(): string {
		return (string) get_option( self::OPTION_PAGE_NAME, '' );
	}

	/**
	 * Mismo token sirve para Messenger e Instagram en este flujo (cuenta IG
	 * profesional vinculada a la Página, per diseño oficial de Meta) — ambas
	 * constantes de wp-config son válidas y equivalentes; no son dos secretos
	 * distintos hoy.
	 */
	public static function get_page_token(): string {
		if ( defined( 'VITACARE_CRM_MESSENGER_PAGE_ACCESS_TOKEN' ) && (string) VITACARE_CRM_MESSENGER_PAGE_ACCESS_TOKEN !== '' ) {
			return (string) VITACARE_CRM_MESSENGER_PAGE_ACCESS_TOKEN;
		}
		if ( defined( 'VITACARE_CRM_INSTAGRAM_ACCESS_TOKEN' ) && (string) VITACARE_CRM_INSTAGRAM_ACCESS_TOKEN !== '' ) {
			return (string) VITACARE_CRM_INSTAGRAM_ACCESS_TOKEN;
		}
		$raw = (string) get_option( self::OPTION_PAGE_TOKEN, '' );
		return Vitacare_Crm_Settings::read_secret( $raw );
	}

	/**
	 * C-3: id de la cuenta profesional de Instagram vinculada a la Página conectada.
	 */
	public static function get_ig_id(): string {
		if ( defined( 'VITACARE_CRM_INSTAGRAM_ACCOUNT_ID' ) && (string) VITACARE_CRM_INSTAGRAM_ACCOUNT_ID !== '' ) {
			return (string) VITACARE_CRM_INSTAGRAM_ACCOUNT_ID;
		}
		return (string) get_option( self::OPTION_IG_ID, '' );
	}

	public static function get_ig_username(): string {
		return (string) get_option( self::OPTION_IG_USERNAME, '' );
	}

	public static function is_instagram_connected(): bool {
		return self::is_connected() && self::get_ig_id() !== '';
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_pages_cache(): array {
		$raw = get_option( self::OPTION_PAGES_CACHE, array() );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Redirige exactamente a la URI de callback registrada en Meta (nunca a
	 * admin_url() genérico ni a un fallback silencioso), con el resultado y
	 * un flash-message sanitizado.
	 */
	private static function redirect_with_result( string $status, string $message, array $extra_args = array() ): void {
		update_option( 'vitacare_crm_fb_oauth_last_status', $status, false );
		set_transient(
			'vitacare_crm_fb_flash',
			array(
				'type' => 'success' === $status ? 'success' : 'error',
				'msg'  => $message,
			),
			60
		);
		// redirect_uri() ya trae su propio "?page=...", así que aquí sí es
		// seguro usar add_query_arg() (los valores son flags simples sin
		// caracteres especiales, no una URL anidada).
		$args                   = $extra_args;
		$args['vitacare_oauth'] = $status;
		$target                 = add_query_arg( $args, self::redirect_uri() );
		Vitacare_Crm_Logger::info( 'fb_oauth_redirect', array( 'status' => $status, 'target' => $target ) );
		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Procesa callback OAuth (code) antes de render. Se ejecuta exactamente
	 * cuando WordPress carga admin.php?page=vitacare-crm-facebook con
	 * code/state o error en la query string.
	 */
	public static function maybe_handle_oauth_return(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( $page !== 'vitacare-crm-facebook' ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['error'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$desc = isset( $_GET['error_description'] ) ? sanitize_text_field( wp_unslash( $_GET['error_description'] ) ) : '';
			Vitacare_Crm_Logger::info( 'fb_oauth_callback', array( 'error' => true, 'has_code' => false, 'has_state' => false ) );
			self::redirect_with_result( 'error', $desc !== '' ? $desc : __( 'Facebook denegó el acceso.', 'vitacare-crm' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$has_code = ! empty( $_GET['code'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$has_state = ! empty( $_GET['state'] );
		if ( ! $has_code && ! $has_state ) {
			// Ni code, ni state, ni error: no es un retorno de OAuth, es solo
			// abrir la página normalmente. No hacer nada.
			return;
		}

		Vitacare_Crm_Logger::info( 'fb_oauth_callback', array( 'error' => false, 'has_code' => $has_code, 'has_state' => $has_state ) );

		if ( ! $has_code ) {
			self::redirect_with_result( 'error', __( 'Meta no envió el código de autorización (falta "code" en el retorno).', 'vitacare-crm' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$state = $has_state ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$saved = (string) get_transient( self::state_transient_key() );
		delete_transient( self::state_transient_key() );

		$state_valid = $saved !== '' && hash_equals( $saved, $state );
		Vitacare_Crm_Logger::info( 'fb_oauth_state_check', array( 'valid' => $state_valid ) );

		if ( ! $state_valid ) {
			self::redirect_with_result( 'error', __( 'Estado OAuth inválido. Vuelve a intentar «Conectar con Facebook».', 'vitacare-crm' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code   = sanitize_text_field( wp_unslash( $_GET['code'] ) );
		$result = self::exchange_code( $code );
		if ( is_wp_error( $result ) ) {
			self::redirect_with_result( 'error', $result->get_error_message() );
		}

		self::redirect_with_result(
			'success',
			__( 'Cuenta Facebook autorizada. Elige la Página que administras.', 'vitacare-crm' ),
			array( 'select_page' => '1' )
		);
	}

	/**
	 * @return true|WP_Error
	 */
	private static function exchange_code( string $code ) {
		$app_id     = Vitacare_Crm_Settings::get( 'app_id' );
		$app_secret = Vitacare_Crm_Settings::get( 'app_secret' );
		if ( $app_id === '' || $app_secret === '' ) {
			return new WP_Error(
				'vitacare_crm_fb_config',
				__( 'Faltan App ID y App Secret en Credenciales CRM (Meta).', 'vitacare-crm' )
			);
		}

		$version   = Vitacare_Crm_Settings::graph_version();
		$token_url = self::build_url(
			'https://graph.facebook.com/' . rawurlencode( $version ) . '/oauth/access_token',
			array(
				'client_id'     => $app_id,
				'client_secret' => $app_secret,
				'redirect_uri'  => self::redirect_uri(),
				'code'          => $code,
			)
		);

		$response = wp_remote_get(
			$token_url,
			array(
				'timeout' => 20,
				'headers' => array(
					'User-Agent' => 'VITACARE-CRM/' . VITACARE_CRM_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			Vitacare_Crm_Logger::error( 'fb_oauth_token_http_error', array( 'msg' => $response->get_error_message() ) );
			return $response;
		}

		$http_code = (int) wp_remote_retrieve_response_code( $response );
		$body      = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		Vitacare_Crm_Logger::info( 'fb_oauth_token_exchange', array( 'http_code' => $http_code ) );
		if ( ! is_array( $body ) || empty( $body['access_token'] ) ) {
			$msg = isset( $body['error']['message'] ) ? (string) $body['error']['message'] : __( 'No se obtuvo access token.', 'vitacare-crm' );
			return new WP_Error( 'vitacare_crm_fb_token', $msg );
		}

		$user_token = (string) $body['access_token'];

		// Long-lived user token.
		$ll_url = self::build_url(
			'https://graph.facebook.com/' . rawurlencode( $version ) . '/oauth/access_token',
			array(
				'grant_type'        => 'fb_exchange_token',
				'client_id'         => $app_id,
				'client_secret'     => $app_secret,
				'fb_exchange_token' => $user_token,
			)
		);
		$ll_res = wp_remote_get( $ll_url, array( 'timeout' => 20 ) );
		if ( ! is_wp_error( $ll_res ) ) {
			$ll_body = json_decode( (string) wp_remote_retrieve_body( $ll_res ), true );
			if ( is_array( $ll_body ) && ! empty( $ll_body['access_token'] ) ) {
				$user_token = (string) $ll_body['access_token'];
			}
		}

		update_option( self::OPTION_USER_TOKEN, Vitacare_Crm_Settings::store_secret( $user_token ), false );

		$pages = self::fetch_pages( $user_token );
		if ( is_wp_error( $pages ) ) {
			return $pages;
		}
		if ( empty( $pages ) ) {
			return new WP_Error(
				'vitacare_crm_fb_no_pages',
				__( 'No se encontraron Páginas administradas por esta cuenta. Revisa permisos o el rol en la Página.', 'vitacare-crm' )
			);
		}

		update_option( self::OPTION_PAGES_CACHE, $pages, false );
		return true;
	}

	/**
	 * @return array<int, array{id: string, name: string, access_token: string, tasks: array}>|WP_Error
	 */
	private static function fetch_pages( string $user_token ) {
		$version = Vitacare_Crm_Settings::graph_version();
		$url     = add_query_arg(
			array(
				'fields'       => 'id,name,access_token,tasks',
				'limit'        => 100,
				'access_token' => $user_token,
			),
			'https://graph.facebook.com/' . rawurlencode( $version ) . '/me/accounts'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => array( 'User-Agent' => 'VITACARE-CRM/' . VITACARE_CRM_VERSION ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'vitacare_crm_fb_pages', __( 'Respuesta inválida al listar Páginas.', 'vitacare-crm' ) );
		}
		if ( isset( $body['error']['message'] ) ) {
			return new WP_Error( 'vitacare_crm_fb_pages', (string) $body['error']['message'] );
		}

		$data = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array();
		$out  = array();
		foreach ( $data as $row ) {
			if ( ! is_array( $row ) || empty( $row['id'] ) || empty( $row['access_token'] ) ) {
				continue;
			}
			$out[] = array(
				'id'           => (string) $row['id'],
				'name'         => (string) ( $row['name'] ?? $row['id'] ),
				'access_token' => (string) $row['access_token'],
				'tasks'        => isset( $row['tasks'] ) && is_array( $row['tasks'] ) ? $row['tasks'] : array(),
			);
		}
		return $out;
	}

	/**
	 * Transient de state ligado al admin actual (no una clave global compartida).
	 */
	private static function state_transient_key(): string {
		return self::TRANSIENT_STATE . '_' . get_current_user_id();
	}

	/**
	 * Construye una URL con querystring correctamente codificado (rawurlencode
	 * por valor, vía http_build_query RFC3986). add_query_arg() de WordPress
	 * NO codifica los valores -- por eso redirect_uri (que trae "?" y "=")
	 * corrompía el querystring externo del diálogo de Facebook, y Meta caía a
	 * un redirect por defecto sin code/state/error en vez de completar el
	 * OAuth. Esta es la causa raíz del bug reportado.
	 *
	 * @param array<string, string> $args
	 */
	private static function build_url( string $base, array $args ): string {
		return $base . '?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
	}

	public static function start_oauth_url(): string|WP_Error {
		$app_id = Vitacare_Crm_Settings::get( 'app_id' );
		if ( $app_id === '' ) {
			return new WP_Error(
				'vitacare_crm_fb_config',
				__( 'Configura el App ID de Meta en Credenciales antes de conectar Facebook.', 'vitacare-crm' )
			);
		}
		if ( Vitacare_Crm_Settings::get( 'app_secret' ) === '' ) {
			return new WP_Error(
				'vitacare_crm_fb_config',
				__( 'Configura el App Secret de Meta en Credenciales.', 'vitacare-crm' )
			);
		}

		$state = wp_generate_password( 32, false, false );
		set_transient( self::state_transient_key(), $state, 600 );

		$redirect_uri = self::redirect_uri();
		$version      = Vitacare_Crm_Settings::graph_version();
		$url          = self::build_url(
			'https://www.facebook.com/' . rawurlencode( $version ) . '/dialog/oauth',
			array(
				'client_id'     => $app_id,
				'redirect_uri'  => $redirect_uri,
				'state'         => $state,
				'scope'         => self::SCOPES,
				'response_type' => 'code',
			)
		);

		update_option( 'vitacare_crm_fb_oauth_last_status', 'started', false );
		Vitacare_Crm_Logger::info(
			'fb_oauth_started',
			array(
				'redirect_uri' => $redirect_uri,
				'user_id'      => get_current_user_id(),
			)
		);

		return $url;
	}

	public static function select_page( string $page_id ): true|WP_Error {
		$pages = self::get_pages_cache();
		$found = null;
		foreach ( $pages as $p ) {
			if ( isset( $p['id'] ) && (string) $p['id'] === $page_id ) {
				$found = $p;
				break;
			}
		}
		if ( ! $found ) {
			// Reintentar fetch si hay user token.
			$user_raw = (string) get_option( self::OPTION_USER_TOKEN, '' );
			$user_tok = Vitacare_Crm_Settings::read_secret( $user_raw );
			if ( $user_tok !== '' ) {
				$pages = self::fetch_pages( $user_tok );
				if ( ! is_wp_error( $pages ) ) {
					update_option( self::OPTION_PAGES_CACHE, $pages, false );
					foreach ( $pages as $p ) {
						if ( (string) $p['id'] === $page_id ) {
							$found = $p;
							break;
						}
					}
				}
			}
		}
		if ( ! $found || empty( $found['access_token'] ) ) {
			return new WP_Error( 'vitacare_crm_fb_page', __( 'Página no encontrada en la lista autorizada.', 'vitacare-crm' ) );
		}

		update_option( self::OPTION_PAGE_ID, (string) $found['id'], false );
		update_option( self::OPTION_PAGE_NAME, (string) $found['name'], false );
		update_option( self::OPTION_PAGE_TOKEN, Vitacare_Crm_Settings::store_secret( (string) $found['access_token'] ), false );
		update_option( self::OPTION_CONNECTED_AT, current_time( 'mysql' ), false );
		// Activar flag de canal Facebook.
		update_option( 'vitacare_crm_feature_facebook', '1', false );
		// Limpiar tokens de páginas del cache (contienen access_token).
		$safe = array();
		foreach ( self::get_pages_cache() as $p ) {
			$safe[] = array(
				'id'   => $p['id'] ?? '',
				'name' => $p['name'] ?? '',
			);
		}
		update_option( self::OPTION_PAGES_CACHE, $safe, false );

		// C-4: suscribir Página a eventos de mensajería de la app.
		if ( class_exists( 'Vitacare_Crm_Channel_Messenger' ) ) {
			$sub = Vitacare_Crm_Channel_Messenger::subscribe_page();
			if ( is_wp_error( $sub ) ) {
				Vitacare_Crm_Logger::error(
					'fb_subscribe_page_failed',
					array( 'msg' => $sub->get_error_message() )
				);
				// No fallar la selección: el admin puede reintentar en la UI.
			}
		}

		// C-3: si la Página tiene una cuenta profesional de Instagram vinculada,
		// guardarla y activar el canal. No falla la selección de Página si no hay IG.
		self::refresh_instagram_account( (string) $found['id'], (string) $found['access_token'] );

		return true;
	}

	/**
	 * C-3: consulta y guarda la cuenta de Instagram profesional vinculada a la
	 * Página (instagram_business_account). Si no hay ninguna, limpia lo guardado.
	 */
	public static function refresh_instagram_account( string $page_id, string $page_token ): void {
		$version  = Vitacare_Crm_Settings::graph_version();
		$url      = add_query_arg(
			array(
				'fields'       => 'instagram_business_account{id,username}',
				'access_token' => $page_token,
			),
			'https://graph.facebook.com/' . rawurlencode( $version ) . '/' . rawurlencode( $page_id )
		);
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => array( 'User-Agent' => 'VITACARE-CRM/' . VITACARE_CRM_VERSION ),
			)
		);
		if ( is_wp_error( $response ) ) {
			Vitacare_Crm_Logger::error( 'ig_account_lookup_failed', array( 'msg' => $response->get_error_message() ) );
			return;
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$ig   = is_array( $body ) ? ( $body['instagram_business_account'] ?? null ) : null;
		if ( ! is_array( $ig ) || empty( $ig['id'] ) ) {
			delete_option( self::OPTION_IG_ID );
			delete_option( self::OPTION_IG_USERNAME );
			return;
		}

		update_option( self::OPTION_IG_ID, (string) $ig['id'], false );
		update_option( self::OPTION_IG_USERNAME, (string) ( $ig['username'] ?? '' ), false );
		update_option( 'vitacare_crm_feature_instagram', '1', false );
	}

	/**
	 * D-27 Fase 5: alcance/impresiones de la Página, gratis (sin gasto en
	 * anuncios, solo lectura de Insights). Cacheado 30 min. Usa métricas
	 * vigentes (page_impressions_unique fue deprecado por Meta -- no se
	 * usa aquí).
	 *
	 * @return array<string, int>|WP_Error
	 */
	public static function get_page_insights() {
		if ( ! self::is_connected() ) {
			return new WP_Error( 'vitacare_crm_fb', __( 'Facebook no está conectado.', 'vitacare-crm' ) );
		}
		$cached = get_transient( 'vitacare_crm_fb_page_insights' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$version = Vitacare_Crm_Settings::graph_version();
		$url     = add_query_arg(
			array(
				'metric'       => 'page_impressions,page_post_engagements',
				'period'       => 'day',
				'since'        => gmdate( 'Y-m-d', strtotime( '-7 days' ) ),
				'until'        => gmdate( 'Y-m-d' ),
				'access_token' => self::get_page_token(),
			),
			'https://graph.facebook.com/' . rawurlencode( $version ) . '/' . rawurlencode( self::get_page_id() ) . '/insights'
		);
		$response = wp_remote_get( $url, array( 'timeout' => 20, 'headers' => array( 'User-Agent' => 'VITACARE-CRM/' . VITACARE_CRM_VERSION ) ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || isset( $body['error'] ) ) {
			$msg = is_array( $body ) && isset( $body['error']['message'] )
				? (string) $body['error']['message']
				: __( 'No se pudieron leer los Insights de la Página.', 'vitacare-crm' );
			return new WP_Error( 'vitacare_crm_fb_insights', $msg );
		}

		$result = self::sum_insight_values( is_array( $body['data'] ?? null ) ? $body['data'] : array() );
		set_transient( 'vitacare_crm_fb_page_insights', $result, 30 * MINUTE_IN_SECONDS );
		return $result;
	}

	/**
	 * D-27 Fase 5: alcance y visitas de perfil de la cuenta de Instagram
	 * profesional vinculada, gratis. Cacheado 30 min.
	 *
	 * @return array<string, int>|WP_Error
	 */
	public static function get_instagram_insights() {
		if ( ! self::is_instagram_connected() ) {
			return new WP_Error( 'vitacare_crm_fb', __( 'Instagram no está vinculado.', 'vitacare-crm' ) );
		}
		$cached = get_transient( 'vitacare_crm_ig_insights' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$version = Vitacare_Crm_Settings::graph_version();
		$url     = add_query_arg(
			array(
				'metric'       => 'reach,profile_views',
				'period'       => 'day',
				'since'        => gmdate( 'Y-m-d', strtotime( '-7 days' ) ),
				'until'        => gmdate( 'Y-m-d' ),
				'access_token' => self::get_page_token(),
			),
			'https://graph.facebook.com/' . rawurlencode( $version ) . '/' . rawurlencode( self::get_ig_id() ) . '/insights'
		);
		$response = wp_remote_get( $url, array( 'timeout' => 20, 'headers' => array( 'User-Agent' => 'VITACARE-CRM/' . VITACARE_CRM_VERSION ) ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || isset( $body['error'] ) ) {
			$msg = is_array( $body ) && isset( $body['error']['message'] )
				? (string) $body['error']['message']
				: __( 'No se pudieron leer los Insights de Instagram.', 'vitacare-crm' );
			return new WP_Error( 'vitacare_crm_ig_insights', $msg );
		}

		$result = self::sum_insight_values( is_array( $body['data'] ?? null ) ? $body['data'] : array() );
		set_transient( 'vitacare_crm_ig_insights', $result, 30 * MINUTE_IN_SECONDS );
		return $result;
	}

	/**
	 * Suma los valores diarios de cada métrica devuelta por /insights.
	 *
	 * @param array<int, mixed> $data
	 * @return array<string, int>
	 */
	private static function sum_insight_values( array $data ): array {
		$out = array();
		foreach ( $data as $metric ) {
			if ( ! is_array( $metric ) || empty( $metric['name'] ) ) {
				continue;
			}
			$name = (string) $metric['name'];
			$sum  = 0;
			foreach ( (array) ( $metric['values'] ?? array() ) as $v ) {
				if ( is_array( $v ) && isset( $v['value'] ) && is_numeric( $v['value'] ) ) {
					$sum += (int) $v['value'];
				}
			}
			$out[ $name ] = $sum;
		}
		return $out;
	}

	public static function disconnect(): void {
		delete_option( self::OPTION_PAGE_ID );
		delete_option( self::OPTION_PAGE_NAME );
		delete_option( self::OPTION_PAGE_TOKEN );
		delete_option( self::OPTION_USER_TOKEN );
		delete_option( self::OPTION_CONNECTED_AT );
		delete_option( self::OPTION_PAGES_CACHE );
		delete_option( self::OPTION_IG_ID );
		delete_option( self::OPTION_IG_USERNAME );
		update_option( 'vitacare_crm_feature_facebook', '', false );
		update_option( 'vitacare_crm_feature_instagram', '', false );
		delete_transient( 'vitacare_crm_fb_page_insights' );
		delete_transient( 'vitacare_crm_ig_insights' );
	}

	/**
	 * Prueba administrativa de diagnóstico del flujo OAuth (sin secretos):
	 * URI generada, handler de callback registrado, state creado, estado de
	 * la última autorización.
	 */
	private static function render_oauth_diagnostics( string $redirect ): void {
		$handler_priority = has_action( 'admin_init', array( __CLASS__, 'maybe_handle_oauth_return' ) );
		$state_present    = get_transient( self::state_transient_key() ) !== false;
		$last_status      = (string) get_option( 'vitacare_crm_fb_oauth_last_status', '' );
		$status_labels    = array(
			''         => __( 'Sin intentos todavía', 'vitacare-crm' ),
			'started'  => __( 'Iniciado, esperando retorno de Meta', 'vitacare-crm' ),
			'success'  => __( 'Última autorización: éxito', 'vitacare-crm' ),
			'error'    => __( 'Última autorización: error', 'vitacare-crm' ),
		);
		?>
		<div class="vcrm-callout" style="background:#fcfcfc;border:1px solid #dcdcde;border-radius:6px;padding:12px 14px;margin:12px 0">
			<h3 style="margin-top:0"><?php echo esc_html__( 'Diagnóstico OAuth Facebook/Messenger', 'vitacare-crm' ); ?></h3>
			<p style="margin:.25em 0">
				<strong><?php echo esc_html__( 'OAuth Redirect URI generada:', 'vitacare-crm' ); ?></strong>
				<code><?php echo esc_html( $redirect ); ?></code>
			</p>
			<p style="margin:.25em 0">
				<?php echo esc_html__( 'Debe coincidir carácter por carácter con la URI registrada en Meta for Developers → tu App → Facebook Login → Valid OAuth Redirect URIs. No se puede verificar esa coincidencia automáticamente desde aquí (Meta no expone esa configuración por API) — cópiala y compárala tú mismo.', 'vitacare-crm' ); ?>
			</p>
			<p style="margin:.25em 0">
				<strong><?php echo esc_html__( 'Callback handler registrado:', 'vitacare-crm' ); ?></strong>
				<?php echo false !== $handler_priority
					? '<span class="dashicons dashicons-yes-alt" style="color:#46b450"></span> ' . esc_html__( 'Sí', 'vitacare-crm' )
					: '<span class="dashicons dashicons-warning" style="color:#d63638"></span> ' . esc_html__( 'No', 'vitacare-crm' ); ?>
			</p>
			<p style="margin:.25em 0">
				<strong><?php echo esc_html__( 'State pendiente para este usuario:', 'vitacare-crm' ); ?></strong>
				<?php echo $state_present ? esc_html__( 'Sí (hay una autorización en curso)', 'vitacare-crm' ) : esc_html__( 'No', 'vitacare-crm' ); ?>
			</p>
			<p style="margin:.25em 0">
				<strong><?php echo esc_html__( 'Estado de la última autorización:', 'vitacare-crm' ); ?></strong>
				<?php echo esc_html( $status_labels[ $last_status ] ?? $last_status ); ?>
			</p>
		</div>
		<?php
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso.', 'vitacare-crm' ) );
		}

		// Acciones POST: seleccionar página / desconectar / iniciar (redirect).
		if ( isset( $_POST['vitacare_crm_fb_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vitacare_crm_fb_nonce'] ) ), 'vitacare_crm_fb' )
		) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$action = isset( $_POST['fb_action'] ) ? sanitize_key( wp_unslash( $_POST['fb_action'] ) ) : '';
			if ( $action === 'disconnect' ) {
				self::disconnect();
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Facebook desconectado.', 'vitacare-crm' ) . '</p></div>';
			} elseif ( $action === 'select_page' ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				$pid = isset( $_POST['page_id'] ) ? sanitize_text_field( wp_unslash( $_POST['page_id'] ) ) : '';
				$r   = self::select_page( $pid );
				if ( is_wp_error( $r ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html( $r->get_error_message() ) . '</p></div>';
				} else {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Página seleccionada y canal Facebook activado.', 'vitacare-crm' ) . '</p></div>';
				}
			} elseif ( $action === 'resubscribe' ) {
				$sub = class_exists( 'Vitacare_Crm_Channel_Messenger' )
					? Vitacare_Crm_Channel_Messenger::subscribe_page()
					: new WP_Error( 'missing', 'Messenger channel missing' );
				if ( is_wp_error( $sub ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html( $sub->get_error_message() ) . '</p></div>';
				} else {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Página re-suscrita a mensajes de la app.', 'vitacare-crm' ) . '</p></div>';
				}
			} elseif ( $action === 'refresh_ig' ) {
				if ( self::is_connected() ) {
					self::refresh_instagram_account( self::get_page_id(), self::get_page_token() );
					echo self::get_ig_id() !== ''
						? '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Cuenta Instagram detectada y canal activado.', 'vitacare-crm' ) . '</p></div>'
						: '<div class="notice notice-warning"><p>' . esc_html__( 'La Página conectada no tiene una cuenta profesional de Instagram vinculada.', 'vitacare-crm' ) . '</p></div>';
				}
			} elseif ( $action === 'connect' ) {
				$url = self::start_oauth_url();
				if ( is_wp_error( $url ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html( $url->get_error_message() ) . '</p></div>';
				} else {
					wp_safe_redirect( $url );
					exit;
				}
			}
		}

		$flash = get_transient( 'vitacare_crm_fb_flash' );
		if ( is_array( $flash ) ) {
			delete_transient( 'vitacare_crm_fb_flash' );
			$class = ( $flash['type'] ?? '' ) === 'error' ? 'notice-error' : 'notice-success';
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( (string) ( $flash['msg'] ?? '' ) ) . '</p></div>';
		}

		$connected = self::is_connected();
		$pages     = self::get_pages_cache();
		// ¿Hay páginas con token en cache (recién oauth)?
		$need_select = false;
		foreach ( $pages as $p ) {
			if ( ! empty( $p['access_token'] ) ) {
				$need_select = true;
				break;
			}
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['select_page'] ) ) {
			$need_select = $need_select || ! empty( $pages );
		}

		$redirect = self::redirect_uri();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Facebook — Conectar cuenta y Página', 'vitacare-crm' ); ?></h1>

			<div class="vcrm-callout" style="background:#f0f6fc;border-left:4px solid #2271b1;padding:12px 14px;margin:12px 0">
				<p style="margin:0">
					<?php
					echo esc_html__(
						'Flujo oficial: autorizas tu cuenta de Facebook/Meta y luego eliges la Página que administras. El CRM guarda el token de esa Página para Messenger (mensajería en fases siguientes).',
						'vitacare-crm'
					);
					?>
				</p>
			</div>

			<h2><?php echo esc_html__( 'Requisitos en Meta for Developers', 'vitacare-crm' ); ?></h2>
			<ol>
				<li><?php echo esc_html__( 'App ID y App Secret guardados en CRM → Credenciales.', 'vitacare-crm' ); ?></li>
				<li>
					<?php echo esc_html__( 'URI de redirección OAuth válida (Valid OAuth Redirect URIs):', 'vitacare-crm' ); ?>
					<br /><code class="vcrm-mono"><?php echo esc_html( $redirect ); ?></code>
				</li>
				<li><?php echo esc_html__( 'Producto Facebook Login (o Login for Business) activado en la App.', 'vitacare-crm' ); ?></li>
				<li><?php echo esc_html__( 'Permisos de páginas / mensajería en modo desarrollo o revisión de app según el caso.', 'vitacare-crm' ); ?></li>
				<li>
					<?php
					echo esc_html__(
						'Para Instagram: la cuenta de Instagram debe ser profesional (empresa/creador) y estar vinculada a esta Página desde Meta Business Suite. Además, agrega el producto «Instagram» en la App de Meta for Developers y suscribe el mismo webhook (object=instagram, campo messages) a la URL de abajo.',
						'vitacare-crm'
					);
					?>
				</li>
			</ol>

			<?php self::render_oauth_diagnostics( $redirect ); ?>

			<?php if ( $connected ) : ?>
				<div class="vcrm-admin-card" style="background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:16px;max-width:560px">
					<h2 style="margin-top:0"><?php echo esc_html__( 'Conectado', 'vitacare-crm' ); ?></h2>
					<p>
						<strong><?php echo esc_html__( 'Página:', 'vitacare-crm' ); ?></strong>
						<?php echo esc_html( self::get_page_name() ); ?>
						(<code><?php echo esc_html( self::get_page_id() ); ?></code>)
					</p>
					<p>
						<strong><?php echo esc_html__( 'Desde:', 'vitacare-crm' ); ?></strong>
						<?php echo esc_html( (string) get_option( self::OPTION_CONNECTED_AT, '—' ) ); ?>
					</p>
					<p>
						<span class="vcrm-status vcrm-status-ok" style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;font-weight:600;background:#d5f5e3;color:#0a6b2d">
							<?php echo esc_html__( 'Canal Facebook activado', 'vitacare-crm' ); ?>
						</span>
					</p>
					<p>
						<strong><?php echo esc_html__( 'Instagram vinculado:', 'vitacare-crm' ); ?></strong>
						<?php if ( self::is_instagram_connected() ) : ?>
							@<?php echo esc_html( self::get_ig_username() !== '' ? self::get_ig_username() : self::get_ig_id() ); ?>
							(<code><?php echo esc_html( self::get_ig_id() ); ?></code>)
							<span class="vcrm-status vcrm-status-ok" style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;font-weight:600;background:#d5f5e3;color:#0a6b2d;margin-left:6px">
								<?php echo esc_html__( 'Canal Instagram activado', 'vitacare-crm' ); ?>
							</span>
						<?php else : ?>
							<span class="vcrm-status" style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;font-weight:600;background:#f0f0f1;color:#646970">
								<?php echo esc_html__( 'Sin cuenta profesional vinculada', 'vitacare-crm' ); ?>
							</span>
							<span class="description"><?php echo esc_html__( 'Vincula una cuenta de Instagram profesional a esta Página en Meta Business Suite y pulsa «Buscar cuenta Instagram».', 'vitacare-crm' ); ?></span>
						<?php endif; ?>
					</p>
					<form method="post" style="display:inline-block;margin-right:8px">
						<?php wp_nonce_field( 'vitacare_crm_fb', 'vitacare_crm_fb_nonce' ); ?>
						<input type="hidden" name="fb_action" value="resubscribe" />
						<?php submit_button( __( 'Re-suscribir Página a webhooks', 'vitacare-crm' ), 'secondary', 'submit', false ); ?>
					</form>
					<form method="post" style="display:inline-block;margin-right:8px">
						<?php wp_nonce_field( 'vitacare_crm_fb', 'vitacare_crm_fb_nonce' ); ?>
						<input type="hidden" name="fb_action" value="refresh_ig" />
						<?php submit_button( __( 'Buscar cuenta Instagram', 'vitacare-crm' ), 'secondary', 'submit', false ); ?>
					</form>
					<form method="post" style="display:inline-block;margin-right:8px">
						<?php wp_nonce_field( 'vitacare_crm_fb', 'vitacare_crm_fb_nonce' ); ?>
						<input type="hidden" name="fb_action" value="disconnect" />
						<?php submit_button( __( 'Desconectar Facebook', 'vitacare-crm' ), 'delete', 'submit', false ); ?>
					</form>
					<form method="post" style="display:inline-block">
						<?php wp_nonce_field( 'vitacare_crm_fb', 'vitacare_crm_fb_nonce' ); ?>
						<input type="hidden" name="fb_action" value="connect" />
						<?php submit_button( __( 'Reconectar / cambiar cuenta', 'vitacare-crm' ), 'secondary', 'submit', false ); ?>
					</form>
					<p class="description" style="margin-top:12px">
						<?php
						echo esc_html__(
							'Webhook (mismo que WhatsApp): suscribe el producto Messenger / Page al mismo callback URL del CRM. Campos: messages.',
							'vitacare-crm'
						);
						?>
						<br />
						<code><?php echo esc_html( Vitacare_Crm_Settings::webhook_url() ); ?></code>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( $need_select && ! empty( $pages ) ) : ?>
				<h2><?php echo esc_html__( 'Selecciona la Página', 'vitacare-crm' ); ?></h2>
				<p><?php echo esc_html__( 'Solo se listan Páginas que esta cuenta puede administrar.', 'vitacare-crm' ); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'vitacare_crm_fb', 'vitacare_crm_fb_nonce' ); ?>
					<input type="hidden" name="fb_action" value="select_page" />
					<table class="widefat striped" style="max-width:640px">
						<thead>
							<tr>
								<th style="width:40px"></th>
								<th><?php echo esc_html__( 'Página', 'vitacare-crm' ); ?></th>
								<th><?php echo esc_html__( 'ID', 'vitacare-crm' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $pages as $i => $p ) : ?>
								<tr>
									<td>
										<input type="radio" name="page_id" value="<?php echo esc_attr( (string) ( $p['id'] ?? '' ) ); ?>" id="fb-page-<?php echo esc_attr( (string) $i ); ?>" <?php checked( $i === 0 ); ?> required />
									</td>
									<td>
										<label for="fb-page-<?php echo esc_attr( (string) $i ); ?>">
											<?php echo esc_html( (string) ( $p['name'] ?? '' ) ); ?>
										</label>
									</td>
									<td><code><?php echo esc_html( (string) ( $p['id'] ?? '' ) ); ?></code></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php submit_button( __( 'Usar esta Página', 'vitacare-crm' ) ); ?>
				</form>
			<?php elseif ( ! $connected ) : ?>
				<h2><?php echo esc_html__( 'Conectar', 'vitacare-crm' ); ?></h2>
				<form method="post">
					<?php wp_nonce_field( 'vitacare_crm_fb', 'vitacare_crm_fb_nonce' ); ?>
					<input type="hidden" name="fb_action" value="connect" />
					<?php submit_button( __( 'Conectar con Facebook', 'vitacare-crm' ), 'primary' ); ?>
				</form>
			<?php endif; ?>

			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=vitacare-crm-accounts' ) ); ?>"><?php echo esc_html__( '← Cuentas conectadas', 'vitacare-crm' ); ?></a>
				|
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=vitacare-crm-settings' ) ); ?>"><?php echo esc_html__( 'Credenciales', 'vitacare-crm' ); ?></a>
			</p>

			<p class="description">
				<?php
				echo esc_html__(
					'La mensajería Messenger en la bandeja (webhooks object=page) se completa en C-4. Esta entrega guarda la Página y el page access token.',
					'vitacare-crm'
				);
				?>
			</p>
		</div>
		<?php
	}
}

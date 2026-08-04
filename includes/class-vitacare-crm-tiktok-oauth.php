<?php
defined( 'ABSPATH' ) || exit;

/**
 * C-6: TikTok Login Kit (OAuth oficial, v2).
 *
 * Alcance real, confirmado contra la documentación oficial de TikTok for
 * Developers (2026-08): Login Kit permite conectar y verificar una cuenta de
 * TikTok (perfil básico), pero **no existe ningún producto público de TikTok
 * para enviar/recibir mensajes directos (DM) ni para leer/responder
 * comentarios** desde una app de terceros (los productos oficiales son Login
 * Kit, Share Kit, Content Posting API, Display API, Webhooks de contenido,
 * Data Portability, Research API y Business API — ninguno cubre mensajería).
 * Por eso este conector NO agrega un canal de mensajes a la bandeja del CRM
 * (a diferencia de WhatsApp/Messenger/Instagram/Gmail): solo verifica la
 * cuenta conectada. Ver D-21/D-04b en ESTADO_CRM.md.
 */
final class Vitacare_Crm_Tiktok_Oauth {

	public const OPTION_OPEN_ID       = 'vitacare_crm_tt_open_id';
	public const OPTION_UNION_ID      = 'vitacare_crm_tt_union_id';
	public const OPTION_DISPLAY_NAME  = 'vitacare_crm_tt_display_name';
	public const OPTION_AVATAR_URL    = 'vitacare_crm_tt_avatar_url';
	public const OPTION_ACCESS_TOKEN  = 'vitacare_crm_tt_access_token';
	public const OPTION_REFRESH_TOKEN = 'vitacare_crm_tt_refresh_token';
	public const OPTION_TOKEN_EXPIRES = 'vitacare_crm_tt_token_expires_at';
	public const OPTION_CONNECTED_AT  = 'vitacare_crm_tt_connected_at';
	public const TRANSIENT_STATE      = 'vitacare_crm_tt_oauth_state';

	/** Único scope necesario: perfil básico para verificar la cuenta. */
	public const SCOPES = 'user.info.basic';

	private const AUTH_URL  = 'https://www.tiktok.com/v2/auth/authorize/';
	private const TOKEN_URL = 'https://open.tiktokapis.com/v2/oauth/token/';
	private const USERINFO_URL = 'https://open.tiktokapis.com/v2/user/info/';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 26 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_handle_oauth_return' ), 1 );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'vitacare-crm-accounts',
			__( 'TikTok', 'vitacare-crm' ),
			__( 'TikTok', 'vitacare-crm' ),
			'manage_options',
			'vitacare-crm-tiktok',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function redirect_uri(): string {
		return admin_url( 'admin.php?page=vitacare-crm-tiktok' );
	}

	public static function is_connected(): bool {
		return self::get_open_id() !== '' && self::get_access_token() !== '';
	}

	public static function get_open_id(): string {
		return (string) get_option( self::OPTION_OPEN_ID, '' );
	}

	public static function get_display_name(): string {
		return (string) get_option( self::OPTION_DISPLAY_NAME, '' );
	}

	public static function get_avatar_url(): string {
		return (string) get_option( self::OPTION_AVATAR_URL, '' );
	}

	public static function get_access_token(): string {
		$raw = (string) get_option( self::OPTION_ACCESS_TOKEN, '' );
		return Vitacare_Crm_Settings::read_secret( $raw );
	}

	public static function get_refresh_token(): string {
		$raw = (string) get_option( self::OPTION_REFRESH_TOKEN, '' );
		return Vitacare_Crm_Settings::read_secret( $raw );
	}

	public static function token_expires_at(): int {
		return (int) get_option( self::OPTION_TOKEN_EXPIRES, 0 );
	}

	public static function token_expired(): bool {
		$exp = self::token_expires_at();
		return $exp > 0 && $exp <= time();
	}

	public static function start_oauth_url(): string|WP_Error {
		$client_key = Vitacare_Crm_Settings::get( 'tiktok_client_key' );
		if ( $client_key === '' ) {
			return new WP_Error(
				'vitacare_crm_tt_config',
				__( 'Configura el Client Key de TikTok en Credenciales antes de conectar.', 'vitacare-crm' )
			);
		}
		if ( Vitacare_Crm_Settings::get( 'tiktok_client_secret' ) === '' ) {
			return new WP_Error(
				'vitacare_crm_tt_config',
				__( 'Configura el Client Secret de TikTok en Credenciales.', 'vitacare-crm' )
			);
		}

		$state = wp_generate_password( 32, false, false );
		set_transient( self::TRANSIENT_STATE, $state, 600 );

		return add_query_arg(
			array(
				'client_key'   => $client_key,
				'response_type' => 'code',
				'scope'        => self::SCOPES,
				'redirect_uri' => self::redirect_uri(),
				'state'        => $state,
			),
			self::AUTH_URL
		);
	}

	public static function maybe_handle_oauth_return(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( $page !== 'vitacare-crm-tiktok' ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['error'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$desc = isset( $_GET['error_description'] ) ? sanitize_text_field( wp_unslash( $_GET['error_description'] ) ) : '';
			self::flash( 'error', $desc !== '' ? $desc : __( 'TikTok denegó el acceso.', 'vitacare-crm' ) );
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
			self::flash( 'error', __( 'Estado OAuth inválido. Vuelve a intentar «Conectar con TikTok».', 'vitacare-crm' ) );
			wp_safe_redirect( self::redirect_uri() );
			exit;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code   = sanitize_text_field( wp_unslash( $_GET['code'] ) );
		$result = self::exchange_code( $code );
		if ( is_wp_error( $result ) ) {
			self::flash( 'error', $result->get_error_message() );
			wp_safe_redirect( self::redirect_uri() );
			exit;
		}

		self::flash( 'success', __( 'Cuenta TikTok conectada y verificada.', 'vitacare-crm' ) );
		wp_safe_redirect( self::redirect_uri() );
		exit;
	}

	/**
	 * @return true|WP_Error
	 */
	private static function exchange_code( string $code ) {
		$client_key    = Vitacare_Crm_Settings::get( 'tiktok_client_key' );
		$client_secret = Vitacare_Crm_Settings::get( 'tiktok_client_secret' );

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
					'User-Agent'   => 'VITACARE-CRM/' . VITACARE_CRM_VERSION,
				),
				'body'    => array(
					'client_key'    => $client_key,
					'client_secret' => $client_secret,
					'code'          => $code,
					'grant_type'    => 'authorization_code',
					'redirect_uri'  => self::redirect_uri(),
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['access_token'] ) ) {
			$msg = isset( $body['error_description'] ) ? (string) $body['error_description'] : __( 'No se obtuvo access token de TikTok.', 'vitacare-crm' );
			return new WP_Error( 'vitacare_crm_tt_token', $msg );
		}

		self::store_tokens( $body );

		$profile = self::fetch_user_info( (string) $body['access_token'] );
		if ( is_wp_error( $profile ) ) {
			// La cuenta ya quedó autorizada (tenemos token); no revertir por un
			// fallo puntual al leer el perfil -- se puede reintentar luego.
			Vitacare_Crm_Logger::error( 'tt_userinfo_failed', array( 'msg' => $profile->get_error_message() ) );
			return true;
		}

		update_option( self::OPTION_OPEN_ID, (string) ( $profile['open_id'] ?? '' ), false );
		update_option( self::OPTION_UNION_ID, (string) ( $profile['union_id'] ?? '' ), false );
		update_option( self::OPTION_DISPLAY_NAME, (string) ( $profile['display_name'] ?? '' ), false );
		update_option( self::OPTION_AVATAR_URL, (string) ( $profile['avatar_url'] ?? '' ), false );
		update_option( self::OPTION_CONNECTED_AT, current_time( 'mysql' ), false );

		return true;
	}

	/**
	 * @param array<string, mixed> $token_body
	 */
	private static function store_tokens( array $token_body ): void {
		update_option( self::OPTION_ACCESS_TOKEN, Vitacare_Crm_Settings::store_secret( (string) $token_body['access_token'] ), false );
		if ( ! empty( $token_body['refresh_token'] ) ) {
			update_option( self::OPTION_REFRESH_TOKEN, Vitacare_Crm_Settings::store_secret( (string) $token_body['refresh_token'] ), false );
		}
		$expires_in = isset( $token_body['expires_in'] ) ? (int) $token_body['expires_in'] : 86400;
		update_option( self::OPTION_TOKEN_EXPIRES, time() + max( 0, $expires_in ), false );
		// Si el open_id no viene del perfil (fallback), al menos queda registrado.
		if ( ! empty( $token_body['open_id'] ) && self::get_open_id() === '' ) {
			update_option( self::OPTION_OPEN_ID, (string) $token_body['open_id'], false );
		}
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private static function fetch_user_info( string $access_token ) {
		$url = add_query_arg(
			array( 'fields' => 'open_id,union_id,avatar_url,display_name' ),
			self::USERINFO_URL
		);
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'User-Agent'    => 'VITACARE-CRM/' . VITACARE_CRM_VERSION,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['data']['user'] ) || ! is_array( $body['data']['user'] ) ) {
			$msg = isset( $body['error']['message'] ) ? (string) $body['error']['message'] : __( 'Respuesta inválida al leer el perfil de TikTok.', 'vitacare-crm' );
			return new WP_Error( 'vitacare_crm_tt_userinfo', $msg );
		}
		return $body['data']['user'];
	}

	/**
	 * Renueva el access token con el refresh token guardado. No hay ningún
	 * canal de mensajería que dependa de esto (C-6: TikTok no tiene API de
	 * DMs) -- se ofrece como botón manual para mantener viva la verificación
	 * de cuenta sin tener que rehacer el OAuth completo cada 24 h.
	 *
	 * @return true|WP_Error
	 */
	public static function refresh_access_token(): true|WP_Error {
		$refresh_token = self::get_refresh_token();
		if ( $refresh_token === '' ) {
			return new WP_Error( 'vitacare_crm_tt_no_refresh', __( 'No hay refresh token guardado; reconecta la cuenta.', 'vitacare-crm' ) );
		}
		$client_key    = Vitacare_Crm_Settings::get( 'tiktok_client_key' );
		$client_secret = Vitacare_Crm_Settings::get( 'tiktok_client_secret' );

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => array(
					'client_key'    => $client_key,
					'client_secret' => $client_secret,
					'grant_type'    => 'refresh_token',
					'refresh_token' => $refresh_token,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['access_token'] ) ) {
			$msg = isset( $body['error_description'] ) ? (string) $body['error_description'] : __( 'No se pudo renovar el token de TikTok.', 'vitacare-crm' );
			return new WP_Error( 'vitacare_crm_tt_refresh', $msg );
		}
		self::store_tokens( $body );
		return true;
	}

	public static function disconnect(): void {
		delete_option( self::OPTION_OPEN_ID );
		delete_option( self::OPTION_UNION_ID );
		delete_option( self::OPTION_DISPLAY_NAME );
		delete_option( self::OPTION_AVATAR_URL );
		delete_option( self::OPTION_ACCESS_TOKEN );
		delete_option( self::OPTION_REFRESH_TOKEN );
		delete_option( self::OPTION_TOKEN_EXPIRES );
		delete_option( self::OPTION_CONNECTED_AT );
	}

	private static function flash( string $type, string $msg ): void {
		set_transient( 'vitacare_crm_tt_flash', array( 'type' => $type, 'msg' => $msg ), 60 );
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso.', 'vitacare-crm' ) );
		}

		if ( isset( $_POST['vitacare_crm_tt_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vitacare_crm_tt_nonce'] ) ), 'vitacare_crm_tt' )
		) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$action = isset( $_POST['tt_action'] ) ? sanitize_key( wp_unslash( $_POST['tt_action'] ) ) : '';
			if ( $action === 'disconnect' ) {
				self::disconnect();
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'TikTok desconectado.', 'vitacare-crm' ) . '</p></div>';
			} elseif ( $action === 'refresh' ) {
				$r = self::refresh_access_token();
				if ( is_wp_error( $r ) ) {
					echo '<div class="notice notice-error"><p>' . esc_html( $r->get_error_message() ) . '</p></div>';
				} else {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Token renovado.', 'vitacare-crm' ) . '</p></div>';
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

		$flash = get_transient( 'vitacare_crm_tt_flash' );
		if ( is_array( $flash ) ) {
			delete_transient( 'vitacare_crm_tt_flash' );
			$class = ( $flash['type'] ?? '' ) === 'error' ? 'notice-error' : 'notice-success';
			echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( (string) ( $flash['msg'] ?? '' ) ) . '</p></div>';
		}

		$connected = self::is_connected();
		$redirect  = self::redirect_uri();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'TikTok — Conectar cuenta (Login Kit)', 'vitacare-crm' ); ?></h1>

			<div class="vcrm-callout-danger vcrm-callout">
				<strong><?php echo esc_html__( 'Sin canal de mensajería', 'vitacare-crm' ); ?></strong>
				<p style="margin:8px 0 0">
					<?php
					echo esc_html__(
						'TikTok no publica ninguna API oficial para que apps de terceros envíen o reciban mensajes directos (DM) ni para leer/responder comentarios. Confirmado contra la documentación de TikTok for Developers: los productos disponibles son Login Kit, Share Kit, Content Posting API, Display API, Webhooks de contenido, Data Portability y Research API — ninguno cubre mensajería. Por eso este conector solo verifica la cuenta; TikTok no aparece como canal en la bandeja /crm.',
						'vitacare-crm'
					);
					?>
				</p>
			</div>

			<div class="vcrm-callout">
				<p style="margin:0">
					<?php
					echo esc_html__(
						'Lo que sí hace: autoriza tu cuenta de TikTok (OAuth oficial v2) y guarda su perfil básico (nombre, avatar, ID) para dejar constancia de que la cuenta quedó verificada.',
						'vitacare-crm'
					);
					?>
				</p>
			</div>

			<h2><?php echo esc_html__( 'Requisitos en TikTok for Developers', 'vitacare-crm' ); ?></h2>
			<ol>
				<li><?php echo esc_html__( 'Client Key y Client Secret guardados en CRM → Credenciales.', 'vitacare-crm' ); ?></li>
				<li>
					<?php echo esc_html__( 'Redirect URI registrada en la app de TikTok (debe coincidir exacto, HTTPS):', 'vitacare-crm' ); ?>
					<br /><code class="vcrm-mono"><?php echo esc_html( $redirect ); ?></code>
				</li>
				<li><?php echo esc_html__( 'Producto Login Kit activado en la app, con el scope user.info.basic.', 'vitacare-crm' ); ?></li>
			</ol>

			<?php if ( $connected ) : ?>
				<div class="vcrm-admin-card" style="background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:16px;max-width:560px">
					<h2 style="margin-top:0"><?php echo esc_html__( 'Conectado', 'vitacare-crm' ); ?></h2>
					<p>
						<?php if ( self::get_avatar_url() !== '' ) : ?>
							<img src="<?php echo esc_url( self::get_avatar_url() ); ?>" alt="" width="48" height="48" style="border-radius:50%;vertical-align:middle;margin-right:10px" />
						<?php endif; ?>
						<strong><?php echo esc_html( self::get_display_name() !== '' ? self::get_display_name() : __( '(sin nombre)', 'vitacare-crm' ) ); ?></strong>
						<br />
						<code><?php echo esc_html( self::get_open_id() ); ?></code>
					</p>
					<p>
						<strong><?php echo esc_html__( 'Desde:', 'vitacare-crm' ); ?></strong>
						<?php echo esc_html( (string) get_option( self::OPTION_CONNECTED_AT, '—' ) ); ?>
						·
						<?php
						echo self::token_expired()
							? esc_html__( 'token expirado', 'vitacare-crm' )
							: esc_html__( 'token vigente', 'vitacare-crm' );
						?>
					</p>
					<form method="post" style="display:inline-block;margin-right:8px">
						<?php wp_nonce_field( 'vitacare_crm_tt', 'vitacare_crm_tt_nonce' ); ?>
						<input type="hidden" name="tt_action" value="refresh" />
						<?php submit_button( __( 'Renovar token', 'vitacare-crm' ), 'secondary', 'submit', false ); ?>
					</form>
					<form method="post" style="display:inline-block;margin-right:8px">
						<?php wp_nonce_field( 'vitacare_crm_tt', 'vitacare_crm_tt_nonce' ); ?>
						<input type="hidden" name="tt_action" value="disconnect" />
						<?php submit_button( __( 'Desconectar TikTok', 'vitacare-crm' ), 'delete', 'submit', false ); ?>
					</form>
					<form method="post" style="display:inline-block">
						<?php wp_nonce_field( 'vitacare_crm_tt', 'vitacare_crm_tt_nonce' ); ?>
						<input type="hidden" name="tt_action" value="connect" />
						<?php submit_button( __( 'Reconectar / cambiar cuenta', 'vitacare-crm' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
			<?php else : ?>
				<h2><?php echo esc_html__( 'Conectar', 'vitacare-crm' ); ?></h2>
				<form method="post">
					<?php wp_nonce_field( 'vitacare_crm_tt', 'vitacare_crm_tt_nonce' ); ?>
					<input type="hidden" name="tt_action" value="connect" />
					<?php submit_button( __( 'Conectar con TikTok', 'vitacare-crm' ), 'primary' ); ?>
				</form>
			<?php endif; ?>

			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=vitacare-crm-accounts' ) ); ?>"><?php echo esc_html__( '← Cuentas conectadas', 'vitacare-crm' ); ?></a>
				|
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=vitacare-crm-settings' ) ); ?>"><?php echo esc_html__( 'Credenciales', 'vitacare-crm' ); ?></a>
			</p>
		</div>
		<?php
	}
}

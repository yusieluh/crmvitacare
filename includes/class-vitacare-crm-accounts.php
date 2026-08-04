<?php
defined( 'ABSPATH' ) || exit;

/**
 * C-1: Cuentas conectadas.
 * C-7: Asistente WhatsApp Coexistence (oficial; sin QR no oficial).
 */
final class Vitacare_Crm_Accounts {

	public const OPTION_COEX_CHECKLIST = 'vitacare_crm_coex_checklist';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
	}

	public static function enqueue_admin( string $hook ): void {
		if ( strpos( $hook, 'vitacare-crm' ) === false ) {
			return;
		}
		wp_register_style( 'vitacare-crm-admin', false, array(), VITACARE_CRM_VERSION );
		wp_enqueue_style( 'vitacare-crm-admin' );
		wp_add_inline_style(
			'vitacare-crm-admin',
			'
			.vcrm-admin-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin:16px 0 24px}
			.vcrm-admin-card{background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:16px 18px;box-shadow:0 1px 1px rgba(0,0,0,.04)}
			.vcrm-admin-card h2{margin:0 0 8px;font-size:1.05rem}
			.vcrm-admin-card p{margin:0 0 8px;color:#50575e}
			.vcrm-status{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;font-weight:600}
			.vcrm-status-ok{background:#d5f5e3;color:#0a6b2d}
			.vcrm-status-warn{background:#fcf0c9;color:#8a6d00}
			.vcrm-status-off{background:#f0f0f1;color:#646970}
			.vcrm-status-err{background:#f8d7da;color:#8a1f11}
			.vcrm-check-list{list-style:none;margin:0;padding:0}
			.vcrm-check-list li{padding:8px 0;border-bottom:1px solid #f0f0f1;display:flex;gap:10px;align-items:flex-start}
			.vcrm-check-list li:last-child{border-bottom:0}
			.vcrm-callout{background:#f0f6fc;border-left:4px solid #2271b1;padding:12px 14px;margin:12px 0}
			.vcrm-callout-warn{background:#fcf9e8;border-left-color:#dba617}
			.vcrm-callout-danger{background:#fcf0f1;border-left-color:#d63638}
			.vcrm-mono{font-family:Consolas,Monaco,monospace;font-size:12px;word-break:break-all}
			'
		);
	}

	public static function register_menu(): void {
		add_submenu_page(
			'vitacare-crm-accounts',
			__( 'Cuentas conectadas', 'vitacare-crm' ),
			__( 'Cuentas conectadas', 'vitacare-crm' ),
			'manage_options',
			'vitacare-crm-accounts',
			array( __CLASS__, 'render_accounts' )
		);
		add_submenu_page(
			'vitacare-crm-accounts',
			__( 'WhatsApp Coexistence', 'vitacare-crm' ),
			__( 'WhatsApp (oficial)', 'vitacare-crm' ),
			'manage_options',
			'vitacare-crm-whatsapp',
			array( __CLASS__, 'render_whatsapp_guide' )
		);
	}

	/**
	 * Estado de cada canal para la tarjetas C-1.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_channels_status(): array {
		$wa_webhook = Vitacare_Crm_Settings::whatsapp_webhook_ready();
		$wa_send    = Vitacare_Crm_Settings::flag( 'whatsapp' )
			&& Vitacare_Crm_Settings::get( 'access_token' ) !== ''
			&& Vitacare_Crm_Settings::get( 'phone_number_id' ) !== '';
		$token_h    = class_exists( 'Vitacare_Crm_Graph' ) ? Vitacare_Crm_Graph::token_health() : 'unknown';
		$checklist  = self::get_checklist();
		$steps      = self::coexistence_steps();
		$done       = 0;
		foreach ( array_keys( $steps ) as $key ) {
			if ( ! empty( $checklist[ $key ] ) ) {
				++$done;
			}
		}
		$total = count( $steps );
		$pct   = $total > 0 ? (int) round( 100 * $done / $total ) : 0;

		$wa_level = 'off';
		$wa_label = __( 'No configurado', 'vitacare-crm' );
		if ( $wa_send && $wa_webhook && $token_h !== 'invalid' && $pct >= 80 ) {
			$wa_level = 'ok';
			$wa_label = __( 'Listo (oficial)', 'vitacare-crm' );
		} elseif ( $wa_webhook || $wa_send || $pct > 0 ) {
			$wa_level = 'warn';
			$wa_label = __( 'En progreso', 'vitacare-crm' );
		}
		if ( $token_h === 'invalid' ) {
			$wa_level = 'err';
			$wa_label = __( 'Token inválido', 'vitacare-crm' );
		}

		$wa_health = class_exists( 'Vitacare_Crm_Channel_Whatsapp' ) ? Vitacare_Crm_Channel_Whatsapp::health() : array( 'available' => false, 'quality_rating' => null, 'messaging_limit' => null );
		$wa_detail = sprintf(
			/* translators: 1: checklist percent, 2: webhook yes/no, 3: send yes/no */
			__( 'Coexistence checklist %1$d%% · Webhook %2$s · Envío %3$s', 'vitacare-crm' ),
			$pct,
			$wa_webhook ? __( 'OK', 'vitacare-crm' ) : __( 'pendiente', 'vitacare-crm' ),
			$wa_send ? __( 'OK', 'vitacare-crm' ) : __( 'pendiente', 'vitacare-crm' )
		);
		if ( $wa_health['available'] ) {
			$wa_detail .= ' · ' . sprintf(
				/* translators: 1: quality rating, 2: messaging limit tier */
				__( 'Calidad: %1$s · Límite de mensajería: %2$s', 'vitacare-crm' ),
				$wa_health['quality_rating'] ?? '—',
				$wa_health['messaging_limit'] ?? '—'
			);
			if ( 'GREEN' !== $wa_health['quality_rating'] && null !== $wa_health['quality_rating'] ) {
				$wa_level = 'err';
				$wa_label = __( 'Calidad del número en riesgo', 'vitacare-crm' );
			}
		}

		return array(
			'whatsapp'  => array(
				'name'        => 'WhatsApp',
				'level'       => $wa_level,
				'label'       => $wa_label,
				'detail'      => $wa_detail,
				'action_url'  => admin_url( 'admin.php?page=vitacare-crm-whatsapp' ),
				'action_label'=> __( 'Asistente Coexistence', 'vitacare-crm' ),
				'flag'        => Vitacare_Crm_Settings::flag( 'whatsapp' ),
			),
			'facebook'  => self::facebook_card_status(),
			'instagram' => self::instagram_card_status(),
			// Zoho Mail primero: es el correo institucional y el canal principal
			// de correo; Gmail queda como proveedor secundario/opcional (D-22).
			'zoho'      => self::zoho_card_status(),
			'gmail'     => self::gmail_card_status(),
			'tiktok'    => self::tiktok_card_status(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function gmail_card_status(): array {
		$connected = class_exists( 'Vitacare_Crm_Gmail' ) && Vitacare_Crm_Gmail::is_connected();
		if ( $connected ) {
			return array(
				'name'         => 'Gmail',
				'level'        => 'ok',
				'label'        => __( 'Conectado', 'vitacare-crm' ),
				'detail'       => sprintf(
					/* translators: %s: email */
					__( 'Buzón: %s · sync automático ~5 min · secundario (el correo institucional es Zoho Mail)', 'vitacare-crm' ),
					Vitacare_Crm_Gmail::get_email()
				),
				'action_url'   => admin_url( 'admin.php?page=vitacare-crm-gmail' ),
				'action_label' => __( 'Administrar Gmail', 'vitacare-crm' ),
				'flag'         => true,
			);
		}
		return array(
			'name'         => 'Gmail',
			'level'        => 'off',
			'label'        => __( 'Pendiente', 'vitacare-crm' ),
			'detail'       => __( 'OAuth Google · opcional, secundario al correo institucional (Zoho Mail).', 'vitacare-crm' ),
			'action_url'   => admin_url( 'admin.php?page=vitacare-crm-gmail' ),
			'action_label' => __( 'Conectar Gmail', 'vitacare-crm' ),
			'flag'         => Vitacare_Crm_Settings::flag( 'email' ),
		);
	}

	/**
	 * Zoho Mail: canal principal de correo -- es el correo institucional de
	 * VITACARE. Vive bajo el mismo canal "email" que Gmail, que queda como
	 * proveedor secundario/opcional (D-22 en ESTADO_CRM.md).
	 *
	 * @return array<string, mixed>
	 */
	private static function zoho_card_status(): array {
		$connected = class_exists( 'Vitacare_Crm_Zoho' ) && Vitacare_Crm_Zoho::is_connected();
		if ( $connected ) {
			return array(
				'name'         => 'Zoho Mail',
				'level'        => 'ok',
				'label'        => __( 'Conectado', 'vitacare-crm' ),
				'detail'       => sprintf(
					/* translators: %s: email */
					__( 'Buzón institucional: %s · sync automático ~5 min', 'vitacare-crm' ),
					Vitacare_Crm_Zoho::get_email()
				),
				'action_url'   => admin_url( 'admin.php?page=vitacare-crm-zoho' ),
				'action_label' => __( 'Administrar Zoho Mail', 'vitacare-crm' ),
				'flag'         => true,
			);
		}
		return array(
			'name'         => 'Zoho Mail',
			'level'        => 'off',
			'label'        => __( 'Pendiente', 'vitacare-crm' ),
			'detail'       => __( 'OAuth Zoho · canal principal de correo (correo institucional de VITACARE).', 'vitacare-crm' ),
			'action_url'   => admin_url( 'admin.php?page=vitacare-crm-zoho' ),
			'action_label' => __( 'Conectar Zoho Mail', 'vitacare-crm' ),
			'flag'         => Vitacare_Crm_Settings::flag( 'email' ),
		);
	}

	/**
	 * C-6: TikTok Login Kit conecta y verifica la cuenta, pero no hay API
	 * pública de DMs/comentarios de terceros -- por eso nunca llega a
	 * "canal de mensajería" como los demás, solo a "cuenta verificada".
	 *
	 * @return array<string, mixed>
	 */
	private static function tiktok_card_status(): array {
		$connected = class_exists( 'Vitacare_Crm_Tiktok_Oauth' ) && Vitacare_Crm_Tiktok_Oauth::is_connected();
		if ( $connected ) {
			$name = Vitacare_Crm_Tiktok_Oauth::get_display_name();
			return array(
				'name'         => 'TikTok',
				'level'        => 'ok',
				'label'        => __( 'Cuenta verificada', 'vitacare-crm' ),
				'detail'       => ( $name !== ''
					? sprintf(
						/* translators: %s: tiktok display name */
						__( 'Cuenta: %s · sin canal de mensajes (TikTok no tiene API pública de DMs)', 'vitacare-crm' ),
						$name
					)
					: __( 'Cuenta conectada · sin canal de mensajes (TikTok no tiene API pública de DMs)', 'vitacare-crm' ) ),
				'action_url'   => admin_url( 'admin.php?page=vitacare-crm-tiktok' ),
				'action_label' => __( 'Administrar TikTok', 'vitacare-crm' ),
				'flag'         => true,
			);
		}
		return array(
			'name'         => 'TikTok',
			'level'        => 'off',
			'label'        => __( 'Pendiente', 'vitacare-crm' ),
			'detail'       => __( 'Login Kit oficial · solo verifica cuenta, sin DMs (limitación de la API pública de TikTok).', 'vitacare-crm' ),
			'action_url'   => admin_url( 'admin.php?page=vitacare-crm-tiktok' ),
			'action_label' => __( 'Conectar TikTok', 'vitacare-crm' ),
			'flag'         => false,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function facebook_card_status(): array {
		$connected = class_exists( 'Vitacare_Crm_Facebook_Oauth' ) && Vitacare_Crm_Facebook_Oauth::is_connected();
		if ( $connected ) {
			$name = Vitacare_Crm_Facebook_Oauth::get_page_name();
			return array(
				'name'         => 'Facebook Messenger',
				'level'        => 'ok',
				'label'        => __( 'Página conectada', 'vitacare-crm' ),
				'detail'       => $name !== ''
					? sprintf(
						/* translators: %s: page name */
						__( 'Página: %s · OAuth OK (mensajería webhook en C-4)', 'vitacare-crm' ),
						$name
					)
					: __( 'OAuth OK', 'vitacare-crm' ),
				'action_url'   => admin_url( 'admin.php?page=vitacare-crm-facebook' ),
				'action_label' => __( 'Administrar Facebook', 'vitacare-crm' ),
				'flag'         => true,
			);
		}
		return array(
			'name'         => 'Facebook Messenger',
			'level'        => Vitacare_Crm_Settings::flag( 'facebook' ) ? 'warn' : 'off',
			'label'        => Vitacare_Crm_Settings::flag( 'facebook' )
				? __( 'Flag ON — falta conectar', 'vitacare-crm' )
				: __( 'Pendiente', 'vitacare-crm' ),
			'detail'       => __( 'Conecta tu cuenta Meta y elige la Página que administras.', 'vitacare-crm' ),
			'action_url'   => admin_url( 'admin.php?page=vitacare-crm-facebook' ),
			'action_label' => __( 'Conectar Facebook', 'vitacare-crm' ),
			'flag'         => Vitacare_Crm_Settings::flag( 'facebook' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function instagram_card_status(): array {
		$connected = class_exists( 'Vitacare_Crm_Facebook_Oauth' ) && Vitacare_Crm_Facebook_Oauth::is_instagram_connected();
		if ( $connected ) {
			$username = Vitacare_Crm_Facebook_Oauth::get_ig_username();
			return array(
				'name'         => 'Instagram',
				'level'        => 'ok',
				'label'        => __( 'Cuenta vinculada', 'vitacare-crm' ),
				'detail'       => $username !== ''
					? sprintf(
						/* translators: %s: instagram username */
						__( 'Cuenta: @%s · vinculada a la Página de Facebook conectada', 'vitacare-crm' ),
						$username
					)
					: __( 'Cuenta profesional vinculada a la Página conectada.', 'vitacare-crm' ),
				'action_url'   => admin_url( 'admin.php?page=vitacare-crm-facebook' ),
				'action_label' => __( 'Administrar en Facebook', 'vitacare-crm' ),
				'flag'         => true,
			);
		}
		$fb_connected = class_exists( 'Vitacare_Crm_Facebook_Oauth' ) && Vitacare_Crm_Facebook_Oauth::is_connected();
		return array(
			'name'         => 'Instagram',
			'level'        => 'off',
			'label'        => __( 'Pendiente', 'vitacare-crm' ),
			'detail'       => $fb_connected
				? __( 'Página de Facebook conectada, pero sin cuenta profesional de Instagram vinculada.', 'vitacare-crm' )
				: __( 'Conecta primero Facebook: la cuenta de Instagram se administra a través de la misma Página.', 'vitacare-crm' ),
			'action_url'   => admin_url( 'admin.php?page=vitacare-crm-facebook' ),
			'action_label' => $fb_connected ? __( 'Buscar cuenta Instagram', 'vitacare-crm' ) : __( 'Conectar Facebook', 'vitacare-crm' ),
			'flag'         => Vitacare_Crm_Settings::flag( 'instagram' ),
		);
	}

	/**
	 * Pasos del asistente Coexistence (oficial).
	 *
	 * @return array<string, string>
	 */
	public static function coexistence_steps(): array {
		return array(
			'meta_app'       => __( 'Crear o usar una App en Meta for Developers con producto WhatsApp.', 'vitacare-crm' ),
			'business'       => __( 'Vincular Meta Business / portafolio y la cuenta WhatsApp Business (WABA).', 'vitacare-crm' ),
			'coexistence'    => __( 'Activar Coexistence (Cloud API) para el número que ya usa la app WhatsApp Business del celular — flujo oficial Meta, no QR de WhatsApp Web.', 'vitacare-crm' ),
			'phone_id'       => __( 'Copiar Phone Number ID y WABA ID a Credenciales CRM (o wp-config).', 'vitacare-crm' ),
			'tokens'         => __( 'Configurar App Secret, Verify Token y Access Token (system user de larga duración).', 'vitacare-crm' ),
			'flag_on'        => __( 'Activar el flag «WhatsApp Cloud API» en Credenciales.', 'vitacare-crm' ),
			'webhook_url'    => __( 'En Meta, registrar la URL del webhook del CRM y el Verify Token.', 'vitacare-crm' ),
			'webhook_fields' => __( 'Suscribir el campo «messages» (y estados) en el webhook.', 'vitacare-crm' ),
			'verify_get'     => __( 'Verificar que Meta complete el challenge GET (verify) sin error.', 'vitacare-crm' ),
			'test_in'        => __( 'Enviar un mensaje de prueba al número y verlo en /crm.', 'vitacare-crm' ),
			'test_out'       => __( 'Responder desde la bandeja CRM y confirmar llegada al celular del cliente.', 'vitacare-crm' ),
		);
	}

	/**
	 * @return array<string, bool>
	 */
	public static function get_checklist(): array {
		$raw = get_option( self::OPTION_COEX_CHECKLIST, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( self::coexistence_steps() as $key => $_label ) {
			$out[ $key ] = ! empty( $raw[ $key ] );
		}
		return $out;
	}

	public static function save_checklist_from_post(): void {
		$steps = self::coexistence_steps();
		$save  = array();
		foreach ( array_keys( $steps ) as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$save[ $key ] = isset( $_POST['coex'][ $key ] ) ? 1 : 0;
		}
		update_option( self::OPTION_COEX_CHECKLIST, $save, false );
	}

	public static function render_accounts(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso.', 'vitacare-crm' ) );
		}
		$channels = self::get_channels_status();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'CRM VITACARE — Cuentas conectadas', 'vitacare-crm' ); ?></h1>
			<p>
				<?php
				echo esc_html__(
					'Estado de cada canal. La integración oficial pide acceso a la cuenta del proveedor (OAuth) en fases siguientes. WhatsApp usa Cloud API + Coexistence (el celular sigue funcionando). No se usa QR no oficial de dispositivo vinculado.',
					'vitacare-crm'
				);
				?>
			</p>
			<p>
				<a class="button" href="<?php echo esc_url( home_url( '/' . VITACARE_CRM_PAGE_SLUG . '/' ) ); ?>"><?php echo esc_html__( 'Abrir bandeja /crm', 'vitacare-crm' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=vitacare-crm-reports' ) ); ?>"><?php echo esc_html__( 'Reportes', 'vitacare-crm' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=vitacare-crm-leads' ) ); ?>"><?php echo esc_html__( 'Leads', 'vitacare-crm' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=vitacare-crm-links' ) ); ?>"><?php echo esc_html__( 'Enlaces', 'vitacare-crm' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=vitacare-crm-campaigns' ) ); ?>"><?php echo esc_html__( 'Campañas de correo', 'vitacare-crm' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=vitacare-crm-settings' ) ); ?>"><?php echo esc_html__( 'Credenciales', 'vitacare-crm' ); ?></a>
			</p>

			<div class="vcrm-admin-grid">
				<?php foreach ( $channels as $id => $ch ) : ?>
					<div class="vcrm-admin-card">
						<h2><?php echo esc_html( $ch['name'] ); ?></h2>
						<p>
							<span class="vcrm-status vcrm-status-<?php echo esc_attr( $ch['level'] === 'ok' ? 'ok' : ( $ch['level'] === 'warn' ? 'warn' : ( $ch['level'] === 'err' ? 'err' : 'off' ) ) ); ?>">
								<?php echo esc_html( $ch['label'] ); ?>
							</span>
						</p>
						<p><?php echo esc_html( $ch['detail'] ); ?></p>
						<?php if ( ! empty( $ch['action_url'] ) ) : ?>
							<p>
								<a class="button button-primary" href="<?php echo esc_url( $ch['action_url'] ); ?>">
									<?php echo esc_html( $ch['action_label'] ); ?>
								</a>
							</p>
						<?php else : ?>
							<p class="description"><?php echo esc_html__( 'Conector OAuth en una próxima entrega.', 'vitacare-crm' ); ?></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="vcrm-callout-warn vcrm-callout">
				<strong><?php echo esc_html__( 'WhatsApp y códigos QR', 'vitacare-crm' ); ?></strong>
				<p style="margin:8px 0 0">
					<?php
					echo esc_html__(
						'No generamos un QR de «dispositivo vinculado» tipo WhatsApp Web (Baileys / similares): viola las reglas de Meta y puede bloquear el número. El camino seguro es Coexistence oficial: la app del teléfono sigue y el CRM usa la API.',
						'vitacare-crm'
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}

	public static function render_whatsapp_guide(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso.', 'vitacare-crm' ) );
		}

		if ( isset( $_POST['vitacare_crm_coex_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vitacare_crm_coex_nonce'] ) ), 'vitacare_crm_save_coex' )
		) {
			self::save_checklist_from_post();
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Checklist Coexistence guardado.', 'vitacare-crm' ) . '</p></div>';
		}

		$checklist = self::get_checklist();
		$steps     = self::coexistence_steps();
		$done      = count( array_filter( $checklist ) );
		$total     = count( $steps );
		$webhook   = Vitacare_Crm_Settings::webhook_url();
		$ready     = Vitacare_Crm_Settings::whatsapp_webhook_ready();
		$send      = Vitacare_Crm_Settings::flag( 'whatsapp' )
			&& Vitacare_Crm_Settings::get( 'access_token' ) !== ''
			&& Vitacare_Crm_Settings::get( 'phone_number_id' ) !== '';
		$token_h   = class_exists( 'Vitacare_Crm_Graph' ) ? Vitacare_Crm_Graph::token_health() : 'unknown';

		// Auto-sugerir checks técnicos según settings actuales (no sobrescribe guardado; solo badge).
		$live = array(
			'tokens'   => Vitacare_Crm_Settings::get( 'app_secret' ) !== ''
				&& Vitacare_Crm_Settings::get( 'verify_token' ) !== ''
				&& Vitacare_Crm_Settings::get( 'access_token' ) !== '',
			'phone_id' => Vitacare_Crm_Settings::get( 'phone_number_id' ) !== '',
			'flag_on'  => Vitacare_Crm_Settings::flag( 'whatsapp' ),
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'WhatsApp — Conexión oficial (Coexistence)', 'vitacare-crm' ); ?></h1>

			<div class="vcrm-callout">
				<p style="margin:0">
					<?php
					echo esc_html__(
						'Objetivo: que el celular con WhatsApp Business siga funcionando y el CRM reciba y envíe mensajes a la vez. Eso lo permite Meta con Cloud API + Coexistence.',
						'vitacare-crm'
					);
					?>
				</p>
			</div>

			<div class="vcrm-callout-danger vcrm-callout">
				<strong><?php echo esc_html__( 'No usamos QR de dispositivo vinculado no oficial', 'vitacare-crm' ); ?></strong>
				<p style="margin:8px 0 0">
					<?php
					echo esc_html__(
						'Vincular el CRM como si fuera WhatsApp Web con librerías no autorizadas puede bloquear el número. Este asistente solo guía el flujo oficial de Meta.',
						'vitacare-crm'
					);
					?>
				</p>
			</div>

			<h2><?php echo esc_html__( 'Estado actual del CRM', 'vitacare-crm' ); ?></h2>
			<div class="vcrm-admin-grid">
				<div class="vcrm-admin-card">
					<h2><?php echo esc_html__( 'Checklist', 'vitacare-crm' ); ?></h2>
					<p><strong><?php echo esc_html( (string) $done . ' / ' . (string) $total ); ?></strong></p>
				</div>
				<div class="vcrm-admin-card">
					<h2><?php echo esc_html__( 'Webhook', 'vitacare-crm' ); ?></h2>
					<p>
						<span class="vcrm-status <?php echo $ready ? 'vcrm-status-ok' : 'vcrm-status-warn'; ?>">
							<?php echo $ready ? esc_html__( 'Listo para recibir', 'vitacare-crm' ) : esc_html__( 'Incompleto', 'vitacare-crm' ); ?>
						</span>
					</p>
					<p class="vcrm-mono"><?php echo esc_html( $webhook ); ?></p>
				</div>
				<div class="vcrm-admin-card">
					<h2><?php echo esc_html__( 'Envío Graph', 'vitacare-crm' ); ?></h2>
					<p>
						<span class="vcrm-status <?php echo $send ? 'vcrm-status-ok' : 'vcrm-status-warn'; ?>">
							<?php echo $send ? esc_html__( 'Token + Phone ID OK', 'vitacare-crm' ) : esc_html__( 'Falta token o Phone ID', 'vitacare-crm' ); ?>
						</span>
					</p>
					<p>
						<?php
						echo esc_html__( 'Salud token:', 'vitacare-crm' ) . ' ';
						echo esc_html( $token_h );
						?>
					</p>
				</div>
			</div>

			<h2><?php echo esc_html__( 'URL para Meta (copiar)', 'vitacare-crm' ); ?></h2>
			<p class="vcrm-mono" style="background:#fff;border:1px solid #c3c4c7;padding:10px;border-radius:4px"><?php echo esc_html( $webhook ); ?></p>
			<p class="description">
				<?php echo esc_html__( 'Enlaces permanentes de WordPress deben estar activos (no «Simple»). Fallback:', 'vitacare-crm' ); ?>
				<code class="vcrm-mono"><?php echo esc_html( home_url( '/?rest_route=/vitacare-crm/v1/webhooks/meta' ) ); ?></code>
			</p>

			<form method="post" action="">
				<?php wp_nonce_field( 'vitacare_crm_save_coex', 'vitacare_crm_coex_nonce' ); ?>
				<h2><?php echo esc_html__( 'Pasos Coexistence (marca lo ya hecho)', 'vitacare-crm' ); ?></h2>
				<ul class="vcrm-check-list">
					<?php foreach ( $steps as $key => $label ) : ?>
						<li>
							<input type="checkbox" name="coex[<?php echo esc_attr( $key ); ?>]" id="coex-<?php echo esc_attr( $key ); ?>" value="1" <?php checked( ! empty( $checklist[ $key ] ) ); ?> />
							<label for="coex-<?php echo esc_attr( $key ); ?>">
								<?php echo esc_html( $label ); ?>
								<?php if ( ! empty( $live[ $key ] ) ) : ?>
									<em style="color:#0a6b2d"> — <?php echo esc_html__( 'detectado en ajustes CRM', 'vitacare-crm' ); ?></em>
								<?php endif; ?>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php submit_button( __( 'Guardar checklist', 'vitacare-crm' ) ); ?>
			</form>

			<h2><?php echo esc_html__( 'Enlaces útiles', 'vitacare-crm' ); ?></h2>
			<ul>
				<li><a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Meta for Developers — Apps', 'vitacare-crm' ); ?></a></li>
				<li><a href="https://business.facebook.com/" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'Meta Business Suite', 'vitacare-crm' ); ?></a></li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=vitacare-crm-settings' ) ); ?>"><?php echo esc_html__( 'Credenciales del CRM', 'vitacare-crm' ); ?></a></li>
				<li><a href="<?php echo esc_url( home_url( '/' . VITACARE_CRM_PAGE_SLUG . '/' ) ); ?>"><?php echo esc_html__( 'Bandeja https://vitacareec.org/crm', 'vitacare-crm' ); ?></a></li>
			</ul>

			<p class="description">
				<?php
				echo esc_html__(
					'Documentación viva: ESTADO_CRM.md en el repositorio GitHub (D-04 Coexistence; D-04b sin QR no oficial).',
					'vitacare-crm'
				);
				?>
			</p>
		</div>
		<?php
	}
}

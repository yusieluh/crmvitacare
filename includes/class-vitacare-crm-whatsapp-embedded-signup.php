<?php
defined( 'ABSPATH' ) || exit;

/**
 * WhatsApp Business App Coexistence vía Embedded Signup oficial de Meta
 * (featureType: whatsapp_business_app_onboarding). Solo prepara el flujo:
 * esta clase no conecta ningún activo real por sí sola — requiere que un
 * administrador con sesión en wp-admin haga clic y complete el diálogo
 * oficial de Meta. No usa QR ni librerías de WhatsApp Web no oficiales;
 * el QR/código lo genera y muestra siempre Meta/WhatsApp Business App.
 */
final class Vitacare_Crm_Whatsapp_Embedded_Signup {

	public const OPTION_STATUS      = 'vitacare_crm_wa_embedded_status';
	public const OPTION_CONFIG_ID   = 'vitacare_crm_wa_embedded_config_id';
	public const OPTION_BUSINESS_ID = 'vitacare_crm_wa_business_id';
	public const OPTION_DISPLAY_NAME = 'vitacare_crm_wa_display_name';
	public const OPTION_LAST_ERROR   = 'vitacare_crm_wa_embedded_last_error';

	public const VALID_STATUSES = array(
		'not_started',
		'requirements_pending',
		'awaiting_meta_auth',
		'auth_completed',
		'exchanging_token',
		'waba_identified',
		'phone_identified',
		'subscribing_webhook',
		'coexistence_active',
		'error',
		'cancelled',
	);

	public static function init(): void {
		add_action( 'wp_ajax_vitacare_crm_wa_embedded_exchange', array( __CLASS__, 'ajax_exchange_code' ) );
		add_action( 'wp_ajax_vitacare_crm_wa_embedded_save_config', array( __CLASS__, 'ajax_save_config' ) );
	}

	public static function status(): string {
		$s = (string) get_option( self::OPTION_STATUS, 'not_started' );
		return in_array( $s, self::VALID_STATUSES, true ) ? $s : 'not_started';
	}

	private static function set_status( string $status, string $error = '' ): void {
		update_option( self::OPTION_STATUS, $status, false );
		if ( 'error' === $status && $error !== '' ) {
			update_option( self::OPTION_LAST_ERROR, $error, false );
		}
	}

	/**
	 * Guarda el Configuration ID de Embedded Signup (no es secreto, se genera en Meta for Developers).
	 */
	public static function ajax_save_config(): void {
		check_ajax_referer( 'vitacare_crm_wa_embedded', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sin permiso.', 'vitacare-crm' ) ), 403 );
		}
		$config_id = isset( $_POST['config_id'] ) ? sanitize_text_field( wp_unslash( $_POST['config_id'] ) ) : '';
		update_option( self::OPTION_CONFIG_ID, $config_id, false );
		wp_send_json_success( array( 'saved' => true ) );
	}

	/**
	 * Intercambia el `code` temporal de Embedded Signup por un access token,
	 * server-a-servidor (nunca se guarda en localStorage/URL/logs). Los
	 * identificadores waba_id/phone_number_id/business_id llegan del evento
	 * postMessage oficial de Meta capturado en el navegador, no se inventan.
	 */
	public static function ajax_exchange_code(): void {
		check_ajax_referer( 'vitacare_crm_wa_embedded', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sin permiso.', 'vitacare-crm' ) ), 403 );
		}

		$code            = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		$waba_id         = isset( $_POST['waba_id'] ) ? sanitize_text_field( wp_unslash( $_POST['waba_id'] ) ) : '';
		$phone_number_id = isset( $_POST['phone_number_id'] ) ? sanitize_text_field( wp_unslash( $_POST['phone_number_id'] ) ) : '';
		$business_id     = isset( $_POST['business_id'] ) ? sanitize_text_field( wp_unslash( $_POST['business_id'] ) ) : '';

		if ( $code === '' ) {
			self::set_status( 'error', 'missing_code' );
			wp_send_json_error( array( 'message' => __( 'Meta no envió el código de autorización.', 'vitacare-crm' ) ), 400 );
		}

		$app_id     = Vitacare_Crm_Settings::get( 'app_id' );
		$app_secret = Vitacare_Crm_Settings::get( 'app_secret' );
		if ( $app_id === '' || $app_secret === '' ) {
			self::set_status( 'error', 'app_credentials_missing' );
			wp_send_json_error( array( 'message' => __( 'Configura App ID y App Secret en Credenciales antes de conectar WhatsApp.', 'vitacare-crm' ) ), 400 );
		}

		self::set_status( 'auth_completed' );

		$version = Vitacare_Crm_Settings::graph_version();
		self::set_status( 'exchanging_token' );

		$exchange = wp_remote_get(
			add_query_arg(
				array(
					'client_id'     => $app_id,
					'client_secret' => $app_secret,
					'code'          => $code,
				),
				'https://graph.facebook.com/' . rawurlencode( $version ) . '/oauth/access_token'
			),
			array(
				'timeout'     => 20,
				'redirection' => 0,
			)
		);

		if ( is_wp_error( $exchange ) ) {
			self::set_status( 'error', 'token_exchange_http_error' );
			Vitacare_Crm_Logger::error( 'wa_embedded_exchange_failed', array( 'err' => $exchange->get_error_message() ) );
			wp_send_json_error( array( 'message' => __( 'No se pudo contactar a Meta para intercambiar el código.', 'vitacare-crm' ) ), 502 );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $exchange ), true );
		$http = (int) wp_remote_retrieve_response_code( $exchange );

		if ( $http < 200 || $http >= 300 || ! is_array( $body ) || empty( $body['access_token'] ) ) {
			self::set_status( 'error', 'token_not_exchanged' );
			Vitacare_Crm_Logger::error(
				'wa_embedded_exchange_rejected',
				array(
					'http'  => $http,
					'error' => is_array( $body ) && isset( $body['error']['message'] ) ? (string) $body['error']['message'] : 'unknown',
				)
			);
			wp_send_json_error( array( 'message' => __( 'Meta no entregó un token válido para este código.', 'vitacare-crm' ) ), 400 );
		}

		$token = (string) $body['access_token'];

		// Guardar en el mismo option ya usado por el canal WhatsApp (Fase 1: ya es de uso exclusivo WhatsApp).
		update_option( 'vitacare_crm_meta_access_token', Vitacare_Crm_Settings::store_secret( $token ), false );
		if ( $waba_id !== '' ) {
			update_option( 'vitacare_crm_wa_waba_id', $waba_id, false );
			self::set_status( 'waba_identified' );
		}
		if ( $phone_number_id !== '' ) {
			update_option( 'vitacare_crm_wa_phone_number_id', $phone_number_id, false );
			self::set_status( 'phone_identified' );
		}
		if ( $business_id !== '' ) {
			update_option( self::OPTION_BUSINESS_ID, $business_id, false );
		}

		$subscribed = false;
		if ( $waba_id !== '' ) {
			self::set_status( 'subscribing_webhook' );
			$sub = wp_remote_post(
				'https://graph.facebook.com/' . rawurlencode( $version ) . '/' . rawurlencode( $waba_id ) . '/subscribed_apps',
				array(
					'timeout' => 15,
					'headers' => array( 'Authorization' => 'Bearer ' . $token ),
				)
			);
			if ( ! is_wp_error( $sub ) ) {
				$sub_code   = (int) wp_remote_retrieve_response_code( $sub );
				$subscribed = $sub_code >= 200 && $sub_code < 300;
			}
		}

		if ( $waba_id !== '' && $phone_number_id !== '' && $subscribed ) {
			self::set_status( 'coexistence_active' );
		} elseif ( $waba_id === '' || $phone_number_id === '' ) {
			// Meta autorizó pero no entregó todavía los identificadores por postMessage.
			self::set_status( 'auth_completed' );
		}

		Vitacare_Crm_Logger::info(
			'wa_embedded_exchange_ok',
			array(
				'waba_present'  => $waba_id !== '',
				'phone_present' => $phone_number_id !== '',
				'subscribed'    => $subscribed,
			)
		);

		wp_send_json_success(
			array(
				'status'     => self::status(),
				'subscribed' => $subscribed,
			)
		);
	}

	public static function render_wizard(): void {
		$app_id    = Vitacare_Crm_Settings::get( 'app_id' );
		$config_id = (string) get_option( self::OPTION_CONFIG_ID, '' );
		$status    = self::status();
		$nonce     = wp_create_nonce( 'vitacare_crm_wa_embedded' );
		?>
		<div class="card" style="max-width:720px;padding:1.25em 1.5em">
			<h3><?php echo esc_html__( 'Conectar WhatsApp Business mediante coexistencia', 'vitacare-crm' ); ?></h3>
			<p class="description">
				<?php echo esc_html__( 'Usa el proceso oficial de Meta (WhatsApp Business App Coexistence + Embedded Signup) para mantener WhatsApp Business activo en el teléfono y conectar simultáneamente el número al CRM. No genera ni almacena ningún QR — el QR o código de vinculación lo muestra únicamente la app oficial de Meta/WhatsApp Business.', 'vitacare-crm' ); ?>
			</p>
			<p><strong><?php echo esc_html__( 'Estado actual:', 'vitacare-crm' ); ?></strong> <code id="vcrm-wa-status"><?php echo esc_html( $status ); ?></code></p>

			<?php if ( $app_id === '' ) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html__( 'Configura primero App ID y App Secret en Credenciales.', 'vitacare-crm' ); ?></p></div>
				<?php return; ?>
			<?php endif; ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="vcrm-wa-config-id"><?php echo esc_html__( 'Configuration ID (Embedded Signup)', 'vitacare-crm' ); ?></label></th>
					<td>
						<input type="text" id="vcrm-wa-config-id" class="regular-text" value="<?php echo esc_attr( $config_id ); ?>" placeholder="<?php esc_attr_e( 'Generado en Meta for Developers → WhatsApp → Configuración → Embedded Signup', 'vitacare-crm' ); ?>" />
						<button type="button" class="button" id="vcrm-wa-config-save"><?php echo esc_html__( 'Guardar', 'vitacare-crm' ); ?></button>
						<p class="description"><?php echo esc_html__( 'No es un secreto — identifica la configuración de onboarding creada en la consola de Meta para esta app.', 'vitacare-crm' ); ?></p>
					</td>
				</tr>
			</table>

			<div class="notice notice-warning inline">
				<p><?php echo esc_html__( 'Este proceso utiliza exclusivamente WhatsApp Business App Coexistence de Meta. El número continuará funcionando en la aplicación oficial siempre que Meta confirme la compatibilidad durante el registro.', 'vitacare-crm' ); ?></p>
			</div>
			<p>
				<label>
					<input type="checkbox" id="vcrm-wa-confirm" />
					<?php echo esc_html__( 'Confirmo que tengo acceso al teléfono, una copia de seguridad reciente de los chats y autorización para conectar este número.', 'vitacare-crm' ); ?>
				</label>
			</p>
			<p>
				<button type="button" class="button button-primary" id="vcrm-wa-connect" disabled <?php echo $config_id === '' ? 'title="' . esc_attr__( 'Guarda primero el Configuration ID', 'vitacare-crm' ) . '"' : ''; ?>>
					<?php echo esc_html__( 'Conectar WhatsApp Business mediante QR seguro', 'vitacare-crm' ); ?>
				</button>
			</p>
			<p class="description" id="vcrm-wa-result"></p>
		</div>
		<div id="fb-root"></div>
		<script>
		(function(){
			window.fbAsyncInit = function() {
				FB.init({ appId: <?php echo wp_json_encode( $app_id ); ?>, autoLogAppEvents: true, xfbml: false, version: 'v21.0' });
			};
			(function(d, s, id){
				var js, fjs = d.getElementsByTagName(s)[0];
				if (d.getElementById(id)) return;
				js = d.createElement(s); js.id = id;
				js.src = 'https://connect.facebook.com/en_US/sdk.js';
				fjs.parentNode.insertBefore(js, fjs);
			}(document, 'script', 'facebook-jssdk'));

			var waIds = { waba_id: '', phone_number_id: '', business_id: '' };

			window.addEventListener('message', function (event) {
				if (!event.origin || event.origin.indexOf('facebook.com') === -1) return;
				try {
					var data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
					if (data && data.type === 'WA_EMBEDDED_SIGNUP' && data.data) {
						if (data.data.waba_id) waIds.waba_id = data.data.waba_id;
						if (data.data.phone_number_id) waIds.phone_number_id = data.data.phone_number_id;
						if (data.data.business_id) waIds.business_id = data.data.business_id;
					}
				} catch (e) { /* ignorar mensajes no relacionados */ }
			});

			var confirmBox = document.getElementById('vcrm-wa-confirm');
			var connectBtn = document.getElementById('vcrm-wa-connect');
			var configInput = document.getElementById('vcrm-wa-config-id');
			var resultEl = document.getElementById('vcrm-wa-result');
			var statusEl = document.getElementById('vcrm-wa-status');

			function refreshButton() {
				connectBtn.disabled = !confirmBox.checked || !configInput.value;
			}
			confirmBox.addEventListener('change', refreshButton);
			configInput.addEventListener('input', refreshButton);
			refreshButton();

			document.getElementById('vcrm-wa-config-save').addEventListener('click', function () {
				var fd = new FormData();
				fd.append('action', 'vitacare_crm_wa_embedded_save_config');
				fd.append('nonce', <?php echo wp_json_encode( $nonce ); ?>);
				fd.append('config_id', configInput.value);
				fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: fd })
					.then(function () { refreshButton(); resultEl.textContent = '<?php echo esc_js( __( 'Configuration ID guardado.', 'vitacare-crm' ) ); ?>'; });
			});

			connectBtn.addEventListener('click', function () {
				if (typeof FB === 'undefined') { resultEl.textContent = 'FB SDK no cargó todavía, reintenta en unos segundos.'; return; }
				FB.login(function (response) {
					if (!response.authResponse || !response.authResponse.code) {
						resultEl.textContent = '<?php echo esc_js( __( 'Autorización cancelada o rechazada por Meta.', 'vitacare-crm' ) ); ?>';
						return;
					}
					var fd = new FormData();
					fd.append('action', 'vitacare_crm_wa_embedded_exchange');
					fd.append('nonce', <?php echo wp_json_encode( $nonce ); ?>);
					fd.append('code', response.authResponse.code);
					fd.append('waba_id', waIds.waba_id);
					fd.append('phone_number_id', waIds.phone_number_id);
					fd.append('business_id', waIds.business_id);
					fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: fd })
						.then(function (r) { return r.json(); })
						.then(function (json) {
							if (json.success) {
								statusEl.textContent = json.data.status;
								resultEl.textContent = '<?php echo esc_js( __( 'Listo. Revisa el estado arriba.', 'vitacare-crm' ) ); ?>';
							} else {
								resultEl.textContent = (json.data && json.data.message) || '<?php echo esc_js( __( 'Error al procesar la conexión.', 'vitacare-crm' ) ); ?>';
							}
						});
				}, {
					config_id: configInput.value,
					response_type: 'code',
					override_default_response_type: true,
					extras: {
						setup: {},
						featureType: 'whatsapp_business_app_onboarding',
						sessionInfoVersion: '3'
					}
				});
			});
		})();
		</script>
		<?php
	}
}

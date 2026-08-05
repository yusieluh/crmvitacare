<?php
defined( 'ABSPATH' ) || exit;

/**
 * Sección "Integraciones": vista por pestañas de Meta general / WhatsApp /
 * Messenger / Instagram / Gmail / Zoho Mail / Diagnóstico. No reemplaza las
 * páginas de edición ya existentes (Credenciales, Facebook, Gmail, Zoho) —
 * las enlaza y muestra su estado, para no duplicar lógica ya aprobada.
 * Reusa los mismos componentes de wp-admin (form-table, notice, card,
 * dashicons) que el resto del CRM; no introduce estilos ni diseño nuevos.
 */
final class Vitacare_Crm_Integrations_Page {

	private const TABS = array(
		'meta'        => 'Meta / Configuración general',
		'whatsapp'    => 'WhatsApp',
		'messenger'   => 'Messenger',
		'instagram'   => 'Instagram',
		'gmail'       => 'Gmail',
		'zoho'        => 'Zoho Mail',
		'diagnostico' => 'Diagnóstico',
	);

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'vitacare-crm-accounts',
			__( 'Integraciones', 'vitacare-crm' ),
			__( 'Integraciones', 'vitacare-crm' ),
			'manage_options',
			'vitacare-crm-integrations',
			array( __CLASS__, 'render' )
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para ver Integraciones.', 'vitacare-crm' ) );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'meta';
		if ( ! array_key_exists( $tab, self::TABS ) ) {
			$tab = 'meta';
		}

		if ( isset( $_POST['vitacare_crm_webhook_diag_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vitacare_crm_webhook_diag_nonce'] ) ), 'vitacare_crm_webhook_diag_clear' )
		) {
			Vitacare_Crm_Webhook_Diagnostics::clear();
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Registro de diagnóstico del webhook limpiado.', 'vitacare-crm' ) . '</p></div>';
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'CRM VITACARE — Integraciones', 'vitacare-crm' ); ?></h1>
			<p class="description"><?php echo esc_html__( 'Vista organizada por canal. Editar credenciales sigue haciéndose en Credenciales / Facebook / Gmail / Zoho Mail — aquí se ve el estado y, en WhatsApp, el asistente de conexión oficial.', 'vitacare-crm' ); ?></p>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( self::TABS as $key => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'vitacare-crm-integrations', 'tab' => $key ), admin_url( 'admin.php' ) ) ); ?>"
						class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<div style="margin-top:1.25em">
				<?php
				switch ( $tab ) {
					case 'whatsapp':
						self::render_whatsapp();
						break;
					case 'messenger':
						self::render_messenger();
						break;
					case 'instagram':
						self::render_instagram();
						break;
					case 'gmail':
						self::render_gmail();
						break;
					case 'zoho':
						self::render_zoho();
						break;
					case 'diagnostico':
						self::render_diagnostico();
						break;
					default:
						self::render_meta();
				}
				?>
			</div>
		</div>
		<?php
	}

	private static function status_row( string $label, bool $ok, string $ok_text = '', string $missing_text = '' ): void {
		$icon = $ok
			? '<span class="dashicons dashicons-yes-alt" style="color:#46b450"></span>'
			: '<span class="dashicons dashicons-warning" style="color:#dba617"></span>';
		$text = $ok ? ( $ok_text ?: __( 'Configurado', 'vitacare-crm' ) ) : ( $missing_text ?: __( 'No configurado', 'vitacare-crm' ) );
		echo '<p>' . $icon . ' <strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $text ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private static function render_meta(): void {
		$app_id  = Vitacare_Crm_Settings::get( 'app_id' );
		$secret  = Vitacare_Crm_Settings::get( 'app_secret' );
		$verify  = Vitacare_Crm_Settings::get( 'verify_token' );
		?>
		<h2><?php echo esc_html__( 'Meta / Configuración general', 'vitacare-crm' ); ?></h2>
		<?php
		self::status_row( __( 'App ID', 'vitacare-crm' ), $app_id !== '', $app_id !== '' ? $app_id : '' );
		self::status_row( __( 'App Secret', 'vitacare-crm' ), $secret !== '' );
		self::status_row( __( 'Verify Token (webhook)', 'vitacare-crm' ), $verify !== '' );
		?>
		<p><strong><?php echo esc_html__( 'Graph API version:', 'vitacare-crm' ); ?></strong> <code><?php echo esc_html( Vitacare_Crm_Settings::graph_version() ); ?></code></p>
		<p><strong><?php echo esc_html__( 'Webhook Meta:', 'vitacare-crm' ); ?></strong> <code><?php echo esc_html( Vitacare_Crm_Settings::webhook_url() ); ?></code></p>
		<p><strong><?php echo esc_html__( 'OAuth Redirect URI (Facebook Login):', 'vitacare-crm' ); ?></strong> <code><?php echo esc_html( Vitacare_Crm_Facebook_Oauth::redirect_uri() ); ?></code></p>
		<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=vitacare-crm-settings#meta' ) ); ?>"><?php echo esc_html__( 'Editar credenciales →', 'vitacare-crm' ); ?></a></p>
		<?php
	}

	private static function render_whatsapp(): void {
		$phone     = Vitacare_Crm_Settings::get( 'phone_number_id' );
		$waba      = Vitacare_Crm_Settings::get( 'waba_id' );
		$token     = Vitacare_Crm_Settings::get( 'access_token' );
		$biz       = Vitacare_Crm_Settings::get( 'wa_business_id' );
		$config_id = Vitacare_Crm_Settings::get( 'wa_embedded_config_id' );
		$intl      = Vitacare_Crm_Settings::get( 'wa_phone' );
		?>
		<h2><?php echo esc_html__( 'WhatsApp', 'vitacare-crm' ); ?></h2>
		<?php
		self::status_row( __( 'System User Access Token', 'vitacare-crm' ), $token !== '' );
		self::status_row( __( 'Business ID', 'vitacare-crm' ), $biz !== '', $biz );
		self::status_row( __( 'WABA ID', 'vitacare-crm' ), $waba !== '', $waba );
		self::status_row( __( 'Phone Number ID', 'vitacare-crm' ), $phone !== '', $phone );
		self::status_row( __( 'Configuration ID (Embedded Signup)', 'vitacare-crm' ), $config_id !== '', $config_id );
		self::status_row( __( 'Número internacional', 'vitacare-crm' ), $intl !== '', $intl );
		self::status_row( __( 'Canal activado (flag)', 'vitacare-crm' ), Vitacare_Crm_Settings::flag( 'whatsapp' ) );
		?>
		<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=vitacare-crm-settings#whatsapp' ) ); ?>"><?php echo esc_html__( 'Editar credenciales →', 'vitacare-crm' ); ?></a></p>
		<hr />
		<?php Vitacare_Crm_Whatsapp_Embedded_Signup::render_wizard(); ?>
		<p style="margin-top:1em"><a href="<?php echo esc_url( admin_url( 'admin.php?page=vitacare-crm-whatsapp' ) ); ?>"><?php echo esc_html__( '← Checklist manual de Coexistence (referencia)', 'vitacare-crm' ); ?></a></p>
		<?php
	}

	private static function render_messenger(): void {
		?>
		<h2><?php echo esc_html__( 'Messenger', 'vitacare-crm' ); ?></h2>
		<?php
		self::status_row( __( 'Page ID', 'vitacare-crm' ), Vitacare_Crm_Facebook_Oauth::get_page_id() !== '', Vitacare_Crm_Facebook_Oauth::get_page_id() );
		self::status_row( __( 'Nombre de la página', 'vitacare-crm' ), Vitacare_Crm_Facebook_Oauth::get_page_name() !== '', Vitacare_Crm_Facebook_Oauth::get_page_name() );
		self::status_row( __( 'Page Access Token', 'vitacare-crm' ), Vitacare_Crm_Facebook_Oauth::get_page_token() !== '' );
		self::status_row( __( 'Canal activado (flag)', 'vitacare-crm' ), Vitacare_Crm_Settings::flag( 'facebook' ) );
		self::status_row( __( 'Estado del webhook', 'vitacare-crm' ), Vitacare_Crm_Settings::flag( 'facebook' ) && Vitacare_Crm_Facebook_Oauth::is_connected() );
		?>
		<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=vitacare-crm-settings#messenger' ) ); ?>"><?php echo esc_html__( 'Editar credenciales →', 'vitacare-crm' ); ?></a>
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=vitacare-crm-facebook' ) ); ?>"><?php echo esc_html__( 'Conectar / gestionar Facebook', 'vitacare-crm' ); ?></a></p>
		<?php
	}

	private static function render_instagram(): void {
		?>
		<h2><?php echo esc_html__( 'Instagram', 'vitacare-crm' ); ?></h2>
		<?php
		self::status_row( __( 'Instagram Business Account ID', 'vitacare-crm' ), Vitacare_Crm_Facebook_Oauth::get_ig_id() !== '', Vitacare_Crm_Facebook_Oauth::get_ig_id() );
		self::status_row( __( 'Username', 'vitacare-crm' ), Vitacare_Crm_Facebook_Oauth::get_ig_username() !== '', '@' . Vitacare_Crm_Facebook_Oauth::get_ig_username() );
		self::status_row( __( 'Instagram Access Token', 'vitacare-crm' ), Vitacare_Crm_Facebook_Oauth::get_page_token() !== '', __( 'Configurado (mismo token que Messenger)', 'vitacare-crm' ) );
		self::status_row( __( 'Página vinculada', 'vitacare-crm' ), Vitacare_Crm_Facebook_Oauth::get_page_name() !== '', Vitacare_Crm_Facebook_Oauth::get_page_name() );
		self::status_row( __( 'Canal activado (flag)', 'vitacare-crm' ), Vitacare_Crm_Settings::flag( 'instagram' ) );
		self::status_row( __( 'Estado del webhook', 'vitacare-crm' ), Vitacare_Crm_Settings::flag( 'instagram' ) && Vitacare_Crm_Facebook_Oauth::get_ig_id() !== '' );
		?>
		<p class="description"><?php echo esc_html__( 'Instagram se conecta desde la misma pantalla de Facebook: la cuenta profesional debe estar vinculada a la Página en Meta Business Suite.', 'vitacare-crm' ); ?></p>
		<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=vitacare-crm-settings#instagram' ) ); ?>"><?php echo esc_html__( 'Editar credenciales →', 'vitacare-crm' ); ?></a>
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=vitacare-crm-facebook' ) ); ?>"><?php echo esc_html__( 'Ir a Facebook (incluye Instagram)', 'vitacare-crm' ); ?></a></p>
		<?php
	}

	private static function render_gmail(): void {
		?>
		<h2><?php echo esc_html__( 'Gmail', 'vitacare-crm' ); ?></h2>
		<?php self::status_row( __( 'Cuenta conectada', 'vitacare-crm' ), class_exists( 'Vitacare_Crm_Gmail' ) && Vitacare_Crm_Gmail::is_connected() ); ?>
		<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=vitacare-crm-gmail' ) ); ?>"><?php echo esc_html__( 'Conectar / gestionar Gmail', 'vitacare-crm' ); ?></a></p>
		<?php
	}

	private static function render_zoho(): void {
		?>
		<h2><?php echo esc_html__( 'Zoho Mail', 'vitacare-crm' ); ?></h2>
		<?php self::status_row( __( 'Cuenta conectada', 'vitacare-crm' ), class_exists( 'Vitacare_Crm_Zoho' ) && Vitacare_Crm_Zoho::is_connected() ); ?>
		<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=vitacare-crm-zoho' ) ); ?>"><?php echo esc_html__( 'Conectar / gestionar Zoho Mail', 'vitacare-crm' ); ?></a></p>
		<?php
	}

	private static function render_diagnostico(): void {
		?>
		<h2><?php echo esc_html__( 'Diagnóstico', 'vitacare-crm' ); ?></h2>
		<p class="description"><?php echo esc_html__( 'Solo estado — nunca muestra valores de secretos/tokens completos.', 'vitacare-crm' ); ?></p>

		<h3><?php echo esc_html__( 'Meta general', 'vitacare-crm' ); ?></h3>
		<?php
		self::status_row( __( 'App ID', 'vitacare-crm' ), Vitacare_Crm_Settings::get( 'app_id' ) !== '' );
		self::status_row( __( 'App Secret', 'vitacare-crm' ), Vitacare_Crm_Settings::get( 'app_secret' ) !== '' );
		self::status_row( __( 'Verify Token', 'vitacare-crm' ), Vitacare_Crm_Settings::get( 'verify_token' ) !== '' );
		self::status_row( __( 'Al menos un canal activado (requisito para que el webhook responda)', 'vitacare-crm' ), Vitacare_Crm_Settings::flag( 'whatsapp' ) || Vitacare_Crm_Settings::flag( 'facebook' ) || Vitacare_Crm_Settings::flag( 'instagram' ) );
		?>

		<h3><?php echo esc_html__( 'WhatsApp', 'vitacare-crm' ); ?></h3>
		<?php
		self::status_row( __( 'System User Access Token', 'vitacare-crm' ), Vitacare_Crm_Settings::get( 'access_token' ) !== '' );
		self::status_row( __( 'Business ID', 'vitacare-crm' ), Vitacare_Crm_Settings::get( 'wa_business_id' ) !== '' );
		self::status_row( __( 'WABA ID', 'vitacare-crm' ), Vitacare_Crm_Settings::get( 'waba_id' ) !== '' );
		self::status_row( __( 'Phone Number ID', 'vitacare-crm' ), Vitacare_Crm_Settings::get( 'phone_number_id' ) !== '' );
		self::status_row( __( 'Configuration ID (Embedded Signup)', 'vitacare-crm' ), Vitacare_Crm_Settings::get( 'wa_embedded_config_id' ) !== '' );
		self::status_row( __( 'Flag activado', 'vitacare-crm' ), Vitacare_Crm_Settings::flag( 'whatsapp' ) );
		self::status_row( __( 'Asistente Embedded Signup', 'vitacare-crm' ), true, Vitacare_Crm_Whatsapp_Embedded_Signup::status() );
		?>

		<h3><?php echo esc_html__( 'Messenger', 'vitacare-crm' ); ?></h3>
		<?php
		self::status_row( __( 'Page ID', 'vitacare-crm' ), Vitacare_Crm_Facebook_Oauth::get_page_id() !== '' );
		self::status_row( __( 'Page Access Token', 'vitacare-crm' ), Vitacare_Crm_Facebook_Oauth::get_page_token() !== '' );
		self::status_row( __( 'Webhook', 'vitacare-crm' ), Vitacare_Crm_Settings::flag( 'facebook' ) && Vitacare_Crm_Facebook_Oauth::is_connected() );
		?>

		<h3><?php echo esc_html__( 'Instagram', 'vitacare-crm' ); ?></h3>
		<?php
		self::status_row( __( 'Account ID', 'vitacare-crm' ), Vitacare_Crm_Facebook_Oauth::get_ig_id() !== '' );
		self::status_row( __( 'Access Token', 'vitacare-crm' ), Vitacare_Crm_Facebook_Oauth::get_page_token() !== '' );
		self::status_row( __( 'Webhook', 'vitacare-crm' ), Vitacare_Crm_Settings::flag( 'instagram' ) && Vitacare_Crm_Facebook_Oauth::get_ig_id() !== '' );
		self::status_row( __( 'Permisos', 'vitacare-crm' ), true, Vitacare_Crm_Facebook_Oauth::SCOPES );
		?>

		<h3><?php echo esc_html__( 'Correo', 'vitacare-crm' ); ?></h3>
		<?php
		self::status_row( 'Gmail', class_exists( 'Vitacare_Crm_Gmail' ) && Vitacare_Crm_Gmail::is_connected() );
		self::status_row( 'Zoho Mail', class_exists( 'Vitacare_Crm_Zoho' ) && Vitacare_Crm_Zoho::is_connected() );
		self::status_row( __( 'Flag Correo', 'vitacare-crm' ), Vitacare_Crm_Settings::flag( 'email' ) );
		?>

		<h3><?php echo esc_html__( 'Sistema', 'vitacare-crm' ); ?></h3>
		<?php
		self::status_row( __( 'DB version', 'vitacare-crm' ), true, (string) get_option( 'vitacare_crm_db_version', '0' ) );
		self::status_row( __( 'Plugin version', 'vitacare-crm' ), true, VITACARE_CRM_VERSION );
		self::status_row( __( 'Respaldo de integraciones más reciente', 'vitacare-crm' ), ! empty( Vitacare_Crm_Backup::list_backups() ), ( Vitacare_Crm_Backup::list_backups()[0]['created_at'] ?? '' ) );
		?>

		<h3><?php echo esc_html__( 'Últimos eventos del webhook (D-31, no sensible)', 'vitacare-crm' ); ?></h3>
		<p class="description">
			<?php
			echo esc_html__(
				'Últimos 20 POST recibidos en el webhook Meta. Nunca guarda texto de mensajes, tokens, App Secret, firma completa ni IDs completos (Page ID/PSID enmascarados).',
				'vitacare-crm'
			);
			?>
		</p>
		<?php self::render_webhook_diagnostics_table(); ?>
		<form method="post" style="margin-top:.5em">
			<?php wp_nonce_field( 'vitacare_crm_webhook_diag_clear', 'vitacare_crm_webhook_diag_nonce' ); ?>
			<?php submit_button( __( 'Limpiar registro', 'vitacare-crm' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}

	private static function render_webhook_diagnostics_table(): void {
		$events = class_exists( 'Vitacare_Crm_Webhook_Diagnostics' ) ? Vitacare_Crm_Webhook_Diagnostics::all() : array();
		if ( empty( $events ) ) {
			echo '<p><em>' . esc_html__( 'Todavía no se ha recibido ningún POST en el webhook desde que existe este registro.', 'vitacare-crm' ) . '</em></p>';
			return;
		}
		?>
		<div style="overflow-x:auto">
			<table class="widefat striped" style="min-width:1100px">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Fecha/hora', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'HTTP', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Object', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Firma', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Entries', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Eventos', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Tipo detectado', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Page ID', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Sender ID', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Contacto', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Conversación', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Mensaje', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Motivo si se ignoró', 'vitacare-crm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $events as $e ) : ?>
						<tr>
							<td><?php echo esc_html( (string) ( $e['at'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $e['http_status'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $e['object'] ?? '' ) ); ?></td>
							<td><?php echo ! empty( $e['has_signature'] ) ? ( ! empty( $e['signature_valid'] ) ? esc_html__( 'válida', 'vitacare-crm' ) : esc_html__( 'inválida', 'vitacare-crm' ) ) : esc_html__( 'ausente', 'vitacare-crm' ); ?></td>
							<td><?php echo esc_html( (string) ( $e['entry_count'] ?? 0 ) ); ?></td>
							<td><?php echo esc_html( (string) ( $e['messaging_count'] ?? 0 ) ); ?></td>
							<td><?php echo esc_html( (string) ( $e['event_type'] ?? '' ) ); ?></td>
							<td><code><?php echo esc_html( (string) ( $e['page_id_masked'] ?? '' ) ); ?></code></td>
							<td><code><?php echo esc_html( (string) ( $e['sender_id_masked'] ?? '' ) ); ?></code></td>
							<td><?php echo ! empty( $e['contact_created'] ) ? esc_html__( 'sí (nuevo)', 'vitacare-crm' ) : esc_html__( 'no', 'vitacare-crm' ); ?></td>
							<td><?php echo ! empty( $e['conversation_created'] ) ? esc_html__( 'sí (nueva)', 'vitacare-crm' ) : esc_html__( 'no', 'vitacare-crm' ); ?></td>
							<td><?php echo ! empty( $e['message_created'] ) ? esc_html__( 'sí', 'vitacare-crm' ) : esc_html__( 'no', 'vitacare-crm' ); ?></td>
							<td><?php echo esc_html( (string) ( $e['skip_reason'] ?? '—' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}

<?php
defined( 'ABSPATH' ) || exit;

/**
 * Settings y secretos Meta del CRM.
 * Resolución: constant wp-config → option (autoload false).
 * UI admin solo con manage_options — no modifica vitacare-core ni la raíz del sitio.
 */
final class Vitacare_Crm_Settings {

	public const OPTION_PREFIX = 'vitacare_crm_';

	/** @var array<string, array{const: string, option: string, secret: bool}> */
	private const SECRETS = array(
		'app_secret'      => array(
			'const'  => 'VITACARE_CRM_META_APP_SECRET',
			'option' => 'vitacare_crm_meta_app_secret',
			'secret' => true,
		),
		'access_token'    => array(
			'const'  => 'VITACARE_CRM_META_ACCESS_TOKEN',
			'option' => 'vitacare_crm_meta_access_token',
			'secret' => true,
		),
		'verify_token'    => array(
			'const'  => 'VITACARE_CRM_META_VERIFY_TOKEN',
			'option' => 'vitacare_crm_meta_verify_token',
			'secret' => true,
		),
		'phone_number_id' => array(
			'const'  => 'VITACARE_CRM_WA_PHONE_NUMBER_ID',
			'option' => 'vitacare_crm_wa_phone_number_id',
			'secret' => false,
		),
		'waba_id'         => array(
			'const'  => 'VITACARE_CRM_WA_WABA_ID',
			'option' => 'vitacare_crm_wa_waba_id',
			'secret' => false,
		),
		'app_id'          => array(
			'const'  => 'VITACARE_CRM_META_APP_ID',
			'option' => 'vitacare_crm_meta_app_id',
			'secret' => false,
		),
		'tiktok_client_key'    => array(
			'const'  => 'VITACARE_CRM_TIKTOK_CLIENT_KEY',
			'option' => 'vitacare_crm_tiktok_client_key',
			'secret' => false,
		),
		'tiktok_client_secret' => array(
			'const'  => 'VITACARE_CRM_TIKTOK_CLIENT_SECRET',
			'option' => 'vitacare_crm_tiktok_client_secret',
			'secret' => true,
		),
	);

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Valor efectivo de un secreto/campo (constant gana sobre option).
	 */
	public static function get( string $key ): string {
		if ( ! isset( self::SECRETS[ $key ] ) ) {
			return '';
		}
		$meta = self::SECRETS[ $key ];
		if ( defined( $meta['const'] ) ) {
			$c = (string) constant( $meta['const'] );
			if ( $c !== '' ) {
				return $c;
			}
		}
		$raw = (string) get_option( $meta['option'], '' );
		return self::maybe_decrypt( $raw );
	}

	/**
	 * True si el valor viene de constant (UI no debe sobrescribirlo por error).
	 */
	public static function is_from_constant( string $key ): bool {
		if ( ! isset( self::SECRETS[ $key ] ) ) {
			return false;
		}
		$const = self::SECRETS[ $key ]['const'];
		return defined( $const ) && (string) constant( $const ) !== '';
	}

	public static function flag( string $channel ): bool {
		$channel = sanitize_key( $channel );
		$option  = 'vitacare_crm_feature_' . $channel;
		return (bool) get_option( $option, false );
	}

	public static function graph_version(): string {
		if ( defined( 'VITACARE_CRM_GRAPH_VERSION' ) && (string) VITACARE_CRM_GRAPH_VERSION !== '' ) {
			return (string) VITACARE_CRM_GRAPH_VERSION;
		}
		$v = (string) get_option( 'vitacare_crm_graph_version', 'v21.0' );
		return $v !== '' ? $v : 'v21.0';
	}

	public static function outbound_soft_limit(): int {
		$n = (int) get_option( 'vitacare_crm_outbound_soft_limit', 1000 );
		return $n > 0 ? $n : 1000;
	}

	public static function debug_enabled(): bool {
		return (bool) get_option( 'vitacare_crm_debug_log', false );
	}

	/**
	 * WhatsApp listo para aceptar webhooks (flag + secret + verify token).
	 */
	public static function whatsapp_webhook_ready(): bool {
		return self::flag( 'whatsapp' )
			&& self::get( 'app_secret' ) !== ''
			&& self::get( 'verify_token' ) !== '';
	}

	/**
	 * URL del webhook Meta (pretty permalinks recomendados).
	 */
	public static function webhook_url(): string {
		return rest_url( 'vitacare-crm/v1/webhooks/meta' );
	}

	public static function register_menu(): void {
		// Menú principal apunta a Cuentas conectadas (C-1); credenciales en submenú.
		add_menu_page(
			__( 'CRM VITACARE', 'vitacare-crm' ),
			__( 'CRM VITACARE', 'vitacare-crm' ),
			'manage_options',
			'vitacare-crm-accounts',
			array( 'Vitacare_Crm_Accounts', 'render_accounts' ),
			'dashicons-format-chat',
			58
		);
		add_submenu_page(
			'vitacare-crm-accounts',
			__( 'Credenciales', 'vitacare-crm' ),
			__( 'Credenciales', 'vitacare-crm' ),
			'manage_options',
			'vitacare-crm-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings(): void {
		$plain = array(
			'vitacare_crm_meta_app_id',
			'vitacare_crm_wa_phone_number_id',
			'vitacare_crm_wa_waba_id',
			'vitacare_crm_graph_version',
			'vitacare_crm_outbound_soft_limit',
			'vitacare_crm_feature_whatsapp',
			'vitacare_crm_feature_facebook',
			'vitacare_crm_feature_instagram',
			'vitacare_crm_feature_email',
			'vitacare_crm_debug_log',
			'vitacare_crm_tiktok_client_key',
		);
		foreach ( $plain as $opt ) {
			register_setting(
				'vitacare_crm_settings',
				$opt,
				array(
					'type'              => 'string',
					'sanitize_callback' => array( __CLASS__, 'sanitize_option' ),
					'show_in_rest'      => false,
					'default'           => '',
				)
			);
		}

		// Secretos: sanitizan y no se vacían si el campo del form llega vacío (mantener valor).
		foreach ( array( 'vitacare_crm_meta_app_secret', 'vitacare_crm_meta_access_token', 'vitacare_crm_meta_verify_token', 'vitacare_crm_tiktok_client_secret' ) as $opt ) {
			register_setting(
				'vitacare_crm_settings',
				$opt,
				array(
					'type'              => 'string',
					'sanitize_callback' => array( __CLASS__, 'sanitize_secret_option' ),
					'show_in_rest'      => false,
					'default'           => '',
				)
			);
		}
	}

	/**
	 * @param mixed $value
	 */
	public static function sanitize_option( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? '1' : '';
		}
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	/**
	 * @param mixed $value
	 */
	public static function sanitize_secret_option( $value ): string {
		// WordPress pasa el valor del POST; si está vacío, conservar option actual.
		if ( ! is_scalar( $value ) || (string) $value === '' ) {
			// Detectar qué option se está sanitizando vía filtro actual no es trivial;
			// se maneja en handle_save() personalizado. Aquí no-op de vacío.
			return '';
		}
		$plain = sanitize_text_field( (string) $value );
		return self::maybe_encrypt( $plain );
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para ver los ajustes del CRM.', 'vitacare-crm' ) );
		}

		if ( isset( $_POST['vitacare_crm_settings_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vitacare_crm_settings_nonce'] ) ), 'vitacare_crm_save_settings' )
		) {
			self::handle_save();
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Ajustes del CRM guardados.', 'vitacare-crm' ) . '</p></div>';
		}

		self::handle_backup_actions();

		$webhook = self::webhook_url();
		$ready   = self::whatsapp_webhook_ready();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'CRM VITACARE — Credenciales', 'vitacare-crm' ); ?></h1>
			<p>
				<?php
				echo esc_html__(
					'Credenciales Meta y flags de canal. Preferible completar el asistente WhatsApp Coexistence y, más adelante, OAuth por canal. No modifica la raíz del sitio ni vitacare-core/tema.',
					'vitacare-crm'
				);
				?>
			</p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=vitacare-crm-accounts' ) ); ?>"><?php echo esc_html__( 'Cuentas conectadas', 'vitacare-crm' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=vitacare-crm-whatsapp' ) ); ?>"><?php echo esc_html__( 'Asistente WhatsApp', 'vitacare-crm' ); ?></a>
				<a class="button" href="<?php echo esc_url( home_url( '/' . VITACARE_CRM_PAGE_SLUG . '/' ) ); ?>"><?php echo esc_html__( 'Bandeja /crm', 'vitacare-crm' ); ?></a>
			</p>
			<p>
				<strong><?php echo esc_html__( 'Webhook Meta:', 'vitacare-crm' ); ?></strong>
				<code><?php echo esc_html( $webhook ); ?></code>
			</p>
			<p>
				<?php if ( $ready ) : ?>
					<span class="dashicons dashicons-yes-alt" style="color:green"></span>
					<?php echo esc_html__( 'WhatsApp: flag ON y secret/verify token configurados.', 'vitacare-crm' ); ?>
				<?php else : ?>
					<span class="dashicons dashicons-warning" style="color:#dba617"></span>
					<?php echo esc_html__( 'WhatsApp: incompleto (flag + App Secret + Verify Token). Webhooks → 403.', 'vitacare-crm' ); ?>
				<?php endif; ?>
			</p>

			<?php self::render_backup_section(); ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'vitacare_crm_save_settings', 'vitacare_crm_settings_nonce' ); ?>

				<h2><?php echo esc_html__( 'WhatsApp / Meta', 'vitacare-crm' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::field_text( 'app_id', __( 'App ID', 'vitacare-crm' ), false );
					self::field_secret( 'app_secret', __( 'App Secret', 'vitacare-crm' ) );
					self::field_secret( 'access_token', __( 'Access Token (permanente / system user)', 'vitacare-crm' ) );
					self::field_secret( 'verify_token', __( 'Verify Token (webhook)', 'vitacare-crm' ) );
					self::field_text( 'phone_number_id', __( 'Phone Number ID', 'vitacare-crm' ), false );
					self::field_text( 'waba_id', __( 'WABA ID', 'vitacare-crm' ), false );
					?>
					<tr>
						<th scope="row"><label for="vitacare_crm_graph_version"><?php echo esc_html__( 'Graph API version', 'vitacare-crm' ); ?></label></th>
						<td>
							<?php if ( defined( 'VITACARE_CRM_GRAPH_VERSION' ) && (string) VITACARE_CRM_GRAPH_VERSION !== '' ) : ?>
								<code><?php echo esc_html( (string) VITACARE_CRM_GRAPH_VERSION ); ?></code>
								<p class="description"><?php echo esc_html__( 'Definido en wp-config.php (VITACARE_CRM_GRAPH_VERSION).', 'vitacare-crm' ); ?></p>
							<?php else : ?>
								<input name="vitacare_crm_graph_version" id="vitacare_crm_graph_version" type="text" class="regular-text" value="<?php echo esc_attr( self::graph_version() ); ?>" placeholder="v21.0" />
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'TikTok (Login Kit — solo verificación de cuenta, sin mensajería)', 'vitacare-crm' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::field_text( 'tiktok_client_key', __( 'Client Key', 'vitacare-crm' ), false );
					self::field_secret( 'tiktok_client_secret', __( 'Client Secret', 'vitacare-crm' ) );
					?>
				</table>

				<h2><?php echo esc_html__( 'Canales (feature flags)', 'vitacare-crm' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php
					self::field_checkbox( 'vitacare_crm_feature_whatsapp', __( 'WhatsApp Cloud API', 'vitacare-crm' ), self::flag( 'whatsapp' ) );
					self::field_checkbox( 'vitacare_crm_feature_facebook', __( 'Facebook Messenger (futuro)', 'vitacare-crm' ), self::flag( 'facebook' ) );
					self::field_checkbox( 'vitacare_crm_feature_instagram', __( 'Instagram Direct (futuro)', 'vitacare-crm' ), self::flag( 'instagram' ) );
					self::field_checkbox( 'vitacare_crm_feature_email', __( 'Correo (futuro)', 'vitacare-crm' ), self::flag( 'email' ) );
					?>
				</table>

				<h2><?php echo esc_html__( 'Operación', 'vitacare-crm' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="vitacare_crm_outbound_soft_limit"><?php echo esc_html__( 'Cupo mensajes salientes / mes (por canal)', 'vitacare-crm' ); ?></label></th>
						<td>
							<input name="vitacare_crm_outbound_soft_limit" id="vitacare_crm_outbound_soft_limit" type="number" min="1" step="1" value="<?php echo esc_attr( (string) self::outbound_soft_limit() ); ?>" />
							<p class="description"><?php echo esc_html__( 'Default 1000. Se aplica de forma independiente a WhatsApp, Messenger e Instagram: al alcanzarlo, ese canal bloquea el envío (no solo alerta) hasta el mes siguiente o hasta subir el cupo.', 'vitacare-crm' ); ?></p>
						</td>
					</tr>
					<?php self::field_checkbox( 'vitacare_crm_debug_log', __( 'Debug log (riesgo PII — off en producción)', 'vitacare-crm' ), self::debug_enabled() ); ?>
				</table>

				<h2><?php echo esc_html__( 'Preferir wp-config en producción', 'vitacare-crm' ); ?></h2>
				<pre style="background:#f6f7f7;padding:12px;overflow:auto"><?php echo esc_html(
					"define( 'VITACARE_CRM_META_APP_SECRET', '...' );\n" .
					"define( 'VITACARE_CRM_META_ACCESS_TOKEN', '...' );\n" .
					"define( 'VITACARE_CRM_META_VERIFY_TOKEN', '...' );\n" .
					"define( 'VITACARE_CRM_WA_PHONE_NUMBER_ID', '...' );\n" .
					"define( 'VITACARE_CRM_GRAPH_VERSION', 'v21.0' );"
				); ?></pre>
				<p class="description">
					<?php echo esc_html__( 'Si la constant está definida, tiene prioridad sobre el valor guardado en la base de datos.', 'vitacare-crm' ); ?>
				</p>

				<?php submit_button( __( 'Guardar ajustes CRM', 'vitacare-crm' ) ); ?>
			</form>
		</div>
		<?php
	}

	private static function handle_backup_actions(): void {
		if ( ! isset( $_POST['vitacare_crm_backup_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vitacare_crm_backup_nonce'] ) ), 'vitacare_crm_backup_action' )
		) {
			return;
		}

		$op = isset( $_POST['vitacare_crm_backup_op'] ) ? sanitize_key( wp_unslash( $_POST['vitacare_crm_backup_op'] ) ) : '';

		if ( 'export' === $op ) {
			$result = Vitacare_Crm_Backup::export_now();
			if ( is_wp_error( $result ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
				return;
			}
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
				sprintf(
					/* translators: 1: file name, 2: byte count, 3: number of keys backed up */
					__( 'Respaldo generado: %1$s (%2$d bytes, %3$d claves). Guardado fuera del directorio público del plugin.', 'vitacare-crm' ),
					$result['file'],
					$result['bytes'],
					$result['keys']
				)
			) . '</p></div>';
			return;
		}

		if ( 'restore' === $op ) {
			if ( empty( $_POST['vitacare_crm_backup_confirm'] ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Debes marcar la casilla de confirmación para restaurar un respaldo.', 'vitacare-crm' ) . '</p></div>';
				return;
			}
			$file   = isset( $_POST['vitacare_crm_backup_file'] ) ? sanitize_text_field( wp_unslash( $_POST['vitacare_crm_backup_file'] ) ) : '';
			$result = Vitacare_Crm_Backup::restore( $file );
			if ( is_wp_error( $result ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
				return;
			}
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
				sprintf(
					/* translators: %d: number of keys restored */
					__( '%d claves restauradas desde el respaldo. Revisa Credenciales/Cuentas conectadas para confirmar el estado.', 'vitacare-crm' ),
					$result['keys']
				)
			) . '</p></div>';
		}
	}

	private static function render_backup_section(): void {
		$backups = Vitacare_Crm_Backup::list_backups();
		?>
		<h2><?php echo esc_html__( 'Respaldo de integraciones Meta', 'vitacare-crm' ); ?></h2>
		<p class="description">
			<?php echo esc_html__( 'Copia los valores actuales de App ID/Secret, tokens, WABA/Phone Number ID y credenciales de Facebook/Instagram a un archivo fuera del directorio público del plugin. No se muestra ni se transmite ningún secreto: la operación ocurre solo en el servidor.', 'vitacare-crm' ); ?>
		</p>
		<form method="post" action="" style="margin-bottom:1em">
			<?php wp_nonce_field( 'vitacare_crm_backup_action', 'vitacare_crm_backup_nonce' ); ?>
			<button type="submit" name="vitacare_crm_backup_op" value="export" class="button button-secondary">
				<?php echo esc_html__( 'Generar respaldo ahora', 'vitacare-crm' ); ?>
			</button>
		</form>
		<?php if ( empty( $backups ) ) : ?>
			<p><em><?php echo esc_html__( 'Todavía no hay respaldos generados.', 'vitacare-crm' ); ?></em></p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:640px">
				<thead><tr>
					<th><?php echo esc_html__( 'Archivo', 'vitacare-crm' ); ?></th>
					<th><?php echo esc_html__( 'Fecha (UTC)', 'vitacare-crm' ); ?></th>
					<th><?php echo esc_html__( 'Tamaño', 'vitacare-crm' ); ?></th>
					<th><?php echo esc_html__( 'Restaurar', 'vitacare-crm' ); ?></th>
				</tr></thead>
				<tbody>
					<?php foreach ( $backups as $i => $b ) : ?>
						<tr>
							<td><code><?php echo esc_html( $b['file'] ); ?></code></td>
							<td><?php echo esc_html( $b['created_at'] ); ?></td>
							<td><?php echo esc_html( size_format( $b['bytes'] ) ); ?></td>
							<td>
								<form method="post" action="" style="display:flex;gap:.5em;align-items:center;flex-wrap:wrap">
									<?php wp_nonce_field( 'vitacare_crm_backup_action', 'vitacare_crm_backup_nonce' ); ?>
									<input type="hidden" name="vitacare_crm_backup_file" value="<?php echo esc_attr( $b['file'] ); ?>" />
									<label style="font-weight:normal">
										<input type="checkbox" name="vitacare_crm_backup_confirm" value="1" required />
										<?php echo esc_html__( 'Confirmo sobrescribir los valores actuales', 'vitacare-crm' ); ?>
									</label>
									<button type="submit" name="vitacare_crm_backup_op" value="restore" class="button">
										<?php echo esc_html__( 'Restaurar este respaldo', 'vitacare-crm' ); ?>
									</button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	private static function handle_save(): void {
		// Flags / plain.
		$checks = array(
			'vitacare_crm_feature_whatsapp',
			'vitacare_crm_feature_facebook',
			'vitacare_crm_feature_instagram',
			'vitacare_crm_feature_email',
			'vitacare_crm_debug_log',
		);
		foreach ( $checks as $opt ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in render_page.
			update_option( $opt, isset( $_POST[ $opt ] ) ? '1' : '', false );
		}

		if ( ! defined( 'VITACARE_CRM_GRAPH_VERSION' ) || (string) VITACARE_CRM_GRAPH_VERSION === '' ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$gv = isset( $_POST['vitacare_crm_graph_version'] ) ? sanitize_text_field( wp_unslash( $_POST['vitacare_crm_graph_version'] ) ) : 'v21.0';
			if ( $gv === '' ) {
				$gv = 'v21.0';
			}
			update_option( 'vitacare_crm_graph_version', $gv, false );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$limit = isset( $_POST['vitacare_crm_outbound_soft_limit'] ) ? (int) $_POST['vitacare_crm_outbound_soft_limit'] : 1000;
		update_option( 'vitacare_crm_outbound_soft_limit', max( 1, $limit ), false );

		// Campos no secretos (solo si no hay constant).
		$map_plain = array(
			'app_id'            => 'vitacare_crm_meta_app_id',
			'phone_number_id'   => 'vitacare_crm_wa_phone_number_id',
			'waba_id'           => 'vitacare_crm_wa_waba_id',
			'tiktok_client_key' => 'vitacare_crm_tiktok_client_key',
		);
		foreach ( $map_plain as $key => $opt ) {
			if ( self::is_from_constant( $key ) ) {
				continue;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$val = isset( $_POST[ $opt ] ) ? sanitize_text_field( wp_unslash( $_POST[ $opt ] ) ) : '';
			update_option( $opt, $val, false );
		}

		// Secretos: solo actualizar si el campo trae valor nuevo y no hay constant.
		$map_secret = array(
			'app_secret'           => 'vitacare_crm_meta_app_secret',
			'access_token'         => 'vitacare_crm_meta_access_token',
			'verify_token'         => 'vitacare_crm_meta_verify_token',
			'tiktok_client_secret' => 'vitacare_crm_tiktok_client_secret',
		);
		foreach ( $map_secret as $key => $opt ) {
			if ( self::is_from_constant( $key ) ) {
				continue;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! isset( $_POST[ $opt ] ) ) {
				continue;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$val = sanitize_text_field( wp_unslash( $_POST[ $opt ] ) );
			if ( $val === '' ) {
				continue; // conservar valor existente
			}
			update_option( $opt, self::maybe_encrypt( $val ), false );
		}
	}

	private static function field_text( string $key, string $label, bool $secret ): void {
		$opt   = self::SECRETS[ $key ]['option'];
		$fromc = self::is_from_constant( $key );
		$value = $fromc ? '' : self::get( $key );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<?php if ( $fromc ) : ?>
					<code><?php echo esc_html__( 'Definido en wp-config.php', 'vitacare-crm' ); ?></code>
					<p class="description"><?php echo esc_html( self::SECRETS[ $key ]['const'] ); ?></p>
				<?php else : ?>
					<input
						name="<?php echo esc_attr( $opt ); ?>"
						id="<?php echo esc_attr( $opt ); ?>"
						type="<?php echo $secret ? 'password' : 'text'; ?>"
						class="regular-text"
						value="<?php echo esc_attr( $value ); ?>"
						autocomplete="off"
					/>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private static function field_secret( string $key, string $label ): void {
		$opt   = self::SECRETS[ $key ]['option'];
		$fromc = self::is_from_constant( $key );
		$has   = self::get( $key ) !== '';
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<?php if ( $fromc ) : ?>
					<code><?php echo esc_html__( 'Definido en wp-config.php', 'vitacare-crm' ); ?></code>
					<p class="description"><?php echo esc_html( self::SECRETS[ $key ]['const'] ); ?></p>
				<?php else : ?>
					<input
						name="<?php echo esc_attr( $opt ); ?>"
						id="<?php echo esc_attr( $opt ); ?>"
						type="password"
						class="regular-text"
						value=""
						placeholder="<?php echo $has ? esc_attr__( '•••••• (dejar vacío para no cambiar)', 'vitacare-crm' ) : ''; ?>"
						autocomplete="new-password"
					/>
					<?php if ( $has ) : ?>
						<p class="description"><?php echo esc_html__( 'Ya hay un valor guardado. Escribe uno nuevo solo para reemplazarlo.', 'vitacare-crm' ); ?></p>
					<?php endif; ?>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private static function field_checkbox( string $option, string $label, bool $checked ): void {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $option ); ?>" value="1" <?php checked( $checked ); ?> />
					<?php echo esc_html__( 'Activado', 'vitacare-crm' ); ?>
				</label>
			</td>
		</tr>
		<?php
	}

	/** Cifrado opcional de secretos (si VITACARE_CRM_ENCRYPTION_KEY en wp-config). */
	public static function store_secret( string $plain ): string {
		return self::maybe_encrypt( $plain );
	}

	/** Lectura de secreto almacenado (con o sin cifrado). */
	public static function read_secret( string $stored ): string {
		return self::maybe_decrypt( $stored );
	}

	private static function maybe_encrypt( string $plain ): string {
		if ( $plain === '' || ! defined( 'VITACARE_CRM_ENCRYPTION_KEY' ) || (string) VITACARE_CRM_ENCRYPTION_KEY === '' ) {
			return $plain;
		}
		$key = hash( 'sha256', (string) VITACARE_CRM_ENCRYPTION_KEY, true );
		$iv  = random_bytes( 16 );
		$enc = openssl_encrypt( $plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $enc ) {
			return $plain;
		}
		return 'enc:' . base64_encode( $iv . $enc ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	private static function maybe_decrypt( string $stored ): string {
		if ( $stored === '' || ! str_starts_with( $stored, 'enc:' ) ) {
			return $stored;
		}
		if ( ! defined( 'VITACARE_CRM_ENCRYPTION_KEY' ) || (string) VITACARE_CRM_ENCRYPTION_KEY === '' ) {
			return '';
		}
		$raw = base64_decode( substr( $stored, 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw || strlen( $raw ) < 17 ) {
			return '';
		}
		$iv  = substr( $raw, 0, 16 );
		$enc = substr( $raw, 16 );
		$key = hash( 'sha256', (string) VITACARE_CRM_ENCRYPTION_KEY, true );
		$dec = openssl_decrypt( $enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		return false === $dec ? '' : $dec;
	}
}

<?php
defined( 'ABSPATH' ) || exit;

/**
 * D-26 Fase 4: admin de campañas de correo. Formularios WP nativos con
 * nonce, mismo patrón que Leads/Enlaces -- crear campaña (queda en
 * "draft" con el segmento ya congelado), iniciar/pausar el despacho por
 * lotes (lo hace el cron, no esta pantalla).
 */
final class Vitacare_Crm_Email_Campaigns {

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 31 );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'vitacare-crm-accounts',
			__( 'Campañas de correo', 'vitacare-crm' ),
			__( 'Campañas de correo', 'vitacare-crm' ),
			'manage_options',
			'vitacare-crm-campaigns',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso.', 'vitacare-crm' ) );
		}

		global $wpdb;
		$table_exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Vitacare_Crm_Email_Campaigns_Repo::table_campaigns() ) );
		if ( ! $table_exists ) {
			?>
			<div class="wrap">
				<h1><?php echo esc_html__( 'CRM VITACARE — Campañas de correo', 'vitacare-crm' ); ?></h1>
				<div class="vcrm-callout-warn vcrm-callout">
					<p style="margin:0"><?php echo esc_html__( 'Las tablas de campañas todavía no existen en este sitio. Se crean solas en la próxima carga del plugin (upgrader DB v5) — recarga esta página en unos segundos.', 'vitacare-crm' ); ?></p>
				</div>
			</div>
			<?php
			return;
		}

		$notice = self::handle_post();

		$zoho_ok   = class_exists( 'Vitacare_Crm_Zoho' ) && Vitacare_Crm_Zoho::is_connected();
		$gmail_ok  = class_exists( 'Vitacare_Crm_Gmail' ) && Vitacare_Crm_Gmail::is_connected();
		$provider  = $zoho_ok ? __( 'Zoho Mail', 'vitacare-crm' ) : ( $gmail_ok ? __( 'Gmail', 'vitacare-crm' ) : '' );
		$campaigns = Vitacare_Crm_Email_Campaigns_Repo::list_campaigns();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'CRM VITACARE — Campañas de correo', 'vitacare-crm' ); ?></h1>
			<p><?php echo esc_html__( 'Solo se envía a leads con consentimiento explícito (opt-in). Cada correo incluye un enlace de baja obligatorio. El envío se despacha en lotes pequeños por cron, nunca todo de una vez.', 'vitacare-crm' ); ?></p>

			<?php if ( '' === $provider ) : ?>
				<div class="vcrm-callout-danger vcrm-callout">
					<p style="margin:0"><?php echo esc_html__( 'No hay ningún proveedor de correo conectado (Zoho Mail o Gmail). Conéctalo antes de crear una campaña.', 'vitacare-crm' ); ?></p>
				</div>
			<?php else : ?>
				<div class="vcrm-callout">
					<p style="margin:0"><?php echo esc_html( sprintf( /* translators: %s: provider name */ __( 'Los correos de campaña se envían por: %s', 'vitacare-crm' ), $provider ) ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( null !== $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['text'] ); ?></p></div>
			<?php endif; ?>

			<details style="margin-bottom:20px;background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:12px 16px">
				<summary style="cursor:pointer;font-weight:600"><?php echo esc_html__( '+ Nueva campaña', 'vitacare-crm' ); ?></summary>
				<form method="post" action="" style="margin-top:12px">
					<?php wp_nonce_field( 'vitacare_crm_campaign_create', 'vitacare_crm_campaign_nonce' ); ?>
					<input type="hidden" name="vcrm_action" value="create_campaign" />
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="camp_subject"><?php echo esc_html__( 'Asunto', 'vitacare-crm' ); ?></label></th>
							<td><input type="text" id="camp_subject" name="subject" class="regular-text" required /></td>
						</tr>
						<tr>
							<th scope="row"><label for="camp_body"><?php echo esc_html__( 'Cuerpo (texto plano)', 'vitacare-crm' ); ?></label></th>
							<td>
								<textarea id="camp_body" name="body" rows="8" class="large-text" required></textarea>
								<p class="description"><?php echo esc_html__( 'El pie de baja se agrega automáticamente al enviar, no lo escribas a mano.', 'vitacare-crm' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="camp_segment"><?php echo esc_html__( 'Segmento (etiqueta)', 'vitacare-crm' ); ?></label></th>
							<td>
								<input type="text" id="camp_segment" name="segment_tag" class="regular-text" placeholder="<?php echo esc_attr__( 'vacío = todos los opt-in', 'vitacare-crm' ); ?>" />
								<p class="description"><?php echo esc_html__( 'Solo se incluyen leads con consent_status = opt-in (y, si pones etiqueta, que además la tengan). Ver CRM VITACARE → Leads.', 'vitacare-crm' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="camp_cap"><?php echo esc_html__( 'Cupo diario', 'vitacare-crm' ); ?></label></th>
							<td>
								<input type="number" id="camp_cap" name="daily_cap" min="1" value="200" class="small-text" />
								<p class="description"><?php echo esc_html__( 'Máximo de correos de ESTA campaña por día. Default conservador: 200.', 'vitacare-crm' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Crear campaña (borrador)', 'vitacare-crm' ), 'primary', 'submit', false ); ?>
				</form>
			</details>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Asunto', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Segmento', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Estado', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Progreso', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Cupo/día', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Creada', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Acciones', 'vitacare-crm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $campaigns ) ) : ?>
						<tr><td colspan="7"><?php echo esc_html__( 'Sin campañas todavía.', 'vitacare-crm' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $campaigns as $c ) : ?>
							<tr>
								<td><?php echo esc_html( $c['subject'] ); ?></td>
								<td><?php echo esc_html( (string) ( $c['segment_tag'] ?? __( '(todos)', 'vitacare-crm' ) ) ); ?></td>
								<td><span class="vcrm-status <?php echo esc_attr( self::status_css( $c['status'] ) ); ?>"><?php echo esc_html( self::status_label( $c['status'] ) ); ?></span></td>
								<td><?php echo esc_html( sprintf( '%d / %d', $c['sent_count'], $c['total_recipients'] ) ); ?></td>
								<td><?php echo esc_html( (string) $c['daily_cap'] ); ?></td>
								<td><?php echo esc_html( (string) $c['created_at'] ); ?></td>
								<td style="white-space:nowrap">
									<?php if ( in_array( $c['status'], array( 'draft', 'paused' ), true ) ) : ?>
										<form method="post" action="" style="display:inline">
											<?php wp_nonce_field( 'vitacare_crm_campaign_start_' . $c['id'], 'vitacare_crm_campaign_start_nonce' ); ?>
											<input type="hidden" name="vcrm_action" value="start_campaign" />
											<input type="hidden" name="campaign_id" value="<?php echo esc_attr( (string) $c['id'] ); ?>" />
											<button type="submit" class="button button-primary button-small"><?php echo esc_html( 'draft' === $c['status'] ? __( 'Iniciar envío', 'vitacare-crm' ) : __( 'Reanudar', 'vitacare-crm' ) ); ?></button>
										</form>
									<?php endif; ?>
									<?php if ( 'sending' === $c['status'] ) : ?>
										<form method="post" action="" style="display:inline">
											<?php wp_nonce_field( 'vitacare_crm_campaign_pause_' . $c['id'], 'vitacare_crm_campaign_pause_nonce' ); ?>
											<input type="hidden" name="vcrm_action" value="pause_campaign" />
											<input type="hidden" name="campaign_id" value="<?php echo esc_attr( (string) $c['id'] ); ?>" />
											<button type="submit" class="button button-small"><?php echo esc_html__( 'Pausar', 'vitacare-crm' ); ?></button>
										</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * @return array{type: string, text: string}|null
	 */
	private static function handle_post(): ?array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$action = isset( $_POST['vcrm_action'] ) ? sanitize_key( wp_unslash( $_POST['vcrm_action'] ) ) : '';
		if ( '' === $action ) {
			return null;
		}

		if ( 'create_campaign' === $action ) {
			if ( ! isset( $_POST['vitacare_crm_campaign_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vitacare_crm_campaign_nonce'] ) ), 'vitacare_crm_campaign_create' ) ) {
				return array( 'type' => 'error', 'text' => __( 'Sesión expirada, intenta de nuevo.', 'vitacare-crm' ) );
			}
			$result = Vitacare_Crm_Email_Campaigns_Repo::create_campaign(
				array(
					'subject'     => isset( $_POST['subject'] ) ? wp_unslash( $_POST['subject'] ) : '',
					'body'        => isset( $_POST['body'] ) ? wp_unslash( $_POST['body'] ) : '',
					'segment_tag' => isset( $_POST['segment_tag'] ) ? wp_unslash( $_POST['segment_tag'] ) : '',
					'daily_cap'   => isset( $_POST['daily_cap'] ) ? (int) $_POST['daily_cap'] : 200,
				)
			);
			if ( is_wp_error( $result ) ) {
				return array( 'type' => 'error', 'text' => $result->get_error_message() );
			}
			return array(
				'type' => 'success',
				'text' => sprintf(
					/* translators: %d: recipient count */
					__( 'Campaña creada como borrador con %d destinatarios (opt-in). Dale "Iniciar envío" cuando quieras.', 'vitacare-crm' ),
					$result['total_recipients']
				),
			);
		}

		if ( 'start_campaign' === $action ) {
			$id = isset( $_POST['campaign_id'] ) ? (int) $_POST['campaign_id'] : 0;
			if ( ! isset( $_POST['vitacare_crm_campaign_start_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vitacare_crm_campaign_start_nonce'] ) ), 'vitacare_crm_campaign_start_' . $id ) ) {
				return array( 'type' => 'error', 'text' => __( 'Sesión expirada, intenta de nuevo.', 'vitacare-crm' ) );
			}
			$result = Vitacare_Crm_Email_Campaigns_Repo::start_campaign( $id );
			if ( is_wp_error( $result ) ) {
				return array( 'type' => 'error', 'text' => $result->get_error_message() );
			}
			return array( 'type' => 'success', 'text' => __( 'Campaña en envío. El cron la despacha en lotes cada pocos minutos.', 'vitacare-crm' ) );
		}

		if ( 'pause_campaign' === $action ) {
			$id = isset( $_POST['campaign_id'] ) ? (int) $_POST['campaign_id'] : 0;
			if ( ! isset( $_POST['vitacare_crm_campaign_pause_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vitacare_crm_campaign_pause_nonce'] ) ), 'vitacare_crm_campaign_pause_' . $id ) ) {
				return array( 'type' => 'error', 'text' => __( 'Sesión expirada, intenta de nuevo.', 'vitacare-crm' ) );
			}
			Vitacare_Crm_Email_Campaigns_Repo::pause_campaign( $id );
			return array( 'type' => 'success', 'text' => __( 'Campaña pausada.', 'vitacare-crm' ) );
		}

		return null;
	}

	private static function status_label( string $status ): string {
		switch ( $status ) {
			case 'draft':
				return __( 'Borrador', 'vitacare-crm' );
			case 'sending':
				return __( 'Enviando', 'vitacare-crm' );
			case 'paused':
				return __( 'Pausada', 'vitacare-crm' );
			case 'done':
				return __( 'Completada', 'vitacare-crm' );
			default:
				return $status;
		}
	}

	private static function status_css( string $status ): string {
		switch ( $status ) {
			case 'sending':
				return 'vcrm-status-warn';
			case 'done':
				return 'vcrm-status-ok';
			case 'paused':
				return 'vcrm-status-err';
			default:
				return 'vcrm-status-off';
		}
	}
}

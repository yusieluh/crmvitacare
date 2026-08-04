<?php
defined( 'ABSPATH' ) || exit;

/**
 * D-25 Fase 3: admin de enlaces con seguimiento propio. Genera un enlace
 * corto autohospedado (redirige vía REST público /go/{code}), con UTM
 * incrustado y contador de clics -- sin depender de Bitly ni terceros.
 */
final class Vitacare_Crm_Links {

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 30 );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'vitacare-crm-accounts',
			__( 'Enlaces', 'vitacare-crm' ),
			__( 'Enlaces', 'vitacare-crm' ),
			'manage_options',
			'vitacare-crm-links',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso.', 'vitacare-crm' ) );
		}

		global $wpdb;
		$table_exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Vitacare_Crm_Links_Repo::table() ) );
		if ( ! $table_exists ) {
			?>
			<div class="wrap">
				<h1><?php echo esc_html__( 'CRM VITACARE — Enlaces', 'vitacare-crm' ); ?></h1>
				<div class="vcrm-callout-warn vcrm-callout">
					<p style="margin:0"><?php echo esc_html__( 'La tabla de enlaces todavía no existe en este sitio. Se crea sola en la próxima carga del plugin (upgrader DB v4) — recarga esta página en unos segundos.', 'vitacare-crm' ); ?></p>
				</div>
			</div>
			<?php
			return;
		}

		$notice = self::handle_post();
		$links  = Vitacare_Crm_Links_Repo::list_all( 100 );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'CRM VITACARE — Enlaces', 'vitacare-crm' ); ?></h1>
			<p><?php echo esc_html__( 'Enlaces cortos propios con seguimiento de clics y UTM incrustado -- sin depender de Bitly ni ningún acortador de terceros. Útiles para medir campañas de WhatsApp/Messenger/correo dentro del dashboard de Reportes.', 'vitacare-crm' ); ?></p>

			<?php if ( null !== $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['text'] ); ?></p></div>
			<?php endif; ?>

			<details open style="margin-bottom:20px;background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:12px 16px">
				<summary style="cursor:pointer;font-weight:600"><?php echo esc_html__( '+ Generar enlace', 'vitacare-crm' ); ?></summary>
				<form method="post" action="" style="margin-top:12px">
					<?php wp_nonce_field( 'vitacare_crm_link_create', 'vitacare_crm_link_nonce' ); ?>
					<input type="hidden" name="vcrm_action" value="create_link" />
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="link_target"><?php echo esc_html__( 'URL de destino', 'vitacare-crm' ); ?></label></th>
							<td><input type="url" id="link_target" name="target_url" class="regular-text" placeholder="https://..." required /></td>
						</tr>
						<tr>
							<th scope="row"><label for="link_campaign"><?php echo esc_html__( 'Etiqueta de campaña', 'vitacare-crm' ); ?></label></th>
							<td>
								<input type="text" id="link_campaign" name="campaign_tag" class="regular-text" placeholder="<?php echo esc_attr__( 'ej. promo-agosto', 'vitacare-crm' ); ?>" />
								<p class="description"><?php echo esc_html__( 'Se agrega como utm_campaign a la URL de destino y agrupa los clics en Reportes.', 'vitacare-crm' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="link_lead"><?php echo esc_html__( 'Lead asociado (opcional)', 'vitacare-crm' ); ?></label></th>
							<td>
								<input type="number" min="1" id="link_lead" name="lead_id" class="small-text" />
								<p class="description"><?php echo esc_html__( 'ID numérico del lead, visible en CRM VITACARE → Leads.', 'vitacare-crm' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Generar enlace', 'vitacare-crm' ), 'primary', 'submit', false ); ?>
				</form>
			</details>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Enlace corto', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Destino', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Campaña', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Clics', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Último clic', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Creado', 'vitacare-crm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $links ) ) : ?>
						<tr><td colspan="6"><?php echo esc_html__( 'Sin enlaces todavía.', 'vitacare-crm' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $links as $link ) : ?>
							<tr>
								<td>
									<input type="text" readonly onclick="this.select()" value="<?php echo esc_attr( $link['short_url'] ); ?>" style="width:100%;font-family:Consolas,Monaco,monospace;font-size:12px" />
								</td>
								<td style="max-width:280px;overflow-wrap:break-word"><?php echo esc_html( $link['target_url'] ); ?></td>
								<td><?php echo esc_html( (string) ( $link['campaign_tag'] ?? '—' ) ); ?></td>
								<td><strong><?php echo esc_html( (string) $link['clicks_count'] ); ?></strong></td>
								<td><?php echo esc_html( (string) ( $link['last_click_at'] ?? '—' ) ); ?></td>
								<td><?php echo esc_html( (string) $link['created_at'] ); ?></td>
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
		if ( 'create_link' !== $action ) {
			return null;
		}
		if ( ! isset( $_POST['vitacare_crm_link_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vitacare_crm_link_nonce'] ) ), 'vitacare_crm_link_create' ) ) {
			return array(
				'type' => 'error',
				'text' => __( 'Sesión expirada, intenta de nuevo.', 'vitacare-crm' ),
			);
		}

		$target_url   = isset( $_POST['target_url'] ) ? wp_unslash( $_POST['target_url'] ) : '';
		$campaign_tag = isset( $_POST['campaign_tag'] ) ? wp_unslash( $_POST['campaign_tag'] ) : '';
		$lead_id      = isset( $_POST['lead_id'] ) ? (int) $_POST['lead_id'] : 0;

		$result = Vitacare_Crm_Links_Repo::create( $target_url, $campaign_tag, $lead_id );
		if ( is_wp_error( $result ) ) {
			return array(
				'type' => 'error',
				'text' => $result->get_error_message(),
			);
		}

		return array(
			'type' => 'success',
			'text' => sprintf(
				/* translators: %s: short URL */
				__( 'Enlace creado: %s', 'vitacare-crm' ),
				$result['short_url']
			),
		);
	}
}

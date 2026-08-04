<?php
defined( 'ABSPATH' ) || exit;

/**
 * D-23 Fase 2: admin de leads (crear manual, filtrar, marcar
 * opt-in/opt-out, importar CSV, convertir a conversación). Formularios
 * WordPress nativos con nonce, mismo patrón que Accounts/Reports/Settings
 * -- sin SPA propia, es una herramienta de escritorio para staff.
 */
final class Vitacare_Crm_Leads {

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 29 );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'vitacare-crm-accounts',
			__( 'Leads', 'vitacare-crm' ),
			__( 'Leads', 'vitacare-crm' ),
			'manage_options',
			'vitacare-crm-leads',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso.', 'vitacare-crm' ) );
		}

		global $wpdb;
		$table_exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Vitacare_Crm_Leads_Repo::table() ) );
		if ( ! $table_exists ) {
			?>
			<div class="wrap">
				<h1><?php echo esc_html__( 'CRM VITACARE — Leads', 'vitacare-crm' ); ?></h1>
				<div class="vcrm-callout-warn vcrm-callout">
					<p style="margin:0"><?php echo esc_html__( 'La tabla de leads todavía no existe en este sitio. Se crea sola en la próxima carga del plugin (upgrader DB v3) — recarga esta página en unos segundos.', 'vitacare-crm' ); ?></p>
				</div>
			</div>
			<?php
			return;
		}

		$notice = self::handle_post();

		$filters = array(
			'source'         => isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '',
			'consent_status' => isset( $_GET['consent_status'] ) ? sanitize_key( wp_unslash( $_GET['consent_status'] ) ) : '',
			'q'              => isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '',
			'page'           => isset( $_GET['lead_page'] ) ? max( 1, (int) $_GET['lead_page'] ) : 1,
			'per_page'       => 20,
		);
		$result = Vitacare_Crm_Leads_Repo::list( $filters );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'CRM VITACARE — Leads', 'vitacare-crm' ); ?></h1>
			<p><?php echo esc_html__( 'Contactos de marketing, separados de la bandeja de soporte. Escribir al CRM no es opt-in de campañas: eso solo se marca explícitamente aquí.', 'vitacare-crm' ); ?></p>

			<?php if ( null !== $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['text'] ); ?></p></div>
			<?php endif; ?>

			<details id="vcrm-lead-create" style="margin-bottom:20px;background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:12px 16px">
				<summary style="cursor:pointer;font-weight:600"><?php echo esc_html__( '+ Agregar lead manual', 'vitacare-crm' ); ?></summary>
				<form method="post" action="" style="margin-top:12px">
					<?php wp_nonce_field( 'vitacare_crm_lead_create', 'vitacare_crm_lead_nonce' ); ?>
					<input type="hidden" name="vcrm_action" value="create_lead" />
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="lead_name"><?php echo esc_html__( 'Nombre', 'vitacare-crm' ); ?></label></th>
							<td><input type="text" id="lead_name" name="name" class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="lead_phone"><?php echo esc_html__( 'Teléfono', 'vitacare-crm' ); ?></label></th>
							<td><input type="text" id="lead_phone" name="phone" class="regular-text" placeholder="+593..." /></td>
						</tr>
						<tr>
							<th scope="row"><label for="lead_email"><?php echo esc_html__( 'Correo', 'vitacare-crm' ); ?></label></th>
							<td><input type="email" id="lead_email" name="email" class="regular-text" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="lead_tags"><?php echo esc_html__( 'Etiquetas', 'vitacare-crm' ); ?></label></th>
							<td><input type="text" id="lead_tags" name="tags" class="regular-text" placeholder="<?php echo esc_attr__( 'separadas por coma', 'vitacare-crm' ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="lead_notes"><?php echo esc_html__( 'Notas', 'vitacare-crm' ); ?></label></th>
							<td><textarea id="lead_notes" name="notes" rows="2" class="large-text"></textarea></td>
						</tr>
					</table>
					<?php submit_button( __( 'Crear lead', 'vitacare-crm' ), 'primary', 'submit', false ); ?>
				</form>
			</details>

			<details id="vcrm-lead-import" style="margin-bottom:20px;background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:12px 16px">
				<summary style="cursor:pointer;font-weight:600"><?php echo esc_html__( '+ Importar CSV', 'vitacare-crm' ); ?></summary>
				<div style="margin-top:12px">
					<p class="description"><?php echo esc_html__( 'Columnas esperadas en la primera fila: name, phone, email, tags (etiquetas separadas por ";" dentro de la celda). Todos los leads importados quedan con consent_status = unknown.', 'vitacare-crm' ); ?></p>
					<form method="post" action="" enctype="multipart/form-data">
						<?php wp_nonce_field( 'vitacare_crm_lead_import', 'vitacare_crm_lead_import_nonce' ); ?>
						<input type="hidden" name="vcrm_action" value="import_leads" />
						<input type="file" name="csv_file" accept=".csv,text/csv" required />
						<?php submit_button( __( 'Importar', 'vitacare-crm' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
			</details>

			<form method="get" action="" style="margin-bottom:16px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
				<input type="hidden" name="page" value="vitacare-crm-leads" />
				<input type="text" name="q" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="<?php echo esc_attr__( 'Buscar nombre/teléfono/correo', 'vitacare-crm' ); ?>" />
				<select name="source">
					<option value=""><?php echo esc_html__( 'Todas las fuentes', 'vitacare-crm' ); ?></option>
					<?php foreach ( Vitacare_Crm_Leads_Repo::SOURCES as $src ) : ?>
						<option value="<?php echo esc_attr( $src ); ?>" <?php selected( $filters['source'], $src ); ?>><?php echo esc_html( ucfirst( $src ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="consent_status">
					<option value=""><?php echo esc_html__( 'Todo consentimiento', 'vitacare-crm' ); ?></option>
					<?php foreach ( Vitacare_Crm_Leads_Repo::CONSENT_STATES as $cs ) : ?>
						<option value="<?php echo esc_attr( $cs ); ?>" <?php selected( $filters['consent_status'], $cs ); ?>><?php echo esc_html( self::consent_label( $cs ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Filtrar', 'vitacare-crm' ), 'secondary', 'submit', false ); ?>
			</form>

			<p><strong><?php echo esc_html( sprintf( /* translators: %d: total */ __( '%d leads', 'vitacare-crm' ), $result['total'] ) ); ?></strong></p>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Nombre', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Contacto', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Fuente', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Etiquetas', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Consentimiento', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Conversación', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Acciones', 'vitacare-crm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $result['items'] ) ) : ?>
						<tr><td colspan="7"><?php echo esc_html__( 'Sin leads todavía.', 'vitacare-crm' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $result['items'] as $lead ) : ?>
							<tr>
								<td><?php echo esc_html( (string) ( $lead['name'] ?? '—' ) ); ?></td>
								<td>
									<?php echo esc_html( (string) ( $lead['phone'] ?? '' ) ); ?>
									<?php if ( ! empty( $lead['phone'] ) && ! empty( $lead['email'] ) ) : ?><br><?php endif; ?>
									<?php echo esc_html( (string) ( $lead['email'] ?? '' ) ); ?>
								</td>
								<td><?php echo esc_html( ucfirst( (string) $lead['source'] ) ); ?></td>
								<td><?php echo esc_html( implode( ', ', (array) $lead['tags'] ) ); ?></td>
								<td>
									<span class="vcrm-status <?php echo esc_attr( self::consent_css( (string) $lead['consent_status'] ) ); ?>">
										<?php echo esc_html( self::consent_label( (string) $lead['consent_status'] ) ); ?>
									</span>
								</td>
								<td>
									<?php if ( ! empty( $lead['conversation_id'] ) ) : ?>
										<a href="<?php echo esc_url( home_url( '/' . VITACARE_CRM_PAGE_SLUG . '/?c=' . (int) $lead['conversation_id'] ) ); ?>"><?php echo esc_html__( 'Ver hilo', 'vitacare-crm' ); ?></a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td style="white-space:nowrap">
									<?php if ( 'opted_in' !== $lead['consent_status'] ) : ?>
										<form method="post" action="" style="display:inline">
											<?php wp_nonce_field( 'vitacare_crm_lead_consent_' . $lead['id'], 'vitacare_crm_lead_consent_nonce' ); ?>
											<input type="hidden" name="vcrm_action" value="set_consent" />
											<input type="hidden" name="lead_id" value="<?php echo esc_attr( (string) $lead['id'] ); ?>" />
											<input type="hidden" name="status" value="opted_in" />
											<button type="submit" class="button button-small"><?php echo esc_html__( 'Opt-in', 'vitacare-crm' ); ?></button>
										</form>
									<?php endif; ?>
									<?php if ( 'opted_out' !== $lead['consent_status'] ) : ?>
										<form method="post" action="" style="display:inline">
											<?php wp_nonce_field( 'vitacare_crm_lead_consent_' . $lead['id'], 'vitacare_crm_lead_consent_nonce' ); ?>
											<input type="hidden" name="vcrm_action" value="set_consent" />
											<input type="hidden" name="lead_id" value="<?php echo esc_attr( (string) $lead['id'] ); ?>" />
											<input type="hidden" name="status" value="opted_out" />
											<button type="submit" class="button button-small"><?php echo esc_html__( 'Opt-out', 'vitacare-crm' ); ?></button>
										</form>
									<?php endif; ?>
									<?php if ( empty( $lead['conversation_id'] ) && ( ! empty( $lead['phone'] ) || ! empty( $lead['email'] ) ) ) : ?>
										<form method="post" action="" style="display:inline">
											<?php wp_nonce_field( 'vitacare_crm_lead_convert_' . $lead['id'], 'vitacare_crm_lead_convert_nonce' ); ?>
											<input type="hidden" name="vcrm_action" value="convert_lead" />
											<input type="hidden" name="lead_id" value="<?php echo esc_attr( (string) $lead['id'] ); ?>" />
											<button type="submit" class="button button-small"><?php echo esc_html__( 'Convertir a conversación', 'vitacare-crm' ); ?></button>
										</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php
			$total_pages = (int) ceil( $result['total'] / max( 1, $result['per_page'] ) );
			if ( $total_pages > 1 ) :
				?>
				<p style="margin-top:12px">
					<?php for ( $p = 1; $p <= $total_pages; $p++ ) : ?>
						<?php if ( $p === $result['page'] ) : ?>
							<strong style="margin-right:6px"><?php echo esc_html( (string) $p ); ?></strong>
						<?php else : ?>
							<a style="margin-right:6px" href="<?php echo esc_url( add_query_arg( array_merge( $_GET, array( 'lead_page' => $p ) ) ) ); ?>"><?php echo esc_html( (string) $p ); ?></a>
						<?php endif; ?>
					<?php endfor; ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @return array{type: string, text: string}|null
	 */
	private static function handle_post(): ?array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$action = isset( $_POST['vcrm_action'] ) ? sanitize_key( wp_unslash( $_POST['vcrm_action'] ) ) : '';
		if ( $action === '' ) {
			return null;
		}

		if ( 'create_lead' === $action ) {
			if ( ! isset( $_POST['vitacare_crm_lead_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vitacare_crm_lead_nonce'] ) ), 'vitacare_crm_lead_create' ) ) {
				return array(
					'type' => 'error',
					'text' => __( 'Sesión expirada, intenta de nuevo.', 'vitacare-crm' ),
				);
			}
			$created = Vitacare_Crm_Leads_Repo::create(
				array(
					'name'   => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '',
					'phone'  => isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '',
					'email'  => isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '',
					'tags'   => isset( $_POST['tags'] ) ? wp_unslash( $_POST['tags'] ) : '',
					'notes'  => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
					'source' => 'manual',
				)
			);
			if ( is_wp_error( $created ) ) {
				return array(
					'type' => 'error',
					'text' => $created->get_error_message(),
				);
			}
			return array(
				'type' => 'success',
				'text' => __( 'Lead creado.', 'vitacare-crm' ),
			);
		}

		if ( 'import_leads' === $action ) {
			if ( ! isset( $_POST['vitacare_crm_lead_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vitacare_crm_lead_import_nonce'] ) ), 'vitacare_crm_lead_import' ) ) {
				return array(
					'type' => 'error',
					'text' => __( 'Sesión expirada, intenta de nuevo.', 'vitacare-crm' ),
				);
			}
			if ( empty( $_FILES['csv_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv_file']['tmp_name'] ) ) {
				return array(
					'type' => 'error',
					'text' => __( 'No se recibió el archivo CSV.', 'vitacare-crm' ),
				);
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents
			$content = file_get_contents( $_FILES['csv_file']['tmp_name'] );
			if ( false === $content ) {
				return array(
					'type' => 'error',
					'text' => __( 'No se pudo leer el archivo.', 'vitacare-crm' ),
				);
			}
			$result = Vitacare_Crm_Leads_Repo::import_csv( $content );
			return array(
				'type' => $result['created'] > 0 ? 'success' : 'warning',
				'text' => sprintf(
					/* translators: 1: created, 2: skipped */
					__( 'Importación: %1$d creados, %2$d omitidos.', 'vitacare-crm' ),
					$result['created'],
					$result['skipped']
				),
			);
		}

		if ( 'set_consent' === $action ) {
			$lead_id = isset( $_POST['lead_id'] ) ? (int) $_POST['lead_id'] : 0;
			if ( ! isset( $_POST['vitacare_crm_lead_consent_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vitacare_crm_lead_consent_nonce'] ) ), 'vitacare_crm_lead_consent_' . $lead_id ) ) {
				return array(
					'type' => 'error',
					'text' => __( 'Sesión expirada, intenta de nuevo.', 'vitacare-crm' ),
				);
			}
			$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
			$result = Vitacare_Crm_Leads_Repo::set_consent( $lead_id, $status, 'staff:' . wp_get_current_user()->user_login );
			if ( is_wp_error( $result ) ) {
				return array(
					'type' => 'error',
					'text' => $result->get_error_message(),
				);
			}
			return array(
				'type' => 'success',
				'text' => __( 'Consentimiento actualizado.', 'vitacare-crm' ),
			);
		}

		if ( 'convert_lead' === $action ) {
			$lead_id = isset( $_POST['lead_id'] ) ? (int) $_POST['lead_id'] : 0;
			if ( ! isset( $_POST['vitacare_crm_lead_convert_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vitacare_crm_lead_convert_nonce'] ) ), 'vitacare_crm_lead_convert_' . $lead_id ) ) {
				return array(
					'type' => 'error',
					'text' => __( 'Sesión expirada, intenta de nuevo.', 'vitacare-crm' ),
				);
			}
			return self::convert_to_conversation( $lead_id );
		}

		return null;
	}

	/**
	 * Crea el "hueco" de conversación (WhatsApp si hay teléfono, si no
	 * correo vía Zoho) para que el staff pueda abrir el hilo desde /crm.
	 * No envía ningún mensaje -- WhatsApp igual exige que el contacto
	 * escriba primero dentro de la ventana de 24h, eso no cambia aquí.
	 *
	 * @return array{type: string, text: string}
	 */
	private static function convert_to_conversation( int $lead_id ): array {
		$lead = Vitacare_Crm_Leads_Repo::get( $lead_id );
		if ( null === $lead ) {
			return array(
				'type' => 'error',
				'text' => __( 'Lead no encontrado.', 'vitacare-crm' ),
			);
		}
		if ( ! empty( $lead['conversation_id'] ) ) {
			return array(
				'type' => 'warning',
				'text' => __( 'Este lead ya tiene una conversación asociada.', 'vitacare-crm' ),
			);
		}

		$conv_id = 0;
		if ( ! empty( $lead['phone'] ) ) {
			$digits  = preg_replace( '/\D+/', '', (string) $lead['phone'] );
			$conv_id = Vitacare_Crm_Conversations_Repo::upsert_whatsapp_contact(
				$digits,
				$lead['name'],
				'+' . ltrim( (string) $lead['phone'], '+' )
			);
		} elseif ( ! empty( $lead['email'] ) ) {
			$conv_id = Vitacare_Crm_Conversations_Repo::upsert_contact(
				'email',
				strtolower( (string) $lead['email'] ),
				$lead['name'],
				null,
				array( 'mail_provider' => 'zoho' )
			);
		}

		if ( $conv_id <= 0 ) {
			return array(
				'type' => 'error',
				'text' => __( 'No se pudo crear la conversación (falta teléfono o correo válido).', 'vitacare-crm' ),
			);
		}

		Vitacare_Crm_Leads_Repo::link_conversation( $lead_id, $conv_id );

		return array(
			'type' => 'success',
			'text' => __( 'Conversación creada. Ábrela desde la bandeja /crm para continuar.', 'vitacare-crm' ),
		);
	}

	private static function consent_label( string $status ): string {
		switch ( $status ) {
			case 'opted_in':
				return __( 'Opt-in', 'vitacare-crm' );
			case 'opted_out':
				return __( 'Opt-out', 'vitacare-crm' );
			default:
				return __( 'Desconocido', 'vitacare-crm' );
		}
	}

	private static function consent_css( string $status ): string {
		switch ( $status ) {
			case 'opted_in':
				return 'vcrm-status-ok';
			case 'opted_out':
				return 'vcrm-status-err';
			default:
				return 'vcrm-status-off';
		}
	}
}

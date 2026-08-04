<?php
defined( 'ABSPATH' ) || exit;

/**
 * Fase 1 (métricas/marketing gratuito, ver ESTADO_CRM.md): dashboard de
 * métricas locales. Todo sale de datos que el CRM ya tiene guardados
 * (wp_vitacare_crm_conversations/_messages) — sin llamadas externas nuevas,
 * salvo el widget de salud de WhatsApp (GET de solo lectura a Graph, ver
 * Vitacare_Crm_Channel_Whatsapp::health()).
 */
final class Vitacare_Crm_Reports {

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 28 );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'vitacare-crm-accounts',
			__( 'Reportes', 'vitacare-crm' ),
			__( 'Reportes', 'vitacare-crm' ),
			'manage_options',
			'vitacare-crm-reports',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso.', 'vitacare-crm' ) );
		}

		$days       = 30;
		$since      = gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) );
		$by_channel = self::messages_by_channel( $since );
		$status     = self::status_counts();
		$daily      = self::daily_volume( 14 );
		$first_resp = self::avg_first_response_minutes( $since );
		$agents     = self::agent_load();
		$wa_health  = class_exists( 'Vitacare_Crm_Channel_Whatsapp' ) ? Vitacare_Crm_Channel_Whatsapp::health() : array( 'available' => false, 'quality_rating' => null, 'messaging_limit' => null );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'CRM VITACARE — Reportes', 'vitacare-crm' ); ?></h1>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of days */
						__( 'Métricas locales calculadas sobre las conversaciones y mensajes ya guardados en el CRM (últimos %d días salvo donde se indique). No se envía nada a terceros para generarlas.', 'vitacare-crm' ),
						$days
					)
				);
				?>
			</p>

			<h2><?php echo esc_html__( 'Salud de WhatsApp', 'vitacare-crm' ); ?></h2>
			<div class="vcrm-admin-grid">
				<div class="vcrm-admin-card">
					<h2><?php echo esc_html__( 'Calidad del número', 'vitacare-crm' ); ?></h2>
					<?php if ( $wa_health['available'] ) : ?>
						<p>
							<span class="vcrm-status <?php echo 'GREEN' === $wa_health['quality_rating'] ? 'vcrm-status-ok' : 'vcrm-status-err'; ?>">
								<?php echo esc_html( (string) $wa_health['quality_rating'] ); ?>
							</span>
						</p>
						<p><?php echo esc_html__( 'Límite de mensajería:', 'vitacare-crm' ); ?> <strong><?php echo esc_html( (string) $wa_health['messaging_limit'] ); ?></strong></p>
						<?php if ( 'GREEN' !== $wa_health['quality_rating'] ) : ?>
							<p class="description"><?php echo esc_html__( 'Meta puede limitar o restringir el número si la calidad baja. Revisa quejas/bloqueos recientes de contactos.', 'vitacare-crm' ); ?></p>
						<?php endif; ?>
					<?php else : ?>
						<p><span class="vcrm-status vcrm-status-off"><?php echo esc_html__( 'Sin datos', 'vitacare-crm' ); ?></span></p>
						<p class="description"><?php echo esc_html__( 'Requiere Access Token y Phone Number ID configurados en Credenciales.', 'vitacare-crm' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( class_exists( 'Vitacare_Crm_Facebook_Oauth' ) ) : ?>
				<h2><?php echo esc_html__( 'Insights de Meta (gratis, sin gasto en anuncios)', 'vitacare-crm' ); ?></h2>
				<div class="vcrm-admin-grid">
					<div class="vcrm-admin-card">
						<h2><?php echo esc_html__( 'Página de Facebook (últimos 7 días)', 'vitacare-crm' ); ?></h2>
						<?php
						$fb_insights = Vitacare_Crm_Facebook_Oauth::is_connected() ? Vitacare_Crm_Facebook_Oauth::get_page_insights() : null;
						if ( null === $fb_insights ) :
							?>
							<p class="description"><?php echo esc_html__( 'Conecta Facebook en CRM VITACARE → Facebook.', 'vitacare-crm' ); ?></p>
						<?php elseif ( is_wp_error( $fb_insights ) ) : ?>
							<p class="description"><?php echo esc_html( $fb_insights->get_error_message() ); ?></p>
							<p class="description"><?php echo esc_html__( 'Si acabas de agregar este permiso, reconecta Facebook (botón «Reconectar / cambiar cuenta») para autorizarlo.', 'vitacare-crm' ); ?></p>
						<?php else : ?>
							<p><?php echo esc_html__( 'Impresiones:', 'vitacare-crm' ); ?> <strong><?php echo esc_html( number_format_i18n( (float) ( $fb_insights['page_impressions'] ?? 0 ) ) ); ?></strong></p>
							<p><?php echo esc_html__( 'Interacciones con publicaciones:', 'vitacare-crm' ); ?> <strong><?php echo esc_html( number_format_i18n( (float) ( $fb_insights['page_post_engagements'] ?? 0 ) ) ); ?></strong></p>
						<?php endif; ?>
					</div>
					<div class="vcrm-admin-card">
						<h2><?php echo esc_html__( 'Instagram (últimos 7 días)', 'vitacare-crm' ); ?></h2>
						<?php
						$ig_insights = Vitacare_Crm_Facebook_Oauth::is_instagram_connected() ? Vitacare_Crm_Facebook_Oauth::get_instagram_insights() : null;
						if ( null === $ig_insights ) :
							?>
							<p class="description"><?php echo esc_html__( 'Vincula una cuenta de Instagram profesional en CRM VITACARE → Facebook.', 'vitacare-crm' ); ?></p>
						<?php elseif ( is_wp_error( $ig_insights ) ) : ?>
							<p class="description"><?php echo esc_html( $ig_insights->get_error_message() ); ?></p>
							<p class="description"><?php echo esc_html__( 'Si acabas de agregar este permiso, reconecta Facebook para autorizarlo.', 'vitacare-crm' ); ?></p>
						<?php else : ?>
							<p><?php echo esc_html__( 'Alcance:', 'vitacare-crm' ); ?> <strong><?php echo esc_html( number_format_i18n( (float) ( $ig_insights['reach'] ?? 0 ) ) ); ?></strong></p>
							<p><?php echo esc_html__( 'Visitas al perfil:', 'vitacare-crm' ); ?> <strong><?php echo esc_html( number_format_i18n( (float) ( $ig_insights['profile_views'] ?? 0 ) ) ); ?></strong></p>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<h2><?php echo esc_html__( 'Mensajes por canal (últimos 30 días)', 'vitacare-crm' ); ?></h2>
			<table class="widefat striped" style="max-width:640px">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Canal', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Recibidos', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Enviados', 'vitacare-crm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $by_channel ) ) : ?>
						<tr><td colspan="3"><?php echo esc_html__( 'Sin mensajes en el período.', 'vitacare-crm' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $by_channel as $channel => $row ) : ?>
							<tr>
								<td><?php echo esc_html( ucfirst( $channel ) ); ?></td>
								<td><?php echo esc_html( (string) $row['inbound'] ); ?></td>
								<td><?php echo esc_html( (string) $row['outbound'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h2><?php echo esc_html__( 'Volumen diario (últimos 14 días)', 'vitacare-crm' ); ?></h2>
			<?php self::render_daily_bars( $daily ); ?>

			<h2><?php echo esc_html__( 'Conversaciones', 'vitacare-crm' ); ?></h2>
			<div class="vcrm-admin-grid">
				<div class="vcrm-admin-card">
					<h2><?php echo esc_html__( 'Por estado', 'vitacare-crm' ); ?></h2>
					<p>
						<?php echo esc_html__( 'Abiertas:', 'vitacare-crm' ); ?> <strong><?php echo esc_html( (string) ( $status['open'] ?? 0 ) ); ?></strong><br>
						<?php echo esc_html__( 'Pendientes:', 'vitacare-crm' ); ?> <strong><?php echo esc_html( (string) ( $status['pending'] ?? 0 ) ); ?></strong><br>
						<?php echo esc_html__( 'Cerradas:', 'vitacare-crm' ); ?> <strong><?php echo esc_html( (string) ( $status['closed'] ?? 0 ) ); ?></strong>
					</p>
				</div>
				<div class="vcrm-admin-card">
					<h2><?php echo esc_html__( 'Tiempo de primera respuesta', 'vitacare-crm' ); ?></h2>
					<?php if ( null === $first_resp ) : ?>
						<p class="description"><?php echo esc_html__( 'Sin datos suficientes en el período.', 'vitacare-crm' ); ?></p>
					<?php else : ?>
						<p><strong><?php echo esc_html( self::format_minutes( $first_resp ) ); ?></strong></p>
						<p class="description"><?php echo esc_html__( 'Promedio entre el primer mensaje del contacto y la primera respuesta del staff.', 'vitacare-crm' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( class_exists( 'Vitacare_Crm_Email_Campaigns_Repo' ) ) : ?>
				<?php $campaigns_list = Vitacare_Crm_Email_Campaigns_Repo::list_campaigns(); ?>
				<h2><?php echo esc_html__( 'Campañas de correo', 'vitacare-crm' ); ?></h2>
				<table class="widefat striped" style="max-width:640px">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Asunto', 'vitacare-crm' ); ?></th>
							<th><?php echo esc_html__( 'Estado', 'vitacare-crm' ); ?></th>
							<th><?php echo esc_html__( 'Enviados', 'vitacare-crm' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $campaigns_list ) ) : ?>
							<tr><td colspan="3"><?php echo esc_html__( 'Sin campañas todavía. Créalas en CRM VITACARE → Campañas de correo.', 'vitacare-crm' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( array_slice( $campaigns_list, 0, 10 ) as $camp ) : ?>
								<tr>
									<td><?php echo esc_html( $camp['subject'] ); ?></td>
									<td><?php echo esc_html( $camp['status'] ); ?></td>
									<td><?php echo esc_html( sprintf( '%d / %d', $camp['sent_count'], $camp['total_recipients'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( class_exists( 'Vitacare_Crm_Links_Repo' ) ) : ?>
				<?php $campaigns = Vitacare_Crm_Links_Repo::clicks_by_campaign(); ?>
				<h2><?php echo esc_html__( 'Clics por campaña (enlaces con seguimiento propio)', 'vitacare-crm' ); ?></h2>
				<table class="widefat striped" style="max-width:640px">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Campaña', 'vitacare-crm' ); ?></th>
							<th><?php echo esc_html__( 'Enlaces', 'vitacare-crm' ); ?></th>
							<th><?php echo esc_html__( 'Clics', 'vitacare-crm' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $campaigns ) ) : ?>
							<tr><td colspan="3"><?php echo esc_html__( 'Sin enlaces todavía. Genera uno en CRM VITACARE → Enlaces.', 'vitacare-crm' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $campaigns as $c ) : ?>
								<tr>
									<td><?php echo esc_html( $c['campaign_tag'] ); ?></td>
									<td><?php echo esc_html( (string) $c['links'] ); ?></td>
									<td><strong><?php echo esc_html( (string) $c['clicks'] ); ?></strong></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2><?php echo esc_html__( 'Carga por agente (conversaciones asignadas)', 'vitacare-crm' ); ?></h2>
			<table class="widefat striped" style="max-width:640px">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Agente', 'vitacare-crm' ); ?></th>
						<th><?php echo esc_html__( 'Conversaciones asignadas', 'vitacare-crm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $agents ) ) : ?>
						<tr><td colspan="2"><?php echo esc_html__( 'Sin conversaciones asignadas.', 'vitacare-crm' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $agents as $agent ) : ?>
							<tr>
								<td><?php echo esc_html( $agent['name'] ); ?></td>
								<td><?php echo esc_html( (string) $agent['count'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * @return array<string, array{inbound: int, outbound: int}>
	 */
	private static function messages_by_channel( string $since ): array {
		global $wpdb;
		$messages      = Vitacare_Crm_Db::messages_table();
		$conversations = Vitacare_Crm_Db::conversations_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.channel AS channel, m.direction AS direction, COUNT(*) AS cnt
				FROM {$messages} m
				INNER JOIN {$conversations} c ON c.id = m.conversation_id
				WHERE m.created_at >= %s
				GROUP BY c.channel, m.direction",
				$since
			)
		);

		$out = array();
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			$channel = (string) $row->channel;
			if ( ! isset( $out[ $channel ] ) ) {
				$out[ $channel ] = array(
					'inbound'  => 0,
					'outbound' => 0,
				);
			}
			if ( 'inbound' === $row->direction ) {
				$out[ $channel ]['inbound'] = (int) $row->cnt;
			} elseif ( 'outbound' === $row->direction ) {
				$out[ $channel ]['outbound'] = (int) $row->cnt;
			}
		}
		return $out;
	}

	/**
	 * @return array<string, int>
	 */
	private static function status_counts(): array {
		global $wpdb;
		$table = Vitacare_Crm_Db::conversations_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS cnt FROM {$table} GROUP BY status" );
		$out  = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[ (string) $row->status ] = (int) $row->cnt;
			}
		}
		return $out;
	}

	/**
	 * @return array<string, int> fecha (Y-m-d) => cantidad de mensajes
	 */
	private static function daily_volume( int $days ): array {
		global $wpdb;
		$messages = Vitacare_Crm_Db::messages_table();
		$since    = gmdate( 'Y-m-d 00:00:00', strtotime( '-' . ( $days - 1 ) . ' days' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS d, COUNT(*) AS cnt
				FROM {$messages}
				WHERE created_at >= %s
				GROUP BY DATE(created_at)
				ORDER BY d ASC",
				$since
			)
		);

		$out = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$date         = gmdate( 'Y-m-d', strtotime( '-' . $i . ' days' ) );
			$out[ $date ] = 0;
		}
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$d = (string) $row->d;
				if ( isset( $out[ $d ] ) ) {
					$out[ $d ] = (int) $row->cnt;
				}
			}
		}
		return $out;
	}

	/**
	 * Promedio (minutos) entre el primer mensaje inbound y el primer
	 * outbound de cada conversación en el período. Aproximación simple
	 * (no exige que el outbound sea estrictamente posterior al inbound
	 * dentro del mismo hilo de re-apertura) — suficiente para un
	 * indicador operativo, no para SLA contractual.
	 */
	private static function avg_first_response_minutes( string $since ): ?float {
		global $wpdb;
		$messages = Vitacare_Crm_Db::messages_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT conversation_id,
					MIN(CASE WHEN direction = 'inbound' THEN created_at END) AS first_in,
					MIN(CASE WHEN direction = 'outbound' THEN created_at END) AS first_out
				FROM {$messages}
				WHERE created_at >= %s
				GROUP BY conversation_id",
				$since
			)
		);

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return null;
		}

		$total = 0.0;
		$n     = 0;
		foreach ( $rows as $row ) {
			if ( empty( $row->first_in ) || empty( $row->first_out ) ) {
				continue;
			}
			$in  = strtotime( (string) $row->first_in );
			$out = strtotime( (string) $row->first_out );
			if ( false === $in || false === $out || $out <= $in ) {
				continue;
			}
			$total += ( $out - $in ) / 60;
			++$n;
		}

		return $n > 0 ? $total / $n : null;
	}

	/**
	 * @return array<int, array{name: string, count: int}>
	 */
	private static function agent_load(): array {
		global $wpdb;
		$table = Vitacare_Crm_Db::conversations_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT assigned_to, COUNT(*) AS cnt FROM {$table} WHERE assigned_to IS NOT NULL AND assigned_to > 0 GROUP BY assigned_to ORDER BY cnt DESC"
		);
		$out = array();
		if ( ! is_array( $rows ) ) {
			return $out;
		}
		foreach ( $rows as $row ) {
			$user = get_userdata( (int) $row->assigned_to );
			$out[] = array(
				'name'  => $user ? $user->display_name : sprintf( '#%d', (int) $row->assigned_to ),
				'count' => (int) $row->cnt,
			);
		}
		return $out;
	}

	private static function format_minutes( float $minutes ): string {
		if ( $minutes < 60 ) {
			return sprintf(
				/* translators: %s: minutes */
				__( '%s min', 'vitacare-crm' ),
				number_format_i18n( $minutes, 1 )
			);
		}
		$hours = $minutes / 60;
		return sprintf(
			/* translators: %s: hours */
			__( '%s h', 'vitacare-crm' ),
			number_format_i18n( $hours, 1 )
		);
	}

	/**
	 * Gráfico de barras simple con divs (sin librerías externas).
	 *
	 * @param array<string, int> $daily
	 */
	private static function render_daily_bars( array $daily ): void {
		if ( empty( $daily ) ) {
			echo '<p class="description">' . esc_html__( 'Sin datos.', 'vitacare-crm' ) . '</p>';
			return;
		}
		$max = max( array_values( $daily ) );
		$max = $max > 0 ? $max : 1;
		?>
		<div style="display:flex;align-items:flex-end;gap:6px;height:140px;background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:16px 12px 8px;max-width:720px">
			<?php foreach ( $daily as $date => $count ) : ?>
				<?php $pct = max( 2, (int) round( ( $count / $max ) * 100 ) ); ?>
				<div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%">
					<div style="font-size:11px;color:#50575e;margin-bottom:2px"><?php echo esc_html( (string) $count ); ?></div>
					<div style="width:100%;max-width:24px;height:<?php echo esc_attr( (string) $pct ); ?>%;background:#2271b1;border-radius:3px 3px 0 0" title="<?php echo esc_attr( $date . ': ' . $count ); ?>"></div>
					<div style="font-size:10px;color:#646970;margin-top:4px;writing-mode:vertical-rl;text-orientation:mixed"><?php echo esc_html( gmdate( 'd/m', strtotime( $date ) ) ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}

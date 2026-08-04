<?php
defined( 'ABSPATH' ) || exit;

/**
 * D-26 Fase 4: campañas de correo (Zoho principal / Gmail secundario) con
 * opt-in obligatorio y límite diario duro. Reglas de seguridad no
 * negociables:
 * - El segmento SOLO puede incluir leads con `consent_status = 'opted_in'`
 *   -- chequeo a nivel de query al crear la campaña Y de nuevo al
 *   despachar cada lote (si alguien se dio de baja entre medio, se salta).
 * - Cada correo lleva pie de baja obligatorio (enlace público sin login).
 * - Nunca se envía todo de una vez: un cron (`vitacare_crm_five_minutes`,
 *   el mismo intervalo que ya usan Gmail/Zoho para sync) despacha lotes
 *   pequeños respetando el `daily_cap` de cada campaña.
 */
final class Vitacare_Crm_Email_Campaigns_Repo {

	public const CRON_HOOK  = 'vitacare_crm_campaign_dispatch';
	public const STATUSES   = array( 'draft', 'sending', 'paused', 'done' );
	private const BATCH_SIZE = 10;

	public static function init(): void {
		add_action( self::CRON_HOOK, array( __CLASS__, 'dispatch_batch' ) );
		add_action( 'init', array( __CLASS__, 'ensure_cron' ) );
	}

	public static function ensure_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			// Mismo intervalo 'vitacare_crm_five_minutes' que ya registra class-vitacare-crm-gmail.php.
			wp_schedule_event( time() + 90, 'vitacare_crm_five_minutes', self::CRON_HOOK );
		}
	}

	public static function table_campaigns(): string {
		global $wpdb;
		return $wpdb->prefix . 'vitacare_crm_email_campaigns';
	}

	public static function table_recipients(): string {
		global $wpdb;
		return $wpdb->prefix . 'vitacare_crm_campaign_recipients';
	}

	private static function ready(): bool {
		global $wpdb;
		$table = self::table_campaigns();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	/**
	 * Crea la campaña y toma la foto del segmento (solo leads opted_in con
	 * correo) como cola de destinatarios pendientes. No envía nada todavía
	 * -- eso requiere start_campaign().
	 *
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>|WP_Error
	 */
	public static function create_campaign( array $data ) {
		global $wpdb;
		if ( ! self::ready() ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_db_error', __( 'Las tablas de campañas todavía no existen (recarga en unos segundos).', 'vitacare-crm' ), 500 );
		}

		$subject = sanitize_text_field( substr( (string) ( $data['subject'] ?? '' ), 0, 255 ) );
		$body    = sanitize_textarea_field( (string) ( $data['body'] ?? '' ) );
		if ( '' === $subject || '' === $body ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_invalid_param', __( 'Asunto y cuerpo son obligatorios.', 'vitacare-crm' ), 400 );
		}

		$segment_tag = sanitize_text_field( substr( (string) ( $data['segment_tag'] ?? '' ), 0, 100 ) );
		$daily_cap   = max( 1, (int) ( $data['daily_cap'] ?? 200 ) );

		$leads = Vitacare_Crm_Leads_Repo::all_opted_in_with_email( $segment_tag );
		if ( empty( $leads ) ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_invalid_param',
				__( 'No hay leads con opt-in que coincidan con ese segmento (o correo). Revisa CRM VITACARE → Leads.', 'vitacare-crm' ),
				400
			);
		}

		$now        = current_time( 'mysql' );
		$campaigns  = self::table_campaigns();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert(
			$campaigns,
			array(
				'subject'          => $subject,
				'body'             => $body,
				'segment_tag'      => $segment_tag !== '' ? $segment_tag : null,
				'status'           => 'draft',
				'daily_cap'        => $daily_cap,
				'total_recipients' => count( $leads ),
				'sent_count'       => 0,
				'created_by'       => get_current_user_id(),
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
		);
		if ( false === $ok ) {
			Vitacare_Crm_Logger::error( 'campaign_insert_failed', array( 'db' => $wpdb->last_error ) );
			return Vitacare_Crm_Db::error( 'vitacare_crm_db_error', __( 'No se pudo crear la campaña.', 'vitacare-crm' ), 500 );
		}
		$campaign_id = (int) $wpdb->insert_id;

		$recipients = self::table_recipients();
		foreach ( $leads as $lead ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$recipients,
				array(
					'campaign_id' => $campaign_id,
					'lead_id'     => $lead['id'],
					'email'       => $lead['email'],
					'status'      => 'pending',
					'created_at'  => $now,
				),
				array( '%d', '%d', '%s', '%s', '%s' )
			);
		}

		return self::get_campaign( $campaign_id );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	public static function start_campaign( int $id ) {
		$campaign = self::get_campaign( $id );
		if ( null === $campaign ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_not_found', __( 'Campaña no encontrada.', 'vitacare-crm' ), 404 );
		}
		if ( ! in_array( $campaign['status'], array( 'draft', 'paused' ), true ) ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_invalid_param', __( 'Solo se puede iniciar un borrador o reanudar una campaña pausada.', 'vitacare-crm' ), 400 );
		}
		self::set_status( $id, 'sending' );
		return self::get_campaign( $id );
	}

	public static function pause_campaign( int $id ): void {
		self::set_status( $id, 'paused' );
	}

	private static function set_status( int $id, string $status ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			self::table_campaigns(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_campaigns(): array {
		global $wpdb;
		if ( ! self::ready() ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table_campaigns() . ' ORDER BY created_at DESC' );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[] = self::format( $row );
		}
		return $out;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get_campaign( int $id ): ?array {
		global $wpdb;
		if ( ! self::ready() ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table_campaigns() . ' WHERE id = %d', $id ) );
		return $row ? self::format( $row ) : null;
	}

	/**
	 * @return array<string, int>
	 */
	public static function recipients_summary( int $campaign_id ): array {
		global $wpdb;
		if ( ! self::ready() ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT status, COUNT(*) AS cnt FROM ' . self::table_recipients() . ' WHERE campaign_id = %d GROUP BY status',
				$campaign_id
			)
		);
		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row->status ] = (int) $row->cnt;
		}
		return $out;
	}

	/**
	 * Worker de cron: procesa un lote pequeño de cada campaña 'sending',
	 * respetando el cupo diario propio de cada una.
	 */
	public static function dispatch_batch(): void {
		global $wpdb;
		if ( ! self::ready() ) {
			return;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM " . self::table_campaigns() . " WHERE status = 'sending' ORDER BY id ASC" );
		foreach ( (array) $rows as $row ) {
			try {
				self::dispatch_campaign_batch( self::format( $row ) );
			} catch ( Throwable $e ) {
				Vitacare_Crm_Logger::error( 'campaign_dispatch_failed', array( 'campaign_id' => $row->id, 'err' => $e->getMessage() ) );
			}
		}
	}

	/**
	 * @param array<string, mixed> $campaign
	 */
	private static function dispatch_campaign_batch( array $campaign ): void {
		global $wpdb;
		$id = (int) $campaign['id'];

		$today_key       = 'vitacare_crm_campaign_' . $id . '_sent_' . gmdate( 'Y_m_d' );
		$sent_today      = (int) get_option( $today_key, 0 );
		$remaining_today = max( 0, (int) $campaign['daily_cap'] - $sent_today );
		if ( $remaining_today <= 0 ) {
			return; // Cupo de hoy agotado para esta campaña; sigue mañana.
		}
		$batch_size = min( self::BATCH_SIZE, $remaining_today );

		$recipients_table = self::table_recipients();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$pending = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$recipients_table} WHERE campaign_id = %d AND status = 'pending' ORDER BY id ASC LIMIT %d",
				$id,
				$batch_size
			)
		);

		if ( empty( $pending ) ) {
			self::set_status( $id, 'done' );
			return;
		}

		$sent_this_run = 0;
		foreach ( $pending as $r ) {
			// Re-verifica el opt-in AHORA, no solo al crear la campaña: si el
			// lead se dio de baja entre medio, se salta sin enviar nada.
			$lead = Vitacare_Crm_Leads_Repo::get( (int) $r->lead_id );
			if ( null === $lead || 'opted_in' !== $lead['consent_status'] ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update( $recipients_table, array( 'status' => 'skipped_opted_out' ), array( 'id' => (int) $r->id ), array( '%s' ), array( '%d' ) );
				continue;
			}

			$body_with_footer = self::append_unsubscribe_footer( (string) $campaign['body'], (int) $r->lead_id );
			$result           = self::send_via_provider( (string) $r->email, (string) $campaign['subject'], $body_with_footer );

			if ( is_wp_error( $result ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$recipients_table,
					array(
						'status' => 'failed',
						'error'  => substr( $result->get_error_message(), 0, 255 ),
					),
					array( 'id' => (int) $r->id ),
					array( '%s', '%s' ),
					array( '%d' )
				);
				Vitacare_Crm_Logger::error( 'campaign_send_failed', array( 'campaign_id' => $id, 'lead_id' => $r->lead_id, 'err' => $result->get_error_message() ) );
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$recipients_table,
				array(
					'status'  => 'sent',
					'sent_at' => current_time( 'mysql' ),
				),
				array( 'id' => (int) $r->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			++$sent_this_run;
		}

		if ( $sent_this_run > 0 ) {
			update_option( $today_key, $sent_today + $sent_this_run, false );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE ' . self::table_campaigns() . ' SET sent_count = sent_count + %d, updated_at = %s WHERE id = %d',
					$sent_this_run,
					current_time( 'mysql' ),
					$id
				)
			);
		}
	}

	private static function append_unsubscribe_footer( string $body, int $lead_id ): string {
		$token = Vitacare_Crm_Leads_Repo::unsubscribe_token( $lead_id );
		$url   = rest_url( 'vitacare-crm/v1/unsubscribe/' . $token );
		return rtrim( $body ) . "\n\n---\n" . sprintf(
			/* translators: %s: unsubscribe URL */
			__( 'Si no quieres volver a recibir estos correos, date de baja aquí: %s', 'vitacare-crm' ),
			$url
		);
	}

	/**
	 * @return true|WP_Error
	 */
	private static function send_via_provider( string $to, string $subject, string $body ) {
		if ( class_exists( 'Vitacare_Crm_Zoho' ) && Vitacare_Crm_Zoho::is_connected() ) {
			return Vitacare_Crm_Zoho::send_campaign_email( $to, $subject, $body );
		}
		if ( class_exists( 'Vitacare_Crm_Gmail' ) && Vitacare_Crm_Gmail::is_connected() ) {
			return Vitacare_Crm_Gmail::send_campaign_email( $to, $subject, $body );
		}
		return new WP_Error( 'vitacare_crm_forbidden', __( 'Ningún proveedor de correo conectado (Zoho Mail o Gmail).', 'vitacare-crm' ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function format( object $row ): array {
		return array(
			'id'                => (int) $row->id,
			'subject'           => (string) $row->subject,
			'body'              => (string) $row->body,
			'segment_tag'       => $row->segment_tag,
			'status'            => (string) $row->status,
			'daily_cap'         => (int) $row->daily_cap,
			'total_recipients'  => (int) $row->total_recipients,
			'sent_count'        => (int) $row->sent_count,
			'created_at'        => Vitacare_Crm_Db::format_datetime( $row->created_at ?? null ),
			'updated_at'        => isset( $row->updated_at ) ? Vitacare_Crm_Db::format_datetime( $row->updated_at ) : null,
		);
	}
}

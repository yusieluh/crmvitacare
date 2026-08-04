<?php
defined( 'ABSPATH' ) || exit;

/**
 * D-25 Fase 3: enlaces con seguimiento propio (UTM/clics), 100%
 * autohospedado -- sin depender de Bitly ni ningún acortador de terceros.
 * El código no es válido para cualquier URL enviada por el público: solo
 * lo genera el staff (cap `manage_options`/`vitacare_crm_access`) desde el
 * admin, así que redirigir sin usar wp_safe_redirect() es intencional
 * (no es un open-redirect: el destino nunca viene del request público).
 */
final class Vitacare_Crm_Links_Repo {

	private const CODE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';
	private const CODE_LENGTH   = 7;

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'vitacare_crm_link_clicks';
	}

	private static function ready(): bool {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	/**
	 * Crea un enlace corto. El destino guarda ya incrustados los
	 * parámetros UTM (utm_source=vitacare-crm, utm_medium=crm,
	 * utm_campaign={campaign_tag}) para que cualquier herramienta de
	 * analítica externa del sitio destino los reciba también.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function create( string $target_url, string $campaign_tag = '', int $lead_id = 0 ) {
		global $wpdb;
		if ( ! self::ready() ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_db_error', __( 'La tabla de enlaces todavía no existe (recarga en unos segundos).', 'vitacare-crm' ), 500 );
		}

		$target_url = esc_url_raw( trim( $target_url ) );
		if ( '' === $target_url || false === filter_var( $target_url, FILTER_VALIDATE_URL ) ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_invalid_param', __( 'URL de destino inválida.', 'vitacare-crm' ), 400 );
		}

		$campaign_tag = sanitize_text_field( substr( $campaign_tag, 0, 100 ) );
		if ( $campaign_tag !== '' ) {
			$target_url = add_query_arg(
				array(
					'utm_source'   => 'vitacare-crm',
					'utm_medium'   => 'crm',
					'utm_campaign' => rawurlencode( $campaign_tag ),
				),
				$target_url
			);
		}

		$code  = self::generate_unique_code();
		$table = self::table();
		$now   = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert(
			$table,
			array(
				'code'         => $code,
				'target_url'   => $target_url,
				'campaign_tag' => $campaign_tag !== '' ? $campaign_tag : null,
				'lead_id'      => $lead_id > 0 ? $lead_id : null,
				'clicks_count' => 0,
				'created_at'   => $now,
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s' )
		);
		if ( false === $ok ) {
			Vitacare_Crm_Logger::error( 'link_insert_failed', array( 'db' => $wpdb->last_error ) );
			return Vitacare_Crm_Db::error( 'vitacare_crm_db_error', __( 'No se pudo crear el enlace.', 'vitacare-crm' ), 500 );
		}

		$row = self::get_by_code( $code );
		return $row ?? Vitacare_Crm_Db::error( 'vitacare_crm_db_error', __( 'Enlace creado pero no se pudo leer.', 'vitacare-crm' ), 500 );
	}

	/**
	 * Registra un clic y devuelve la URL de destino, o null si el código
	 * no existe.
	 */
	public static function register_click( string $code ): ?string {
		global $wpdb;
		if ( ! self::ready() ) {
			return null;
		}
		$code  = sanitize_text_field( $code );
		$table = self::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, target_url FROM {$table} WHERE code = %s LIMIT 1", $code ) );
		if ( ! $row ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET clicks_count = clicks_count + 1, last_click_at = %s WHERE id = %d",
				current_time( 'mysql' ),
				(int) $row->id
			)
		);

		return (string) $row->target_url;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_all( int $limit = 100 ): array {
		global $wpdb;
		if ( ! self::ready() ) {
			return array();
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit ) );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[] = self::format( $row );
		}
		return $out;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get_by_code( string $code ): ?array {
		global $wpdb;
		if ( ! self::ready() ) {
			return null;
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE code = %s LIMIT 1", sanitize_text_field( $code ) ) );
		return $row ? self::format( $row ) : null;
	}

	/**
	 * Agregado para el dashboard de Reportes (D-23 Fase 1): clics totales
	 * y por campaña.
	 *
	 * @return array<int, array{campaign_tag: string, links: int, clicks: int}>
	 */
	public static function clicks_by_campaign(): array {
		global $wpdb;
		if ( ! self::ready() ) {
			return array();
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT COALESCE(campaign_tag, '(sin campaña)') AS campaign_tag, COUNT(*) AS links, SUM(clicks_count) AS clicks
			FROM {$table}
			GROUP BY campaign_tag
			ORDER BY clicks DESC"
		);
		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'campaign_tag' => (string) $row->campaign_tag,
				'links'        => (int) $row->links,
				'clicks'       => (int) $row->clicks,
			);
		}
		return $out;
	}

	private static function generate_unique_code(): string {
		global $wpdb;
		$table = self::table();
		for ( $attempt = 0; $attempt < 10; $attempt++ ) {
			$code = '';
			for ( $i = 0; $i < self::CODE_LENGTH; $i++ ) {
				$code .= self::CODE_ALPHABET[ random_int( 0, strlen( self::CODE_ALPHABET ) - 1 ) ];
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE code = %s", $code ) );
			if ( ! $exists ) {
				return $code;
			}
		}
		// Extremadamente improbable con 7 caracteres de un alfabeto de 55; fallback con más longitud.
		return $code . (string) random_int( 10, 99 );
	}

	public static function short_url( string $code ): string {
		return rest_url( 'vitacare-crm/v1/go/' . rawurlencode( $code ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function format( object $row ): array {
		return array(
			'id'            => (int) $row->id,
			'code'          => (string) $row->code,
			'short_url'     => self::short_url( (string) $row->code ),
			'target_url'    => (string) $row->target_url,
			'campaign_tag'  => $row->campaign_tag,
			'lead_id'       => null !== $row->lead_id && '' !== $row->lead_id ? (int) $row->lead_id : null,
			'clicks_count'  => (int) $row->clicks_count,
			'created_at'    => Vitacare_Crm_Db::format_datetime( $row->created_at ?? null ),
			'last_click_at' => isset( $row->last_click_at ) ? Vitacare_Crm_Db::format_datetime( $row->last_click_at ) : null,
		);
	}
}

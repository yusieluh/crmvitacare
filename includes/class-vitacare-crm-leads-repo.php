<?php
defined( 'ABSPATH' ) || exit;

/**
 * D-23 Fase 2: pipeline de leads (DB v3). Un lead es un contacto de
 * marketing, separado de la conversación de soporte — el opt-in de
 * campañas (`consent_status`) nunca se asume solo porque alguien escribió
 * al CRM; eso solo habilita responder dentro de la ventana ya permitida
 * hoy. Marcar `opted_in` es siempre una acción explícita del staff.
 */
final class Vitacare_Crm_Leads_Repo {

	public const SOURCES         = array( 'manual', 'whatsapp', 'facebook', 'instagram', 'email', 'import' );
	public const CONSENT_STATES  = array( 'unknown', 'opted_in', 'opted_out' );

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'vitacare_crm_leads';
	}

	private static function ready(): bool {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	/**
	 * @param array<string, mixed> $args source, consent_status, tag, q, page, per_page.
	 * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
	 */
	public static function list( array $args ): array {
		global $wpdb;
		if ( ! self::ready() ) {
			return array(
				'items'    => array(),
				'total'    => 0,
				'page'     => 1,
				'per_page' => 20,
			);
		}
		$table = self::table();

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();

		$source = isset( $args['source'] ) ? sanitize_key( (string) $args['source'] ) : '';
		if ( $source !== '' && in_array( $source, self::SOURCES, true ) ) {
			$where[]  = 'source = %s';
			$params[] = $source;
		}

		$consent = isset( $args['consent_status'] ) ? sanitize_key( (string) $args['consent_status'] ) : '';
		if ( $consent !== '' && in_array( $consent, self::CONSENT_STATES, true ) ) {
			$where[]  = 'consent_status = %s';
			$params[] = $consent;
		}

		$tag = isset( $args['tag'] ) ? sanitize_text_field( (string) $args['tag'] ) : '';
		if ( $tag !== '' ) {
			$where[]  = 'tags LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $tag ) . '%';
		}

		$q = isset( $args['q'] ) ? sanitize_text_field( (string) $args['q'] ) : '';
		if ( $q !== '' ) {
			$q        = substr( $q, 0, 100 );
			$like     = '%' . $wpdb->esc_like( $q ) . '%';
			$where[]  = '(name LIKE %s OR phone LIKE %s OR email LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) $wpdb->get_var( ! empty( $params ) ? $wpdb->prepare( $count_sql, $params ) : $count_sql );

		$list_sql      = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
		$list_params   = $params;
		$list_params[] = $per_page;
		$list_params[] = $offset;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) );

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::format( $row );
		}

		return array(
			'items'    => $items,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		if ( ! self::ready() ) {
			return null;
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
		return $row ? self::format( $row ) : null;
	}

	/**
	 * Alta manual/import. No valida duplicados de forma estricta (un
	 * mismo teléfono/correo puede repetirse a propósito para fuentes
	 * distintas); sí normaliza y valida el mínimo (nombre o contacto).
	 *
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>|WP_Error
	 */
	public static function create( array $data ) {
		global $wpdb;
		$table = self::table();

		$name  = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';
		$phone = isset( $data['phone'] ) ? preg_replace( '/[^\d+]/', '', (string) $data['phone'] ) : '';
		$email = isset( $data['email'] ) ? sanitize_email( (string) $data['email'] ) : '';

		if ( $name === '' && $phone === '' && $email === '' ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_invalid_param',
				__( 'El lead necesita al menos nombre, teléfono o correo.', 'vitacare-crm' ),
				400
			);
		}

		$source = isset( $data['source'] ) ? sanitize_key( (string) $data['source'] ) : 'manual';
		if ( ! in_array( $source, self::SOURCES, true ) ) {
			$source = 'manual';
		}

		$tags = self::normalize_tags( $data['tags'] ?? array() );
		$now  = current_time( 'mysql' );

		$row = array(
			'name'            => $name !== '' ? $name : null,
			'phone'           => $phone !== '' ? $phone : null,
			'email'           => $email !== '' ? $email : null,
			'source'          => $source,
			'tags'            => wp_json_encode( $tags ),
			'consent_status'  => 'unknown',
			'notes'           => isset( $data['notes'] ) ? sanitize_textarea_field( (string) $data['notes'] ) : null,
			'created_at'      => $now,
			'updated_at'      => $now,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert(
			$table,
			$row,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $ok ) {
			Vitacare_Crm_Logger::error( 'lead_insert_failed', array( 'db' => $wpdb->last_error ) );
			return Vitacare_Crm_Db::error( 'vitacare_crm_db_error', __( 'No se pudo crear el lead.', 'vitacare-crm' ), 500 );
		}

		$created = self::get( (int) $wpdb->insert_id );
		return $created ?? Vitacare_Crm_Db::error( 'vitacare_crm_db_error', __( 'Lead creado pero no se pudo leer.', 'vitacare-crm' ), 500 );
	}

	/**
	 * Actualiza campos editables (allowlist). No permite tocar
	 * consent_status aquí a propósito -- eso pasa por set_consent() para
	 * dejar rastro explícito de origen/fecha.
	 *
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>|WP_Error
	 */
	public static function update( int $id, array $data ) {
		global $wpdb;
		$table = self::table();

		if ( null === self::get( $id ) ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_not_found', __( 'Lead no encontrado.', 'vitacare-crm' ), 404 );
		}

		$allowed = array( 'name', 'phone', 'email', 'tags', 'notes', 'assigned_to' );
		$unknown = array_diff( array_keys( $data ), $allowed );
		if ( ! empty( $unknown ) ) {
			return Vitacare_Crm_Db::error(
				'vitacare_crm_invalid_param',
				sprintf(
					/* translators: %s: field names */
					__( 'Campos no permitidos: %s', 'vitacare-crm' ),
					implode( ', ', $unknown )
				),
				400
			);
		}

		$set     = array();
		$formats = array();

		if ( array_key_exists( 'name', $data ) ) {
			$set['name'] = sanitize_text_field( (string) $data['name'] );
			$formats[]   = '%s';
		}
		if ( array_key_exists( 'phone', $data ) ) {
			$set['phone'] = preg_replace( '/[^\d+]/', '', (string) $data['phone'] );
			$formats[]    = '%s';
		}
		if ( array_key_exists( 'email', $data ) ) {
			$set['email'] = sanitize_email( (string) $data['email'] );
			$formats[]    = '%s';
		}
		if ( array_key_exists( 'tags', $data ) ) {
			$set['tags'] = wp_json_encode( self::normalize_tags( $data['tags'] ) );
			$formats[]   = '%s';
		}
		if ( array_key_exists( 'notes', $data ) ) {
			$set['notes'] = sanitize_textarea_field( (string) $data['notes'] );
			$formats[]    = '%s';
		}
		if ( array_key_exists( 'assigned_to', $data ) ) {
			$val = $data['assigned_to'];
			if ( null === $val || $val === '' || (int) $val === 0 ) {
				$set['assigned_to'] = null;
				$formats[]          = '%s';
			} else {
				$set['assigned_to'] = (int) $val;
				$formats[]          = '%d';
			}
		}

		if ( empty( $set ) ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_invalid_param', __( 'Nada que actualizar.', 'vitacare-crm' ), 400 );
		}

		$set['updated_at'] = current_time( 'mysql' );
		$formats[]         = '%s';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $table, $set, array( 'id' => $id ), $formats, array( '%d' ) );

		$updated = self::get( $id );
		return $updated ?? Vitacare_Crm_Db::error( 'vitacare_crm_not_found', __( 'Lead no encontrado.', 'vitacare-crm' ), 404 );
	}

	/**
	 * Marca opt-in/opt-out con rastro de origen y fecha -- es la única
	 * vía para tocar consent_status (nunca desde update() genérico).
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function set_consent( int $id, string $status, string $source = 'staff' ) {
		global $wpdb;
		$status = sanitize_key( $status );
		if ( ! in_array( $status, self::CONSENT_STATES, true ) ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_invalid_param', __( 'Estado de consentimiento inválido.', 'vitacare-crm' ), 400 );
		}
		if ( null === self::get( $id ) ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_not_found', __( 'Lead no encontrado.', 'vitacare-crm' ), 404 );
		}

		$table = self::table();
		$now   = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array(
				'consent_status' => $status,
				'consent_source' => sanitize_text_field( $source ),
				'consent_at'     => $now,
				'updated_at'     => $now,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return self::get( $id );
	}

	/**
	 * Enlaza un lead a una conversación (o crea el lead si hace falta) y
	 * anota conversations.lead_id -- usado por "Convertir a conversación"
	 * en el admin y por el auto-alta de leads en mensajes entrantes.
	 */
	public static function link_conversation( int $lead_id, int $conversation_id ): void {
		global $wpdb;
		$now = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			self::table(),
			array(
				'conversation_id' => $conversation_id,
				'updated_at'      => $now,
			),
			array( 'id' => $lead_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
		$conversations = Vitacare_Crm_Db::conversations_table();
		if ( Vitacare_Crm_Db::column_exists( $conversations, 'lead_id' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$conversations,
				array( 'lead_id' => $lead_id ),
				array( 'id' => $conversation_id ),
				array( '%d' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Auto-alta (D-23 Fase 2): si una conversación no tiene lead asociado
	 * todavía, crea uno con consent_status='unknown' -- escribir al CRM
	 * NO es opt-in de marketing, solo habilita la respuesta ya permitida
	 * hoy dentro de la ventana del canal.
	 */
	public static function ensure_lead_for_conversation(
		int $conversation_id,
		string $channel,
		?string $contact_name,
		?string $contact_phone,
		?string $contact_email
	): void {
		global $wpdb;
		if ( ! self::ready() ) {
			return;
		}
		$conversations = Vitacare_Crm_Db::conversations_table();
		if ( ! Vitacare_Crm_Db::column_exists( $conversations, 'lead_id' ) ) {
			return;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$current = $wpdb->get_var( $wpdb->prepare( "SELECT lead_id FROM {$conversations} WHERE id = %d", $conversation_id ) );
		if ( null !== $current && '' !== $current && (int) $current > 0 ) {
			return;
		}

		// Reusar un lead existente por teléfono/correo antes de crear uno
		// nuevo -- evita duplicados cuando el mismo contacto ya tenía un
		// lead (p. ej. "Convertir a conversación" crea la conversación
		// primero y este método se dispara solo, o el mismo número escribe
		// por dos canales distintos).
		$existing_lead = self::find_by_contact( $contact_phone, $contact_email );
		if ( null !== $existing_lead ) {
			self::link_conversation( (int) $existing_lead['id'], $conversation_id );
			return;
		}

		$source = in_array( $channel, array( 'whatsapp', 'facebook', 'instagram', 'email' ), true ) ? $channel : 'manual';
		$lead   = self::create(
			array(
				'name'  => $contact_name ?? '',
				'phone' => $contact_phone ?? '',
				'email' => $contact_email ?? '',
				'source' => $source,
			)
		);
		if ( is_wp_error( $lead ) || empty( $lead['id'] ) ) {
			return;
		}
		self::link_conversation( (int) $lead['id'], $conversation_id );
	}

	/**
	 * Busca un lead existente por correo exacto o por los últimos 8
	 * dígitos del teléfono (mismo criterio de match que D-19 para
	 * números ecuatorianos sin formato garantizado).
	 *
	 * @return array<string, mixed>|null
	 */
	private static function find_by_contact( ?string $phone, ?string $email ): ?array {
		global $wpdb;
		$table = self::table();

		if ( ! empty( $email ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s ORDER BY id ASC LIMIT 1", strtolower( $email ) ) );
			if ( $row ) {
				return self::format( $row );
			}
		}

		if ( ! empty( $phone ) ) {
			$digits = preg_replace( '/\D+/', '', $phone );
			if ( is_string( $digits ) && strlen( $digits ) >= 8 ) {
				$suffix = substr( $digits, -8 );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE phone LIKE %s ORDER BY id ASC LIMIT 1", '%' . $wpdb->esc_like( $suffix ) ) );
				if ( $row ) {
					return self::format( $row );
				}
			}
		}

		return null;
	}

	/**
	 * Import CSV: columnas esperadas name,phone,email,tags (tags separados
	 * por ";" dentro de la celda). Fila de encabezado obligatoria.
	 *
	 * @return array{created: int, skipped: int, errors: array<int, string>}
	 */
	public static function import_csv( string $csv_content ): array {
		$result = array(
			'created' => 0,
			'skipped' => 0,
			'errors'  => array(),
		);

		$lines = preg_split( '/\r\n|\r|\n/', trim( $csv_content ) );
		if ( ! is_array( $lines ) || count( $lines ) < 2 ) {
			$result['errors'][] = __( 'CSV vacío o sin filas de datos.', 'vitacare-crm' );
			return $result;
		}

		$header = str_getcsv( array_shift( $lines ) );
		$header = array_map( static fn( $h ) => strtolower( trim( (string) $h ) ), $header );
		$idx    = array_flip( $header );

		foreach ( $lines as $line_num => $line ) {
			if ( trim( $line ) === '' ) {
				continue;
			}
			$cols = str_getcsv( $line );

			$name  = isset( $idx['name'], $cols[ $idx['name'] ] ) ? trim( (string) $cols[ $idx['name'] ] ) : '';
			$phone = isset( $idx['phone'], $cols[ $idx['phone'] ] ) ? trim( (string) $cols[ $idx['phone'] ] ) : '';
			$email = isset( $idx['email'], $cols[ $idx['email'] ] ) ? trim( (string) $cols[ $idx['email'] ] ) : '';
			$tags  = isset( $idx['tags'], $cols[ $idx['tags'] ] ) ? array_filter( array_map( 'trim', explode( ';', (string) $cols[ $idx['tags'] ] ) ) ) : array();

			if ( $name === '' && $phone === '' && $email === '' ) {
				++$result['skipped'];
				$result['errors'][] = sprintf(
					/* translators: %d: line number */
					__( 'Fila %d: sin nombre, teléfono ni correo — omitida.', 'vitacare-crm' ),
					$line_num + 2
				);
				continue;
			}

			$created = self::create(
				array(
					'name'   => $name,
					'phone'  => $phone,
					'email'  => $email,
					'tags'   => $tags,
					'source' => 'import',
				)
			);
			if ( is_wp_error( $created ) ) {
				++$result['skipped'];
				$result['errors'][] = sprintf(
					/* translators: 1: line number, 2: error message */
					__( 'Fila %1$d: %2$s', 'vitacare-crm' ),
					$line_num + 2,
					$created->get_error_message()
				);
				continue;
			}
			++$result['created'];
		}

		return $result;
	}

	/**
	 * @param mixed $tags
	 * @return array<int, string>
	 */
	private static function normalize_tags( $tags ): array {
		if ( is_string( $tags ) ) {
			$tags = explode( ',', $tags );
		}
		if ( ! is_array( $tags ) ) {
			return array();
		}
		$out = array();
		foreach ( $tags as $t ) {
			$t = sanitize_text_field( trim( (string) $t ) );
			if ( $t !== '' && ! in_array( $t, $out, true ) ) {
				$out[] = $t;
			}
		}
		return array_slice( $out, 0, 20 );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function format( object $row ): array {
		$tags = array();
		if ( ! empty( $row->tags ) ) {
			$decoded = json_decode( (string) $row->tags, true );
			if ( is_array( $decoded ) ) {
				$tags = $decoded;
			}
		}
		return array(
			'id'              => (int) $row->id,
			'name'            => $row->name,
			'phone'           => $row->phone,
			'email'           => $row->email,
			'source'          => (string) $row->source,
			'tags'            => $tags,
			'consent_status'  => (string) $row->consent_status,
			'consent_source'  => $row->consent_source,
			'consent_at'      => Vitacare_Crm_Db::format_datetime( $row->consent_at ?? null ),
			'notes'           => $row->notes,
			'assigned_to'     => null !== $row->assigned_to && '' !== $row->assigned_to ? (int) $row->assigned_to : null,
			'conversation_id' => null !== $row->conversation_id && '' !== $row->conversation_id ? (int) $row->conversation_id : null,
			'created_at'      => Vitacare_Crm_Db::format_datetime( $row->created_at ?? null ),
			'updated_at'      => isset( $row->updated_at ) ? Vitacare_Crm_Db::format_datetime( $row->updated_at ) : null,
		);
	}
}

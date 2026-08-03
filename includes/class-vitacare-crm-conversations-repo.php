<?php
defined( 'ABSPATH' ) || exit;

final class Vitacare_Crm_Conversations_Repo {

	/**
	 * @param array<string, mixed> $args channel, status, assigned_to, page, per_page, q.
	 * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
	 */
	public static function list( array $args ): array {
		global $wpdb;
		$table    = Vitacare_Crm_Db::conversations_table();
		$messages = Vitacare_Crm_Db::messages_table();

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = (int) ( $args['per_page'] ?? 20 );
		$per_page = min( 50, max( 1, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();

		$channel = isset( $args['channel'] ) ? sanitize_key( (string) $args['channel'] ) : '';
		if ( $channel !== '' ) {
			$where[]  = 'c.channel = %s';
			$params[] = $channel;
		}

		$status = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : 'open';
		if ( $status !== 'all' && $status !== '' ) {
			$where[]  = 'c.status = %s';
			$params[] = $status;
		}

		if ( isset( $args['assigned_to'] ) && $args['assigned_to'] !== '' && $args['assigned_to'] !== null ) {
			$asg = $args['assigned_to'];
			if ( $asg === 'me' ) {
				$where[]  = 'c.assigned_to = %d';
				$params[] = get_current_user_id();
			} elseif ( $asg === 'unassigned' ) {
				$where[] = '(c.assigned_to IS NULL OR c.assigned_to = 0)';
			} else {
				$where[]  = 'c.assigned_to = %d';
				$params[] = (int) $asg;
			}
		}

		$q = isset( $args['q'] ) ? sanitize_text_field( (string) $args['q'] ) : '';
		if ( $q !== '' ) {
			$q = substr( $q, 0, 100 );
			$like     = '%' . $wpdb->esc_like( $q ) . '%';
			$where[]  = '(c.contact_name LIKE %s OR c.contact_phone LIKE %s OR c.external_contact_id LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} c WHERE {$where_sql}";
		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$total = (int) $wpdb->get_var( $count_sql );
		}

		$has_unread = Vitacare_Crm_Db::column_exists( $table, 'unread_count' );
		$unread_sel = $has_unread ? 'c.unread_count' : '0 AS unread_count';
		$has_lead   = Vitacare_Crm_Db::column_exists( $table, 'lead_id' );
		$lead_sel   = $has_lead ? 'c.lead_id' : 'NULL AS lead_id';

		$list_sql = "SELECT c.id, c.channel, c.external_contact_id, c.contact_name, c.contact_phone,
			c.wp_user_id, c.status, c.assigned_to, c.last_message_at, c.created_at,
			{$unread_sel}, {$lead_sel},
			(SELECT m.body FROM {$messages} m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message_preview
			FROM {$table} c
			WHERE {$where_sql}
			ORDER BY c.last_message_at DESC, c.id DESC
			LIMIT %d OFFSET %d";

		$list_params   = $params;
		$list_params[] = $per_page;
		$list_params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) );
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = self::format_list_item( $row );
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
		$table = Vitacare_Crm_Db::conversations_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
		if ( ! $row ) {
			return null;
		}
		return self::format_detail( $row );
	}

	/**
	 * PATCH allowlist: status, assigned_to, wp_user_id, lead_id (si columna existe).
	 *
	 * @param array<string, mixed> $body
	 * @return array<string, mixed>|WP_Error
	 */
	public static function update( int $id, array $body ) {
		global $wpdb;
		$table = Vitacare_Crm_Db::conversations_table();

		$existing = self::get( $id );
		if ( null === $existing ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_not_found', __( 'Conversación no encontrada.', 'vitacare-crm' ), 404 );
		}

		$allowed = array( 'status', 'assigned_to', 'wp_user_id', 'lead_id' );
		$unknown = array_diff( array_keys( $body ), $allowed );
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

		$data  = array();
		$formats = array();

		if ( array_key_exists( 'status', $body ) ) {
			$status = sanitize_key( (string) $body['status'] );
			if ( ! in_array( $status, array( 'open', 'pending', 'closed' ), true ) ) {
				return Vitacare_Crm_Db::error( 'vitacare_crm_invalid_param', __( 'Estado inválido.', 'vitacare-crm' ), 400 );
			}
			$data['status'] = $status;
			$formats[]      = '%s';
		}

		if ( array_key_exists( 'assigned_to', $body ) ) {
			$val = $body['assigned_to'];
			if ( null === $val || $val === '' || $val === 0 || $val === '0' ) {
				$data['assigned_to'] = null;
				$formats[]           = '%s'; // null handled below
			} else {
				$data['assigned_to'] = (int) $val;
				$formats[]           = '%d';
			}
		}

		if ( array_key_exists( 'wp_user_id', $body ) ) {
			$val = $body['wp_user_id'];
			if ( null === $val || $val === '' || $val === 0 || $val === '0' ) {
				$data['wp_user_id'] = null;
				$formats[]          = '%s';
			} else {
				$data['wp_user_id'] = (int) $val;
				$formats[]          = '%d';
			}
		}

		if ( array_key_exists( 'lead_id', $body ) ) {
			if ( ! Vitacare_Crm_Db::column_exists( $table, 'lead_id' ) ) {
				return Vitacare_Crm_Db::error(
					'vitacare_crm_invalid_param',
					__( 'Los leads aún no están disponibles en esta versión de esquema.', 'vitacare-crm' ),
					400
				);
			}
			$val = $body['lead_id'];
			if ( null === $val || $val === '' || $val === 0 || $val === '0' ) {
				$data['lead_id'] = null;
				$formats[]       = '%s';
			} else {
				$data['lead_id'] = (int) $val;
				$formats[]       = '%d';
			}
		}

		if ( empty( $data ) ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_invalid_param', __( 'Nada que actualizar.', 'vitacare-crm' ), 400 );
		}

		if ( Vitacare_Crm_Db::column_exists( $table, 'updated_at' ) ) {
			$data['updated_at'] = current_time( 'mysql' );
		}

		$set_parts = array();
		$values    = array();
		foreach ( $data as $col => $val ) {
			$col_sql = preg_replace( '/[^a-z0-9_]/i', '', $col );
			if ( $col_sql === '' ) {
				continue;
			}
			if ( null === $val ) {
				$set_parts[] = "`{$col_sql}` = NULL";
			} elseif ( is_int( $val ) ) {
				$set_parts[] = "`{$col_sql}` = %d";
				$values[]    = $val;
			} else {
				$set_parts[] = "`{$col_sql}` = %s";
				$values[]    = (string) $val;
			}
		}
		$values[] = $id;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET " . implode( ', ', $set_parts ) . ' WHERE id = %d', $values ) );

		$updated = self::get( $id );
		return $updated ?? Vitacare_Crm_Db::error( 'vitacare_crm_not_found', __( 'Conversación no encontrada.', 'vitacare-crm' ), 404 );
	}

	/**
	 * @param object $row DB row.
	 * @return array<string, mixed>
	 */
	public static function format_list_item( object $row ): array {
		$preview = isset( $row->last_message_preview ) ? (string) $row->last_message_preview : '';
		if ( function_exists( 'mb_substr' ) ) {
			$preview = mb_substr( $preview, 0, 120 );
		} else {
			$preview = substr( $preview, 0, 120 );
		}

		return array(
			'id'                   => (int) $row->id,
			'channel'              => (string) $row->channel,
			'contact_name'         => $row->contact_name,
			'contact_phone'        => $row->contact_phone,
			'status'               => (string) $row->status,
			'assigned_to'          => null !== $row->assigned_to && '' !== $row->assigned_to ? (int) $row->assigned_to : null,
			'lead_id'              => isset( $row->lead_id ) && null !== $row->lead_id && '' !== $row->lead_id ? (int) $row->lead_id : null,
			'last_message_at'      => Vitacare_Crm_Db::format_datetime( $row->last_message_at ?? null ),
			'last_message_preview' => $preview,
			'unread_count'         => isset( $row->unread_count ) ? (int) $row->unread_count : 0,
		);
	}

	/**
	 * @param object $row DB row.
	 * @return array<string, mixed>
	 */
	public static function format_detail( object $row ): array {
		$item = self::format_list_item( $row );
		$item['external_contact_id'] = (string) $row->external_contact_id;
		$item['wp_user_id']          = null !== $row->wp_user_id && '' !== $row->wp_user_id ? (int) $row->wp_user_id : null;
		$item['created_at']          = Vitacare_Crm_Db::format_datetime( $row->created_at ?? null );
		$item['updated_at']          = isset( $row->updated_at ) ? Vitacare_Crm_Db::format_datetime( $row->updated_at ) : null;
		$item['meta']                = null;
		if ( isset( $row->meta ) && is_string( $row->meta ) && $row->meta !== '' ) {
			$decoded = json_decode( $row->meta, true );
			$item['meta'] = is_array( $decoded ) ? $decoded : $row->meta;
		}
		return $item;
	}
}

<?php
defined( 'ABSPATH' ) || exit;

final class Vitacare_Crm_Messages_Repo {

	/**
	 * Mensajes de una conversación: últimos N, o id < before_id (historial).
	 *
	 * @return array{items: array<int, array<string, mixed>>, has_more: bool}|WP_Error
	 */
	public static function list_for_conversation( int $conversation_id, int $limit = 30, ?int $before_id = null ) {
		global $wpdb;

		$conv = Vitacare_Crm_Conversations_Repo::get( $conversation_id );
		if ( null === $conv ) {
			return Vitacare_Crm_Db::error( 'vitacare_crm_not_found', __( 'Conversación no encontrada.', 'vitacare-crm' ), 404 );
		}

		$limit = min( 50, max( 1, $limit ) );
		$table = Vitacare_Crm_Db::messages_table();

		// Pedimos limit+1 para saber has_more.
		$fetch = $limit + 1;

		if ( $before_id ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql  = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE conversation_id = %d AND id < %d ORDER BY id DESC LIMIT %d",
				$conversation_id,
				$before_id,
				$fetch
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE conversation_id = %d ORDER BY id DESC LIMIT %d",
				$conversation_id,
				$fetch
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql );
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$has_more = count( $rows ) > $limit;
		if ( $has_more ) {
			$rows = array_slice( $rows, 0, $limit );
		}

		// Devolver en orden cronológico ascendente (UI de hilo).
		$rows = array_reverse( $rows );

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = self::format( $row );
		}

		return array(
			'items'    => $items,
			'has_more' => $has_more,
		);
	}

	/**
	 * @return object|null
	 */
	public static function find_by_external_id( string $external_message_id ) {
		global $wpdb;
		if ( $external_message_id === '' ) {
			return null;
		}
		$table = Vitacare_Crm_Db::messages_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE external_message_id = %s LIMIT 1",
				$external_message_id
			)
		);
		return $row ?: null;
	}

	/**
	 * Inserta mensaje. Idempotente por external_message_id.
	 *
	 * @param array<string, mixed> $data
	 * @return int|false id insertado; false si dedupe; -1 error
	 */
	public static function insert_message( array $data ) {
		global $wpdb;
		$table = Vitacare_Crm_Db::messages_table();

		$ext = isset( $data['external_message_id'] ) ? (string) $data['external_message_id'] : '';
		if ( $ext !== '' && self::find_by_external_id( $ext ) ) {
			return false;
		}

		$row = array(
			'conversation_id'     => (int) $data['conversation_id'],
			'direction'           => (string) $data['direction'],
			'sender_type'         => (string) $data['sender_type'],
			'body'                => $data['body'] ?? null,
			'media_url'           => $data['media_url'] ?? null,
			'external_message_id' => $ext !== '' ? $ext : null,
			'created_at'          => (string) ( $data['created_at'] ?? current_time( 'mysql' ) ),
		);
		$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( Vitacare_Crm_Db::column_exists( $table, 'message_type' ) ) {
			$row['message_type'] = (string) ( $data['message_type'] ?? 'text' );
			$formats[]           = '%s';
		}
		if ( Vitacare_Crm_Db::column_exists( $table, 'media_mime' ) ) {
			$row['media_mime'] = $data['media_mime'] ?? null;
			$formats[]         = '%s';
		}
		if ( Vitacare_Crm_Db::column_exists( $table, 'delivery_status' ) ) {
			$row['delivery_status'] = $data['delivery_status'] ?? null;
			$formats[]              = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert( $table, $row, $formats );
		if ( false === $ok ) {
			// Posible carrera UNIQUE.
			if ( $ext !== '' && self::find_by_external_id( $ext ) ) {
				return false;
			}
			Vitacare_Crm_Logger::error( 'message_insert_failed', array( 'db' => $wpdb->last_error ) );
			return -1;
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Actualiza delivery_status por wamid. false si no hay fila.
	 */
	public static function update_delivery_status( string $external_message_id, string $status ): bool {
		global $wpdb;
		if ( $external_message_id === '' ) {
			return false;
		}
		$table = Vitacare_Crm_Db::messages_table();
		if ( ! Vitacare_Crm_Db::column_exists( $table, 'delivery_status' ) ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$n = $wpdb->update(
			$table,
			array( 'delivery_status' => $status ),
			array( 'external_message_id' => $external_message_id ),
			array( '%s' ),
			array( '%s' )
		);
		return false !== $n && (int) $n > 0;
	}

	/**
	 * @param object $row DB row.
	 * @return array<string, mixed>
	 */
	public static function format( object $row ): array {
		return array(
			'id'                   => (int) $row->id,
			'conversation_id'      => (int) $row->conversation_id,
			'direction'            => (string) $row->direction,
			'sender_type'          => (string) $row->sender_type,
			'message_type'         => isset( $row->message_type ) && $row->message_type !== '' ? (string) $row->message_type : 'text',
			'body'                 => $row->body,
			'media_url'            => $row->media_url,
			'media_mime'           => $row->media_mime ?? null,
			'delivery_status'      => $row->delivery_status ?? null,
			'external_message_id'  => $row->external_message_id,
			'created_at'           => Vitacare_Crm_Db::format_datetime( $row->created_at ?? null ),
		);
	}
}

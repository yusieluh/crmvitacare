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

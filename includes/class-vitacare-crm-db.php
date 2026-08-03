<?php
defined( 'ABSPATH' ) || exit;

/** Helpers de tablas y errores REST del CRM. */
final class Vitacare_Crm_Db {

	public static function conversations_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'vitacare_crm_conversations';
	}

	public static function messages_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'vitacare_crm_messages';
	}

	public static function column_exists( string $table, string $column ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", $column ) );
		return ! empty( $col );
	}

	/**
	 * @return WP_Error
	 */
	public static function error( string $code, string $message, int $status ): WP_Error {
		return new WP_Error(
			$code,
			$message,
			array( 'status' => $status )
		);
	}

	public static function require_crm_access(): bool {
		return is_user_logged_in() && current_user_can( VITACARE_CRM_CAPABILITY );
	}

	/**
	 * ISO-ish datetime for JSON (WP local time as stored).
	 */
	public static function format_datetime( ?string $mysql_dt ): ?string {
		if ( null === $mysql_dt || $mysql_dt === '' || $mysql_dt === '0000-00-00 00:00:00' ) {
			return null;
		}
		return str_replace( ' ', 'T', $mysql_dt );
	}
}

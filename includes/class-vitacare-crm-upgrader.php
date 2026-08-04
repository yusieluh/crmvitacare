<?php
defined( 'ABSPATH' ) || exit;

/**
 * Migraciones de esquema y reparación de capabilities.
 * Solo additive; actualiza vitacare_crm_db_version al éxito de cada paso.
 */
final class Vitacare_Crm_Upgrader {

	public static function init(): void {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ), 5 );
		add_action( 'plugins_loaded', array( __CLASS__, 'ensure_caps' ), 6 );
	}

	public static function maybe_upgrade(): void {
		$current = (string) get_option( 'vitacare_crm_db_version', '0' );
		$target  = (string) VITACARE_CRM_DB_VERSION;

		if ( version_compare( $current, $target, '>=' ) ) {
			return;
		}

		if ( version_compare( $current, '1', '<' ) ) {
			Vitacare_Crm_Activator::install_tables_v1();
			update_option( 'vitacare_crm_db_version', '1', false );
			$current = '1';
		}

		if ( version_compare( $current, '2', '<' ) && version_compare( $target, '2', '>=' ) ) {
			self::upgrade_to_2();
			update_option( 'vitacare_crm_db_version', '2', false );
			$current = '2';
		}

		if ( version_compare( $current, '3', '<' ) && version_compare( $target, '3', '>=' ) ) {
			self::upgrade_to_3();
			update_option( 'vitacare_crm_db_version', '3', false );
			$current = '3';
		}

		if ( version_compare( $current, '4', '<' ) && version_compare( $target, '4', '>=' ) ) {
			self::upgrade_to_4();
			update_option( 'vitacare_crm_db_version', '4', false );
			$current = '4';
		}

		if ( version_compare( $current, '5', '<' ) && version_compare( $target, '5', '>=' ) ) {
			self::upgrade_to_5();
			update_option( 'vitacare_crm_db_version', '5', false );
			$current = '5';
		}
	}

	/**
	 * DB v2: columnas inbox + UNIQUE channel/contact + message fields.
	 */
	public static function upgrade_to_2(): void {
		global $wpdb;

		Vitacare_Crm_Activator::install_tables_v2();

		$conversations = $wpdb->prefix . 'vitacare_crm_conversations';
		$messages      = $wpdb->prefix . 'vitacare_crm_messages';

		// Asegurar columnas por si dbDelta no las añadió en algún entorno.
		self::add_column_if_missing( $conversations, 'unread_count', "INT UNSIGNED NOT NULL DEFAULT 0" );
		self::add_column_if_missing( $conversations, 'updated_at', 'DATETIME NULL' );
		self::add_column_if_missing( $conversations, 'meta', 'LONGTEXT NULL' );
		self::add_column_if_missing( $messages, 'message_type', "VARCHAR(20) NOT NULL DEFAULT 'text'" );
		self::add_column_if_missing( $messages, 'media_mime', 'VARCHAR(100) NULL' );
		self::add_column_if_missing( $messages, 'delivery_status', 'VARCHAR(20) NULL' );

		// Reemplazar KEY channel_contact no-único por UNIQUE si aplica.
		self::drop_index_if_exists( $conversations, 'channel_contact' );
		self::add_unique_if_missing( $conversations, 'channel_contact_unique', 'channel, external_contact_id' );

		self::add_index_if_missing( $conversations, 'assigned_to', 'assigned_to' );
		self::add_index_if_missing( $conversations, 'last_message_at', 'last_message_at' );
		self::add_index_if_missing( $conversations, 'status_last_msg', 'status, last_message_at' );

		self::drop_index_if_exists( $messages, 'external_message_id' );
		self::add_unique_if_missing( $messages, 'external_message_id_unique', 'external_message_id' );

		// Backfill suave.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "UPDATE {$conversations} SET unread_count = 0 WHERE unread_count IS NULL" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "UPDATE {$conversations} SET updated_at = created_at WHERE updated_at IS NULL AND created_at IS NOT NULL" );
	}

	/**
	 * DB v3 (D-23 Fase 2): tabla de leads + columna lead_id en conversations.
	 */
	public static function upgrade_to_3(): void {
		global $wpdb;

		Vitacare_Crm_Activator::install_tables_v3();

		$conversations = $wpdb->prefix . 'vitacare_crm_conversations';
		self::add_column_if_missing( $conversations, 'lead_id', 'BIGINT UNSIGNED NULL' );
		self::add_index_if_missing( $conversations, 'lead_id', 'lead_id' );
	}

	/**
	 * DB v4 (D-25 Fase 3): tabla de enlaces con seguimiento propio.
	 */
	public static function upgrade_to_4(): void {
		Vitacare_Crm_Activator::install_tables_v4();
	}

	/**
	 * DB v5 (D-26 Fase 4): campañas de correo con opt-in.
	 */
	public static function upgrade_to_5(): void {
		Vitacare_Crm_Activator::install_tables_v5();
	}

	public static function ensure_caps(): void {
		Vitacare_Crm_Activator::ensure_capability();
	}

	private static function add_column_if_missing( string $table, string $column, string $definition ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$col = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", $column ) );
		if ( ! empty( $col ) ) {
			return;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}" );
	}

	private static function drop_index_if_exists( string $table, string $index ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$found = $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM `{$table}` WHERE Key_name = %s", $index ) );
		if ( empty( $found ) ) {
			return;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "ALTER TABLE `{$table}` DROP INDEX `{$index}`" );
	}

	private static function add_unique_if_missing( string $table, string $index, string $columns_sql ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$found = $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM `{$table}` WHERE Key_name = %s", $index ) );
		if ( ! empty( $found ) ) {
			return;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "ALTER TABLE `{$table}` ADD UNIQUE KEY `{$index}` ({$columns_sql})" );
	}

	private static function add_index_if_missing( string $table, string $index, string $columns_sql ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$found = $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM `{$table}` WHERE Key_name = %s", $index ) );
		if ( ! empty( $found ) ) {
			return;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "ALTER TABLE `{$table}` ADD KEY `{$index}` ({$columns_sql})" );
	}
}

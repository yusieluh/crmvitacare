<?php
defined( 'ABSPATH' ) || exit;

final class Vitacare_Crm_Activator {

	public static function activate(): void {
		// Instalación limpia = esquema actual (v2).
		self::install_tables_v2();
		self::ensure_capability();
		self::install_page();
		update_option( 'vitacare_crm_db_version', VITACARE_CRM_DB_VERSION, false );
	}

	public static function deactivate(): void {
		// No se borra la página ni las tablas al desactivar.
	}

	/**
	 * Esquema v1 (base) — usado por el upgrader paso a paso.
	 */
	public static function install_tables_v1(): void {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$conversations    = $wpdb->prefix . 'vitacare_crm_conversations';
		$messages         = $wpdb->prefix . 'vitacare_crm_messages';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta(
			"CREATE TABLE {$conversations} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				channel VARCHAR(20) NOT NULL,
				external_contact_id VARCHAR(191) NOT NULL,
				contact_name VARCHAR(191) NULL,
				contact_phone VARCHAR(32) NULL,
				wp_user_id BIGINT UNSIGNED NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'open',
				assigned_to BIGINT UNSIGNED NULL,
				last_message_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY channel_contact (channel, external_contact_id),
				KEY status (status),
				KEY wp_user_id (wp_user_id)
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$messages} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				conversation_id BIGINT UNSIGNED NOT NULL,
				direction VARCHAR(10) NOT NULL,
				sender_type VARCHAR(20) NOT NULL,
				body LONGTEXT NULL,
				media_url VARCHAR(500) NULL,
				external_message_id VARCHAR(191) NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY conversation_id (conversation_id),
				KEY external_message_id (external_message_id)
			) {$charset_collate};"
		);
	}

	/**
	 * Esquema v2 (dbDelta idempotente): columnas e índices de PR-2.
	 */
	public static function install_tables_v2(): void {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$conversations    = $wpdb->prefix . 'vitacare_crm_conversations';
		$messages         = $wpdb->prefix . 'vitacare_crm_messages';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta(
			"CREATE TABLE {$conversations} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				channel VARCHAR(20) NOT NULL,
				external_contact_id VARCHAR(191) NOT NULL,
				contact_name VARCHAR(191) NULL,
				contact_phone VARCHAR(32) NULL,
				wp_user_id BIGINT UNSIGNED NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'open',
				assigned_to BIGINT UNSIGNED NULL,
				last_message_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				unread_count INT UNSIGNED NOT NULL DEFAULT 0,
				updated_at DATETIME NULL,
				meta LONGTEXT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY channel_contact_unique (channel, external_contact_id),
				KEY status (status),
				KEY wp_user_id (wp_user_id),
				KEY assigned_to (assigned_to),
				KEY last_message_at (last_message_at),
				KEY status_last_msg (status, last_message_at)
			) {$charset_collate};"
		);

		dbDelta(
			"CREATE TABLE {$messages} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				conversation_id BIGINT UNSIGNED NOT NULL,
				direction VARCHAR(10) NOT NULL,
				sender_type VARCHAR(20) NOT NULL,
				message_type VARCHAR(20) NOT NULL DEFAULT 'text',
				body LONGTEXT NULL,
				media_url VARCHAR(500) NULL,
				media_mime VARCHAR(100) NULL,
				external_message_id VARCHAR(191) NULL,
				delivery_status VARCHAR(20) NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY conversation_id (conversation_id),
				UNIQUE KEY external_message_id_unique (external_message_id)
			) {$charset_collate};"
		);
	}

	/**
	 * Alias usado por código legacy del upgrader.
	 */
	public static function install_tables(): void {
		self::install_tables_v2();
		update_option( 'vitacare_crm_db_version', VITACARE_CRM_DB_VERSION, false );
	}

	public static function ensure_capability(): void {
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( VITACARE_CRM_CAPABILITY ) ) {
			$admin->add_cap( VITACARE_CRM_CAPABILITY );
		}
	}

	private static function install_page(): void {
		$existing = get_page_by_path( VITACARE_CRM_PAGE_SLUG );
		if ( $existing instanceof WP_Post ) {
			return;
		}
		wp_insert_post(
			array(
				'post_title'     => 'CRM',
				'post_name'      => VITACARE_CRM_PAGE_SLUG,
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'post_content'   => '',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			)
		);
	}
}

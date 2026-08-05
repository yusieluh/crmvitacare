<?php
defined( 'ABSPATH' ) || exit;

/**
 * Respaldo/restauración manual de las opciones de integraciones Meta del CRM.
 * Copia los valores tal como están guardados en wp_options (sin desencriptar
 * secretos, sin loguear su contenido) a un archivo fuera del webroot público,
 * reusando el mismo directorio privado que ya usa vitacare-core.
 */
final class Vitacare_Crm_Backup {

	private const OPTION_KEYS = array(
		'vitacare_crm_meta_app_id',
		'vitacare_crm_meta_app_secret',
		'vitacare_crm_meta_access_token',
		'vitacare_crm_meta_verify_token',
		'vitacare_crm_wa_phone_number_id',
		'vitacare_crm_wa_waba_id',
		'vitacare_crm_graph_version',
		'vitacare_crm_outbound_soft_limit',
		'vitacare_crm_feature_whatsapp',
		'vitacare_crm_feature_facebook',
		'vitacare_crm_feature_instagram',
		'vitacare_crm_feature_email',
		'vitacare_crm_debug_log',
		'vitacare_crm_tiktok_client_key',
		'vitacare_crm_tiktok_client_secret',
		'vitacare_crm_fb_page_id',
		'vitacare_crm_fb_page_name',
		'vitacare_crm_fb_page_token',
		'vitacare_crm_fb_ig_id',
		'vitacare_crm_fb_ig_username',
		'vitacare_crm_fb_connected_at',
		'vitacare_crm_fb_user_token',
		'vitacare_crm_fb_pages_cache',
		'vitacare_crm_db_version',
	);

	public static function dir(): string {
		$base = defined( 'VITACARE_PRIVATE_STORAGE_DIR' ) && VITACARE_PRIVATE_STORAGE_DIR !== ''
			? rtrim( (string) VITACARE_PRIVATE_STORAGE_DIR, '/' )
			: dirname( ABSPATH ) . '/vitacare-private';
		return $base . '/crm-integration-backups';
	}

	private static function ensure_dir(): void {
		$dir = self::dir();
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	/**
	 * @return array{path:string,file:string,bytes:int,keys:int}|WP_Error
	 */
	public static function export_now() {
		self::ensure_dir();
		$dir = self::dir();

		$data = array();
		foreach ( self::OPTION_KEYS as $opt ) {
			$data[ $opt ] = get_option( $opt, '' );
		}

		$payload = wp_json_encode(
			array(
				'created_at'  => current_time( 'mysql' ),
				'db_version'  => (string) get_option( 'vitacare_crm_db_version', '' ),
				'plugin_version' => defined( 'VITACARE_CRM_VERSION' ) ? VITACARE_CRM_VERSION : '',
				'options'     => $data,
			),
			JSON_PRETTY_PRINT
		);

		if ( false === $payload ) {
			return new WP_Error( 'vitacare_crm_backup_encode', __( 'No se pudo serializar el respaldo.', 'vitacare-crm' ) );
		}

		$file = 'backup-' . gmdate( 'Ymd-His' ) . '.json';
		$path = $dir . '/' . $file;

		$bytes = file_put_contents( $path, $payload ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === $bytes ) {
			return new WP_Error( 'vitacare_crm_backup_write', __( 'No se pudo escribir el archivo de respaldo.', 'vitacare-crm' ) );
		}

		return array(
			'path'  => $path,
			'file'  => $file,
			'bytes' => (int) $bytes,
			'keys'  => count( $data ),
		);
	}

	/**
	 * @return array<int, array{file:string,bytes:int,created_at:string}>
	 */
	public static function list_backups(): array {
		$dir = self::dir();
		if ( ! is_dir( $dir ) ) {
			return array();
		}
		$out = array();
		foreach ( glob( $dir . '/backup-*.json' ) ?: array() as $path ) {
			$out[] = array(
				'file'       => basename( $path ),
				'bytes'      => (int) filesize( $path ),
				'created_at' => gmdate( 'Y-m-d H:i:s', (int) filemtime( $path ) ),
			);
		}
		usort( $out, static fn( $a, $b ) => strcmp( $b['file'], $a['file'] ) );
		return $out;
	}

	/**
	 * Restaura un respaldo puntual. $file debe ser solo el basename (sin rutas).
	 *
	 * @return array{keys:int}|WP_Error
	 */
	public static function restore( string $file ) {
		$file = basename( $file );
		if ( ! preg_match( '/^backup-\d{8}-\d{6}\.json$/', $file ) ) {
			return new WP_Error( 'vitacare_crm_backup_invalid_name', __( 'Nombre de respaldo inválido.', 'vitacare-crm' ) );
		}
		$path = self::dir() . '/' . $file;
		if ( ! is_file( $path ) ) {
			return new WP_Error( 'vitacare_crm_backup_not_found', __( 'Ese respaldo no existe.', 'vitacare-crm' ) );
		}
		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents
		$json = is_string( $raw ) ? json_decode( $raw, true ) : null;
		if ( ! is_array( $json ) || ! isset( $json['options'] ) || ! is_array( $json['options'] ) ) {
			return new WP_Error( 'vitacare_crm_backup_corrupt', __( 'El archivo de respaldo no es válido.', 'vitacare-crm' ) );
		}

		$n = 0;
		foreach ( self::OPTION_KEYS as $opt ) {
			if ( ! array_key_exists( $opt, $json['options'] ) ) {
				continue;
			}
			update_option( $opt, $json['options'][ $opt ], false );
			++$n;
		}

		return array( 'keys' => $n );
	}
}

<?php
defined( 'ABSPATH' ) || exit;

/**
 * Logger simple bajo uploads/vitacare-crm/logs (deny directo + sin tokens).
 */
final class Vitacare_Crm_Logger {

	public static function debug( string $message, array $context = array() ): void {
		if ( ! class_exists( 'Vitacare_Crm_Settings' ) || ! Vitacare_Crm_Settings::debug_enabled() ) {
			return;
		}
		self::write( 'debug', $message, $context );
	}

	public static function info( string $message, array $context = array() ): void {
		self::write( 'info', $message, $context );
	}

	public static function error( string $message, array $context = array() ): void {
		self::write( 'error', $message, $context );
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private static function write( string $level, string $message, array $context ): void {
		$dir = self::ensure_log_dir();
		if ( ! $dir ) {
			return;
		}

		// Redactar claves sensibles.
		foreach ( array( 'access_token', 'app_secret', 'Authorization', 'token', 'secret' ) as $k ) {
			if ( isset( $context[ $k ] ) ) {
				$context[ $k ] = '[redacted]';
			}
		}

		$line = sprintf(
			"[%s] %s %s %s\n",
			gmdate( 'c' ),
			strtoupper( $level ),
			$message,
			$context ? wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : ''
		);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		@file_put_contents( $dir . '/crm-' . gmdate( 'Y-m' ) . '.log', $line, FILE_APPEND | LOCK_EX );
	}

	private static function ensure_log_dir(): string {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return '';
		}
		$base = trailingslashit( $upload['basedir'] ) . 'vitacare-crm';
		$logs = $base . '/logs';

		if ( ! is_dir( $logs ) ) {
			wp_mkdir_p( $logs );
		}
		self::protect_dir( $base );
		self::protect_dir( $logs );

		return is_dir( $logs ) && is_writable( $logs ) ? $logs : '';
	}

	private static function protect_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
		$ht = $dir . '/.htaccess';
		if ( ! file_exists( $ht ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $ht, "Require all denied\n" );
		}
	}
}

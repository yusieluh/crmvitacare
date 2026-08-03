<?php
defined( 'ABSPATH' ) || exit;

/**
 * PR-6: almacenamiento opaco de media (WhatsApp / Facebook Messenger /
 * Instagram). Los archivos se guardan fuera del alcance HTTP directo
 * (deny-all vía .htaccess) y solo se sirven a través del endpoint REST
 * protegido por la capability del CRM. Nunca se exponen URLs de Meta ni
 * rutas de disco al cliente.
 */
final class Vitacare_Crm_Media {

	/** Tope duro por archivo para no agotar memoria/disco en hosting compartido. */
	private const MAX_BYTES = 25 * 1024 * 1024; // 25 MB

	private const EXT_MAP = array(
		'image/jpeg'                                                             => 'jpg',
		'image/png'                                                              => 'png',
		'image/gif'                                                              => 'gif',
		'image/webp'                                                             => 'webp',
		'audio/ogg'                                                              => 'ogg',
		'audio/opus'                                                             => 'opus',
		'audio/mpeg'                                                             => 'mp3',
		'audio/mp4'                                                              => 'm4a',
		'audio/aac'                                                              => 'aac',
		'audio/amr'                                                              => 'amr',
		'video/mp4'                                                              => 'mp4',
		'video/3gpp'                                                             => '3gp',
		'application/pdf'                                                        => 'pdf',
		'application/msword'                                                     => 'doc',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
		'application/vnd.ms-excel'                                               => 'xls',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'      => 'xlsx',
		'application/vnd.ms-powerpoint'                                          => 'ppt',
		'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
		'text/plain'                                                             => 'txt',
	);

	/**
	 * Directorio privado (crea protección deny-all la primera vez).
	 */
	public static function base_dir(): string {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'vitacare-crm-media';
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
		$ht = $dir . '/.htaccess';
		if ( ! file_exists( $ht ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents(
				$ht,
				"<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n"
			);
		}
		return $dir;
	}

	public static function is_local_ref( ?string $ref ): bool {
		return is_string( $ref ) && str_starts_with( $ref, 'local:' );
	}

	/**
	 * Resuelve un ref `local:YYYY/MM/archivo.ext` a una ruta de disco real,
	 * validando que quede dentro de base_dir() (anti path-traversal).
	 */
	public static function resolve_path( ?string $ref ): ?string {
		if ( ! self::is_local_ref( $ref ) ) {
			return null;
		}
		$rest = substr( (string) $ref, 6 );
		if ( ! preg_match( '#^(\d{4})/(\d{2})/([A-Za-z0-9._-]+)$#', $rest ) ) {
			return null;
		}
		$base = self::base_dir();
		$path = $base . '/' . $rest;
		$real = realpath( $path );
		$real_base = realpath( $base );
		if ( false === $real || false === $real_base || strpos( $real, $real_base ) !== 0 ) {
			return null;
		}
		return $real;
	}

	public static function public_media_url( int $message_id ): string {
		return rest_url( 'vitacare-crm/v1/media/' . $message_id );
	}

	/**
	 * Descarga un media de WhatsApp Cloud API por su media id (dos pasos:
	 * resolver URL temporal, luego descargar con el mismo token).
	 *
	 * @return array{ref: string, mime: string, path: string}|WP_Error
	 */
	public static function download_whatsapp_media( string $media_id ) {
		$media_id = trim( $media_id );
		if ( $media_id === '' ) {
			return new WP_Error( 'vitacare_crm_media', __( 'media_id vacío.', 'vitacare-crm' ) );
		}
		$token = Vitacare_Crm_Settings::get( 'access_token' );
		if ( $token === '' ) {
			return new WP_Error( 'vitacare_crm_media', __( 'Falta Access Token para descargar media de WhatsApp.', 'vitacare-crm' ) );
		}

		$version = Vitacare_Crm_Settings::graph_version();
		$url     = 'https://graph.facebook.com/' . rawurlencode( $version ) . '/' . rawurlencode( $media_id );
		$ua      = 'VITACARE-CRM/' . VITACARE_CRM_VERSION;

		$info = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'User-Agent'    => $ua,
				),
			)
		);
		if ( is_wp_error( $info ) ) {
			return $info;
		}
		$code = (int) wp_remote_retrieve_response_code( $info );
		$data = json_decode( (string) wp_remote_retrieve_body( $info ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) || empty( $data['url'] ) ) {
			return new WP_Error( 'vitacare_crm_media', __( 'No se pudo resolver el enlace de descarga de WhatsApp.', 'vitacare-crm' ) );
		}

		$mime_hint    = isset( $data['mime_type'] ) ? self::clean_mime( (string) $data['mime_type'] ) : '';
		$target       = self::reserve_path( self::extension_for_mime( $mime_hint ) );
		$download_url = (string) $data['url'];

		$dl = wp_remote_get(
			$download_url,
			array(
				'timeout'             => 25,
				'headers'             => array(
					'Authorization' => 'Bearer ' . $token,
					'User-Agent'    => $ua,
				),
				'stream'              => true,
				'filename'            => $target['path'],
				'limit_response_size' => self::MAX_BYTES,
			)
		);

		return self::finalize_download( $dl, $target, $mime_hint );
	}

	/**
	 * Descarga un adjunto de Messenger/Instagram (URL de CDN de Meta, sin auth).
	 *
	 * @return array{ref: string, mime: string, path: string}|WP_Error
	 */
	public static function download_remote_media( string $url ) {
		$url = trim( $url );
		if ( $url === '' || ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'vitacare_crm_media', __( 'URL de adjunto inválida.', 'vitacare-crm' ) );
		}

		$target = self::reserve_path( 'bin' );

		$dl = wp_remote_get(
			$url,
			array(
				'timeout'             => 25,
				'headers'             => array( 'User-Agent' => 'VITACARE-CRM/' . VITACARE_CRM_VERSION ),
				'stream'              => true,
				'filename'            => $target['path'],
				'limit_response_size' => self::MAX_BYTES,
			)
		);

		return self::finalize_download( $dl, $target, '' );
	}

	/**
	 * @param array<string, mixed>|WP_Error $response
	 * @param array{path: string, ref: string} $target
	 * @return array{ref: string, mime: string, path: string}|WP_Error
	 */
	private static function finalize_download( $response, array $target, string $mime_hint ) {
		if ( is_wp_error( $response ) ) {
			self::cleanup( $target['path'] );
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			self::cleanup( $target['path'] );
			return new WP_Error(
				'vitacare_crm_media',
				sprintf(
					/* translators: %d: HTTP status */
					__( 'Descarga de media falló (HTTP %d).', 'vitacare-crm' ),
					$code
				)
			);
		}
		if ( ! file_exists( $target['path'] ) || filesize( $target['path'] ) <= 0 ) {
			self::cleanup( $target['path'] );
			return new WP_Error( 'vitacare_crm_media', __( 'Archivo descargado vacío o ilegible.', 'vitacare-crm' ) );
		}

		$mime = self::clean_mime( (string) wp_remote_retrieve_header( $response, 'content-type' ) );
		if ( $mime === '' ) {
			$mime = $mime_hint !== '' ? $mime_hint : 'application/octet-stream';
		}

		return array(
			'ref'  => $target['ref'],
			'mime' => $mime,
			'path' => $target['path'],
		);
	}

	/**
	 * @return array{path: string, ref: string}
	 */
	private static function reserve_path( string $ext ): array {
		$rel_dir = gmdate( 'Y' ) . '/' . gmdate( 'm' );
		$dir     = self::base_dir() . '/' . $rel_dir;
		wp_mkdir_p( $dir );

		$ext  = strtolower( preg_replace( '/[^a-z0-9]/i', '', $ext ) ?: '' );
		$ext  = $ext !== '' ? $ext : 'bin';
		$name = wp_generate_uuid4() . '.' . $ext;

		return array(
			'path' => $dir . '/' . $name,
			'ref'  => 'local:' . $rel_dir . '/' . $name,
		);
	}

	private static function clean_mime( string $raw ): string {
		$parts = explode( ';', $raw );
		return trim( $parts[0] ?? '' );
	}

	private static function extension_for_mime( string $mime ): string {
		if ( $mime !== '' && isset( self::EXT_MAP[ $mime ] ) ) {
			return self::EXT_MAP[ $mime ];
		}
		return 'bin';
	}

	private static function cleanup( string $path ): void {
		if ( $path !== '' && file_exists( $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink
			@unlink( $path );
		}
	}
}

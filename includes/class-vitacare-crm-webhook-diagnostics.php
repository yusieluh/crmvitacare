<?php
defined( 'ABSPATH' ) || exit;

/**
 * Auditoría no sensible de ingesta del webhook Meta (D-31). Guarda como
 * máximo los últimos MAX_ENTRIES eventos en una sola opción de WordPress,
 * nunca en una tabla nueva ni en un log de archivo. Nunca almacena: nombres
 * completos, texto del mensaje, tokens, App Secret, firma completa, PSID/
 * Page ID completos ni cualquier otro dato sensible -- solo metadata de
 * clasificación y resultado (creado sí/no, motivo de descarte, código HTTP).
 */
final class Vitacare_Crm_Webhook_Diagnostics {

	private const OPTION      = 'vitacare_crm_webhook_diag';
	private const MAX_ENTRIES = 20;

	/**
	 * @param array<string, mixed> $entry
	 */
	public static function record( array $entry ): void {
		$entry['at'] = current_time( 'mysql' );

		$list = self::all();
		array_unshift( $list, $entry );
		if ( count( $list ) > self::MAX_ENTRIES ) {
			$list = array_slice( $list, 0, self::MAX_ENTRIES );
		}
		update_option( self::OPTION, $list, false );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function all(): array {
		$list = get_option( self::OPTION, array() );
		return is_array( $list ) ? $list : array();
	}

	public static function clear(): void {
		delete_option( self::OPTION );
	}

	/**
	 * Enmascara un identificador externo (Page ID, PSID, etc.) mostrando solo
	 * los primeros y últimos 3 caracteres. Nunca se guarda ni se muestra el
	 * valor completo en este diagnóstico.
	 */
	public static function mask_id( string $id ): string {
		$id = trim( $id );
		if ( $id === '' ) {
			return '';
		}
		$len = strlen( $id );
		if ( $len <= 6 ) {
			return str_repeat( '•', $len );
		}
		return substr( $id, 0, 3 ) . str_repeat( '•', max( 3, $len - 6 ) ) . substr( $id, -3 );
	}
}

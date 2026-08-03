<?php
defined( 'ABSPATH' ) || exit;

/**
 * Migraciones de esquema y reparación de capabilities en cada carga.
 * Cubre deploys por ZIP sin re-activar el plugin.
 */
final class Vitacare_Crm_Upgrader {

	public static function init(): void {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ), 5 );
		add_action( 'plugins_loaded', array( __CLASS__, 'ensure_caps' ), 6 );
	}

	/**
	 * Aplica pasos de esquema si la option está por debajo de VITACARE_CRM_DB_VERSION.
	 * DB v1: tablas base (idempotente vía dbDelta).
	 */
	public static function maybe_upgrade(): void {
		$current = (string) get_option( 'vitacare_crm_db_version', '0' );
		if ( version_compare( $current, (string) VITACARE_CRM_DB_VERSION, '>=' ) ) {
			return;
		}

		Vitacare_Crm_Activator::install_tables();
		// install_tables ya actualiza vitacare_crm_db_version.
	}

	/**
	 * Re-aplica vitacare_crm_access al rol administrator (y futuros roles).
	 */
	public static function ensure_caps(): void {
		Vitacare_Crm_Activator::ensure_capability();
	}
}

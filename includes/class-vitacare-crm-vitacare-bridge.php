<?php
defined( 'ABSPATH' ) || exit;

/**
 * Puente de SOLO LECTURA hacia los datos de VITACARE (mismo WordPress/misma
 * base de datos que vitacare-core, pero sin depender de sus clases ni
 * tocar ninguno de sus archivos -- consultas SQL directas contra sus
 * tablas `wp_vitacare_*`, coherente con D-02 de ESTADO_CRM.md ("no
 * modificar vitacare-core; solo lectura de datos WP cuando haga falta").
 *
 * Nunca escribe en tablas de vitacare-core. Si vitacare-core no está
 * instalado (tablas ausentes), todo aquí se degrada a "sin match" en vez
 * de fallar, para que el CRM siga funcionando de forma independiente.
 */
final class Vitacare_Crm_Vitacare_Bridge {

	/** @var array<string, bool> Cache de existencia de tabla por request. */
	private static array $table_exists_cache = array();

	private static function table_exists( string $table ): bool {
		if ( isset( self::$table_exists_cache[ $table ] ) ) {
			return self::$table_exists_cache[ $table ];
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		self::$table_exists_cache[ $table ] = $exists;
		return $exists;
	}

	/**
	 * Punto de entrada: a partir de una conversación del CRM (channel +
	 * contact_phone + external_contact_id), intenta identificar si el
	 * contacto es un usuario real de VITACARE. Devuelve null si no hay
	 * match o si vitacare-core no está instalado -- nunca lanza error (el
	 * hilo del CRM debe seguir funcionando igual sin este dato).
	 *
	 * @param array<string, mixed> $conversation Fila de conversación ya formateada (ver Conversations_Repo::get()).
	 */
	public static function lookup_for_conversation( array $conversation ): ?array {
		if ( ! self::table_exists( self::users_table() ) ) {
			return null; // WordPress sin usuarios reales -- no deberia pasar, pero por si acaso.
		}
		$channel = (string) ( $conversation['channel'] ?? '' );
		$user    = null;
		if ( 'email' === $channel ) {
			$email = (string) ( $conversation['external_contact_id'] ?? '' );
			if ( '' !== $email && is_email( $email ) ) {
				$user = get_user_by( 'email', $email );
			}
		} else {
			$phone = (string) ( $conversation['contact_phone'] ?? '' );
			if ( '' !== $phone ) {
				$user = self::find_user_by_phone( $phone );
			}
		}
		if ( ! $user instanceof WP_User ) {
			return null;
		}
		return self::build_profile( $user );
	}

	private static function users_table(): string {
		global $wpdb;
		return $wpdb->users;
	}

	private static function profiles_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'vitacare_profiles';
	}

	/**
	 * Coincidencia por teléfono normalizada: se compara solo por los
	 * últimos 9 dígitos (número significativo nacional de Ecuador), porque
	 * `wp_vitacare_profiles.phone` es texto libre sin formato garantizado
	 * (con/sin +593, espacios, guiones) y los contactos de Meta llegan en
	 * formato E.164 sin "+". Comparar el sufijo evita falsos negativos por
	 * formato sin perder demasiada precision (9 digitos es suficiente
	 * entropia para un numero movil real).
	 */
	private static function find_user_by_phone( string $raw_phone ): ?WP_User {
		$digits = preg_replace( '/\D+/', '', $raw_phone );
		if ( null === $digits || strlen( $digits ) < 8 ) {
			return null;
		}
		$suffix = substr( $digits, -9 );
		if ( ! self::table_exists( self::profiles_table() ) ) {
			return null;
		}
		global $wpdb;
		$table   = self::profiles_table();
		$user_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT user_id FROM {$table} WHERE phone IS NOT NULL AND phone <> '' AND REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'+','') LIKE %s ORDER BY user_id ASC LIMIT 1",
				'%' . $wpdb->esc_like( $suffix )
			)
		);
		if ( ! $user_id ) {
			return null;
		}
		$user = get_user_by( 'id', $user_id );
		return $user instanceof WP_User ? $user : null;
	}

	/**
	 * Ficha resumida de solo lectura: identidad + hasta 5 citas recientes +
	 * membresía activa (si existe) + total pendiente de pago. Cada tabla se
	 * consulta solo si existe (instalaciones sin vitacare-core, o versiones
	 * mas viejas sin alguna tabla, no rompen esto).
	 */
	private static function build_profile( WP_User $user ): array {
		global $wpdb;
		$profile = array(
			'user_id'                => (int) $user->ID,
			'name'                   => $user->display_name,
			'email'                  => $user->user_email,
			'roles'                  => array_values( $user->roles ),
			'appointments'           => array(),
			'membership'             => null,
			'pending_payments_minor' => 0,
		);

		$appointments_table = $wpdb->prefix . 'vitacare_appointments';
		if ( self::table_exists( $appointments_table ) ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT id, service_code, status, payment_status, scheduled_at FROM {$appointments_table} WHERE user_id = %d ORDER BY scheduled_at DESC LIMIT 5",
					$user->ID
				),
				ARRAY_A
			);
			foreach ( (array) $rows as $row ) {
				$profile['appointments'][] = array(
					'id'             => (int) $row['id'],
					'service_code'   => (string) $row['service_code'],
					'status'         => (string) $row['status'],
					'payment_status' => (string) $row['payment_status'],
					'scheduled_at'   => str_replace( ' ', 'T', (string) $row['scheduled_at'] ),
				);
			}
		}

		$membership_orders_table = $wpdb->prefix . 'vitacare_membership_orders';
		$memberships_table       = $wpdb->prefix . 'vitacare_memberships';
		if ( self::table_exists( $membership_orders_table ) && self::table_exists( $memberships_table ) ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT mo.status, mo.starts_at, mo.ends_at, m.name AS membership_name
					 FROM {$membership_orders_table} mo
					 INNER JOIN {$memberships_table} m ON m.id = mo.membership_id
					 WHERE mo.user_id = %d AND mo.status = 'active'
					 ORDER BY mo.id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user->ID
				),
				ARRAY_A
			);
			if ( $row ) {
				$profile['membership'] = array(
					'name'      => (string) $row['membership_name'],
					'status'    => (string) $row['status'],
					'starts_at' => $row['starts_at'] ? str_replace( ' ', 'T', (string) $row['starts_at'] ) : null,
					'ends_at'   => $row['ends_at'] ? str_replace( ' ', 'T', (string) $row['ends_at'] ) : null,
				);
			}
		}

		$payments_table = $wpdb->prefix . 'vitacare_payments';
		if ( self::table_exists( $payments_table ) ) {
			$profile['pending_payments_minor'] = (int) $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT COALESCE(SUM(amount_minor),0) FROM {$payments_table} WHERE user_id = %d AND status IN ('pending','awaiting_review','awaiting_proof')",
					$user->ID
				)
			);
		}

		return $profile;
	}
}

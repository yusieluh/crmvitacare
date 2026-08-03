<?php
defined( 'ABSPATH' ) || exit;

/**
 * Renderiza /crm reutilizando header/footer del tema activo
 * (get_header()/get_footer()), sin modificar vitacare-theme.
 * Solo afecta la página con slug `crm` — no la raíz del sitio.
 */
final class Vitacare_Crm_Page {

	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'gate_access' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'send_noindex_headers' ), 1 );
		add_filter( 'template_include', array( __CLASS__, 'maybe_override_template' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'wp_robots', array( __CLASS__, 'filter_wp_robots' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'exclude_from_sitemaps' ), 10, 2 );
	}

	public static function is_crm_page(): bool {
		return is_page( VITACARE_CRM_PAGE_SLUG );
	}

	public static function user_can_access(): bool {
		return current_user_can( VITACARE_CRM_CAPABILITY );
	}

	/**
	 * Login obligatorio; sin capability no se consultan tablas CRM.
	 */
	public static function gate_access(): void {
		if ( ! self::is_crm_page() ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			auth_redirect();
			exit;
		}

		// Usuario logueado sin cap: se muestra plantilla de denegación (sin SQL en shell).
	}

	public static function send_noindex_headers(): void {
		if ( ! self::is_crm_page() ) {
			return;
		}
		if ( ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, nofollow', true );
		}
	}

	/**
	 * @param array<string, bool|string> $robots
	 * @return array<string, bool|string>
	 */
	public static function filter_wp_robots( array $robots ): array {
		if ( self::is_crm_page() ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	/**
	 * Excluye la página CRM del sitemap nativo de WordPress.
	 *
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	public static function exclude_from_sitemaps( array $args, string $post_type ): array {
		if ( 'page' !== $post_type ) {
			return $args;
		}
		$page = get_page_by_path( VITACARE_CRM_PAGE_SLUG );
		if ( ! $page instanceof WP_Post ) {
			return $args;
		}
		$not_in = isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array();
		$not_in[] = (int) $page->ID;
		$args['post__not_in'] = array_unique( array_map( 'intval', $not_in ) );
		return $args;
	}

	public static function maybe_override_template( string $template ): string {
		if ( ! self::is_crm_page() ) {
			return $template;
		}
		$own_template = VITACARE_CRM_DIR . 'template-parts/crm-page.php';
		return is_readable( $own_template ) ? $own_template : $template;
	}

	public static function enqueue_assets(): void {
		if ( ! self::is_crm_page() ) {
			return;
		}
		// Defensa en profundidad: no encolar a anónimos (gate ya redirige).
		if ( ! is_user_logged_in() ) {
			return;
		}

		wp_enqueue_style( 'vitacare-crm', VITACARE_CRM_URL . 'assets/css/crm.css', array(), VITACARE_CRM_VERSION );
		wp_enqueue_script( 'vitacare-crm', VITACARE_CRM_URL . 'assets/js/crm.js', array(), VITACARE_CRM_VERSION, true );

		if ( self::user_can_access() ) {
			wp_localize_script(
				'vitacare-crm',
				'vitacareCrm',
				array(
					'restUrl'      => esc_url_raw( rest_url( 'vitacare-crm/v1' ) ),
					'restNonce'    => wp_create_nonce( 'wp_rest' ),
					'pollInterval' => 20000,
					'i18n'         => array(
						'emptyList'      => __( 'No hay conversaciones con estos filtros.', 'vitacare-crm' ),
						'emptyThread'    => __( 'Sin mensajes aún.', 'vitacare-crm' ),
						'errorLoad'      => __( 'No se pudo cargar la lista.', 'vitacare-crm' ),
						'errorThread'    => __( 'No se pudo cargar el hilo.', 'vitacare-crm' ),
						'errorGeneric'   => __( 'Error de red o permisos.', 'vitacare-crm' ),
						'outsideWindow'  => __( 'Fuera de la ventana de 24 horas. El cliente debe escribir primero.', 'vitacare-crm' ),
						'hintWa'         => __( 'Solo texto · ventana 24 h de WhatsApp', 'vitacare-crm' ),
						'hintFb'         => __( 'Messenger · ventana 24 h · solo texto', 'vitacare-crm' ),
						'hintChannel'    => __( 'Envío no disponible para este canal todavía.', 'vitacare-crm' ),
						'fromStaff'      => __( 'CRM', 'vitacare-crm' ),
						'fromApp'        => __( 'App', 'vitacare-crm' ),
						'statusOpen'     => __( 'Abierta', 'vitacare-crm' ),
						'statusPending'  => __( 'Pendiente', 'vitacare-crm' ),
						'statusClosed'   => __( 'Cerrada', 'vitacare-crm' ),
						'email'          => __( 'Correo', 'vitacare-crm' ),
						'noConfig'       => __( 'CRM no configurado (REST).', 'vitacare-crm' ),
					),
				)
			);
		}
	}
}

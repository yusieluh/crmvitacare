<?php
defined( 'ABSPATH' ) || exit;

/**
 * Renderiza /crm/ reutilizando el header/footer del tema activo
 * (get_header()/get_footer()), sin declarar ni modificar ningún archivo de
 * vitacare-theme. Así el CRM hereda la identidad visual VITACARE de forma
 * automática, incluso si el tema se actualiza después.
 */
final class Vitacare_Crm_Page {

	public static function init(): void {
		add_filter( 'template_include', array( __CLASS__, 'maybe_override_template' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	private static function is_crm_page(): bool {
		return is_page( VITACARE_CRM_PAGE_SLUG );
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
		wp_enqueue_style( 'vitacare-crm', VITACARE_CRM_URL . 'assets/css/crm.css', array(), VITACARE_CRM_VERSION );
		wp_enqueue_script( 'vitacare-crm', VITACARE_CRM_URL . 'assets/js/crm.js', array(), VITACARE_CRM_VERSION, true );
		wp_localize_script(
			'vitacare-crm',
			'vitacareCrm',
			array(
				'restUrl'   => esc_url_raw( rest_url( 'vitacare-crm/v1' ) ),
				'restNonce' => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
			)
		);
	}

	public static function user_can_access(): bool {
		return current_user_can( VITACARE_CRM_CAPABILITY );
	}
}

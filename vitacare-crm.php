<?php
/**
 * Plugin Name: VITACARE CRM
 * Plugin URI: https://vitacareec.org/
 * Description: Bandeja de conversaciones (WhatsApp, Facebook, Instagram, correo) y gestión de leads de VITACARE, en /crm/. Plugin independiente: no modifica vitacare-core ni vitacare-theme.
 * Version: 0.1.0
 * Requires at least: 7.0.2
 * Requires PHP: 8.1
 * Author: VITACARE Ecuador
 * Text Domain: vitacare-crm
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'VITACARE_CRM_VERSION', '0.1.0' );
define( 'VITACARE_CRM_DB_VERSION', '1' );
define( 'VITACARE_CRM_FILE', __FILE__ );
define( 'VITACARE_CRM_DIR', plugin_dir_path( __FILE__ ) );
define( 'VITACARE_CRM_URL', plugin_dir_url( __FILE__ ) );
define( 'VITACARE_CRM_PAGE_SLUG', 'crm' );
define( 'VITACARE_CRM_CAPABILITY', 'vitacare_crm_access' );

require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-activator.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-page.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-rest.php';

register_activation_hook( __FILE__, array( 'Vitacare_Crm_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Vitacare_Crm_Activator', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Vitacare_Crm_Page', 'init' ) );
add_action( 'rest_api_init', array( 'Vitacare_Crm_Rest', 'register_routes' ) );

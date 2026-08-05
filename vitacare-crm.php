<?php
/**
 * Plugin Name: VITACARE CRM
 * Plugin URI: https://vitacareec.org/crm
 * Description: Bandeja de conversaciones (WhatsApp, Facebook, Instagram, correo) y gestión de leads de VITACARE, en /crm. Plugin independiente: no modifica vitacare-core ni vitacare-theme ni la raíz del sitio.
 * Version: 1.15.4
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: VITACARE Ecuador
 * Text Domain: vitacare-crm
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'VITACARE_CRM_VERSION', '1.15.4' );
define( 'VITACARE_CRM_DB_VERSION', '5' );
define( 'VITACARE_CRM_FILE', __FILE__ );
define( 'VITACARE_CRM_DIR', plugin_dir_path( __FILE__ ) );
define( 'VITACARE_CRM_URL', plugin_dir_url( __FILE__ ) );
define( 'VITACARE_CRM_PAGE_SLUG', 'crm' );
define( 'VITACARE_CRM_CAPABILITY', 'vitacare_crm_access' );

require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-activator.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-upgrader.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-backup.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-settings.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-accounts.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-reports.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-leads-repo.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-leads.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-links-repo.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-links.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-email-campaigns-repo.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-email-campaigns.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-facebook-oauth.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-whatsapp-embedded-signup.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-integrations-page.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-tiktok-oauth.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-gmail.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-zoho.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-logger.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-db.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-graph.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-media.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-vitacare-bridge.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-conversations-repo.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-messages-repo.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-channel-whatsapp.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-channel-messenger.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-channel-instagram.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-page.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-webhook-diagnostics.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-webhook.php';
require_once VITACARE_CRM_DIR . 'includes/class-vitacare-crm-rest.php';

register_activation_hook( __FILE__, array( 'Vitacare_Crm_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Vitacare_Crm_Activator', 'deactivate' ) );

Vitacare_Crm_Upgrader::init();
add_action( 'plugins_loaded', array( 'Vitacare_Crm_Page', 'init' ) );
add_action( 'plugins_loaded', array( 'Vitacare_Crm_Settings', 'init' ) );
add_action( 'plugins_loaded', array( 'Vitacare_Crm_Accounts', 'init' ) );
add_action( 'plugins_loaded', array( 'Vitacare_Crm_Reports', 'init' ) );
add_action( 'plugins_loaded', array( 'Vitacare_Crm_Leads', 'init' ) );
add_action( 'plugins_loaded', array( 'Vitacare_Crm_Links', 'init' ) );
add_action( 'plugins_loaded', array( 'Vitacare_Crm_Email_Campaigns_Repo', 'init' ) );
add_action( 'plugins_loaded', array( 'Vitacare_Crm_Email_Campaigns', 'init' ) );
add_action( 'plugins_loaded', array( 'Vitacare_Crm_Facebook_Oauth', 'init' ) );
add_action( 'plugins_loaded', array( 'Vitacare_Crm_Whatsapp_Embedded_Signup', 'init' ) );
add_action( 'plugins_loaded', array( 'Vitacare_Crm_Integrations_Page', 'init' ) );
add_action( 'plugins_loaded', array( 'Vitacare_Crm_Tiktok_Oauth', 'init' ) );
add_action( 'plugins_loaded', array( 'Vitacare_Crm_Gmail', 'init' ) );
add_action( 'plugins_loaded', array( 'Vitacare_Crm_Zoho', 'init' ) );
add_action( 'rest_api_init', array( 'Vitacare_Crm_Rest', 'register_routes' ) );

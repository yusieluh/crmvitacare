<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// No se borran las tablas de conversaciones/mensajes ni la página /crm/ al
// desinstalar: son datos reales de negocio. Solo se limpia la opción de
// versión de esquema, para permitir una reinstalación limpia si hace falta.
delete_option( 'vitacare_crm_db_version' );

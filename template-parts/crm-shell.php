<?php
defined( 'ABSPATH' ) || exit;

if ( ! Vitacare_Crm_Page::user_can_access() ) {
	echo '<section class="section"><div class="container"><div class="vitacare-notice"><p>' . esc_html__( 'No tienes permiso para abrir el CRM.', 'vitacare-crm' ) . '</p></div></div></section>';
	return;
}

global $wpdb;
$conversations_table = $wpdb->prefix . 'vitacare_crm_conversations';
$channels             = array( 'whatsapp' => 'WhatsApp', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'email' => 'Correo' );
$open_by_channel      = array();
foreach ( $channels as $key => $label ) {
	$open_by_channel[ $key ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$conversations_table} WHERE channel = %s AND status = 'open'", $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
$total_open = array_sum( $open_by_channel );
?>
<section class="page-hero">
	<div class="container staff-top">
		<div>
			<div class="breadcrumb"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Inicio</a> / CRM</div>
			<h1>CRM VITACARE</h1>
			<p>Bandeja de conversaciones y gestión de leads (WhatsApp, Facebook, Instagram, correo).</p>
		</div>
	</div>
</section>
<section class="section" style="padding-top:1.25rem">
	<div class="container">
		<div class="metrics">
			<div class="metric"><div class="m-label">Conversaciones abiertas</div><div class="m-value"><?php echo esc_html( (string) $total_open ); ?></div></div>
			<?php foreach ( $channels as $key => $label ) : ?>
				<div class="metric"><div class="m-label"><?php echo esc_html( $label ); ?></div><div class="m-value"><?php echo esc_html( (string) $open_by_channel[ $key ] ); ?></div></div>
			<?php endforeach; ?>
		</div>
		<div class="card" style="margin-top:1.5rem">
			<h2 class="admin-section-title">Estado del módulo</h2>
			<p>Fase 1: estructura base del plugin, tablas de conversaciones/mensajes y página <code>/crm/</code> listas. Los canales (WhatsApp, Facebook, Instagram, correo) se conectan en las siguientes fases, una vez definidos los webhooks con Meta y las credenciales correspondientes.</p>
		</div>
	</div>
</section>

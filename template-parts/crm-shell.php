<?php
defined( 'ABSPATH' ) || exit;

// Defensa en profundidad: sin capability no se consulta la base CRM.
if ( ! Vitacare_Crm_Page::user_can_access() ) {
	echo '<section class="vcrm-section section"><div class="vcrm-container container"><div class="vcrm-notice vitacare-notice"><p>' . esc_html__( 'No tienes permiso para abrir el CRM.', 'vitacare-crm' ) . '</p></div></div></section>';
	return;
}

global $wpdb;
$conversations_table = $wpdb->prefix . 'vitacare_crm_conversations';
$channels             = array(
	'whatsapp'  => 'WhatsApp',
	'facebook'  => 'Facebook',
	'instagram' => 'Instagram',
	'email'     => 'Correo',
);
$open_by_channel = array();
foreach ( $channels as $key => $label ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix, not user input.
	$open_by_channel[ $key ] = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$conversations_table} WHERE channel = %s AND status = 'open'",
			$key
		)
	);
}
$total_open = array_sum( $open_by_channel );
?>
<section class="vcrm-hero page-hero">
	<div class="vcrm-container container staff-top">
		<div>
			<div class="vcrm-breadcrumb breadcrumb"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html__( 'Inicio', 'vitacare-crm' ); ?></a> / CRM</div>
			<h1><?php echo esc_html__( 'CRM VITACARE', 'vitacare-crm' ); ?></h1>
			<p><?php echo esc_html__( 'Bandeja de conversaciones y gestión de leads (WhatsApp, Facebook, Instagram, correo).', 'vitacare-crm' ); ?></p>
		</div>
	</div>
</section>
<section class="vcrm-section section" style="padding-top:1.25rem">
	<div class="vcrm-container container">
		<div class="vcrm-metrics metrics">
			<div class="vcrm-metric metric">
				<div class="vcrm-m-label m-label"><?php echo esc_html__( 'Conversaciones abiertas', 'vitacare-crm' ); ?></div>
				<div class="vcrm-m-value m-value"><?php echo esc_html( (string) $total_open ); ?></div>
			</div>
			<?php foreach ( $channels as $key => $label ) : ?>
				<div class="vcrm-metric metric">
					<div class="vcrm-m-label m-label"><?php echo esc_html( $label ); ?></div>
					<div class="vcrm-m-value m-value"><?php echo esc_html( (string) $open_by_channel[ $key ] ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>
		<div class="vcrm-card card" style="margin-top:1.5rem">
			<h2 class="admin-section-title"><?php echo esc_html__( 'Estado del módulo', 'vitacare-crm' ); ?></h2>
			<p>
				<?php
				echo esc_html__(
					'Fase 1H: estructura base, acceso restringido y página /crm listas. Los canales (WhatsApp, Facebook, Instagram, correo) se conectan en las siguientes fases.',
					'vitacare-crm'
				);
				?>
			</p>
			<p class="vcrm-muted">
				<?php
				printf(
					/* translators: %s: public CRM URL */
					esc_html__( 'URL del panel: %s — no modifica la raíz del sitio ni el sistema instalado.', 'vitacare-crm' ),
					esc_html( 'https://vitacareec.org/crm' )
				);
				?>
			</p>
		</div>
	</div>
</section>

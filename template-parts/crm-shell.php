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
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$open_by_channel[ $key ] = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$conversations_table} WHERE channel = %s AND status = 'open'",
			$key
		)
	);
}
$total_open = array_sum( $open_by_channel );
$current    = wp_get_current_user();
?>
<section class="vcrm-hero page-hero">
	<div class="vcrm-container container vcrm-topbar">
		<div>
			<div class="vcrm-breadcrumb breadcrumb"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html__( 'Inicio', 'vitacare-crm' ); ?></a> / CRM</div>
			<h1><?php echo esc_html__( 'CRM VITACARE', 'vitacare-crm' ); ?></h1>
			<p><?php echo esc_html__( 'Bandeja de conversaciones (WhatsApp y más canales).', 'vitacare-crm' ); ?></p>
		</div>
		<div class="vcrm-topbar-actions">
			<span class="vcrm-user"><?php echo esc_html( $current->display_name ? $current->display_name : $current->user_login ); ?></span>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<a class="vcrm-btn vcrm-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=vitacare-crm-settings' ) ); ?>">
					<?php echo esc_html__( 'Ajustes', 'vitacare-crm' ); ?>
				</a>
			<?php endif; ?>
			<a class="vcrm-btn vcrm-btn-ghost" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">
				<?php echo esc_html__( 'Salir', 'vitacare-crm' ); ?>
			</a>
		</div>
	</div>
</section>

<section class="vcrm-section section" style="padding-top:0.75rem">
	<div class="vcrm-container container">
		<div class="vcrm-metrics metrics" id="vcrm-metrics">
			<div class="vcrm-metric metric">
				<div class="vcrm-m-label m-label"><?php echo esc_html__( 'Conversaciones abiertas', 'vitacare-crm' ); ?></div>
				<div class="vcrm-m-value m-value" data-metric="total"><?php echo esc_html( (string) $total_open ); ?></div>
			</div>
			<?php foreach ( $channels as $key => $label ) : ?>
				<div class="vcrm-metric metric">
					<div class="vcrm-m-label m-label"><?php echo esc_html( $label ); ?></div>
					<div class="vcrm-m-value m-value" data-metric="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( (string) $open_by_channel[ $key ] ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="vcrm-inbox" id="vcrm-inbox" data-ready="0">
			<aside class="vcrm-inbox-list">
				<div class="vcrm-filters">
					<div class="vcrm-filter-group">
						<label class="vcrm-filter-label"><?php echo esc_html__( 'Estado', 'vitacare-crm' ); ?></label>
						<select id="vcrm-filter-status" class="vcrm-select">
							<option value="open" selected><?php echo esc_html__( 'Abiertas', 'vitacare-crm' ); ?></option>
							<option value="pending"><?php echo esc_html__( 'Pendientes', 'vitacare-crm' ); ?></option>
							<option value="closed"><?php echo esc_html__( 'Cerradas', 'vitacare-crm' ); ?></option>
							<option value="all"><?php echo esc_html__( 'Todas', 'vitacare-crm' ); ?></option>
						</select>
					</div>
					<div class="vcrm-filter-group">
						<label class="vcrm-filter-label"><?php echo esc_html__( 'Canal', 'vitacare-crm' ); ?></label>
						<select id="vcrm-filter-channel" class="vcrm-select">
							<option value=""><?php echo esc_html__( 'Todos', 'vitacare-crm' ); ?></option>
							<option value="whatsapp">WhatsApp</option>
							<option value="facebook">Facebook</option>
							<option value="instagram">Instagram</option>
							<option value="email"><?php echo esc_html__( 'Correo', 'vitacare-crm' ); ?></option>
						</select>
					</div>
					<div class="vcrm-filter-group vcrm-filter-search">
						<label class="vcrm-filter-label" for="vcrm-filter-q"><?php echo esc_html__( 'Buscar', 'vitacare-crm' ); ?></label>
						<input type="search" id="vcrm-filter-q" class="vcrm-input" placeholder="<?php echo esc_attr__( 'Nombre o teléfono…', 'vitacare-crm' ); ?>" autocomplete="off" />
					</div>
					<button type="button" class="vcrm-btn vcrm-btn-ghost" id="vcrm-refresh" title="<?php echo esc_attr__( 'Actualizar', 'vitacare-crm' ); ?>">↻</button>
				</div>
				<div class="vcrm-conv-list" id="vcrm-conv-list" role="listbox" aria-label="<?php echo esc_attr__( 'Conversaciones', 'vitacare-crm' ); ?>">
					<div class="vcrm-empty"><?php echo esc_html__( 'Cargando…', 'vitacare-crm' ); ?></div>
				</div>
			</aside>

			<main class="vcrm-inbox-thread">
				<div class="vcrm-thread-header" id="vcrm-thread-header">
					<div class="vcrm-thread-title">
						<span id="vcrm-thread-name"><?php echo esc_html__( 'Selecciona una conversación', 'vitacare-crm' ); ?></span>
						<span class="vcrm-chip" id="vcrm-thread-channel" hidden></span>
					</div>
					<div class="vcrm-thread-actions" id="vcrm-thread-actions" hidden>
						<button type="button" class="vcrm-btn vcrm-btn-ghost" id="vcrm-btn-close"><?php echo esc_html__( 'Cerrar hilo', 'vitacare-crm' ); ?></button>
						<button type="button" class="vcrm-btn vcrm-btn-ghost" id="vcrm-btn-reopen" hidden><?php echo esc_html__( 'Reabrir', 'vitacare-crm' ); ?></button>
					</div>
				</div>
				<div class="vcrm-thread-messages" id="vcrm-thread-messages" aria-live="polite">
					<div class="vcrm-empty vcrm-empty-thread"><?php echo esc_html__( 'Elige un contacto a la izquierda para ver el hilo.', 'vitacare-crm' ); ?></div>
				</div>
				<div class="vcrm-composer" id="vcrm-composer" hidden>
					<div class="vcrm-composer-error" id="vcrm-composer-error" hidden></div>
					<label class="screen-reader-text" for="vcrm-composer-input"><?php echo esc_html__( 'Mensaje', 'vitacare-crm' ); ?></label>
					<textarea id="vcrm-composer-input" class="vcrm-textarea" rows="2" maxlength="4096" placeholder="<?php echo esc_attr__( 'Escribe una respuesta… (Enter envía, Shift+Enter nueva línea)', 'vitacare-crm' ); ?>"></textarea>
					<div class="vcrm-composer-bar">
						<span class="vcrm-muted" id="vcrm-composer-hint"><?php echo esc_html__( 'Solo texto · ventana 24 h de WhatsApp', 'vitacare-crm' ); ?></span>
						<button type="button" class="vcrm-btn vcrm-btn-primary" id="vcrm-btn-send" disabled><?php echo esc_html__( 'Enviar', 'vitacare-crm' ); ?></button>
					</div>
				</div>
			</main>

			<aside class="vcrm-inbox-context" id="vcrm-context">
				<h2 class="vcrm-context-title"><?php echo esc_html__( 'Contacto', 'vitacare-crm' ); ?></h2>
				<dl class="vcrm-dl" id="vcrm-context-dl">
					<div><dt><?php echo esc_html__( 'Nombre', 'vitacare-crm' ); ?></dt><dd data-field="name">—</dd></div>
					<div><dt><?php echo esc_html__( 'Teléfono', 'vitacare-crm' ); ?></dt><dd data-field="phone">—</dd></div>
					<div><dt><?php echo esc_html__( 'Canal', 'vitacare-crm' ); ?></dt><dd data-field="channel">—</dd></div>
					<div><dt><?php echo esc_html__( 'Estado', 'vitacare-crm' ); ?></dt><dd data-field="status">—</dd></div>
					<div><dt><?php echo esc_html__( 'Asignado', 'vitacare-crm' ); ?></dt><dd data-field="assigned">—</dd></div>
					<div><dt><?php echo esc_html__( 'ID externo', 'vitacare-crm' ); ?></dt><dd data-field="external">—</dd></div>
				</dl>
				<p class="vcrm-muted vcrm-context-note">
					<?php echo esc_html__( 'Vínculo a paciente WP y leads: próximas fases. No se modifica el sistema principal.', 'vitacare-crm' ); ?>
				</p>
			</aside>
		</div>
	</div>
</section>

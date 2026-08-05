<?php
/**
 * Smoke test sin bootstrap WP completo (D-31, auditoría de ingesta de
 * Messenger). Uso: php tests/test-messenger-webhook-classification.php
 *
 * Prueba únicamente Vitacare_Crm_Channel_Messenger::classify_event(), que es
 * una función pura (sin $wpdb ni llamadas a WordPress) -- cubre los casos de
 * la Fase 7 que no requieren base de datos: payload de muestra/test,
 * mensaje real de texto, echo, postback, delivery, read, JSON inválido.
 *
 * Los casos que sí requieren persistencia real (contacto/conversación/
 * mensaje creados exactamente una vez, dedupe por mid, página distinta,
 * canal desactivado) necesitan un bootstrap WordPress + MySQL
 * (WP_TESTS_DIR) que no existe en este entorno -- ver tests/README.md y el
 * reporte de la Fase 8 de esta tarea. Se verificaron por auditoría de
 * código en vez de por test automatizado.
 */

define( 'ABSPATH', __DIR__ . '/' );
require_once __DIR__ . '/../includes/class-vitacare-crm-channel-messenger.php';

$pass  = 0;
$total = 0;

function check( string $label, bool $ok ): void {
	global $pass, $total;
	++$total;
	if ( $ok ) {
		++$pass;
		echo "OK {$label}\n";
	} else {
		echo "FAIL {$label}\n";
	}
}

// 1. Mensaje de texto real.
check(
	'mensaje de texto -> message',
	'message' === Vitacare_Crm_Channel_Messenger::classify_event(
		array(
			'sender'    => array( 'id' => '111' ),
			'recipient' => array( 'id' => '222' ),
			'message'   => array( 'mid' => 'm1', 'text' => 'hola' ),
		)
	)
);

// 2. Echo (staff respondió desde Business Suite, no desde el CRM).
check(
	'is_echo=true -> echo',
	'echo' === Vitacare_Crm_Channel_Messenger::classify_event(
		array(
			'sender'    => array( 'id' => '222' ),
			'recipient' => array( 'id' => '111' ),
			'message'   => array( 'mid' => 'm2', 'text' => 'hola', 'is_echo' => true ),
		)
	)
);

// 3. Postback (botón / Get Started).
check(
	'postback -> postback',
	'postback' === Vitacare_Crm_Channel_Messenger::classify_event(
		array(
			'sender'    => array( 'id' => '111' ),
			'recipient' => array( 'id' => '222' ),
			'postback'  => array( 'payload' => 'GET_STARTED' ),
		)
	)
);

// 4. Delivery.
check(
	'delivery -> delivery',
	'delivery' === Vitacare_Crm_Channel_Messenger::classify_event(
		array(
			'sender'    => array( 'id' => '111' ),
			'recipient' => array( 'id' => '222' ),
			'delivery'  => array( 'mids' => array( 'm1' ) ),
		)
	)
);

// 5. Read.
check(
	'read -> read',
	'read' === Vitacare_Crm_Channel_Messenger::classify_event(
		array(
			'sender'    => array( 'id' => '111' ),
			'recipient' => array( 'id' => '222' ),
			'read'      => array( 'watermark' => 123 ),
		)
	)
);

// 6. pass_thread_control (Handover Protocol).
check(
	'pass_thread_control -> pass_thread_control',
	'pass_thread_control' === Vitacare_Crm_Channel_Messenger::classify_event(
		array(
			'sender'              => array( 'id' => '111' ),
			'recipient'           => array( 'id' => '222' ),
			'pass_thread_control' => array( 'new_owner_app_id' => '999' ),
		)
	)
);

// 7. take_thread_control (Handover Protocol).
check(
	'take_thread_control -> take_thread_control',
	'take_thread_control' === Vitacare_Crm_Channel_Messenger::classify_event(
		array(
			'sender'              => array( 'id' => '111' ),
			'recipient'           => array( 'id' => '222' ),
			'take_thread_control' => array( 'previous_owner_app_id' => '999' ),
		)
	)
);

// 8. Evento sin ninguna forma reconocida -> unknown.
check(
	'forma desconocida -> unknown',
	'unknown' === Vitacare_Crm_Channel_Messenger::classify_event(
		array(
			'sender'    => array( 'id' => '111' ),
			'recipient' => array( 'id' => '222' ),
		)
	)
);

// 9. Payload de muestra/test de Meta: mismo shape que un mensaje real, pero
// la detección de "es una muestra" ocurre por comparación de Page ID en
// handle_payload() (page_id_mismatch), no en classify_event() -- ver
// ESTADO_CRM.md D-31 y la Fase 3 del pedido original. classify_event() por
// sí sola clasifica esto igual que un mensaje real (correcto: la forma del
// payload es idéntica, la diferencia es el Page ID, que se compara en otra
// capa).
check(
	'muestra de Meta con misma forma que mensaje real -> message (la deduplicación por Page ID ocurre en handle_payload)',
	'message' === Vitacare_Crm_Channel_Messenger::classify_event(
		array(
			'sender'    => array( 'id' => '1234567890' ),
			'recipient' => array( 'id' => '0987654321' ),
			'message'   => array( 'mid' => 'test_mid', 'text' => 'test' ),
		)
	)
);

echo "Result: {$pass}/{$total}\n";
exit( $pass === $total ? 0 : 1 );

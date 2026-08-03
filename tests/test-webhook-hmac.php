<?php
/**
 * Smoke test sin bootstrap WP completo.
 * Uso: php tests/test-webhook-hmac.php
 *
 * Carga solo la función de firma reimplementada en miniatura para CI local.
 */

function vitacare_crm_valid_signature( string $raw_body, string $header, string $app_secret ): bool {
	if ( $app_secret === '' || $header === '' ) {
		return false;
	}
	$expected = 'sha256=' . hash_hmac( 'sha256', $raw_body, $app_secret );
	return hash_equals( $expected, $header );
}

$secret = 'test_secret';
$body   = '{"object":"whatsapp_business_account","entry":[]}';
$good   = 'sha256=' . hash_hmac( 'sha256', $body, $secret );
$bad    = 'sha256=' . str_repeat( '0', 64 );

$pass = 0;
$total = 4;

if ( vitacare_crm_valid_signature( $body, $good, $secret ) ) {
	++$pass;
	echo "OK valid signature\n";
} else {
	echo "FAIL valid signature\n";
}

if ( ! vitacare_crm_valid_signature( $body, $bad, $secret ) ) {
	++$pass;
	echo "OK reject bad signature\n";
} else {
	echo "FAIL reject bad signature\n";
}

if ( ! vitacare_crm_valid_signature( $body, $good, '' ) ) {
	++$pass;
	echo "OK reject empty secret\n";
} else {
	echo "FAIL reject empty secret\n";
}

if ( ! vitacare_crm_valid_signature( $body, '', $secret ) ) {
	++$pass;
	echo "OK reject empty header\n";
} else {
	echo "FAIL reject empty header\n";
}

echo "Result: {$pass}/{$total}\n";
exit( $pass === $total ? 0 : 1 );

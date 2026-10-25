<?php
/**
 * Owner confirmation and staff alert: right people, right subjects, no health details, everything escaped.
 * Run: php tests/mailer-test.php
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/user-stubs.php';

use Pawfectly\Mailer;
use Pawfectly\Settings;

$GLOBALS['paw_options']['admin_email']      = 'owner@example.com';
$GLOBALS['paw_transients'][ 'paw_pg_' . md5( 'pawfectly_dashboard' ) ] = 'https://example.test/dashboard/';
$GLOBALS['paw_options'][ Settings::OPTION ] = array( 'staff_emails' => 'desk@example.com, manager@example.com' );

$reservation = array(
	'id'               => 12,
	'reference'        => 'PAW-7K3QX9',
	'status'           => 'pending',
	'owner_first_name' => 'Zoë',
	'owner_last_name'  => "O'Brien",
	'owner_email'      => 'zoe@example.com',
	'owner_phone'      => '555 010 2030',
	'pet_name'         => '<b>x</b>',
	'pet_type'         => 'dog',
	'check_in'         => '2026-10-28',
	'check_out'        => '2026-10-30',
	'details'          => array( 'medications' => 'yes', 'medications_details' => 'SECRET-MEDS 5mg', 'pet_breed' => 'Beagle' ),
	'files'            => array( array( 'name' => 'Rabies.pdf', 'path' => '2026/10/x.pdf', 'mime' => 'application/pdf', 'size' => 10 ) ),
);

Mailer::reservation_received( $reservation );
$mails = $GLOBALS['paw_mail'];
t( 'two emails sent', 2 === count( $mails ) );
$owner = $mails[0];
$staff = $mails[1];

t( 'owner email goes to the owner', 'zoe@example.com' === $owner['to'] );
t( 'owner subject', "We've received your reservation request — PAW-7K3QX9" === $owner['subject'] );
t( 'owner body has the stay dates as m/d/Y', str_contains( $owner['message'], '10/28/2026' ) && str_contains( $owner['message'], '10/30/2026' ) );
t( 'owner body says it is pending', str_contains( $owner['message'], 'pending' ) );
t( 'owner body leaves out health details', ! str_contains( $owner['message'], 'SECRET-MEDS' ) );
t( 'pet name escaped', str_contains( $owner['message'], '&lt;b&gt;x&lt;/b&gt;' ) && ! str_contains( $owner['message'], '<b>x</b>' ) );
t( 'owner name escaped and kept', str_contains( $owner['message'], 'Zoë' ) );
t( 'html content type', in_array( 'Content-Type: text/html; charset=UTF-8', (array) $owner['headers'], true ) );
t( 'replies from the owner go to staff', in_array( 'Reply-To: desk@example.com', (array) $owner['headers'], true ) );
t( 'no attachments', array() === $owner['attachments'] && array() === $staff['attachments'] );
t( 'branded with the business name', str_contains( $owner['message'], 'Pawfectly' ) );

t( 'staff email goes to every staff address', array( 'desk@example.com', 'manager@example.com' ) === $staff['to'] );
t( 'staff subject', 'New reservation request PAW-7K3QX9: <b>x</b> (10/28/2026 → 10/30/2026)' === $staff['subject'] );
t( 'staff body links to the reservation', str_contains( $staff['message'], 'https://example.test/dashboard/?paw_view=reservations&amp;open=12' ) );
t( 'staff body leaves out health details', ! str_contains( $staff['message'], 'SECRET-MEDS' ) );
t( 'staff replies go to the owner', in_array( 'Reply-To: zoe@example.com', (array) $staff['headers'], true ) );

$GLOBALS['paw_mail'] = array();
Mailer::reservation_received(
	array_merge(
		$reservation,
		array(
			'booking_type'     => 'grooming',
			'appointment_date' => '2026-10-29',
			'check_in'         => '2026-10-29',
			'check_out'        => '2026-10-29',
			'service'          => 'basic_bath',
			'details'          => array( 'appointment_time' => 'afternoon', 'pricing' => array( 'lines' => array( array( 'label' => 'Basic Bath', 'amount' => 45.0, 'monthly' => false ) ), 'total' => 45.0, 'on_request' => false, 'currency' => '$' ) ),
		)
	)
);
$g = $GLOBALS['paw_mail'][0]['message'] ?? '';
t( 'grooming email: appointment instead of check-in/out', str_contains( $g, 'Appointment' ) && str_contains( $g, '10/29/2026 · Afternoon (12–5)' ) && ! str_contains( $g, 'Check-out' ) );
t( 'grooming email: service and estimate', str_contains( $g, 'Basic Bath' ) && str_contains( $g, '$45' ) && str_contains( $g, 'staff confirm the final price' ) );
t( 'grooming staff subject names the day', str_contains( $GLOBALS['paw_mail'][1]['subject'] ?? '', '(10/29/2026 · Afternoon (12–5))' ) );

$GLOBALS['paw_mail'] = array();
Mailer::reservation_received( array_merge( $reservation, array( 'pet_name' => 'Rex + Tom', 'booking_type' => 'both', 'appointment_date' => '2026-10-29', 'service' => 'nail_trim', 'details' => array( 'pets' => array( array( 'pet_name' => 'Rex', 'service' => 'nail_trim', 'groomer' => 'Ana' ), array( 'pet_name' => 'Tom' ) ) ) ) ) );
$m2 = $GLOBALS['paw_mail'][0]['message'] ?? '';
t( 'two pets: both listed, with the service and groomer', str_contains( $m2, 'Rex: Nail Trimming, groomer Ana' ) && str_contains( $m2, 'Tom' ) );

$GLOBALS['paw_mail'] = array();
Mailer::status_changed( array_merge( $reservation, array( 'status' => 'confirmed' ) ) );
t( 'status email to the owner', 1 === count( $GLOBALS['paw_mail'] ) && 'zoe@example.com' === $GLOBALS['paw_mail'][0]['to'] );
t( 'status email subject', 'Your reservation PAW-7K3QX9 is now Confirmed' === $GLOBALS['paw_mail'][0]['subject'] );

$GLOBALS['paw_mail_result'] = false;
$threw = false;
try {
	ini_set( 'error_log', '/dev/null' );
	Mailer::reservation_received( $reservation );
} catch ( Throwable $e ) {
	$threw = true;
}
t( 'a failing wp_mail does not throw', ! $threw );

$crlf = Mailer::subject_safe( "Line\r\nBcc: evil@example.com" );
t( 'subjects cannot carry extra header lines', ! str_contains( $crlf, "\n" ) && ! str_contains( $crlf, "\r" ) );

done();

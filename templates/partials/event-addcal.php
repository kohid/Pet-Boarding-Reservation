<?php
/**
 * The Add to calendar menu: Google, Outlook and .ics.
 *
 * @package Pawfectly
 *
 * @var array $paw_ev The event (Events::get()).
 */

use Pawfectly\EventFields;

defined( 'ABSPATH' ) || exit;
?>
<details class="paw-event__addcal">
	<summary><?php esc_html_e( 'Add to calendar', 'pawfectly-reservations' ); ?></summary>
	<a href="<?php echo esc_url( EventFields::link( $paw_ev, 'google' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Google Calendar', 'pawfectly-reservations' ); ?></a>
	<a href="<?php echo esc_url( EventFields::link( $paw_ev, 'outlook' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Outlook', 'pawfectly-reservations' ); ?></a>
	<a href="<?php echo esc_url( EventFields::link( $paw_ev, 'ics' ) ); ?>"><?php esc_html_e( 'Apple / iCal (.ics)', 'pawfectly-reservations' ); ?></a>
</details>

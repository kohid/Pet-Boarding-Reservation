<?php
/**
 * Wraps email content in the branded layout.
 *
 * @package Pawfectly
 */

namespace Pawfectly;

defined( 'ABSPATH' ) || exit;

final class EmailTemplate {

	/**
	 * @param string $heading   Plain text; escaped here.
	 * @param string $body_html Already-escaped HTML.
	 */
	public static function render( string $heading, string $body_html, array $settings ): string {
		$logo = $settings['logo_id'] ? wp_get_attachment_image_url( (int) $settings['logo_id'], 'medium' ) : '';
		$name = (string) $settings['business_name'];

		ob_start();
		include PAW_DIR . 'templates/email.php';
		return (string) ob_get_clean();
	}

	/** A two-column summary table from label => value pairs (values escaped here). */
	public static function rows( array $rows ): string {
		$html = '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;margin:16px 0;">';
		foreach ( $rows as $label => $value ) {
			$html .= '<tr><td style="padding:8px 0;border-bottom:1px solid #f1eeeb;color:#6b7280;font-size:14px;width:40%;">' . esc_html( $label ) . '</td>'
				. '<td style="padding:8px 0;border-bottom:1px solid #f1eeeb;color:#2c3947;font-size:14px;">' . esc_html( $value ) . '</td></tr>';
		}
		return $html . '</table>';
	}
}

<?php
/**
 * Email layout: inline styles and tables, because email clients ignore stylesheets.
 *
 * @package Pawfectly
 *
 * @var string $heading   Plain text.
 * @var string $body_html Escaped HTML.
 * @var string $logo      Logo URL or ''.
 * @var string $name      Business name.
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#fff8f3;">
<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#fff8f3;">
	<tr>
		<td align="center" style="padding:32px 16px;">
			<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:560px;background:#ffffff;border-radius:8px;overflow:hidden;font-family:Nunito,'Segoe UI',Helvetica,Arial,sans-serif;">
				<tr><td style="height:6px;background:#ff7f50;font-size:0;line-height:0;">&nbsp;</td></tr>
				<tr>
					<td style="padding:28px 32px 0;">
						<?php if ( $logo ) : ?>
							<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $name ); ?>" width="72" style="display:block;width:72px;height:auto;border:0;">
						<?php else : ?>
							<p style="margin:0;color:#ff7f50;font-size:20px;font-weight:700;"><?php echo esc_html( $name ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td style="padding:20px 32px 28px;color:#2c3947;font-size:15px;line-height:1.55;">
						<h1 style="margin:0 0 12px;font-size:22px;line-height:1.3;color:#2c3947;"><?php echo esc_html( $heading ); ?></h1>
						<?php echo $body_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped by the caller. ?>
					</td>
				</tr>
				<tr>
					<td style="padding:16px 32px;background:#fbf6f2;color:#6b7280;font-size:12px;"><?php echo esc_html( $name ); ?></td>
				</tr>
			</table>
		</td>
	</tr>
</table>
</body>
</html>

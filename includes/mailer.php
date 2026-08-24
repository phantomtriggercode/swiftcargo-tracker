<?php
/**
 * Sends tracking-update alert emails over plain SMTP using PHPMailer.
 *
 * No third-party email API (SendGrid, Mailgun, etc.) is used anywhere here —
 * this talks directly to an SMTP server using the standard SMTP protocol,
 * the same way any desktop email client does.
 *
 * SMTP credentials are read from the settings table first (editable at
 * /admin/smtp_settings.php using any webmail/SMTP provider's details),
 * falling back to the SMTP_* constants in config.php if nothing has been
 * saved yet.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/settings.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function smtp_config(): array
{
    return [
        'host' => get_setting('smtp_host', defined('SMTP_HOST') ? SMTP_HOST : ''),
        'port' => (int) get_setting('smtp_port', (string) (defined('SMTP_PORT') ? SMTP_PORT : 587)),
        'user' => get_setting('smtp_user', defined('SMTP_USER') ? SMTP_USER : ''),
        'pass' => get_setting('smtp_pass', defined('SMTP_PASS') ? SMTP_PASS : ''),
        'secure' => get_setting('smtp_secure', defined('SMTP_SECURE') ? SMTP_SECURE : 'tls'),
        'from_email' => get_setting('smtp_from_email', defined('SMTP_FROM') ? SMTP_FROM : ''),
        'from_name' => get_setting('smtp_from_name', defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : get_site_name()),
    ];
}

/**
 * @return array{ok: bool, error?: string}
 */
function send_smtp_mail(string $toEmail, string $toName, string $subject, string $htmlBody, string $altBody, ?string $replyToEmail = null, ?string $replyToName = null): array
{
    $cfg = smtp_config();
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $cfg['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['user'];
        $mail->Password   = $cfg['pass'];
        $mail->SMTPSecure = $cfg['secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $cfg['port'];
        $mail->Timeout    = 10;
        $mail->SMTPKeepAlive = false;

        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($toEmail, $toName);
        if ($replyToEmail) {
            $mail->addReplyTo($replyToEmail, $replyToName ?? '');
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $altBody;

        $mail->send();
        return ['ok' => true];
    } catch (PHPMailerException $e) {
        return ['ok' => false, 'error' => $mail->ErrorInfo ?: $e->getMessage()];
    }
}

/**
 * @return array{ok: bool, error?: string}
 */
function send_tracking_update_email(array $shipment, array $event): array
{
    $site = get_site_name();
    $subject = $site . ' — Shipment ' . $shipment['tracking_number'] . ' update: ' . $event['status'];

    return send_smtp_mail(
        $shipment['receiver_email'],
        $shipment['receiver_name'],
        $subject,
        render_tracking_email_html($shipment, $event),
        render_tracking_email_text($shipment, $event)
    );
}

/**
 * Notifies the receiver when a shipment's insurance status changes —
 * whether insurance was just added or removed — instead of the change
 * happening silently. $shipment only needs tracking_number, receiver_name,
 * receiver_email.
 *
 * @return array{ok: bool, error?: string}
 */
function send_insurance_status_email(array $shipment, bool $nowInsured, float $insuranceValue): array
{
    $site = get_site_name();
    $theme = get_active_palette();
    $ink = h($theme['color_ink']);
    $primary = h($theme['color_primary']);
    $tn = h($shipment['tracking_number']);
    $receiver = h($shipment['receiver_name']);
    $trackUrl = get_site_url() . '/track.php?tn=' . urlencode($shipment['tracking_number']);

    if ($nowInsured) {
        $subject = $site . ' — Shipment ' . $shipment['tracking_number'] . ' is now insured';
        $valueLine = $insuranceValue > 0 ? ' for a declared value of <strong style="color:' . $primary . ';">$' . number_format($insuranceValue, 2) . '</strong>' : '';
        $bodyHtml = '<p>Your shipment <strong>' . $tn . '</strong> now has shipping insurance' . $valueLine . '.</p>';
        $bodyText = "Your shipment {$tn} now has shipping insurance" . ($insuranceValue > 0 ? ' for a declared value of $' . number_format($insuranceValue, 2) : '') . ".\n";
    } else {
        $subject = $site . ' — Shipment ' . $shipment['tracking_number'] . ' insurance removed';
        $bodyHtml = '<p>Shipping insurance has been removed from your shipment <strong>' . $tn . '</strong>. It is no longer covered by insurance in transit.</p>';
        $bodyText = "Shipping insurance has been removed from your shipment {$tn}. It is no longer covered by insurance in transit.\n";
    }

    $htmlBody = '<div style="font-family:Arial,sans-serif;font-size:14px;color:' . $ink . ';">'
        . '<p>Hi ' . $receiver . ',</p>'
        . $bodyHtml
        . '<p><a href="' . h($trackUrl) . '" style="color:' . $primary . ';">Track your shipment</a></p>'
        . '</div>';
    $altBody = "Hi {$shipment['receiver_name']},\n\n{$bodyText}\nTrack your shipment: {$trackUrl}\n";

    return send_smtp_mail($shipment['receiver_email'], $shipment['receiver_name'], $subject, $htmlBody, $altBody);
}

function render_tracking_email_html(array $shipment, array $event): string
{
    $trackUrl = get_site_url() . '/track.php?tn=' . urlencode($shipment['tracking_number']);
    $status = h($event['status']);
    $location = h($event['location_label']);
    $noteText = trim((string) ($event['note'] ?? ''));
    $noteHtml = $noteText !== ''
        ? '<p style="margin:0 0 20px;color:#374151;background:#f9fafb;border-radius:6px;padding:12px 14px;">' . h($noteText) . '</p>'
        : '';
    $tn = h($shipment['tracking_number']);
    $receiver = h($shipment['receiver_name']);
    $site = h(get_site_name());

    // Colors follow the active site theme (see includes/theme.php) so
    // emails match whatever's live at /admin/themes.php instead of
    // staying hardcoded to one brand's colors.
    $theme = get_active_palette();
    $primary = h($theme['color_primary']);
    $accent = h($theme['color_accent']);
    $ink = h($theme['color_ink']);
    $muted = h($theme['color_muted']);
    $bgSoft = h($theme['color_bg_soft']);
    $white = h($theme['color_white']);
    $border = h($theme['color_border']);

    return <<<HTML
    <div style="font-family:Arial,Helvetica,sans-serif;background:{$bgSoft};padding:24px;">
      <div style="max-width:560px;margin:0 auto;background:{$white};border-radius:8px;overflow:hidden;border:1px solid {$border};">
        <div style="background:{$primary};padding:20px 24px;">
          <span style="color:{$accent};font-size:22px;font-weight:bold;letter-spacing:1px;">{$site}</span>
        </div>
        <div style="padding:24px;">
          <p style="margin:0 0 12px;color:{$ink};">Hi {$receiver},</p>
          <p style="margin:0 0 20px;color:{$ink};">There's a new update on your shipment.</p>
          <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
            <tr>
              <td style="padding:8px 0;color:{$muted};">Tracking number</td>
              <td style="padding:8px 0;color:{$ink};font-weight:bold;text-align:right;">{$tn}</td>
            </tr>
            <tr>
              <td style="padding:8px 0;color:{$muted};">Status</td>
              <td style="padding:8px 0;color:{$primary};font-weight:bold;text-align:right;">{$status}</td>
            </tr>
            <tr>
              <td style="padding:8px 0;color:{$muted};">Location</td>
              <td style="padding:8px 0;color:{$ink};text-align:right;">{$location}</td>
            </tr>
          </table>
          {$noteHtml}
          <div style="text-align:center;margin-top:12px;">
            <a href="{$trackUrl}" style="display:inline-block;background:{$accent};color:{$ink};font-weight:bold;padding:12px 28px;border-radius:6px;text-decoration:none;">Track my package</a>
          </div>
        </div>
        <div style="background:{$bgSoft};padding:16px 24px;color:#9ca3af;font-size:12px;">
          You're receiving this because this email is registered as the receiver for shipment {$tn}.
        </div>
      </div>
    </div>
    HTML;
}

function render_tracking_email_text(array $shipment, array $event): string
{
    $trackUrl = get_site_url() . '/track.php?tn=' . urlencode($shipment['tracking_number']);
    return get_site_name() . " tracking update\n\n"
        . "Tracking number: {$shipment['tracking_number']}\n"
        . "Status: {$event['status']}\n"
        . "Location: {$event['location_label']}\n"
        . (!empty($event['note']) ? "Note: {$event['note']}\n" : '')
        . "\nTrack your package: {$trackUrl}\n";
}

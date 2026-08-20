<?php
/**
 * Sends tracking-update alert emails over plain SMTP using PHPMailer.
 *
 * No third-party email API (SendGrid, Mailgun, etc.) is used anywhere here —
 * this talks directly to an SMTP server using the standard SMTP protocol,
 * the same way any desktop email client does.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * @return array{ok: bool, error?: string}
 */
function send_tracking_update_email(array $shipment, array $event): array
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->Timeout    = 10;
        $mail->SMTPKeepAlive = false;

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($shipment['receiver_email'], $shipment['receiver_name']);

        $mail->isHTML(true);
        $mail->Subject = SITE_NAME . ' — Shipment ' . $shipment['tracking_number'] . ' update: ' . $event['status'];
        $mail->Body    = render_tracking_email_html($shipment, $event);
        $mail->AltBody = render_tracking_email_text($shipment, $event);

        $mail->send();
        return ['ok' => true];
    } catch (PHPMailerException $e) {
        return ['ok' => false, 'error' => $mail->ErrorInfo ?: $e->getMessage()];
    }
}

function render_tracking_email_html(array $shipment, array $event): string
{
    $trackUrl = rtrim(SITE_URL, '/') . '/track.php?tn=' . urlencode($shipment['tracking_number']);
    $status = h($event['status']);
    $location = h($event['location_label']);
    $noteText = trim((string) ($event['note'] ?? ''));
    $noteHtml = $noteText !== ''
        ? '<p style="margin:0 0 20px;color:#374151;background:#f9fafb;border-radius:6px;padding:12px 14px;">' . h($noteText) . '</p>'
        : '';
    $tn = h($shipment['tracking_number']);
    $receiver = h($shipment['receiver_name']);
    $site = h(SITE_NAME);

    return <<<HTML
    <div style="font-family:Arial,Helvetica,sans-serif;background:#f4f5f7;padding:24px;">
      <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb;">
        <div style="background:#d40511;padding:20px 24px;">
          <span style="color:#ffcc00;font-size:22px;font-weight:bold;letter-spacing:1px;">{$site}</span>
        </div>
        <div style="padding:24px;">
          <p style="margin:0 0 12px;color:#111827;">Hi {$receiver},</p>
          <p style="margin:0 0 20px;color:#111827;">There's a new update on your shipment.</p>
          <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
            <tr>
              <td style="padding:8px 0;color:#6b7280;">Tracking number</td>
              <td style="padding:8px 0;color:#111827;font-weight:bold;text-align:right;">{$tn}</td>
            </tr>
            <tr>
              <td style="padding:8px 0;color:#6b7280;">Status</td>
              <td style="padding:8px 0;color:#d40511;font-weight:bold;text-align:right;">{$status}</td>
            </tr>
            <tr>
              <td style="padding:8px 0;color:#6b7280;">Location</td>
              <td style="padding:8px 0;color:#111827;text-align:right;">{$location}</td>
            </tr>
          </table>
          {$noteHtml}
          <div style="text-align:center;margin-top:12px;">
            <a href="{$trackUrl}" style="display:inline-block;background:#ffcc00;color:#111827;font-weight:bold;padding:12px 28px;border-radius:6px;text-decoration:none;">Track my package</a>
          </div>
        </div>
        <div style="background:#f4f5f7;padding:16px 24px;color:#9ca3af;font-size:12px;">
          You're receiving this because this email is registered as the receiver for shipment {$tn}.
        </div>
      </div>
    </div>
    HTML;
}

function render_tracking_email_text(array $shipment, array $event): string
{
    $trackUrl = rtrim(SITE_URL, '/') . '/track.php?tn=' . urlencode($shipment['tracking_number']);
    return SITE_NAME . " tracking update\n\n"
        . "Tracking number: {$shipment['tracking_number']}\n"
        . "Status: {$event['status']}\n"
        . "Location: {$event['location_label']}\n"
        . (!empty($event['note']) ? "Note: {$event['note']}\n" : '')
        . "\nTrack your package: {$trackUrl}\n";
}

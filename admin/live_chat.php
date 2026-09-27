<?php
/**
 * Live chat settings, where the site owner connects their own Tawk.to
 * account so the chat bubble appears on the public site.
 *
 * Deliberately paste-anything friendly: the owner copies the "Widget Code"
 * out of their Tawk.to dashboard and drops the whole thing in, and this
 * page picks the two IDs out of it (see live_chat_parse_ids()). No code
 * editing, no file uploads.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_once __DIR__ . '/../includes/live_chat.php';
require_super_admin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        $pasted  = trim($_POST['widget_code'] ?? '');
        $enabled = !empty($_POST['enabled']);
        $current = live_chat_settings();

        $propertyId = $current['property_id'];
        $widgetId   = $current['widget_id'];

        if ($pasted !== '') {
            $parsed = live_chat_parse_ids($pasted);
            if ($parsed === null) {
                $errors[] = 'That does not look like a Tawk.to widget code. In your Tawk.to dashboard go to '
                    . 'Administration → Channels → Chat Widget, copy everything in the "Widget Code" box, '
                    . 'and paste all of it here. Pasting just the https://embed.tawk.to/... link works too.';
            } else {
                $propertyId = $parsed['property_id'];
                $widgetId   = $parsed['widget_id'];
            }
        }

        // Belt and braces: never store an ID that would not pass the same
        // allowlist the public page checks before printing it.
        if (!$errors && $propertyId !== '' && !live_chat_is_valid_property_id($propertyId)) {
            $errors[] = 'The Property ID in that code is not in a format Tawk.to uses. Please re-copy the widget code.';
        }
        if (!$errors && $widgetId !== '' && !live_chat_is_valid_widget_id($widgetId)) {
            $errors[] = 'The Widget ID in that code is not in a format Tawk.to uses. Please re-copy the widget code.';
        }
        if (!$errors && $enabled && ($propertyId === '' || $widgetId === '')) {
            $errors[] = 'Paste your Tawk.to widget code first. There is nothing to switch on yet.';
        }

        if (!$errors) {
            set_setting('live_chat_property_id', $propertyId);
            set_setting('live_chat_widget_id', $widgetId);
            set_setting('live_chat_enabled', $enabled ? '1' : '0');
            log_admin_activity(
                'Changed live chat settings',
                ($enabled ? 'Enabled' : 'Disabled')
                . ($propertyId !== '' ? ', property ' . $propertyId . '/' . $widgetId : '')
            );
            flash_set(
                'success',
                $enabled
                    ? 'Live chat is on. Open your public site and the chat bubble will be at the bottom-right.'
                    : 'Saved. Live chat is switched off, so no chat bubble shows on the public site.'
            );
            redirect('/admin/live_chat.php');
        }
    } elseif ($action === 'disconnect') {
        set_setting('live_chat_enabled', '0');
        set_setting('live_chat_property_id', '');
        set_setting('live_chat_widget_id', '');
        log_admin_activity('Disconnected live chat');
        flash_set('success', 'Disconnected. The Tawk.to details have been removed from this site.');
        redirect('/admin/live_chat.php');
    }
}

$cfg = live_chat_settings();

$activeAdminNav = 'live_chat';
$pageTitle = 'Live Chat';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Live Chat</h1>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= h($err) ?></div>
<?php endforeach; ?>

<?php if ($cfg['enabled'] && live_chat_is_configured()): ?>
  <div class="alert alert-success">
    Live chat is <strong>on</strong>. Visitors see the chat bubble at the bottom-right of every
    public page, and their messages arrive in your Tawk.to dashboard.
  </div>
<?php elseif (live_chat_is_configured()): ?>
  <div class="alert alert-info">
    Your Tawk.to account is connected but live chat is currently <strong>switched off</strong>,
    so no chat bubble shows on the public site.
  </div>
<?php endif; ?>

<div class="form-card" style="max-width:640px;">
  <p style="margin-top:0;color:var(--muted);font-size:14px;">
    This connects your own free
    <a href="https://www.tawk.to" target="_blank" rel="noopener" style="color:var(--brand-red);">Tawk.to</a>
    account to the site, so visitors can chat with you while they track a shipment.
    You keep your Tawk.to login. It is never stored here, and you answer chats in the
    Tawk.to dashboard or its phone app, not in this admin panel.
  </p>

  <ol style="color:var(--muted);font-size:14px;line-height:1.7;padding-left:20px;">
    <li>Sign in at <strong>tawk.to</strong> (create a free account if you don't have one).</li>
    <li>Go to <strong>Administration → Channels → Chat Widget</strong>.</li>
    <li>Copy everything inside the <strong>Widget Code</strong> box.</li>
    <li>Paste it below and press Save.</li>
  </ol>

  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <div class="form-group">
      <label>Tawk.to widget code</label>
      <textarea name="widget_code" rows="6" placeholder="<!--Start of Tawk.to Script-->&#10;<script type=&quot;text/javascript&quot;>&#10;...&#10;s1.src='https://embed.tawk.to/xxxxxxxxxxxxxxxxxxxxxxxx/default';&#10;...&#10;</script>&#10;<!--End of Tawk.to Script-->"></textarea>
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">
        Paste the whole block: extra lines are fine, only the two ID codes inside it are saved.
        Just the <code>https://embed.tawk.to/...</code> link on its own works too.
        <?php if (live_chat_is_configured()): ?>
          Leave this blank to keep the account already connected below.
        <?php endif; ?>
      </span>
    </div>

    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px;font-weight:normal;">
        <input type="checkbox" name="enabled" value="1" <?= $cfg['enabled'] ? 'checked' : '' ?>>
        Show the chat bubble on the public site
      </label>
      <span style="display:block;font-size:12px;color:var(--muted);margin-top:6px;">
        Untick this to hide chat from visitors without disconnecting your account: handy
        outside business hours, or while you are still setting Tawk.to up.
      </span>
    </div>

    <button type="submit" class="btn btn-primary btn-block">Save Live Chat Settings</button>
  </form>
</div>

<?php if (live_chat_is_configured()): ?>
  <div class="form-card" style="max-width:640px;margin-top:16px;">
    <h3 style="margin-top:0;">Connected account</h3>
    <p style="color:var(--muted);font-size:14px;margin-top:0;">
      These are the codes read out of the widget code you pasted. They are not secret. They are the same codes Tawk.to puts on any page your widget runs on.
    </p>
    <dl style="margin:0;font-size:14px;">
      <dt style="font-weight:700;color:var(--ink-soft);font-size:12.5px;text-transform:uppercase;letter-spacing:0.4px;">Property ID</dt>
      <dd style="margin:4px 0 12px;"><code style="overflow-wrap:break-word;"><?= h($cfg['property_id']) ?></code></dd>
      <dt style="font-weight:700;color:var(--ink-soft);font-size:12.5px;text-transform:uppercase;letter-spacing:0.4px;">Widget ID</dt>
      <dd style="margin:4px 0 12px;"><code style="overflow-wrap:break-word;"><?= h($cfg['widget_id']) ?></code></dd>
      <dt style="font-weight:700;color:var(--ink-soft);font-size:12.5px;text-transform:uppercase;letter-spacing:0.4px;">Loads from</dt>
      <dd style="margin:4px 0 0;"><code style="overflow-wrap:break-word;"><?= h(live_chat_embed_url()) ?></code></dd>
    </dl>
    <form method="post" style="margin-top:14px;" onsubmit="return confirm('Remove your Tawk.to details from this site? The chat bubble will disappear from the public site. Your Tawk.to account and chat history are not affected.');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="disconnect">
      <button type="submit" class="btn btn-outline btn-sm">Disconnect Tawk.to</button>
    </form>
  </div>
<?php endif; ?>

<div class="form-card" style="max-width:640px;margin-top:16px;">
  <h3 style="margin-top:0;">Good to know</h3>
  <ul style="color:var(--muted);font-size:14px;line-height:1.8;padding-left:20px;margin-bottom:0;">
    <li><strong>Where the bubble sits, what colour it is and the greeting text</strong> are all
      set inside Tawk.to (Administration → Channels → Chat Widget). Bottom-right is its
      default. Nothing about the look of the widget is controlled from this page.</li>
    <li><strong>Only the public site</strong> shows the bubble: never this admin panel.</li>
    <li><strong>If chat is unreachable</strong> (a visitor's network blocks it, or Tawk.to is
      down), the widget just doesn't appear. The rest of the page, including the live
      tracking map, is completely unaffected.</li>
    <li><strong>Switching chat on also tells browsers to trust Tawk.to</strong> for scripts on
      the public site. Switching it off withdraws that again automatically, so the site
      goes back to allowing nothing from outside.</li>
    <li><strong>Worth a line in your Privacy Policy.</strong> The chat widget sets its own
      cookies so a visitor's conversation survives a page reload. Your Privacy Policy
      currently says the site uses no third-party cookies, which stops being quite true
      once chat is on. You can edit that wording under
      <a href="/admin/content.php" style="color:var(--brand-red);">Site Content</a>.</li>
  </ul>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>

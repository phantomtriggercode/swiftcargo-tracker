<?php
/**
 * Live chat widget (Tawk.to).
 *
 * Nothing here is tied to one Tawk.to account: the site owner pastes the
 * widget code from their own Tawk.to dashboard into /admin/live_chat.php
 * and it is stored in the settings table like any other site setting.
 *
 * Only the two IDs out of that snippet are kept, and both are checked
 * against a strict letters-and-digits allowlist before ever being saved,
 * because they end up inside a <script src="..."> URL on every public
 * page — anything looser would let an admin-supplied value point that
 * script somewhere else entirely.
 *
 * Requires config/db.php, includes/functions.php and includes/settings.php.
 */

// Tawk.to property IDs are long hex strings (currently 24 characters) and
// widget IDs are short alphanumerics ("default" for the default widget).
// These patterns are deliberately alphanumeric-only: no dot, slash, colon,
// quote or angle bracket can get through, so a saved value cannot escape
// its path segment in the embed URL.
const LIVE_CHAT_PROPERTY_ID_PATTERN = '/^[A-Za-z0-9]{16,40}$/';
const LIVE_CHAT_WIDGET_ID_PATTERN   = '/^[A-Za-z0-9]{1,40}$/';

/**
 * The saved live-chat configuration.
 *
 * @return array{enabled:bool, property_id:string, widget_id:string}
 */
function live_chat_settings(): array
{
    return [
        'enabled'     => get_setting('live_chat_enabled', '0') === '1',
        'property_id' => get_setting('live_chat_property_id', ''),
        'widget_id'   => get_setting('live_chat_widget_id', ''),
    ];
}

function live_chat_is_valid_property_id(string $id): bool
{
    return (bool) preg_match(LIVE_CHAT_PROPERTY_ID_PATTERN, $id);
}

function live_chat_is_valid_widget_id(string $id): bool
{
    return (bool) preg_match(LIVE_CHAT_WIDGET_ID_PATTERN, $id);
}

/**
 * True when the widget is switched on and properly configured — i.e. when
 * the chat bubble should actually appear on the public site.
 */
function live_chat_is_configured(): bool
{
    $cfg = live_chat_settings();
    return live_chat_is_valid_property_id($cfg['property_id'])
        && live_chat_is_valid_widget_id($cfg['widget_id']);
}

function live_chat_is_active(): bool
{
    return live_chat_settings()['enabled'] && live_chat_is_configured();
}

/**
 * Pulls the Property ID and Widget ID out of whatever the site owner
 * pasted, so they never have to pick the codes out of the snippet by hand:
 *
 *   - the whole <script> block Tawk.to gives them ("Widget Code"),
 *   - just the https://embed.tawk.to/PROPERTY/WIDGET link,
 *   - the two IDs separated by a slash, comma or space,
 *   - or the Property ID on its own (the default widget is then assumed).
 *
 * @return array{property_id:string, widget_id:string}|null null when
 *         nothing that looks like a Tawk.to ID pair could be found.
 */
function live_chat_parse_ids(string $input): ?array
{
    $input = trim($input);
    if ($input === '') {
        return null;
    }

    // The embed URL, wherever it sits inside a pasted snippet.
    if (preg_match('~embed\.tawk\.to/([A-Za-z0-9]{16,40})/([A-Za-z0-9]{1,40})~', $input, $m)) {
        return ['property_id' => $m[1], 'widget_id' => $m[2]];
    }

    // The two IDs on their own.
    if (preg_match('~^([A-Za-z0-9]{16,40})[\s/,]+([A-Za-z0-9]{1,40})$~', $input, $m)) {
        return ['property_id' => $m[1], 'widget_id' => $m[2]];
    }

    // Just the Property ID — Tawk.to calls the widget every account starts
    // with "default", so this is the safe assumption rather than an error.
    if (preg_match('~^([A-Za-z0-9]{16,40})$~', $input, $m)) {
        return ['property_id' => $m[1], 'widget_id' => 'default'];
    }

    return null;
}

/**
 * The script URL the widget loads from, or '' when not configured.
 */
function live_chat_embed_url(): string
{
    $cfg = live_chat_settings();
    if (!live_chat_is_valid_property_id($cfg['property_id']) || !live_chat_is_valid_widget_id($cfg['widget_id'])) {
        return '';
    }
    return 'https://embed.tawk.to/' . $cfg['property_id'] . '/' . $cfg['widget_id'];
}

/**
 * The <script> block that loads the chat bubble, or an empty string when
 * live chat is off or not configured.
 *
 * Called only from includes/footer.php, so the widget shows up on the
 * public site and never inside the admin panel — staff answer chats in
 * their own Tawk.to dashboard, not here.
 *
 * Loading it last and asynchronously is deliberate: if Tawk.to is slow or
 * unreachable, nothing else on the page (least of all the live map) waits
 * for it or breaks.
 */
function live_chat_script_tag(): string
{
    if (!live_chat_is_active()) {
        return '';
    }

    // Safe to interpolate: both halves passed the alphanumeric-only
    // allowlist above, so neither can contain a quote or a slash.
    $src = live_chat_embed_url();

    return <<<HTML
<!-- Live chat widget. Switch it on/off or change accounts at /admin/live_chat.php -->
<script>
  var Tawk_API = Tawk_API || {}, Tawk_LoadStart = new Date();
  (function () {
    var s1 = document.createElement('script'),
        s0 = document.getElementsByTagName('script')[0];
    s1.async = true;
    s1.src = '{$src}';
    s1.charset = 'UTF-8';
    s1.setAttribute('crossorigin', '*');
    if (s0 && s0.parentNode) {
      s0.parentNode.insertBefore(s1, s0);
    } else {
      document.body.appendChild(s1);
    }
  })();
</script>

HTML;
}

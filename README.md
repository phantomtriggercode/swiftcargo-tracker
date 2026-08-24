# Shipping & Live Tracking Site

A white-labeled, DHL-style shipping & live package tracking website built in
**plain PHP + MySQL** so it runs on Hostinger's cheapest shared hosting plan
(which only supports PHP/MySQL, not Node.js) using the free **temporary
domain** Hostinger gives you before you buy a real domain.

**Not tied to any brand or domain.** The site name, logo, and virtually all
page content and settings — including email delivery — are configured from
the admin dashboard, not the codebase. Deploy this under any domain and set
it up as any company from `/admin` with zero code edits. "SwiftCargo" only
appears as the out-of-the-box default brand name and demo tracking-number
prefix — change it on your first login.

## What it does

- Public site (home, services, about, contact) styled like a global courier brand.
- **Track a shipment** by tracking number and see:
  - An interactive **live map** (Leaflet.js + OpenStreetMap tiles) showing origin,
    current position, and destination, with the position auto-refreshing every 15
    seconds by polling the site's own `api/track.php` endpoint.
  - A full timestamped status timeline.
- **Staff admin panel** (`/admin`) to create shipments and add tracking updates,
  each with a shipping method (Air / Sea / Land, with Van/Trailer/Train for Land),
  packaging type (Box, Crate, Pallet, Loose Cargo, FCL/LCL container, envelope),
  Regular/Express service level, and optional declared-value insurance.
  Every time a status update is added, the receiver automatically gets an **email
  alert** — no third-party API/service, just plain SMTP.
- A full **content management system**: Home/About/Services/Contact/Footer
  copy and the supported-countries list are all editable from the admin
  panel — nothing is hardcoded.
- **Branding and email, configured in the dashboard, not code**: set the site
  name and upload a logo at `/admin/branding.php`; set SMTP host/port/username
  /password (from any mailbox — Hostinger webmail, Gmail, etc.) at
  `/admin/smtp_settings.php`, with a one-click test-email button.
- A public **multi-step "Request a Shipment"** wizard (route & schedule →
  package details → service options → review) with a live, admin-configurable
  cost calculator; submissions land in an admin queue that can be reviewed and
  converted straight into a real shipment.
- Custom illustrated homepage sections (warehouse handling, fleet, van
  unloading, doorstep delivery) — original hand-built vector art, not stock
  photography.
- **Printable PDF waybill and shipping label** for every shipment, with a
  real scannable barcode. Staff can print from the dashboard, and the
  receiver can print their own from the tracking page — see below.

### "No API" — what that means here

No paid/keyed third-party APIs are used anywhere:

- **Map**: [Leaflet.js](https://leafletjs.com) + free [OpenStreetMap](https://www.openstreetmap.org)
  tiles — no Google Maps API key, no billing account, no signup.
- **Email**: [PHPMailer](https://github.com/PHPMailer/PHPMailer) talking directly to
  an SMTP server over the standard SMTP protocol — the same way Outlook or Thunderbird
  sends mail. No SendGrid/Mailgun/SES API involved.
- **Live updates**: the map polls a small JSON endpoint that is part of this app
  (`api/track.php`), not an external API.
- **Barcode**: a Code 39 barcode encoder hand-written in plain PHP
  (`includes/barcode.php`), rendered locally with GD — no barcode-generator API.
- **PDF documents**: [Dompdf](https://github.com/dompdf/dompdf) renders the
  waybill/label HTML to a PDF entirely on your server — no cloud PDF/print API.

The only "endpoint" the JS calls is your own server. Leaflet itself is served
from your own site too (`assets/vendor/leaflet/`), so the only external network
calls anything makes are (a) map tile images from OpenStreetMap — free, keyless,
and the map still works without them — and (b) an SMTP connection to send email.

## Tech stack

- PHP 8+ (no framework — plain scripts + includes)
- MySQL / MariaDB
- [PHPMailer](https://github.com/PHPMailer/PHPMailer) and [Dompdf](https://github.com/dompdf/dompdf)
  (vendored via Composer, included in this repo's `vendor/` folder — no Composer needed on the server)
- [Leaflet.js](https://leafletjs.com) (vendored in `assets/vendor/leaflet/`, no CDN, no build step) + OpenStreetMap tiles
- PHP's GD extension (for barcode rendering — enabled by default on Hostinger)

## Project structure

```
config/         Database config (config.php is git-ignored — copy config.sample.php).
                SMTP_* constants here are only the first-boot fallback — the
                dashboard's saved settings take priority once you set them.
sql/            schema.sql (fresh installs) + migrations/ (updates for an existing DB)
includes/       Shared PHP: db helpers, auth, mailer, settings/CMS helper, header/footer
admin/          Staff panel: dashboard, shipments, tracking updates, requests,
                content, rates, branding, SMTP settings
api/            track.php (live map polling) + geocode.php (address lookup)
assets/         CSS + JS + images (including illustrations/ and uploads/ for
                admin-uploaded logos)
vendor/         PHPMailer (vendored, committed to the repo)
index.php, track.php, services.php, about.php, contact.php,
countries.php, request-shipment.php                          Public pages
```

## Local testing

```bash
php -S localhost:8000
```
Then visit `http://localhost:8000`. You'll need a local MySQL database and a
`config/config.php` (see setup below) for anything beyond static pages to work.

## Deploying to Hostinger (shared hosting, free temporary domain)

### 1. Get your temporary domain + hosting live
Hostinger's shared hosting plans issue a free temporary domain (something like
`https://yourname.hostingerapp.com`) the moment you set up hosting — you don't need
to buy a domain to get started.

### 2. Create the MySQL database
In **hPanel → Databases → MySQL Databases**:
1. Create a new database (e.g. `u123456789_swiftcargo`) and a database user with a
   strong password. Note the database name, username, password, and host (Hostinger
   shared hosting is almost always `localhost`).
2. Open **phpMyAdmin** for that database, go to the **Import** tab, and upload
   `sql/schema.sql` from this repo. This creates all tables and seeds two demo
   shipments plus a default admin login.

**Already have a live SwiftCargo Tracker site from an earlier version?** Don't
re-import `schema.sql` — instead import any `sql/migrations/*.sql` files you
haven't run yet, in order, via the same phpMyAdmin Import tab. Each one only
adds new columns/tables and leaves your existing data untouched. See
"Updating an existing site" below.

### 3. Upload the files
Using Hostinger's **File Manager** (or FTP):
1. Upload the entire contents of this repo into `public_html/` (or a subfolder if
   you want the site at a sub-path).
2. Make sure hidden files are included — especially every `.htaccess` file (in the
   root, `config/`, `sql/`, `vendor/`, `includes/`) which blocks direct browser
   access to sensitive folders.

### 4. Configure the app
1. In `config/`, duplicate `config.sample.php` as `config.php`.
2. Fill in your real MySQL credentials from step 2.
3. Set `SITE_URL` to your live domain — `https://yourdomain.com`, with no
   trailing slash. See **Pointing the site at a domain** below for why this
   matters and what breaks if you skip it.

Everything else (SMTP, site name, logo, page content, colors, templates) is
configured from `/admin` after you log in — no other file needs editing.

`config/config.php` is git-ignored on purpose — never commit real credentials.

### Pointing the site at a domain

**`SITE_URL` in `config/config.php` is the only place the site's own web
address is configured.** Nothing else in the codebase hardcodes a domain, so
moving to a new domain later means changing that one line and nothing else.

It's used for every absolute link that leaves the site:

- the "track your shipment" link in status-update emails
- password-reset links in admin recovery emails
- the "track at ..." line printed on the waybill PDF
- the URLs listed in `sitemap.php` for search engines

Leaving it blank makes the site auto-detect its address from each incoming
request, which is fine for a quick throwaway test deploy. **Don't leave it
blank in production.** The detected address comes from the request's own
`Host` header, which the visitor's browser controls — someone can forge it,
request a password reset for your admin email, and have the reset link in
*your* inbox point at *their* server with a valid token attached. Setting
`SITE_URL` explicitly makes that impossible, because the constant always
wins over anything in the request.

Whatever you do, don't leave a *stale* domain in there: a `SITE_URL` still
pointing at an old address is worse than a blank one, because every tracking
link you email to customers will quietly lead to a dead site.

After changing it, the fastest way to confirm it took effect is to open
`https://yourdomain.com/sitemap.php` — every URL listed there is built from
`SITE_URL`, so if the sitemap shows the right domain, so will your emails
and PDFs.

### 5. Log in to the admin panel
Visit `https://your-temp-domain/admin/login.php`.

Default demo login: **admin / ChangeMe123!**

**Change this password immediately** — log in, then go to **My Profile** in
the sidebar to set a real password, and add an email address while you're
there (it lets you log in with your email instead of your username, and is
required for the "Forgot password?" link on the login page to work).

Locked out and don't know the current password? Generate a new hash locally
and update the `admins` table's `password_hash` column via phpMyAdmin:
```bash
php -r "echo password_hash('YourNewPassword', PASSWORD_DEFAULT);"
```

## Setting up email alerts (SMTP, no API) — in the dashboard

Go to **`/admin/smtp_settings.php`** and enter any mailbox's SMTP details —
host, port, username, password, and encryption type. Use the "Send a Test
Email" button to confirm it works before relying on it. This is stored in
the database and takes priority over the `SMTP_*` constants in
`config/config.php`, so you never need to touch code for this again — not
even to switch providers later.

**Options for the mailbox itself:**
- **Your own domain's webmail** (recommended for a real deployment) — in
  hPanel → Emails, create a mailbox, then use `smtp.hostinger.com`, port 587,
  TLS, and that mailbox's address + password.
- **A personal Gmail account** with an [App Password](https://myaccount.google.com/apppasswords) —
  `smtp.gmail.com`, port 587, TLS.
- **[Ethereal](https://ethereal.email)** — a free, instant, throwaway SMTP
  inbox for testing. Emails sent through it never reach a real inbox; view
  them at https://ethereal.email/messages with the same generated
  username/password. Good for trying the whole flow before you have a real
  mailbox ready. Click "Create Ethereal Account" there to get credentials.

`config/config.php`'s `SMTP_*` constants still work as a fallback for a fresh
install before you've visited the dashboard — but the dashboard is the
intended way to manage this.

## Branding & white-labeling

Go to **`/admin/branding.php`** to set the site name and upload a logo (PNG,
JPG, WEBP, GIF, or SVG). This name and logo appear in the header, footer,
staff login, browser tab, and outgoing emails — everywhere the brand shows up
site-wide. There's no dependency between this and your domain name; set
whatever brand you want regardless of what domain you deploy under.

## Adding shipments & tracking updates

1. Log in to `/admin/`.
2. **New Shipment**: fill in sender, receiver (their email is where alerts go),
   package details, and origin/destination. Creating a shipment sends an initial
   "Pending" confirmation email.
3. **Add Update** (from the dashboard): pick a new status, enter the current
   location, add an optional note, and save. This instantly:
   - Updates the shipment's live position (shown on the public tracking map).
   - Adds a new row to the status timeline.
   - Emails the receiver about the change. If the note is left blank, that
     status's default message (see below) fills it in instead.

### Status messages

Every status (Pending, Picked Up, En Route, Customs Clearance, Insurance
Clearance, Out for Delivery, Delivered, On Hold, Delayed, Exception) has a
default explanatory message, editable by any admin at **Status Messages**.
When staff add an update and leave the note blank, that status's message is
used — for the receiver email and the public tracking timeline — so
customers always get a plain-language explanation of what a status means,
not just the status name. Staff can still type a specific note on any
individual update to override it. The message is copied into that update
the moment it's created, so editing a template later never rewrites what
an already-sent update said.

### Payment tracking

Each shipment has a **Payment** section on the New/Edit Shipment form:

- **Full Payment** — an optional price field (leave blank if not decided yet).
- **Payment on Arrival** — same price field, worded for cash-on-delivery —
  the receiver sees "Payment due on arrival" with the amount.
- **Partial Payment** — enter the initial amount expected and the amount
  paid so far; the remaining balance is computed automatically (never
  stored, so it can't drift out of sync) and shown live as you type.

Payment status shows on the admin dashboard, the public tracking page, and
the waybill PDF, all pulling from the same wording via one shared helper
(`payment_status_label()`) so it can't say different things in different
places.

### Insurance status changes

Insurance (the "Shipment is insured" checkbox + declared value on the Edit
Shipment form) can be changed any time after a shipment is created — not
just at booking. Whenever it changes, either direction, the receiver gets
an email: newly insured shows the declared value, insurance removed says
so plainly. This isn't silent — a receiver who was told their shipment
was insured always finds out if that stops being true.

### Turning any address into map coordinates

Every location field (origin, destination, and each tracking update's location)
has a **"Find on map"** button next to it. Paste in any address — a street
address, a home address, a business name, or just a city — and click it; the
latitude/longitude fields fill in automatically.

This works by having the server (`api/geocode.php`) forward your query to
[Nominatim](https://nominatim.org), OpenStreetMap's free, keyless geocoder — the
same project that provides the map tiles. No API key, no signup, no paid
service. It's gated behind admin login since it makes an outbound request per
lookup. If a lookup can't find a precise match (very new addresses, unnamed
rural roads, etc.), just enter coordinates manually — [latlong.net](https://www.latlong.net)
is a reliable fallback for that.

## Updating an existing site

If you already deployed an earlier version of this project:

1. Download the latest code (GitHub → Code → Download ZIP, or `git pull`).
2. Re-upload the changed/new files to `public_html` (your `config/config.php`
   is untouched — don't overwrite it, and it isn't in the ZIP anyway since it's
   git-ignored). Make sure `assets/images/uploads/` and its `.htaccess` come
   along — that's where uploaded logos are stored.
3. Make sure `documents/` and the (larger, now ~15MB) `vendor/` folder come
   along too — that's the waybill/label PDF endpoints and the Dompdf library
   they use. Check with your host that PHP's **GD extension** is enabled
   (it is by default on Hostinger) since the barcode needs it.
4. **Database migration:** if you're updating from before the shipping
   method/packaging/insurance/CMS features existed, import
   `sql/migrations/002_expand_features.sql` once via phpMyAdmin's Import tab
   (see the note above). If you already have that, **no further database
   migration is needed** for branding, SMTP-in-dashboard, the booking wizard,
   the expanded content tabs, or the waybill/label PDFs — none of that
   touches the database schema.
5. **Admin login by email + password reset:** import
   `sql/migrations/003_admin_profile.sql` once to add the `email`,
   `reset_token`, and `reset_token_expires` columns to `admins`. Until you
   run this, the site still works fine — you just won't have the new
   My Profile page, email login, or "Forgot password?" yet.
6. **Admin roles (super admin / regular admin):** import
   `sql/migrations/004_admin_roles.sql` once to add `is_super_admin` and
   `is_active` to `admins`. This migration promotes every existing admin
   account to super admin (so nobody's locked out by running it) — visit
   `/admin/admins.php` afterward to demote accounts that shouldn't have
   full access, or create new regular-admin accounts for staff.
7. **Couriers/carriers + tracking number format:** import
   `sql/migrations/005_couriers_tracking_format.sql` once to add the
   `couriers` table, a nullable `courier_id` column on `shipments`, and the
   `tracking_number_prefix`/`tracking_number_suffix` settings. It seeds a
   starter list of carriers (DHL, UPS, FedEx, USPS, TNT, Aramex, DPD, Royal
   Mail) — manage that list at `/admin/couriers.php`.
8. **Site themes:** import `sql/migrations/006_themes.sql` once to add the
   `themes` table. It seeds 10 preset themes and activates "Classic Red" —
   the site's original look — so this migration causes no visible change
   until a super admin picks something else at `/admin/themes.php`.
9. **Regular-admin color picker + forced password change:** import
   `sql/migrations/007_admin_theme_picker_and_forced_password.sql` once. It
   adds an `is_admin_selectable` flag to `themes` (flagging "Classic Red"
   and a new "Classic Green" preset it seeds) and a `must_change_password`
   flag to `admins`. No visible change until you use the new controls.
10. **Theme contrast fix:** import `sql/migrations/008_theme_contrast_fix.sql`
    once. Two preset themes ("Sunset Orange", "Teal Logistics") had button
    text that fell just short of WCAG AA contrast against their primary
    color; this darkens those two presets' primary colors slightly so
    button text stays readable. Only touches those two rows — any theme
    you've already customized is untouched.
11. **Login security:** import `sql/migrations/009_login_security.sql` once
    to add the `login_attempts` table used for rate-limiting/lockout on
    the login form (see **Login security** below). The login page works
    fine before you run this too — it just skips rate-limiting until the
    table exists, rather than breaking.
12. **Payment tracking + status messages:** import
    `sql/migrations/010_payment_and_status_messages.sql` once. Adds
    `payment_type`/`payment_price`/`payment_initial_amount`/
    `payment_amount_paid` to `shipments` (every existing shipment defaults
    to "Full Payment" with no price set) and seeds the 10 status message
    settings with sensible defaults, editable at **Status Messages**.
13. **Colors split from Templates:** import
    `sql/migrations/011_split_templates_and_colors.sql` once. This splits
    the old combined `themes` table into two independent things: a
    `color_palettes` table (just the 12 colors, managed at
    `/admin/themes.php`, now labeled **Colors**) and a `templates` table
    (structural design — homepage section order, hero treatment, scroll
    animations, and a default logo — managed at the new
    `/admin/templates.php`, **super admin only**). Every existing theme's
    colors become a color palette, and whichever of the 6 structural
    styles matched your active theme becomes the active template — so this
    migration causes no visible change the moment you run it. The old
    `themes` table is renamed to `themes_legacy_backup` (not dropped); safe
    to drop yourself later once you've confirmed everything looks right.
14. **Privacy Policy + Terms of Service:** import
    `sql/migrations/012_privacy_terms_pages.sql` once. Seeds default page
    content (title, lead, and body) for the new `/privacy.php` and
    `/terms.php` pages, editable afterward at **Site Content** under the
    "Privacy Policy" and "Terms of Service" tabs. The seeded text is a
    generic, good-faith starting point, not legal advice — have it
    reviewed before relying on it for a real business.
15. **Admin activity log:** import
    `sql/migrations/013_admin_activity_log.sql` once to add the
    `admin_activity_log` table used by **Activity Log** (see
    **Admin activity log** below). Works fine before you run this too — it
    just skips logging until the table exists, the same fail-open pattern
    as login rate-limiting.

## Login security

The login form (`/admin/login.php`) is hardened against bots and brute
force with a few layers, all self-hosted — nothing to sign up for, no API
key, no third-party service:

- **A math challenge** ("what is 4 + 7?") — trivial for a human, but stops
  the large volume of unsophisticated bots that just fill in a form and
  submit without solving anything.
- **A honeypot field**, invisible to real visitors (off-screen and
  `aria-hidden`), that only a bot filling every field in a scraped form
  would fill in. Tripping it is treated exactly like a wrong password —
  it never reveals that a trap exists.
- **Rate limiting with a temporary lockout**: after 6 failed attempts on
  one account, or 10 from one IP address, within 15 minutes, further
  attempts are blocked (even with the correct password) until the window
  passes. A successful login clears the count for that account and IP.
- **CSRF tokens on every admin form, site-wide** — not just the
  login/password forms. Every POST request to any page behind
  `require_admin()` (dashboard, shipment forms, colors, templates, admin
  accounts, SMTP settings, everything) is checked centrally in
  `require_admin_base()`, so a malicious page an admin happens to have
  open in another tab can't silently submit actions using their logged-in
  session.
- **A 60-minute idle session timeout** for the admin panel — resets on
  every admin page load, so it's 60 minutes of inactivity, not a hard
  60-minute cap on a session you're actively using.
- **A honeypot field on the public contact and shipment-request forms**
  too, not just login — the same invisible-to-humans trick, so a
  submission never reaches your inbox or the database from a bot filling
  in every field of a scraped form.
- **Uploaded SVG images are checked for executable content** (`<script>`
  tags, `onload`/`onerror`/etc. event-handler attributes, `javascript:`
  URIs, embedded `<iframe>`/`<foreignObject>`) and rejected if found —
  SVG can otherwise carry a working XSS payload that a plain "is this an
  image" check wouldn't catch.

**On "reCAPTCHA" specifically:** a real Google reCAPTCHA (or hCaptcha,
Cloudflare Turnstile, etc.) needs a site key and secret key that only the
site owner can generate, tied to their own account and domain via that
provider's console — there's no way to create or embed one on your behalf
without you doing that signup step yourself, and I'm not going to wire the
site to my own keys. If you'd rather have a real reCAPTCHA later, get a
free key pair from
[google.com/recaptcha/admin](https://www.google.com/recaptcha/admin) and
it's a small change to swap it in; until/unless you want that, the
math-challenge + honeypot + rate-limiting combination above requires
nothing from you and stops the same class of automated abuse.

**On "impossible to hack":** no realistic claim can promise that — for
any software, ever. What's actually in place: every database query uses
parameterized statements (no SQL injection surface), all output is
HTML-escaped (no XSS from stored data), passwords are hashed with
bcrypt via PHP's `password_hash()` (never stored or logged in plain
text), session cookies are `HttpOnly` + `SameSite=Lax` (+ `Secure` on
HTTPS), file uploads are validated by content and extension with
randomized filenames, and site-wide security headers are sent on every
response (`X-Content-Type-Options`, `X-Frame-Options`,
`Referrer-Policy`, `Permissions-Policy`, `Strict-Transport-Security` on
HTTPS, and a `Content-Security-Policy` restricting scripts/styles/images
to this site plus the two external hosts it actually uses — the Leaflet
map library's CDN and OpenStreetMap's map tiles). That's a genuinely
solid baseline for a small PHP site — not a guarantee nothing can ever
go wrong.

## System Health check

**System Health** (super admin only, `/admin/health.php`) is a plain-English
"is this installed correctly?" page. It changes nothing — it only looks and
reports — and every item that isn't OK says exactly what to do about it in
non-technical language.

Open it right after moving the site to a new server, and any time something
looks wrong. It checks:

- **The live map files** are present and complete — including the `images/`
  subfolder that file managers most often skip on upload. If the map ever
  stops appearing, this is the first thing to look at.
- **PHP version and extensions** (`gd` for barcodes, `curl` for the address
  lookup, `openssl` for email, and so on), naming what each one is for.
- **The database** connects and has all ten tables the site expects,
  flagging by name any that a missed migration left behind.
- **An active colour palette and template** exist.
- **The uploads folder** exists and can be written to.
- **`SITE_URL`** is set, and actually matches the address you're viewing —
  this catches a stale domain left over from a previous host, which would
  otherwise send every customer a tracking link to a dead site.
- **Email** is configured, and that the "from" address isn't a placeholder
  domain that real mail servers reject.
- **No admin is still using the default installation password.**
- **HTTPS** is in use.
- **The `.htaccess` files** protecting `config/`, `includes/`, `sql/` and
  `vendor/` all uploaded — these start with a dot and are easy to miss.

## Admin activity log

**Activity Log** (super admin only, `/admin/activity_log.php`) shows the
most recent 200 sensitive admin actions — admin accounts created,
promoted, suspended, or deleted; password reset links sent; shipments
deleted; SMTP credentials changed; color palettes and templates
activated or deleted — each with who did it, when, from what IP address,
and a short detail line. It's an audit trail for accountability, not a
full page-view log — routine reads (viewing the dashboard, editing site
content) aren't recorded.

A log entry survives even if the admin account that created it is later
deleted — the row keeps the admin's name as it was at the time, with the
link to the (now-gone) account cleared rather than the whole entry being
lost.

## Admin accounts, roles, and login

There is exactly **one login page** (`/admin/login.php`) for every admin,
regardless of role — there's no separate URL or link for a more privileged
account. After a correct username/email + password, the system looks up
that account's role from the database and takes it from there: a regular
admin lands on the normal dashboard, a super admin additionally gets the
extra nav items below. Nothing about *how* you log in ever differs by role.

There are two kinds of admin account:

- **Regular admin** — full access to shipments, requests, content, images,
  rates, a **Site Color** picker (see below), and their own profile. Can't
  see or manage other admin accounts, and — deliberately — nothing in
  their own screens mentions that a super-admin role exists at all; it's
  documented here for whoever manages the site, not surfaced in the
  day-to-day staff UI.
- **Super admin** — everything a regular admin can do, plus `/admin/admins.php`
  (only visible to super admins): create new admin accounts, edit any
  admin's name/username/email, set a new password for them directly,
  require a password change at their next login, promote/demote super
  admin status, suspend or reactivate an account, send someone a password
  reset link, or delete an account. The system always keeps at least one
  active super admin — you can't suspend, demote, or delete the last one
  (including yourself), so the site can never end up with no one able to
  manage staff access. Also the only role that can reach `/admin/themes.php`
  (all 11 color palettes, full color editing, delete) and
  `/admin/templates.php` (all 6 design templates, full layout/animation/
  logo editing, delete).

Every admin can log in with either their **username or email**, change
their password from **My Profile**, and use **"Forgot password?"** on the
login page to reset it by email (requires an email to be set on the account
first — add one under My Profile).

### Forced password change

A super admin can require any admin to set a new password at their next
login — either as a one-off toggle from **Admin Accounts**, or bundled with
setting them a temporary password directly. The affected admin sees a
plain "set a new password to continue" screen right after logging in
(automatically — not a separate link) and can't reach anything else until
they do; the screen never attributes the requirement to a super admin or
mentions the role at all, consistent with regular admins having no
visibility into that role's existence.

### Go-live alert (optional)

Under **Branding**, you can set a "Go-Live Alert" email. The first time the
site is visited on a given domain, it sends that address a one-time email
confirming the site is live there — useful if you deploy this codebase
somewhere new and want to know the moment it's reachable. It's entirely
opt-in (blank by default), tracked in a plain settings row, and fires once
per domain, not on every visit.

### Couriers/carriers and tracking number format

Under **Couriers & Carriers**, admins can add, rename, deactivate, or delete
the carrier options offered when creating or editing a shipment (DHL, UPS,
FedEx, USPS, or any custom name). A carrier assigned to existing shipments
can be deactivated (hides it from the dropdown) but not deleted, so shipment
history always keeps its carrier name. The chosen carrier shows on the
public tracking page and on the printable waybill/label.

Under **Branding**, the "Tracking Number Format" card lets you set the
prefix and/or suffix used when a tracking number is generated for a new
shipment (default: `SC` prefix, no suffix — e.g. `SC7482913KE`). Changing it
only affects shipments created afterward; existing tracking numbers never
change.

### Colors and Templates (super admin only)

Site-wide look is controlled by **two completely independent choices**,
each with its own admin page and its own database table. Activating a
color never touches the design, and activating a template never touches
color — they're separate on purpose, so you can mix any color with any
design.

#### Colors (`/admin/themes.php`)

Controls the 12-color palette used on every public page, the staff login
and password-reset screens, and the rest of the admin panel.

- **11 preset palettes** ship out of the box (Classic Red, Classic Green,
  Ocean Blue, Emerald Freight, Sunset Orange, Royal Purple, Midnight Navy,
  Charcoal Mono, Teal Logistics, Crimson Express, Amber Cargo). "Classic
  Red" — the site's original look — is active by default.
- **Activate** any palette to make it live site-wide instantly — colors
  only, layout is untouched.
- **Edit Colors** on any palette (preset or custom) to change its 12
  colors individually via color pickers. Editing the *active* palette
  applies immediately.
- **Duplicate** a palette to start a custom variant from an existing
  one's colors, without touching the original.
- **Delete** a palette permanently — presets included. There is no undo.
  Blocked only for the active palette (activate a different one first)
  and the last remaining palette.

**Contrast:** every preset ships verified against WCAG AA contrast ratios
(body text ≥4.5:1 on both the page and soft backgrounds, button text
≥4.5:1 on its button color). Editing a palette's colors shows a live
warning (not a block — it's still your choice) if a chosen pair would
fail that same check, so a custom color combination doesn't silently make
text invisible.

#### Templates (`/admin/templates.php`)

Controls the *structural design* — everything colors don't touch: which
order the homepage sections appear in, the hero section's background
treatment, corner radius and shadow depth, heading typography, scroll-in
animations as you scroll down the homepage, and the site's own default
logo mark.

- **6 preset templates** ship out of the box — Classic, Modern, Minimal,
  Bold, Corporate, and Dark Header — each with a genuinely different
  homepage layout (sections reordered, not just recolored), its own hero
  treatment (gradients, an angled clip-path edge, a dot-pattern overlay,
  or a flat block color depending on the template), and its own scroll
  animation style (fade, fade-up, scale-in, slide-in, or none). "Classic"
  is active by default.
- **Activate** any template to make it live site-wide instantly — design
  only, colors are untouched.
- **Edit** a template to rename it, change its layout, change its scroll
  animation, or upload a custom logo for it (falls back to that
  template's built-in logo mark if you never upload one — a **Branding**
  logo upload always wins over either).
- **Duplicate** a template to start a custom variant from an existing
  one's settings.
- **Delete** a template permanently — presets included. Blocked only for
  the active template and the last remaining template.

Both pages are visible only to super admins — regular (non-super-admin)
accounts can't see or reach `/admin/themes.php`, `/admin/theme_edit.php`,
`/admin/templates.php`, or `/admin/template_edit.php` at all; attempting
to visit any of them redirects back to the dashboard with a permission
error.

### Site Color (regular admins)

Regular admins get a **Site Color** page — not called "Colors" — limited
to switching between exactly two color palettes: **Classic Red** and
**Classic Green**. It's activate-only: no color editing, no delete, no
template access, and no way to reach any other palette. A super admin
decides which palettes carry this "admin-selectable" flag
(`is_admin_selectable` on the `color_palettes` table); by default it's
those same two. Activating either changes the live site color the same
way a super admin's activation does — it's the same shared `is_active`
palette, just reached through a narrower door.

## Managing site content

`/admin/content.php` has tabs for **Home**, **About**, **Services**, **Ship
Now Page**, **Contact**, **Footer**, **Countries**, **Privacy Policy**, and
**Terms of Service** — every headline, paragraph, stat number, service
tier, contact detail, the full list of countries you ship to, and the
legal page content is editable there, no code changes needed. The public
pages read directly from this content. Branding (name/logo) and SMTP are
separate pages — see above — since they're settings rather than marketing copy.

The Privacy Policy and Terms of Service pages (`/privacy.php`, `/terms.php`,
linked in the site footer) ship with generic, good-faith default text
covering what a small shipping site typically collects and how it's used —
it is **not legal advice**, and you should have it reviewed by a lawyer
familiar with your jurisdiction before relying on it for a real business.

## Public shipment requests & the shipping calculator

`/request-shipment.php` is a guided 4-step wizard (Route & Schedule → Package
Details → Service Options → Review & Submit) where any visitor can request a
shipment: what they're shipping, weight/dimensions, packaging type, shipping
method (Air/Sea/Land, with Van/Trailer/Train for Land), Regular/Express
service, optional insurance, and a preferred pickup date/time and method. A
live cost estimate updates as they go and is shown again on the review step
before they submit.

Configure those rates at `/admin/rates.php` — base fee, price per kg, a
multiplier per shipping method, an Express multiplier, and an insurance
percentage. The formula is:
```
estimate = (base_fee + price_per_kg × weight_kg) × method_multiplier × service_multiplier
         + (insured ? declared_value × insurance_percent / 100 : 0)
```

Every submission lands in `/admin/requests.php`, where you can mark it
New/Contacted/Converted/Closed, or click **Convert** to open a pre-filled New
Shipment form (assign a tracking number and it becomes a real, trackable
shipment).

## Printable waybill & shipping label

Every shipment has two downloadable PDF documents, generated on the fly —
nothing pre-rendered or stored:

- **Waybill** (`/documents/waybill.php?tn=<tracking number>`) — a full
  letter-size shipping document: sender/receiver blocks, service and
  shipping-method details, package info, insurance, and a barcode.
- **Label** (`/documents/label.php?tn=<tracking number>`) — a standard
  4"×6" carrier-style label: prominent SHIP TO address, service badge,
  weight/packaging/method, and a large barcode — sized to print on a real
  label printer or a normal sheet of paper.

Both open inline in the browser's PDF viewer (with its own print/save/download
controls) so "print" and "save as PDF" both just work, from any device.

**Where to find them:**
- **Staff**: on `/admin/dashboard.php`, every shipment row has **Waybill** and
  **Label** links.
- **Receivers**: on the public tracking page (`/track.php?tn=...`), right
  below the status badge, there are **Print Waybill (PDF)** and **Print Label
  (PDF)** buttons — anyone who has the tracking number can print their own
  copy, the same way a real courier's tracking page would let you reprint a
  label.

The barcode is a genuine Code 39 barcode (verifiable with any barcode
scanner/app) rendered by a barcode encoder hand-written for this project in
`includes/barcode.php` — not a third-party barcode API.

## Devices and browsers

**Layout** was verified in a real Chromium engine at nine viewport sizes —
320px (small Android), 360px (Galaxy S20), 375px (iPhone SE), 393px
(iPhone 14 Pro), 430px (iPhone 14 Pro Max), 768px (iPad Mini), 1024px
(iPad Pro), 1366px (laptop) and 1920px (desktop) — across every public
page and every admin page, checking for horizontal overflow, elements
escaping the viewport, and unreadably small text. All clean. Wide content
that genuinely can't shrink (data tables, the barcode) scrolls inside its
own container rather than pushing the page sideways.

**Touch** interactions are verified too, not just layout: the mobile menu,
the admin sidebar, the multi-step booking wizard, and the row action menus
were each driven with real emulated taps on an iPhone-profile browser.
Tap targets on mobile are at least 32px tall.

**Browser support floor** is roughly **Chrome/Edge 90+, Firefox 81+, and
Safari 16+ (iOS 16+)** — that's a 2022-and-newer baseline covering
effectively all traffic today. The JavaScript is deliberately plain ES5
(`var`, `function`, no arrow functions, no template literals), so nothing
needs transpiling; the floor comes from CSS, mainly `overflow-x: clip`,
plus `aspect-ratio` and flexbox `gap`. On an older browser those rules are
simply ignored — the page still renders and works, it just loses a little
polish. Nothing is behind a feature that silently breaks the layout.

### Keeping the live map working

The live map is the core of the product, so it's the most defended part of
the codebase. Nothing outside your own server is required for it to load:

- **Leaflet (the map library) is served from your own site**, from
  `assets/vendor/leaflet/` — not a CDN. A CDN outage, a corporate
  firewall, an ad blocker, or a country blocking that CDN can no longer
  take the map down. (The Content-Security-Policy no longer lists any
  external script or style host at all.)
- **Map tiles are the only outside request left**, from OpenStreetMap. If
  they fail — outage, blocked host, rate limit, patchy mobile signal — the
  map still loads, pans and zooms, and still draws the route, the
  checkpoints and the live position. Only the background imagery is
  missing, and a short note under the map explains that the position shown
  is still accurate.
- **Bad coordinates can't break it.** Latitude/longitude are range-checked
  when an admin saves a shipment or a tracking update, so a typo (a
  longitude pasted without its decimal point, say) is rejected with a
  plain-English message instead of being stored. The map *also* re-checks
  every coordinate independently before use, so even a bad row saved some
  other way is clamped or skipped rather than breaking the map — worth
  knowing that an out-of-range value silently puts a marker in the wrong
  place, while a non-numeric one would otherwise throw an error that kills
  the whole map.
- **Every remaining failure explains itself.** If the map genuinely can't
  be shown, the map area says so and points out that the full status
  history below is still current — instead of a blank grey box. That
  message is produced three independent ways (inside the map script, by an
  inline safety net on the page itself that can't be blocked, and by a
  `<noscript>` block), so there is no single file whose failure leaves the
  visitor with no explanation.

All of the above is verified by driving a real browser with each failure
forced: tiles blocked, the library blocked, the map script blocked, the
live-updates endpoint blocked, JavaScript switched off entirely, and
shipments with deliberately corrupt coordinates. In every case the page
stays usable and the status timeline stays complete.

**If a script gets blocked.** Ad blockers, corporate firewalls, privacy
extensions, country-level CDN blocks and plain old outages are all facts of
life, so the site is built to degrade rather than break:

- **The homepage never depends on JavaScript to be readable.** Sections
  animate in as you scroll, but that hiding is opt-in via a class that
  JavaScript itself adds, and a failsafe timer removes it again if the
  animation script doesn't start within two seconds. With JavaScript
  disabled entirely, or the script blocked, every section renders normally
  with no animation. (Verified in all three states.)
- **Nothing else is third-party at all.** No analytics, no ad scripts, no
  webfonts, no jQuery, no tracking pixels. Emails go over plain SMTP and
  PDFs are generated on your own server, so there's no external service
  that can disappear and take a feature with it.

**No CDN to remove — it's already done.** Leaflet used to load from
`cdn.jsdelivr.net`; it is now vendored in `assets/vendor/leaflet/` (v1.9.4,
the official distribution, with its LICENSE alongside). Nothing on the site
loads from an external host any more except OpenStreetMap tile images, and
the map degrades gracefully without those. To update Leaflet later, replace
the files in that folder with a newer release from
[leafletjs.com/download.html](https://leafletjs.com/download.html) — keep
the `images/` folder next to the CSS, since the CSS references it.

## Production-readiness polish

- **`/404.php`** — a branded not-found page (same header/footer/theme as the
  rest of the site) instead of the host's generic error page, wired up via
  `ErrorDocument 404 /404.php` in the root `.htaccess`.
- **`/robots.txt` and `/sitemap.php`** — search engines are pointed at the
  public pages and kept out of `/admin/`, `/api/`, `/documents/`, `/config/`,
  `/includes/`, and `/sql/`. The sitemap is a `.php` file (not a static
  `.xml`) so its URLs always match whatever domain this is deployed under.
- **Shipment request emails**: submitting `/request-shipment.php` now sends
  the customer a confirmation email (reference number + estimate) and
  notifies your `contact_email` (set under Site Content) of the new lead —
  previously a request just sat in `/admin/requests.php` with nothing
  telling you it arrived. Both are best-effort: if SMTP isn't configured yet,
  the request still saves and the visitor still sees their confirmation
  page — only the emails are skipped.
- **Every email and PDF now follows the active color palette**
  (`/admin/themes.php`) instead of being hardcoded to the original
  DHL-style red/yellow. Tracking-update emails, password-reset emails, the
  contact-form notification, and the waybill/label PDFs all pull from
  `get_active_palette()` — switch colors and everything customers and staff
  see matches, not just the website.
- **`/.well-known/security.txt`** ([RFC 9116](https://www.rfc-editor.org/rfc/rfc9116)) —
  a standard place for a security researcher to find how to report a
  vulnerability responsibly, instead of guessing or going public first.
  **Edit the placeholder contact email in that file before you go live** —
  it ships pointing at `security@example.com`.
- A full bug-scan pass before launch added: CSRF protection on every admin
  form (not just login), a 60-minute admin idle timeout, a honeypot on the
  public contact and shipment-request forms, stricter SVG upload
  validation, numeric validation on manually-entered map coordinates, an
  admin activity log, and Privacy Policy / Terms of Service pages — see
  **Login security** and **Admin activity log** above for the security
  items, and **Managing site content** for the legal pages.

## Security notes

- `config/config.php`, `sql/`, `vendor/`, and `includes/` all have `.htaccess`
  files denying direct web access — only PHP scripts that `require` them can read
  their contents.
- **The waybill and label expose full sender/receiver addresses to anyone
  with the tracking number** — this is intentional, matching the request that
  receivers be able to print their own label/waybill from the tracking page,
  and it's inherent to what a shipping label is. It's a bit more than the
  public tracking page itself shows (which only shows the receiver's name).
  If that's not the trade-off you want for a real deployment, gate
  `documents/waybill.php` and `documents/label.php` behind `require_admin()`
  and remove the public-facing buttons on `track.php`.
- Passwords are hashed with PHP's `password_hash()` (bcrypt).
- All DB queries use PDO prepared statements.
- All user-supplied output is escaped with `htmlspecialchars()` via the `h()` helper.
- `assets/images/uploads/` (logo uploads) has its own `.htaccess` that blocks
  PHP execution from that folder, and uploaded files are validated as real
  images (or scanned for `<script` tags for SVGs) before being saved.
- The SMTP password saved via `/admin/smtp_settings.php` is stored in the
  same database as everything else — treat your database credentials as
  sensitive, same as you already do for `config/config.php`.
- Change the default admin password before sharing your temporary domain publicly.

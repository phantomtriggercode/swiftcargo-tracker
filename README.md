# SwiftCargo Tracker

A DHL-style shipping & live package tracking website built in **plain PHP + MySQL**
so it runs on Hostinger's cheapest shared hosting plan (which only supports PHP/MySQL,
not Node.js) using the free **temporary domain** Hostinger gives you before you buy a
real domain.

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
- A full **content management system**: Home/About/Contact/Footer copy and the
  supported-countries list are all editable from the admin panel — nothing is
  hardcoded.
- A public **"Request a Shipment"** page with a live, admin-configurable cost
  calculator; submissions land in an admin queue that can be reviewed and
  converted straight into a real shipment.

### "No API" — what that means here

No paid/keyed third-party APIs are used anywhere:

- **Map**: [Leaflet.js](https://leafletjs.com) + free [OpenStreetMap](https://www.openstreetmap.org)
  tiles — no Google Maps API key, no billing account, no signup.
- **Email**: [PHPMailer](https://github.com/PHPMailer/PHPMailer) talking directly to
  an SMTP server over the standard SMTP protocol — the same way Outlook or Thunderbird
  sends mail. No SendGrid/Mailgun/SES API involved.
- **Live updates**: the map polls a small JSON endpoint that is part of this app
  (`api/track.php`), not an external API.

The only "endpoint" the JS calls is your own server, and the only external network
calls the PHP backend makes are (a) loading Leaflet's JS/CSS from its CDN and map
tiles from OpenStreetMap — both free, keyless, static assets — and (b) an SMTP
connection to send email.

## Tech stack

- PHP 8+ (no framework — plain scripts + includes)
- MySQL / MariaDB
- [PHPMailer](https://github.com/PHPMailer/PHPMailer) (vendored via Composer, included in this repo's `vendor/` folder — no Composer needed on the server)
- [Leaflet.js](https://leafletjs.com) + OpenStreetMap tiles (loaded from CDN, no build step)

## Project structure

```
config/         Database + SMTP config (config.php is git-ignored — copy config.sample.php)
sql/            schema.sql (fresh installs) + migrations/ (updates for an existing DB)
includes/       Shared PHP: db helpers, auth, mailer, settings/CMS helper, header/footer
admin/          Staff panel: dashboard, shipments, tracking updates, requests, content, rates
api/            track.php (live map polling) + geocode.php (address lookup)
assets/         CSS + JS
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
re-import `schema.sql` — instead import `sql/migrations/002_expand_features.sql`
once via the same phpMyAdmin Import tab. It only adds the new columns/tables and
leaves your existing shipments untouched. See "Updating an existing site" below.

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
3. Fill in SMTP credentials (see next section for email).
4. Set `SITE_URL` to your Hostinger temporary domain, e.g.
   `https://yourname.hostingerapp.com`.

`config/config.php` is git-ignored on purpose — never commit real credentials.

### 5. Log in to the admin panel
Visit `https://your-temp-domain/admin/login.php`.

Default demo login: **admin / ChangeMe123!**

**Change this password immediately** — either add a "change password" flow, or
generate a new hash locally with:
```bash
php -r "echo password_hash('YourNewPassword', PASSWORD_DEFAULT);"
```
and update the `admins` table's `password_hash` column via phpMyAdmin.

## Setting up email alerts (SMTP, no API)

This project currently ships configured for **Ethereal** — a free fake-SMTP inbox
meant for development/demos. Emails sent through it never reach a real inbox; you
view them in a web inbox instead. This is intentional for a first demo deployment
so you can see the full flow working without handing over real email credentials.

**To try it right now:**
1. Go to https://ethereal.email and click "Create Ethereal Account" (free, instant,
   no signup form beyond a click).
2. Copy the generated SMTP **host, username, and password** into `config/config.php`
   (`SMTP_HOST`, `SMTP_USER`, `SMTP_PASS`).
3. Add a tracking update from the admin panel — the "email sent" flash message will
   confirm it went out.
4. View the sent email by logging in at https://ethereal.email/messages with the
   same generated username/password.

**To send real emails to real inboxes later**, swap in real SMTP credentials — for
example your own Hostinger email mailbox (hPanel → Emails → create a mailbox, then
use `smtp.hostinger.com`, port 587, your mailbox address + password), or a personal
Gmail account with an [App Password](https://myaccount.google.com/apppasswords).
No code changes needed — just update the `SMTP_*` constants in `config/config.php`.

## Adding shipments & tracking updates

1. Log in to `/admin/`.
2. **New Shipment**: fill in sender, receiver (their email is where alerts go),
   package details, and origin/destination. Creating a shipment sends an initial
   "Pending" confirmation email.
3. **Add Update** (from the dashboard): pick a new status, enter the current
   location, add an optional note, and save. This instantly:
   - Updates the shipment's live position (shown on the public tracking map).
   - Adds a new row to the status timeline.
   - Emails the receiver about the change.

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
   git-ignored).
3. In phpMyAdmin, open the **Import** tab and upload `sql/migrations/002_expand_features.sql`
   — run it once. It adds the new columns and tables (shipping method, packaging,
   insurance, expanded statuses, site content, calculator rates, shipment requests)
   without touching your existing shipments.

## Managing site content

`/admin/content.php` has tabs for **Home**, **About**, **Contact**, **Footer**, and
**Countries** — every headline, paragraph, stat number, contact detail, and the
full list of countries you ship to is editable there, no code changes needed.
The public `/countries.php` page and the footer read directly from this content.

## Public shipment requests & the shipping calculator

`/request-shipment.php` lets any visitor request a shipment: what they're
shipping, weight/dimensions, packaging type, shipping method (Air/Sea/Land, with
Van/Trailer/Train for Land), Regular/Express service, optional insurance, and a
preferred pickup date/time and method. As they fill it in, a live cost estimate
updates instantly using the rates you've configured.

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

## Security notes

- `config/config.php`, `sql/`, `vendor/`, and `includes/` all have `.htaccess`
  files denying direct web access — only PHP scripts that `require` them can read
  their contents.
- Passwords are hashed with PHP's `password_hash()` (bcrypt).
- All DB queries use PDO prepared statements.
- All user-supplied output is escaped with `htmlspecialchars()` via the `h()` helper.
- Change the default admin password before sharing your temporary domain publicly.

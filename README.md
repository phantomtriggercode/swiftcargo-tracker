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

The only "endpoint" the JS calls is your own server, and the only external network
calls the PHP backend makes are (a) loading Leaflet's JS/CSS from its CDN and map
tiles from OpenStreetMap — both free, keyless, static assets — and (b) an SMTP
connection to send email.

## Tech stack

- PHP 8+ (no framework — plain scripts + includes)
- MySQL / MariaDB
- [PHPMailer](https://github.com/PHPMailer/PHPMailer) and [Dompdf](https://github.com/dompdf/dompdf)
  (vendored via Composer, included in this repo's `vendor/` folder — no Composer needed on the server)
- [Leaflet.js](https://leafletjs.com) + OpenStreetMap tiles (loaded from CDN, no build step)
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
2. Fill in your real MySQL credentials from step 2. That's the only thing
   that *must* be set here — everything else (SMTP, site name, logo, page
   content) is configured from `/admin` after you log in (see below), and
   `SITE_URL` auto-detects your domain if you leave it as-is.

`config/config.php` is git-ignored on purpose — never commit real credentials.

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

## Admin accounts, roles, and login

There are two kinds of admin account:

- **Regular admin** — full access to shipments, requests, content, images,
  rates, and their own profile. Can't see or manage other admin accounts.
- **Super admin** — everything a regular admin can do, plus `/admin/admins.php`
  (only visible to super admins): create new admin accounts, promote/demote
  super admin status, suspend or reactivate an account, send someone a
  password reset link, or delete an account. The system always keeps at
  least one active super admin — you can't suspend, demote, or delete the
  last one (including yourself), so the site can never end up with no one
  able to manage staff access.

Every admin can log in with either their **username or email**, change
their password from **My Profile**, and use **"Forgot password?"** on the
login page to reset it by email (requires an email to be set on the account
first — add one under My Profile).

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

## Managing site content

`/admin/content.php` has tabs for **Home**, **About**, **Services**, **Ship
Now Page**, **Contact**, **Footer**, and **Countries** — every headline,
paragraph, stat number, service tier, contact detail, and the full list of
countries you ship to is editable there, no code changes needed. The public
pages read directly from this content. Branding (name/logo) and SMTP are
separate pages — see above — since they're settings rather than marketing copy.

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

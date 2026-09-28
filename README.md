# Shipping & Live Tracking Site

A courier and freight website with customer-facing shipment tracking and a
staff panel for creating shipments and posting updates.

Everything a customer sees is editable from the staff panel rather than in
the code: the company name, logo, colours, page copy, service list,
countries served, carriers, shipment statuses, pricing for the quote
calculator, and the text of the email alerts. The name used in the demo
data is a placeholder, not a brand: the site is set up as whatever company
runs it, from the panel, with no code edits.

## What it runs on

PHP 8 and MySQL, with no framework and no build step. Two libraries ship
with the code (one for sending mail over SMTP, one for generating PDF
waybills); everything else is written for this site. The map is served
from this site rather than from a content delivery network, so it keeps
working on networks that block third-party hosts.

That combination is deliberate: it runs on ordinary shared hosting with
nothing to install, no background process to keep alive, and no monthly
service to subscribe to.

## Running it

Upload the files, create a MySQL database, import `sql/schema.sql`, and
copy `config/config.sample.php` to `config/config.php` with your database
and mailbox details. Everything after that is done from the staff panel.

Full setup, deployment and day-to-day operating instructions are in
`docs/OPERATIONS.md`. That file is for whoever runs the site: it is
blocked from being served over the web, and can be deleted from the live
server once the site is up, since nothing on the site reads it.

## Layout

```
admin/       Staff panel
api/         Small JSON endpoints this site's own pages call
assets/      Stylesheet, scripts, images, and the map library
config/      Database and mail credentials (not in version control)
docs/        Operator guide
documents/   Printable waybill and shipping label
includes/    Shared code: settings, security, mail, page header/footer
sql/         Database structure and starter data
vendor/      The two third-party libraries
```

## Before going live

- Change the password on the first staff account. The panel asks you to
  on first sign-in.
- Set `SITE_URL` in `config/config.php` to the site's real address.
- Put a real address in `.well-known/security.txt`.
- Keep `config/config.php` out of version control. It holds credentials
  and is already listed in `.gitignore`.

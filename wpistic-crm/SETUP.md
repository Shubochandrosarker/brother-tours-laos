# WPistic CRM — Site Setup Guide

Step-by-step deployment guide for installing the plugin on **guns2ammo.com** and
configuring it for production.

---

## 1. Build a shippable zip

From the repo root:

```bash
./build-zip.sh
# → ./dist/guns2ammo-crm-0.5.0.zip
```

The script ships the PHP source + the prebuilt React dashboard (`admin/app/dist/`)
and strips the dev toolchain (`node_modules`, `src/`, `package*.json`, etc.) so
the zip is small and the live site never needs Node.

If you'd rather build manually:

```bash
cd guns2ammo-crm/admin/app && npm install && npm run build
cd ../../..
zip -r guns2ammo-crm.zip guns2ammo-crm \
  -x "guns2ammo-crm/admin/app/node_modules/*" \
     "guns2ammo-crm/admin/app/src/*" \
     "guns2ammo-crm/admin/app/package*.json" \
     "guns2ammo-crm/admin/app/vite.config.js" \
     "guns2ammo-crm/admin/app/index.html" \
     "guns2ammo-crm/admin/app/.gitignore" \
     "guns2ammo-crm/admin/app/README.md"
```

---

## 2. Install on guns2ammo.com

**Via WP Admin:** Plugins → Add New → Upload Plugin → pick the zip → **Activate**.

**Via SSH/SFTP:** drop the unzipped `guns2ammo-crm/` folder into
`wp-content/plugins/` on the server, then activate from the Plugins screen.

On activation the plugin will:

- Create all CRM tables (`wp_g2a_crm_customers`, `wp_g2a_crm_bookings`, …).
- Register 8 custom roles (see [§4 Roles & Permissions](#4-roles--permissions)).
- Grant the administrator every `g2a_crm_*` capability automatically.
- Seed default message templates.
- Flush rewrite rules.

**Verify activation succeeded** — run from the server:

```bash
wp option get g2a_crm_db_version       # → 5
wp db tables 'wp_g2a_crm_*' --format=csv | head
```

---

## 3. Required settings

There is **no settings UI yet** — config lives in a single `wp_options` row
(`g2a_crm_settings`, a serialized array). Use WP-CLI to set values one at a time:

```bash
# Business identity (used in emails, unsubscribe page, kiosk header)
wp option patch update g2a_crm_settings business_name      "WPistic"
wp option patch update g2a_crm_settings business_email     "hello@guns2ammo.com"
wp option patch update g2a_crm_settings business_phone     "+1-555-0100"
wp option patch update g2a_crm_settings business_timezone  "America/New_York"

# Email "From" header (otherwise wp_mail uses wordpress@guns2ammo.com)
wp option patch update g2a_crm_settings email_from_name    "WPistic CRM"
wp option patch update g2a_crm_settings email_from_address "no-reply@guns2ammo.com"
```

### Test mode (recommended during onboarding)

Logs every outgoing email/SMS to PHP error log instead of actually sending —
great for staging or the first hour after going live:

```bash
wp option patch update g2a_crm_settings messaging_test_mode true
# Once you're confident:
wp option patch update g2a_crm_settings messaging_test_mode false
```

### Kiosk

The customer-facing check-in / waiver kiosk is **disabled by default**. Enable
when you're ready to put a tablet at the counter:

```bash
wp option patch update g2a_crm_settings kiosk_enabled true
```

Kiosk URL: `https://guns2ammo.com/?g2a_kiosk=1` (no login required, rate-limited
to 30 requests / 10 min per IP).

---

## 4. Roles & permissions

The plugin creates these roles on activation. Assign existing staff users to
them via **Users → Edit User → Role**.

| Role | Use for | Highlights |
|------|---------|------------|
| `crm_owner` | Co-founder / GM | Everything except WP admin |
| `crm_manager` | Store manager | Full CRM minus role/settings |
| `crm_compliance_officer` | FFL/4473 compliance lead | Waivers, sensitive records, audit logs |
| `crm_range_staff` | RSOs / range counter | View customers/bookings, edit bookings |
| `crm_instructor` | Class instructors | Class roster + their own bookings |
| `crm_sales` | Front desk / phone sales | Customers, leads, bookings, messaging |
| `crm_marketing` | Email/SMS campaigns | Templates + send permission |
| `crm_accountant` | Bookkeeper | Reports + exports only |

All 25 `g2a_crm_*` capabilities are also granted to the `administrator` role,
so your existing admin account works out of the box.

---

## 5. Email deliverability

`wp_mail()` defaults to PHP `mail()` which most managed hosts mark as spam.
**Strongly recommend** installing one of these alongside this plugin:

- [WP Mail SMTP](https://wordpress.org/plugins/wp-mail-smtp/) — easiest, routes
  through SendGrid / SES / Postmark / your SMTP host.
- [Post SMTP](https://wordpress.org/plugins/post-smtp/) — alternative with
  built-in logging.

Set the SMTP "From" to match the address you set in
`g2a_crm_settings.email_from_address` so SPF/DKIM line up.

---

## 6. SMS via Twilio (optional)

By default `sms_provider = log_only` — SMS sends are written to the PHP error
log only. To go live with real SMS:

```bash
wp option patch update g2a_crm_settings sms_provider     "twilio"
wp option patch update g2a_crm_settings sms_twilio_sid   "ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
wp option patch update g2a_crm_settings sms_twilio_token "your-twilio-auth-token"
wp option patch update g2a_crm_settings sms_twilio_from  "+15555550100"   # Twilio-purchased number
```

The CRM unsubscribe link (channel=sms) is appended to outbound SMS by the
message renderer. Twilio's STOP keyword handling is independent — make sure
your Twilio number has Advanced Opt-Out enabled in the Twilio console.

---

## 7. Cron (reminders + queued messages)

The reminder scheduler and message dispatcher run on `wp_cron`, which only
fires when someone visits the site. For a live business you want a **real
system cron**:

```bash
# Disable virtual WP-Cron in wp-config.php:
echo "define('DISABLE_WP_CRON', true);" >> /path/to/wp-config.php

# Add a system cron entry (every minute):
crontab -e
* * * * * curl -sS https://guns2ammo.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```

The dispatcher honors `messaging_test_mode` — safe to leave on during this
switchover.

---

## 8. Public URLs to know

| Purpose | URL pattern | Auth |
|---------|-------------|------|
| Kiosk | `/?g2a_kiosk=1` | None (requires `kiosk_enabled = true`) |
| Unsubscribe | `/?g2a_unsubscribe=1&customer=…&channel=…&token=…` | Signed token, generated in messages |
| Waiver view (signed) | `/?g2a_waiver_view=<id>&token=<sig>` | Signed token (used in emails / PDFs) |
| Waiver view (staff) | `/?g2a_waiver_view=<id>` | Logged-in user with `g2a_crm_view_waivers` |
| Admin dashboard | `/wp-admin/admin.php?page=g2a-crm` | Requires `g2a_crm_view_customers` |
| REST API root | `/wp-json/g2a-crm/v1/` | WP REST nonce, capability per route |

If kiosk or unsubscribe URLs 404 right after activation, visit
**Settings → Permalinks → Save Changes** to force a rewrite-rules flush.

---

## 9. Smoke test checklist

After activating and configuring:

- [ ] `wp-admin/admin.php?page=g2a-crm` loads the React dashboard (no "build needed" banner).
- [ ] `GET /wp-json/g2a-crm/v1/reports/dashboard` returns JSON when logged in.
- [ ] Create a test customer → create a booking → check it in.
- [ ] Send a test email from **Messages → Send** with `messaging_test_mode = true` and confirm it shows up in the PHP error log.
- [ ] Turn off test mode, send a real email to yourself, confirm receipt.
- [ ] Visit `/?g2a_kiosk=1` on a phone with kiosk enabled — confirm the check-in form renders.
- [ ] Open **Audit Logs** in the dashboard — every action above should appear.

---

## 10. Upgrade / redeploy

When you ship a new version:

```bash
# Bump version in guns2ammo-crm/guns2ammo-crm.php and readme.txt, then:
./build-zip.sh
```

Upload via **Plugins → Add New → Upload** and WP will offer to "Replace
current with uploaded". Database migrations run automatically on next admin
page load — `G2A_CRM_Plugin::maybe_upgrade_db()` compares
`g2a_crm_db_version` against the constant in the bootstrap file.

To skip data deletion on uninstall, drop this into `wp-config.php`:

```php
define( 'G2A_CRM_KEEP_DATA_ON_UNINSTALL', true );
```

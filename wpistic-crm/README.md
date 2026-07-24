# WPistic CRM

Compliance-aware CRM and operations dashboard for WPistic — a WordPress plugin that lives inside WP-Admin.

**Status:** v1.0.0 — production release. All six phases from the blueprint ship. Subsequent work will integrate the standalone POS system.

**Phase 1:** customers, leads, tasks, audit log, REST API, React dashboard shell, 8 CRM roles.

**Phase 2:** bookings + calendar + Mission Check-In, waivers with HTML print view, public kiosk endpoint at `?g2a_kiosk=1`, WP-cron for booking reminders / waiver expiry / overdue tasks.

**Phase 3:** memberships (renew / cancel / suspend with customer-status sync), classes + roster (capacity-enforced, instructor-of-record permissions), class auto-completion with follow-up, renewal warnings, past-due staff tasks.

**Phase 4:** communication center. Dispatcher (every 5 min) sends queued reminders + manual messages via wp_mail() or Twilio. Template renderer with `{{vars}}`, seeded defaults, opt-in tracking, public unsubscribe endpoint at `?g2a_unsubscribe=1`, dedup_key idempotency, exponential-backoff retry.

**Phase 5:** full reporting + premium dashboard. 6 detailed reports with date-range filters (Revenue, Lead Pipeline, Operations, Membership Health, Staff Performance, Communications). CSV exports for 6 entity types. MRR computed from `memberships.monthly_amount`. Inline SVG sparklines, no chart library.

---

## Installation

1. Drop the `guns2ammo-crm` folder into `wp-content/plugins/`.
2. Build the React dashboard once:
   ```bash
   cd wp-content/plugins/guns2ammo-crm/admin/app
   npm install
   npm run build
   ```
   Vite emits `admin/app/dist/` and the PHP loader picks it up automatically via the Vite manifest.
3. Activate **WPistic CRM** from the Plugins screen. Activation creates the tables and CRM roles.
4. Open **G2A CRM** in the admin sidebar.

If you see a "build needed" message in the dashboard, step 2 hasn't been run yet — the PHP layer is live but there's no compiled JS to mount.

## Dev workflow

```bash
cd admin/app
npm run dev      # hot reload at http://localhost:5173 (won't run inside WP — use for component work)
npm run build    # production build → admin/app/dist/
```

Most CRM work happens against a real WP install: rebuild and reload WP-Admin.

## Tests

PHPUnit + the official WP test scaffold. Locally:

```bash
composer install
bash bin/install-wp-tests.sh wp_test root '' 127.0.0.1
composer test
```

CI runs the same matrix (PHP 7.4 / 8.1 / 8.2 against WP latest) on every push and pull request — see `.github/workflows/ci.yml`. Coverage so far focuses on the security boundaries (kiosk session token, webhook HMAC, role capabilities) and the bug fixes from the Phase 6 audit (customer merge moving integrations rows, integrations repository upsert idempotency). Add new tests under `tests/test-*.php`.

## Architecture

```
guns2ammo-crm.php           — plugin bootstrap, constants, hook wiring
includes/
  class-plugin.php          — orchestrator, REST route registration, on-load DB upgrade check
  class-activator.php       — install tables + roles + defaults on activate
  class-deactivator.php     — flush rewrite rules (non-destructive)
  class-db.php              — every table dbDelta'd here; idempotent
  class-permissions.php     — 25+ capabilities mapped to 8 CRM roles
  class-audit-log.php       — record/diff/list helpers; every sensitive write goes through here
  class-settings.php        — wp_options-backed settings with defaults
  helpers.php               — table-name, sanitize, IP, UA, allow-list helpers
  modules/<module>/         — data access (repositories) per domain
  rest/                     — REST controllers, all extend G2A_CRM_REST_Base_Controller
  services/
    class-waiver-view-service.php  — signed-token HTML print view (browser Save-as-PDF)
    class-reminder-scheduler.php   — WP-cron: booking reminders, waiver expiry, overdue tasks
admin/
  class-admin-menu.php      — WP-Admin menu, asset enqueue (reads Vite manifest)
  views/dashboard.php       — mount point for the React app
  app/                      — React + Vite source
public/
  class-kiosk.php           — public ?g2a_kiosk=1 endpoint + kiosk REST routes (own nonce)
  views/kiosk.php           — self-service customer waiver flow
uninstall.php               — drops tables + roles + options
readme.txt                  — WordPress.org plugin readme
```

## REST API

All routes are under `/wp-json/g2a-crm/v1/`. Auth uses the WP nonce (`X-WP-Nonce` header).

| Resource | Methods | Capability |
|----------|---------|------------|
| `/customers` | GET, POST | `g2a_crm_view_customers` / `g2a_crm_edit_customers` |
| `/customers/{id}` | GET, PUT, DELETE | view / edit / delete |
| `/customers/{id}/timeline` | GET | view |
| `/leads` | GET, POST | view_leads / edit_leads |
| `/leads/{id}` | GET, PUT, DELETE | view / edit / delete |
| `/leads/{id}/convert` | POST | edit_leads |
| `/leads/{id}/activity` | GET, POST | view / edit |
| `/tasks` | GET, POST | view_tasks / edit_tasks |
| `/tasks/{id}` | GET, PUT, DELETE | view / edit |
| `/tasks/{id}/complete` | POST | edit_tasks |
| `/bookings` | GET, POST | view_bookings / edit_bookings |
| `/bookings/calendar` | GET | view_bookings |
| `/bookings/{id}` | GET, PUT, DELETE | view / edit |
| `/bookings/{id}/check-in` | POST | edit_bookings (verify_waivers can override) |
| `/bookings/{id}/cancel` | POST | cancel_bookings |
| `/bookings/{id}/complete` | POST | edit_bookings |
| `/waivers` | GET, POST | view_waivers |
| `/waivers/{id}` | GET | view_waivers |
| `/waivers/{id}/verify` | POST | verify_waivers |
| `/waivers/{id}/reject` | POST | verify_waivers |
| `/check-in/lookup` | GET | view_bookings |
| `/memberships` | GET, POST | view_memberships / edit_memberships |
| `/memberships/{id}` | GET, PUT, DELETE | view / edit |
| `/memberships/{id}/renew` | POST | edit_memberships |
| `/memberships/{id}/cancel` | POST | edit_memberships |
| `/memberships/{id}/suspend` | POST | edit_memberships |
| `/classes` | GET, POST | view_classes / edit_classes |
| `/classes/{id}` | GET, PUT, DELETE | view / edit |
| `/classes/{id}/cancel` | POST | edit_classes |
| `/classes/{id}/students` | GET, POST | view_classes (or instructor of record) / manage_class_roster |
| `/classes/{id}/students/{student_id}` | PUT | manage_class_roster |
| `/messages` | GET | view_customers |
| `/messages/{id}` | GET | view_customers |
| `/messages/{id}/cancel` | POST | send_email or send_sms |
| `/messages/{id}/retry` | POST | send_email or send_sms |
| `/messages/send-email` | POST | send_email |
| `/messages/send-sms` | POST | send_sms |
| `/messages/dispatch-now` | POST | send_email or send_sms |
| `/message-templates` | GET, POST | send_email (read) / manage_templates (write) |
| `/message-templates/{id}` | PUT, DELETE | manage_templates |
| `/message-templates/preview` | POST | send_email |
| `/kiosk/check-in` | POST | public — X-G2A-Kiosk-Nonce + rate limit |
| `/kiosk/waiver` | POST | public — X-G2A-Kiosk-Nonce + rate limit |
| `/reports/dashboard` | GET | view_reports |
| `/reports/revenue` | GET | view_reports |
| `/reports/pipeline` | GET | view_reports |
| `/reports/operations` | GET | view_reports |
| `/reports/membership` | GET | view_reports |
| `/reports/staff` | GET | view_reports |
| `/reports/communications` | GET | view_reports |
| `/reports/export?type=X` | GET | export_data (streams CSV) |
| `/audit-logs` | GET | view_audit_logs |

## Kiosk

To run a self-service waiver kiosk at the front desk:

1. Open **G2A CRM → Settings** in WP-Admin (or set `kiosk_enabled = true` in the `g2a_crm_settings` option).
2. Point a tablet browser at `https://yoursite.com/?g2a_kiosk=1`.
3. Each session uses a one-time nonce; the endpoint is rate-limited to 30 requests per 10 minutes per IP.

## WP-cron

Two events are scheduled on plugin boot:

- `g2a_crm_cron_hourly` — queues booking reminders (24h lead time, configurable via `booking_reminder_lead_hours`), class reminders (`class_reminder_lead_hours`), and auto-completes classes whose `end_time` has passed (flipping registered students to attended/no_show and queueing a follow-up "thanks" message). All queued rows land in `wp_g2a_crm_messages` with status `queued`.
- `g2a_crm_cron_daily` (02:00 UTC) — expires past-due waivers, flips active memberships past their `renewal_date` to `past_due`, queues 14-day waiver expiry warnings, queues 10-day membership renewal warnings (`renewal_warn_days`) plus a staff follow-up task when the membership is already past-due, and marks tasks `overdue` when past their `due_at`.
- `g2a_crm_cron_dispatch` (every 5 min) — claims a batch of queued messages, renders templates against the customer context, checks per-channel opt-in, and sends via wp_mail or the configured SMS provider. Failed sends back off exponentially (1, 2, 4, 8, 16 min) up to 5 attempts.

Both events unschedule on plugin deactivate.

## Testing checklist (Phase 1)

**Functional**
- [ ] Activate plugin → all tables exist under `wp_g2a_crm_*`
- [ ] Roles `crm_owner`, `crm_manager`, `crm_compliance_officer`, `crm_range_staff`, `crm_instructor`, `crm_sales`, `crm_marketing`, `crm_accountant` exist
- [ ] Create / edit / delete a customer
- [ ] Create a lead, log activity, change status, convert to customer
- [ ] Create a task, complete it
- [ ] Audit log shows each create/update/delete with old/new diff

**Permissions**
- [ ] Range Staff user can view customers but cannot delete
- [ ] Sales user can edit leads but cannot view audit logs
- [ ] Logged-out request to `/customers` → 401
- [ ] Logged-in user without `view_customers` → 403

**Security**
- [ ] All REST endpoints require permission callback (no public reads)
- [ ] Nonce required for state-changing requests
- [ ] SQL queries use `$wpdb->prepare`
- [ ] Output is escaped (React + dangerouslySetInnerHTML is never used)

## Testing checklist (Phase 2)

**Bookings**
- [ ] Create a booking → check resource conflict by booking the same lane in the same window (should 409)
- [ ] Check in a customer with a valid waiver → status becomes `checked_in`
- [ ] Check in a customer with no valid waiver → 409 with `g2a_crm_waiver_required`
- [ ] Manager with `verify_waivers` can override the waiver gate (logged in audit log)
- [ ] Cancel a booking → status updates, reason appended to notes
- [ ] Calendar view shows the booking in the right cell

**Waivers**
- [ ] Submit a waiver from the kiosk → customer's `waiver_status` flips to `submitted`
- [ ] Verify a waiver → `waiver_status` flips to `verified`, audit entry recorded
- [ ] Print view URL is a valid signed token; tampering with the token denies access (unless logged in with `view_waivers`)
- [ ] Cron `g2a_crm_cron_daily` expires waivers past `expires_at`

**Kiosk**
- [ ] `?g2a_kiosk=1` returns 403 when `kiosk_enabled` is false
- [ ] Submitting a waiver without typed-name and without signature returns 400
- [ ] Rate limit kicks in after 30 requests per IP in 10 minutes

## Testing checklist (Phase 3)

**Memberships**
- [ ] Create a membership for a customer → `wp_g2a_crm_customers.membership_status` syncs
- [ ] Try to create a second active membership for the same customer → 409 conflict
- [ ] Renew a membership for 3 months → `renewal_date` advances correctly, status returns to `active`, billing back to `current`
- [ ] Cancel a membership → customer membership_status flips to `cancelled`
- [ ] Daily cron flips active memberships past `renewal_date` to `past_due`

**Classes**
- [ ] Create a class with capacity 2 → register 2 students → registering a third returns 409 (`g2a_crm_full`)
- [ ] Register the same customer twice → idempotent, returns existing record
- [ ] Cancel a class → all enrollments flip to `cancelled`
- [ ] Class end_time in the past + hourly cron run → status auto-flips to `completed`, attended students get follow-up reminder queued
- [ ] Instructor user with `crm_instructor` role can view roster of their own class even without `view_classes`

**Capabilities**
- [ ] Range Staff can view memberships but cannot edit
- [ ] Sales can register students for classes but cannot create new classes
- [ ] Instructor cannot create classes but can mark attendance

## Testing checklist (Phase 4)

**Sending**
- [ ] Set `messaging_test_mode = true` to start — logs sends instead of dispatching to real providers
- [ ] Manually send a test email from Customer Detail → opens Messages with status `sent`
- [ ] Send an email to a customer with `email_opt_in = false` → status flips to `skipped`
- [ ] Twilio: set `sms_provider = twilio` + SID/token/from, send SMS → check Twilio dashboard
- [ ] Cron `g2a_crm_cron_dispatch` runs every 5 min — queued rows drain on their own

**Dedup + retry**
- [ ] Run the hourly cron twice in a row → reminders are not duplicated (dedup_key unique per booking/class/waiver)
- [ ] Make wp_mail fail (e.g., bad SMTP settings) → message goes to `failed` with retry scheduled in `next_attempt_at`, attempt_count increments

**Opt-out**
- [ ] Visit `/?g2a_unsubscribe=1&customer=N&channel=email&token=...` → confirmation page → POST flips opt-in to 0
- [ ] Tampered token → 403
- [ ] Customer Detail opt-in toggles persist via the customers PUT endpoint

**Templates**
- [ ] Reactivate the plugin → 6 default templates exist (booking_reminder, waiver_expiry, etc.)
- [ ] Edit a template → preview shows resolved {{first_name}} etc.

## Testing checklist (Phase 5)

**Reports**
- [ ] Each report loads without error and respects the date-range filter
- [ ] Revenue report MRR matches `SUM(monthly_amount)` for active/trial/vip memberships
- [ ] No-show rate matches `no_show / (no_show + completed + checked_in)` × 100
- [ ] Lead conversion rate matches `converted / total` × 100 in range
- [ ] Staff Performance lists every user assigned to a task / booking / lead / class in range, with display name resolved from WP users
- [ ] Communications "Delivery Rate" matches sent/total per channel

**Exports**
- [ ] CSV downloads with proper filename `g2a-crm-{type}-YYYY-MM-DD-HHMMSS.csv`
- [ ] Each export gets logged to the audit trail (action `export.download`)
- [ ] User without `g2a_crm_export_data` gets 403
- [ ] Range filter applies to created_at (or signed_at for waivers, start_time for bookings)

**MRR setup**
- [ ] Edit a membership → set `monthly_amount` → MRR card on Revenue report updates on refresh
- [ ] DB upgrade adds the `monthly_amount` column on reactivation

## Phase 6: compliance + integrations

Phase 6 wraps up the blueprint: compliance-aware restricted workflow + POS/inventory integration. All three subsystems now ship:

**Sensitive Records** — repository, REST controller, and React panel gated behind `g2a_crm_view_sensitive_records` / `g2a_crm_edit_sensitive_records`. Every read is audit-logged so compliance can answer "who looked at this and when".

**Outbound Webhooks** — HMAC-signed delivery for every domain event, exponential-backoff retry cron, per-webhook test send, delivery log. Subscribe to a single event or `*` for everything.

**WooCommerce purchase sync** — when WooCommerce is active, every order create / update / status transition / refund is mirrored into `wp_g2a_crm_integrations` keyed by `(woocommerce, order, <order_id>)` and linked to the matching CRM customer. Customer Detail surfaces lifetime value + sortable purchase history, with a one-click "Backfill from Woo" action and expandable per-order line items. HPOS-compatible (declared via `before_woocommerce_init`). Unmatched orders park as `local_type='unlinked'` on the Integrations page for manual reconcile.

Matching uses the verified WP user's email first (always trusted), then falls back to `billing_email`. Setting `woo_require_verified_user_link` flips that fallback to a `guest_link` bucket instead, mitigating the email-match IDOR where a stranger placing an order under an existing customer's email could pollute their purchase history.

The sync is one-way (Woo → CRM); the CRM never writes back. Line items mirror only `name`, `qty`, and `line_total` (capped to 20 per order) — **SKU, serial, and product_id are intentionally omitted** so regulated-product details never leak into the CRM audit trail.

**WooCommerce Subscriptions → CRM memberships** — when the WC Subscriptions extension is also active, subscription lifecycle transitions mirror to CRM memberships: `status_active` creates or resumes a membership, `on-hold` suspends, `cancelled`/`expired` close it out, and a successful renewal payment extends `renewal_date`. The link is stored in the same integrations table keyed by `(woocommerce, subscription, <sub_id>)` with `local_type='membership'`.

**Email/SMS suppression list** — `wp_g2a_crm_suppressions` keyed by (channel, normalized_address). The dispatcher consults `Suppressions_Repository::is_suppressed()` before every send — suppressed recipients get status `skipped` rather than burning ISP / carrier reputation. Auto-suppression sources: hard_bounce, complaint, unsubscribe (public footer link adds the address), send_failure (any wp_mail/Twilio error marked non-retryable). Sticky source rank: a hard_bounce row will NOT downgrade if a staff member later re-adds the same address manually.

Inbound bounces flow through a single provider-agnostic endpoint: `POST /wp-json/g2a-crm/v1/inbound/bounce` with `X-G2A-CRM-Bounce-Secret: <inbound_bounce_secret>` (set in CRM Settings; endpoint returns 503 until configured). Body: `{ channel: "email"|"sms", address: "...", type: "hard_bounce"|"soft_bounce"|"complaint"|"unsubscribe", reason: "..." }`. Wire SES / Postmark / Mailgun / Twilio webhooks through a Zapier or Make relay and normalize the payload before forwarding. Soft bounces are recorded for observability but don't suppress.

Capability: `g2a_crm_manage_integrations` for the Integrations page; the per-customer Purchases panel inherits `g2a_crm_view_customers`. Suppression management uses `g2a_crm_manage_settings`.

REST surface (all under `/wp-json/g2a-crm/v1/`):

| Resource | Methods | Capability |
|----------|---------|------------|
| `/integrations` | GET | `g2a_crm_manage_integrations` |
| `/integrations/stats` | GET | `g2a_crm_manage_integrations` |
| `/integrations/unlinked` | GET | `g2a_crm_manage_integrations` |
| `/integrations/woocommerce/sync` | POST `{order_id}` | `g2a_crm_manage_integrations` |
| `/integrations/woocommerce/backfill` | POST `{customer_id}` | `g2a_crm_manage_integrations` |
| `/customers/{id}/purchases` | GET | `g2a_crm_view_customers` |

=== WPistic CRM ===
Contributors: wordpressistic
Tags: crm, customers, leads, bookings, contacts, dashboard, wordpressistic
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Compliance-aware CRM and operations dashboard for your business.

== Description ==

WPistic CRM is the central operations system for the WPistic business: customers, leads, range bookings, classes, memberships, waivers, communications, staff tasks, and audit logs in one place.

v1.0.0 is the production release. All six phases from the blueprint ship:

* **Customers** — 360 profile, search, edit, delete, duplicate detection + merge.
* **Leads** — pipeline + activity log + auto-assignment (round-robin / first-available) + auto-created follow-up tasks + assignee notification.
* **Tasks** — open / overdue / mine, related-record linking, complete-in-place, due-date reminders.
* **Bookings** — calendar, resource-conflict guard, mission check-in, cancel/complete.
* **Waivers** — kiosk capture, signed records, HTML print view (Save-as-PDF), expiry tracking.
* **Memberships + classes** — one active membership per customer, renewals, past-due flip, class roster, capacity guard, auto-completion, follow-up.
* **Communications** — email (wp_mail) + SMS (Twilio or log-only), templates with `{{vars}}`, dedup + exponential-backoff retry, opt-in tracking, public unsubscribe.
* **Reports + exports** — Revenue, Lead Pipeline, Operations, Membership Health, Staff Performance, Communications. CSV exports for 6 entity types.
* **Sensitive records** — compliance workflow (FFL transfer notes, NICS references, A&D references, background checks). Every read audit-logged.
* **Outbound webhooks** — HMAC-signed delivery for every event, exponential-backoff retry, per-webhook test send.
* **WooCommerce integration** — one-way order mirror, refund handling, HPOS-compatible, optional WC Subscriptions → CRM memberships.
* **Bulk operations + saved filter views** on Customers + Leads list pages.
* **Suppression list** — auto-suppress on hard bounce / complaint / unsubscribe / permanent send failure. Provider-agnostic `/inbound/bounce` endpoint with shared-secret auth.
* **8 CRM roles + 26 capabilities** for fine-grained access control.
* **REST API** namespace `g2a-crm/v1` with nonce-based auth.
* **Tactical dark React + Vite dashboard** mounted inside WP-Admin.

== Important Compliance Boundary ==

This plugin is for business administration and workflow tracking. It does **not** make legal decisions, automatically approve firearm transfers, or replace ATF/FBI/state compliance systems. Regulated actions require human staff review.

== Installation ==

1. Upload the `guns2ammo-crm` folder to `/wp-content/plugins/`.
2. Build the React dashboard once:
   * `cd wp-content/plugins/guns2ammo-crm/admin/app`
   * `npm install`
   * `npm run build`
3. Activate the plugin through the Plugins screen in WordPress.
4. Open **G2A CRM** in the WP-Admin sidebar.
5. Assign the `CRM Owner` role (or a more restricted CRM role) to any staff user who should see the dashboard.

== Changelog ==

= 1.0.0 =
* Production release.
* **Email/SMS suppression list** (new). `wp_g2a_crm_suppressions` table keyed by (channel, normalized_address). Dispatcher gates every send against the list — suppressed recipients get status `skipped` instead of risking ISP/carrier reputation penalties. Sources tracked: manual, hard_bounce, complaint, unsubscribe, send_failure. Source-rank rule: a hard_bounce row will NOT downgrade to manual if a staff member later re-adds the same address.
* **Auto-suppress on permanent send failure** — wp_mail / Twilio errors marked non-retryable flow straight into the list.
* **Unsubscribe link integrates** — using the public unsubscribe footer adds the customer's address to the suppression list in addition to flipping their per-customer opt-in flag. Belt + suspenders: same address on a different (e.g. duplicated) customer record stays protected.
* **Inbound bounce webhook** (new) — provider-agnostic `POST /wp-json/g2a-crm/v1/inbound/bounce` accepts normalized payloads `{channel, address, type, reason}`. Wire SES / Postmark / Mailgun / Twilio webhooks through a relay (Zapier / Make) and forward here. Auth via shared `inbound_bounce_secret` (set in CRM Settings) compared with `hash_equals`. Endpoint returns 503 until the secret is configured.
* **Suppressions admin page** with channel + source + search filters, manual add/remove, and inline webhook setup instructions.
* DB version bumped to 9. New cap is not required — suppression management uses the existing `g2a_crm_manage_settings` cap.
* Plus all earlier 0.x work consolidated: Sensitive Records, Outbound Webhooks, Customer Merge + duplicate detection, Lead Auto-Assignment, WooCommerce purchase sync (HPOS + refunds + line items, opt-in verified-user-only linking), WC Subscriptions → memberships, bulk operations, saved filter views, kiosk-session IDOR fix, customer-merge integrations-row fix.

= 0.10.0 =
* Lead auto-assignment + post-create workflow (blueprint §12.1). When a new lead lands — via the REST API, kiosk, or any other entry point that flows through `G2A_CRM_Leads_Repository::create()` — the auto-processor runs four steps, each independently toggleable in settings:
  1. **Customer match** — if the lead has an email or phone that matches an existing customer, the lead is linked via `customer_id` automatically.
  2. **Auto-assign** — pulls from a per-interest route map first, falling back to a global pool. Strategy is either `round_robin` (fair cycling, persisted in `wp_options`) or `first_available` (always pick the first listed user). Users without `g2a_crm_view_leads` are skipped silently — so removing a staff cap removes them from the rotation without editing settings.
  3. **Follow-up task** — a task is auto-created assigned to the new owner, due `lead_follow_up_hours` from now, priority configurable (`low` / `normal` / `high` / `urgent`).
  4. **Notification email** — `wp_mail()` to the assignee's WordPress user email with the lead's name / email / phone / interest / source + a deep link to the lead detail page. Honors the existing `messaging_test_mode` short-circuit.
* The lead update fired by the assignment hits the audit log as `lead.update`, which means **webhooks fire automatically** on every auto-assignment — Zapier / Make / Twilio Studio can subscribe to `lead.update` and watch for new `assigned_to` values. No extra wiring required.
* Settings page gains a **Lead routing** section with strategy picker, global pool field, per-interest route grid (one row per interest type), and three toggles for customer-match / follow-up-task / notify-assignee.
* New settings keys: `lead_auto_assign_enabled`, `lead_auto_assign_strategy`, `lead_auto_assign_pool`, `lead_auto_assign_routes`, `lead_match_customer_on_create`, `lead_create_followup_task`, `lead_followup_task_priority`, `lead_notify_assignee`.

= 0.9.0 =
* Customer merge + duplicate detection. New endpoints `GET /customers/{id}/duplicates` (returns candidates with confidence score 0–100 and the reasons matched — email / phone / name) and `POST /customers/{id}/merge` (gated by g2a_crm_delete_customers since the source customer is deleted).
* Merge service walks 11 related tables in a single transaction: leads, bookings, waivers, memberships, messages, customer_meta, sensitive_records, class_students (UNIQUE-aware), customer_tags (UNIQUE-aware), polymorphic notes where related_type='customer'. Pivot collisions on UNIQUE keys are dropped from the source side BEFORE reassignment to avoid constraint failures.
* Survivor's contact fields win; survivor's blanks are backfilled from the deleted record. Every per-table count and every backfilled field is recorded in the audit log under two entries: `customer.merge_source` and `customer.merge_target` — so the merge can be reconstructed later from the audit trail.
* React "Merge / Dedupe" button on Customer Detail launches a modal that loads candidates ranked by score, lets the operator pick a direction (keep anchor or keep candidate), confirms with row counts on success, and auto-redirects to the surviving customer page if the anchor was the one deleted.

= 0.8.0 =
* Outbound webhooks with HMAC-SHA256 signing — events from any CRM state change (customer, lead, booking, waiver, membership, class, task, message, sensitive record) can stream to any HTTPS endpoint (Zapier, Make, n8n, Twilio callbacks, custom backends).
* New g2a_crm_event WP action emitted by the audit log; the dispatcher subscribes, enqueues one delivery row per active subscriber, and POSTs them on a 5-minute cron (g2a_crm_cron_dispatch_webhooks). Manual "Run dispatcher now" button in the UI.
* Per-delivery exponential backoff (1m → 5m → 30m → 2h → 12h → 24h) up to 6 attempts, then status=failed.
* Signature scheme: header X-G2A-CRM-Signature: sha256=HMAC-SHA256("{timestamp}.{raw body}", secret). Companion headers X-G2A-CRM-Event, X-G2A-CRM-Delivery, X-G2A-CRM-Timestamp. Receivers verify by recomputing the HMAC and rejecting timestamps older than 5 minutes (replay protection).
* Wildcard subscription (event="*") receives every event.
* Secrets are auto-generated server-side on create, returned in cleartext exactly once (one-time-visible banner in the UI), and masked on all subsequent reads. Sending the masked placeholder back preserves the stored secret.
* Built-in "Send test" button delivers a synthetic webhook.ping event so operators can verify connectivity + signing without waiting for real activity.
* New /webhooks React page with list, edit modal, recent-deliveries table, and per-row status badges. New WP submenu page + sidebar entry, both gated by g2a_crm_manage_webhooks (granted to Owner + Manager).
* Read-only audit entries (sensitive_record.read_*) are intentionally excluded from webhook fan-out — they're forensics, not state changes.
* New table wp_g2a_crm_webhook_deliveries (DB v8) tracks every attempt: webhook_id, event, payload, status, response_code, response_body (truncated to 4 KB), attempt_count, last_error, next_attempt_at, delivered_at.

= 0.7.0 =
* Phase 6 begins: compliance-aware Sensitive Records module (blueprint §5.9). Restricted workflow for FFL transfer notes, NICS reference status, A&D references, background checks, legal hold, and staff compliance reviews. The CRM does not make legal decisions and does not auto-approve regulated actions — this is a workflow + checklist + audit-trail layer.
* Status pipeline: inquiry_received → staff_review_required → documents_pending → verification_in_progress → waiting_external → ready_for_staff_action → completed / cancelled / rejected / archived. Closing transitions stamp closed_at; re-opening clears it.
* Per-record JSON staff checklist with done/by/at metadata.
* Two-layer access control: WP capability (g2a_crm_view_sensitive_records / edit_sensitive_records) AND optional role allowlist from settings.restrict_sensitive_view_to_roles. Both must pass — REST returns 403 if either gate fails.
* **Every read is audit-logged** (sensitive_record.read_list and sensitive_record.read_view), not just writes. Bodies are redacted in audit payloads (sha256-prefixed length marker) so the audit log proves a change happened without re-storing the regulated text.
* New REST endpoints under /g2a-crm/v1/sensitive-records (list, create, get, update, delete) + /sensitive-records/meta for the dropdown options.
* New /sensitive React page with type/status/open-only/assigned-to-me/search filters, plus a restricted panel mounted on the Customer Detail page (hidden client-side from users without view_sensitive).
* Modal editor with inline checklist add/toggle/remove and a close-reason field that appears when the user moves the record into a terminal status.
* Schema bump (DB v7): added checklist LONGTEXT, closed_at DATETIME, closed_reason TEXT, plus a handled_by index.

= 0.6.0 =
* Tags module: shared taxonomy with attach/detach to customers. Inline chip picker on Customer Detail with create-on-the-fly when the user has the new `g2a_crm_manage_tags` capability. REST: `/tags`, `/customers/{id}/tags`.
* Notes module: polymorphic free-form notes attached to any record by `(related_type, related_id)`. Edit/delete capability is derived from the underlying record (customer note requires `g2a_crm_edit_customers`, etc.). REST: `/notes`. Notes panel mounted on Customer Detail.
* Settings page in the React dashboard, gated by `g2a_crm_manage_settings`. Business info, email from address/name, Twilio SMS config (token is masked on read and only overwritten when the masked placeholder is replaced), waiver/booking/class/renewal lead times, kiosk toggle, compliance review list, sensitive-records role allowlist.
* All settings, tag, and note writes feed the audit log.

= 0.5.0 =
* Phase 5: full reporting + premium dashboard. 6 detail reports — Revenue, Lead Pipeline, Operations, Membership Health, Staff Performance, Communications — all with date-range filters (7d / 30d / 90d / YTD / 12m presets + custom).
* Revenue report: booking + class revenue rollup, current MRR, top classes by revenue, daily revenue sparkline.
* Pipeline report: leads by source with conversion rate, by interest, status funnel, lead-score buckets (cold/warm/hot/on_fire).
* Operations report: total bookings, no-show rate, attended count, range-lane utilization (by resource_type/resource_id with hours), waiver submission + verify rate, daily booking sparkline.
* Membership Health: MRR, churn rate, net change in range, active by status/plan, cancellation reasons, upcoming-30-day renewals.
* Staff Performance: per-user tasks open/done/overdue + bookings handled + leads/converted + classes taught. Sortable.
* Communications: delivery rate per channel, top failure reasons, daily volume, opt-out totals.
* CSV exports for customers / leads / bookings / memberships / messages / waivers with date-range filter. Every export logged to the audit trail.
* New `memberships.monthly_amount` column powers MRR computation. Edit it from Membership Detail or the create modal.
* Inline-SVG sparkline + bar-chart component (no chart library — dependency-free).
* Reports page index in WP-Admin sidebar; capability-gated by `g2a_crm_view_reports`. Exports gated by `g2a_crm_export_data`.

= 0.4.0 =
* Phase 4: communication center. The dispatcher cron (every 5 min, `g2a_crm_cron_dispatch`) now picks up queued rows from the messages table that Phases 2–3 have been writing, and actually sends them.
* Email via wp_mail() with configurable from-name / from-address. SMS via pluggable provider — `log_only` (default, dev-safe) or `twilio` (requires sms_twilio_sid/token/from).
* Template renderer with `{{variable}}` substitution. Default templates seeded on activation: booking_reminder, waiver_expiry, membership_renewal, class_reminder, class_followup, booking_reminder_sms.
* Manual "Send Email" / "Send SMS" buttons on the Customer Detail page with template picker + variable preview, send-now or queue.
* Public unsubscribe endpoint at `/?g2a_unsubscribe=1` — signed token, GET shows confirmation page, POST commits. Email/SMS opt-in toggles on Customer Detail. Unsubscribe footer auto-appended to email; "Reply STOP" added to SMS.
* Per-customer opt-in tracked on `customers.email_opt_in` / `sms_opt_in` (default 1) with `*_opt_out_at` timestamps. Dispatcher checks opt-in at send time — opted-out messages get status `skipped`.
* Reliability: messages have `dedup_key` (idempotent queueing — cron re-runs don't double-send), `attempt_count`, and exponential-backoff retry up to 5 attempts on retryable errors.
* Test mode: setting `messaging_test_mode=true` logs sends instead of dispatching to real providers — safe for staging.
* React: Messages page with filters + Dispatch Now button, Message Detail with rendered vs. template view + retry/cancel, Templates page with editor and live preview.
* Dashboard: messages_queued, messages_sent_today, messages_failed_24h cards.

= 0.3.0 =
* Phase 3: memberships module (CRUD + renew / cancel / suspend, one active per customer, customer.membership_status auto-sync, daily past-due flip).
* Classes module with roster, capacity-enforced enrollment, instructor-of-record permission for roster view, per-student status (registered → checked_in → attended → completed → no_show).
* Class auto-completion: classes whose end_time has passed get marked complete by the hourly cron, attended students get a follow-up reminder queued.
* Membership renewal warnings (10 days before renewal_date) + auto-created staff follow-up tasks for past-due memberships.
* Class reminders (24h before start, configurable).
* New caps: g2a_crm_view_classes, g2a_crm_edit_classes, g2a_crm_manage_class_roster. Role grants updated for Manager, Sales, Range Staff, Instructor, Accountant.
* Dashboard: members_active, members_past_due, renewals_due, cancellations_30d, classes_today, classes_upcoming_week cards + today's classes table.
* Customer detail: memberships + class-history sections.

= 0.2.0 =
* Phase 2: bookings (CRUD + check-in / cancel / complete with resource-conflict guard).
* Waivers module with signed-record storage, signature hash, and HTML print view (browser Save-as-PDF).
* Public kiosk endpoint (`?g2a_kiosk=1`) for self-service customer waiver capture.
* Mission Check-In screen: search customer, see waiver + upcoming bookings, one-click check-in with optional manager override when waiver is missing.
* Calendar month view for bookings.
* WP-cron: booking reminders (24h lead time), waiver-expiry warnings, waiver auto-expire, overdue-task marking. Reminders are queued into the messages table for the Phase 4 communication center to send.
* Customer detail page now shows booking + waiver lists alongside the timeline.
* Dashboard expanded with Today's Bookings, Pending Confirms, Waivers Need Verify, and Expiring Waivers cards.

= 0.1.0 =
* Initial Phase 1 release: customers, leads, tasks, audit log, REST API, React shell.

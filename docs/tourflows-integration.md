# Tourflows integration

Tourflows is the CRM. This website is not a second CRM: it captures inquiries,
lets the team triage them, and pushes each one to Tourflows exactly once. Leads,
opportunities, quotations, itineraries, suppliers, guides, drivers and reporting
all live in Tourflows.

## How it is wired

Tourflows is a **connection profile** in Tour Manager's generic Connections
engine, not a bespoke integration. That means retries, signing, logging and the
manual resend already exist and are shared with any other consumer.

```
Formistic (5 seeded forms)
        │  wpistic_formistic_submission_captured
        ▼
FormisticIngestion            ← the only listener that may create a booking
        │  claims the submission, creates ONE booking
        ├─► do_action( 'wpistic/notify', inquiry.created )   → team + guest email
        └─► ConnectionsManager::dispatch( 'inquiry.created' ) → Tourflows
```

Tour Manager's own booking widget (`[wpistic_booking_widget]`, the primary
"Request Availability" call to action on a tour detail page) takes a
parallel path through `CaptureController` (REST
`POST /wp-json/wpistic/v1/booking`), because it carries `tour_id`, a
departure and the deposit workflow. It ends at the same `dispatch()` call.

As of v2.0.0, Formistic *also* seeds a `request-availability` form (for
placements where the full booking widget doesn't fit, e.g. an Elementor
widget on a lighter page) — this is a deliberate second entry point, not a
duplicate. The two never process the same submission (different HTTP
endpoints, different handlers), so there is still exactly one booking per
visitor action either way. See
`docs/formistic-brother-tours-integration.md`, "Request Tour Availability —
a second, deliberate entry point" for the full reasoning.

## Configuration

Go to **Tour Manager → Connections**. The Site Setup screen registers a
disabled profile named `Tourflows` with no endpoint and no secret; fill both in
and enable it.

| Field | Value |
|---|---|
| Name | `Tourflows` |
| Type | `webhook` |
| Target URL | The Tourflows ingest endpoint |
| Secret | The shared signing secret |
| Events | `inquiry.created`, `booking.deposit_paid`, `booking.confirmed`, `booking.balance_paid` |

**Nothing secret is committed.** The endpoint and secret are stored in the
`wpistic_connections` table. If you would rather hold them outside the database,
set them in `wp-config.php` and leave the database fields empty — see
*Rotating the secret* below.

## Payload contract

`POST` to the configured endpoint, `Content-Type: application/json`.

```json
{
  "event": "inquiry.created",
  "data": {
    "id": 412,
    "reference": "BT-260724-K4M2P",
    "type": "build_my_trip",
    "status": "inquiry",
    "portal_status": "new",
    "tour_id": 0,
    "departure_id": 0,
    "customer_name": "…",
    "customer_email": "…",
    "customer_phone": "…",
    "customer_country": "…",
    "party_adults": 2,
    "party_children": 0,
    "hotel_pref": "…",
    "special_requests": "…",
    "currency": "USD",
    "source_url": "https://brothertours.com/build-my-trip/?utm_source=…",
    "created_at": "2026-07-24 09:12:44"
  },
  "sent_at": "2026-07-24T09:12:45+00:00"
}
```

`type` distinguishes the source form: `build_my_trip`, `contact`, `agent` (travel
agent) or `booking` (tour-page Request Availability). Newsletter sign-ups are
**not** sent — they stay a Formistic list entry.

`reference` is the local identifier (`BT-YYMMDD-XXXXX`, unique in the database).
Use it as the correlation key on the Tourflows side.

### Signature

When a secret is set, every request carries:

```
X-Wpistic-Signature: sha256=<hex>
```

where `<hex>` is `hash_hmac('sha256', <raw request body>, <secret>)`.

Verify against the **raw body**, before any JSON parsing or re-serialization —
re-encoding changes key order and whitespace and will not match. Compare with a
constant-time function (`hash_equals` in PHP, `crypto.timingSafeEqual` in Node).

### Idempotency

Two guards, at different layers:

1. **Ingestion.** A row is claimed in `wpistic_form_ingestions` before a booking
   is created, and the table's `UNIQUE KEY (source, external_id)` decides the
   winner. A replayed hook, a double-submit or two concurrent workers therefore
   produce one booking, not several.
2. **Delivery.** Treat `data.reference` as the idempotency key on the Tourflows
   side. A retry after a timeout resends the same reference; Tourflows must
   recognize it and not create a second lead.

Tourflows should respond `2xx` to a duplicate rather than an error, so the retry
loop settles.

## Retry behavior

| Attempt | When |
|---|---|
| 1 | Immediately |
| 2 | +60 seconds |
| 3 | +240 seconds |

Backoff is `60 × attempt²`, scheduled with `wp_schedule_single_event` on hook
`wpistic_tm_connection_retry`. After the third attempt the engine stops.

A delivery is only considered successful on a **2xx** response. A request that
merely started, or returned 4xx/5xx, is not success — an inquiry is never marked
`sent` just because a request was attempted. `portal_status` moves to `sent`
only when an operator dispatches from the portal.

Every attempt — including failures — is written to `wpistic_connection_log` with
the connection id, event, HTTP status, response body (first 2000 chars) and
attempt number. That log is the failure reason surface.

> **Known limitation.** Retries are scheduled through WP-Cron, which only runs
> when the site receives traffic. On a low-traffic site a retry can be late.
> Configure a real system cron and set `DISABLE_WP_CRON` — see the README.
> After the third failed attempt there is no automatic recovery; an operator
> must resend from the portal. Surfacing exhausted deliveries proactively is
> tracked as an open item in `docs/implementation-plan.md`.

## Manual resend

**Tour Manager → Bookings & Inquiries → open a booking → Connections tab →
"Resend to connections"** (as of v2.0.0; previously a "Dispatch to
connections" checkbox on the single catch-all action form). It re-dispatches
`inquiry.created` to every enabled connection subscribed to that event.
Protected by capability `edit_posts` and a per-booking nonce
(`wpistic_tm_resend_connection_{id}`).

Because Tourflows keys on `reference`, a resend of an inquiry Tourflows already
holds must update rather than duplicate it.

### Delivery history on the booking itself (v2.0.0)

The Connections tab also shows this booking's own dispatch history — every
event, status code, and connection it went to. `wpistic_connection_log` (the
table `ConnectionsManager::log()` writes on every attempt) has no `booking_id`
column, so that history is not readable from it directly. Rather than a schema
migration, `ConnectionsManager::send()` fires a `wpistic_tm_connection_dispatched`
action after every attempt, carrying the booking id extracted from the event
payload (every booking-scoped event's `$payload` is the full booking row, so
`$payload['id']` is always present). `BookingService::record_connection_dispatch()`
listens and mirrors each dispatch into the existing `wpistic_audit_log` table
(`object_type = 'booking'`, `action = 'connection_dispatch'`) — the same table
and mechanism the Activity tab already reads. The Connections tab then filters
the booking's own audit rows for that action; no new table, no new query
shape.

## Testing the integration

Before pointing at production Tourflows:

1. Create a connection aimed at a request-capture endpoint you control
   (RequestBin, `webhook.site`, or a local listener). Set a throwaway secret.
2. Submit the Build My Trip form on the front end.
3. Confirm **one** booking in the portal, **one** row in
   `wpistic_form_ingestions`, and **one** delivery in `wpistic_connection_log`
   with a 2xx.
4. Verify the signature end-to-end by recomputing the HMAC over the captured raw
   body with your secret.
5. Force a failure: point the connection at a URL returning 500 and submit
   again. Confirm three log rows at the expected intervals and that
   `portal_status` stays `new`.
6. Fix the URL, use "Dispatch to connections", and confirm a 2xx row and that
   Tourflows did not create a second lead for the same `reference`.
7. Replay the same submission id through the captured action and confirm **no**
   second booking is created.

## Operational failure recovery

| Symptom | Where to look | Action |
|---|---|---|
| Inquiry in portal, nothing in Tourflows | `wpistic_connection_log` for the reference | Check status code. 401/403 → secret or endpoint wrong. Fix, then resend. |
| Nothing in the portal either | `wpistic_form_ingestions` | No row → the form is not tagged with a `_brother_tours_form` slug, so it was correctly ignored. Tag the form. |
| Duplicate leads in Tourflows | `wpistic_form_ingestions` for the submission | One row means the site sent once and the duplicate is Tourflows-side; make it key on `reference`. |
| Deliveries stopped entirely | Connection `enabled` flag; WP-Cron health | Re-enable, or run cron manually. |
| Secret rotated, deliveries now 401 | Connections screen | Update the secret; resend affected inquiries. |

## Rotating the secret

1. Add the new secret in Tourflows, allowing both old and new briefly if it
   supports overlap.
2. Update the secret on the Connections screen.
3. Send a test inquiry and confirm a 2xx.
4. Retire the old secret in Tourflows.

If you prefer the secret outside the database, define it in `wp-config.php` and
filter it in from a small must-use plugin rather than pasting it into the admin.
Never commit it.

# Brother Tours Operations API

Secure REST adapter for the Brother Tours management app at `app.brothertours.com`.

**Version:** 1.0.0  
**WordPress:** 6.4+  
**PHP:** 8.1+  
**REST namespace:** `/wp-json/bridgistic/v1`

## Purpose

This plugin does **not** replace or duplicate the existing Brother Tours backend.

It exposes a clean REST control plane over the existing system:

- **WPistic Tour Manager 2.0+** remains the owner of tours, destinations, experiences, departures, inquiries/bookings, lifecycle rules, payment metadata, webhooks, connections and audit data.
- **Formistic 2.0+** remains the owner of form submissions, replies, notes, AI metadata and newsletter subscribers.
- **WordPress/MySQL** remains the source of truth.
- **Horizons/React** is the operations user interface only.

No business tables are created by this plugin.

## Installation

1. Install and activate:
   - WPistic Tour Manager 2.0+
   - Formistic 2.0+
2. Upload `brother-tours-operations-api.zip` through **Plugins → Add New → Upload Plugin**.
3. Activate **Brother Tours Operations API**.
4. Open:

```text
https://brothertours.com/wp-json/bridgistic/v1/system/health
```

The health endpoint is authenticated. Sign in through `/auth/session/login` first or use an administrator Application Password from a server-side client.

## Horizons production origin

By default the plugin allows:

```text
https://app.<your WordPress host>
```

For `brothertours.com`, that means:

```text
https://app.brothertours.com
```

To add a Horizons preview origin or another trusted frontend, add this to `wp-config.php`:

```php
define(
    'BT_OPS_ALLOWED_ORIGINS',
    'https://app.brothertours.com,https://YOUR-HORIZONS-PREVIEW-DOMAIN'
);
```

Never use `*` for credentialed CORS.

## Authentication

### Login

```http
POST /wp-json/bridgistic/v1/auth/session/login
Content-Type: application/json

{
  "username": "manager@example.com",
  "password": "..."
}
```

Successful login:

- creates an opaque 12-hour server-side operations session;
- sets a **Secure HttpOnly** `bt_ops_session` cookie;
- returns a per-session CSRF token;
- does **not** create a wp-admin login session.

Store the returned CSRF token in React memory only.

### Unsafe requests

Every POST/PATCH/PUT/DELETE request from the dashboard session must send:

```http
X-BT-CSRF: <csrfToken>
```

Every browser request must use:

```js
credentials: 'include'
```

Do not store the session token or CSRF token in `localStorage`.

### Login rate limit

The login endpoint permits five failed attempts per IP per 15-minute window.

## Core routes

### Auth

| Method | Route | Purpose |
| --- | --- | --- |
| POST | `/auth/session/login` | Sign in with an authorized WordPress account |
| GET | `/auth/session` | Hydrate current operations session |
| POST | `/auth/session/logout` | Revoke current session |
| POST | `/auth/session/revoke-all` | Revoke all operations sessions for current user |

### Command Center

| Method | Route | Purpose |
|---|---|---|
| GET | `/dashboard` | Aggregated command-center KPIs, recent inquiries, revenue groups, Formistic stats, departure status and connection failures |

Optional query:

```text
?from=2026-08-01&to=2026-08-31
```

### Tours

| Method | Route |
| --- | --- |
| GET | `/tours` |
| POST | `/tours` |
| GET | `/tours/{id}` |
| PATCH | `/tours/{id}` |
| DELETE | `/tours/{id}` |

Tour REST fields map to the exact Brother Tours 2.0 metadata already used by WPistic Tour Manager, including itinerary, FAQ, inclusions/exclusions, pricing and deposit override data.

Use `POST /content/media` in this namespace for uploads, then provide the
attachment ID as `featuredMedia`.

> Do **not** use the core `wp/v2` media API from the dashboard. `SessionController::determine_current_user()`
> only resolves the operations session for request URIs containing `/bridgistic/v1/`,
> so a `wp/v2` call carrying the `bt_ops_session` cookie resolves to user 0 and
> returns 401. Earlier revisions of this README recommended `wp/v2` here; that
> guidance was wrong.

### Destinations

| Method | Route |
| --- | --- |
| GET | `/destinations` |
| POST | `/destinations` |
| GET | `/destinations/{id}` |
| PATCH | `/destinations/{id}` |
| DELETE | `/destinations/{id}` |

### Experiences

| Method | Route |
| --- | --- |
| GET | `/experiences` |
| POST | `/experiences` |
| GET | `/experiences/{id}` |
| PATCH | `/experiences/{id}` |
| DELETE | `/experiences/{id}` |

### Departures

| Method | Route |
| --- | --- |
| GET | `/departures` |
| POST | `/departures` |
| GET | `/departures/{id}` |
| PATCH | `/departures/{id}` |
| DELETE | `/departures/{id}` |

Departure fields:

```json
{
  "tourId": 123,
  "date": "2026-11-20",
  "seatsTotal": 12,
  "seatsLeft": 12,
  "status": "open"
}
```

A departure with linked inquiry/booking rows cannot be deleted through this API.

### Inquiries / Bookings

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/bookings` | Search/filter/paginate existing Tour Manager inquiry/booking records |
| GET | `/bookings/{id}` | Full operations detail: traveler, trip, transactions, activity, connection history and allowed lifecycle actions |
| GET | `/bookings/{id}/actions` | Read backend-approved actions |
| POST | `/bookings/{id}/workflow` | Update portal workflow (`new`, `reviewed`, `sent`, `closed`) |
| POST | `/bookings/{id}/assign` | Assign WordPress team member |
| POST | `/bookings/{id}/note` | Add immutable Tour Manager audit note |
| POST | `/bookings/{id}/lifecycle` | Run an action through the existing `BookingService` state machine |
| POST | `/bookings/{id}/payment-link` | Ask the existing configured gateway/service to create a deposit or balance link |
| POST | `/bookings/{id}/dispatch` | Dispatch an existing supported connection event |

The plugin **does not duplicate the booking state machine**. Lifecycle mutations call:

```text
Wpistic\TourManager\Booking\BookingService
```

The valid lifecycle remains the Tour Manager 2.0 contract.

### Formistic inbox

| Method | Route |
| --- | --- |
| GET | `/inbox/submissions` |
| GET | `/inbox/submissions/{id}` |
| POST | `/inbox/submissions/{id}/status` |
| POST | `/inbox/submissions/{id}/notes` |
| POST | `/inbox/submissions/{id}/reply` |
| GET | `/inbox/stats` |
| GET | `/newsletter/subscribers` |

Reply sending reuses Formistic's existing internal mail transport and reply logging. It does not create a second inbox database.

### Connections / Tourflows webhooks

| Method | Route |
| --- | --- |
| GET | `/connections` |
| POST | `/connections` |
| PATCH | `/connections/{id}` |
| DELETE | `/connections/{id}` |
| GET | `/connections/logs` |

Connection secrets are **write-only** and are never returned by the API.

Supported events are exactly the Tour Manager 2.0 connection events:

```text
inquiry.created
booking.deposit_link
booking.deposit_paid
booking.confirmed
booking.balance_link
booking.balance_paid
```

Production webhook targets must use HTTPS.

### Reports

| Method | Route |
| --- | --- |
| GET | `/reports/overview` |
| GET | `/reports/bookings` |
| GET | `/reports/forms` |

Booking revenue is grouped by currency. The API does not incorrectly combine USD/EUR/crypto amounts into one number.

### Team

| Method | Route |
| --- | --- |
| GET | `/team` |
| GET | `/team/{id}` |

The Team API is read-only in v1.0. Assignments are changed through the booking action endpoint. WordPress roles/capabilities remain authoritative.

### Insightistic

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/insightistic` | Read active Insightistic plugin status and version |

This route exposes Insightistic availability and version information for the site.

### System

| Method | Route |
| --- | --- |
| GET | `/system/health` |

Checks:

- WordPress/PHP version
- WPistic Tour Manager availability/version
- Formistic availability/version
- Brother Tours Formistic fork marker
- tour CPT registration
- Tour Manager custom tables
- connection failures in the last 24 hours
- known cron hooks
- HTTPS/session/CSRF configuration

## Response envelope

Success:

```json
{
  "success": true,
  "data": {},
  "meta": {
    "generatedAt": "2026-08-10T09:00:00+00:00",
    "timezone": "Asia/Vientiane",
    "apiVersion": "1.0.0"
  }
}
```

Errors use standard WordPress `WP_Error` REST responses with a machine-readable error code and HTTP status.

### Content (1.1.0)

| Method | Route | Capability |
|---|---|---|
| GET | `/content/types` | `edit_posts` |
| GET, POST | `/content/posts` | `edit_posts` |
| GET, PATCH, DELETE | `/content/posts/{id}` | `edit_posts` / `delete_posts` |
| POST | `/content/posts/{id}/restore` | `edit_posts` |
| GET | `/content/posts/{id}/revisions` | `edit_posts` |
| GET | `/content/taxonomies` | `edit_posts` |
| GET, POST | `/content/terms` | `edit_posts` / `manage_categories` |

Post types are allowlisted to `post`, `page`, `wpistic_tour`,
`wpistic_destination` and `wpistic_experience`. An unrecognised field returns
422 rather than being forwarded to `wp_insert_post()`.

`bt_seo_title`, `bt_seo_description` and `_wpistic_tone` are writable. The
`_seoistic_*` keys are returned read-only — they are SEOISTIC's audit output.

A record with `_elementor_edit_mode = builder` or `has_blocks()` rejects a
`content` PATCH with 409 and an edit link. Round-tripping that markup through a
plain text field destroys the layout.

Send the record's `modifiedGmt` back on PATCH for optimistic concurrency; the
server returns 409 if it changed underneath you.

### Media (1.1.1)

| Method | Route | Capability |
|---|---|---|
| GET, POST | `/content/media` | `upload_files` |
| GET, PATCH, DELETE | `/content/media/{id}` | `upload_files` / `delete_posts` |

> **Not `/media`.** The Bridgistic connector plugin registers `/media` in this
> same namespace. WordPress merges the two registrations instead of rejecting
> them, and the earlier-loading plugin wins dispatch — so `/media` here looked
> registered, activated without complaint, and answered the dashboard with
> "Missing authentication headers" from the connector's HMAC plane. See
> `tests/verify-routes.php` for the guard that now prevents a repeat, and
> "Namespace sharing" below.

`POST /content/media` takes `multipart/form-data` with a `file` part, plus optional
`title`, `alt` and `caption`. Type is detected from the file itself and checked
against both an explicit allowlist and the current user's permitted types. SVG
is refused. 16 MB ceiling.

### Analytics (1.1.0)

| Method | Route | Capability |
|---|---|---|
| GET | `/analytics/status` | `bt_view_health` |
| GET | `/analytics/search-console?days=28` | `bt_view_health` |
| GET | `/analytics/ga4?days=28` | `bt_view_health` |
| GET | `/analytics/pagespeed?url=&strategy=` | `bt_view_health` |
| POST | `/analytics/pagespeed/run` | `manage_options` |
| GET | `/analytics/404s` | `bt_view_health` |

A server-side adapter over the Insightistic plugin, which registers no REST
namespace of its own. Four behaviours are enforced in code:

- **No secret is ever returned.** `/analytics/status` reports booleans, and a
  recursive scrub drops any key matching
  `private_key|api_key|secret|_enc$|password|token` at any depth.
- **The `html` string from `get_dashboard_data()` is dropped** at the same
  boundary, so pre-rendered markup from another plugin cannot reach a client.
- **PageSpeed is asynchronous.** `GET` serves a cached result and reports
  `fresh`, `stale` or `never_run`; `POST /run` schedules a cron event. A live
  PSI call takes 10–30s and would hit the PHP timeout inside a request.
- **PageSpeed targets are same-origin only**, or the route is an open relay on
  the site's API key.

GA4 responses carry `dailyAvailable`. On this property `daily` returns empty
while `channels` returns rows, so a client must render an unavailable state
rather than charting an empty series as a flat line.

Responses are cached in transients: GSC and GA4 one hour, PageSpeed six hours,
status five minutes.

### Site (1.1.0)

| Method | Route | Capability |
|---|---|---|
| GET | `/site/overview` | `bt_view_health` |
| GET | `/site/plugins` | `manage_options` |
| GET | `/site/users` | `list_users` |
| GET | `/site/cron` | `manage_options` |

Read-only. Activation, updates and user creation stay in wp-admin.

## Namespace sharing — read before adding a route

This plugin does not own `bridgistic/v1`. The **Bridgistic connector plugin**
registers into the same namespace, and WordPress treats that as legal:

```php
// WP_REST_Server::register_route()
$this->endpoints[ $route ] = array_merge( $this->endpoints[ $route ], $route_args );
```

Two plugins registering the same path produce one route with both sets of
handlers appended, and `dispatch()` takes the **first** handler whose methods
match. The connector loads earlier, so on any shared path the connector answers
and this plugin never runs. No warning, no error, no log line.

That is not hypothetical. Version 1.1.0 shipped `/media` and the live media
library returned **"Missing authentication headers"** — the connector's HMAC
plane rejecting a browser that correctly carried a session cookie instead. The
route was registered, active, and unreachable. Fixed in 1.1.1 by moving to
`/content/media`.

**Before adding a route, run the test.** `tests/verify-routes.php` carries the
list of connector-owned paths confirmed against production and fails the build
on a collision:

```bash
php tests/verify-routes.php .
```

Prefix new routes with a group this plugin already owns — `/content/*`,
`/analytics/*`, `/site/*`, `/auth/session/*`, `/inbox/*`, `/reports/*` — rather
than claiming a bare noun. Bare nouns are where the connector lives.

The permanent fix is a namespace of this plugin's own (`bt-ops/v2`), which also
requires updating `SessionController::determine_current_user()` — it resolves
the operations session only for request URIs containing `/bridgistic/v1/` — and
the dashboard's `VITE_BT_API_BASE` in the same deploy. Until those move
together, the guard above is what stands between a new route and a silent
outage.

## Capabilities

`Csrf::authorize( $request, $capability, $write )` enforces the capability, and
CSRF on top of it when `$write` is true. Routes do not share one capability:

| Area | Capability |
|---|---|
| Operations reads and writes (tours, bookings, inbox, connections, reports, team) | `bt_manage_operations` |
| Health, analytics and site overview reads | `bt_view_health` |
| Content and media (`/content/*`) | `edit_posts` / `upload_files` |
| Deletion | the relevant WordPress delete capability |
| Plugin, user and cron reads, and running PageSpeed | `manage_options` |

**`bt_manage_operations` is not a content capability.** It gates dashboard
login and is held by seven roles — `administrator`, `tour_staff`,
`wpistic_travel_manager`, `wpistic_travel_agent`, `crm_owner`, `crm_manager`
and `crm_sales` — but only `administrator` also holds `edit_posts`,
`publish_posts`, `upload_files` or `manage_options`. Content routes therefore
check the real WordPress capability, and per-object routes additionally check
`current_user_can( 'edit_post', $id )` inside the handler.

Publishing still checks WordPress publishing capability.

Connection creation/update/deletion requires:

```text
manage_options
```

Frontend route hiding is never treated as authorization; WordPress permissions remain authoritative.

## Important source-of-truth rules

Do not create a Horizons database for:

- tours
- destinations
- experiences
- departures
- inquiries/bookings
- transactions
- Formistic submissions
- replies
- notes
- newsletter subscribers
- connection records

Those records already belong to WordPress/Tour Manager/Formistic.

## Recommended Horizons client configuration

```env
VITE_BT_API_BASE=https://brothertours.com/wp-json/bridgistic/v1
```

This value is not a secret.

Do not put WordPress passwords, Application Passwords, webhook secrets, Stripe keys, PayPal secrets or other server credentials in a `VITE_*` variable.

## Recommended React auth flow

```text
POST /auth/session/login
        ↓
HttpOnly cookie + csrfToken
        ↓
keep csrfToken in module memory
        ↓
GET /auth/session on reload
        ↓
credentials: include on every request
        ↓
X-BT-CSRF on state-changing requests
```

## Data ownership confirmed from Brother Tours 2.0 packages

The plugin was designed against the supplied Brother Tours 2.0.0 packages:

- `wpistic-tour-manager-2.0.0`
- `formistic-2.0.0`
- `wpistic-2.0.0`
- `brother-tours-2.0.0`

It deliberately preserves the existing runtime contract:

```text
Formistic submission
        ↓
Formistic capture hook
        ↓
Tour Manager ingestion ledger
        ↓
one inquiry/booking record
        ↓
Tour Manager lifecycle + connections
```

Newsletter subscriptions remain inside Formistic and do not become Tour Manager inquiries.

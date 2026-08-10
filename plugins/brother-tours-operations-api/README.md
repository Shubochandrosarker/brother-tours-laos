# Brother Tours Operations API

Secure REST adapter for the Brother Tours management app at `app.brothertours.com`.

**Version:** 1.0.0  
**WordPress:** 6.4+  
**PHP:** 8.1+  
**REST namespace:** `/wp-json/bt-ops/v1`

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
https://brothertours.com/wp-json/bt-ops/v1/system/health
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
POST /wp-json/bt-ops/v1/auth/session/login
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
|---|---|---|
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
|---|---|
| GET | `/tours` |
| POST | `/tours` |
| GET | `/tours/{id}` |
| PATCH | `/tours/{id}` |
| DELETE | `/tours/{id}` |

Tour REST fields map to the exact Brother Tours 2.0 metadata already used by WPistic Tour Manager, including itinerary, FAQ, inclusions/exclusions, pricing and deposit override data.

Use the standard WordPress media REST API for media uploads, then provide the attachment ID as `featuredMedia`.

### Destinations

| Method | Route |
|---|---|
| GET | `/destinations` |
| POST | `/destinations` |
| GET | `/destinations/{id}` |
| PATCH | `/destinations/{id}` |
| DELETE | `/destinations/{id}` |

### Experiences

| Method | Route |
|---|---|
| GET | `/experiences` |
| POST | `/experiences` |
| GET | `/experiences/{id}` |
| PATCH | `/experiences/{id}` |
| DELETE | `/experiences/{id}` |

### Departures

| Method | Route |
|---|---|
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
|---|---|---|
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
|---|---|
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
|---|---|
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
|---|---|
| GET | `/reports/overview` |
| GET | `/reports/bookings` |
| GET | `/reports/forms` |

Booking revenue is grouped by currency. The API does not incorrectly combine USD/EUR/crypto amounts into one number.

### Team

| Method | Route |
|---|---|
| GET | `/team` |
| GET | `/team/{id}` |

The Team API is read-only in v1.0. Assignments are changed through the booking action endpoint. WordPress roles/capabilities remain authoritative.

### System

| Method | Route |
|---|---|
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

## Capabilities

General read/operations endpoints require:

```text
edit_posts
```

Content deletion requires the relevant WordPress delete capability.

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
VITE_BT_OPS_API_BASE=https://brothertours.com/wp-json/bt-ops/v1
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

# Tour Manager guide

The Tour Manager admin — dashboard, bookings list, and booking detail — for
operators reviewing and triaging inquiries. This is deliberately **not a
CRM**: Tourflows remains the system of record for leads, quotations,
suppliers, and reporting. This dashboard exists for intake review, status,
assignment, notes, retry/resync, and CSV export only.

## Roles and capabilities

| Capability | Can do |
|---|---|
| `edit_posts` | View the dashboard, bookings list, booking detail; triage (status, assignment, notes, lifecycle actions); CSV export; manual connection resend |
| `manage_options` | Everything above, plus Settings and Connections (endpoint URLs, secrets, gateway configuration) |

There is no bundled 2FA — enable it via an approved security plugin or host
policy (see `docs/launch-checklist.md`, section 5.4).

## Dashboard

**Tour Manager → Dashboard** (`admin.php?page=wpistic-tour-manager`).

A date-range selector (`?since=&until=`, defaults to the last 30 days)
drives every period-bound figure and its percentage-delta comparison
against an equal-length prior period.

KPI cards, each with its exact data source:

| KPI | Source |
|---|---|
| New inquiries | Bookings created in the period |
| Awaiting review | `portal_status = 'new'`, all time |
| Awaiting customer | `portal_status = 'sent'`, all time |
| Open bookings | Lifecycle not in completed/expired/refunded/cancelled |
| Confirmed bookings | Lifecycle in confirmed/balance_due/paid_in_full |
| Upcoming departures | `wpistic_departure` posts with `wpistic_dep_date` &ge; today |
| Deposits paid | Paid deposit transactions in the period |
| Outstanding balances | Sum of `balance_amount` for bookings awaiting final payment |
| Revenue | Paid deposit + balance transactions in the period. **Approximation**: sums raw transaction amounts across gateways with no currency conversion. |
| Failed deliveries | Connection dispatches outside the 2xx range in the period |

Below the KPIs: inquiry-source and pipeline breakdowns (CSS-only bar
charts, no chart library or CDN dependency), recent inquiries, upcoming
departures, a payment summary by gateway, integration health (each
connection's enabled state and most recent dispatch), and quick-action
links into pre-filtered views of the bookings list.

**"Upcoming departures" needs data you may not have yet.** The
`wpistic_dep_date` field (a native HTML5 date input on the Departure edit
screen) must be filled in on each departure post. Until then the KPI and
list correctly show zero — nothing is fabricated to make the dashboard
look populated.

## Bookings & Inquiries

**Tour Manager → Bookings & Inquiries** (`admin.php?page=wpistic-tm-bookings`).

Server-side paginated (replacing the old fixed 200-row list), with:

- Search (name, email, reference)
- Filters: type, lifecycle stage, workflow stage, payment status
  (derived from the lifecycle — see below), assigned staff, tour, date range
- Sortable columns: reference, name, created date
- Bulk actions: assign to a staff member, set workflow status
- CSV export that respects whatever filters are currently active

**Payment status is derived from the booking lifecycle**, not a separate
field — the lifecycle already advances deterministically as deposits and
balances are marked paid, so a parallel payment-status field would be
redundant and could drift out of sync. The groups: `unpaid`
(inquiry/quoted/deposit_link_sent), `deposit_paid`
(deposit_paid/confirmed), `balance_due`, `paid_in_full`
(paid_in_full/completed).

Every filter value is bound through `$wpdb->prepare()`; the sort column is
checked against a fixed allow-list before use, since `prepare()` can
parameterize values but never identifiers.

## Booking detail

Open any booking (`&view={id}`) for a tabbed view:

- **Overview** — reference, type, lifecycle, workflow, assignment, source
  URL, special requests
- **Traveler** — name, email, phone, country, party size, hotel preference
- **Trip** — the associated tour (resolved to its actual title and edit
  link, not just a raw id) and departure, if any
- **Payments** — price/deposit/balance figures and the transaction ledger
- **Activity** — the full audit trail for this booking
- **Connections** — this booking's Tourflows/webhook delivery history, and
  a manual "Resend to connections" action

The sticky action panel (right-hand column on desktop, stacked below the
tabs on mobile) carries every state-changing action: workflow status,
assignment, a note field, lifecycle transitions (confirm, mark deposit
paid, etc. — the exact set available depends on the booking's current
status), and deposit/balance payment-link generation.

### Manual resend and delivery history

See `docs/tourflows-integration.md` for the full mechanism. In short: every
connection dispatch attempt is mirrored into the existing audit log (no new
database table), so the Connections tab can show a booking's delivery
history without a schema change, and "Resend to connections" re-dispatches
`inquiry.created` without re-creating the booking or re-sending the guest's
acknowledgement email.

## Light / dark mode

The dashboard has its own token system, independent of the frontend's
(see `docs/light-dark-mode.md`) — wp-admin never loads the frontend theme's
stylesheet, so it needed a self-contained set scoped to
`.wpistic-tm-dashboard`. A signed-in user's choice (light/dark/auto)
persists in `user_meta` and survives across devices.

## What this dashboard deliberately does not do

No full sales pipeline, supplier management, accounting, advanced
quotation, complex CRM reporting, marketing automation, or itinerary
builder. Anyone needing those uses Tourflows directly — adding them here
would make this a second CRM, which is explicitly out of scope.

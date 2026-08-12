=== WPistic Tour Manager ===
Contributors: wordpressistic
Requires at least: 6.4
Requires PHP: 8.1
Stable tag: 2.5.0
License: GPLv2 or later

Premium tour, booking, deposit and payment system for Brother Tours.

== Changelog ==

= 2.5.0 =
* Bump release version to 2.5.0.

== Architecture ==

Framework-agnostic core + WordPress adapter. The domain logic (booking state machine,
deposit calculation, payment-gateway contract, HMAC signing) lives in the
`Wpistic\TourCore\` package and contains ZERO WordPress calls, so the same rules run
under a future Laravel app. This plugin is the WordPress binding.

Bundled autoloader (src/autoload.php) loads:
  - Wpistic\TourManager\  -> src/
  - Wpistic\TourCore\     -> lib/tour-core/src/  (production)  OR
                             ../../packages/tour-core/src/      (monorepo dev)

No `composer install` is required.

== Production packaging ==

When building a standalone plugin zip, copy the core package into the plugin:

    packages/tour-core/src  ->  adapters/wp-tour-manager/lib/tour-core/src

The autoloader checks `lib/tour-core/src` first. In the monorepo it is absent, so the
sibling `packages/` source is used directly during development.

== What it provides ==

* CPTs: wpistic_tour, wpistic_destination, wpistic_experience, wpistic_departure
* Taxonomies: country, region, travel_style
* Tour authoring: itinerary, packages, pricing, deposit override (fixed OR percent)
* Booking engine: inquiry -> human quote -> deposit link (human-confirmed; no self-serve checkout)
* Gateways: Stripe, PayPal, Bank/Wise, Binance (wallet address + Binance Pay) behind one interface
* Signature-verified, idempotent webhooks; transactions ledger; immutable audit log
* Generic Connections engine (signed outbound webhooks to any 3rd-party tool)
* Lightweight admin portal (dashboard, list, detail, workflow, CSV export)
* SEOISTIC data filters (SEOISTIC emits the JSON-LD)
* Emails via Formistic (wp_mail fallback)

== Changelog ==

= 2.0.0 =
* New: modernized admin dashboard (KPI cards, date-range comparison, integration
  health, paginated/filterable/sortable bookings list with bulk actions and CSV
  export, tabbed booking detail screen). See docs/tour-manager-guide.md.
* New: ingestion support for Formistic's fifth Brother Tours form, "Request
  Tour Availability" — its hidden tour_id now populates the booking's tour_id
  column directly instead of only ever coming from the booking widget.
* Change: coordinated suite version bump to 2.0.0. No database schema change
  in this release — WPISTIC_TM_DB_VERSION stays 1.1.0.

# 06 - Code and Plugin Audit

Audit date: 2026-08-11. Source findings are E2; WordPress/browser/payment behavior remains E0 unless explicitly observed over public HTTP.

## Release baseline

- The supplied root is an extracted non-Git bundle. The reported branch, split commit, remote and clean status cannot be independently verified.
- Operations API is version `1.0.0` and namespace `bt-ops/v1` in `plugins/brother-tours-operations-api/brother-tours-operations-api.php:6,28-31`; its README uses `VITE_BT_OPS_API_BASE` at line 344. This conflicts with the reported `1.1.0`, `bridgistic-api/v1` and `VITE_BT_API_BASE` release.
- WPistic parent theme, Brother Tours child theme, WPistic Tour Manager and Formistic are version `2.0.0`. The child correctly declares `Template: wpistic`.
- SEOISTIC is absent from the inspected tree. The source assumes SEOISTIC consumes `seoistic/*_data` filters, but rendered ownership cannot be tested.
- `scripts/build-release.sh` omits Operations API and SEOISTIC, and requires Git. The current bundle therefore cannot produce the claimed complete release.

## Critical/high findings

### BTL-SEC-001 - sensitive Operations API routes use `edit_posts`

`src/Auth/Csrf.php:48-80` checks only the supplied capability. Booking/customer PII, payment-link actions, form submissions/replies/newsletter records and team records use `edit_posts` in the Operations API controllers. Contributor-like roles can therefore receive more access than operational least privilege permits.

Acceptance: dedicated capabilities for booking, inbox, payment and team administration; automated 403 tests for Contributor and success tests for the intended role under cookie/CSRF and Application Password auth.

### BTL-PAY-001 - webhook reconciliation is not transaction-safe

`Payments/WebhookController.php:55-104` records the event before completing reconciliation. A crash can make a retry appear processed. Reconciliation guesses deposit/balance from booking state and transaction schema has no unique idempotency constraint. Stripe amount is null and signature timestamp age is not bounded.

Acceptance: atomic exact-transaction claim by gateway/idempotency identity; amount/currency/type verification; processed marker after successful state transition; unique constraint plus concurrency and crash/retry tests.

### BTL-SCH-001 - unsupported Offer/availability output

`Integration/SchemaData.php:24-42` always returns an `Offer`, defaults USD and hard-codes `LimitedAvailability`, even with blank or unverified data.

Acceptance: omit Offer without visible verified numeric price/currency; availability only from authoritative inventory; rendered/schema parity tests.

### BTL-SEED-001 - seeder overwrites reviewed content

`Admin/ContentSeeder.php:93-175` republishes and overwrites existing destinations/tours, resets taxonomy and writes fixed itinerary, FAQ, season, inclusion and price content. There is no dry run, backup, reviewed-content guard or rollback.

Acceptance: preview, stable ownership markers, fill-only default, explicit force plus export, audit log and rerun tests proving manual edits survive.

### BTL-URL-001 - route ownership conflicts

- CPT singles use `/tour/{slug}/`, while theme fallbacks generate `/tours/{slug}/` in `archive-tour.php`, `template-tags.php` and `page-travel-from.php`.
- Legal registry uses `/privacy/`, `/terms/`, `/cancellation/`; parent footer uses `/privacy-policy/` and `/terms-and-conditions/`.
- Footer destination fallbacks reference unseeded or conflicting destination slugs.
- Nested experience rewrite ignores destination ownership, allowing a valid experience beneath the wrong destination path.

Acceptance: one approved slug registry used by CPTs, templates, seeders, canonicals, schema, sitemaps and internal links; wrong-parent paths 404 or one-hop redirect to the approved canonical.

### BTL-FORM-001 - public booking duplicate/abuse controls are insufficient

`Rest/CaptureController.php:28-103` creates and dispatches a booking after nonce, honeypot and email checks, but lacks durable server-side rate limiting and unique request idempotency. The reusable nonce is globally exposed. Formistic has stronger CAPTCHA/rate/dedupe patterns that should inform the canonical implementation.

Acceptance: server-side abuse control and unique request idempotency; replay/double-click/concurrency tests create exactly one booking and notification.

### BTL-ENT-001 - placeholders and unsupported trust facts can render

Theme defaults include `+856 21 000 000`; templates publish founding/licensing claims and single-tour fallbacks can invent itinerary/highlight/inclusion content. Live production also contains `hello@brothertors.com` and rating/history claims requiring verification.

Acceptance: suppress missing facts, remove invented fallbacks, approve one fact sheet, and scan representative rendered pages for placeholders or unsupported claims.

## Medium findings

- Front page bypasses normal `the_content()`/Elementor Theme Builder handoff.
- Tour filtering and the `wpistic_contact_form` shortcode have duplicate owners, so behavior depends on hook/plugin order.
- Newsletter failure logging can include raw subscriber email addresses.

## Positive controls

- Reviewed REST routes include permission callbacks; public session/webhook/unsubscribe routes add credential, signature or token checks.
- Formistic admin AJAX uses capability and nonce validation.
- Upload handling blocks executable/double extensions, verifies MIME and gates downloads.
- Formistic-to-booking ingestion uses a unique source/external ledger.

## Validation baseline

- PHP syntax: **154/154 passed** under PHP 8.2.31.
- Brand lint: **passed**, 0 errors; warnings/manual-review items remain for human review.
- Static credential/sensitive-file scan: no credential-shaped values, `.env`, PEM/key or log files found in the extracted bundle.
- Blocked/unavailable: Git release checks, Composer, WP-CLI, PHPUnit, PHPCS/PHPStan, WordPress activation/upgrades, browser/Elementor QA, email delivery, payment/webhook sandbox tests and rendered SEO ownership validation.

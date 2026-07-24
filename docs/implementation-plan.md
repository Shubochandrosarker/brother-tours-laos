# Implementation plan

Written during Phase 0, after auditing the parent theme, the child theme, Tour
Manager, the Formistic source and the full construction pack. Updated to record
what was built and what remains.

## Architecture

```
themes/
  wpistic/            reusable presentation base (shared with other sites)
  brother-tours/      site-specific child: locked design system, routes, CTAs
plugins/
  wpistic-tour-manager/     tours, bookings, payments, connections, portal
  brother-tours-formistic/  Brother Tours Forms — vendored Formistic fork
  wpistic-crm/              pre-existing, unrelated to this build
docs/                 this documentation set
scripts/              brand-lint.php, release-check.sh
```

### Layout decision

The repository was previously flat: `wpistic/`, `brother-tours/`,
`wpistic-tour-manager/`, `wpistic-crm/` at the root, with no theme/plugin
separation. The build brief prescribes `themes/` and `plugins/` and permits
adapting only when an equivalent standard layout already exists. None did, so
the prescribed structure was adopted via `git mv` (history preserved).

**Deployment impact:** anything that previously synced the repository root into
`wp-content` must now map `themes/` → `wp-content/themes` and `plugins/` →
`wp-content/plugins`. See the README.

## Data ownership

The boundary that matters most, since both plugins touch inquiries:

| Concern | Owner |
|---|---|
| Form definitions, fields, rendering, validation, spam, consent, submission storage, CSV, guest email | Brother Tours Forms (Formistic) |
| Tours, destinations, experiences, departures, capacity, pricing, deposits, payments | Tour Manager |
| Inquiry records, portal triage, Tourflows dispatch | Tour Manager |
| Leads, opportunities, quotations, itineraries, suppliers, reporting | **Tourflows** (external) |
| Presentation, templates, tokens, navigation | Child theme |

The site is not a second CRM. The portal does triage — status, assignment, notes,
resend, export — and nothing more.

## Integration map

```
Formistic form submit
  → Capture::store()            spam gate, 60s dedupe, insert submission
  → wpistic_formistic_submission_captured
      → FormisticIngestion::ingest()      ← the ONLY listener creating bookings
          route by _brother_tours_form slug (unknown form ⇒ ignore)
          claim in wpistic_form_ingestions (UNIQUE KEY decides the winner)
          BookingService::create()        one booking
          audit: consent, UTM, referrer
          wpistic/notify inquiry.created  → team + guest email
          ConnectionsManager::dispatch()  → Tourflows

Tour page Request Availability (separate, by design)
  → REST POST /wp-json/wpistic/v1/booking
  → CaptureController → BookingService::create() → notify → dispatch
```

`ConnectionsManager::dispatch_notification()` deliberately skips
`inquiry.created`, so firing `wpistic/notify` does not also auto-dispatch. The
explicit `dispatch()` call is the single outbound event.

## Migration

| Change | Safety |
|---|---|
| New table `wpistic_form_ingestions` | Created by `dbDelta`; additive only |
| `WPISTIC_TM_DB_VERSION` → `1.1.0` | Triggers `Plugin::maybe_upgrade()` on the first admin request |
| Seeded pages, forms, connection profile | Fill-only; existing records never rewritten |
| `/plan-my-laos-trip/` | 301 to `/build-my-trip/`; page not deleted |

No table is dropped, no column removed, no slug rewritten, no data deleted.
Rollback is therefore mostly a code operation — see `docs/rollback.md`.

## Risks

| Risk | Mitigation |
|---|---|
| Two Formistic codebases active ⇒ fatal on class redeclaration | Duplicate guard before every `require_once`, plus an admin notice |
| Duplicate inquiries from replayed hooks | DB `UNIQUE KEY` claim, not a read-then-write check |
| Duplicate leads in Tourflows | `reference` is the idempotency key; Tourflows must key on it |
| A stalled claim blocking a submission forever | Claim released on every path that creates no booking |
| WP-Cron not firing ⇒ late retries | Document a system cron; `DISABLE_WP_CRON` in the README |
| Editor renames a form ⇒ misrouted inquiries | Routing keyed on slug meta, not title |
| Editor deletes and recreates a form ⇒ routing tag lost | Documented in the content-import guide |
| Blocking CSP breaks payment/analytics embeds | Ships report-only behind `brother_tours/csp_enforce` |
| Locked palette drifts | Single token file; brand lint on phrases |
| Publishing an unverified rating claim | Brand lint fails on `AggregateRating`, `ratingValue`, numeric rating claims |
| Legacy URLs guessed ⇒ wrong 301s | Redirect map ships empty; URLs must come from Search Console |

## Completed

- Repository restructure, history preserved
- Formistic vendored, branded, guarded; `UPSTREAM.md` provenance recorded
- Four forms seeded idempotently with stable routing slugs
- Single-ingestion adapter with DB-level idempotency; `Notifier::on_formistic()` removed
- Schema upgrade routine
- Locked design tokens and typography in the child theme
- Parent CTA, nav and footer made filterable; child sets the locked values
- Tours archive filters wired to the taxonomies
- Mobile sticky Build My Trip, keyboard reachable, yields to focused inputs
- Reduced-motion support, visible focus ring
- Site seeder for the locked routes; drafts for unreviewed content
- Tourflows connection profile registered, disabled, no secret
- Security headers; CSP report-only behind a filter
- Analytics and verification fields as site settings
- `scripts/brand-lint.php`, `scripts/release-check.sh`
- Documentation set

## Open items

Ordered by how much they block a launch.

### Blocking

1. **Nothing has run against WordPress.** No install, database or browser was
   available. Every functional claim needs verification on staging with
   `WP_DEBUG` on. See `docs/launch-checklist.md`.
2. **Owner-supplied content**: photography, verified testimonials, Tourflows
   endpoint and secret, Google/TripAdvisor profile URLs, the legacy URL export,
   legal review of the three policy pages.

### Non-blocking

3. Destination pillar and tour detail pages need real copy in the CPTs. The
   templates and the homepage now read from those CPTs, so this is data entry
   rather than development.
4. The alternate homepage variants (`parts/home-v2.php`, `parts/home-v3.php`),
   `single-destination.php`, `page-travel-from.php` and the tours archive's
   empty-state fallback still read `inc/sample-data.php`. The default homepage
   (V1) and the archive's real loop are CPT-driven; the variants were left for a
   separate change because they are not on the launch path.
5. Journal starter posts — drafts and seeds only; no fabricated articles.
6. Exhausted Tourflows deliveries are visible only in `wpistic_connection_log`.
   An admin notice or portal flag after three failures would be better.
7. Consent-gated analytics: the Customizer fields exist; the loader that gates
   GA4/Pixel/Clarity behind the cookie banner is not yet written.
8. `themes/wpistic/_preview-*.html` — 13 export artifacts inside the theme.
   Should not reach production. Pre-existing; removal left as a separate change.
9. `home.php` and `page-laos-travel-guide.php` are two journal indexes;
   `page-build-my-trip.php` and `page-plan-my-laos-trip.php` are two copies of
   one page. Consolidate.
10. No automated test suite for the ingestion path. The idempotency guard is the
    highest-value thing to cover — assert that firing the captured action twice
    for one submission yields exactly one booking.
11. PHPCS with WordPress standards is not configured in the repository.

### Resolved after the plan was first written

- Homepage now reads the `wpistic_tour` and `wpistic_destination` CPTs, falling
  back to samples only while they are empty.
- Tour card links follow the CPT's registered rewrite via `get_permalink()`,
  fixing cards that pointed at `/tours/{slug}/` against a `/tour/{slug}/` rewrite.
- Form seeding no longer depends on activation running before `init`.
- A form submission arriving before the schema upgrade can no longer be lost.

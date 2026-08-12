# Architecture

A system map of what lives where, who owns what data, and how the pieces
talk to each other. For narrower topics see the linked documents rather
than duplicating them here.

## The four shipped components

```
themes/
  wpistic/            reusable parent presentation theme
  brother-tours/       Brother Tours child theme — locked brand, routes, CTAs
plugins/
  wpistic-tour-manager/  tours, destinations, experiences, departures,
                          bookings, payments, Tourflows connections,
                          the admin dashboard
  formistic/             form capture, validation, consent, storage,
                          email delivery — WordPressistic's product,
                          configured for Brother Tours
```

All four are versioned together (currently 2.5.0) but remain independently
installable plugins/themes — nothing here creates a hard runtime dependency
of one on another beyond the two documented integration points below.

## Ownership boundaries

**wpistic (parent theme)** owns reusable presentation: base templates,
design-token plumbing, the light/dark toggle mechanism, Elementor theme
locations and CPT support. It ships no Brother Tours copy or branding —
a future site could reuse it with a different child theme.

**brother-tours (child theme)** owns everything Brother Tours-specific:
the locked color/type tokens, navigation and CTA copy, the 18 Elementor
widgets, security headers, and Customizer fields for reviews/analytics.
Presentation only — see the "Keep business logic out of themes" rule below.

**wpistic-tour-manager** owns tours, destinations, experiences, departures,
availability, the booking/inquiry lifecycle, pricing, deposits, payments,
the Tourflows dispatch, and operational reporting (the admin dashboard).
This is where business logic lives, framework-agnostic where possible
(`lib/tour-core/` has zero WordPress calls — see its own README).

**formistic** owns form definitions and fields, rendering, validation,
nonces, honeypot, rate limiting, spam protection, consent capture,
submission storage, audit history, CSV export, and email delivery. It does
not know what a "tour" or a "booking" is — see
`docs/formistic-brother-tours-integration.md` for the exact boundary and
the single-ingestion guarantee that keeps the two plugins from ever
double-creating a record.

**Rule enforced throughout:** business logic (tour/payment/form-processing/
email/integration logic) belongs in the plugins, never in a theme. The
child theme may contain presentation, template overrides, enqueues, and
Customizer/site settings only.

## The two integration points

1. **Formistic → Tour Manager.** A guest submission fires
   `wpistic_formistic_submission_captured`. `Integration\FormisticIngestion`
   is the *only* listener allowed to turn that into a booking — routing by
   the form's stable slug meta, claiming the submission via a database
   unique-key insert before creating anything, and dispatching to
   Tourflows exactly once. Full detail:
   `docs/formistic-brother-tours-integration.md`.
2. **Tour Manager → Tourflows (or any connection).** `ConnectionsManager`
   is a generic, HMAC-signed outbound webhook engine; Tourflows is one
   configured connection, not a hard dependency. Full contract, retry
   behavior, and the v2.0.0 delivery-history mechanism:
   `docs/tourflows-integration.md`.

## Presentation layer: Elementor and templates

Every route has a full PHP template that renders on its own. Elementor is
optional: when active, `elementor_theme_do_location()` lets an assigned
Elementor template take over a location (header/footer/single/archive/404);
when inactive or no template is assigned, the PHP fallback renders exactly
as before. The 18 Brother Tours widgets call existing theme/plugin
functions and shortcodes rather than re-implementing their logic — see
`docs/elementor-guide.md`.

## Data model quick reference

| Post type / table | Owner | Purpose |
| --- | --- | --- |
| `wpistic_tour`, `wpistic_destination`, `wpistic_experience`, `wpistic_departure` | Tour Manager | Content model — see `PostTypes\ContentTypes` |
| `{prefix}wpistic_bookings` | Tour Manager | Every inquiry/booking, one row per guest request |
| `{prefix}wpistic_transactions` | Tour Manager | Payment attempts against a booking |
| `{prefix}wpistic_audit_log` | Tour Manager | Immutable event trail — status changes, notes, connection dispatch history (v2.0.0) |
| `{prefix}wpistic_connections`, `{prefix}wpistic_connection_log` | Tour Manager | Outbound webhook config and delivery attempts |
| `{prefix}wpistic_form_ingestions` | Tour Manager | Idempotency ledger for the Formistic → booking boundary |
| `formistic_form` (CPT) | Formistic | The five Brother Tours form definitions |
| Formistic submissions table | Formistic | Every raw form submission, independent of whether it became a booking |

## Where to look next

- New to the repo? Start with `docs/source-inventory.md` (what was found)
  and `docs/implementation-plan.md` (what was built and why).
- Upgrading an existing install? `docs/upgrade-to-2.0.0.md`.
- Cutting a release? `docs/release-checklist.md`.

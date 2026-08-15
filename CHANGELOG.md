# Changelog

Version history across the six Brother Tours 3.0.0 components. Per-component
detail lives in each plugin's own documentation; this file is the top-level
summary of what shipped together.

## 3.0.0 — clean client release

### Added

- Version-aligned 3.0.0 parent and child themes.
- Version-aligned Formistic, WPistic Tour Manager, Content Studio, and
  Operations API packages.
- Client-facing installation, configuration, migration, security, SEO,
  rollback, and Operations API documentation.
- A reproducible package builder for six individual components, one suite
  bundle, and SHA-256 checksums.
- An explicit `archive/pre-3.0.0/` boundary preserving the prior working tree
  and historical artifacts without mixing them into deployable folders.

### Changed

- Review copy uses neutral guest-note wording and does not claim unverified
  ratings or AggregateRating schema.
- Content Studio and Operations API are packaged explicitly instead of being
  silently omitted from the release bundle.
- The release remains configuration-driven: secrets, credentials, database
  exports, and production media are not stored in Git.

### Upgrade note

3.0.0 is a coordinated code/package version, not an automatic database
migration. Follow `docs/content-migration.md`, take backups, and test the
staging install before running any seeder or migration.

## 2.0.0 — coordinated suite release

### Removed

- `plugins/wpistic-crm` — an unrelated Guns2Ammo firearms-retailer CRM,
  confirmed via its own `guns2ammo-crm` text domain and `G2A_CRM` package
  constant, with zero cross-references from any Brother Tours code.
- Every G2A code path from Formistic: `class-formistic-g2a-defaults.php`
  (a *reachable* admin action that seeded real G2A business facts into the
  AI knowledge base — a live defect, not dead weight), the legacy
  `g2a_request`/`g2a_reservation` theme-form capture, its option and
  settings row, and the `g2a_biz()`/`guns2ammo` template fallbacks in the
  email layer.
- `Admin\Portal` (Tour Manager) — superseded by `Admin\Dashboard` +
  `Admin\Bookings` + `Admin\BookingDetail`; menu URLs unchanged.

### Changed

- Formistic renamed from "Brother Tours Forms" back to **Formistic**,
  authored by **WordPressistic** — the product's own identity, not a
  white-labeled fork name. Directory `plugins/brother-tours-formistic/` →
  `plugins/formistic/`. Customer-facing forms and emails stay Brother
  Tours branded.
- All four components coordinated at version **2.0.0**. No database
  schema change in this release for either plugin.
- Frontend light mode is now a genuine second palette (previously
  identical to dark mode), WCAG AA-checked pairing by pairing. Dark mode
  is unchanged and remains the default.
- The Tour Manager admin is a modernized dashboard: KPI cards with a
  date-range comparison, a paginated/filterable/sortable bookings list
  (replacing a fixed 200-row query), bulk actions, and a tabbed booking
  detail screen (Overview / Traveler / Trip / Payments / Activity /
  Connections). Its own light/dark theme, persisted per user.
- `wpistic_dep_date` (Departure post meta) upgraded from a free-text field
  to a native date input.

### Added

- A fifth Formistic form, **Request Tour Availability**, with hidden
  `tour_id`/`tour_title` fields — a second, Formistic-rendered entry point
  alongside (not instead of) Tour Manager's own booking widget.
- Elementor integration: theme locations with PHP fallback
  (header/footer/single/archive/404), additive CPT editing support for
  `wpistic_tour`/`wpistic_destination`/`wpistic_experience`, and 18
  Brother Tours widgets under their own panel category.
- Tourflows/webhook delivery history on each booking's detail screen, and
  a manual resend action — both built on the existing audit log via a new
  `wpistic_tm_connection_dispatched` action, with no schema migration.
- `themes/brother-tours/theme.json` (previously absent — the child theme
  inherited the parent's own palette/fonts for the block editor and
  Elementor globals instead of the locked Brother Tours design).
- Generic semantic CSS token aliases (`--color-background`,
  `--color-primary`, `--color-danger`, etc.) mapped onto the brand tokens.
- `unrelated-client` rule in `scripts/brand-lint.php` and a fifth,
  independent gate in `scripts/release-check.sh` — both fail the build if
  a G2A/unrelated-client identifier is found in a deployable file.
- Documentation: `docs/architecture.md`, `docs/elementor-guide.md`,
  `docs/tour-manager-guide.md`, `docs/light-dark-mode.md`,
  `docs/upgrade-to-2.0.0.md`, `docs/release-checklist.md`.

### Fixed

- The old `Notifier::on_formistic()` created a booking for **every**
  captured Formistic submission with no idempotency guard — this was
  already replaced by `Integration\FormisticIngestion` before v2.0.0 and
  remains the sole, guarded ingestion path.
- A CSS selector bug caught before shipping: the Tour Gallery widget's
  Columns control targeted `.gallery-grid[style*="..."]`, but Elementor's
  responsive `selectors` config emits a page-level `<style>` rule, not an
  inline `style` attribute — the control would have silently done nothing.
  Fixed to a `var(--bt-ew-gal-cols, 4)` rule applied directly to the
  selector.
- A recursion bug caught before shipping: an early draft of the
  frontend's dark-mode-default filter called `get_theme_mod()` from
  inside its own `theme_mod_wpistic_default_mode` filter, which would
  have recursed until the stack overflowed. Fixed to read the raw
  `theme_mods` array directly.

## 1.1.0 and earlier

See individual plugin `readme.txt` changelogs and `docs/source-inventory.md`
for the initial Brother Tours build (repository restructure, the original
Formistic fork, the single-ingestion adapter, brand-lint, and the base
theme design system).

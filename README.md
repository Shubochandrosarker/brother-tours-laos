# Brother Tours

The Brother Tours website: a WordPress parent theme, a site-specific child
theme, and the plugins that run tours, inquiries, payments and the Tourflows
connection. Elementor-editable throughout, with a modern operations
dashboard and full light/dark support on both the frontend and in wp-admin.

**Coordinated release version: 2.0.0.** See `docs/upgrade-to-2.0.0.md` if
upgrading an existing install, and `CHANGELOG.md` for what changed.

*Born Here. Guide Here.*

## Layout

```
themes/
  wpistic/                  reusable presentation base
  brother-tours/            site-specific child theme
plugins/
  wpistic-tour-manager/     tours, bookings, payments, connections, portal
  formistic/                Formistic (vendored fork, WordPressistic-authored)
docs/                       architecture, integration and launch documentation
scripts/                    brand-lint.php, release-check.sh
```

Deployment maps `themes/` → `wp-content/themes/` and `plugins/` →
`wp-content/plugins/`. The repository root is **not** `wp-content`.

## Requirements

- PHP 8.1+ (Tour Manager requires it; the Formistic fork runs on 7.4+)
- WordPress 6.4+
- MySQL 5.7+ / MariaDB 10.3+
- HTTPS, with a valid auto-renewing certificate

No Composer or npm build step. Tour Manager ships its own PSR-4 autoloader and
bundles `tour-core` under `lib/`.

## Install

### 1. Files

```sh
rsync -a themes/wpistic          /path/to/wp-content/themes/
rsync -a themes/brother-tours    /path/to/wp-content/themes/
rsync -a plugins/wpistic-tour-manager    /path/to/wp-content/plugins/
rsync -a plugins/formistic /path/to/wp-content/plugins/
```

### 2. Activation order

Order matters — Tour Manager's ingestion adapter checks for the Formistic
classes at boot.

1. **Formistic** (`plugins/formistic/`) — creates the submission tables and
   seeds the five Brother Tours forms (Build My Trip, Contact, Newsletter,
   Travel Agent, Request Tour Availability). Presents in wp-admin as
   "Formistic" by "WordPressistic"; customer-facing forms and emails stay
   Brother Tours branded regardless.
2. **WPistic Tour Manager** — creates the booking, connection and ingestion
   tables and registers the CPTs.
3. Theme **Brother Tours** (the child; it activates `wpistic` as its parent).
4. Optional: **Elementor** (Free or Pro). The site works fully without it —
   every template has a PHP fallback. When active, theme locations, CPT
   editing, and 18 Brother Tours widgets become available; see
   `docs/elementor-guide.md`.

> **Only one Formistic may be active.** The standalone Formistic plugin (or
> any other install of it) declares the same classes. This install's
> bootstrap guards against loading both — you get an admin notice rather
> than a fatal — but you must still deactivate the other one.

### 3. Permalinks

**Settings → Permalinks → Save.** Tour Manager flushes rewrites on activation,
but a manual save is the reliable way to pick up the CPT routes.

### 4. Scaffold the site

**Tour Manager → Site Setup → "Create missing pages and profile"**.

Creates the Brother Tours pages and registers a disabled `Tourflows` connection
profile. Fill-only: it never modifies a page that already exists, so it is safe
to run again later.

Pages needing owner copy or legal review are created as **drafts** — FAQ, Visa
Guide, When to Visit, Privacy, Terms, Cancellation, Sitemap.

### 5. Set the front page

**Settings → Reading** → static front page. The Journal page is set as the posts
page automatically if one was not already set.

## Configuration

### Email

**Options**, editable in the admin (no credentials stored in the repository):

| Option | Purpose | Default |
|---|---|---|
| `brother_tours_from_email` | Sender address | `enquiry@brothertours.com` |
| `brother_tours_from_name` | Sender name | Site name |
| `brother_tours_reply_to` | Reply-To | Sender address |
| `wpistic_tm_from_email` | Tour Manager team notifications | Admin email |

`wp_mail()` is a documented fallback, not the delivery plan. Configure a
transactional provider (SMTP plugin or API), then set up **SPF**, **DKIM** and
**DMARC** for the sending domain. These are launch checklist items — no code in
this repository can make a domain deliverable.

### Tourflows

**Tour Manager → Connections** → the `Tourflows` profile. Add the endpoint and
signing secret, then enable it. Full contract, signature format, retry behavior
and failure recovery: **`docs/tourflows-integration.md`**.

Never commit the endpoint or the secret.

### Payments

**Tour Manager → Settings**. Stripe, PayPal, Binance and bank transfer are
supported. Each gateway's keys are stored as options.

Webhook endpoints:

```
POST /wp-json/wpistic/v1/webhook/stripe
POST /wp-json/wpistic/v1/webhook/paypal
POST /wp-json/wpistic/v1/webhook/binance
```

Signatures are verified and events de-duplicated on the `wpistic_webhook_events`
table. There is no self-serve checkout by design: a human confirms the quote,
then sends a deposit link.

### Analytics

**Appearance → Customize → Brother Tours — Analytics**: GA4 measurement ID, Meta
Pixel ID, Microsoft Clarity ID, Search Console and Bing verification tokens.

These are public identifiers, not secrets. Non-essential tags must load only
after consent — see the open item in `docs/implementation-plan.md`.

### Content Security Policy

Ships **report-only** so an untested policy cannot break payment links or
consent-gated tags. Once the report endpoint is quiet:

```php
add_filter( 'brother_tours/csp_enforce', '__return_true' );
```

Adjust the policy itself with the `brother_tours/csp` filter.

### Cron

Tourflows retries are scheduled through WP-Cron, which only runs when the site
receives traffic. On a low-traffic site, use a real system cron:

```php
// wp-config.php
define( 'DISABLE_WP_CRON', true );
```

```cron
*/5 * * * * curl -s https://brothertours.com/wp-cron.php?doing_wp_cron >/dev/null
```

### Security

- Require 2FA for every admin account, via an approved security plugin or host
  policy. **Do not build a custom 2FA system.** None is bundled.
- Dashboard access uses `edit_posts` (triage) and `manage_options` (settings and
  connections). Grant reviewers the least privilege that lets them work.

### Elementor

Optional. Every route works without it — theme locations
(header/footer/single/archive/404) fall back to the existing PHP templates
when no Elementor template is assigned or Elementor is inactive. When
active, `wpistic_tour`/`wpistic_destination`/`wpistic_experience` become
Elementor-editable (additive to whatever post types are already enabled —
never overwritten), and a "Brother Tours" widget category ships 18 widgets
covering tours, destinations, forms, and CTAs. Full guide, including which
behavior needs Elementor Pro's Theme Builder versus what Elementor Free
already covers: **`docs/elementor-guide.md`**.

### Tour Manager dashboard

**Tour Manager → Dashboard / Bookings & Inquiries** — KPI cards with a
date-range comparison, a paginated/filterable/sortable bookings list, bulk
assign/status actions, CSV export, and a tabbed booking detail screen
(Overview / Traveler / Trip / Payments / Activity / Connections) including
Tourflows delivery history and a manual resend action. Full guide:
**`docs/tour-manager-guide.md`**.

### Light / dark mode

Both the frontend and the dashboard support light and dark modes, each a
genuine second palette (not a copy of the other) checked against WCAG AA.
The frontend defaults to dark — Brother Tours' primary, locked identity —
with a keyboard-accessible toggle that persists per visitor; the dashboard
persists per signed-in user. Full explanation, including the exact token
values and why light mode needed a split gold token: **`docs/light-dark-mode.md`**.

## Before release

```sh
sh scripts/release-check.sh
```

Runs `php -l` across the themes and plugins, the brand lint, and a secret scan.

The brand lint alone:

```sh
php scripts/brand-lint.php
```

It fails on banned words, retired phrases, budget language and any unverified
rating or award claim — including `AggregateRating` schema, which must not be
emitted until the verified-review threshold is reached.

## Staging to production

1. Back up the production database **and** `wp-content`. Verify the backup
   restores — an untested backup is not a rollback plan.
2. Deploy to staging first. Activate in the order above with `WP_DEBUG` on and
   confirm no notices, warnings or fatals.
3. Work through `docs/launch-checklist.md`. Do not mark an item passed without
   testing it.
4. Submit all five Formistic forms plus the tour booking widget. Confirm
   **exactly one** inquiry and **one** Tourflows delivery per submission.
5. Test a failed Tourflows delivery and a manual resend.
6. Verify webhook signature rejection with a deliberately bad signature.
7. Load the redirect map from the Search Console export. Do not guess old URLs.
8. Deploy, flush permalinks, re-run the smoke tests on production.
9. Submit the sitemap to Search Console and Bing Webmaster Tools.

Rollback: **`docs/rollback.md`**.

## Documentation

| Document | Contents |
|---|---|
| `docs/architecture.md` | System map: themes, plugins, data ownership, how they talk to each other |
| `docs/source-inventory.md` | Supplied sources, precedence, conflicts resolved, defects found |
| `docs/implementation-plan.md` | Architecture, data ownership, integration map, migration, risks, open items |
| `docs/formistic-brother-tours-integration.md` | Why the fork is shaped this way; safe upstream merges |
| `docs/tourflows-integration.md` | Payload contract, signature, idempotency, retries, recovery |
| `docs/elementor-guide.md` | Theme locations, CPT support, all 18 widgets, Free vs Pro behavior |
| `docs/tour-manager-guide.md` | Dashboard, bookings list, booking detail, roles, capabilities |
| `docs/light-dark-mode.md` | Token system, contrast verification, defaults, how to extend it |
| `docs/content-import-guide.md` | Editor guide: pages, tours, destinations, journal, images, brand rules |
| `docs/upgrade-to-2.0.0.md` | What changed, migration steps, what to re-test after upgrading |
| `docs/release-checklist.md` | Steps to cut a release: gates, packaging, checksums, what to verify |
| `docs/launch-checklist.md` | Acceptance checklist with status and evidence |
| `docs/rollback.md` | How to undo this change at three levels |
| `CHANGELOG.md` | Version history for all four shipped components |
| `plugins/formistic/UPSTREAM.md` | Fork provenance and every local change |

## Brand rules

Enforced by `scripts/brand-lint.php`, and non-negotiable:

- Tagline: **Born Here. Guide Here.**
- Homepage H1: **Experience Laos Through the People Who Call It Home.**
- About lede: **Lao-led. Globally understood.**
- Hosts: **Not a guide. A host.**
- Custom trips: **We design your journey, your way.** Never ask for a budget.
- Standard: **Founder-set. Team-delivered.**
- Capacity, always per-tour and never per-company: **Each journey runs a fixed
  number of times each year. By design.**
- Reviews, until the verified threshold is met: **Consistently top-rated on
  Google and TripAdvisor.** No rating numbers, no awards, no `AggregateRating`.
- The founder's story is told through his work — licensed National Tour Guide
  since 2010, founded Brother Tours in 2018. No ethnicity, no monastic detail.
- American English.

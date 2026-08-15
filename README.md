# Brother Tours Laos — 3.0.0

Client-facing WordPress release for Brother Tours Laos: a structured travel
website, tour management system, inquiry forms, content editing tools, and the
optional operations API.

**Release:** 3.0.0
**Repository:** `https://github.com/Shubochandrosarker/brother-tours-laos`
**Brand:** Brother Tours Laos — *Born Here. Guide Here.*

This repository contains deployable source and documentation. It does not
contain production credentials, payment keys, SMTP passwords, API secrets, or
database exports.

## Components

| Component | Version | Purpose |
|---|---:|---|
| `themes/wpistic` | 3.0.0 | Reusable WPistic parent theme and templates |
| `themes/brother-tours` | 3.0.0 | Brother Tours design system and child-theme overrides |
| `plugins/formistic` | 3.0.0 | Forms, inbox, newsletter, spam protection, and replies |
| `plugins/wpistic-tour-manager` | 3.0.0 | Tours, destinations, bookings, departures, payments, and connections |
| `plugins/brother-tours-content-studio` | 3.0.0 | Structured editing, migration, SEO, blocks, and sitemap controls |
| `plugins/brother-tours-operations-api` | 3.0.0 | Authenticated REST control plane for the operations dashboard |

The Operations API is an optional integration component. It requires the
Tour Manager and Formistic plugins and should only be exposed behind HTTPS,
authenticated sessions, least-privilege accounts, and a trusted app origin.

## Repository layout

```text
themes/        WordPress parent and child themes
plugins/       Brother Tours production plugins
docs/          installation, operations, SEO, migration, and release guides
scripts/       lint, regression, and release-package checks
release/       generated 3.0.0 ZIPs and checksums
archive/       preserved pre-3.0.0 source snapshot and historical artifacts
```

The `archive/pre-3.0.0/` directory preserves the previous working tree,
legacy nested project files, scratch packages, and older release artifacts. It
is reference-only and must not be deployed to `wp-content`.

## Requirements

- WordPress 6.4 or newer
- PHP 8.1 or newer
- MySQL 5.7+ or MariaDB 10.3+
- HTTPS with a valid, auto-renewing certificate
- A verified database and `wp-content` backup before installation
- A transactional email provider with SPF, DKIM, and DMARC configured

Elementor is supported but the PHP templates remain the fallback. WooCommerce
is not required by the core tour workflow.

## Installation order

1. Take and verify a Hostinger database and `wp-content` backup.
2. Upload the two theme folders to `wp-content/themes/`.
3. Upload the plugins to `wp-content/plugins/`.
4. Activate **Formistic** first.
5. Activate **WPistic Tour Manager**.
6. Activate **Brother Tours Content Studio**.
7. Activate **Brother Tours Operations API** only when the operations app is
   ready and its origin/authentication settings are configured.
8. Activate **Brother Tours** and confirm the `wpistic` parent theme is present.
9. Save **Settings → Permalinks** once.
10. Configure email, WhatsApp, analytics, legal pages, forms, and any external
    connection settings in WordPress admin.
11. Run the staging checklist in `docs/release-3.0.0.md` before production.

Never activate two copies of Formistic. Keep payment gateways disabled until
the owner has verified provider credentials, webhook signatures, reconciliation,
capacity handling, and rollback behavior.

## Hostinger deployment mapping

This is a WordPress deployment, not a Node application bundle. The source maps
as follows:

```text
themes/wpistic                  → wp-content/themes/wpistic
themes/brother-tours            → wp-content/themes/brother-tours
plugins/formistic               → wp-content/plugins/formistic
plugins/wpistic-tour-manager    → wp-content/plugins/wpistic-tour-manager
plugins/brother-tours-content-studio
                                 → wp-content/plugins/brother-tours-content-studio
plugins/brother-tours-operations-api
                                 → wp-content/plugins/brother-tours-operations-api
```

Use Hostinger File Manager, SFTP, or the approved deployment workflow. Do not
upload the repository root, `.git`, `archive/`, `work/`, or development files
into `wp-content`.

## Configuration boundaries

The repository intentionally contains defaults and code, not environment
secrets. Configure these per environment:

- sender, reply-to, and notification email;
- SMTP/API provider and DNS authentication;
- WhatsApp display number and canonical click URL;
- analytics and consent-gated identifiers;
- review profile URLs, without unverified rating claims;
- Tourflows or other signed connection endpoints;
- payment gateway keys and webhook secrets;
- Operations API trusted origins and administrator access;
- legal/privacy/terms/cancellation copy;
- staging noindex and host-level access protection.

See `docs/configuration-matrix.md` for the handoff checklist.

## Release checks

From the repository root:

```sh
php scripts/test-release-fixes.php
php scripts/brand-lint.php --json
sh scripts/release-check.sh
sh scripts/build-release.sh
# Windows PowerShell alternative:
pwsh -File scripts/build-release.ps1
```

The package builder creates component ZIPs, a combined suite ZIP, and SHA-256
checksums. It packages tracked release source only. Review and commit all
intended source before building a client artifact.

## Production decision

Version 3.0.0 is a prepared client release, not automatic authorization to
change production. Production installation requires the signed-off migration
map, verified file/database backups, approved legal content, complete staging
crawl, real form/email tests, and payment/webhook review.

## Documentation index

- `docs/release-3.0.0.md` — release scope, acceptance gates, and handoff
- `docs/installation.md` — Hostinger/WordPress installation runbook
- `docs/configuration-matrix.md` — settings and ownership matrix
- `docs/content-migration.md` — content model and migration boundaries
- `docs/security.md` — authentication, capabilities, and secret handling
- `docs/seo.md` — canonical URLs, metadata, sitemaps, and staging rules
- `docs/rollback.md` — backup and recovery runbook
- `docs/operations-api.md` — app/API integration boundary
- `archive/pre-3.0.0/` — preserved historical source and artifacts

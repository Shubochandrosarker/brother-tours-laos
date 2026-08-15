# Content Studio implementation status

## Completed in source

- Standalone `plugins/brother-tours-content-studio` plugin.
- Twelve dynamic Gutenberg blocks with server-rendered output.
- Two reusable block patterns: Premium Homepage Starter and Tour Detail Starter.
- Structured Tour and Destination fields with REST exposure and sanitization.
- Gutenberg enabled for pages, posts, tours and destinations while preserving non-empty Elementor records.
- Reversible child-theme bridges for homepage, tour and destination block content.
- Granular capabilities: `bt_manage_bookings`, `bt_view_booking_pii`, `bt_manage_payments`, `bt_manage_operations`, `bt_view_health`, `bt_manage_content`, `bt_edit_templates`, `bt_view_seo`.
- Least-privilege role mapping for Brother Tours travel/CRM roles.
- Operations API route permissions updated to use the new capabilities.
- Public WordPress user REST enumeration restricted for visitors without `list_users`.
- Portable SEO fields and SEOISTIC enrichment filters.
- FAQ schema from visible FAQ blocks; review schema remains opt-in.
- Staging-aware noindex/robots behavior and valid XML sitemap fallback endpoints.
- WP-CLI dry-run/export/migration commands that avoid overwriting reviewed content.
- Staging lockdown, capability matrix and architecture documentation.

## Verification completed

- PHP lint: passed for Content Studio, Operations API and child theme bridge PHP files.
- Node syntax check: passed for the editor bundle.
- Git whitespace check: passed.
- WordPress runtime activation: passed on staging.
- Gutenberg editor availability: passed for pages, posts, tours and destinations.
- Content migration: 37 tours marked and USD currency copied into empty structured fields; 10 destinations discovered with no unsafe inferred field writes.
- Homepage conversion: page 77 converted to dynamic visual blocks with PHP fallback preserved.
- SEOISTIC metadata enrichment: applied to 37 tours and 10 destinations where fields were blank; SEOISTIC remains the primary SEO/schema owner.
- Runtime schema sampling: homepage, tour and destination pages expose expected JSON-LD types without duplicate primary schema in the sampled pages.
- Runtime security sampling: staging debug disabled, file editor disabled, noindex headers present, `/wp-json/wp/v2/users` no longer enumerates anonymously, Bridgistic/Operations API routes require authentication.
- Homepage internal link crawl: 29 internal links checked after publishing the existing About page and fixing its menu URL; zero failures.
- Public staging browser render: homepage rendered successfully; hero and CTA contrast were corrected and verified with Content Studio CSS `1.0.2`.
- Public live browser render: homepage rendered successfully; current live navigation still contains service-oriented sections and therefore does not yet match the tour-only target.

## Not claimed as complete

- Staging authentication/Cloudflare Access: `MISSING - infrastructure action required`.
- Live production deployment: not performed; the connector only targets staging and production remains untouched.
- `/sitemap_index.xml` on staging intentionally returns 404 while noindex staging protection is active; use WordPress core `/wp-sitemap.xml` for staging checks.
- SEOISTIC default share image option was not changed because the bridge option allowlist blocked that option.
- Lighthouse, Core Web Vitals, full browser/device matrix, forms, payment sandbox and webhook runtime: `MISSING - runtime test access required`.
- Search Console, analytics, backlink and redirect evidence: `MISSING - owner/export access required`.

## Release status

**STAGING IMPLEMENTED / PRODUCTION NO-GO.** Gate A staging work is installed and verified within the connector scope. Production deployment still requires explicit production approval, a production backup, infrastructure access control, and final browser/performance/UAT gates.

# 06 — Code and Plugin Audit

Overview
--------
This file records direct observations from a repository scan and the initial staging/prod crawl artifacts saved under `docs/seo/crawl-output/`.

Key Observations (direct evidence)
----------------------------------
- Theme: `wpistic` is present and referenced in staging assets (eg. `wp-content/themes/wpistic/style.css?ver=2.1.0`). Evidence: staging sitemap HTML and asset links. (E2)
- Child/parent relationship: repository includes a `brother-tours` theme and references `wpistic` as a parent/primary theme in HTML body classes. (E2)
- Tour plugin: `wpistic-tour-manager` appears installed; asset URLs reference `ver=2.1.0` and booking REST endpoints such as `/wp-json/wpistic/v1/booking`. (E2)
- SEO layer: staging sitemap contains `<!-- SEOISTIC -->` markers indicating the SEOISTIC plugin or integration is present on staging. (E2)
- Sitemap structure: WordPress-generated sitemaps include custom post-type sitemaps `wpistic_tour` and `wpistic_destination`, and taxonomies `travel_style`, `region`, etc., confirming CPTs for tours/destinations. (E2)
- Forms and booking: HTML contains `wpistic-booking-form` and newsletter forms (`wpistic-newsletter`) indicating integrated booking/newsletter flows. (E2)

Files and manifests discovered
----------------------------
- `docs/brother-tours-seo-program.json` (project program and evidence summary)
- `docs/brother-tours-codex-master-prompt.md` (execution prompt)
- Saved crawl outputs: `docs/seo/crawl-output/*` (robots, sitemap indexes, child sitemaps)

Immediate Risks & Notes
-----------------------
- Plugin/theme version strings observed in asset URLs (eg. `?ver=2.1.0`) are useful but do not guarantee package versions in source; confirm via `composer.json` or plugin headers where available. (E1)
- The staging sitemap contains an apparent 404 page title in one saved file — verify staging protection and correct sitemap generation to avoid exposing 404s. (E2)
- There is evidence of SEOISTIC markers; detect duplicate metadata output between theme and plugin layers. (E2)

Next recommended Phase 2 actions
-------------------------------
1. Inspect WordPress plugin headers under `wp-content/plugins` (on staging or source) to record plugin slugs, versions, and whether SEO plugins are active.
2. Confirm theme parent/child relationship and review `functions.php` for metadata output (canonicals, breadcrumbs, schema emitters).
3. Review REST endpoints referenced (`/wp-json/wpistic/*`) for permission callbacks and nonce usage.
4. Run static scans for insecure patterns (unescaped outputs, direct SQL queries, open permission callbacks) limited to code under `wp-content/themes/wpistic` and `wp-content/plugins/wpistic-*`.
5. Produce a prioritized list of code tickets with file references and acceptance tests.

Evidence files: see `docs/seo/crawl-output/` for the raw sitemap and homepage captures used to derive these observations.


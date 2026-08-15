# Brother Tours Gate A submission

## Executive finding

Gate A was approved with the exact phrase `APPROVE GATE A: MIGRATION MAP AND SEO SPEC`. The repository and staging site now contain the Content Studio implementation, runtime hardening, SEOISTIC enrichment, and reversible template bridge work.

## High-risk blockers

1. Staging is still publicly reachable and is not protected by HTTP authentication or Cloudflare Access.
2. Production is untouched because no production connector/approval was available in this Gate A scope.
3. The current checkout contains pre-existing uncommitted release changes that must be reviewed and committed separately from Content Studio.
4. Full browser/device, Lighthouse, forms, payment sandbox, webhook and Search Console evidence is still missing.
5. Search Console, analytics, backlink and redirect evidence is required before deleting or redirecting legacy service pages.

## Approved migration decisions

- Preserve existing tour and destination URLs.
- Use Gutenberg blocks and structured fields as the new commercial editing surface.
- Keep Elementor custom widgets available for legacy content only.
- Keep the existing PHP templates as reversible fallbacks during migration.
- Keep SEOISTIC as the primary metadata/schema/sitemap owner when active.
- Do not delete service pages or bulk-redirect them without Search Console, analytics, backlink, semantic-equivalence and owner evidence.

## Gate A staging evidence

- WordPress 7.0.4 / PHP 8.3.31 baseline verified through Bridgistic.
- Content Studio activated on staging with 12 Gutenberg blocks and two starter patterns.
- Gutenberg confirmed enabled for pages, posts, tours and destinations.
- Homepage page 77 converted to dynamic visual blocks with legacy PHP fallback preserved.
- 37 tours discovered and marked; USD currency copied only into empty structured fields.
- 10 destinations discovered; no inferred destination metadata was force-written.
- SEOISTIC metadata fields filled for 37 tours and 10 destinations where blank.
- Staging `WP_ENVIRONMENT_TYPE` is `staging`, `WP_DEBUG` and `WP_DEBUG_LOG` are false, and `DISALLOW_FILE_EDIT` is true.
- Staging sends `X-Robots-Tag: noindex, nofollow, noarchive`.
- Anonymous `/wp-json/wp/v2/users` enumeration returns 404 after Content Studio security hardening.
- Bridgistic and Operations API sample routes require authentication.
- Homepage internal link crawl checked 29 internal links with zero failures after fixing the About menu URL.
- Browser UI pass verified tour-only navigation and corrected hero/CTA contrast with Content Studio CSS `1.0.2`.

## Files changed by this source batch

- `plugins/brother-tours-content-studio/`
- `plugins/brother-tours-operations-api/src/`
- `themes/brother-tours/front-page.php`
- `themes/brother-tours/single-wpistic_tour.php`
- `themes/brother-tours/single-wpistic_destination.php`
- `docs/content-studio-architecture.md`
- `docs/deployment/staging-lockdown.md`
- `docs/security/api-capability-matrix.md`
- `docs/release/content-studio-*.md`

Pre-existing dirty files outside this list were preserved.

## Rollback

Remove the new plugin and child-theme bridge files, revert only the listed Operations API capability edits, and restore the pre-change files/database from the verified backup. Do not use a broad reset because the checkout already contained unrelated user work.

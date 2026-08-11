# 16 - Production Launch Runbook

Status: **DRAFT - REQUIRES EXACT `APPROVE GATE B: PRODUCTION LAUNCH`**

## Preconditions

1. Named final approver, launch window, deployment owner and rollback owner recorded.
2. Gate A migration/spec approved; Gate B staging matrix has no unresolved critical/high blocker.
3. Exact Git commit and reviewed release archives/checksums recorded. Archive audit confirms no repo metadata, tests, internal notes, source maps, secrets or unrelated files.
4. Fresh database plus `wp-content` backup completed and restore-tested in an isolated environment.
5. Current redirects, SEO/plugin settings, menus/widgets, cron, cache and environment configuration exported.
6. Production baseline captured for homepage, hubs, sample tour/destination, forms, booking, analytics, robots, sitemaps, canonicals, schema and error logs.

## Approved deployment sequence

1. Enable the agreed deployment window; keep staging authentication enabled.
2. Deploy only checksum-matched reviewed archives. Do not copy the whole working directory.
3. Run additive database/plugin upgrades; record output. Stop on fatal, failed migration or version mismatch.
4. Apply the approved one-hop redirect map in its authoritative layer.
5. Apply approved SEOISTIC configuration and sitemap eligibility rules.
6. Flush permalinks once; purge only the relevant WordPress/CDN caches.
7. Run smoke tests: canonical host/protocol, home, hubs, sample singles, legal, 404, redirects, robots, sitemap, schema, internal links, forms and booking/payment sandbox paths.
8. Verify analytics/consent events without real customer data or charges.
9. Check PHP/server/browser logs for new errors and compare key response/render baselines.
10. Submit/refresh Search Console and Bing sitemaps only with explicit authenticated approval.
11. Record UTC timestamp, release ID, operator, evidence links and rollback status.

## Immediate rollback triggers

Sitewide noindex/robots block, staging canonical on production, widespread 5xx, redirect loop/chain outbreak, broken forms/bookings/payments, missing consent/analytics, severe layout failure, or unreconciled payment state.

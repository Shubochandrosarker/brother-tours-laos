# 14 - Implementation Tickets

Status: **DRAFT - NO IMPLEMENTATION BEFORE GATE A**

| ID | Priority | Proposed files/layer | Acceptance | Dependency |
|---|---|---|---|---|
| BTL-P3-001 | P0 | `docs/seo/09-url-migration-map.csv` | Every discovered URL has evidence, approved action and tested target; unavailable metrics are `MISSING` | GSC, GA4, backlinks, logs, owner decisions |
| BTL-P3-002 | P0 | Tour CPT registration plus `archive-tour.php`, `template-tags.php`, `page-travel-from.php` | Archive stays `/tours/`; all singles use `/tour/{slug}/`; approved legacy redirects are one hop | Gate A URL registry |
| BTL-P3-003 | P0 | Route registry, footer, SiteSeeder and ContentSeeder | Navigation, seeder, canonical, schema and sitemap agree on destination/legal slugs | Approved slugs |
| BTL-P3-004 | P0 | Hosting/CDN and SEOISTIC/WordPress config | Staging requires authentication, has explicit noindex fallback and exposes no public user sitemap | Hosting/admin access |
| BTL-P3-005 | P0 | `Integration/SchemaData.php` plus SEOISTIC emitter | No empty Offer or guessed availability; rendered/schema parity passes | Verified tour facts and SEOISTIC source |
| BTL-P3-006 | P0 | Operations API auth/capability layer and tests | Contributor 403 on sensitive routes; approved operations roles pass | WordPress test harness |
| BTL-P3-007 | P0 | Payment tables/webhook services and tests | Atomic exact reconciliation; concurrent/crash retries reconcile once | DB migration and sandbox gateways |
| BTL-P3-008 | P0 | `Admin/ContentSeeder.php` | Dry run, backup, ownership markers, fill-only default, audit log and rollback; reruns preserve manual edits | Approved content facts |
| BTL-P3-009 | P1 | Booking capture service and tests | Durable rate/idempotency controls; double-click/replay creates one booking/notification | Integration environment |
| BTL-P3-010 | P1 | SEOISTIC taxonomy/sitemap config | Only approved hubs index; parameters/users/utility taxonomies excluded | SEOISTIC source and IA approval |
| BTL-P3-011 | P1 | Redirect importer/authoritative layer | Approved CSV only; targets direct 200; no loops/chains/homepage dumping | Completed migration map |
| BTL-P3-012 | P1 | Templates/content | Verified priority pages replace boilerplate and unsupported claims | Product/legal/business facts |
| BTL-P3-013 | P2 | Metadata and `llms.txt` generator | Unique metadata; optional list contains direct 200 approved canonical URLs only | Approved canonical inventory |
| BTL-P3-014 | P0 | Release/build scripts | Package Operations API and SEOISTIC if required; clean roots, checksums and exclusion audit | Actual Git checkout and component decision |

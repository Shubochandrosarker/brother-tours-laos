# Production readiness matrix

Assessment date: 2026-08-15

Release status: **NO-GO**

The staging content and navigation cleanup was performed through the guarded
Bridgistic connector. Production was not mutated. This matrix is a release
decision record, not authorization to deploy.

| Gate | Status | Evidence / required action |
|---|---|---|
| Tracked release source matches staging baseline | BLOCKED | Git `HEAD` remains suite 2.0.0 at `e0cefe5`; staging runs Brother Tours 2.5.0, Formistic 2.5.0 and Tour Manager 2.5.0. The working tree also contains uncommitted theme/plugin changes and the Content Studio source is not yet tracked. Reconcile and review the source before packaging. |
| Restorable production rollback boundary | PASS | Bridgistic snapshot `snap_aeccc6224eccddd6f7f2` captured the production content/options tables before any production mutation. |
| Restorable staging database boundary | PASS | Bridgistic staging snapshots were captured before the approved page, menu, content, media and status changes; the fresh pre-change boundary was `snap_a2e7e0df399835eea568`. |
| Restorable staging `wp-content` backup | BLOCKED | No verified full `wp-content` backup identifier is available through the connector. Obtain and verify the Hostinger file backup before installing files. |
| Staging index protection | PARTIAL | Anonymous staging access is expected for QA. `robots.txt` returns `Disallow: /`, HTML emits `noindex,nofollow,noarchive`, and `wp-sitemap.xml` is reachable. Add authentication or an equivalent host-level gate before public exposure. |
| Staging route/content QA | PARTIAL PASS | Homepage is page 77 and returns 200. Active menus resolve to real routes. All 37 published tour URLs and 10 published destination URLs return 200; all have short summaries. Legacy source links `/laos-travel-guide/` and `/privacy-policy/` still produce 404s until the patched theme source is deployed. |
| Images and media reconciliation | PARTIAL | Destination thumbnails were mapped only where the asset was semantically trustworthy. Unmatched records were left without an invented image; 29 published tours and 3 destinations still lack a trustworthy thumbnail. |
| Legal pages | BLOCKED | The existing Privacy Policy was WordPress placeholder text and was removed from the active footer menu. Approved legal copy must be supplied and published before production launch. |
| Outbound email | SOURCE FIXED / RUNTIME UNVERIFIED | Form and contact surfaces render with `enquiry@brothertours.com` and the configured WhatsApp number. A real delivery test and provider/DNS verification remain open. |
| Payments and webhooks | BLOCKED | No production payment or webhook migration was attempted. Keep gateways disabled until the exact provider, reconciliation, capacity and rollback tests pass. |
| Operations API | EXCLUDED | The separate Operations API remains outside the theme/content release and was not changed on production. |
| Local source gates | PASS | PHP lint, focused regression checks, brand lint, secret scan, `.env` scan and unrelated-client scan pass. Bash/WSL was unavailable, so `scripts/release-check.sh` was manually reproduced rather than executed. |
| Installable release artifacts | BLOCKED | The release builder packages tracked `HEAD` only. Required files are currently modified or untracked, so no installable production artifact was produced or pushed. |
| Production migration | NOT RUN | Production still runs Travel Tour Child 1.0.0 with Tour Master and has not received the new theme, Content Studio, Tour Manager or migration writes. |

No production deployment is authorized by this document.

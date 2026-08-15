# Rollback runbook

Rollback is a release operation, not a guess.

1. Stop new deployment writes and record the incident time.
2. Preserve logs and the current release ZIP/checksum.
3. Restore the verified database backup only when the approved rollback owner
   authorizes it.
4. Restore the matching `wp-content` file backup or reactivate the previous
   theme/plugin set.
5. Save permalinks and clear page/object/CDN caches.
6. Verify homepage, forms, tours, destinations, admin login, and critical API
   health endpoints.
7. Crawl the site and record the result before reopening traffic.

Never delete the previous release before the new release has passed its
rollback window. Keep the old source in `archive/pre-3.0.0/` for provenance,
but use an operational Hostinger backup for restoring production files.

# 17 - Rollback Runbook

## Ownership

- Rollback decision owner: **MISSING - OWNER INPUT REQUIRED**
- Technical operator: WordPressistic - Shuvo
- Hosting/CDN operator: **MISSING**

## Order of operations

1. Declare rollback and record UTC time, trigger and affected paths. Pause further writes/imports.
2. Disable only the newly applied redirect/config batch using its release identifier; restore the exported prior redirect and SEO settings.
3. Restore the previous checksum-verified plugin/theme archives. Do not delete uploads or unrelated plugins.
4. If an additive migration caused the incident, execute its reviewed down/restore procedure. Otherwise restore the tested database backup; never improvise destructive SQL.
5. Restore prior menus/widgets/options/config exports where required.
6. Flush permalinks once and purge only affected caches.
7. Re-run availability, canonical/robots/sitemap, forms, booking/payment and log smoke checks.
8. Confirm staging remains protected and production contains no staging URL/canonical.
9. Record recovery evidence, data-loss assessment, unresolved payment/webhook events and follow-up owner.

## Component rollback

- Redirects: remove the approved batch by stable ID; restore previous rules; verify old and target URLs.
- Seeder/config: restore pre-run export; never rerun with force during incident response.
- Code: redeploy the previous signed/checksummed artifacts.
- Database: restore only from the verified backup when additive rollback is insufficient.

Rollback is complete only when revenue paths work, critical SEO signals match the prelaunch baseline, queues/webhooks are reconciled and monitoring is active.

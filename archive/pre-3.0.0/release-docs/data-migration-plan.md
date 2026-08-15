# Data migration plan

Status: **BLOCKED — no data mutation authorized**

The current URL migration inventory contains 109 `NEEDS_DECISION` records. No redirect, canonical, legal slug, destination slug, content deletion, bulk update, seeder, or SEO configuration change may be applied until the owner supplies the exact gate phrase required by the master brief:

`APPROVE VERIFIED FACTS AND MIGRATION MAP`

Until approval:

- preserve the staging and production databases;
- do not run `ContentSeeder` (it is quarantined from the plugin bootstrap);
- do not run bulk redirects or rewrite legal/destination URLs;
- do not publish placeholder tours, prices, availability, reviews, staff facts, licences, NAP, or operating claims;
- export any later approved changes as a dry-run report first;
- record row counts and before/after identifiers; and
- require a verified database restore point before applying the approved plan.

Rollback for the current source-only changes is a code rollback. No migration has been executed.

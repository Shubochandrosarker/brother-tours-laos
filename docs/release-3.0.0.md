# Release 3.0.0

## Scope

3.0.0 is the clean, client-shareable Brother Tours release containing the
parent theme, child theme, forms, tour manager, Content Studio, and optional
Operations API. The source is version-aligned at 3.0.0 and the previous
working tree is preserved under `archive/pre-3.0.0/`.

## Acceptance gates

Before production installation, the owner must confirm:

- a restorable Hostinger database backup and full `wp-content` backup;
- the staging site uses this exact 3.0.0 source;
- staging is protected from indexing and, preferably, access-controlled;
- homepage, header, footer, CTA, contact, WhatsApp, forms, tours, and
  destinations pass an anonymous crawl;
- email delivery is verified without exposing mailbox credentials;
- legal/privacy/terms/cancellation content is approved;
- payments and webhooks are disabled until their provider-specific tests pass;
- the old-to-new URL redirect map is reviewed;
- the release ZIP checksums are recorded; and
- rollback has been rehearsed or independently verified.

## Build

```sh
php scripts/test-release-fixes.php
php scripts/brand-lint.php --json
sh scripts/release-check.sh
sh scripts/build-release.sh
# Windows PowerShell alternative:
pwsh -File scripts/build-release.ps1
```

Build output is written to `release/`. The generated `release/` directory is a
delivery artifact and should be reviewed before sharing with the client.

## Version policy

The six shipped components use the coordinated 3.0.0 release number. Database
schema versions remain independent and are changed only when a migration is
actually introduced. A version bump does not authorize a database migration.

## Release contents

- `wpistic-3.0.0.zip`
- `brother-tours-3.0.0.zip`
- `formistic-3.0.0.zip`
- `wpistic-tour-manager-3.0.0.zip`
- `brother-tours-content-studio-3.0.0.zip`
- `brother-tours-operations-api-3.0.0.zip`
- `brother-tours-suite-3.0.0.zip`
- `checksums.sha256`

The Operations API package is optional and should only be activated when the
operations app has a secured origin and tested authentication flow.

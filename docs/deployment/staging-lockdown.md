# Brother Tours staging lockdown runbook

This is an infrastructure gate, not a WordPress-only setting. `Disallow: /` does not protect a public staging site.

## Required controls

1. Put `staging.brothertours.com` behind Cloudflare Access, Basic Authentication or an IP allowlist.
2. Confirm an unauthenticated request returns `401` or `403`, not a rendered WordPress page.
3. Keep staging `noindex,nofollow,noarchive` after authentication is configured.
4. Remove staging from every production sitemap and Search Console property.
5. Do not reuse production cookies, API keys, payment secrets or webhook destinations on staging.

## WordPress configuration

Use environment-specific configuration, never a committed secret:

```php
define( 'WP_ENVIRONMENT_TYPE', 'staging' );
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISALLOW_FILE_EDIT', true );
```

Archive the existing debug log only after a verified file/database backup and an owner-approved retention decision. Do not delete the live or staging log blindly.

## Verification

- Unauthenticated homepage: `401`/`403`.
- Authenticated homepage: `200`.
- Authenticated REST and admin flows: functional.
- Unauthenticated REST user enumeration: blocked or intentionally redacted.
- Staging sitemap: not indexable and not advertised publicly.

# Brother Tours Content Studio architecture

## Ownership

`plugins/brother-tours-content-studio` owns reusable content blocks, structured editing fields, capabilities and portable fallback integrations. It does not own payment, booking persistence, email delivery or SEOISTIC's primary metadata output.

The child theme remains responsible for visual tokens and the reversible template bridge. The parent theme remains the fallback renderer until each content type has been migrated and accepted on staging.

## Editing model

Gutenberg is enabled for pages, posts, tours and destinations at a high filter priority. Existing Elementor pages and custom widgets remain available for legacy content. New commercial content should use Content Studio blocks and patterns.

Global brand settings are limited to verified identity, contact, logo and CTA values. Editors receive content and SEO capabilities; booking PII, payment actions, operations actions and health data are administrator/operations capabilities.

## Migration rule

Migration is intentionally opt-in. Empty block content never replaces the existing PHP template. The rollout sequence is:

1. Back up files and database.
2. Create the block content for one representative tour, one destination and the homepage.
3. Test the visual bridges and compare the rendered pages with the current templates.
4. Migrate the remaining 37 tours and 10 destinations through an idempotent, reviewed mapping.
5. Enable visual rendering only after staging UAT and rollback verification.

## Schema and SEO ownership

SEOISTIC remains the primary owner when detected. Content Studio enriches SEOISTIC tour/destination data with verified structured fields. FAQ schema is emitted only from visible FAQ blocks. Review schema is opt-in and does not generate aggregate ratings.

The sitemap implementation is a fallback for installations where the authoritative SEO layer does not return valid XML. Do not run both sitemap owners without checking the final response and duplicate output.


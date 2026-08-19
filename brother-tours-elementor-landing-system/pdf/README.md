# The ten PDF guides

Not written. This directory is the place they go.

Each landing page has a resource section wired to a guide by id. That section
renders nothing to visitors until a PDF is attached, so the pages are safe to
publish now and improve when the guides land.

| Guide | Resource id | Landing page |
|---|---|---|
| Laos Adventure Travel Planner | `adventure-planner` | `/adventure-tours/` |
| Central Laos Travel Guide | `central-laos-guide` | `/central-laos/` |
| The Founder-Hosted Laos Experience | `founder-hosted-guide` | `/founder-hosted-signature-journeys/` |
| Laos Honeymoon Planning Guide | `honeymoon-guide` | `/honeymoon-packages/` |
| Laos + Indochina Journey Planner | `indochina-planner` | `/indochina-tours/` |
| Brother Tours Signature Laos Guide | `signature-guide` | `/laos-signature-tours/` |
| Lao-China Railway E-Ticket Guide | `lcr-guide` | `/lcr-e-ticket-guide/` |
| Private Luxury Travel in Laos | `luxury-guide` | `/luxury-laos-tours/` |
| Laos Student Group Learning Planner | `student-group-planner` | `/student-group-learning/` |
| When to Visit Laos — Planning Calendar | `journey-calendar` | `/upcoming-tours/` |

## Why they are not here

They are content, not code. Writing them well needs the same business facts the
landing pages are still waiting on — pricing policy, group capacities, what
Brother Tours operates directly versus coordinates, and, for the railway guide,
the current 2026 rules. Generating ten PDFs from unverified assumptions would
produce ten documents that have to be withdrawn.

A guide must also be worth downloading on its own. The brief is explicit that a
PDF must not be a printout of the page it sits on.

## Attaching one

Upload the PDF to the media library, then register it:

```php
add_filter( 'btrd_resources', function ( array $resources ): array {
    $resources['lcr-guide']['pdf_url']      = 'https://www.brothertours.com/wp-content/uploads/…/guide.pdf';
    $resources['lcr-guide']['pdf_filename'] = 'brother-tours-lao-china-railway-e-ticket-guide-2026.pdf';
    $resources['lcr-guide']['cover_image']  = 'https://www.brothertours.com/wp-content/uploads/…/cover.jpg';
    $resources['lcr-guide']['updated_date'] = 'August 2026';
    return $resources;
} );
```

The landing page section and the download popup both light up on their own. See
`plugins/brother-tours-resource-downloads/README.md`.

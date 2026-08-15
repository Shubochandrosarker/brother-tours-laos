# 08 - Information Architecture

Status: **PROPOSED - GATE A APPROVAL REQUIRED**

## Canonical hierarchy

- `/` - Home
- `/tours/` - primary tour discovery archive
  - `/tour/{approved-tour-slug}/` - canonical tour detail
- `/destinations/` - destination hub
  - `/destinations/{approved-destination-slug}/` - canonical destination detail
  - `/destinations/{destination-slug}/{experience-slug}/` - only for unique, verified standalone experiences
- `/travel-style/{approved-style-slug}/` - optional intent hub after content/value approval
- `/journal/` - practical Laos planning hub
- `/visa-guide/` and `/when-to-visit/` - planning resources only after official-source review
- `/about/`, `/contact/`, `/build-my-trip/`, `/agents/`, `/faq/`
- `/privacy/`, `/terms/`, `/cancellation/`
- `/thank-you/` - noindex conversion utility

## Ownership rules

1. Destination CPT pages own place intent. `tour_destination`, `region` and `country` taxonomies must not compete with them.
2. Travel-style terms become indexable only with validated intent, useful unique content, sufficient eligible tours and approved metadata.
3. `tour_category`, `tour_duration_range`, `tour_difficulty` and `tour_season` remain noindex filtering/classification utilities by default.
4. Experiences require a verified parent destination; wrong-parent paths must not resolve.
5. No US/UK/EU doorway trees. Shared English pages handle international terminology.
6. Standalone visa, accommodation, ticketing, rental, driver and guide sales are outside scope unless the owner explicitly approves them.

## Internal linking

- Home links to Tours, Destinations, priority planning content, About, Contact and Build My Trip.
- Every tour links to relevant destinations, up to three genuinely related tours, planning resources, breadcrumbs and Build My Trip.
- Every destination links to eligible tours, verified experiences, planning content and breadcrumbs.
- Every approved indexable URL has at least one crawlable inlink and is reachable within three clicks from Home.

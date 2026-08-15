# Content migration boundary

The new system uses structured `wpistic_tour`, `wpistic_destination`, and
`wpistic_experience` content. Legacy Tour Master `tour` records and Elementor
page data must not be copied by title or by `post_content` alone.

## Required mapping

For every migrated record, preserve an approved map of:

- legacy ID and slug;
- new ID and slug;
- title and canonical URL;
- status and publication decision;
- short summary, body, duration, region, category, and itinerary;
- featured image and gallery assignments;
- SEO title, description, canonical, and redirect target.

Incomplete records should remain drafts. Generic template fallback copy is not
approved business content. If an image is not semantically matched, leave the
thumbnail empty and record it in the media backlog rather than assigning a
generic asset.

Run the Content Studio migration or seeder only after the map, backups, legal
content, and rollback procedure are approved.

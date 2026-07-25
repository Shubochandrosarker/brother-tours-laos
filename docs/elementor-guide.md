# Elementor guide

The site works fully without Elementor — every route has a complete PHP
template. This document covers what changes when Elementor is active:
theme locations, CPT editing support, and the 18 Brother Tours widgets.

## Theme locations (header, footer, single, archive, 404)

Registered in `themes/wpistic/inc/elementor.php` via
`elementor/theme/register_locations` (`register_all_core_location()`).
Each relevant template then checks `elementor_theme_do_location()` before
falling back to its own PHP:

```php
if ( ! function_exists( 'elementor_theme_do_location' ) || ! elementor_theme_do_location( 'header' ) ) {
    // existing PHP header markup
}
```

Templates wired this way: `header.php`, `footer.php`, `404.php`,
`archive-tour.php`, `archive-wpistic_destination.php`, `single-tour.php`,
`single-destination.php`, `single-experience.php`. Only the location
wrapper is conditional — `the_content()` calls and business logic are
unchanged.

**Elementor Free**: theme locations still work for a header/footer
assigned globally (Elementor's own "Site Settings" / template assignment).
**Elementor Pro**: Theme Builder adds conditional assignment (e.g. a
different single template for tours vs. destinations vs. the blog) — set
this up under Templates → Theme Builder, targeting the location names
above.

## CPT editing support

`elementor_cpt_support` is extended, never overwritten
(`array_unique( array_merge( $existing, [...] ) )`), so any post type an
operator already enabled from Elementor → Settings survives:

- `wpistic_tour`
- `wpistic_destination`
- `wpistic_experience`

`page` and `post` are Elementor's own defaults and are untouched by this
filter.

## The Brother Tours widget category

Registered as `brother-tours` (`elementor/elements/categories_registered`).
All 18 widgets below appear under it in the Elementor panel.

## Architecture: where the code lives

- `themes/wpistic/inc/elementor/class-widget-base.php` — the shared,
  generic `Wpistic_Elementor_Widget_Base` (empty-state rendering, the
  "current post or manual picker" pattern, shared spacing controls). No
  Brother Tours-specific copy; reusable by a future wpistic-based site.
- `themes/brother-tours/inc/elementor/bootstrap.php` — registers the
  shared widget stylesheet and all 18 widget classes, each guarded by
  `is_readable()`/`class_exists()` so a missing file or Elementor being
  inactive never fatals.
- `themes/brother-tours/inc/elementor/class-tour-widgets.php`,
  `class-destination-widgets.php`, `class-form-widgets.php`,
  `class-misc-widgets.php` — the 18 concrete widgets.
- `themes/brother-tours/assets/css/elementor-widgets.css` — registered,
  not enqueued unconditionally; it only loads on a page that actually
  places one of these widgets (`get_style_depends()`).

Every color in that stylesheet is a `var(--...)` token from
`brand-tokens.css`, so every widget already supports light/dark mode with
no extra work — see `docs/light-dark-mode.md`.

## The 18 widgets

| Widget | Data source | Notes |
|---|---|---|
| Tour Hero | Current tour or manual picker; `wpistic_from_price`, `wpistic_duration`, featured image, `region` taxonomy | |
| Tour Grid | `wpistic_tour` query, optional taxonomy filter | Reuses `wpistic_tour_card()` |
| Tour Search and Filters | `tour_category`/`tour_destination`/`tour_difficulty`/`tour_duration_range`/`region`/`travel_style`/`tour_season` taxonomies | Submits GET params `brother_tours_filter_tours()` already consumes |
| Tour Facts | `wpistic_duration`/`start`/`end`/`style`/`group_size`/`season` meta | Only shows facts that have a value |
| Tour Pricing | `wpistic_from_price`, `wpistic_departures_label` | Embeds `[wpistic_booking_widget]` |
| Tour Itinerary | `wpistic_itinerary` meta (`{title, body}` pairs) | |
| Included and Excluded | `wpistic_inclusions`/`wpistic_exclusions` meta (arrays of strings) | **No existing editor UI for these two keys yet** — shows an editor-only notice until an admin metabox is added. Does not fabricate content. |
| Tour Gallery | `wpistic_gallery` meta (attachment ids) | Columns control wired via a `--bt-ew-gal-cols` CSS custom property |
| Tour FAQ | `wpistic_faq` meta (`{q, a}` pairs) | Already editable via the Tour edit screen's FAQ repeater (`Admin\MetaBoxes`) |
| Related Tours | Same `tour_category` term, falls back to any other tour | |
| Destination Hero | Current destination or manual picker; `wpistic_region_tag`, featured image | |
| Destination Experiences | `wpistic_experience` posts where `wpistic_parent_destination` matches | |
| Request Availability | Formistic's `request-availability` form, tour context injected | Calls `Wpistic_Formistic_BT_Forms::render_request_availability()` |
| Formistic Form | Any published `formistic_form` post, picked in the widget settings | Generic — not Brother Tours-specific |
| Build My Trip CTA | — | Wraps the existing locked-copy `wpistic_build_my_trip_cta()` helper |
| Brother Tours Reviews | `wpistic_google_reviews_url`/`wpistic_tripadvisor_url`/`wpistic_reviews_embed` theme_mods | Text-only — never a rating number or `AggregateRating` |
| Newsletter Form | Formistic's `newsletter` form | |
| Contact Information | `wpistic_contact()` helper (email/phone/WhatsApp/office/hours) | |

Every widget fails safely — an editor-only notice or a plain empty state,
never a fatal — when its data source is empty or a dependency (Formistic)
is inactive.

## Placing the widgets: building real page templates

This repository does not ship a pre-built Elementor template kit. A
hand-authored JSON export would be untested and likely broken — the only
way to produce a genuinely working template is to build it in the live
Elementor editor. To assemble the homepage, a tour template, or a
destination template:

1. Create or edit the page/CPT in Elementor.
2. Drag in the relevant widgets from the "Brother Tours" category above,
   in the order the reference design specifies (see the master build
   brief's page-by-page section order).
3. For CPT-wide templates (every tour, every destination), use Elementor
   Pro's Theme Builder with a "Single" location condition targeting
   `wpistic_tour` / `wpistic_destination`, or leave the PHP fallback in
   place on Elementor Free.
4. Save, preview, and verify against `docs/launch-checklist.md`'s
   Elementor rows before publishing.

## Testing without a live install

Everything above was verified by static analysis: every widget's meta-key
reads were checked against the templates that already read them in
production (`single-tour.php`, `single-destination.php`) rather than
assumed; every class name matches `bootstrap.php`'s registration list;
`php -l` is clean across every file. Elementor editor rendering,
drag-and-drop control behavior, and actual on-screen output cannot be
verified without a live WordPress + Elementor install — that verification
is a `docs/launch-checklist.md` item, not something this document can
claim.

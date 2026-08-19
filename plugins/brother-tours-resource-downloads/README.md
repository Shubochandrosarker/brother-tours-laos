# Brother Tours Resource Downloads

One reusable system for downloadable travel guides: a single popup controller, an
inline CTA shortcode, and a resource registry shared by every landing page.

Built so that adding the eleventh guide is a filter entry, not another codebase.

---

## Status

**The system is complete. The PDFs are not.**

Every resource ships with an empty `pdf_url`, and a resource with no file behind
it renders nothing and opens nothing — deliberately. A download button pointing
at a missing file is worse than no button. Attach the PDFs (§ Configuration) and
each landing page lights up on its own.

The ten Elementor landing templates now exist, in
`brother-tours-elementor-landing-system/`, and each one calls this plugin's
shortcode. Still outstanding: the PDF documents themselves and the legacy
content migration. See § What this does not cover.

---

## What it does

| Piece | File |
|---|---|
| Resource registry — normalised metadata for every guide | `includes/Registry.php` |
| Asset loading + popup markup | `includes/Assets.php` |
| Inline CTA `[bt_resource_download]` | `includes/Shortcode.php` |
| Popup controller | `assets/js/bt-resource.js` |
| Styling, on the theme's tokens | `assets/css/bt-resource.css` |
| Browser behaviour tests | `tests/` |

Assets load **only** on pages with a ready resource. The rest of the site pays
nothing. Nothing external is enqueued — no icon font, no popup framework, no
analytics snippet.

---

## Two deliberate changes from the legacy implementation

### 1. It does not interrupt after 2 seconds

The old popup opened after 2 seconds and again near the bottom of the page. Two
seconds is before anyone has read a sentence, so it reads as an ad rather than
an offer.

This one waits for evidence of interest:

| Trigger | Behaviour |
|---|---|
| Timed | 10s **and** engagement — any scroll, pointer, key or touch. A tab opened and abandoned never triggers it. |
| Scroll | 40% page depth, whichever comes first |
| Exit intent | Desktop pointers only. Meaningless on touch, where moving toward the top is ordinary scrolling. |
| Manual | Any `[data-btrd-open]` button. **Always works**, whatever the suppression state. |

All four are tunable via the `btrd_trigger_config` filter.

### 2. It never calls `alert()`

Download and print feedback goes through a non-blocking toast that does not
steal focus or block the page. There is no `alert()` anywhere in the plugin.

---

## Configuration

`pdf_url`, `cover_image` and `updated_date` are intentionally empty in
`Registry.php` so no staging or developer URL can ever be committed. Supply them
per environment:

```php
add_filter( 'btrd_resources', function ( array $resources ): array {
    $resources['lcr-guide']['pdf_url']      = 'https://www.brothertours.com/wp-content/uploads/2026/08/brother-tours-lao-china-railway-e-ticket-guide-2026.pdf';
    $resources['lcr-guide']['pdf_filename'] = 'brother-tours-lao-china-railway-e-ticket-guide-2026.pdf';
    $resources['lcr-guide']['cover_image']  = 'https://www.brothertours.com/wp-content/uploads/2026/08/lcr-guide-cover.jpg';
    $resources['lcr-guide']['updated_date'] = 'August 2026';
    return $resources;
} );
```

Trigger tuning:

```php
add_filter( 'btrd_trigger_config', fn( array $c ): array => $c + [ 'delay_ms' => 12000 ] );
```

### Placement

A resource binds to its landing page through `canonical_page`; the page does not
declare it twice. The popup appears automatically. Add the inline CTA roughly
40–65% into the editorial content:

```
[bt_resource_download id="lcr-guide"]
[bt_resource_download id="honeymoon-guide" style="compact"]
```

An unknown id or a resource with no PDF shows a diagnostic to editors
(`edit_posts`) and nothing at all to visitors.

---

## Registered resources

| id | Guide | Landing page | Secondary |
|---|---|---|---|
| `adventure-planner` | Laos Adventure Travel Planner | `/adventure-tours/` | View |
| `central-laos-guide` | Central Laos Travel Guide | `/central-laos/` | View |
| `founder-hosted-guide` | The Founder-Hosted Laos Experience | `/founder-hosted-signature-journeys/` | View |
| `honeymoon-guide` | Laos Honeymoon Planning Guide | `/honeymoon-packages/` | View |
| `indochina-planner` | Laos + Indochina Journey Planner | `/indochina-tours/` | View |
| `signature-guide` | Brother Tours Signature Laos Guide | `/laos-signature-tours/` | View |
| `lcr-guide` | Lao-China Railway E-Ticket Guide | `/lcr-e-ticket-guide/` | **Print** |
| `luxury-guide` | Private Luxury Travel in Laos | `/luxury-laos-tours/` | View |
| `student-group-planner` | Laos Student Group Learning Planner | `/student-group-learning/` | **Print** |
| `journey-calendar` | When to Visit Laos — Planning Calendar | `/upcoming-tours/` | View |

Print is offered only where someone genuinely carries the paper — the railway
guide at a station, a planner in a staff room. Inspirational guides get View.

Note `student-group-planner` points at `/student-group-learning/`, the canonical
page; `/student-group-tours/` redirects there.

---

## Design

Every value in the stylesheet resolves to a token already defined by the Brother
Tours theme, so the popup inherits the site's palette, typography and dark mode
rather than carrying its own. Dark mode needs no work: the theme flips
`[data-theme]` on `<html>` and these tokens follow.

The most visible detail is the corner radius. The brand sets `--radius: 0` — the
legacy popup was rounded, and matching the current site means square. Selectors
are namespaced `btrd-*` so nothing collides with the theme, Elementor or core.

---

## Accessibility

Focus moves into the dialog on open and is trapped while it is open; ESC closes;
focus returns to the originating control. Overlay click closes, but only when the
press started on the overlay — a drag from inside the dialog does not.
Interactive controls are `<button>`; file links are `<a>` with `download`, so
right-click and open-in-new-tab behave normally.

The scroll lock uses `position: fixed`, the only approach iOS Safari respects,
and restores the exact offset on close.

---

## Analytics

Events fire through whatever the site already loads — `gtag` and/or `dataLayer`.
If neither exists the calls are no-ops. **No analytics script is ever injected.**

`resource_popup_view` · `resource_popup_close` · `resource_download` ·
`resource_view` · `resource_print` · `resource_cta_build_trip`

Each carries `resource_id`, `resource_name`, `resource_category`,
`landing_page`, `page_type` and `trigger_type`
(`timed` | `scroll` | `exit` | `manual`).

Clicks through to Build My Trip gain `source_resource` and `trip_interest`
parameters, so a lead can be traced back to the guide that produced it rather
than counting downloads in isolation.

---

## Testing

```bash
npm install playwright --no-save
node tests/popup.test.mjs
```

20 assertions in headless Chromium covering trigger timing, the engagement gate,
scroll threshold, focus management, scroll lock, toast, suppression and
analytics. See `tests/README.md`.

---

## What this does not cover

| Deliverable | Why not here |
|---|---|
| The ten PDF documents | Content, not code. Needs verified business facts and the production media library. |
| Ten Elementor landing templates | Built — `brother-tours-elementor-landing-system/`. Each calls `[bt_resource_download]`. |
| Legacy content migration | `Landing-pages.zip` was not supplied. |
| LCR factual verification | Railway rules change; 2026 rules need checking against the operator before publishing. |

This plugin is the part that is the same for every guide. The guides themselves
still have to be written.

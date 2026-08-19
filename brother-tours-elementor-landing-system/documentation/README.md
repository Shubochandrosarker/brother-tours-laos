# Brother Tours Elementor Landing System

Ten landing-page templates and seven reusable sections, as Elementor JSON, for
manual import.

Import → insert → the page is built. No section is reconstructed by hand.

---

## Status

**The templates are complete. Three things they depend on are not.**

| Deliverable | State |
|---|---|
| 10 page templates | ✅ Built and validated |
| 7 global sections | ✅ Built and validated |
| Landing stylesheet | ✅ Built — ships in the theme, mirrored in `assets/` |
| Hero images | ❌ Every template ships with an empty image slot |
| The 10 PDF guides | ❌ Not written — see `../pdf/` |
| Taxonomy term slugs | ⚠️ Guessed from the brief; each carries a note to confirm |

None of these blocks the import. A template with no hero image imports and
renders; the slot shows Elementor's placeholder until someone picks a picture.

---

## What is here

```
brother-tours-elementor-landing-system/
├── templates/     10 page templates          → import as Saved Templates
├── globals/        7 reusable sections       → import once, insert anywhere
├── assets/         bt-landing.css            → mirror of the theme stylesheet
├── pdf/            the ten guides            → not written yet
├── build/          generator + validator
└── documentation/  this file
```

`templates/` and `globals/` are **generated**. Edit `build/content/pages.mjs`
and re-run the build; do not hand-edit the JSON, or the next build will
overwrite you.

```bash
node build/build.mjs                      # write templates/ and globals/
node build/build.mjs --check              # fail if the committed files are stale
node build/validate.mjs                   # 8,000+ structural and content checks
node build/preview.mjs bt-honeymoon       # render one to HTML, on the real theme CSS
node build/preview.mjs bt-honeymoon --light --notes
```

`preview.mjs` writes a standalone HTML page to stdout using the site's actual
stylesheets. It is an approximation of Elementor's markup, not a substitute for
it, but it is enough to see a page before importing it — and it earned its keep
during the build by catching a primary CTA that rendered identically to its
ghost neighbour.

---

## Importing

**WordPress Admin → Templates → Saved Templates → Import Templates**, then
upload the `.json` file. It appears in the list; **Insert** it into a page.

Import the seven globals first — they are the pieces you will re-use — then the
page templates as you need them.

### After inserting a page template

1. **Set the page template to Elementor Full Width.**
   Each file already carries `elementor_header_footer` in its page settings,
   but a Saved Template inserted into an existing page does not always apply
   them. Check *Page Settings → Layout* in Elementor.

   Not Canvas. Canvas strips the production header and footer, and rebuilding
   navigation inside each page is the legacy architecture this rebuild removes.

2. **Choose the hero image.** One empty image slot per page, with a note saying
   what it should show.

3. **Resolve the verification notes** (below).

4. **Confirm the tour grid is showing journeys.** If it says *"No tours match
   this filter yet"*, the term slug does not exist — see below.

5. **Check the hero on a phone.** Every row carries
   `flex_direction_tablet: column`, and Elementor emits that as a media query
   on its own per-element selector. If a hero ever stays two-column at 390px,
   that setting is the cause and nothing in `bt-landing.css` can override it —
   open the hero row in Elementor and check *Layout → Direction* on the tablet
   breakpoint.

---

## Verification notes

Every unverifiable specific is marked rather than invented, per the brief.

The notes are **hidden from visitors** and visible to:

- anyone editing the page in Elementor, and
- any logged-in user with `edit_posts` viewing the published page.

They look like this:

> **REQUIRES BUSINESS VERIFICATION** — confirm how many founder-hosted journeys
> run per year, the guest capacity per journey…

Resolve one by confirming the fact and either writing it in or deleting the
note. A page with unresolved notes is safe to publish — nothing false is
visible — it is simply less complete than it could be.

The heaviest concentration is on **`bt-lcr-guide`**. Railway procedures change,
and the brief marks that page HIGH PRIORITY: no timing, luggage allowance,
prohibited-item list or ID rule is stated anywhere on it. Verify the 2026 rules
with the operator, write them in, and add a *Last reviewed* date.

---

## What each page is made of

The same twelve sections, in the same order, on all ten pages:

| # | Section | Built from |
|---|---|---|
| 01 | Hero | eyebrow · H1 · lede · description · badges · two CTAs · image slot |
| 02 | Quick answer | the block a search or answer engine can lift whole |
| 03 | Why this experience | 4–6 cards |
| 04 | Featured journeys | **`bt-tour-grid`** — live from WordPress |
| 05 | Destinations | `bt-destination-experiences` or a linked list (omitted where a page has none) |
| 06 | Planning guide | days · seasons · pace · transport · suitability |
| 07 | PDF resource | `[bt_resource_download]` shortcode |
| 08 | Local insight | Ken's note |
| 09 | How Brother Tours plans it | the five steps |
| 10 | FAQ | Elementor accordion, 5–6 questions |
| 11 | Related content | internal links |
| 12 | Final invitation | **`bt-build-my-trip-cta`** — theme widget |

### Nothing about a journey is written into a template

Section 04 is the theme's own `bt-tour-grid` widget querying `wpistic_tour`.
No journey title, duration or price appears in any file in this kit. Retire a
tour in WordPress and it leaves all ten pages the same day.

Where a grid filters by taxonomy term, the slug is a **guess from the brief**
and carries a note:

| Page | Filter |
|---|---|
| Adventure Tours | `tour_category` = `adventure` |
| Central Laos | `tour_destination` = `central-laos` |
| Founder Hosted | `tour_category` = `founder-hosted` |
| Honeymoon | `tour_category` = `honeymoon` |
| Indochina | `tour_category` = `indochina` |
| Signature Tours | `tour_category` = `signature-journeys` |
| Luxury Laos | `tour_category` = `luxury` |
| LCR guide · Student groups · Calendar | all tours, unfiltered |

If a slug does not exist the widget renders its own empty state rather than
breaking. Fix it by correcting the slug in the widget, or by creating the term.

### The Final Invitation is not rebuilt

Section 12 is `bt-build-my-trip-cta`, which renders the theme's locked
invitation copy and **reads the WhatsApp number from Theme Options**. No
WhatsApp number is written into any template — change it in one place and ten
pages follow.

The hero's secondary CTA points at `/contact/` for the same reason, with a note
offering the swap.

---

## Styling

The templates carry the theme's own classes — `.section`, `.wrap`, `.eyebrow`,
`.btn`, `.btn--primary`, `.section-sand`, `.section-navy` — so they inherit the
site's spacing, typography, buttons and square corners rather than carrying a
second design system.

> **A note on `.btn-solid`.** The templates deliberately do not use it. The
> parent theme defines `.btn-solid` as the gold fill, but the child theme's
> `brand-tokens.css` redefines `.btn` as ghost-gold and loads later, so a
> `.btn-solid` button renders with a transparent background — identical to the
> ghost button beside it. The child theme's own name for the solid treatment is
> `.btn--primary`, and that is what these templates use.
>
> This was found by rendering a template rather than reading it, and it has one
> consequence outside this kit: `wpistic_build_my_trip_cta()` in the parent
> theme still uses `.btn-solid`, so **the site's own Final Invitation CTA is
> currently rendering as a ghost button everywhere it appears** — including at
> the foot of these ten pages, since section 12 is that widget. Adding
> `.btn-solid` to the `.btn--primary` rule group in `brand-tokens.css` would fix
> it site-wide in one line. That is a visible change to every page on the site,
> so it is flagged here rather than made.

**No colour, font family or font size is written into any widget's settings.**
That is deliberate: the site flips `data-theme` on `<html>` and every theme
token follows, but a hex value baked into Elementor settings does not. Light
and dark mode work on these pages because nothing overrides the tokens.

`bt-landing.css` adds only what the theme did not already have: the card grid,
the planning grid, the numbered process list, the badge row and the
verification notes.

**It ships in the theme** at
`themes/brother-tours/assets/css/bt-landing.css`, enqueued only on pages whose
Elementor data contains a `bt-landing-` class — a page that does not use a
landing template pays nothing.

If you are importing templates before the theme is deployed, paste
`assets/bt-landing.css` into **Appearance → Customize → Additional CSS** as an
interim. That file is a generated mirror of the theme copy; the theme is the
original.

---

## The PDF resource section

Section 07 renders `[bt_resource_download id="…"]`, resolved by the **Brother
Tours Resource Downloads** plugin.

| Template | Resource id |
|---|---|
| `bt-adventure-tours` | `adventure-planner` |
| `bt-central-laos` | `central-laos-guide` |
| `bt-founder-hosted` | `founder-hosted-guide` |
| `bt-honeymoon` | `honeymoon-guide` |
| `bt-indochina` | `indochina-planner` |
| `bt-signature-tours` | `signature-guide` |
| `bt-lcr-guide` | `lcr-guide` |
| `bt-luxury-laos` | `luxury-guide` |
| `bt-student-groups` | `student-group-planner` |
| `bt-journey-calendar` | `journey-calendar` |

Until a PDF is attached (via the `btrd_resources` filter — see that plugin's
README) the section renders **nothing** to visitors and a diagnostic to
editors. A download button pointing at a missing file is worse than no button.

The popup is not in this kit and does not need to be. It is implemented once in
the same plugin and appears automatically on any page whose resource has a file
behind it. `globals/bt-pdf-popup.json` is a stub that says so.

---

## Content rules this kit enforces

`build/validate.mjs` fails the build if published copy contains any of:

- a price figure — pricing is *confirmed on request*
- artificial scarcity (*"only 2 seats left"*)
- an ABTA or comparable accreditation claim
- a Picsum or placeholder image host
- an absolute external URL
- a hardcoded WhatsApp number
- an unverified group capacity

It also checks, per page: exactly one H1, exactly one resource shortcode, a
live tour grid, a FAQ, a Final Invitation, no duplicate element ids, no
Elementor Canvas, no baked image URL, and that every raw-markup block is either
a verification note or the one known markup block — so nothing slips past the
copy scan by omitting a class.

The notes themselves are excluded from that scan, because their job is to name
the forbidden things: *"do not restore the $7,200 pricing"* would otherwise
read as a price.

---

## Regenerating

Content lives in `build/content/pages.mjs`, one object per page. Structure
lives in `build/lib/sections.mjs`. Change either and:

```bash
node build/build.mjs && node build/validate.mjs
```

Output is deterministic — element ids are hashed from a stable path, not
random — so the same input produces byte-identical files and a diff shows the
words that changed rather than a thousand new ids.

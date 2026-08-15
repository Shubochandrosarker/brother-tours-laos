# Brother Tours — Content Import Guide

**Who this is for:** the content lead and anyone entering pages, tours, destinations, journal posts, or images into the Brother Tours WordPress site. No coding knowledge is needed. Where a step does need a developer, the guide says so.

**One rule above all others:** run the brand lint before you publish anything (see the last section), and never publish copy that has not passed the brand rules in this guide.

---

## 1. First-time setup: Tour Manager → Site Setup

The site does not start empty. A one-click setup screen creates the core pages for you.

### How to run it

1. Log in to the WordPress admin.
2. In the left menu, go to **Tour Manager → Site Setup**.
3. Read the screen. It lists any pages that are still missing.
4. Click **Create missing pages and profile**.

You can run this as many times as you like. It only creates pages that are missing — it never edits or overwrites a page that already exists, so your work is always safe.

### What it creates

**Published immediately** (structure is complete and safe to show):

| Page | Address | Notes |
|---|---|---|
| About | `/about/` | Uses the About template with the locked founder copy |
| Build My Trip | `/build-my-trip/` | The main inquiry page; carries the Build My Trip form |
| Contact | `/contact/` | Carries the Contact form |
| Travel Agents | `/agents/` | B2B page; carries the Travel Agent form |
| Thank You | `/thank-you/` | Shown after a form is sent |
| Journal | `/journal/` | Becomes the blog index automatically |

**Created as drafts** (need work before they can go live — see section 2):

| Page | Address |
|---|---|
| FAQ | `/faq/` |
| Visa Guide | `/visa-guide/` |
| When to Visit | `/when-to-visit/` |
| Privacy Policy | `/privacy/` |
| Terms | `/terms/` |
| Cancellation Policy | `/cancellation/` |
| Sitemap | `/sitemap/` |

The setup also registers a **Tourflows connection profile**. It is created switched off, with no address and no secret. A developer or the owner adds the Tourflows endpoint and signing secret on the Connections screen and switches it on. You do not need to touch this.

---

## 2. The draft pages: what each needs before publishing

A draft page is invisible to visitors. Each of these stays a draft until the work below is done. To publish: open the page, add the content, then change the status from Draft to Published.

| Page | What it needs before publishing |
|---|---|
| **FAQ** | Real questions guests actually ask, with plain answers. No invented questions. |
| **Visa Guide** | Accurate, current visa information checked against official Lao government sources, with a "last checked" date. |
| **When to Visit** | Honest month-by-month guidance: weather, festivals, river levels, what is open. |
| **Privacy Policy** | The policy text, **approved by legal review**. Must state how long form data is kept. Do not publish without sign-off. |
| **Terms** | The terms text, **approved by legal review**. Do not publish without sign-off. |
| **Cancellation Policy** | The cancellation text, **approved by legal review**. Do not publish without sign-off. |
| **Sitemap** | A simple page of links to every main section of the site (this is the human-readable sitemap, separate from the XML one search engines use). |

---

## 3. The five forms

The site ships with five ready-made forms. Edit them at **WP admin → Formistic → Forms**.

| Form | Routing slug | What a submission does |
|---|---|---|
| Build My Trip | `build-my-trip` | Creates an inquiry in Tour Manager → Bookings & Inquiries |
| Contact | `contact` | Creates an inquiry |
| Newsletter | `newsletter` | Adds the address to the mailing list only — never creates an inquiry |
| Travel Agent | `travel-agent` | Creates an inquiry marked as a trade/agent inquiry |
| Request Tour Availability | `request-availability` | Creates an inquiry carrying the tour's id and title (a second entry point alongside the tour page's own booking widget — see `docs/formistic-brother-tours-integration.md`) |

### What you may safely change

- Field labels, help text, the order of fields, the success message, the button label.
- The form's **title**. Routing does not depend on the title, so renaming "Build My Trip" to something else does not break anything.

### The routing slug (`_brother_tours_form`) — read this before deleting anything

Each form carries a hidden tag called `_brother_tours_form` (values in the table above). This tag — not the form's name — is how the system knows which submissions should become inquiries in Tour Manager. It is stored as hidden data on the form, so you will not see it on the edit screen.

> **Warning: never delete and recreate a form.**
> If you delete one of the five forms and build a new one by hand, the new form will **not** carry the routing tag. It will still look identical and still send email, but its submissions will silently stop appearing in Tour Manager → Bookings & Inquiries. Edit the existing forms in place instead.
>
> **If a form has already been deleted:** ask a developer to deactivate and reactivate the Formistic plugin. That recreates any missing form with its routing tag restored. Then copy your changes into the recreated form and delete the untagged copy. (A developer can also set the tag directly on a hand-made form.)

Two more rules:

- The **Newsletter** form's type must stay set to "newsletter". That setting is what keeps sign-ups out of the inquiries screen.
- Never add a question about the guest's budget to any form. See the brand rules in section 8.
- The **Request Tour Availability** form's two hidden fields (tour id and tour name) are populated automatically per placement — do not remove them or hand-edit their values; see `Wpistic_Formistic_BT_Forms::render_request_availability()`.

---

## 4. Adding a Tour

Tours live under **Tour Manager → Tours** (they are their own content type, not ordinary pages). The tour catalog appears at `/tours/`.

### Steps

1. Go to **Tours → Add New**.
2. Enter the tour title and the full description in the main editor.
3. Set a featured image (see the image rules in section 7).
4. Fill in the tour details boxes below the editor (field list next).
5. Assign the taxonomies in the right-hand column (list below).
6. Click **Publish**, then open the live page and check it.

### Tour detail fields

| Field | What to enter |
|---|---|
| `wpistic_subtitle` | One short line shown under the title |
| `wpistic_short_summary` | One or two sentences used on cards and lists |
| `wpistic_duration` | e.g. "8 days" |
| `wpistic_start_location` | Where the journey begins, e.g. "Luang Prabang" |
| `wpistic_end_location` | Where it ends |
| `wpistic_group_size` | Phrased for this tour only, e.g. "Private, 2–6 guests" |
| `wpistic_minimum_age` | e.g. "12" — leave empty if none |
| `wpistic_season` | The months this journey runs best |
| `wpistic_accommodation` | e.g. "4-star and heritage properties" |
| `wpistic_transport` | e.g. "Private vehicle and river boat" |
| `wpistic_from_price` | Number only, no currency symbol, e.g. `2400` |
| `wpistic_pricing_type` | How the price is quoted (per person, per group) |
| `wpistic_availability` | Availability note shown to guests |

### Tour taxonomies (the checkboxes in the right column)

| Taxonomy | Use it for |
|---|---|
| `tour_category` | The catalog grouping, e.g. Signature Journeys |
| `tour_destination` | The places this tour visits |
| `tour_duration_range` | The length bracket, e.g. 7–10 days |
| `tour_difficulty` | Physical demand level |
| `tour_season` | Best season(s) |
| `travel_style` | The style of travel, e.g. Culture, Family |
| `region` | Region of Laos, e.g. Northern Laos |
| `country` | Country (for multi-country journeys) |

These power the filters on the `/tours/` catalog, so assign them thoughtfully — a tour with no taxonomies will not show up when guests filter.

Tour pages carry the booking widget automatically; you do not add it by hand.

---

## 5. Adding a Destination and an Experience

### Destination

1. Go to **Tour Manager → Destinations → Add New**.
2. Title, full description, featured image, publish.
3. The page appears at `/destinations/your-destination/`, and the index at `/destinations/`.

### Experience (always belongs to a destination)

Experiences are the things to do within a destination. Their web address is nested under their destination: `/destinations/{destination}/{experience}/` — for example `/destinations/luang-prabang/alms-ceremony/`.

1. Create and publish the **Destination first**.
2. Go to **Tour Manager → Experiences → Add New**.
3. Write the title and description.
4. In the details box, set **Parent destination** (this is the `wpistic_parent_destination` field) to the destination it belongs to. **This choice is what builds the web address.** An experience with no parent destination will not have a correct address.
5. Publish, then click through to the live page and confirm the address reads `/destinations/{destination}/{experience}/`.

If a new experience page shows "not found": go to **Settings → Permalinks** and click **Save Changes** (no need to change anything — saving refreshes the address rules), then reload.

---

## 6. Journal posts

Journal posts are ordinary WordPress **Posts**. The index lives at `/journal/`.

### Categories

The content plan calls for six journal categories. They are **not** created automatically — create them once under **Posts → Categories**, using the names agreed in the approved content plan. Keep names short (one or two words). Give every post exactly one primary category.

### The structure every post follows

1. **Standfirst** — one bold opening line under the headline that says what the piece delivers.
2. **Lede** — answer the question the headline raises within the first 2–3 sentences. Do not make the reader scroll for the answer.
3. **At least three H2 sections** — each H2 a plain statement of what the section covers. Do not skip heading levels (H2 then H3, never H2 then H4).
4. **Images roughly every 300 words** — following the image rules in section 7.
5. **Author block** — the named author with a one-line bio.
6. **Dates** — the published date and a visible "last updated" date whenever the post is revised.
7. **Related posts / related journeys** — end with links to related journal posts and to relevant tours.

Every post must name real places, real hosts, and real dates. No invented statistics, no invented quotes.

---

## 7. Image rules

Every image, on every page, follows all of these:

| Rule | Detail |
|---|---|
| Format | WebP, with a JPG fallback |
| Size | Under 250KB per image |
| Filename | Descriptive, lowercase, hyphen-separated: `luang-prabang-morning-market.webp` — never `IMG_4032.jpg` |
| Alt text | 8–20 words describing the scene for someone who cannot see it, e.g. "Vendors laying out river fish at the Luang Prabang morning market". Purely decorative images get an empty alt (`alt=""`). |
| Dimensions | Explicit width and height set on every image |
| Loading | Lazy-load anything below the first screen; never lazy-load the top hero image |
| EXIF | Strip metadata (location, camera data) before upload |
| Children | No identifiable children in marketing images without written permission from a parent or guardian |
| Licensing | Licensed media only, with the license records kept; model releases for identifiable adults |

---

## 8. Brand rules every editor must follow

The brand voice is locked. These rules are not stylistic suggestions — the launch checklist blocks the site on them.

### The locked phrases (use them exactly, letter for letter)

| Phrase | Where it belongs |
|---|---|
| "Experience Laos Through the People Who Call It Home." | Homepage hero headline |
| "Lao-led. Globally understood." | About hero lede |
| "Born Here. Guide Here." | Tagline, in every page footer |
| "Not a guide. A host." | The host introduction opener |
| "We design your journey, your way." | Pricing language — never "your budget" |
| "Each journey runs a fixed number of times each year." | Capacity language |
| "Consistently top-rated on Google and TripAdvisor." | The review section |

Retired phrases that must never reappear: "Small, Lao, and ours" · "Our guides are licensed, Lao, and our own" · "named guides" (say "named hosts").

### The banned word list

These words are banned from body copy, headlines, page titles, descriptions, and image alt text. The list below quotes them so you know what to avoid — do not use them anywhere on the site:

> authentic · immersive / immersion · meaningful · hidden gem / hidden corners · curated · bespoke · discover / explore (figurative use) · unforgettable · world-class · premier · luxury (as a descriptor) · professional / expert / expertise (as praise) · your budget / envelope · magical · stunning · passionate · boutique · award-winning

Also banned as constructions: defensive "We do not X" sentences; "Unlike other operators..." comparisons; rhetorical questions to the reader; self-praise; softening words ("try to", "maybe", "perhaps").

### The other hard rules

1. **No rating numbers, no award claims.** No "4.9 stars", no review counts, no star graphics, no awards. The only approved review line is the locked phrase above. This holds until the verified-review threshold is reached and the owner lifts it.
2. **Never ask for a budget.** Not on forms, not on pages, not in email copy. We ask about dates, interests, and hotel preference — never money the guest is willing to spend.
3. **Capacity is phrased per-tour, not per-company.** Say what a single journey does ("Each journey runs a fixed number of times each year."). Never make company-wide volume or scarcity claims.
4. **The founder bio stays general.** Told through work: licensed National Tour Guide since 2010, founded Brother Tours in 2018. No ethnicity. No monastic detail. No personal-origin storytelling.
5. **Testimonials must be real.** Only quotes the owner has confirmed as verified guest reviews may publish. Unconfirmed quotes — including the three in the reference design — stay off the site.

---

## 9. The redirect map (old site addresses)

The old site at `brothertourslaos.com` has addresses that search engines still know. Each of those must forward to the right page on the new site, and the list of them lives in one file: **`docs/redirect-map.csv`**.

Right now that file is a **template with no entries** — just the header row and a commented example:

```
old_url,new_url,status,notes
```

How it gets filled in:

1. The owner exports the list of indexed addresses from **Google Search Console** for brothertourslaos.com.
2. Each exported address gets one row: the old path, the new page it should forward to, the status (`301`), and a short note.
3. A developer implements the rows as redirects.

> **Never guess an old address.** Only addresses that come from the Search Console export (or server logs) go in the file. A guessed address wastes a redirect at best and forwards a real page to the wrong place at worst.

---

## 10. Run the brand lint before publishing

Before any page, tour, or post goes live, run the brand lint. It scans for banned words and brand-rule violations.

Ask whoever has command-line access to the site (or run it yourself from the project folder):

```
php scripts/brand-lint.php
```

- **If it reports nothing:** you are clear to publish.
- **If it flags something:** fix the copy it points to, then run it again. Do not publish over a failing lint.

The lint is a safety net, not a substitute for the rules in section 8 — it cannot judge tone, rhetorical questions, or a figurative use it has never seen. Read your copy against section 8 first, then lint.

# Brother Tours — Launch Checklist

Converted from **06 – Acceptance Test Checklist** ("Born Here. Guide Here." · Pre-Launch Verification · Brother Tours Sole Co., Ltd. · Vientiane, Lao PDR). Every item from the source checklist is preserved below, with a status recorded against the current repository build.

> ## ⚠ Testing status: NOTHING has been verified against a running site
>
> **As of 2026-07-24, no item in this document has been tested on a running WordPress installation.** This build exists only as code in a repository. There is no WordPress install, no database, no browser session, no Lighthouse run, no schema validation, no Core Web Vitals measurement, and no Search Console access. Every status below that says `PASS` means one thing only: *the claim is verifiable by reading the code in this repository*. No `PASS` claims runtime behavior. Anything that requires a browser, a database, a live site, an email delivery, or an external tool is marked `NOT-YET-VERIFIABLE` and must be tested on staging before launch.
>
> The source checklist's rule stands: *"If any item below fails, the launch is delayed until it passes. There are no 'minor' items on this list."* Not "mostly pass." Not "good enough." Every single item.

## How statuses were assigned

| Status | Meaning |
|---|---|
| `PASS` | The repository itself is the evidence, verified by reading code in this build. No runtime behavior is claimed. Used only where the claim is fully decidable from the repo. |
| `FAIL` | Known not satisfied in this build: the deliverable is absent from the repository and from the plan of record, or owner input it depends on has not been supplied. Action is required before the item can even be tested. |
| `NOT-YET-VERIFIABLE` | Cannot be checked without a running WordPress install, a browser, a database, email delivery, or an external tool. No claim is made either way. |

**Tally: 3 `PASS` · 24 `FAIL` · 155 `NOT-YET-VERIFIABLE` (182 items).**

Repo evidence cited below was gathered by reading and searching the repository on 2026-07-24, and re-verified for the v2.0.0 addendum (section 10) on the date that section was added. "Repo scan" means a text search across the theme templates, the child theme, the Formistic fork, and the Tour Manager plugin in this repository. A repo scan can never prove anything about pages an editor later writes into the database — final verification always requires the built site. Before launch, also run `scripts/release-check.sh` and `php scripts/brand-lint.php` (see `docs/content-import-guide.md`).

---

## 1. Brand voice verification

### 1.1 Locked phrases present and correct

Template copy in the repo carries these phrases, which is positive evidence — but rendered pages and editor-entered content are unverified, so no row can pass yet.

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | Homepage hero headline: "Experience Laos Through the People Who Call It Home." | `NOT-YET-VERIFIABLE` | Phrase hardcoded in `themes/wpistic/front-page.php` (line 63, with `<em>Home</em>` markup). Verify rendered homepage on staging. |
| 2 | About hero lede: "Lao-led. Globally understood." | `NOT-YET-VERIFIABLE` | Phrase present in `themes/wpistic/page-about.php`, `front-page.php` and the footer colophon (`footer.php` line 76). Verify rendered About page. |
| 3 | Tagline "Born Here. Guide Here." on every page footer | `NOT-YET-VERIFIABLE` | Hardcoded in `themes/wpistic/footer.php` (line 20). "Every page" requires a rendered-site crawl. |
| 4 | Host introduction uses "Not a guide. A host." opener | `NOT-YET-VERIFIABLE` | Present in `page-about.php` line 81 and `front-page.php` line 143 (with `<em>host</em>` markup). Verify rendered pages. |
| 5 | Pricing language uses "We design your journey, your way." (not "your budget") | `NOT-YET-VERIFIABLE` | Phrase in `themes/wpistic/page-build-my-trip.php` and as the seeded Build My Trip page excerpt (`SiteSeeder.php`). Repo scan finds no "your budget" in guest-facing copy. Verify rendered pages and all editor content. |
| 6 | Capacity language uses "Each journey runs a fixed number of times each year." | `NOT-YET-VERIFIABLE` | Phrase in `themes/wpistic/single-tour.php`. Verify rendered tour pages once tour content exists. |
| 7 | Review section: "Consistently top-rated on Google and TripAdvisor." | `NOT-YET-VERIFIABLE` | Phrase in `front-page.php`, `page-about.php`, `page-travel-from.php`. Verify rendered review sections. |

### 1.2 Retired phrases NOT present

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | "Small, Lao, and ours" appears NOWHERE on the site | `NOT-YET-VERIFIABLE` | Repo scan: zero occurrences anywhere in the repository. There is no site to scan yet; re-scan the built site (all pages, meta, alt text) before launch. |
| 2 | "Our guides are licensed, Lao, and our own" appears NOWHERE | `NOT-YET-VERIFIABLE` | Repo scan: zero occurrences. Re-scan the built site before launch. |
| 3 | "named guides" appears NOWHERE (should be "named hosts") | `NOT-YET-VERIFIABLE` | Repo scan: zero occurrences of "named guides"; `page-about.php` uses "Named hosts". Re-scan the built site before launch. |

### 1.3 Banned words NOT present

The source requires scanning **every page** — body copy, headlines, meta, alt text. No pages exist yet, so no row can pass on rendered output. What *can* be stated: `php scripts/brand-lint.php` currently reports **0 errors** across the repository, with all eight locked phrases present.

Re-run it, plus a full-site crawl covering rendered copy, meta descriptions and image alt text, on staging before launch.

**Resolved since this checklist was first written:** the demo catalog seeder
`plugins/wpistic-tour-manager/src/Admin/ContentSeeder.php` shipped demo tour titles and slugs containing "Immersion", "immersive", "authentic" and "hidden-gems" — values that become live URLs and page titles as soon as an admin runs the seeder. They have been retitled and re-slugged, and the brand lint now passes on that file.

**What the lint does not cover**, and therefore still needs a human pass:

- Content editors type into WordPress. The lint only sees the repository.
- Four matches remain as warnings inside code comments (not guest-facing), and three as review items — the base theme's "Explore Laos" / "Explore destination" UI labels in `archive-wpistic_destination.php` and `taxonomy-region.php`. These were left for a human decision rather than silently rewritten, since "explore" is banned only in its figurative use.
- The rulebook files themselves (`README.md`, `docs/content-import-guide.md`, `docs/launch-checklist.md`) and the vendored upstream Formistic source are excluded from the scan; see the header of `scripts/brand-lint.php` for why.

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | "authentic" — zero occurrences | `NOT-YET-VERIFIABLE` | Repo templates clean; known hit in legacy demo seeder (see note above). Full-site scan pending. |
| 2 | "immersive" / "immersion" — zero occurrences | `NOT-YET-VERIFIABLE` | Repo templates clean; known hit in legacy demo seeder (see note above). Full-site scan pending. |
| 3 | "meaningful" — zero occurrences | `NOT-YET-VERIFIABLE` | Repo template scan clean. Full-site scan pending. |
| 4 | "hidden gem" / "hidden corners" — zero occurrences | `NOT-YET-VERIFIABLE` | Repo templates clean; known hit in legacy demo seeder slug (see note above). Full-site scan pending. |
| 5 | "curated" — zero occurrences | `NOT-YET-VERIFIABLE` | Repo template scan clean. Full-site scan pending. |
| 6 | "bespoke" — zero occurrences | `NOT-YET-VERIFIABLE` | Repo template scan clean. Full-site scan pending. |
| 7 | "discover" (figurative) — zero occurrences | `NOT-YET-VERIFIABLE` | Requires human judgment on figurative use across final copy. Full-site scan pending. |
| 8 | "unforgettable" — zero occurrences | `NOT-YET-VERIFIABLE` | Repo template scan clean. Full-site scan pending. |
| 9 | "world-class" / "premier" — zero occurrences | `NOT-YET-VERIFIABLE` | Repo template scan clean. Full-site scan pending. |
| 10 | "luxury" as descriptor — zero occurrences | `NOT-YET-VERIFIABLE` | Repo template scan clean. Full-site scan pending. |
| 11 | "professional" / "expert" / "expertise" — zero occurrences | `NOT-YET-VERIFIABLE` | Repo guest-facing template scan clean (technical code comments excluded). Full-site scan pending. |
| 12 | "your budget" / "envelope" — zero occurrences | `NOT-YET-VERIFIABLE` | Repo template scan clean; no budget field in any seeded form (`class-formistic-bt-forms.php`). Full-site scan pending. |
| 13 | "magical" / "stunning" / "passionate" — zero occurrences | `NOT-YET-VERIFIABLE` | Repo template scan clean. Full-site scan pending. |

### 1.4 Banned constructions NOT present

All four constructions require editorial judgment over final page copy, which does not exist yet.

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | No "We do not X" defensive constructions | `NOT-YET-VERIFIABLE` | Review final copy on staging; include in the content lead's pre-publish pass. |
| 2 | No "Unlike other operators..." negative framing | `NOT-YET-VERIFIABLE` | Repo scan finds none in templates. Review final copy. |
| 3 | No rhetorical questions to the reader | `NOT-YET-VERIFIABLE` | Requires editorial review of every final page. |
| 4 | No self-praise statements | `NOT-YET-VERIFIABLE` | Requires editorial review of every final page. |
| 5 | No softening phrases ("try to", "maybe", "perhaps") | `NOT-YET-VERIFIABLE` | Requires editorial review of every final page. |

### 1.5 Founder bio compliance

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | Founder bio kept GENERAL — no ethnicity referenced | `NOT-YET-VERIFIABLE` | Repo scan of templates finds no ethnicity reference (only a code comment stating the rule in `page-about.php`). Verify final published bio copy. |
| 2 | Founder bio does not mention monastic detail | `NOT-YET-VERIFIABLE` | Repo scan finds no monastic reference in templates. Verify final published bio copy. |
| 3 | Founder credentials: "licensed National Tour Guide since 2010, founded Brother Tours in 2018" | `NOT-YET-VERIFIABLE` | Template copy: "Licensed Lao National Tour Guide since 2010 · Brother Tours founded 2018" (`front-page.php` line 146) and matching body copy at line 145. Confirm final wording on the published About page. |
| 4 | Founder story told through work, not personal origins | `NOT-YET-VERIFIABLE` | Template copy follows this framing; requires editorial review of the final page. |

---

## 2. Technical SEO verification

### 2.1 Core Web Vitals (mobile)

No Lighthouse or field measurement has been run — there is no site to measure. All eight rows require a staging environment and Lighthouse/PageSpeed Insights on mobile.

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | Largest Contentful Paint (LCP) under 2.0s | `NOT-YET-VERIFIABLE` | Measure on staging. |
| 2 | Interaction to Next Paint (INP) under 200ms | `NOT-YET-VERIFIABLE` | Measure on staging. |
| 3 | Cumulative Layout Shift (CLS) under 0.1 | `NOT-YET-VERIFIABLE` | Measure on staging. |
| 4 | Time to First Byte (TTFB) under 600ms | `NOT-YET-VERIFIABLE` | Measure on staging; depends on host. |
| 5 | First Contentful Paint under 1.5s | `NOT-YET-VERIFIABLE` | Measure on staging. |
| 6 | Lighthouse Performance score 90+ on every page tested | `NOT-YET-VERIFIABLE` | Run Lighthouse per page type on staging. |
| 7 | Lighthouse Accessibility score 95+ | `NOT-YET-VERIFIABLE` | Run Lighthouse on staging. |
| 8 | Total page weight under 1.5MB | `NOT-YET-VERIFIABLE` | Measure on staging once real images exist (none supplied yet). |

### 2.2 Indexability

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | XML sitemap accessible at /sitemap.xml, returns 200 | `NOT-YET-VERIFIABLE` | WordPress core serves `/wp-sitemap.xml` by default; nothing in this build serves `/sitemap.xml`. Decide at launch: SEO plugin, or a redirect from `/sitemap.xml`. Then test the URL. |
| 2 | HTML sitemap exists at /sitemap | `FAIL` | The seeder creates the Sitemap page as a **draft** with no content (`SiteSeeder.php`, route `sitemap`). Write the HTML sitemap content, publish, then test. |
| 3 | robots.txt allows crawl, disallows admin paths | `NOT-YET-VERIFIABLE` | WordPress default robots.txt behaves this way; confirm on the live host (a physical robots.txt file or host rule can override it). |
| 4 | No noindex tags accidentally left on production pages | `NOT-YET-VERIFIABLE` | Check `Settings → Reading → Search engine visibility` and crawl the built site. |
| 5 | All Phase 1 pages crawlable | `NOT-YET-VERIFIABLE` | Requires the built site and a crawl. |
| 6 | Sitemap submitted to Google Search Console | `NOT-YET-VERIFIABLE` | Requires live site + GSC access (verification token field exists in the Customizer; token not supplied). |
| 7 | Sitemap submitted to Bing Webmaster Tools | `NOT-YET-VERIFIABLE` | Requires live site + Bing access (token field exists; token not supplied). |
| 8 | Mobile-first indexing verified | `NOT-YET-VERIFIABLE` | Requires GSC after launch. |

### 2.3 Security and trust

The child theme implements a security-header baseline in code (`themes/brother-tours/functions.php`, `brother_tours_security_headers()`), but a header only counts as "set" when it is observed on a live HTTP response — server config and full-page caches can strip or override headers. So no header row can pass yet.

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | HTTPS active site-wide, no mixed content | `NOT-YET-VERIFIABLE` | Host-level; test on live site. |
| 2 | HSTS header set | `NOT-YET-VERIFIABLE` | Code sends `Strict-Transport-Security` when `is_ssl()` (`functions.php` line 383). Verify on a live HTTPS response. |
| 3 | SSL certificate valid, auto-renewing | `NOT-YET-VERIFIABLE` | Host-level; confirm certificate and renewal automation. |
| 4 | X-Frame-Options header set | `NOT-YET-VERIFIABLE` | Code sends `X-Frame-Options: SAMEORIGIN`. Verify on a live response. |
| 5 | X-Content-Type-Options header set | `NOT-YET-VERIFIABLE` | Code sends `X-Content-Type-Options: nosniff`. Verify on a live response. |
| 6 | Content Security Policy header set | `NOT-YET-VERIFIABLE` | Code sends CSP in **Report-Only** mode by default; enforcement is opt-in via the `brother_tours/csp_enforce` filter after the report endpoint is quiet. Decide report-only vs. enforcing for launch, then verify the header live. |
| 7 | Physical address in footer | `NOT-YET-VERIFIABLE` | Footer template carries "Vientiane, Lao PDR" (`footer.php` line 76) — city and country only. Confirm whether the full registered street address is required, add it, verify rendered footer. |
| 8 | Contact info visible on every page | `NOT-YET-VERIFIABLE` | Footer template includes contact links; verify rendered output on every page type. |

### 2.4 Old-site redirects

**Blocking condition:** the indexed-URL list from `brothertourslaos.com` was never supplied. `docs/redirect-map.csv` is a header-only template with zero data rows. Old URLs must be exported from Google Search Console (and/or server logs) — **never guessed**. Until that export arrives, the mapping work cannot start.

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | Every indexed URL from brothertourslaos.com mapped | `FAIL` | `docs/redirect-map.csv` has no rows. Owner must export the indexed URL list from Search Console; then fill the map. |
| 2 | Every mapping implemented as 301 redirect | `FAIL` | No mappings exist to implement. (The only redirect in this build is the on-site `/plan-my-laos-trip/` → `/build-my-trip/` 301 in `themes/brother-tours/functions.php`; it is unrelated to the old domain.) |
| 3 | Every redirect tested — returns target URL | `NOT-YET-VERIFIABLE` | Blocked on rows 1–2; then test each redirect on the live setup. |
| 4 | No 404s on previously-indexed URLs | `NOT-YET-VERIFIABLE` | Blocked on rows 1–2; then crawl the old URL list against the new site. |
| 5 | Unmapped URLs redirect to most relevant section, not 404 | `FAIL` | No fallback rule exists in this build. Design the catch-all (host rule or plugin) alongside the redirect map. |

---

## 3. On-page SEO verification

### 3.1 Every page passes 13-element template

Every row here is a property of final page content, which has not been written. All rows require the built site plus the keyword map (see 3.3).

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | Primary keyword identified for every page | `NOT-YET-VERIFIABLE` | Blocked on the keyword map (3.3, currently missing). |
| 2 | Title tag under 60 chars, keyword near front | `NOT-YET-VERIFIABLE` | Audit each built page. |
| 3 | Meta description 140-160 chars, keyword present | `NOT-YET-VERIFIABLE` | Audit each built page (an SEO plugin will likely supply the fields). |
| 4 | URL clean, lowercase, hyphen-separated | `NOT-YET-VERIFIABLE` | Seeded slugs and CPT rewrites in the repo follow this pattern (`build-my-trip`, `tours`, `destinations/{d}/{e}`); editor-created URLs must be audited on the built site. |
| 5 | One H1 per page, no skipped heading levels | `NOT-YET-VERIFIABLE` | Audit rendered pages. |
| 6 | Primary keyword in first 100 words | `NOT-YET-VERIFIABLE` | Audit final copy. |
| 7 | Word count meets minimum for page type | `NOT-YET-VERIFIABLE` | Audit final copy. |
| 8 | Internal links: 3-5 minimum per page | `NOT-YET-VERIFIABLE` | Audit final copy. |
| 9 | All anchor text descriptive | `NOT-YET-VERIFIABLE` | Audit final copy. |
| 10 | Page-type appropriate structured data, validates | `NOT-YET-VERIFIABLE` | Tour/destination schema emitters exist (see 3.2); several page types have no emitter yet. Validate on staging. |
| 11 | Open Graph and Twitter Card tags present | `NOT-YET-VERIFIABLE` | No OG/Twitter tag emitter found in this build's themes; plan to supply via SEO plugin, then verify in page source. |
| 12 | Featured image set for OG | `NOT-YET-VERIFIABLE` | Requires real images (not yet supplied) and the OG mechanism above. |

### 3.2 Structured data validates

Repo facts: `plugins/wpistic-tour-manager/src/Integration/SchemaData.php` emits `TouristTrip` (with `Offer`), `TouristDestination`, and `TouristAttraction` only. No other schema emitter exists anywhere in this build. Missing types must be implemented (SEO plugin or code) before they can validate.

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | Organization schema on homepage | `FAIL` | No emitter in this build. Add via SEO plugin or theme code, then validate. |
| 2 | LocalBusiness / TravelAgency schema | `FAIL` | No emitter in this build. Add, then validate. |
| 3 | BreadcrumbList on every sub-page | `FAIL` | No emitter in this build. Add, then validate. |
| 4 | TouristTrip schema on tour pages | `NOT-YET-VERIFIABLE` | Emitter exists (`SchemaData.php` line 29). Verify emitted JSON-LD on a built tour page, then validate. |
| 5 | TouristDestination schema on destination pages | `NOT-YET-VERIFIABLE` | Emitter exists (`SchemaData.php` line 53). Verify on a built destination page, then validate. |
| 6 | BlogPosting schema on journal posts with Person author | `FAIL` | No emitter in this build. Add, then validate. |
| 7 | FAQPage schema where Q&A structure used | `FAIL` | No emitter in this build (FAQ page itself is an empty draft). Add alongside the FAQ content. |
| 8 | Person schema for Ken on About page | `FAIL` | No emitter in this build. Add, then validate. |
| 9 | NO AggregateRating schema (until 100+ verified reviews) | `PASS` | Verified by reading code: no `AggregateRating` emitter exists anywhere in this repository (`SchemaData.php` emits only the three types above; repo-wide search matches only comments documenting the exclusion, e.g. the Customizer description in `themes/brother-tours/functions.php` line 436). Re-check the live site at launch in case a third-party plugin or review embed injects rating markup. |
| 10 | All schema validates with zero errors in Rich Results Test | `NOT-YET-VERIFIABLE` | Requires the built site and the Rich Results Test; currently also blocked by the missing types above. |

### 3.3 Keyword cannibalization

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | Keyword map exists and is current | `FAIL` | No keyword map exists in this repository or the supplied materials. Create it before content entry begins. |
| 2 | No two pages target the same primary keyword | `NOT-YET-VERIFIABLE` | Blocked on the keyword map and final content. |
| 3 | Pillar pages and cluster pages do not compete | `NOT-YET-VERIFIABLE` | Blocked on the keyword map and final content. |

---

## 4. Image SEO verification

**Context:** real photography has not been supplied. The reference design uses gradient placeholders. Every row in 4.1–4.3 can only be checked once real, licensed images are uploaded to the media library.

### 4.1 Every image

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | WebP format with JPG fallback | `NOT-YET-VERIFIABLE` | No real images exist yet. Enforce at upload time; audit on staging. |
| 2 | Under 250KB per image | `NOT-YET-VERIFIABLE` | Audit the media library once populated. |
| 3 | Filename descriptive, hyphen-separated, lowercase | `NOT-YET-VERIFIABLE` | Enforce at upload; audit. |
| 4 | Alt text present on every meaningful image | `NOT-YET-VERIFIABLE` | Audit rendered pages. |
| 5 | Alt text describes scene, includes context, 8-20 words | `NOT-YET-VERIFIABLE` | Editorial audit. |
| 6 | Decorative images have empty alt='' | `NOT-YET-VERIFIABLE` | Template decorative images already use `alt=""` (e.g. hero background in `front-page.php` line 58); audit content images on the built site. |
| 7 | Width and height attributes set explicitly | `NOT-YET-VERIFIABLE` | Audit rendered markup. |
| 8 | Lazy loading on below-fold images | `NOT-YET-VERIFIABLE` | WordPress lazy-loads by default; hero uses `fetchpriority="high"` in the template. Verify rendered attributes per page. |
| 9 | EXIF metadata stripped | `NOT-YET-VERIFIABLE` | Add to the upload workflow; spot-check uploaded files. |

### 4.2 Featured images

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | Every page has a featured image | `NOT-YET-VERIFIABLE` | Requires real photography (not supplied). Audit once set. |
| 2 | Featured image set as og:image | `NOT-YET-VERIFIABLE` | Blocked on the OG tag mechanism (3.1 row 11) and real images. |
| 3 | Share preview tested on Facebook, Twitter, LinkedIn | `NOT-YET-VERIFIABLE` | Requires the live site and each platform's debugger. |

### 4.3 Photography rules

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | Stock images licensed and attributed where required | `NOT-YET-VERIFIABLE` | No images supplied yet. Owner must supply licensed media with records. |
| 2 | Model releases secured for identifiable people | `NOT-YET-VERIFIABLE` | Owner to collect releases alongside the photography. |
| 3 | No children's faces in public marketing without explicit permission | `NOT-YET-VERIFIABLE` | Owner policy check on every supplied image before upload. |
| 4 | Cultural sensitivity verified — no religious ceremonies used disrespectfully | `NOT-YET-VERIFIABLE` | Owner review of every supplied image. |

---

## 5. Functional verification

### 5.1 Forms

**Updated for v2.0.0:** Formistic now seeds **five** forms (`plugins/formistic/includes/class-formistic-bt-forms.php`), routed to Tour Manager by `plugins/wpistic-tour-manager/src/Integration/FormisticIngestion.php`. The fifth, Request Tour Availability, is a second, Formistic-rendered entry point alongside — not instead of — Tour Manager's own booking widget; see `docs/formistic-brother-tours-integration.md` for why both exist safely. None of this has ever executed — there is no WordPress install — so every submission-path row is untested.

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | Build My Trip form submits successfully | `NOT-YET-VERIFIABLE` | Seed definition exists (slug `build-my-trip`); test a real submission on staging end-to-end into the portal. |
| 2 | Tour inquiry form submits successfully | `NOT-YET-VERIFIABLE` | Tour-page "Request Availability" posts to REST `POST /wp-json/wpistic/v1/booking` via `[wpistic_booking_widget]` (separate from Formistic by design). Test on staging. |
| 3 | Contact form submits successfully | `NOT-YET-VERIFIABLE` | Seed definition exists (slug `contact`). Test on staging. |
| 4 | Newsletter signup submits successfully | `NOT-YET-VERIFIABLE` | Seeded as Formistic type `newsletter`, whose code path subscribes and returns without creating an inquiry. Confirm on staging: subscription recorded, no inquiry created. |
| 5 | Travel agent form submits successfully | `NOT-YET-VERIFIABLE` | Seed definition exists (slug `travel-agent`). Test on staging. |
| 6 | Request Tour Availability (Formistic form) submits successfully | `NOT-YET-VERIFIABLE` | Seed definition exists (slug `request-availability`), hidden tour_id/tour_title populated by `Wpistic_Formistic_BT_Forms::render_request_availability()`. Test a real submission on staging via the Elementor widget, and confirm exactly one booking is created with the correct tour_id. |
| 7 | Form validations work on client and server side | `NOT-YET-VERIFIABLE` | Test required-field, email-format and consent validation in a browser. |
| 8 | Confirmation message shown after submit | `NOT-YET-VERIFIABLE` | Success copy is seeded per form; verify displayed message in a browser. |
| 9 | Thank-you page returns 200 | `NOT-YET-VERIFIABLE` | Page is seeded published with template `page-thank-you.php`; verify HTTP 200 on staging. |

### 5.2 Tourflows integration

**Blocking condition:** the Tourflows endpoint URL and signing secret have not been supplied. The seeder registers the connection profile **disabled with an empty endpoint and secret** (`SiteSeeder.php`, `seed_tourflows_profile()`), so nothing can be pushed anywhere yet.

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | API endpoint configured for Tourflows push | `FAIL` | Profile is seeded disabled with empty endpoint/secret by design. Owner must supply both; enter them on the Connections screen and enable. |
| 2 | Test inquiry pushes to Tourflows successfully | `NOT-YET-VERIFIABLE` | Blocked on row 1; then run a live test submission and confirm receipt (HMAC-SHA256 signature header `X-Wpistic-Signature: sha256=<hex>`). |
| 3 | API failure handling — data saved locally on failure | `NOT-YET-VERIFIABLE` | By code order, the booking is created locally before dispatch (`FormisticIngestion::ingest()`), and connection attempts log to `wpistic_connection_log`. Prove it on staging by pointing at a dead endpoint. |
| 4 | Retry logic functional | `NOT-YET-VERIFIABLE` | Connections engine retries 3x at 60s/240s per code. Verify retries fire on staging. |
| 5 | Manual re-sync option available in portal | `NOT-YET-VERIFIABLE` | Manual re-dispatch exists in the portal code. Verify the control works on staging. |

### 5.3 Email notifications

No email has ever been sent from this build. Delivery additionally depends on SMTP/transactional provider credentials, which have not been supplied.

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | Internal team notified on new inquiry | `NOT-YET-VERIFIABLE` | Ingestion fires `wpistic/notify` `inquiry.created` exactly once per submission (code); Notifier sends the mail. Test delivery on staging with a real mailbox. |
| 2 | Guest receives confirmation email | `NOT-YET-VERIFIABLE` | Formistic autoresponder exists in code. Test delivery to a real guest address. |
| 3 | Email templates branded correctly | `NOT-YET-VERIFIABLE` | Review rendered emails in real clients on staging. |
| 4 | Sender address: enquiry@brothertours.com | `PASS` | Code-level default verified: `Wpistic_Formistic_BT_Branding::DEFAULT_FROM_EMAIL = 'enquiry@brothertours.com'`, configurable via option `brother_tours_from_email`, applied only to this plugin's own mail (`class-formistic-bt-branding.php` lines 42, 139-142, 183-185). Actual delivery and SPF/DKIM alignment are untested — covered by rows 1-3 and the SMTP owner item. |
| 5 | Reply-to address: enquiry@brothertours.com | `PASS` | Code-level default verified: option `brother_tours_reply_to` falls back to the sender address (default `enquiry@brothertours.com`); a `Reply-To` header is added when the caller has not set one (`class-formistic-bt-branding.php` lines 159-162, 203-224). Confirm on a delivered message during staging tests. |

### 5.4 Internal portal

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | Portal accessible at admin URL with login | `NOT-YET-VERIFIABLE` | Portal code exists (Tour Manager → Bookings & Inquiries). Verify login + access on staging. |
| 2 | 2FA enabled for all admin accounts | `FAIL` | No 2FA solution exists in this build, and per policy it must come from an approved security plugin or host policy — none has been chosen. Owner must pick one; then enable and verify per account. |
| 3 | New inquiries appear in dashboard | `NOT-YET-VERIFIABLE` | Test with a real submission on staging. |
| 4 | Status workflow functional (New / Reviewed / Sent to Tourflows / Closed) | `NOT-YET-VERIFIABLE` | Statuses new/reviewed/sent/closed exist in code. Exercise the workflow on staging. |
| 5 | Manual note field works | `NOT-YET-VERIFIABLE` | Notes exist in portal code. Test on staging. |
| 6 | CSV export works | `NOT-YET-VERIFIABLE` | Export exists in portal code. Download and open a real export on staging. |
| 7 | Role-based access enforced | `NOT-YET-VERIFIABLE` | Capability checks exist in code. Test with a non-admin account on staging. |

### 5.5 Booking widget (tour pages only)

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | Booking widget on every tour detail page | `NOT-YET-VERIFIABLE` | `[wpistic_booking_widget]` and the tour template exist in code; verify rendering on built tour pages (no real tours exist yet). |
| 2 | Date picker functional | `NOT-YET-VERIFIABLE` | Browser test on staging. |
| 3 | Guest count selector functional | `NOT-YET-VERIFIABLE` | Browser test on staging. |
| 4 | Hotel preference selector functional | `NOT-YET-VERIFIABLE` | Browser test on staging. |
| 5 | Add-ons selector functional (if applicable) | `NOT-YET-VERIFIABLE` | Browser test on staging. |
| 6 | Submit pushes to Tourflows with tour pre-filled | `NOT-YET-VERIFIABLE` | Also blocked on Tourflows credentials (5.2 row 1). End-to-end test on staging. |
| 7 | "Build My Trip" link visible as secondary action on tour pages | `NOT-YET-VERIFIABLE` | CTA URL/label are locked by child-theme filters (`wpistic/cta_url`, `wpistic/cta_label`); verify placement visually on a built tour page. |

---

## 6. Content verification

### 6.1 Phase 1 pages all live

The Site Setup seeder (`plugins/wpistic-tour-manager/src/Admin/SiteSeeder.php`) creates the structural pages, but it has never been run (no WordPress install). Pages marked `FAIL` below are known-missing content: they cannot go live until someone writes or approves the content — no page in this build was fabricated to fill the gap.

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | Homepage | `NOT-YET-VERIFIABLE` | `front-page.php` template with locked copy exists; build and verify on staging. |
| 2 | About | `NOT-YET-VERIFIABLE` | Seeder publishes `/about` with template `page-about.php`; verify on staging. |
| 3 | Contact | `NOT-YET-VERIFIABLE` | Seeder publishes `/contact` with template `page-contact.php`; verify on staging. |
| 4 | Tours catalog (/tours) | `NOT-YET-VERIFIABLE` | CPT archive at `/tours` with `archive-wpistic_tour.php` and working filters (`pre_get_posts` tax_query in the child theme); verify once tours exist. |
| 5 | 5 Signature Journey detail pages | `FAIL` | No real tour content exists in this build. The legacy demo seeder's tours are another dataset and must not be used as-is (see 1.3 note). Owner/content lead must create the five journeys. |
| 6 | 6 Destination pillar pages | `FAIL` | Destination CPT content not populated with real copy. Content lead must create the six destinations. |
| 7 | Journal index | `NOT-YET-VERIFIABLE` | Seeder creates `/journal` and assigns it as the posts page (`page_for_posts`); verify on staging. |
| 8 | 6 Journal category pages | `FAIL` | Journal categories are not created anywhere in this build. Create them per the approved content plan. |
| 9 | 8-10 starter Journal posts | `FAIL` | Not written. By policy, no fabricated articles were generated. Content lead must write them (structure in `docs/content-import-guide.md`). |
| 10 | Privacy Policy | `FAIL` | Seeded as **draft** pending legal review; not live until reviewed, filled and published. |
| 11 | Terms & Conditions | `FAIL` | Seeded as **draft** pending legal review. |
| 12 | Cancellation Policy | `FAIL` | Seeded as **draft** pending legal review. |
| 13 | Build My Trip page | `NOT-YET-VERIFIABLE` | Seeder publishes `/build-my-trip` with template and seeded form; verify on staging. |
| 14 | Travel Agents (B2B) page | `NOT-YET-VERIFIABLE` | Seeder publishes `/agents` with template `page-agents.php`; verify on staging. |
| 15 | 404 page | `NOT-YET-VERIFIABLE` | `404.php` template exists; verify a live 404 response renders it. |
| 16 | Thank-you page | `NOT-YET-VERIFIABLE` | Seeder publishes `/thank-you`; verify on staging (see 5.1 row 8). |

### 6.2 Content quality

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | Every page has been independently reviewed | `NOT-YET-VERIFIABLE` | Content does not exist yet; schedule the review pass once written. |
| 2 | Every page passes brand voice check | `NOT-YET-VERIFIABLE` | Run `php scripts/brand-lint.php` plus a human pass on final copy. |
| 3 | Every page has the correct CTA placement | `NOT-YET-VERIFIABLE` | Verify per page type on staging (see 6.3). |
| 4 | Real specifics throughout — named places, named hosts, named dates | `NOT-YET-VERIFIABLE` | Editorial audit of final copy. |
| 5 | No fabricated stats, no invented testimonials, no fake reviews | `NOT-YET-VERIFIABLE` | **Warning:** three testimonial quotes exist in the reference design captures but are UNVERIFIED — the owner must confirm each one is a real, verified guest review before any of them is published. Until confirmed, they must stay off the site. Demo/sample data (theme sample data, legacy demo seeder) must not ship to production. |

### 6.3 CTA placement

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | Every non-tour page has Build My Trip as primary CTA | `NOT-YET-VERIFIABLE` | CTA URL and label are locked by child-theme filters to `/build-my-trip/`; verify placement on every built page type. |
| 2 | Tour detail pages have booking widget as primary | `NOT-YET-VERIFIABLE` | Template code exists; verify on a built tour page. |
| 3 | Tour detail pages have Build My Trip as secondary action | `NOT-YET-VERIFIABLE` | Verify on a built tour page. |
| 4 | Sticky Build My Trip button visible on mobile | `NOT-YET-VERIFIABLE` | Rendered in `wp_footer` by `brother_tours_sticky_cta()` (child theme); "visible on mobile" requires a device/browser test. |
| 5 | Footer CTA section present on every page | `NOT-YET-VERIFIABLE` | Footer template includes the CTA section; verify on rendered pages. |

---

## 7. Legal and compliance

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | Privacy Policy reviewed and approved | `FAIL` | Legal review has not happened. Page is deliberately a draft until the owner's legal review approves the copy. |
| 2 | Terms & Conditions reviewed and approved | `FAIL` | Same — pending legal review. |
| 3 | Cancellation Policy reviewed and approved | `FAIL` | Same — pending legal review. |
| 4 | Cookie banner present and functional | `NOT-YET-VERIFIABLE` | Banner markup and accept/decline handling exist in code (`footer.php` lines 104-108, `navigation.js`); "functional" requires a browser test. |
| 5 | Cookie consent stored, respected by analytics scripts | `NOT-YET-VERIFIABLE` | Consent storage exists in code, but **no analytics tag-output code exists in this build** (see 8.1) — when analytics wiring or a tag plugin is added, it must read the stored consent choice. Verify in a browser: decline → no tags fire. |
| 6 | GDPR-compliant data handling for EU visitors | `NOT-YET-VERIFIABLE` | Consent capture on forms and a data-retention cron exist in code (`class-formistic-gdpr.php`; ingestion records consent to the audit log). Full compliance requires the reviewed privacy policy and a live-site check. |
| 7 | Form data retention policy stated | `NOT-YET-VERIFIABLE` | Belongs in the Privacy Policy draft, which has no content yet. |
| 8 | All third-party scripts identified and disclosed | `NOT-YET-VERIFIABLE` | Inventory the live site once analytics/embeds are added; disclose in the privacy policy. |

---

## 8. Analytics and monitoring

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | Google Analytics 4 installed and configured | `FAIL` | Not installed: the Customizer stores a GA4 ID (`brother_tours_ga4_id`), but no code in this build outputs the GA4 tag, and no ID has been supplied by the owner. Add consent-gated tag output (or a tag plugin), enter the ID, then verify events. Same applies to the Meta Pixel and Clarity fields. |
| 2 | Google Search Console verified | `NOT-YET-VERIFIABLE` | Verification meta tag output exists (`brother_tours_verification_tags()`); requires the live site plus the owner's GSC token. |
| 3 | Bing Webmaster Tools verified | `NOT-YET-VERIFIABLE` | Same mechanism (`msvalidate.01`); requires live site + token. |
| 4 | Conversion goals configured (form submissions) | `NOT-YET-VERIFIABLE` | Configure in GA4 after row 1 is resolved. |
| 5 | Uptime monitor configured (Uptime Robot or similar) | `NOT-YET-VERIFIABLE` | External service; set up at launch. |
| 6 | Daily backup configured, tested with restore | `NOT-YET-VERIFIABLE` | Host-level; configure and run a real restore test. |

---

## 9. Pre-launch final review

All eight rows are launch-stage human verification steps. None can occur before a staging site exists.

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | SEO Strategist has signed off | `NOT-YET-VERIFIABLE` | Schedule after content and technical items close. |
| 2 | Ken has reviewed and approved every page | `NOT-YET-VERIFIABLE` | Owner review on staging. |
| 3 | Content Lead has confirmed all posts ready | `NOT-YET-VERIFIABLE` | After journal posts are written (currently `FAIL`, 6.1 row 9). |
| 4 | Developer has run the full QA pass | `NOT-YET-VERIFIABLE` | Includes `scripts/release-check.sh` and `php scripts/brand-lint.php` plus this checklist end to end. |
| 5 | Mobile experience tested on iOS and Android devices | `NOT-YET-VERIFIABLE` | Real-device test on staging. |
| 6 | Cross-browser tested (Chrome, Safari, Firefox, Edge) | `NOT-YET-VERIFIABLE` | Browser matrix test on staging. |
| 7 | Slow-network test passed (3G simulation) | `NOT-YET-VERIFIABLE` | DevTools 3G throttle test on staging. |
| 8 | Old domain (brothertourslaos.com) redirects tested | `NOT-YET-VERIFIABLE` | Blocked on the redirect map (2.4) and DNS/host setup for the old domain. |

---

## 10. v2.0.0 changes verification

Added for the v2.0.0 coordinated release (G2A removal, Formistic rename,
Elementor integration, Tour Manager dashboard rebuild, frontend + admin
light/dark modes). These rows are in addition to the tally at the top of
this document, which was last computed before this release; re-total both
together before sign-off.

### 10.1 G2A / unrelated-client removal

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | `plugins/wpistic-crm` (confirmed unrelated Guns2Ammo firearms-retailer CRM) removed from the deployable tree | `PASS` | Verified by reading: directory no longer exists in the repository; confirmed before removal via its own `guns2ammo-crm` text domain and `G2A_CRM` package constant, and zero cross-references from any other plugin or theme. |
| 2 | No G2A code path remains reachable in Formistic | `PASS` | `class-formistic-g2a-defaults.php` deleted (previously a *reachable* admin action seeding real G2A business facts — a live defect, not dead code); the `g2a_request`/`g2a_reservation` legacy capture path, its option, and its settings-screen row removed; `g2a_biz()`/`guns2ammo` template fallbacks in the email layer replaced. See `plugins/formistic/UPSTREAM.md`, "G2A removal (v2.0.0)". |
| 3 | Automated scan confirms zero G2A residue in deployable files | `PASS` | `php scripts/brand-lint.php` (new `unrelated-client` rule) and `sh scripts/release-check.sh` (new Gate 5, an independent second scan over all tracked files) both report zero matches outside the two files that legitimately document the removal as history (`UPSTREAM.md`, `readme.txt`). Re-run both after any future merge from upstream Formistic. |

### 10.2 Formistic naming

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | Plugin presents as "Formistic" only, authored by "WordPressistic" | `NOT-YET-VERIFIABLE` | Plugin header, admin menu relabel (`Wpistic_Formistic_BT_Branding::rebrand_menu()`), and settings screens all say "Formistic"/"WordPressistic" in code. Verify the rendered wp-admin UI on staging. |
| 2 | Customer-facing forms/emails remain Brother Tours branded | `NOT-YET-VERIFIABLE` | Guest-facing copy is unchanged by the rename (only the plugin's own admin identity changed). Verify rendered forms and delivered emails on staging. |
| 3 | Only one Formistic plugin active; duplicate-load guard works | `NOT-YET-VERIFIABLE` | Guard exists in `formistic.php` (unchanged by the rename beyond the constant it checks). Test by installing both plugins side by side on a staging install and confirming the second to load shows the admin notice rather than fataling. |

### 10.3 Elementor integration

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | Theme locations (header/footer/single/archive/404) registered with PHP fallback | `NOT-YET-VERIFIABLE` | `elementor_theme_do_location()` wraps the relevant section in every listed template with a `function_exists()` guard; PHP fallback renders when Elementor is inactive or no template is assigned. Verify both states on a staging install with and without Elementor. |
| 2 | `wpistic_tour`/`wpistic_destination`/`wpistic_experience` are Elementor-editable | `NOT-YET-VERIFIABLE` | `elementor_cpt_support` filter extends (never overwrites) the existing array. Verify in Elementor > Settings on staging that `page`/`post` (or any operator-enabled type) survived alongside the three new ones. |
| 3 | All 18 Brother Tours widgets registered and render real data | `NOT-YET-VERIFIABLE` | All 18 class names exist and match `bootstrap.php`'s registration list; every widget's meta-key reads were checked against the templates that already read them in production (`single-tour.php`, `single-destination.php`) rather than assumed. Elementor editor rendering, drag-and-drop behavior, and actual on-screen output require a live WordPress + Elementor install. |
| 4 | Widgets fail safely without Elementor or without their data source | `PASS` (code-evidenced) | Every widget class only registers inside the `elementor/widgets/register` hook Elementor itself fires; every `render()` method checks its data source and calls `render_empty_state()`/`render_admin_notice()` rather than emitting broken markup. Confirmed by reading every widget file; no fatal-error path found. Live verification still recommended on staging. |

### 10.4 Tour Manager dashboard

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | Dashboard KPIs render with real data sources | `NOT-YET-VERIFIABLE` | Every KPI's data source is documented inline in `Admin\Dashboard::render_kpis()`; "Upcoming departures" depends on the `wpistic_dep_date` field being filled in on each departure (correctly shows zero until then, not fabricated). Verify rendered values against known bookings on staging. |
| 2 | Bookings list: pagination, search, filters, sort, bulk actions all function | `NOT-YET-VERIFIABLE` | Replaces the prior unpaginated `LIMIT 200` query; every filter is parameterized via `$wpdb->prepare()`, sort columns validated against an allow-list. Exercise every control on staging with a realistic number of bookings. |
| 3 | Booking detail tabs (Overview/Traveler/Trip/Payments/Activity/Connections) all render | `NOT-YET-VERIFIABLE` | `BookingDetail.php` implements all six; the Trip tab resolves `tour_id` to the tour's real title (previously showed only a raw numeric id). Verify each tab against a real booking on staging. |
| 4 | Connections tab shows real delivery history and manual resend works | `NOT-YET-VERIFIABLE` | Delivery history is read from the audit log via a new `wpistic_tm_connection_dispatched` hook (see `docs/tourflows-integration.md` for the full mechanism); manual resend is nonce/capability-protected and re-dispatches without re-creating the booking or re-sending the guest email — confirmed by reading the call chain. Blocked on Tourflows credentials (5.2 row 1) for an end-to-end test; the UI and audit trail can be verified without them using any configured connection. |
| 5 | Admin light/dark theme toggle persists per user | `NOT-YET-VERIFIABLE` | Persisted via `user_meta`, AJAX round-trip nonce-protected. Verify the toggle and its persistence across a login session on staging. |

### 10.5 Frontend + admin light/dark mode

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | Frontend light mode is a genuine second palette, not a copy of dark mode | `PASS` (code-evidenced) | Previously `[data-theme="light"]` and `[data-theme="dark"]` resolved to identical values in `brand-tokens.css`; now genuinely distinct. Every text/background/fill pairing was checked against WCAG AA with a short script (documented inline in the CSS and in `docs/light-dark-mode.md`) rather than eyeballed. Visual review on staging still recommended. |
| 2 | Frontend defaults to dark mode (Brother Tours' primary identity) for new visitors | `NOT-YET-VERIFIABLE` | `theme_mod_wpistic_default_mode` filter in the child theme; tested against a minimal WP-function stub for correctness and for the infinite-recursion bug an earlier draft would have caused. Verify on a fresh browser profile on staging. |
| 3 | Admin dashboard light/dark tokens pass WCAG AA | `PASS` (code-evidenced) | Computed and verified the same way as the frontend tokens before writing `dashboard.css`; see the file's header comment. |
| 4 | Theme toggle is keyboard-operable with correct `aria-pressed` state | `NOT-YET-VERIFIABLE` | Both the frontend toggle (`aria-pressed` added and synced on load/click) and the admin toggle set `aria-pressed` correctly in code. Verify with a screen reader and keyboard-only navigation on staging. |

### 10.6 Version coordination

| # | Item | Status | Evidence / Next action |
|---|---|---|---|
| 1 | All four shipped components report version 2.0.0 | `PASS` | Verified by reading every version string: both theme `style.css` headers, both plugin headers, both `WPISTIC_*_VERSION` constants, both `readme.txt` Stable tags. |
| 2 | No database schema silently claimed as migrated | `PASS` | Both `WPISTIC_FORMISTIC_DB_VERSION` and `WPISTIC_TM_DB_VERSION` deliberately left unchanged — no schema change in this release for either plugin (the connection-dispatch history reuses the existing audit log rather than a new column; documented inline at both version constants and in `docs/upgrade-to-2.0.0.md`). |

## Sign-off

Preserved from the source checklist. Signed by the lead reviewer **only when every item above is checked** — which, given the tally at the top, is not yet possible.

```
_______________________________________  Name

_______________________________________  Role

_______________________________________  Date
```

> "If any item below fails, the launch is delayed until it passes. There are no 'minor' items on this list. Each one was put here because failing it has real consequences — broken SEO, contradicted brand voice, lost trust, lost guests."

---

## Outstanding items requiring owner input

Nothing on this list can be closed by the development team alone. Each item blocks one or more checklist rows above.

| # | Owner must supply | Blocks |
|---|---|---|
| 1 | **Real photography** — licensed images with model releases; no identifiable children without written permission | All of section 4; featured images; OG images; page-weight measurement |
| 2 | **Tourflows endpoint URL + signing secret** — entered on the Connections screen, never committed to the repo | 5.2 entirely; 5.5 row 6 |
| 3 | **Google reviews profile URL + TripAdvisor profile URL** — for the Customizer review fields and any review widget embed | Review section links/widgets (1.1 row 7 context) |
| 4 | **Verified testimonial quotes** — confirm whether the three quotes in the reference design captures are real, verified guest reviews; only confirmed ones may publish | 6.2 row 5 |
| 5 | **Legacy URL export from Search Console** for brothertourslaos.com — the redirect map has zero rows and old URLs must never be guessed | 2.4 entirely; 9 row 8 |
| 6 | **Legal review of the three policy pages** — Privacy Policy, Terms & Conditions, Cancellation Policy (all currently drafts) | 6.1 rows 10-12; 7 rows 1-3, 7 |
| 7 | **SMTP / transactional email provider credentials** — configured at the host level, never stored in this plugin or repo | 5.3 delivery rows |
| 8 | **2FA plugin choice** — an approved security plugin or host-level policy; none is bundled | 5.4 row 2 |
| 9 | **GA4 measurement ID, Meta Pixel ID, Microsoft Clarity ID** (plus GSC and Bing verification tokens) | Section 8; 7 row 5 |

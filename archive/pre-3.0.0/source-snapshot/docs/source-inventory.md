# Source inventory

Every supplied source inspected before code was changed, where it lives, and
what was taken from it.

## Supplied archives

### `Brother_Tours_website_construction.zip`

Extracted for inspection only. Nothing from it is committed to this repository:
its HTML carries export artifacts, inline scripts and gradient placeholder
imagery that must not ship.

| Path in archive | What it is | Used for |
|---|---|---|
| `uploads/Brother-Tours-Developer-Handoff-Pack_5/` | Newest handoff pack | **Authoritative.** All docs below |
| `uploads/Brother-Tours-Developer-Handoff-Pack_4/` | Prior pack | Verified byte-identical to pack 5 for the four key documents |
| `uploads/Brother-Tours-Developer-Handoff-Pack_2/` | Older pack | Superseded |
| `…/LOCKED-PHRASE-UPDATES.txt` | June 2026 locked-phrase addendum | **Highest precedence.** Retired phrases, new locked phrases |
| `…/MASTER-AI-BUILD-PROMPT.md` | Canonical master brief | Brand, IA, homepage sections, design tokens, typography |
| `…/Brother-Tours-Brand-Copy-Clean.md` | Rewritten brand copy | Section copy, founder story, how-we-work |
| `…/01-PDF/`, `…/02-DOCX-editable/` | 15 documents each | Architecture v2, SEO blueprint, migration plan, acceptance checklist, legal templates |
| `…/03-Reference-HTML/brother-tours-homepage-v5.html` | Reference homepage | Design tokens, type scale, component patterns |
| `…/03-Reference-HTML/brother-tours-about.html` | Reference About | Section order, standards ledger |
| `…/03-Reference-HTML/brother-tours-contact.html` | Reference Contact | Form layout, field styling |
| `*.dc.html` (root of archive) | Design captures, newer IA | Eight-section homepage order, nav and footer structure |
| `assets/logo-*.png/.webp` | Logo files | Already present in the child theme |
| `support.js`, `image-slot.js`, `.image-slots.state.json` | Editor tooling from the design tool | **Not used.** Export artifacts |

### `brothertoursclaudecodebuildprompt.md`

The execution brief for this build. Establishes that this is a **WordPress**
project and that the construction pack's Next.js/Sanity/Prisma stack is
reference-only.

## Precedence applied

Resolved in this order, per the build brief:

1. `LOCKED-PHRASE-UPDATES.txt`
2. Construction pack: project brief, architecture v2, SEO blueprint, migration
   plan, acceptance checklist
3. `MASTER-AI-BUILD-PROMPT.md`
4. Reference HTML and design captures
5. Existing repository code, where it does not conflict with the above

### Conflicts found and how they were resolved

| Conflict | Resolution |
|---|---|
| Master prompt locks the stack to Next.js + Sanity + Prisma + Vercel; the build brief says WordPress | **WordPress.** The build brief is the execution instruction; the pack's stack section is reference-only. Brand, content, SEO, UX and acceptance requirements from the pack still govern. |
| `Brother-Tours-Brand-Copy-Clean.md` says "British English throughout"; `MASTER-AI-BUILD-PROMPT.md` and the build brief both say American English | **American English.** Two higher-precedence sources agree against one lower one. |
| Brand copy uses the retired "Small, Lao, and ours" / "Our guides are licensed, Lao, and our own" | Retired per the locked-phrase addendum. `scripts/brand-lint.php` fails the build if either reappears. |
| Reference v5 homepage has 9 sections and nav "Journeys, Destinations, About, Journal, Contact"; the `.dc` captures have the 8-section order and nav "About, Destinations, Tours, Journal, Contact" | **The 8-section order and the `.dc` navigation**, which match the master prompt's prescribed IA. v5 remains the styling authority. |
| Reference v5 contact form asks for "The envelope we design within" (a budget field) | **Removed.** The master prompt bans asking for budget. The Build My Trip form asks "What kind of experience are you imagining?" instead. |
| Parent theme CTA is "Plan My Laos Trip" → `/plan-my-laos-trip/`; brand locks "Build My Trip" → `/build-my-trip/` | Brand wins. The parent's CTA became filterable; the child sets both. The old route 301s rather than being deleted. |

## Existing repository code

Inspected before any edit. The working tree was clean at the start; no unrelated
user changes were present to preserve.

| Component | State found | Notes |
|---|---|---|
| `themes/wpistic` (parent) | WPistic 1.2.1 | Complete template set. Palette and typography are a **different** design system from the locked Brother Tours tokens — only `--ink` (dark mode) and Caveat overlapped |
| `themes/brother-tours` (child) | v1.1.0 | Was nearly empty: two CSS rules, three hooks, logo files |
| `plugins/wpistic-tour-manager` | v1.1.0, PHP 8.1, WP 6.4 | CPTs, bookings, payments, connections, portal. Custom PSR-4 autoloader, no Composer |
| `plugins/wpistic-crm` | Present | **Unrelated** to this build. Untouched, and excluded from brand lint |
| `plugins/brother-tours-formistic` | Created in this change | Copy of Formistic 2.1.0 @ `313e98c` |

### Defects found in existing code

Recorded here because each one is a correctness issue, not a preference:

1. **`Notifier::on_formistic()` created a booking for every captured submission**,
   of any form, with no idempotency guard. A replayed hook produced duplicate
   inquiries and duplicate guest email. Replaced by `Integration\FormisticIngestion`.
2. **No schema upgrade path.** `wpistic_tm_db_version` was written on activation
   but never read, so an already-activated site would never receive new tables.
   `Plugin::maybe_upgrade()` added.
3. **Tours archive filters were decorative.** The parent rendered seven filter
   controls, but no `pre_get_posts` or `tax_query` existed anywhere in the theme,
   so selecting a filter reloaded the same unfiltered list. Now wired.
4. **Homepage sections read from hard-coded sample arrays** (`inc/sample-data.php`)
   rather than the Tour Manager CPTs, so editing a tour in the admin did not
   change the homepage. **Fixed** — the homepage now reads the CPTs and falls
   back to samples only while they are empty.
5. **Tour card links pointed at `/tours/{slug}/`** while the CPT's single rewrite
   is `/tour/{slug}/`, so every card 404'd. **Fixed** — cards use
   `get_permalink()` and therefore follow the registered rewrite.
6. **Export artifacts in the parent theme.** 13 `_preview-*.html` files ship
   inside the theme directory. Left in place — they are pre-existing and outside
   this change's scope — but they should not reach production. Open item.

## Reference-only material, deliberately not shipped

- Embedded `<script>` blocks and remote dependencies in the reference HTML
- Gradient placeholder imagery standing in for unshot photography
- Demo/sample data from the reference pages
- Copied page markup and HTML export artifacts
- The Guns 2 Ammo demo seed inside Formistic (loaded but never invoked)
- The three testimonial quotes in the design captures — real, verified guest
  reviews must be confirmed by the owner before any of them is published

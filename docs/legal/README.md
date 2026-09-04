# Legal pages — review & publishing workflow

**Why this folder exists:** the client asked for "legal pages and full detail terms and policy on site" as a priority, because visitors keep asking questions the current pages do not answer. The live legal pages are thin or, in one case, still WordPress boilerplate.

**Rule (from `docs/content-import-guide.md`):** Privacy, Terms and Cancellation pages need legal sign-off before they are published. Do not publish any file in this folder without owner approval.

## Current live state (checked 2026-09-04)

| Page | URL | State |
|---|---|---|
| Terms & Conditions | `/terms-and-conditions/` | Live, very thin (~300 words of content): Acceptance, Bookings, Traveler responsibilities, Liability, Changes. Missing: payments, changes/refunds, insurance, health & conduct, complaints, governing law. |
| Privacy Policy | `/privacy-policy/` | **Live, but it is the default WordPress boilerplate** — talks about comments, Gravatar and user-login cookies, none of which this site uses publicly. Says nothing about Build My Trip / Contact / Travel Agent forms, WhatsApp correspondence, booking records, or payment data. Highest-priority replacement. |
| Booking & Cancellation Policy | `/booking-cancellation-policy/` | Live, thin. States 30+ days fully refundable less non-recoverable costs; the "within 30 days sliding scale" is "set out in your itinerary" and is not published anywhere — the direct cause of repeated guest questions. |
| Cookie Policy | `/cookie-policy/` | Live, brief but adequate for now. |
| Disclaimer | `/disclaimer/` | Live, brief but adequate for now. |

## Drafts in this folder

| File | Target page | Action |
|---|---|---|
| `terms-and-conditions.md` | `/terms-and-conditions/` | Replace content with the full version |
| `privacy-policy.md` | `/privacy-policy/` | Replace boilerplate with a policy matching what the site actually collects |
| `booking-cancellation-policy.md` | `/booking-cancellation-policy/` | Replace with the version that publishes the sliding scale |

Every `[TO CONFIRM]` marker is a business decision for the owner (deposit %, tiers, retention periods, insurance requirement, payment provider name). Resolve all of them before publishing. If the owner has already sent approved legal text (the message "can you add Term and policy that sent you early"), merge that text first — the sent version wins over these drafts.

## Publishing steps (WordPress admin)

1. Owner reviews each draft, resolves every `[TO CONFIRM]`, and merges any previously sent legal text.
2. WP admin → Pages → open the target page by slug → replace the content → update the visible "Last updated" date → **Publish** (the three pages already exist live, so this is an update, not a new page).
3. Footer links already point to `/terms-and-conditions/`, `/privacy-policy/`, `/booking-cancellation-policy/`, `/cookie-policy/`, `/disclaimer/` — no menu changes needed.
4. Run `php scripts/brand-lint.php` — if it flags legal text for constructions the brand rules ban, record the exception with the owner rather than weakening a legal clause.
5. Add the three pages to the XML sitemap if not already present (they appear in the sitemap already, so no action expected).
6. After publishing, link the pages from the inquiry forms' success message ("Read our Booking & Cancellation Policy") — this is what will cut the repeated guest questions.

## What still needs the owner's input

- Exact deposit % and balance-due date (drafts propose 30% / 30 days, matching the operations console default).
- The 15–29 day and 1–14 day cancellation tiers (draft proposes 50% / 25% less non-recoverable costs).
- Whether travel insurance is mandatory or recommended.
- Name of the payment provider and analytics tools, for the privacy policy.
- Retention periods (drafts propose defaults).
- Any previously sent legal text that should replace or merge into these drafts.

# Formistic ↔ Tour Manager integration

Brother Tours runs its own configured copy of **Formistic**, WordPressistic's
form-capture plugin, at `plugins/formistic/`. `plugins/formistic/UPSTREAM.md`
records the exact upstream commit, licence and every local change. This
document explains the ownership boundary between Formistic and Tour Manager,
why the fork is shaped the way it is, and how to work on it safely.

## Repository boundary

The standalone Formistic repository is **never modified by this project** —
not its branches, tags, remotes, packages or release files. The relationship
is a one-way copy.

The copy is a plain copy. Not a symlink, not a git submodule, not a Composer
path repository, not a dependency on a sibling checkout. Any of those would
make a Brother Tours deployment depend on a second repository being present
and at the right revision, which is exactly the coupling the client build
must avoid.

## Naming: Formistic, not a renamed fork

As of v2.0.0 the plugin presents as **Formistic**, authored by
**WordPressistic** — matching the upstream product identity exactly, rather
than the "Brother Tours Forms" rebrand used before v2.0.0. This site runs
WordPressistic's own product, configured for one client; it is not a
white-labeled fork with a different product name. Customer-facing surfaces
(the five forms guests fill in, the acknowledgement emails, the internal
alert emails) stay Brother Tours branded regardless — only the plugin's own
admin identity (menu label, plugin-list entry, settings screen headers)
reads "Formistic".

## Only one Formistic may load

Both plugins declare the same classes, constants and database tables. If both
were active, the second to load would fatal on class redeclaration — before
WordPress could render any notice, taking the site down.

The bootstrap therefore checks `WPISTIC_FORMISTIC_VERSION` before *any*
`require_once`. If it is already defined, the bootstrap returns immediately and
registers an admin notice telling the operator to deactivate the other
Formistic plugin. Whichever plugin loads first wins; the site stays up either
way.

**This site must activate only `plugins/formistic/`.**

## Compatibility surface

Tour Manager consumes these symbols. They are preserved exactly as upstream
defines them:

| Symbol | Used by |
|---|---|
| `Wpistic_Formistic_Database` (`::get_submission()`) | `Integration\FormisticIngestion` |
| `Wpistic_Formistic_Capture` | `Notifications\Notifier` |
| `Wpistic_Formistic_Capture::send_internal()` | `Notifications\Notifier::send()` |
| `wpistic_formistic_submission_captured` (action) | `Integration\FormisticIngestion::ingest()` |

Renaming any of them requires updating every consumer in this repository in the
same commit and shipping a compatibility adapter. There is no reason to.

## Fork strategy: additive, not invasive — with one deliberate exception

Upstream files under `includes/class-formistic-*.php` are left **byte-identical**
to the source commit wherever possible. Brother Tours behavior lives in two
additive files plus a rewritten bootstrap:

| File | Role |
|---|---|
| `formistic.php` | Bootstrap: branding, duplicate guard, require list, activation |
| `includes/class-formistic-bt-branding.php` | Admin relabel, sender/Reply-To, capture field aliases |
| `includes/class-formistic-bt-forms.php` | The five form definitions and their idempotent seeder |

Everything in those two `-bt-` files works through hooks and filters. That is
what keeps a future upstream merge mechanical rather than a manual
reconciliation of twenty conflicted files.

**The one exception is the v2.0.0 G2A removal.** The upstream commit this
fork was copied from (`313e98c`) carried demo content and a capture
integration for a different WordPressistic client (a firearms retailer,
internally "G2A") — see `plugins/formistic/UPSTREAM.md` section "G2A removal
(v2.0.0)" for the exact list of what was deleted and why. That removal edits
upstream files directly, because the code being removed belongs to another
client and must not ship at all — additive layering only makes sense for
things Brother Tours *adds*, not things a future merge would otherwise
silently reintroduce. A future upstream diff touching those same lines will
conflict; resolve by keeping the removal, never by reintroducing G2A code.

## Email

The plugin does not hard-code an address or any credential.

| Option | Default |
|---|---|
| `brother_tours_from_email` | `enquiry@brothertours.com` |
| `brother_tours_from_name` | Site name |
| `brother_tours_reply_to` | Falls back to the sender address |

The `wp_mail_from` / `wp_mail_from_name` / `wp_mail` filters are **scoped to
this plugin's own mail**, gated on `Wpistic_Formistic_Capture::$sending_internal`.
Rewriting the sender for every `wp_mail()` on the site would silently change the
From address of core password resets and unrelated plugin notifications — a
common and hard-to-diagnose bug.

`wp_mail()` is a fallback, not the delivery plan. Configure a transactional
provider, and treat SPF, DKIM and DMARC as launch checklist items — see
`docs/launch-checklist.md`. Nothing in this repository can make a domain
deliverable on its own.

## The five forms

Seeded idempotently and fill-only: a form is created once, tagged with post meta
`_brother_tours_form`, and never rewritten. Editors own the content afterwards.

| Slug | Title | Type | Creates an inquiry? | Destination |
|---|---|---|---|---|
| `build-my-trip` | Build My Trip | contact | Yes | Tour Manager inquiry + Tourflows |
| `contact` | Contact | contact | Yes | Tour Manager inquiry + Tourflows |
| `newsletter` | Newsletter | newsletter | **No** | Formistic list only |
| `travel-agent` | Travel Agent | contact | Yes | Tour Manager agent inquiry + Tourflows |
| `request-availability` | Request Tour Availability | contact | Yes, carries `tour_id`/`tour_title` | Tour Manager inquiry (`type: booking`) + Tourflows |

**Routing is keyed on the slug meta, never the form title.** An editor may rename
"Build My Trip" without breaking ingestion. Conversely, deleting and recreating a
form loses the tag — see `docs/content-import-guide.md`.

Newsletter's exclusion is structural rather than a rule someone has to remember:
upstream's submit handler subscribes the address and returns *before*
`Capture::store()`, so it never fires `wpistic_formistic_submission_captured` and
cannot reach the ingestion adapter at all.

### Request Tour Availability — a second, deliberate entry point

Tour Manager's own `CaptureController` booking widget (`[wpistic_booking_widget]`)
already owns the primary "Request Availability" call to action on a tour
detail page, because it needs `tour_id`, departure selection, and leads
directly into the deposit/payment workflow that Formistic has no reason to
know about.

The `request-availability` Formistic form exists alongside it, not instead of
it, for placements where the full booking widget doesn't fit — an Elementor
widget, a destination page, a lighter-weight "ask about this tour" placement.
It carries the tour's id and title in two hidden fields, populated per
placement by `Wpistic_Formistic_BT_Forms::render_request_availability( $tour_id, $tour_title )`
(not by the form's own static definition, which cannot vary per page — see
that method's docblock for how the substitution works and why it was tested
against the actual rendered markup rather than assumed correct).

**Both paths converge on the same single-ingestion guarantee.** The booking
widget posts directly to `CaptureController`, which creates the booking
itself. The Formistic form posts through Formistic's own pipeline and is
picked up by `Integration\FormisticIngestion`, which creates the booking
from *its* side. They are two different HTTP endpoints handling two
different submissions — a guest who fills in one does not also trigger the
other — so there is no scenario where a single visitor action produces two
bookings. Do not add a bridge between them; the separation is what makes the
"exactly once" guarantee provable by inspection rather than by testing every
interleaving.

## What Formistic owns, and what it does not

Formistic owns form definitions and fields, front-end rendering and error states,
server-side validation, nonces, the honeypot, rate limiting and spam protection,
consent capture, submission storage, audit history, CSV export, the searchable
submission view, branded email templates and delivery, and the acknowledgement
flow.

Formistic does **not** own tour data, destinations, experiences, departures,
availability, pricing, deposits, payments, the Tourflows dispatch, or
operational reporting. Those belong to Tour Manager. The boundary is the
`wpistic_formistic_submission_captured` action, and
`Integration\FormisticIngestion` is the only thing allowed to cross it —
nothing else may listen to that action and create a booking. See
`Integration\FormisticIngestion`'s class docblock for the three mechanisms
(routing by stable slug, a database-backed idempotency claim, single-fire
dispatch) that make "exactly one inquiry, exactly one Tourflows event per
submission" a guarantee rather than a hope.

## Merging a future upstream release

1. Fetch the new upstream tag and diff it against `313e98c` (recorded in
   `UPSTREAM.md`).
2. Apply that diff to `includes/class-formistic-*.php` and `assets/`. Expect
   a conflict where the v2.0.0 G2A removal touched upstream files directly
   (see "one deliberate exception" above) — resolve by keeping the removal.
3. Re-apply by hand only to `formistic.php`. Re-check: the duplicate guard
   still runs before every `require_once`; the activation hook still calls
   the Brother Tours seeder (`Wpistic_Formistic_BT_Forms::seed()`).
4. Leave the two `class-formistic-bt-*.php` files alone.
5. Confirm the compatibility surface still exists — grep for the four symbols
   in the table above.
6. Update the version table in `UPSTREAM.md` to the new upstream baseline and
   this install's resulting version.
7. Run `php scripts/brand-lint.php` and `sh scripts/release-check.sh`.
8. Activate on a staging database with `WP_DEBUG` on and submit all five forms.

# The Brother Tours Formistic fork

Brother Tours runs its own copy of Formistic at
`plugins/formistic/`, presented in the admin as **Brother Tours
Forms**.

`plugins/formistic/UPSTREAM.md` records the exact upstream commit,
licence and every local change. This document explains *why* the fork is shaped
the way it is, and how to work on it safely.

## Repository boundary

The standalone Formistic repository is **never modified by this project** — not
its branches, tags, remotes, packages or release files. The relationship is a
one-way copy.

The copy is a plain copy. Not a symlink, not a git submodule, not a Composer
path repository, not a dependency on a sibling checkout. Any of those would make
a Brother Tours deployment depend on a second repository being present and at
the right revision, which is exactly the coupling the client build must avoid.

## Only one Formistic may load

Both plugins declare the same classes, constants and database tables. If both
were active, the second to load would fatal on class redeclaration — before
WordPress could render any notice, taking the site down.

The fork's bootstrap therefore checks `WPISTIC_FORMISTIC_VERSION` before *any*
`require_once`. If it is already defined, the bootstrap returns immediately and
registers an admin notice telling the operator to deactivate the standalone
plugin. Whichever plugin loads first wins; the site stays up either way.

The sentinel works in both directions and regardless of plugin load order,
because both bootstraps define that constant.

**This site must activate only the fork.**

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

Note the class names already carry the `Wpistic_` prefix upstream, so no aliasing
was needed to satisfy Tour Manager.

## Fork strategy: additive, not invasive

Upstream files under `includes/class-formistic-*.php` are left **byte-identical**
to the source commit. Brother Tours behavior lives in two new files plus a
rewritten bootstrap:

| File | Role |
|---|---|
| `formistic.php` | Rewritten bootstrap: branding, duplicate guard, require list, activation |
| `includes/class-formistic-bt-branding.php` | Admin relabel, sender/Reply-To, capture field aliases |
| `includes/class-formistic-bt-forms.php` | The four form definitions and their idempotent seeder |

Everything in those two new files works through hooks and filters. That is what
keeps a future upstream merge mechanical rather than a manual reconciliation of
twenty conflicted files.

### One deliberate exception

`includes/class-formistic-g2a-defaults.php` is another client's demo seed. It is
still **loaded**, because `Wpistic_Formistic_Plugin::boot()` instantiates the
class directly and `class-formistic-settings.php` references its `ACTION`
constant — removing the file would fatal. Only its *seeding* is skipped: the
activation hook calls `Wpistic_Formistic_BT_Forms::seed()` instead, so no other
client's demo content is ever written to a Brother Tours database.

Deleting the file would require editing two upstream files, which is exactly the
coupling this strategy avoids. An unloaded seeding path costs nothing.

## Email

The fork does not hard-code an address or any credential.

| Option | Default |
|---|---|
| `brother_tours_from_email` | `enquiry@brothertours.com` |
| `brother_tours_from_name` | Site name |
| `brother_tours_reply_to` | Falls back to the sender address |

The `wp_mail_from` / `wp_mail_from_name` / `wp_mail` filters are **scoped to this
plugin's own mail**, gated on `Wpistic_Formistic_Capture::$sending_internal`.
Rewriting the sender for every `wp_mail()` on the site would silently change the
From address of core password resets and unrelated plugin notifications — a
common and hard-to-diagnose bug.

`wp_mail()` is a fallback, not the delivery plan. Configure a transactional
provider, and treat SPF, DKIM and DMARC as launch checklist items — see
`docs/launch-checklist.md`. Nothing in this repository can make a domain
deliverable on its own.

## The four forms

Seeded idempotently and fill-only: a form is created once, tagged with post meta
`_brother_tours_form`, and never rewritten. Editors own the content afterwards.

| Slug | Title | Type | Creates an inquiry? | Destination |
|---|---|---|---|---|
| `build-my-trip` | Build My Trip | contact | Yes | Tour Manager inquiry + Tourflows |
| `contact` | Contact | contact | Yes | Tour Manager inquiry + Tourflows |
| `newsletter` | Newsletter | newsletter | **No** | Formistic list only |
| `travel-agent` | Travel Agent | contact | Yes | Tour Manager agent inquiry + Tourflows |

**Routing is keyed on the slug meta, never the form title.** An editor may rename
"Build My Trip" without breaking ingestion. Conversely, deleting and recreating a
form loses the tag — see `docs/content-import-guide.md`.

Newsletter's exclusion is structural rather than a rule someone has to remember:
upstream's submit handler subscribes the address and returns *before*
`Capture::store()`, so it never fires `wpistic_formistic_submission_captured` and
cannot reach the ingestion adapter at all.

## What Formistic owns, and what it does not

Formistic owns form definitions and fields, front-end rendering and error states,
server-side validation, nonces, the honeypot, rate limiting and spam protection,
consent capture, submission storage, audit history, CSV export, the searchable
submission view, branded email templates and delivery, and the acknowledgement
flow.

Formistic does **not** own tour data, departures, pricing, deposits, payments or
the Tourflows dispatch. Those belong to Tour Manager. The boundary is the
`wpistic_formistic_submission_captured` action, and
`Integration\FormisticIngestion` is the only thing allowed to cross it.

## Merging a future upstream release

1. Fetch the new upstream tag and diff it against `313e98c` (recorded in
   `UPSTREAM.md`).
2. Apply that diff to `includes/class-formistic-*.php` and `assets/`. These are
   unmodified, so it should be clean.
3. Re-apply by hand only to `formistic.php`. Re-check three things:
   the duplicate guard still runs before every `require_once`; the require list
   still includes `class-formistic-g2a-defaults.php`; the activation hook still
   calls the Brother Tours seeder.
4. Leave the two `class-formistic-bt-*.php` files alone.
5. Confirm the compatibility surface still exists — grep for the four symbols in
   the table above.
6. Bump the fork suffix (`2.1.0-bt.1` → `-bt.2`, or to the new upstream version)
   and update `UPSTREAM.md`.
7. Run `php scripts/brand-lint.php` and `sh scripts/release-check.sh`.
8. Activate on a staging database with `WP_DEBUG` on and submit all four forms.

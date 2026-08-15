# Upgrading to 2.0.0

This build has never been deployed anywhere — there is no live site to
upgrade yet. This document is written for the first real deployment and for
any future upgrade of a site already running an earlier version of this
repository's code.

## What changed, in order of how much it affects you

### 1. Formistic renamed and moved

`plugins/brother-tours-formistic/` → `plugins/formistic/`,
`brother-tours-formistic.php` → `formistic.php`. **This is a directory and
file rename, not a new plugin.** WordPress tracks an active plugin by its
basename (`folder/file.php`); a rename means WordPress will show it as
deactivated after you deploy the new files, even though the site was
running fine a moment before.

**Deployment steps, in order:**

1. Deploy the new files (they can sit alongside the old plugin directory
   briefly, or replace it — either is safe since only the directory name
   changed).
2. Reactivate the plugin under its new location: **Plugins → Formistic →
   Activate**. Do this promptly — until you do, no Formistic forms will
   render and no submissions can be captured.
3. Remove the old `plugins/brother-tours-formistic/` directory once you've
   confirmed the new one is active and forms work.

**Nothing else changes on reactivation.** All `Wpistic_Formistic_*` class
names, the `wpistic_formistic_submission_captured` action, database table
names, and every stored option are unchanged — this was a presentation
rename, not a data migration. Existing forms, submissions, and settings
survive untouched.

### 2. G2A code removed from Formistic

If your Formistic install ever had its "Seed Guns 2 Ammo defaults" action
run (Settings → a button that filled the AI knowledge base with another
client's business facts), that seeded content stays in your database — it
is not automatically cleaned up, since it lives in ordinary options an
operator may have since edited. If you know that button was clicked on
this install, review Settings → AI for any G2A-sourced text and clear it
manually. See `plugins/formistic/UPSTREAM.md`, "G2A removal (v2.0.0)" for
the exact list of what code was removed.

### 3. `plugins/wpistic-crm` removed

The unrelated Guns2Ammo CRM plugin is no longer part of this repository.
If it was ever installed on your site from an earlier deployment of this
repo, deactivate and delete it manually — it was never wired into any
Brother Tours workflow (confirmed: zero cross-references from Tour Manager
or Formistic), so removing it has no effect on tours, bookings, or forms.

### 4. A fifth Formistic form

`request-availability` is seeded alongside the existing four on next
activation/upgrade (`Wpistic_Formistic_BT_Forms::maybe_seed()` runs on
`init` and is idempotent — it only creates what doesn't already exist). No
action needed; it will simply appear.

### 5. Tour Manager admin replaced

`Admin\Portal` (dashboard + bookings list + booking detail in one class) is
replaced by `Admin\Dashboard`, `Admin\Bookings`, and `Admin\BookingDetail`.
**Menu URLs are unchanged** (`admin.php?page=wpistic-tour-manager`,
`admin.php?page=wpistic-tm-bookings&view={id}`) — any bookmark or link to
the old screens keeps working. Every action Portal offered (workflow
status, assignment, notes, lifecycle transitions, deposit/balance link
generation, CSV export) is carried over unchanged in behavior.

**New**: a `wpistic_dep_date` field on each Departure post (native date
input, replacing a plain text field) drives the new "Upcoming departures"
KPI and list. **Existing departures need this field filled in** — the
dashboard correctly shows zero departures until you do, rather than
guessing.

### 6. No database schema change

Neither `WPISTIC_FORMISTIC_DB_VERSION` nor `WPISTIC_TM_DB_VERSION` changed
in this release. The new "connection dispatch history" feature reuses the
existing `wpistic_audit_log` table via a new WordPress action
(`wpistic_tm_connection_dispatched`) rather than a schema migration — see
`docs/tourflows-integration.md`. **Nothing to migrate.**

### 7. Frontend and admin now support light/dark mode genuinely

Purely a visual change — no data implications. See
`docs/light-dark-mode.md`. If you customized `brand-tokens.css` on a prior
deployment, re-apply those customizations against the new file (it was
substantially rewritten to add the light-mode palette).

### 8. Elementor integration added

Optional and additive. If Elementor is not installed, nothing changes. If
it is, `wpistic_tour`/`wpistic_destination`/`wpistic_experience` become
newly Elementor-editable (this never removes editability from `page`/`post`
or any type you'd already enabled) and 18 new widgets appear under a
"Brother Tours" category. See `docs/elementor-guide.md`.

## Standard upgrade procedure

1. Back up the database and `wp-content`. Verify the backup restores.
2. Deploy to staging first.
3. Update files in this order: Formistic (reactivate immediately per step
   1 above), Tour Manager, both themes.
4. Confirm no PHP notices/warnings/fatals with `WP_DEBUG` on.
5. Work through `docs/launch-checklist.md` section 10 (the v2.0.0 addendum)
   — it lists exactly what to re-verify.
6. Fill in `wpistic_dep_date` on existing departures if you want the
   "Upcoming departures" KPI populated immediately.
7. Deploy to production; repeat the smoke tests.

## Rollback

See `docs/rollback.md`. Nothing in this release drops, renames, or
rewrites existing data, which is why rollback stays primarily a code
operation (`git revert`) plus reactivating Formistic under its old
directory name if you revert past the rename.

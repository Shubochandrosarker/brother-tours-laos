# Rollback

How to undo this change safely, at three levels of severity. Read the whole
section before running anything.

## What this change touched

| Layer | Change | Reversible by |
|---|---|---|
| Repository layout | Themes/plugins moved into `themes/` and `plugins/` | Git revert |
| Code | Formistic (renamed from "Brother Tours Forms" in v2.0.0), ingestion adapter, theme design system, Elementor integration, Tour Manager dashboard | Git revert |
| Removed | `plugins/wpistic-crm` (unrelated G2A CRM); G2A code paths inside Formistic; `Admin\Portal` (Tour Manager, superseded by Dashboard/Bookings/BookingDetail) | Git revert restores them if truly needed, but see the note below — none of this should be restored |
| Database schema | **One new table** `{prefix}wpistic_form_ingestions`. No schema change in v2.0.0. | Left in place; harmless |
| Database rows | Seeded pages, five forms (four before v2.0.0), one disabled connection profile | Manual, see below |
| Options | `wpistic_tm_db_version`; new `brother_tours_*` options | Manual, see below |

Nothing is dropped, renamed or rewritten by any *data* operation. No
existing table, column, row, post, option or slug is destroyed — which is
why rollback is mostly a code operation. The one true rename is the
Formistic plugin directory itself (`brother-tours-formistic/` →
`formistic/` in v2.0.0) — see the caveat below, that one needs a manual
reactivation step either direction.

**Do not restore `plugins/wpistic-crm` or any G2A code as part of a
rollback**, even though `git revert` is technically capable of resurrecting
deleted files. Those removals were a correctness fix, not a feature this
site ever needed — reverting past them would reintroduce another client's
business content and a live defect (a reachable admin action that seeded
G2A business facts into the AI knowledge base). If a rollback target
predates the G2A removal, cherry-pick around it or re-apply the removal
immediately after reverting.

## Level 1 — revert the code

The safe default. Restores the previous behavior without touching data.

```sh
git revert --no-commit <first-commit>..<last-commit>
git commit -m "Revert Brother Tours build"
git push -u origin <branch>
```

Then, on the site:

1. Deactivate **Formistic** (or **Brother Tours Forms**, if reverting past
   the v2.0.0 rename — check which directory name is on disk after the
   revert and match the plugin list entry to it).
2. Reactivate the standalone Formistic plugin if that is what you are going back
   to. Both may be *installed* at once — the duplicate guard keeps whichever
   loads second dormant — but only one should be **active**.
3. Flush permalinks: **Settings → Permalinks → Save**.

The `wpistic_form_ingestions` table stays behind. It is inert once the ingestion
class is gone: nothing reads or writes it, and it holds no guest data beyond
submission ids. Leave it — dropping it would make a re-apply lose its
idempotency history and risk duplicate bookings on replayed hooks.

### One caveat before reverting

Reverting all the way past the single-ingestion adapter's introduction
restores `Notifier::on_formistic()`, which creates a booking for **every**
captured submission with no idempotency guard. If you revert that far
while the Formistic forms remain live, expect duplicate inquiries. Either
deactivate Formistic in the same maintenance window, or accept and
de-duplicate afterwards. (Reverting only the v2.0.0 changes, and stopping
before that earlier point, does not hit this caveat — `FormisticIngestion`
predates v2.0.0 and stays in place either way.)

### If reverting past the v2.0.0 Formistic rename specifically

WordPress tracks an active plugin by its file path (`folder/file.php`).
Renaming `plugins/formistic/formistic.php` back to
`plugins/brother-tours-formistic/brother-tours-formistic.php` via a code
revert means WordPress will show the plugin as **deactivated** the moment
the old files land, even though nothing else changed — reactivate it
promptly, in the plugins list, under its restored name. Forms, submissions
and settings are unaffected; only the activation record needs a manual
touch.

## Level 2 — undo the seeded content

Only if you want the site returned to its pre-seed state. All of this is
reversible by hand because the seeder never overwrites anything.

**Pages.** Seeder-created pages carry post meta `_brother_tours_route`. Find them
under Pages, or:

```sql
SELECT p.ID, p.post_name, p.post_status
FROM wp_posts p
JOIN wp_postmeta m ON m.post_id = p.ID
WHERE m.meta_key = '_brother_tours_route';
```

Move them to Trash rather than deleting permanently. **Check each one first** —
if an editor has written real copy into a page since the seed, that copy is not
recoverable from this repository.

**Forms.** Formistic forms carry post meta `_brother_tours_form`. Same approach,
same caution. Deleting a form does **not** delete its submissions; those live in
the Formistic submissions table and are unaffected.

**Tourflows connection profile.** Delete the row named `Tourflows` on
**Tour Manager → Connections**. It ships disabled with an empty endpoint, so if
nobody configured it, deleting it changes nothing.

**Posts page.** The seeder sets `page_for_posts` to the Journal page only when it
was previously unset. To undo: **Settings → Reading**.

**Options.** Safe to delete if you want a clean slate:

```
brother_tours_from_email
brother_tours_from_name
brother_tours_reply_to
brother_tours_forms_seeded_at
brother_tours_site_seeded_at
```

Leave `wpistic_tm_db_version` alone. Lowering it makes the upgrade routine
re-run `dbDelta`, which is additive and harmless but pointless.

## Level 3 — full restore

If the site is broken in a way the above does not explain, restore the most
recent pre-deployment backup — database **and** `wp-content` together. A database
restored without matching files (or the reverse) leaves the schema and the code
disagreeing about which tables exist.

Take that backup before deploying. Verify it restores. An untested backup is not
a rollback plan.

## Recovering from a fatal error

If the site white-screens after activating Brother Tours Forms, the likeliest
cause is two Formistic codebases loading. The duplicate guard prevents this, but
if the guard itself was edited or an older copy is present:

```sh
# Rename the plugin directory over SSH/SFTP; WordPress deactivates it on the
# next request and the admin becomes reachable again.
mv wp-content/plugins/formistic wp-content/plugins/formistic.off
```

Then reach the admin, deactivate the other Formistic, rename the directory back,
and activate one of them.

## Verifying a rollback worked

1. Front end loads; no PHP notices with `WP_DEBUG` on.
2. **Tour Manager → Bookings & Inquiries** opens and lists existing bookings.
3. Submit one form and confirm exactly one new inquiry — not zero, not two.
4. Existing bookings still show their references, transactions and audit trail.
5. Permalinks resolve; no unexpected 404s on `/tours/` or `/destinations/`.

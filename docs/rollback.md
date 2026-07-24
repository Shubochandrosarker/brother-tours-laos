# Rollback

How to undo this change safely, at three levels of severity. Read the whole
section before running anything.

## What this change touched

| Layer | Change | Reversible by |
|---|---|---|
| Repository layout | Themes/plugins moved into `themes/` and `plugins/` | Git revert |
| Code | Formistic fork, ingestion adapter, theme design system | Git revert |
| Database schema | **One new table** `{prefix}wpistic_form_ingestions` | Left in place; harmless |
| Database rows | Seeded pages, four forms, one disabled connection profile | Manual, see below |
| Options | `wpistic_tm_db_version` bumped to `1.1.0`; new `brother_tours_*` options | Manual, see below |

Nothing is dropped, renamed or rewritten. No existing table, column, row, post,
option or slug is destroyed by this change — which is why rollback is mostly a
code operation.

## Level 1 — revert the code

The safe default. Restores the previous behavior without touching data.

```sh
git revert --no-commit <first-commit>..<last-commit>
git commit -m "Revert Brother Tours build"
git push -u origin <branch>
```

Then, on the site:

1. Deactivate **Brother Tours Forms**.
2. Reactivate the standalone Formistic plugin if that is what you are going back
   to. Both may be *installed* at once — the duplicate guard keeps whichever
   loads second dormant — but only one should be **active**.
3. Flush permalinks: **Settings → Permalinks → Save**.

The `wpistic_form_ingestions` table stays behind. It is inert once the ingestion
class is gone: nothing reads or writes it, and it holds no guest data beyond
submission ids. Leave it — dropping it would make a re-apply lose its
idempotency history and risk duplicate bookings on replayed hooks.

### One caveat before reverting

Reverting restores `Notifier::on_formistic()`, which creates a booking for
**every** captured submission with no idempotency guard. If you revert while the
four Brother Tours forms remain live, expect duplicate inquiries. Either
deactivate Brother Tours Forms in the same maintenance window, or accept and
de-duplicate afterwards.

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

# Upstream baseline — Brother Tours Formistic fork

This plugin is a **copy** of the standalone Formistic plugin, vendored into the
Brother Tours repository. It is not a submodule, a symlink, a Composer path
repository, or a dependency on a sibling checkout.

## Source

| Field | Value |
|---|---|
| Source repository | `https://github.com/Shubochandrosarker/formistic` |
| Source path in that repo | `formistic/` |
| Source commit | `313e98c65e735ca3294a744950eee061ecebf169` |
| Source ref at copy time | `main` (merge of PR #8, "Crossmatch from monorepo: formistic 2.1.0") |
| Upstream version | 2.1.0 |
| Fork version | 2.1.0-bt.1 |
| Copy date | 2026-07-24 |
| Licence | GPL-2.0+ (unchanged; `LICENSE` copied verbatim) |
| Upstream author | Wordpressistic Organization |

The standalone Formistic repository is **not modified by this project**. No
branch, tag, remote, package, or release file in that repository is touched.
Changes below exist only inside this directory.

## Compatibility surface — do not rename

WPistic Tour Manager consumes these symbols. They are preserved exactly as
upstream defines them, and the fork adds no wrappers around them:

- `Wpistic_Formistic_Database` (and `::get_submission()`)
- `Wpistic_Formistic_Capture`
- `Wpistic_Formistic_Capture::send_internal( $to, $subject, $body, $headers, $attach )`
- the action `wpistic_formistic_submission_captured( $id, $form_name, $fields )`

Renaming any of them requires updating every consumer in this repository in the
same commit and shipping a compatibility adapter. See
`docs/formistic-fork.md`.

## Brother Tours changes

The fork's guiding rule is **additive, hook-based customization**. Upstream
files under `includes/class-formistic-*.php` are left byte-identical to the
source commit wherever possible, so a future upstream merge stays mechanical.

### 1. Renamed bootstrap — `formistic.php` → `brother-tours-formistic.php`

Rewritten, not copied. Differences from upstream:

- Plugin header presents the plugin as **Brother Tours Forms**, version
  `2.1.0-bt.1`, authored by Brother Tours Sole Co., Ltd. Text domain stays
  `formistic` so the upstream `.pot` and every existing `__()` call keep working.
- **Duplicate-plugin guard** added ahead of every `require_once`. If
  `WPISTIC_FORMISTIC_VERSION` is already defined — i.e. a standalone Formistic is
  also active — the bootstrap returns early and registers an admin notice
  instead of loading. Without this, the second plugin to load would fatal on
  class redeclaration before WordPress could render any warning.
- New constant `BROTHER_TOURS_FORMISTIC` marks this install as the fork, so Tour
  Manager and the release checks can tell it apart from stock Formistic.
- Activation seeds the Brother Tours form set
  (`Wpistic_Formistic_BT_Forms::seed()`) instead of the upstream Guns 2 Ammo
  demo data (`Wpistic_Formistic_G2A_Defaults::seed()`).
- Boots `Wpistic_Formistic_BT_Branding` alongside the upstream plugin class.

`includes/class-formistic-g2a-defaults.php` is still **loaded**, because
`Wpistic_Formistic_Plugin::boot()` instantiates the class directly and
`class-formistic-settings.php` references its `ACTION` constant — omitting the
file would fatal. Only its *seeding* is skipped, so no other client's demo
content is ever written to a Brother Tours database.

### 2. New file — `includes/class-formistic-bt-branding.php`

Additive. Registers no new data and edits no upstream file.

- Relabels the admin menu to **Forms** / **Inbox** under the Brother Tours
  branding, by rewriting the registered `$menu` / `$submenu` arrays on
  `admin_menu` priority 999 (preserving the unread-count bubble markup).
- Notes the fork on the Plugins screen via `plugin_row_meta`.
- Applies a configurable sender and Reply-To to outbound Formistic mail through
  `wp_mail_from`, `wp_mail_from_name` and `wp_mail`. Scoped by
  `Wpistic_Formistic_Capture::$sending_internal` so it rewrites **only this
  plugin's** mail and never hijacks core or third-party notifications. Addresses
  come from the options `brother_tours_from_email`, `brother_tours_from_name`
  and `brother_tours_reply_to` — defaults only, no credentials stored.
- Extends the `wpistic_formistic_field_aliases` filter so capture can normalize
  the Brother Tours labels ("Phone / WhatsApp", "Interests", "Anything else")
  into the submission's sender columns.

### 3. New file — `includes/class-formistic-bt-forms.php`

Additive. Defines the four Brother Tours forms and an **idempotent, fill-only**
seeder: a form is created once, tagged with post meta `_brother_tours_form`, and
never rewritten. Editors own the content from then on.

The `_brother_tours_form` meta value is the stable routing contract used by the
Tour Manager ingestion adapter, so an editor may freely rename a form's title
without breaking inquiry routing.

| Slug | Title | Formistic type | Becomes a Tour Manager inquiry? |
|---|---|---|---|
| `build-my-trip` | Build My Trip | contact | Yes |
| `contact` | Contact | contact | Yes |
| `newsletter` | Newsletter | newsletter | **No** — list entry only |
| `travel-agent` | Travel Agent | contact | Yes |

Newsletter relies on upstream behavior: `Wpistic_Formistic_Forms::handle_submit()`
subscribes the address and returns *before* `Capture::store()`, so it never fires
`wpistic_formistic_submission_captured` and can never create an inquiry record.

## Merging future upstream releases

1. Fetch the new upstream tag and diff it against commit `313e98c` (the baseline
   recorded above).
2. Apply that diff to `includes/class-formistic-*.php` and `assets/`. These files
   are unmodified, so the merge should be clean.
3. Re-apply by hand only to `brother-tours-formistic.php`, which is a rewritten
   bootstrap — re-check the duplicate guard, the require list (including the
   `g2a-defaults` load-bearing include) and the activation hook.
4. Leave `class-formistic-bt-branding.php` and `class-formistic-bt-forms.php`
   alone; they are Brother Tours files with no upstream counterpart.
5. Confirm the compatibility surface above still exists, bump the fork version
   suffix (`-bt.N`), and update the table at the top of this file.

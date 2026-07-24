# Upstream baseline — Formistic (Brother Tours configuration)

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
| This install's version | 2.0.0 |
| Copy date | 2026-07-24 |
| Version normalization | 2026-07-24 — normalized from an interim `2.1.0-bt.1` label to the coordinated suite version `2.0.0`. Confirmed safe: this fork had never been packaged, tagged, or deployed, so there was no `2.1.0-bt.1` install anywhere to be silently downgraded. See `docs/upgrade-to-2.0.0.md`. |
| Licence | GPL-2.0+ (unchanged; `LICENSE` copied verbatim) |
| Upstream author | WordPressistic |

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
The exception is the G2A removal in v2.0.0 (below), which edits upstream files
directly because the code being removed belongs to an unrelated client and
must not ship at all — additive layering does not apply to deletions.

### 1. Renamed bootstrap — `formistic.php` → `formistic.php`

The upstream and this install share the same bootstrap filename
(`formistic.php`) and directory name (`plugins/formistic/`); only the plugin
header and the additions below differ from stock Formistic. Differences from
upstream:

- Plugin header presents the plugin as **Formistic**, version `2.0.0`,
  authored by **WordPressistic** (`https://wordpressistic.com/`) — matching
  upstream's own identity, since this is WordPressistic's product configured
  for one client rather than a renamed fork. Text domain stays `formistic` so
  the upstream `.pot` and every existing `__()` call keep working.
- **Duplicate-plugin guard** added ahead of every `require_once`. If
  `WPISTIC_FORMISTIC_VERSION` is already defined — i.e. a standalone Formistic is
  also active — the bootstrap returns early and registers an admin notice
  instead of loading. Without this, the second plugin to load would fatal on
  class redeclaration before WordPress could render any warning.
- New constant `BROTHER_TOURS_FORMISTIC` marks this install as the Brother
  Tours configuration, so Tour Manager and the release checks can confirm the
  right build is active.
- Activation seeds the Brother Tours form set (`Wpistic_Formistic_BT_Forms::seed()`).
- Boots `Wpistic_Formistic_BT_Branding` alongside the upstream plugin class.

### 2. G2A removal (v2.0.0)

Upstream, as copied at commit `313e98c`, carried demo content and a capture
integration for a *different* WordPressistic client (a firearms retailer,
internally "G2A"). None of it is Brother Tours code and none of it is
reachable from a legitimate Brother Tours workflow, but it was live and
callable — this was a defect, not inert dead code. Removed entirely:

- `includes/class-formistic-g2a-defaults.php` — deleted. Seeded the AI
  knowledge base with hard-coded G2A business facts (address, phone,
  `sales@guns2ammo.com`) and NRA-course/FFL/membership auto-reply templates
  via an admin-clickable "Seed Guns 2 Ammo defaults" button. The button and
  its `admin_post` handler were both reachable by any Brother Tours
  administrator before this removal.
- `class-formistic-capture.php` — removed the `g2a_request` /
  `g2a_reservation` legacy theme-form capture (`capture_theme_form()`), its
  registration gated by the now-deleted `wpistic_formistic_capture_g2a`
  option, and the `g2a_f_*` field-prefix handling. This mirrored form fields
  from a firearms-course booking form (course, preferred date, participant
  count, experience level) that does not exist on Brother Tours.
- `class-formistic-settings.php` — removed the "Guns 2 Ammo defaults" tools
  section, its `g2a_seeded` admin notice, and the "G2A Theme" row on the
  Captures tab.
- `class-formistic-emails.php` — replaced the `g2a_biz()`-based business-NAP
  lookup and the `guns2ammo` template-slug logo fallback with the Brother
  Tours branding layer (`Wpistic_Formistic_BT_Branding::from_name()`) and
  plain options; no other client's function names or template slugs remain.
- `class-formistic-newsletter.php` — dropped the dead `g2a_f_email` fallback
  key from the auto-subscribe field lookup.
- `uninstall.php` — dropped the `wpistic_formistic_capture_g2a` option from
  the cleanup list (the option no longer exists to clean up).

Verify with `rg -n -i "guns.?2.?ammo|g2a|firearm|shooting.?range|waiver|kiosk" plugins/formistic`
— expected: no results outside this file's own history section.

### 3. New file — `includes/class-formistic-bt-branding.php`

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

### 4. New file — `includes/class-formistic-bt-forms.php`

Additive. Defines the five Brother Tours forms and an **idempotent, fill-only**
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
| `request-availability` | Request Tour Availability | contact | Yes — carries `tour_id`/`tour_title` |

Newsletter relies on upstream behavior: `Wpistic_Formistic_Forms::handle_submit()`
subscribes the address and returns *before* `Capture::store()`, so it never fires
`wpistic_formistic_submission_captured` and can never create an inquiry record.

`request-availability` is a *second, Formistic-rendered* entry point for
availability requests, distinct from Tour Manager's own `CaptureController`
booking widget (see `docs/formistic-brother-tours-integration.md` for why both
exist and how duplicate ingestion is prevented).

## Merging future upstream releases

1. Fetch the new upstream tag and diff it against commit `313e98c` (the baseline
   recorded above).
2. Apply that diff to `includes/class-formistic-*.php` and `assets/`. These files
   are unmodified except for the G2A removal in v2.0.0 (section 2 above) — expect
   that removal to conflict with an upstream diff touching the same lines, and
   resolve by keeping the removal (do not reintroduce G2A code).
3. Re-apply by hand only to `formistic.php`, which is a rewritten bootstrap —
   re-check the duplicate guard, the require list, and the activation hook.
4. Leave `class-formistic-bt-branding.php` and `class-formistic-bt-forms.php`
   alone; they are Brother Tours files with no upstream counterpart.
5. Confirm the compatibility surface above still exists, then bump this
   install's version to match the new coordinated suite release and update the
   table at the top of this file.

# Light / dark mode

Two separate token systems, both genuinely dual-mode: the **frontend**
(`themes/brother-tours/assets/css/brand-tokens.css`) and the **admin
dashboard** (`plugins/wpistic-tour-manager/assets/admin/css/dashboard.css`).
They deliberately don't share a palette — the dashboard is
WordPressistic's tool, not the Brother Tours guest-facing site — but both
follow the same discipline: every color pairing is checked against WCAG AA
(4.5:1 for body text, 3:1 for large text and UI-component boundaries)
before shipping, not chosen by eye.

## Frontend

### Before v2.0.0

The mechanism already existed and worked — the parent theme
(`themes/wpistic/`) ships a no-flash inline script, `localStorage`
persistence, a `prefers-color-scheme` fallback, and a keyboard-operable
toggle button. What didn't exist: the Brother Tours child theme's
`brand-tokens.css` set `:root`, `[data-theme="light"]`, and
`[data-theme="dark"]` to *identical* values, so toggling had no visible
effect on this site.

### What v2.0.0 changed

A genuine second palette for light mode, built from the same locked brand
hues (green, gold) rather than a generic swap:

| Token | Dark (default, locked, unchanged) | Light (new) |
|---|---|---|
| `--bg` | `#0E100D` | `#FAF6EC` |
| `--bg-raise` | `#161812` | `#F1E7D2` |
| `--ink` | `#EDE6D3` | `#1E2117` |
| `--ink-soft` | `#B8B09D` | `#4A4839` |
| `--ink-muted` | `#7A7566` | `#6B6656` |
| `--gold` (text/border) | `#C9A96E` | `#835D1E` |
| `--gold-fill` (button backgrounds) | `#C9A96E` | `#C9A050` |
| `--green` | `#1B5E3B` (mode-independent) | |

**Why gold needed two values in light mode but not dark.** No single gold
tone cleared 4.5:1 as small text on paper *and* produced a legible fill
with dark text on top at the same time — darkening it for text-safety
makes it a poor button fill; lightening it for a good fill makes it fail as
text. Dark mode never had this problem (the one existing value already
served both roles), so `--gold-fill` equals `--gold` there and no
component CSS had to change shape — only the two light-mode-specific
component rules that consume `--gold-fill` for a background
(`.btn--primary`, `.wpistic-formistic-form-submit`) needed touching.

**Default mode**: dark, via a `theme_mod_wpistic_default_mode` filter in
the child theme that only overrides when no operator choice has ever been
saved (read the raw `theme_mods` array directly rather than calling
`get_theme_mod()` again — that would re-enter the very filter being
defined and recurse).

**Always-dark surfaces**: the hero band, footer, and the mobile sticky
Build My Trip bar stay dark-grounded in *both* modes — a deliberate
editorial device (full-bleed photography needs a dark overlay for text
legibility regardless of the page's own mode), not a mode leak. These use
fixed values, never the mode-variable tokens.

### Generic semantic aliases

New in v2.0.0, mapped onto the tokens above so new code (Elementor widgets,
future components) has a stable vocabulary that already resolves correctly
in both modes without its own light/dark block:

```
--color-background, --color-surface, --color-surface-elevated,
--color-text, --color-text-muted, --color-border,
--color-primary, --color-primary-fill, --color-primary-hover,
--color-accent, --color-success, --color-warning, --color-danger,
--color-focus
```

### `theme.json`

`themes/brother-tours/theme.json` (previously absent) declares Brother
Tours' own locked palette and font stack for the block editor and
Elementor global colors/typography — before v2.0.0 the child theme
inherited the parent's own navy/clay palette and Cormorant/Manrope fonts
there, a real mismatch with the locked green/gold/Playfair/Crimson design.

### Accessibility

`aria-pressed` added to the toggle button (both the desktop icon toggle
and the mobile text toggle), synced on page load and on every click, so
assistive technology announces the current state correctly — not just
after the first interaction. `prefers-reduced-motion` is respected
(reveal animations and the mode-transition itself both disable).

## Admin dashboard

Self-contained, scoped to `.wpistic-tm-dashboard` so it can never leak into
another plugin's admin screens. A WordPressistic identity distinct from the
frontend: clean white/warm-light in light mode, deep navy in dark mode,
green primary actions, a restrained gold accent for anything Brother
Tours-specific (badges tied to Tourflows/booking status).

| Token | Light | Dark |
|---|---|---|
| `--wtm-bg` | `#F8FAFC` | `#0F172A` |
| `--wtm-surface` | `#FFFFFF` | `#1E293B` |
| `--wtm-text` | `#0F172A` | `#F1F5F9` |
| `--wtm-muted` | `#64748B` | `#94A3B8` |
| `--wtm-primary` | `#15803D` | `#3DDC84` |
| `--wtm-accent` | `#835D1E` | `#C9A96E` |

Defaults to the browser's `prefers-color-scheme`. A signed-in user's
explicit choice (light / dark / auto) is saved to `user_meta` via a
nonce-protected AJAX call (`AdminAssets::ajax_set_theme()`) and rendered
server-side on every subsequent page load — there is no separate no-flash
script needed here, unlike the frontend, because wp-admin pages are never
statically cached the way front-end pages can be, so the server already
knows the right value before the first byte is sent.

## Verifying the contrast values yourself

Every pairing above was computed with the standard WCAG relative-luminance
formula, not estimated. To re-check a pair:

```python
def lum(hexcolor):
    hexcolor = hexcolor.lstrip('#')
    r, g, b = (int(hexcolor[i:i+2], 16) / 255 for i in (0, 2, 4))
    def lin(c): return c/12.92 if c <= 0.03928 else ((c+0.055)/1.055) ** 2.4
    r, g, b = lin(r), lin(g), lin(b)
    return 0.2126*r + 0.7152*g + 0.0722*b

def ratio(c1, c2):
    l1, l2 = lum(c1), lum(c2)
    l1, l2 = max(l1, l2), min(l1, l2)
    return (l1 + 0.05) / (l2 + 0.05)
```

`ratio("#FAF6EC", "#1E2117")` for example returns the light-mode
`--bg`/`--ink` pairing above, ≈15:1 — comfortably past the 4.5:1 minimum
for body text.

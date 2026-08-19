# Popup behaviour tests

Drives the real controller in headless Chromium. `php -l` cannot tell you
whether a modal traps focus or whether the popup waits long enough before
interrupting; this can.

```bash
npm install playwright --no-save
node tests/popup.test.mjs
```

The harness copies `assets/css/bt-resource.css` and `assets/js/bt-resource.js`
next to `tests/harness/index.html` before running — the test does this itself.

Chromium path is pinned to this image's build. Adjust `executablePath` if your
environment differs.

## What it covers

| Group | Assertion |
|---|---|
| Trigger timing | Closed at 2.5s and 7.5s — the legacy 2-second interruption stays gone |
| Engagement gate | An idle visitor is never interrupted; an engaged one sees it after 10s |
| Scroll trigger | Closed at 15%, opens at 55% (threshold 40%) |
| Accessibility | Manual open, focus enters, focus trapped, ESC closes, focus restored |
| Scroll lock | Background locked while open, exact position restored on close |
| Toast | Non-blocking toast after download — never `alert()` |
| Suppression | Auto-opens once, suppressed on return, manual button still works |
| Analytics | `resource_popup_view` / `resource_download` fire; Build My Trip carries `source_resource` |

## Note on `page.click()`

`page.click()` scrolls the target into view first. For scroll-lock assertions
open the popup with `BTResourcePopup.open('manual')` instead, or the harness
moves the page before the lock captures its position and the test measures
Playwright rather than the product.

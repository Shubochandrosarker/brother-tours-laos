# Guns2Ammo CRM — React Dashboard

Vite + React 18 + React Router. Mounted at `#g2a-crm-root` inside WP-Admin.

## Setup

```bash
npm install
npm run build      # → dist/ (with .vite/manifest.json the PHP loader reads)
```

## Dev

`npm run dev` runs Vite standalone — useful for component work but won't talk to the WP REST API unless you set up a proxy. Most of the time, run `npm run build` and reload `wp-admin/admin.php?page=g2a-crm`.

## Boot data

PHP injects a global `window.G2A_CRM_BOOT` containing:
- `restRoot` — e.g. `https://example.com/wp-json/g2a-crm/v1`
- `nonce` — for the `X-WP-Nonce` header
- `capabilities` — `{ view_customers, edit_customers, ... }`
- `currentUser` — `{ id, name }`

`src/api.js` wraps `fetch` and includes the nonce automatically.

## Adding a page

1. New file in `src/pages/`
2. Register the route in `src/App.jsx`
3. Add the nav entry in `src/components/Sidebar.jsx` (with a capability gate)
4. Add API methods in `src/api.js` if needed

Theme variables live in `src/styles.css` under `:root`.

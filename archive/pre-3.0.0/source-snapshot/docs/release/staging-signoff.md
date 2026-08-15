# Staging signoff

Date: 2026-08-15

Decision: **PARTIAL QA PASS — NOT A PRODUCTION SIGNOFF**

Approved staging-only work was executed through guarded Bridgistic operations
with rollback snapshots. The homepage is page 77 and returns 200 anonymously;
active menus resolve to real routes; 37 published tour URLs and 10 published
destination URLs return 200; and published tour/destination summaries are
non-empty.

Open gates:

- staging runtime is Brother Tours/Formistic/Tour Manager 2.5.0 while the
  tracked coordinated source is 2.0.0;
- required local source and Content Studio files are not yet in a reviewed
  commit, so no reproducible production artifact exists;
- the deployed staging theme still emits `/laos-travel-guide/` and
  `/privacy-policy/`, both 404, until the local route patch is deployed;
- the existing Privacy Policy was placeholder text and remains unpublished;
- 29 published tours and 3 published destinations lack trustworthy thumbnails;
- no full Hostinger `wp-content` backup/restore proof is available;
- real outbound email, form delivery, booking/payment, webhook, browser and
  rollback flows remain unproven; and
- staging is publicly reachable even though it emits `noindex,nofollow,noarchive`
  and `robots.txt` blocks `/`.

Production installation and ContentSeeder execution remain blocked.

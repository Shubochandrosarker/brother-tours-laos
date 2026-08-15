# Brother Tours Operations API capability matrix

| Capability | Purpose | Default role |
|---|---|---|
| `bt_manage_content` | Edit tours, destinations, pages and Content Studio settings | Administrator, Editor, Tour Author, Tour Staff, WPistic Travel Manager, WPistic Travel Agent, CRM Marketing |
| `bt_edit_templates` | Change protected visual templates and global patterns | Administrator, Editor, WPistic Travel Manager |
| `bt_view_seo` | Edit portable SEO fields and validation data | Administrator, Editor, Tour Author, WPistic Travel Manager, WPistic Travel Agent, CRM Marketing |
| `bt_manage_bookings` | Booking and inquiry workflow actions | Administrator, Tour Staff, WPistic Travel Manager, WPistic Travel Agent, CRM Owner, CRM Manager, CRM Sales, CRM Accountant |
| `bt_view_booking_pii` | Read customer identity, contact and financial booking data | Administrator, WPistic Travel Manager, WPistic Travel Agent, CRM Owner, CRM Manager, CRM Compliance Officer, CRM Sales, CRM Accountant |
| `bt_manage_payments` | Create payment links and payment actions | Administrator, WPistic Travel Manager, CRM Owner, CRM Accountant |
| `bt_manage_operations` | Operational dashboard, tour operations and workflow actions | Administrator, Tour Staff, WPistic Travel Manager, WPistic Travel Agent, CRM Owner, CRM Manager, CRM Sales |
| `bt_view_health` | Technical health and integration diagnostics | Administrator, WPistic Travel Manager, CRM Owner, CRM Compliance Officer |

`edit_posts` is intentionally not sufficient for booking PII, payment operations, health diagnostics or operations API mutations. The role map is additive and least-privilege; it does not remove existing WordPress capabilities from the site's custom roles.

## Rollout order

1. Activate Content Studio and confirm capabilities.
2. Log in with the approved operations account.
3. Test read-only dashboard, tours and booking screens.
4. Test a harmless workflow update in staging.
5. Test payment/webhook paths with provider sandbox credentials only.
6. Remove temporary compatibility permissions only after successful UAT.

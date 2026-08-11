# 15 - Staging QA Report

Status: **PRE-GATE-A READ-ONLY BASELINE - NOT A RELEASE CANDIDATE**

| Check | Result | Evidence / blocker |
|---|---|---|
| HTTPS homepage response | PASS | Public `200`, PHP 8.3.31/LiteSpeed observed 2026-08-11 |
| Authentication protection | FAIL | Homepage readable without authentication |
| Robots protection | FAIL | `Disallow: /` conflicts with homepage `index, follow`; compliant crawlers cannot see page directive |
| Homepage canonical | FAIL | Self-canonical to staging while publicly accessible |
| XML sitemap privacy | FAIL | Core sitemap publicly lists post/page/tour/destination/taxonomy/user child maps |
| Security headers | PARTIAL PASS | HSTS, nosniff, SAMEORIGIN, referrer and permissions policies observed; CSP is report-only plus host upgrade policy |
| SEOISTIC ownership | BLOCKED | Source/settings/active plugin export missing |
| Representative templates | BLOCKED | Robots policy respected; authenticated crawl/browser access missing |
| Forms/email/spam/consent | BLOCKED | Admin, mail logs and safe test inbox missing |
| Booking/payment/webhooks | BLOCKED | Sandbox credentials and test data missing; no real charge attempted |
| Analytics events | BLOCKED | GA4/consent access missing |
| Accessibility/mobile/browser | BLOCKED | Authenticated browser QA not completed |
| Cache/PHP/JS logs | BLOCKED | Hosting/admin/log access missing |

Staging QA is not Gate B evidence. The next safe action is to add HTTP authentication and explicit noindex protection, then provide authorized access for the complete matrix.

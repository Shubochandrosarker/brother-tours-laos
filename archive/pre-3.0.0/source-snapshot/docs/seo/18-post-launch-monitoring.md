# 18 - Post-Launch Monitoring

Launch date is **MISSING**; dates below are relative and must be converted to calendar dates in Gate B. Technical owner: WordPressistic - Shuvo. Business/analytics/hosting owners remain MISSING.

| Time | Checks | Owner | Evidence |
|---|---|---|---|
| T+0 | Availability, 5xx, redirects, canonicals, robots, sitemap, schema, forms, booking/payment sandbox, consent/analytics, logs | Technical + hosting owner | HTTP/browser/log smoke bundle |
| T+2h | Repeat revenue paths; queue/webhook failures; redirect anomalies; cache drift | Technical owner | Exception report |
| T+24h | 404/5xx logs, sitemap fetch, staging leakage, analytics conversion continuity | Technical + analytics owner | Day-1 brief |
| T+72h | GSC sitemap/pages freshness, landing-page/redirect anomalies, backlinks hitting retired URLs | SEO owner | 72-hour migration brief |
| T+7d | GSC queries/pages, GA4 landing/conversion trends, crawl logs, CWV lab regressions | SEO + analytics owner | Week-1 comparison |
| T+14d | Indexation/canonical clusters, traffic/conversion association, unresolved redirects/content | SEO owner | Week-2 review |
| T+30d | Full migration review, field CWV when available, decision on remaining exceptions | Final approver + owners | 30-day report |

Use equal comparison periods and label changes as measured, associated, inferred or pending. Record data freshness, outages, promotions, consent/tracking changes and seasonality. Do not promise rankings or traffic.

Every issue needs stable ID, severity, owner, due date, duplicate link, state and recovery verification. Re-run the immediate checks after every approved deployment.

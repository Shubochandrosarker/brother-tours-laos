# 10 - Canonical, Indexation and Sitemap Rules

Status: **PROPOSED - GATE A APPROVAL REQUIRED**

Canonical origin: `https://www.brothertours.com/`. HTTP/apex/slash variants must resolve to it in one hop; current HTTP apex uses two hops and needs infrastructure review.

| URL class | Indexation | Canonical | Sitemap |
|---|---|---|---|
| Home | index, follow | self | include |
| `/tours/` and `/destinations/` | index, follow after approved content | self | include |
| Approved tour/destination single | index, follow | self | include when 200 |
| Approved travel-style hub | index, follow | self | include |
| Unapproved taxonomy archive | noindex, follow | self | exclude |
| Filtered `/tours/?...` | noindex, follow | normalized self because filtered content differs | exclude |
| Tracking/sort/display parameters | inherit page | clean equivalent | exclude |
| Useful pagination | index, follow | self; never page 2 to page 1 | exclude page URLs |
| Search and author/user archives | noindex, follow | self | exclude |
| Attachments | redirect to relevant parent or noindex | target/self | exclude |
| Feeds | noindex HTTP header where supported | none | exclude |
| Thank-you/process pages | noindex | self | exclude |
| Legal pages | index unless legal owner decides otherwise | self | optional exclusion |
| Redirect/error/private/staging | not indexable | none | exclude |

- Never use `robots.txt` to hide a URL that must expose `noindex`.
- Never canonicalize materially different content to an unrelated page.
- Only canonical, indexable, public 200 URLs with approved content enter XML sitemaps.
- SEOISTIC must be the sole owner after its source and settings are audited; disabling Rank Math or core output is a post-Gate-A staging change.
- `llms.txt` is optional and is generated only from the approved canonical inventory.

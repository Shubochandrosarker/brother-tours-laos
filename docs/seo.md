# SEO and crawlability

## Staging

Staging must emit `noindex,nofollow,noarchive`, block crawling in
`robots.txt`, and preferably require authentication. A public sitemap is not a
replacement for access control.

## Production

Before launch, verify one canonical host, HTTPS redirects, page titles,
descriptions, canonical URLs, Open Graph data, XML sitemaps, structured data,
internal links, and the intended indexation state. Do not publish unverified
review ratings, prices, awards, or business claims.

## URL migration

Preserve approved old URLs with permanent redirects where content moved. Do
not create redirect chains or redirect unrelated pages to the homepage. Record
every redirect in the migration map and crawl the final production site
anonymously after deployment.

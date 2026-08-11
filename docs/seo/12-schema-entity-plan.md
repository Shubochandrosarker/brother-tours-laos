# 12 - Schema and Entity Plan

Status: **PROPOSED - GATE A APPROVAL REQUIRED**

## Stable IDs

- Business: `https://www.brothertours.com/#organization`
- Website: `https://www.brothertours.com/#website`
- Page: `{canonical-url}#webpage`
- Breadcrumbs: `{canonical-url}#breadcrumb`
- Tour: `{canonical-tour-url}#tour`
- Destination: `{canonical-destination-url}#destination`
- Article/author: `{canonical-url}#article`; author ID pending verified roster

## Graph by page

- Home: `TravelAgency`, `WebSite`, `WebPage`
- Archives: `CollectionPage`, `BreadcrumbList`; item links only
- Tour: `WebPage`, `BreadcrumbList`, `TouristTrip`
- Destination: `WebPage`, `BreadcrumbList`, `TouristDestination`
- Eligible experience: `WebPage`, `BreadcrumbList`, `TouristAttraction`
- Planning article: one `BlogPosting` or `Article` entity
- Visible verified FAQ content: `FAQPage` only where accurate and eligible

## Truth conditions

Business name, address, phone, WhatsApp, email, hours, geo, founding year, licence, logo, profiles and price range are all **MISSING - OWNER INPUT REQUIRED**.

`TouristTrip` may contain only visible, verified name, description, URL, image, duration, itinerary, destinations, traveler fit, provider and commercial data. Omit `Offer` when price/currency/booking state is blank, provisional or unauthoritative. Availability comes only from the booking/departure source; never hard-code `LimitedAvailability` or `InStock`. Do not emit ratings, counts, awards or reviews without verified source records.

Connect `WebSite.publisher`, `WebPage.isPartOf`, `WebPage.breadcrumb` and `TouristTrip.provider` using the stable IDs. Validator success proves syntax, not truth or rich-result eligibility.

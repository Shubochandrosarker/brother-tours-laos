# Content Studio QA checklist

## Install and editor

- [ ] Activate Content Studio on a protected staging copy.
- [ ] Confirm all twelve blocks appear in the Brother Tours inserter category.
- [ ] Insert Premium Homepage Starter into a duplicate homepage.
- [ ] Confirm server-rendered previews update after changing each control.
- [ ] Confirm non-empty Elementor pages still open with Elementor.
- [ ] Confirm block editor is enabled for pages, posts, tours and destinations.
- [ ] Confirm editors can edit content but cannot change protected capabilities/settings.

## Content and schema

- [ ] Complete verified fields for one representative tour and destination.
- [ ] Confirm empty price/currency does not create an `Offer`.
- [ ] Confirm FAQ schema matches visible FAQ text.
- [ ] Confirm no aggregate rating is emitted from a single review block.
- [ ] Confirm all non-decorative images have media-library alt text.
- [ ] Confirm tour/destination URLs remain unchanged unless an approved map exists.

## Security and operations

- [ ] Staging unauthenticated response is `401` or `403`.
- [ ] Debug logging is disabled and the old log is archived under the approved retention plan.
- [ ] Operations API uses custom capabilities, not `edit_posts`.
- [ ] Booking PII is inaccessible to an editor without `bt_view_booking_pii`.
- [ ] Payment-link action requires `bt_manage_payments`.
- [ ] Health endpoint requires `bt_view_health`.
- [ ] Webhook signatures, idempotency and replay behavior pass sandbox tests.

## Browser and performance

- [ ] Chrome, Firefox, Safari and Edge latest two versions.
- [ ] iPhone Safari, Android Chrome, iPad portrait/landscape and Android tablet.
- [ ] Hover, keyboard focus, menu, accordion, filters, forms and sticky CTA.
- [ ] Touch targets are at least 48px.
- [ ] Lighthouse is at least 90 in all four categories on the approved representative pages.
- [ ] LCP < 2.5s, INP < 200ms, CLS < 0.1.
- [ ] No console errors or broken internal links.


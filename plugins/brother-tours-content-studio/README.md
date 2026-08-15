# Brother Tours Content Studio

Gutenberg-first visual content system for the Brother Tours WordPress site.

**Version:** 3.0.0

## What it provides

- Twelve dynamic Gutenberg blocks under the `Brother Tours` inserter category.
- Reusable homepage and tour-detail patterns.
- Structured tour and destination fields exposed through the WordPress REST API.
- Portable SEO fields with SEOISTIC ownership preserved when SEOISTIC is active.
- Granular operations capabilities for booking, payment, operations and health data.
- A reversible child-theme bridge for homepage, tour and destination block content.
- A valid XML sitemap endpoint for sites where the authoritative SEO layer does not provide one.

## Installation

1. Copy this folder to `wp-content/plugins/brother-tours-content-studio/`.
2. Activate it before changing Operations API permissions.
3. Confirm the administrator role has the `bt_*` capabilities.
4. Open **Content Studio** and configure only verified business facts.
5. Insert the Premium Homepage Starter pattern into the assigned homepage.
6. Preview on staging before enabling **Visual homepage**.

The plugin does not delete Elementor data, existing PHP templates, tours or destinations. A page becomes visual only when it contains block content and the operator explicitly enables the visual homepage setting.

## Block inventory

Hero, Tour Collection, Destination Grid, Trust/Facts Strip, Founder Profile, Review, Itinerary, Included/Excluded, FAQ, Gallery/Story, CTA & Inquiry, and Newsletter.

## Safety boundaries

- No prices, availability, ratings, reviews or destination facts are invented.
- Empty price currency means no commercial `Offer` should be emitted by the SEO layer.
- Review schema is disabled by default and must be enabled only for genuine visible reviews.
- The newsletter block refuses to pretend that delivery is configured when no approved provider shortcode exists.
- Staging protection still requires Cloudflare Access or HTTP authentication; robots directives are not access control.

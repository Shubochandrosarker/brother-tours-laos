# Installation runbook

## Pre-installation

1. Confirm the target WordPress and PHP versions meet the requirements.
2. Record the current active theme, plugins, front page, menus, permalinks,
   forms, redirects, and SEO settings.
3. Create and verify both a database backup and a `wp-content` file backup.
4. Put the site in a controlled maintenance window.
5. Keep the existing theme and plugins available for rollback until the new
   site passes the production crawl.

## File deployment

Upload only the folders listed in the root README. Do not upload the repository
root, `.git`, `archive`, scratch files, or source snapshots.

## Activation order

Activate Formistic, WPistic Tour Manager, Content Studio, and then the Brother
Tours child theme. Activate the Operations API only if its app integration is
ready. Keep one Formistic installation active.

## WordPress settings

- Save permalinks after activation.
- Set the intended static homepage and posts page.
- Assign primary and footer menus.
- Configure contact and newsletter forms.
- Configure sender/reply-to addresses and transactional mail.
- Keep staging `noindex` and production indexing intentional.
- Review all legal pages before publishing.

## Verification

Check the homepage, header/footer links, mobile navigation, Build My Trip,
contact form, newsletter, WhatsApp, tour archive, destination archive, single
tour, single destination, sitemap, canonical tags, and 404 page. Test on mobile
and desktop. Do not call the site production-ready from a successful upload
alone.

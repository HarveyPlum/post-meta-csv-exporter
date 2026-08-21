=== Post Meta CSV Exporter ===
Contributors: harveyplum
Tags: export, csv, metadata, admin, posts
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Export WordPress post meta to CSV and choose exactly which fields are included.

== Description ==

Post Meta CSV Exporter adds a Tools page in the WordPress admin where administrators can:

- Choose a post type to export.
- Load all discovered meta keys for that post type.
- Filter the export by post status, author, category, and publish date.
- Optionally include protected keys that start with `_`.
- Pick which standard columns and meta fields appear in the CSV, including Category and Tags columns.
- Download the export immediately as a CSV file.

== Installation ==

1. Copy the `post-meta-csv-exporter` folder into your `/wp-content/plugins/` directory.
2. Activate the plugin from the WordPress admin.
3. Go to `Tools > Post Meta CSV Export`.

== Frequently Asked Questions ==

= Can I export custom post types? =

Yes. Any post type with a WordPress admin UI is available in the exporter, except attachments and a few internal types.

= Can I include hidden meta keys? =

Yes. Enable the "Include protected meta keys" option before loading fields.

== Changelog ==

= 1.0.7 =

- Added GitHub update metadata and standardized Harvey Plum branding.
- Prevented exported values from being interpreted as spreadsheet formulas.

= 1.0.6 =

- Added category filtering, Category and Tags standard columns, and Harvey Plum-branded screen/footer text.

= 1.0.5 =

- Updated status and author filters to allow selecting multiple options.

= 1.0.4 =

- Added export filters for post status, author, and publish date range.

= 1.0.0 =

- Initial release.

=== GenerateBlocks Dynamic Tag Extensions by BWS ===
Contributors: david-mitchell
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.16.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See README.md for overview, docs/tag-reference.md for architecture, and CHANGELOG.md for version history.

== Upgrade Notice ==

= 1.16.0 =
Heads up: the {{join}} tag's Fallback Text option is renamed. Join shipped one release ago, so there is no migration. If you set Fallback Text on a join tag in 1.15.0, open it and re-enter the value. Fallback on every other tag is unaffected.

= 1.15.1 =
Fixes a bug in 1.15.0 that breaks WP-CLI. If you run 1.15.0, every wp command on that site stops early and does nothing, including wp search-replace during a domain move. Update before running WP-CLI again. Sites that never use WP-CLI are unaffected.

= 1.14.0 =
Old deprecated tags are no longer registered as of v1.14.0. After upgrading, any instances in content will return the unprocessed tag strings on the frontend. Scan and migrate with the Migration Tool (Settings > Tag Extensions) before updating.

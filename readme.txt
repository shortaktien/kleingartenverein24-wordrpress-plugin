=== KGV24 ===
Contributors: shortaktien
Tags: garden, association, shortcode, api, listings
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Shows available garden plots from Kleingartenverein24 on a WordPress site using a shortcode.

== Description ==

KGV24 connects a WordPress site with the Kleingartenverein24 API. Garden clubs can display available or not yet leased garden plots from their tenant account on their public website.

The first feature is a shortcode for available garden plots:

`[kgv-garten]`

The plugin adds a WordPress admin menu named "KGV24" where site administrators can configure the API URL, save a tenant-bound API key, and test the connection.

= External service =

This plugin connects to the Kleingartenverein24 API at `https://kleingartenverein24.de`.

API requests are made only after an administrator configures an API key in the plugin settings or runs the connection test. The plugin sends the configured API key as a Bearer token:

`Authorization: Bearer kgv_live_...`

The API is used to authenticate the tenant and retrieve tenant garden plot data. A separate `X-Tenant-Slug` header is not required for tenant-bound API keys.

Service website: https://kleingartenverein24.de

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/kgv24/`.
2. Activate the plugin through the WordPress "Plugins" screen.
3. Open the new "KGV24" admin menu.
4. Enter the API URL. The default is `https://kleingartenverein24.de`.
5. Enter the tenant-bound API key from Kleingartenverein24.
6. Save the settings.
7. Run "Authentifizierung testen".
8. Add `[kgv-garten]` to a page, post, or shortcode-compatible block.

== Frequently Asked Questions ==

= Where do I get the API key? =

Create or renew the API key in the Kleingartenverein24 settings. The full key is shown only directly after creation or renewal, so copy it immediately.

= Do I need a tenant slug? =

No. Tenant-bound API keys are resolved by the API. The plugin only sends the Bearer token.

= Which shortcode displays available garden plots? =

Use:

`[kgv-garten]`

You can limit the number of cards:

`[kgv-garten limit="6"]`

You can also show plots where the API response does not yet include a clear available/leased status:

`[kgv-garten show_unknown="1"]`

= What happens if the API key is invalid? =

The admin connection test and the frontend shortcode show an error message. API access can also be blocked by the subscription or trial status of the tenant account.

== Changelog ==

= 0.1.0 =

* Initial plugin version.
* Added KGV24 admin settings page.
* Added tenant-bound Bearer token support.
* Added authentication test against `/api/tenant/session`.
* Added `[kgv-garten]` shortcode for available garden plots.
* Added responsive frontend card styling.

== Upgrade Notice ==

= 0.1.0 =

Initial release.

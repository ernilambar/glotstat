=== GlotStat ===

Contributors: nilambar
Tags: translation, i18n, l10n, plugin install, glotpress
Requires at least: 6.9
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Displays plugin translation stats.

== Description ==

GlotStat shows the translation completion percentage for your current locale directly on the Add Plugins screen, so you can see at a glance how well a plugin is translated before installing it.

= Features =

* Translation percentage shown on each plugin card
* Data pulled live from translate.wordpress.org
* Link to the plugin's translation project
* Shows waiting, fuzzy, and warning string counts
* Results cached to avoid repeated lookups
* Hidden automatically when your site locale is English (US)

== Installation ==

= Using The WordPress Dashboard =

1. Navigate to Plugins → Add New Plugin
1. Search for "GlotStat"
1. Install and activate the plugin

= Using FTP =

1. Extract 'glotstat.zip' to your computer
1. Upload the 'glotstat' directory to your '/wp-content/plugins/' directory
1. Activate the plugin on the WordPress Plugins dashboard

== Frequently Asked Questions ==

= Why don't I see any translation status? =

GlotStat only runs when your site locale is set to something other than English (US). Check Settings → General → Site Language.

= Where does the translation data come from? =

Data is fetched from the [WordPress.org translation API](https://translate.wordpress.org/) for the plugin's development version.

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release.

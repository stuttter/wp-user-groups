=== WP User Groups ===
Contributors: johnjamesjacoby, stuttter
Tags: taxonomy, term, user, group, type
Requires at least: 4.7
Tested up to: 4.9
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=9Q4F4EL5YJ62J

== Description ==

WP User Groups allows users to be categorized using custom taxonomies & terms.

* "Groups" & "Types" are created by default, and can be overridden
* More user group types can be registered with custom arguments
* Edit users and set their relationships
* Bulk edit many users to quickly assign several at once
* Filter the users list to see which users are in what groups
* Not destructive data storage (plugin can be enabled & disabled without damage)
* Works great with all WP Term Meta plugins (see below)

= Also checkout =

* [WP Chosen](https://wordpress.org/plugins/wp-chosen/ "Make long, unwieldy select boxes much more user-friendly.")
* [WP Pretty Filters](https://wordpress.org/plugins/wp-pretty-filters/ "Makes post filters better match what's already in Media & Attachments.")
* [WP Event Calendar](https://wordpress.org/plugins/wp-event-calendar/ "The best way to manage events in WordPress.")
* [WP Media Categories](https://wordpress.org/plugins/wp-media-categories/ "Add categories to media & attachments.")
* [WP Term Meta](https://wordpress.org/plugins/wp-term-meta/ "Sort taxonomy terms, your way.")
* [WP Term Order](https://wordpress.org/plugins/wp-term-order/ "Sort taxonomy terms, your way.")
* [WP Term Authors](https://wordpress.org/plugins/wp-term-authors/ "Authors for categories, tags, and other taxonomy terms.")
* [WP Term Colors](https://wordpress.org/plugins/wp-term-colors/ "Pretty colors for categories, tags, and other taxonomy terms.")
* [WP Term Icons](https://wordpress.org/plugins/wp-term-icons/ "Pretty icons for categories, tags, and other taxonomy terms.")
* [WP Term Visibility](https://wordpress.org/plugins/wp-term-visibility/ "Visibilities for categories, tags, and other taxonomy terms.")
* [WP User Activity](https://wordpress.org/plugins/wp-user-activity/ "The best way to log activity in WordPress.")
* [WP User Avatars](https://wordpress.org/plugins/wp-user-avatars/ "Allow users to upload avatars or choose them from your media library.")
* [WP User Profiles](https://wordpress.org/plugins/wp-user-profiles/ "A sophisticated way to edit users in WordPress.")

== Screenshots ==

1. Menu Items
2. Groups Taxonomy
3. Types Taxonomy
4. User Edit & Assignment
5. Users List
6. Users List (Filtered)

== Installation ==

1. Download and install using the built in WordPress plugin installer.
1. Activate in the "Plugins" area of your admin by clicking the "Activate" link.
1. Visit "Users > Groups" and create some groups
1. Add users to groups by editing their profile and checking the boxes

== Frequently Asked Questions ==

= Does this create new database tables? =

No. There are no new database tables with this plugin.

= Does this modify existing database tables? =

No. All of WordPress's core database tables remain untouched.

= Does this plugin integrate with user roles? =

No. This is best left to plugins that choose to integrate with this plugin.

= Where can I get support? =

* Basic: https://wordpress.org/support/plugin/wp-user-groups/
* Priority: https://chat.flox.io/support/channels/wp-user-groups/

= Where can I find documentation? =

http://github.com/stuttter/wp-user-groups/

== Changelog ==

= [2.0.0] - 2017/10/24 =
* Fix bug with user filtering
* Fix bug with setting user terms
* Add `exclusive` group argument to use radios instead of checkboxes

= [1.1.0] - 2017/03/28 =
* Change default taxonomy to `user-group` in wp_get_users_of_group()

= [1.0.0] - 2016/12/07 =
* WordPress 4.7 compatibility
* Improved bulk actions (requires WordPress 4.7)
* Official stable release

= [0.2.1] - 2016/05/25 =
* Fix bug with user list
* Introduce wp_get_users_of_group() helper function
* Add unique class to administration forms

= [0.2.0] - 2015/12/23 =
* Support for WP User Profiles 0.2.0

= [0.1.9] - 2015/12/21 =
* Fix bug with User Profiles integration

= [0.1.8] - 2015/11/11 =
* Support for WP User Profiles 0.1.9

= [0.1.7] - 2015/11/09 =
* Update assets & meta

= [0.1.6] - 2015/10/23 =
* Add support for WP User Profiles

= [0.1.5] - 2015/10/13 =
* Added `user_group` property to taxonomies
* Added functions for retrieving only user-groups from taxonomies global

= [0.1.0] - 2015/09/10 =
* Refactor
* Improve asset management
* Styling tweaks

= [0.1.2] - 2015/09/01 =
* Namespace default taxonomy IDs

= [0.1.1] - 2015/08/24 =
* User profile UI uses a mock list-table

= [0.1.0] - 2015/08/19 =
* Initial release

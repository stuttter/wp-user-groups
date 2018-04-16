<?php

/**
 * Plugin Name: WP User Groups
 * Plugin URI:  https://wordpress.org/plugins/wp-user-groups/
 * Author:      John James Jacoby
 * Author URI:  https://profiles.wordpress.org/johnjamesjacoby/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Description: Group users together with taxonomies & terms.
 * Version:     2.1.1
 * Text Domain: wp-user-groups
 * Domain Path: /wp-user-groups/assets/languages/
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Include the User Groups files
 *
 * @since 0.1.0
 */
function _wp_user_groups() {

	// Get the plugin path
	$plugin_path = plugin_dir_path( __FILE__ ) . 'wp-user-groups/';

	// Classes
	require_once $plugin_path . 'includes/classes/class-user-taxonomy.php';

	// Functions
	require_once $plugin_path . 'includes/functions/admin.php';
	require_once $plugin_path . 'includes/functions/common.php';
	require_once $plugin_path . 'includes/functions/taxonomies.php';
	require_once $plugin_path . 'includes/functions/hooks.php';
}
add_action( 'plugins_loaded', '_wp_user_groups' );

/**
 * Return the plugin URL
 *
 * @since 0.1.4
 *
 * @return string
 */
function wp_user_groups_get_plugin_url() {
	return plugin_dir_url( __FILE__ ) . 'wp-user-groups/';
}

/**
 * Return the asset version
 *
 * @since 0.1.4
 *
 * @return int
 */
function wp_user_groups_get_asset_version() {
	return 201804160001;
}

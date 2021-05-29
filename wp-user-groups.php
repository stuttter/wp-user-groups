<?php

/**
 * Plugin Name:       WP User Groups
 * Description:       Group users together with taxonomies & terms
 * Plugin URI:        https://wordpress.org/plugins/wp-user-groups/
 * Author:            Triple J Software, Inc.
 * Author URI:        https://jjj.software
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-user-activity
 * Domain Path:       /wp-user-activity/includes/languages
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Tested up to:      5.8
 * Version:           2.5.0
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
	require_once $plugin_path . 'includes/functions/sponsor.php';
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
	return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG
		? time()
		: 202103230001;
}

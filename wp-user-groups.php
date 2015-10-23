<?php

/**
 * Plugin Name: WP User Groups
 * Plugin URI:  https://wordpress.org/plugins/wp-user-groups/
 * Description: Group users together with taxonomies & terms.
 * Author:      John James Jacoby
 * Version:     0.1.6
 * Author URI:  https://profiles.wordpress.org/johnjamesjacoby/
 * License:     GPL v2 or later
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
	$plugin_path = plugin_dir_path( __FILE__ );

	// Required files
	require_once $plugin_path . 'includes/admin.php';
	require_once $plugin_path . 'includes/class-user-taxonomy.php';
	require_once $plugin_path . 'includes/functions.php';
	require_once $plugin_path . 'includes/taxonomies.php';
	require_once $plugin_path . 'includes/hooks.php';
}
add_action( 'plugins_loaded', '_wp_user_groups' );

/**
 * Return the plugin's URL
 *
 * @since 0.1.4
 *
 * @return string
 */
function wp_user_groups_get_plugin_url() {
	return plugin_dir_url( __FILE__ );
}

/**
 * Return the asset version
 *
 * @since 0.1.4
 *
 * @return int
 */
function wp_user_groups_get_asset_version() {
	return 201510230001;
}

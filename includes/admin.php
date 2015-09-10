<?php

/**
 * User Groups Admin
 *
 * @package UserGroups/Admin
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Tweak admin styling for a user groups layout
 *
 * @since 0.1.4
 */
function wp_user_groups_admin_assets() {
	wp_enqueue_style( 'wp_user_groups', wp_user_groups_get_plugin_url() . '/assets/css/user-groups.css', false, wp_user_groups_get_asset_version(), false );
}

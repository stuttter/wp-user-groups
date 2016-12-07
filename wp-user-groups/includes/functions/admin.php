<?php

/**
 * User Groups Admin
 *
 * @package Plugins/Users/Groups/Admin
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Tweak admin styling for a user groups layout
 *
 * @since 0.1.4
 */
function wp_user_groups_admin_assets() {
	$url = wp_user_groups_get_plugin_url();	
	$ver = wp_user_groups_get_asset_version();

	wp_enqueue_style( 'wp_user_groups', $url. 'assets/css/user-groups.css', false, $ver, false );
}


/**
 * Add new section to User Profiles
 *
 * @since 0.1.9
 *
 * @param array $sections
 */
function wp_user_groups_add_profile_section( $sections = array() ) {

	// Copy for modifying
	$new_sections = $sections;

	// Add the "Activity" section
	$new_sections['groups'] = array(
		'id'    => 'groups',
		'slug'  => 'groups',
		'name'  => esc_html__( 'Groups', 'wp-user-activity' ),
		'cap'   => 'edit_profile',
		'icon'  => 'dashicons-groups',
		'order' => 90
	);

	// Filter & return
	return apply_filters( 'wp_user_groups_add_profile_section', $new_sections, $sections );
}

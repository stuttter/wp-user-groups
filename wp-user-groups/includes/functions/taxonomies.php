<?php

/**
 * User Groups Taxonomies
 *
 * @package Plugins/Users/Groups/Taxonomy
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Register default user group taxonomies
 *
 * This function is hooked onto WordPress's `init` action and creates two new
 * `WP_User_Taxonomy` objects for user "groups" and "types". It can be unhooked
 * and these taxonomies can be replaced with your own custom ones.
 *
 * @since 0.1.4
 */
function wp_register_default_user_group_taxonomy() {
	new WP_User_Taxonomy( 'user-group', 'users/group', array(
		'singular' => __( 'Group',  'wp-user-groups' ),
		'plural'   => __( 'Groups', 'wp-user-groups' )
	) );
}

/**
 * Register default user group taxonomies
 *
 * This function is hooked onto WordPress's `init` action and creates two new
 * `WP_User_Taxonomy` objects for user "groups" and "types". It can be unhooked
 * and these taxonomies can be replaced with your own custom ones.
 *
 * @since 0.1.4
 */
function wp_register_default_user_type_taxonomy() {
	new WP_User_Taxonomy( 'user-type',  'users/type',  array(
		'singular' => __( 'Type',  'wp-user-groups' ),
		'plural'   => __( 'Types', 'wp-user-groups' )
	) );
}

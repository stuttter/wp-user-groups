<?php

/**
 * User Groups Hooks
 *
 * @package Plugins/Users/Groups/Hooks
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Register the default taxonomies
add_action( 'init', 'wp_register_default_user_group_taxonomy' );
add_action( 'init', 'wp_register_default_user_type_taxonomy'  );

// Enqueue assets
add_action( 'admin_head', 'wp_user_groups_admin_assets' );

// WP User Profiles
add_filter( 'wp_user_profiles_sections', 'wp_user_groups_add_profile_section' );

<?php

/**
 * User Groups Functions
 *
 * @package Plugins/Users/Groups/Functions
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Get terms for a user and a taxonomy
 *
 * @since 0.1.0
 *
 * @param  mixed  $user
 * @param  int    $taxonomy
 *
 * @return boolean
 */
function wp_get_terms_for_user( $user = false, $taxonomy = '' ) {

	// Verify user ID
	$user_id = is_object( $user )
		? $user->ID
		: absint( $user );

	// Bail if empty
	if ( empty( $user_id ) ) {
		return false;
	}

	// Return user terms
	return wp_get_object_terms( $user_id, $taxonomy, array(
		'fields' => 'all_with_object_id'
	) );
}

/**
 * Save taxonomy terms for a specific user
 *
 * @since 0.1.0
 *
 * @param  int      $user_id
 * @param  string   $taxonomy
 * @param  array    $terms
 * @param  boolean  $bulk
 *
 * @return boolean
 */
function wp_set_terms_for_user( $user_id, $taxonomy, $terms = array(), $bulk = false ) {

	// Get the taxonomy
	$tax = get_taxonomy( $taxonomy );

	// Make sure the current user can edit the user and assign terms before proceeding.
	if ( ! current_user_can( 'edit_user', $user_id ) && current_user_can( $tax->cap->assign_terms ) ) {
		return false;
	}

	if ( empty( $terms ) && empty( $bulk ) ) {
		$terms = isset( $_POST[ $taxonomy ] )
			? $_POST[ $taxonomy ]
			: null;
	}

	// Delete all user terms
	if ( is_null( $terms ) || empty( $terms ) ) {
		wp_delete_object_term_relationships( $user_id, $taxonomy );

	// Set the terms
	} else {
		$_terms = array_map( 'sanitize_key', $terms );

		// Sets the terms for the user
		wp_set_object_terms( $user_id, $_terms, $taxonomy, false );
	}

	// Clean the cache
	clean_object_term_cache( $user_id, $taxonomy );
}

/**
 * Get all user groups
 *
 * @uses get_taxonomies() To get user-group taxonomies
 *
 * @since 0.1.5
 *
 * @param array  $args     Optional. An array of `key => value` arguments to
 *                         match against the taxonomy objects. Default empty array.
 * @param string $output   Optional. The type of output to return in the array.
 *                         Accepts either taxonomy 'names' or 'objects'. Default 'names'.
 * @param string $operator Optional. The logical operation to perform.
 *                         Accepts 'and' or 'or'. 'or' means only one element from
 *                         the array needs to match; 'and' means all elements must
 *                         match. Default 'and'.
 *
 * @return array A list of taxonomy names or objects.
 */
function wp_get_user_groups( $args = array(), $output = 'names', $operator = 'and' ) {

	// Parse arguments
	$r = wp_parse_args( $args, array(
		'user_group' => true
	) );

	// Return user group taxonomies
	return get_taxonomies( $r, $output, $operator );
}

/**
 * Get all user group objects
 *
 * @uses wp_get_user_groups() To get user group objects
 *
 * @since 0.1.5
 *
 * @param  array  $args     See wp_get_user_groups()
 * @param  string $operator See wp_get_user_groups()
 *
 * @return array
 */
function wp_get_user_group_objects( $args = array(), $operator = 'and' ) {
	return wp_get_user_groups( $args, 'objects', $operator );
}

/**
 * Return a list of users in a specific group
 *
 * @since 0.1.0
 */
function wp_get_users_of_group( $args = array() ) {

	// Parse arguments
	$r = wp_parse_args( $args, array(
		'taxonomy' => 'user-type',
		'term'     => '',
		'term_by'  => 'slug'
	) );

	// Get user IDs in group
	$term     = get_term_by( $r['term_by'], $r['term'], $r['taxonomy'] );
	$user_ids = get_objects_in_term( $term->term_id, $r['taxonomy'] );

	// Bail if no users in this term
	if ( empty( $term ) || empty( $user_ids ) ) {
		return array();
	}

	// Return queried users
	return get_users( array(
		'orderby' => 'display_name',
		'include' => $user_ids,
	) );
}

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
 * @param  string $taxonomy
 *
 * @return array<int, WP_Term>|WP_Error|false
 */
function wp_get_terms_for_user( $user = false, $taxonomy = '' ) {

	// Verify user ID
	$user_id = is_object( $user ) && ! empty( $user->ID )
		? $user->ID
		: absint( $user );

	// Bail if empty
	if ( empty( $user_id ) ) {
		return false;
	}

	// Return user terms
	return wp_get_object_terms(
		$user_id,
		$taxonomy,
		array(
			'fields' => 'all_with_object_id',
		)
	);
}

/**
 * Save taxonomy terms for a specific user
 *
 * @since 0.1.0
 *
 * @param  mixed                  $user
 * @param  string                 $taxonomy
 * @param array<int, int|string> $terms
 * @param bool                   $append Whether to append the terms instead of replacing them.
 *
 * @return bool|null
 */
function wp_set_terms_for_user( $user = false, $taxonomy = '', $terms = array(), $append = false ) {

	// Verify user ID
	$user_id = is_object( $user ) && ! empty( $user->ID )
		? $user->ID
		: absint( $user );

	// Bail if empty
	if ( empty( $user_id ) ) {
		return false;
	}

	// Delete all terms for the user
	if ( empty( $terms ) ) {
		wp_delete_object_term_relationships( $user_id, $taxonomy );

		// Sets the terms for the user
	} else {
		wp_set_object_terms( $user_id, $terms, $taxonomy, $append );
	}

	// Clean the cache
	clean_object_term_cache( $user_id, $taxonomy );

	return null;
}

/**
 * Get all user groups
 *
 * @uses get_taxonomies() To get user-group taxonomies
 *
 * @since 0.1.5
 *
 * @param array<string, mixed> $args Optional. An array of `key => value` arguments to
 *                         match against the taxonomy objects. Default empty array.
 * @param string               $output   Optional. The type of output to return in the array.
 *                                       Accepts either taxonomy 'names' or 'objects'. Default 'names'.
 * @param 'and'|'or'           $operator Optional. The logical operation to perform.
 *                         Accepts 'and' or 'or'. 'or' means only one element from
 *                         the array needs to match; 'and' means all elements must
 *                         match. Default 'and'.
 *
 * @return array<int|string, string|WP_Taxonomy> A list of taxonomy names or objects.
 */
function wp_get_user_groups( $args = array(), $output = 'names', $operator = 'and' ) {

	// Parse arguments
	$r = wp_parse_args(
		$args,
		array(
			'user_group' => true,
		)
	);

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
 * @param array<string, mixed> $args See wp_get_user_groups()
 * @param 'and'|'or'           $operator See wp_get_user_groups()
 *
 * @return array<int|string, string|WP_Taxonomy>
 */
function wp_get_user_group_objects( $args = array(), $operator = 'and' ) {
	return wp_get_user_groups( $args, 'objects', $operator );
}

/**
 * Return a list of users in a specific group.
 *
 * @since 0.1.0
 *
 * @param array<string, mixed> $args      Term lookup arguments.
 * @param array<string, mixed> $user_args Optional. WP_User_Query arguments.
 *
 * @return array<int, WP_User> List of users in the user group.
 */
function wp_get_users_of_group( $args = array(), $user_args = array() ) {

	// Parse arguments.
	$r = wp_parse_args(
		$args,
		array(
			'taxonomy' => 'user-group',
			'term'     => '',
			'term_by'  => 'slug',
		)
	);

	// Get user IDs in group.
	$term = get_term_by( $r['term_by'], $r['term'], $r['taxonomy'] );

	// Bail if the term does not exist.
	if ( ! $term instanceof WP_Term ) {
		return array();
	}

	$user_ids = get_objects_in_term( $term->term_id, $r['taxonomy'] );

	// Bail if no users are in this term.
	if ( empty( $user_ids ) || is_wp_error( $user_ids ) ) {
		return array();
	}

	// Parse optional user arguments.
	$user_args = wp_parse_args( $user_args, array(
		'orderby' => 'display_name',
	) );

	// Strictly enforce the inclusion of user IDs to this group
	$user_args['include'] = array_map( 'absint', $user_ids );

	// Return queried users.
	return get_users( $user_args );
}

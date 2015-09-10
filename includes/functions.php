<?php

/**
 * User Groups Functions
 *
 * @package UserGroups/Functions
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

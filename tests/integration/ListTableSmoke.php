<?php

/**
 * Real-WordPress smoke coverage for the user taxonomy list table.
 */

defined( 'ABSPATH' ) || exit( 1 );

function wpug_smoke_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
if ( ! class_exists( 'WP_User_Taxonomy_List_Table' ) ) {
	require_once WP_PLUGIN_DIR . '/wp-user-groups/wp-user-groups/includes/classes/class-user-taxonomy-list-table.php';
}

set_current_screen( 'user-edit' );
wp_set_current_user( 1 );

class WPUG_Smoke_User_Taxonomy extends WP_User_Taxonomy {
	public function render_for_smoke_test( $user, $taxonomy, $terms ) {
		ob_start();
		$this->table_contents( $user, $taxonomy, $terms );
		return ob_get_clean();
	}
}

$controller = new WPUG_Smoke_User_Taxonomy(
	'qa-user-group',
	'users/qa-group',
	array(
		'singular' => 'QA Group',
		'plural'   => 'QA Groups',
		'managed'  => false,
	)
);

$user_id = wp_create_user( 'list-table-user', wp_generate_password(), 'list-table@example.test' );
$term    = wp_insert_term( 'Editors', 'qa-user-group', array( 'slug' => 'editors' ) );

wpug_smoke_assert( ! is_wp_error( $user_id ), 'Could not create smoke-test user.' );
wpug_smoke_assert( ! is_wp_error( $term ), 'Could not create smoke-test term.' );

wp_set_object_terms( $user_id, array( (int) $term['term_id'] ), 'qa-user-group' );

add_filter(
	'manage_edit-qa-user-group_columns',
	function ( $columns ) {
		$columns['qa_marker'] = 'QA marker';
		return $columns;
	}
);
add_filter(
	'manage_qa-user-group_custom_column',
	function ( $display, $column_name, $term_id ) use ( $term ) {
		if ( 'qa_marker' === $column_name ) {
			return ( (int) $term['term_id'] === (int) $term_id ) ? 'custom-column-ok' : 'wrong-term';
		}
		return $display;
	},
	20,
	3
);

$taxonomy = get_taxonomy( 'qa-user-group' );
$terms    = get_terms(
	array(
		'taxonomy'   => 'qa-user-group',
		'hide_empty' => false,
	)
);
$html     = $controller->render_for_smoke_test( get_user_by( 'id', $user_id ), $taxonomy, $terms );

wpug_smoke_assert( false !== strpos( $html, 'wp-list-table' ), 'List table markup is missing.' );
wpug_smoke_assert( false !== strpos( $html, 'QA marker' ), 'Custom taxonomy column heading is missing.' );
wpug_smoke_assert( false !== strpos( $html, 'custom-column-ok' ), 'Custom taxonomy column value is missing.' );
wpug_smoke_assert( false !== strpos( $html, 'name="qa-user-group[]"' ), 'Relationship checkbox is missing.' );
wpug_smoke_assert( false !== strpos( $html, 'wp_user_taxonomy_qa-user-group' ), 'Relationship nonce is missing.' );
wpug_smoke_assert( (bool) preg_match( '/checked=(?:"checked"|\'checked\')/', $html ), 'Selected relationship is not checked.' );

echo "WP User Groups list-table smoke test passed.\n";

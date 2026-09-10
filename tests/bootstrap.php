<?php

declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['wpug_test'] = array();

function wpug_test_call( $name, $arguments ) {
	$GLOBALS['wpug_test']['calls'][ $name ][] = $arguments;

	if (isset($GLOBALS['wpug_test']['callbacks'][ $name ])) {
		return $GLOBALS['wpug_test']['callbacks'][ $name ]( ...$arguments );
	}

	return $GLOBALS['wpug_test']['returns'][ $name ] ?? null;
}

function absint( $value ) { return abs( (int) $value ); }
function wp_get_object_terms( ...$arguments ) { return wpug_test_call( __FUNCTION__, $arguments ); }
function wp_delete_object_term_relationships( ...$arguments ) { return wpug_test_call( __FUNCTION__, $arguments ); }
function wp_set_object_terms( ...$arguments ) { return wpug_test_call( __FUNCTION__, $arguments ); }
function clean_object_term_cache( ...$arguments ) { return wpug_test_call( __FUNCTION__, $arguments ); }
function get_taxonomies( ...$arguments ) { return wpug_test_call( __FUNCTION__, $arguments ); }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
function get_terms( ...$arguments ) { return wpug_test_call( __FUNCTION__, $arguments ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function esc_html__( $text ) { return $text; }
function apply_filters( $hook, $value, ...$arguments ) { return wpug_test_call( __FUNCTION__ . ':' . $hook, array_merge( array( $value ), $arguments ) ) ?? $value; }
function current_user_can( ...$arguments ) { return (bool) wpug_test_call( __FUNCTION__, $arguments ); }
function is_object_in_term( ...$arguments ) { return (bool) wpug_test_call( __FUNCTION__, $arguments ); }
function esc_html_e( $text ) { echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function checked( $checked ) { if ($checked) { echo 'checked="checked"'; } }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function esc_url( $url ) { return $url; }
function wp_nonce_field( $action, $name ) { echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="nonce" />'; }

class WP_Error {}

require_once dirname( __DIR__ ) . '/wp-user-groups/includes/functions/common.php';
require_once dirname( __DIR__ ) . '/wp-user-groups/includes/functions/admin.php';
require_once dirname( __DIR__ ) . '/wp-user-groups/includes/classes/class-user-taxonomy.php';

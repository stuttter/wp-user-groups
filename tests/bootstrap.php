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
function checked( $checked, $current = true, $display = true ) {
	$result = ( (string) $checked === (string) $current ) ? 'checked="checked"' : '';
	if ( $display ) {
		echo $result;
	}
	return $result;
}
function admin_url( $path ) { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function esc_url( $url ) { return $url; }
function wp_nonce_field( $action, $name ) { echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="nonce" />'; }

class WP_Error {}

class WP_List_Table {
	public $items = array();
	protected $_args = array();
	protected $_column_headers = array();

	public function __construct( $args = array() ) {
		$this->_args = $args;
	}

	protected function set_pagination_args( $args ) {}

	protected function get_table_classes() {
		return array( 'widefat', 'fixed', 'striped' );
	}

	protected function display_tablenav( $which ) {}

	protected function single_row_columns( $item ) {
		foreach ( $this->_column_headers[0] as $column_name => $label ) {
			$method = 'column_' . $column_name;
			$value  = method_exists( $this, $method )
				? $this->$method( $item )
				: $this->column_default( $item, $column_name );
			$tag = ( 'cb' === $column_name ) ? 'th' : 'td';
			echo '<' . $tag . ' class="column-' . esc_attr( $column_name ) . '">' . $value . '</' . $tag . '>';
		}
	}

	protected function print_column_headers() {
		static $select_all = 0;
		++$select_all;
		foreach ( $this->_column_headers[0] as $column_name => $label ) {
			if ( 'cb' === $column_name ) {
				echo '<td class="column-cb check-column"><input id="cb-select-all-' . $select_all . '" type="checkbox" /></td>';
			} else {
				echo '<th class="column-' . esc_attr( $column_name ) . '">' . $label . '</th>';
			}
		}
	}

	public function display() {
		echo '<table class="wp-list-table ' . esc_attr( implode( ' ', $this->get_table_classes() ) ) . '"><thead><tr>';
		$this->print_column_headers();
		echo '</tr></thead><tbody>';
		if ( empty( $this->items ) ) {
			echo '<tr><td>';
			$this->no_items();
			echo '</td></tr>';
		} else {
			foreach ( $this->items as $item ) {
				$this->single_row( $item );
			}
		}
		echo '</tbody><tfoot><tr>';
		$this->print_column_headers();
		echo '</tr></tfoot></table>';
	}
}

require_once dirname( __DIR__ ) . '/wp-user-groups/includes/functions/common.php';
require_once dirname( __DIR__ ) . '/wp-user-groups/includes/functions/admin.php';
require_once dirname( __DIR__ ) . '/wp-user-groups/includes/classes/class-user-taxonomy.php';
require_once dirname( __DIR__ ) . '/wp-user-groups/includes/classes/class-user-taxonomy-list-table.php';

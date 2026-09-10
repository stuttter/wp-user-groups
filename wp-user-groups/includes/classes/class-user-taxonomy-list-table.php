<?php

/**
 * User taxonomy relationship list table.
 *
 * @package Plugins/Users/Groups/Classes/ListTable
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_User_Taxonomy_List_Table' ) ) :

/**
 * Displays one user taxonomy's terms on a user profile.
 *
 * @since 2.7.0
 */
class WP_User_Taxonomy_List_Table extends WP_List_Table {

	/**
	 * User taxonomy controller.
	 *
	 * @since 2.7.0
	 *
	 * @var WP_User_Taxonomy
	 */
	protected $user_taxonomy;

	/**
	 * User whose relationships are being displayed.
	 *
	 * @since 2.7.0
	 *
	 * @var WP_User
	 */
	protected $user;

	/**
	 * Registered taxonomy object.
	 *
	 * @since 2.7.0
	 *
	 * @var WP_Taxonomy
	 */
	protected $taxonomy;

	/**
	 * Whether this taxonomy uses the checkbox selection column.
	 *
	 * @since 2.7.0
	 *
	 * @var bool
	 */
	protected $has_bulk_selection = false;

	/**
	 * Cached relationship state keyed by term ID.
	 *
	 * @since 2.7.0
	 *
	 * @var array
	 */
	protected $term_relationships = array();

	/**
	 * Constructor.
	 *
	 * @since 2.7.0
	 *
	 * @param WP_User_Taxonomy $user_taxonomy User taxonomy controller.
	 * @param WP_User          $user          User being edited.
	 * @param WP_Taxonomy      $taxonomy      Registered taxonomy object.
	 * @param array            $terms         Terms to display.
	 */
	public function __construct( $user_taxonomy, $user, $taxonomy, $terms = array() ) {
		$this->user_taxonomy    = $user_taxonomy;
		$this->user             = $user;
		$this->taxonomy         = $taxonomy;
		$this->items            = is_array( $terms ) ? $terms : array();
		$this->has_bulk_selection = ! $user_taxonomy->is_managed() && ! $user_taxonomy->is_exclusive();

		parent::__construct( array(
			'singular' => 'user-group',
			'plural'   => 'user-groups',
			'ajax'     => false,
		) );
	}

	/**
	 * Prepare the fixed set of terms and column metadata.
	 *
	 * @since 2.7.0
	 */
	public function prepare_items() {
		$columns = $this->get_columns();
		$primary = isset( $columns['name'] ) ? 'name' : key( $columns );

		$this->_column_headers = array( $columns, array(), array(), $primary );
		$this->set_pagination_args( array(
			'total_items' => count( $this->items ),
			'per_page'    => max( 1, count( $this->items ) ),
			'total_pages' => 1,
		) );
	}

	/**
	 * Return columns, including taxonomy-specific custom columns.
	 *
	 * @since 2.7.0
	 *
	 * @return array
	 */
	public function get_columns() {
		$selection_column = $this->has_bulk_selection ? 'cb' : 'selection';
		$columns = array(
			$selection_column => '',
			'name'            => esc_html__( 'Name', 'wp-user-groups' ),
			'description'     => esc_html__( 'Description', 'wp-user-groups' ),
			'users'           => esc_html__( 'Users', 'wp-user-groups' ),
		);

		/**
		 * Filters the columns displayed for this user taxonomy.
		 *
		 * This is the same dynamic hook used by the taxonomy terms list table.
		 *
		 * @since 2.7.0
		 *
		 * @param array $columns Column labels keyed by column name.
		 */
		return apply_filters( "manage_edit-{$this->taxonomy->name}_columns", $columns );
	}

	/**
	 * Suppress empty navigation containers around this compact profile table.
	 *
	 * @since 2.7.0
	 *
	 * @param string $which Navigation position.
	 */
	protected function display_tablenav( $which ) {}

	/**
	 * Add the plugin's stable table class.
	 *
	 * @since 2.7.0
	 *
	 * @return array
	 */
	protected function get_table_classes() {
		return array( 'widefat', 'fixed', 'striped', 'user-groups' );
	}

	/**
	 * Render one term row with its current relationship state.
	 *
	 * @since 2.7.0
	 *
	 * @param WP_Term $term Term being displayed.
	 */
	public function single_row( $term ) {
		$active = $this->is_term_active( $term );
		$class  = ( true === $active ) ? 'active' : 'inactive';

		echo '<tr class="' . esc_attr( $class ) . '">';
		$this->single_row_columns( $term );
		echo '</tr>';
	}

	/**
	 * Render a checkbox selection cell.
	 *
	 * @since 2.7.0
	 *
	 * @param WP_Term $term Term being displayed.
	 * @return string
	 */
	protected function column_cb( $term ) {
		return $this->selection_input( $term, 'checkbox' );
	}

	/**
	 * Render the non-bulk selection cell used by radios and managed tables.
	 *
	 * @since 2.7.0
	 *
	 * @param WP_Term $term Term being displayed.
	 * @return string
	 */
	protected function column_selection( $term ) {
		if ( $this->user_taxonomy->is_managed() ) {
			return '';
		}

		return $this->selection_input( $term, 'radio' );
	}

	/**
	 * Build one relationship selection field.
	 *
	 * @since 2.7.0
	 *
	 * @param WP_Term $term Term being displayed.
	 * @param string  $type Input type.
	 * @return string
	 */
	protected function selection_input( $term, $type ) {
		$active = $this->is_term_active( $term );
		$id     = $this->taxonomy->name . '-' . $term->slug;

		return sprintf(
			'<input type="%1$s" name="%2$s[]" id="%3$s" value="%4$s" %5$s/><label class="screen-reader-text" for="%3$s">%6$s</label>',
			esc_attr( $type ),
			esc_attr( $this->taxonomy->name ),
			esc_attr( $id ),
			esc_attr( $term->slug ),
			checked( $active, true, false ),
			esc_html( $term->name )
		);
	}

	/**
	 * Return and cache whether the displayed user has a term relationship.
	 *
	 * @since 2.7.0
	 *
	 * @param WP_Term $term Term being displayed.
	 * @return bool
	 */
	protected function is_term_active( $term ) {
		$term_id = (int) $term->term_id;

		if ( ! array_key_exists( $term_id, $this->term_relationships ) ) {
			$this->term_relationships[ $term_id ] = is_object_in_term( $this->user->ID, $this->taxonomy->name, $term->slug );
		}

		return $this->term_relationships[ $term_id ];
	}

	/**
	 * Render the primary term-name column.
	 *
	 * @since 2.7.0
	 *
	 * @param WP_Term $term Term being displayed.
	 * @return string
	 */
	protected function column_name( $term ) {
		return '<strong>' . esc_html( $term->name ) . '</strong>'
			. '<div class="row-actions">'
			. $this->user_taxonomy->get_term_row_actions( $this->taxonomy, $term )
			. '</div>';
	}

	/**
	 * Render the description column.
	 *
	 * @since 2.7.0
	 *
	 * @param WP_Term $term Term being displayed.
	 * @return string
	 */
	protected function column_description( $term ) {
		return ! empty( $term->description ) ? esc_html( $term->description ) : '&#8212;';
	}

	/**
	 * Render native and custom taxonomy columns through the established hook.
	 *
	 * @since 2.7.0
	 *
	 * @param WP_Term $term        Term being displayed.
	 * @param string  $column_name Column name.
	 * @return string
	 */
	protected function column_default( $term, $column_name ) {
		/**
		 * Filters a user taxonomy custom column's display value.
		 *
		 * This is the same dynamic hook used by the taxonomy terms list table.
		 *
		 * @since 2.7.0
		 *
		 * @param string $display     Existing display value.
		 * @param string $column_name Column name.
		 * @param int    $term_id     Term ID.
		 */
		return (string) apply_filters(
			"manage_{$this->taxonomy->name}_custom_column",
			'',
			$column_name,
			$term->term_id
		);
	}

	/**
	 * Display the taxonomy-specific empty state.
	 *
	 * @since 2.7.0
	 */
	public function no_items() {
		echo esc_html( $this->taxonomy->labels->not_found );
	}
}

endif;

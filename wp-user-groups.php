<?php

/**
 * Plugin Name: WP User Groups
 * Plugin URI:  https://wordpress.org/plugins/wp-user-groups/
 * Description: Group users together with taxonomies & terms.
 * Author:      John James Jacoby
 * Version:     0.1.0
 * Author URI:  https://profiles.wordpress.org/johnjamesjacoby/
 * License:     GPL2
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Register user groups
 *
 * @since 0.1.0
 */
function wp_register_default_user_taxonomies() {
	new WP_User_Taxonomy( 'group', 'users/group', array( 'singular' => 'Group', 'plural' => 'Groups' ) );
	new WP_User_Taxonomy( 'type',  'users/type',  array( 'singular' => 'Type',  'plural' => 'Types'  ) );
}
add_action( 'init', 'wp_register_default_user_taxonomies' );

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

	clean_object_term_cache( $user_id, $taxonomy );
}

/** Main Class ****************************************************************/

if ( ! class_exists( 'WP_User_Taxonomy' ) ) :
/**
 * The main User Taxonomy class
 *
 * @since 0.1.0
 */
class WP_User_Taxonomy {

	public $taxonomy = '';
	public $slug = '';
	public $args = array();
	public $labels = array();

	/**
	 * Main constructor
	 *
	 * @since 0.1.0
	 *
	 * @param  string  $taxonomy
	 * @param  string  $slug
	 * @param  array   $args
	 * @param  array   $labels
	 */
	public function __construct( $taxonomy = '', $slug = '', $args = array(), $labels = array() ) {

		// Bail if no taxonomy is passed
		if ( empty( $taxonomy ) ) {
			return;
		}

		// Set the taxonomy
		$this->taxonomy = sanitize_key( $taxonomy );
		$this->slug     = sanitize_text_field( $slug );
		$this->args     = $args;
		$this->labels   = $labels;

		// Label helpers
		$this->tax_singular     = $args['singular'];
		$this->tax_plural       = $args['plural'];
		$this->tax_singular_low = strtolower( $this->tax_singular );
		$this->tax_plural_low   = strtolower( $this->tax_plural   );

		// Register the taxonomy
		$this->register_user_taxonomy();

		// Bulk edit
		add_action('admin_init', array( $this, 'bulk_edit_action' ) );
		add_filter('views_users', array( $this, 'bulk_edit' ) );

		// Include users by taxonomy term in users.php
		add_action( 'pre_get_users', array( $this, 'pre_get_users' ) );

		// Custom list-table views
		add_filter( 'views_users', array( $this, 'list_table_views' ) );

		// Column styling
		add_action( 'admin_head', array( $this, 'admin_head'     ) );
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );

		// Taxonomy columns
		add_action( "manage_{$this->taxonomy}_custom_column", array( $this, 'manage_custom_column'     ), 10, 3 );
		add_filter( "manage_edit-{$this->taxonomy}_columns",  array( $this, 'manage_edit_users_column' ) );

		// User columns
		add_filter( 'manage_users_columns',       array( $this, 'add_manage_users_columns' ), 15, 1 );
		add_action( 'manage_users_custom_column', array( $this, 'user_column_data'         ), 15, 3 );

		// Update the groups when the edit user page is updated
		add_action( 'personal_options_update',  array( $this, 'save_terms_for_user' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_terms_for_user' ) );

		// Add section to the edit user page in the admin to select group
		add_action( 'show_user_profile', array( $this, 'edit_user_relationships' ), 99 );
		add_action( 'edit_user_profile', array( $this, 'edit_user_relationships' ), 99 );

		// Cleanup stuff
		add_action( 'delete_user',   array( $this, 'delete_term_relationships' ) );
		add_filter( 'sanitize_user', array( $this, 'disable_username'          ) );
	}

	/**
	 * Add the administration page for this taxonomy
	 *
	 * @since 0.1.0
	 */
	public function add_admin_page() {

		// Setup the URL
		$tax = get_taxonomy( $this->taxonomy );

		// No UI
		if ( false === $tax->show_ui ) {
			return;
		}

		// URL for the taxonomy
		$url = add_query_arg( array( 'taxonomy' => $tax->name ), 'edit-tags.php' );

		// Add page to users
		add_users_page(
			esc_attr( $tax->labels->menu_name ),
			esc_attr( $tax->labels->menu_name ),
			$tax->cap->manage_terms,
			$url
		);

		// Hook into early actions to load custom CSS and our init handler.
		add_action( 'load-users.php',     array( $this, 'admin_load' ) );
		add_action( 'load-edit-tags.php', array( $this, 'admin_load' ) );
		add_action( 'load-edit-tags.php', array( $this, 'admin_menu_highlight' ) );
	}

	/**
	 * This tells WordPress to highlight the "Users" menu item when viewing a
	 * user taxonomy.
	 *
	 * @since 0.1.0
	 *
	 * @global string $plugin_page
	 */
	public function admin_menu_highlight() {
		global $plugin_page;

		// Set plugin page to "users.php" to get highlighting to be correct
		if ( isset( $_GET['taxonomy'] ) && ( $_GET['taxonomy'] === $this->taxonomy ) ) {
			$plugin_page = 'users.php';
		}
	}

	/**
	 * Filter the body class
	 *
	 * @since 0.1.0
	 */
	public function admin_load() {
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
	}

	/**
	 * Add a class for this taxonomy
	 *
	 * @since 0.1.0
	 *
	 * @param   string $classes
	 * @return  string
	 */
	public function admin_body_class( $classes = '' ) {

		// Add a body class for this taxonomy if it's currently selected
		if ( isset( $_GET[ $this->taxonomy ] ) ) {
			$classes .= "tax-{$this->taxonomy}";
		}

		// Return maybe modified class
		return $classes;
	}

	/**
	 * Stylize custom columns
	 *
	 * @since 0.1.0
	 */
	public function admin_head() {
	?>

		<style type="text/css">
			.column-users {
				width: 10%;
				text-align: center;
			}
			.column-<?php echo esc_attr( $this->taxonomy ); ?> {
				width: 10%;
			}
			body.users-php.tax-<?php echo esc_attr( $this->taxonomy ); ?> .wrap > h1 {
				display: none;
			}
			.user-tax-form fieldset {
				margin: 8px 10px 0 0;
			}
			.subsubsub + form + br.clear {
				display: none;
			}
			.tax-actions {
				margin-bottom: 5px;
			}
		</style>

	<?php
	}

	/**
	 * Save terms for a user for this taxonomy
	 *
	 * @since 0.1.0
	 *
	 * @param int $user_id
	 */
	public function save_terms_for_user( $user_id = 0 ) {
		wp_set_terms_for_user( $user_id, $this->taxonomy );
	}

	/**
	 * Update the term count for a user and taxonomy
	 *
	 * @since 0.1.0
	 *
	 * @param int $user_id
	 */
	public function update_term_user_count( $terms = array(), $taxonomy = '' ) {
		global $wpdb;

		// Fallback to this taxonomy
		if ( empty( $taxonomy ) ) {
			$taxonomy = $this->taxonomy;
		}

		// Loop through terms and update individual counts
		foreach ( (array) $terms as $term ) {

			// Get the count
			$sql     = "SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d";
			$prepare = $wpdb->prepare( $sql, $term );
			$count   = $wpdb->get_var( $prepare );

			// Core action
			do_action( 'edit_term_taxonomy', $term, $taxonomy );

			// Update the DB
			$wpdb->update(
				$wpdb->term_taxonomy,
				compact( 'count' ),
				array(
					'term_taxonomy_id' => $term
				)
			);

			// Core action
			do_action( 'edited_term_taxonomy', $term, $taxonomy );
		}
	}

	/**
	 * Manage columns for user taxonomies
	 *
	 * @since 0.1.0
	 *
	 * @param   array $columns
	 * @return  array
	 */
	public function manage_edit_users_column( $columns = array() ) {

		// Unset the "Posts" column
		unset( $columns['posts'] );

		// Add the "Users" column
		$columns['users'] = esc_html__( 'Users', 'wp-user-terms' );

		// Return modified columns
		return $columns;
	}

	/**
	 * Output the data for the "Users" column when viewing user taxonomies
	 *
	 * @since 0.1.0
	 *
	 * @param string $display
	 * @param string $column
	 * @param string $term_id
	 */
	public function manage_custom_column( $display = false, $column = '', $term_id = 0 ) {
		if ( 'users' === $column ) {
			$term  = get_term( $term_id, $this->taxonomy );
			$args  = array( $this->taxonomy => $term->slug );
			$users = admin_url( 'users.php' );
			$url   = add_query_arg( $args, $users );
			$text  = number_format_i18n( $term->count );
			echo '<a href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>';
		}
	}

	/**
	 * Output a "Relationships" section to show off taxonomy groupings
	 *
	 * @since 0.1.0
	 *
	 * @param  mixed  $user
	 */
	public function edit_user_relationships( $user = false ) {

		$tax = get_taxonomy( $this->taxonomy );

		// Make sure the user can assign terms of the group taxonomy before proceeding.
		if ( ! current_user_can( 'edit_user', $user->ID ) || ! current_user_can( $tax->cap->assign_terms ) ) {
			return;
		}

		// Get the terms of the taxonomy.
		$terms = get_terms( $this->taxonomy, array(
			'hide_empty' => false
		) ); ?>

		<?php

		// Check for a global, because this is a huge dumb hack
		if ( ! isset( $GLOBALS['wp_user_taxonomies'] ) ) : ?>

			<h3 id="<?php echo esc_html( $this->taxonomy ); ?>">
				<?php esc_html_e( 'Relationships', 'wp-user-terms' ); ?>
			</h3>

			<?php

			// Set big dumb hack global to true
			$GLOBALS['wp_user_taxonomies'] = true;

		endif; ?>

		<table class="form-table">
			<tr>
				<th>
					<label for="<?php echo esc_html( $this->taxonomy ); ?>">
						<?php echo esc_html( $tax->labels->name ); ?>
					</label>
				</th>
				<td><?php

					// If there are any terms available, loop through them and display checkboxes.
					if ( ! empty( $terms ) ) : ?>

						<ul>

							<?php foreach ( $terms as $term ) : ?>

								<li>
									<input type="checkbox" name="<?php echo esc_attr( $this->taxonomy ); ?>[]" id="<?php echo esc_attr( $this->taxonomy ); ?>-<?php echo esc_attr( $term->slug ); ?>" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( is_object_in_term( $user->ID, $this->taxonomy, $term->slug ) ); ?> />
									<label for="<?php echo esc_attr( $this->taxonomy ); ?>-<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></label>
								</li>

							<?php endforeach; ?>

						</ul>

					<?php

					// If there are no user groups
					else :
						echo esc_html( $tax->labels->not_found );
					endif;

				?></td>
			</tr>
		</table>
	<?php
	}

	/**
	 * Disallow taxonomy as a username
	 *
	 * @since 0.1.0
	 *
	 * @param   string  $username
	 * @return  string
	 */
	public function disable_username( $username = '' ) {

		// Set username to empty if it's this taxonomy
		if ( $this->taxonomy === $username ) {
			$username = '';
		}

		// Return possible emptied username
		return $username;
	}

	/**
	 * Delete term relationships
	 *
	 * @since 0.1.0
	 *
	 * @param int $user_id
	 */
	public function delete_term_relationships( $user_id = 0 ) {
		wp_delete_object_term_relationships( $user_id, $this->taxonomy );
	}

	/** Post Type *************************************************************/

	/**
	 * Parse taxonomy labels
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	protected function parse_labels() {
		return wp_parse_args( $this->labels, array(
			'menu_name'                  => $this->tax_plural,
			'name'                       => $this->tax_plural,
			'singular_name'              => $this->tax_singular,
			'search_items'               => sprintf( 'Search %s',                $this->tax_plural ),
			'popular_items'              => sprintf( 'Popular %s',               $this->tax_plural ),
			'all_items'                  => sprintf( 'All %s',                   $this->tax_plural ),
			'parent_item'                => sprintf( 'Parent %s',                $this->tax_singular ),
			'parent_item_colon'          => sprintf( 'Parent %s:',               $this->tax_singular ),
			'edit_item'                  => sprintf( 'Edit %s',                  $this->tax_singular ),
			'view_item'                  => sprintf( 'View %s',                  $this->tax_singular ),
			'update_item'                => sprintf( 'Update %s',                $this->tax_singular ),
			'add_new_item'               => sprintf( 'Add New %s',               $this->tax_singular ),
			'new_item_name'              => sprintf( 'New %s Name',              $this->tax_singular ),
			'separate_items_with_commas' => sprintf( 'Separate %s with commas',  $this->tax_plural_low ),
			'add_or_remove_items'        => sprintf( 'Add or remove %s',         $this->tax_plural_low ),
			'choose_from_most_used'      => sprintf( 'Choose from most used %s', $this->tax_plural_low ),
			'not_found'                  => sprintf( 'No %s found',              $this->tax_plural_low ),
			'no_item'                    => sprintf( 'No %s',                    $this->tax_singular ),
			'no_items'                   => sprintf( 'No %s',                    $this->tax_plural_low )
		) );
	}

	/**
	 * Parse taxonomy options
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	protected function parse_options() {
		return wp_parse_args( $this->args, array(
			'hierarchical' => true,
			'public'       => false,
			'show_ui'      => true,
			'meta_box_cb'  => '',
			'labels'       => $this->parse_labels(),
			'rewrite'      => array(
				'with_front'   => false,
				'slug'         => $this->slug,
				'hierarchical' => true
			),
			'capabilities' => array(
				'manage_terms' => 'edit_users',
				'edit_terms'   => 'edit_users',
				'delete_terms' => 'edit_users',
				'assign_terms' => 'read',
			),

			// @see _update_post_term_count()
			'update_count_callback' => array( $this, 'update_term_user_count' )
		) );
	}

	/**
	 * Register the taxonomy
	 *
	 * @since 0.1.0
	 */
	public function register_user_taxonomy() {
		register_taxonomy(
			$this->taxonomy,
			'user',
			$this->parse_options()
		);
	}

	/** Bulk Edit *************************************************************/

	/**
	 * Handle bulk editing of users
	 *
	 * @since 0.1.0
	 */
	public function bulk_edit_action() {

		// Bail if not a bulk edit request
		if ( ! isset( $_REQUEST[ $this->taxonomy . '-submit' ] ) || empty( $_POST[ $this->taxonomy ] ) ) {
			return;
		}

		check_admin_referer( "bulk-edit-{$this->taxonomy}" );

		// Get an array of users from the string
		parse_str( urldecode( $_POST[ $this->taxonomy . '-users'] ), $users );

		// Bail if no users to edit
		if ( empty( $users['users'] ) ) {
			return;
		}

		$users    = $users['users'];
		$action   = strstr( $_POST[ $this->taxonomy ], '-', true );
		$term     = str_replace( $action, '', $_POST[ $this->taxonomy ] );

		// Loop through users
		foreach ( $users as $user ) {

			// Skip if current user cannot edit this user
			if ( ! current_user_can( 'edit_user', $user ) ) {
				continue;
			}

			// Get term slugs of user for this taxonomy
			$terms        = wp_get_terms_for_user( $user, $this->taxonomy );
			$update_terms = wp_list_pluck( $terms, 'slug' );

			// Adding
			if ( 'add' === $action ) {
				if ( ! in_array( $term, $update_terms ) ) {
					$update_terms[] = $term;
				}

			// Removing
			} elseif ( 'remove' === $action ) {
				$index = array_search( $term, $update_terms );
				if ( isset( $update_terms[ $index ] ) ) {
					unset( $update_terms[ $index ] );
				}
			}

			// Delete all groups if they're empty
			if ( empty( $update_terms ) ) {
				$update_terms = null;
			}

			// Update terms for users
			if ( $update_terms !== $terms ) {
				wp_set_terms_for_user( $user, $this->taxonomy, $update_terms, true );
			}
		}

		// Success
		wp_safe_redirect( admin_url( 'users.php' ) );
		die;
	}

	/**
	 * Output the bulk edit markup
	 *
	 * @since 0.1.0
	 *
	 * @param   type  $views
	 * @return  type
	 */
	public function bulk_edit( $views = array() ) {

		// Bail if user cannot edit other users
		if ( ! current_user_can( 'edit_users' ) ) {
			return $views;
		}

		// Get taxonomy & terms
		$tax   = get_taxonomy( $this->taxonomy );
		$terms = get_terms( $this->taxonomy, array(
			'hide_empty' => false
		) ); ?>

		<form method="post" class="user-tax-form">
			<fieldset class="alignleft">
				<legend class="screen-reader-text"><?php esc_html_e( 'Update Groups', 'wp-user-terms' ); ?></legend>

				<input name="<?php echo esc_attr( $this->taxonomy ); ?>-users" value="" type="hidden" id="<?php echo esc_attr( $this->taxonomy ); ?>-bulk-users" />

				<label for="<?php echo esc_attr( $this->taxonomy ); ?>-select" class="screen-reader-text">
					<?php echo esc_html( $tax->labels->name ); ?>
				</label>

				<select class="tax-picker" name="<?php echo esc_attr( $this->taxonomy ); ?>" id="<?php echo esc_attr( $this->taxonomy ); ?>-select">
					<option value=""><?php printf( esc_html__( '%s Bulk Actions', 'wp-user-groups' ), $tax->labels->name ); ?></option>

					<optgroup label="<?php esc_html_e( 'Add', 'wp-user-groups' ); ?>">

						<?php foreach ( $terms as $term ) : ?>

							<option value="add-<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>

						<?php endforeach; ?>

					</optgroup>

					<optgroup label="<?php esc_html_e( 'Remove', 'wp-user-groups' ); ?>">

						<?php foreach ( $terms as $term ) : ?>

							<option value="remove-<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>

						<?php endforeach; ?>

					</optgroup>

				</select>

				<?php wp_nonce_field( "bulk-edit-{$this->taxonomy}" ); ?>

				<?php submit_button( esc_html__( 'Apply' ), 'action', $this->taxonomy . '-submit', false ); ?>

			</fieldset>
		</form>

		<script type="text/javascript">
			jQuery( document ).ready( function( $ ) {
				$( '.tablenav.bottom' ).remove();
				$( '.wrap' ).append( $( '.user-tax-form' ) );
				$( '.wrap' ).on( 'submit', '.user-tax-form', function() {
					var users = $( '.wp-list-table.users .check-column input:checked' ).serialize();
					$( '#<?php echo esc_attr( $this->taxonomy ); ?>-bulk-users' ).val( users );
				} );
			} );
		</script>

		<?php

		return $views;
	}

	/**
	 * Output an additional list-table view section that replaces the "h1" when
	 * viewing a single user relationship term.
	 *
	 * @since 0.1.0
	 *
	 * @param  array $views
	 * @return array
	 */
	public function list_table_views( $views = array() ) {

		// Get tax & terms
		$terms   = get_terms( $this->taxonomy, array( 'hide_empty' => false ) );
		$slugs   = wp_list_pluck( $terms, 'slug' );
		$current = isset( $_GET[ $this->taxonomy ] ) ? sanitize_key( $_GET[ $this->taxonomy ] ) : '';
		$viewing = array_search( $current, $slugs, true );

		// Viewing a specific taxonomy term
		if ( false !== $viewing ) {

			// Assemble the "Edit" h1 link
			$edit = admin_url( 'edit-tags.php' );
			$args = array(
				'action'   => 'edit',
				'taxonomy' => $this->taxonomy,
				'tag_ID'   => $terms[ $viewing ]->term_id,
			);
			$url = add_query_arg( $args, $edit ); ?>

			<div id="<?php echo esc_attr( $this->taxonomy ); ?>-header">
				<h1>
					<?php esc_html_e( 'Users', 'wp-user-taxonomies' ); ?>

					<?php if ( current_user_can( 'create_users' ) ) : ?>

						<a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>" class="page-title-action"><?php echo esc_html_x( 'Add New', 'user', 'wp-user-taxonomies' ); ?></a>

					<?php elseif ( is_multisite() && current_user_can( 'promote_users' ) ) : ?>

						<a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>" class="page-title-action"><?php echo esc_html_x( 'Add Existing', 'user', 'wp-user-taxonomies' ); ?></a>

					<?php endif; ?>

					<span class="subtitle"><?php printf( esc_html__( 'Viewing users of %s: %s', 'wp-user-taxonomies' ), $this->tax_singular_low, '<a href="' . esc_url( $url ) . '">' . $terms[ $viewing ]->name . '</a>' ); ?></span>
				</h1>
				<?php echo wpautop( $terms[ $viewing ]->description ); ?>
			</div>
			<div class="clear"></div>

		<?php
		}

		return $views;
	}

	/**
	 * Modify the users.php query
	 *
	 * @since 0.1.0
	 *
	 * @global  string  $pagenow
	 *
	 * @param   object  $user_query
	 */
	public function pre_get_users( $user_query ) {
		global $pagenow;

		// Bail if not a users query
		if ( 'users.php' !== $pagenow ) {
			return;
		}

		if ( ! empty( $_GET[ $this->taxonomy ] ) ) {

			// Sanitize taxonomies
			$groups = array_map( 'sanitize_key', explode( ',', $_GET[ $this->taxonomy ] ) );
			$ids    = array();

			// Get terms
			foreach ( $groups as $group ) {
				$term     = get_term_by( 'slug', $group, $this->taxonomy );
				$user_ids = get_objects_in_term( $term->term_id, $this->taxonomy );
				$ids      = array_merge( $user_ids, $ids );
			}

			// Set IDs to be included
			$user_query->query_vars['include'] = $user_ids;
		}
	}

	/**
	 * Generated user taxonomy query SQL
	 *
	 * @since 0.1.0
	 *
	 * @param  object  $user_query
	 */
	public function user_tax_query( $user_query = '' ) {
		return get_tax_sql( $user_query->tax_query, $GLOBALS['wpdb']->users, 'ID' );
	}

	/**
	 * Get links to user taxonomy terms
	 *
	 * @since 0.1.0
	 *
	 * @param  mixed  $user
	 * @param  string $page
	 *
	 * @return string
	 */
	private function get_user_term_links( $user, $page = null ) {

		// Get terms for user and this taxonomy
		$terms = wp_get_terms_for_user( $user, $this->taxonomy );

		// Bail if user has no terms
		if ( empty( $terms ) ) {
			return false;
		}

		$in  = array();
		$url = admin_url( 'users.php' );

		// Loop through terms
		foreach ( $terms as $term ) {
			$args = array( $this->taxonomy => $term->slug );
			$href = empty( $page )
				? add_query_arg( $args, $url  )
				: add_query_arg( $args, $page );

			// Add link to array
			$in[] = '<a href="' . esc_url( $href ) . '" title="' . esc_attr( $term->description ) . '">' . esc_html( $term->name ) . '</a>';
		}

		return implode( ', ', $in );
	}

	/**
	 * Add taxonomy links for a column
	 *
	 * @since 0.1.0
	 *
	 * @param  string $value
	 * @param  string $column_name
	 * @param  string $user_id
	 * @return string
	 */
	public function user_column_data( $value = '', $column_name = '', $user_id = 0 ) {

		// Only for this column name
		if ( $column_name === $this->taxonomy ) {

			// Get term links
			$links = $this->get_user_term_links( $user_id );

			// Use links
			if ( ! empty( $links ) ) {
				$value = $links;

			// No links
			} else {
				$value = '&#8212;';
			}
		}

		// Return possibly modified value
		return $value;
	}

	/**
	 * Add the label to the table header
	 *
	 * @since 0.1.0
	 *
	 * @param   array $defaults
	 *
	 * @return  array
	 */
	public function add_manage_users_columns( $defaults = array() ) {

		// Get the taxonomy
		$tax = get_taxonomy( $this->taxonomy );

		// Bail if no UI
		if ( false === $tax->show_ui ) {
			return $defaults;
		}

		// Add the taxonomy
		$defaults[ $this->taxonomy ] = $tax->labels->name;

		// Return columns
		return $defaults;
	}
}
endif;

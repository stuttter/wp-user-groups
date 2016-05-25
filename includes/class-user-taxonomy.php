<?php

/**
 * User Groups Taxonomy
 *
 * @package Plugins/Users/Groups/Classes/Taxonomy
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_User_Taxonomy' ) ) :
/**
 * The main User Taxonomy class
 *
 * @since 0.1.0
 */
class WP_User_Taxonomy {

	/**
	 * The unique ID to use for the taxonomy type
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	public $taxonomy = '';

	/**
	 * The URL friendly slug to use for the taxonomy
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	public $slug = '';

	/**
	 * Array of taxonomy properties
	 *
	 * Use the custom `singular` and `plural` arguments to let this class
	 * generate labels for you. Note that labels cannot be translated using
	 * this method, so if you need different languages, use the `$labels`
	 * array below.
	 *
	 * @since 0.1.0
	 *
	 * @var array
	 */
	public $args = array();

	/**
	 * Array of taxonomy labels, if you'd like to customize them completely
	 *
	 * @since 0.1.0
	 *
	 * @var array
	 */
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

		/** Class Variables ***************************************************/

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

		// Hook into actions & filters
		$this->hooks();
	}

	/**
	 * Hook in to actions & filters
	 *
	 * @since 0.1.1
	 */
	protected function hooks() {

		// Bulk edit
		add_action( 'admin_init',  array( $this, 'bulk_edit_action' ) );
		add_filter( 'views_users', array( $this, 'bulk_edit'        ) );

		// Include users by taxonomy term in users.php
		add_action( 'pre_get_users', array( $this, 'pre_get_users' ) );

		// Custom list-table views
		add_filter( 'views_users', array( $this, 'list_table_views' ) );

		// Column styling
		add_action( 'admin_head', array( $this, 'admin_head'     ) );
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );

		// WP User Profile support
		add_action( 'wp_user_profiles_add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );

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
		add_action( 'load-term.php',      array( $this, 'admin_menu_highlight' ) );
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

		// Compile the style
		$style = "
			.column-{$this->taxonomy} {
				width: 10%;
			}
			body.users-php.tax-{$this->taxonomy} .wrap > h1 {
				display: none;
			}";

		// Add inline style
		wp_add_inline_style( 'wp_user_groups', $style );
	}

	/**
	 * Metaboxes for profile sections
	 *
	 * @since 0.1.6
	 */
	public function add_meta_box( $type = '' ) {

		// Get hookname
		$hooks = wp_user_profiles_get_section_hooknames( 'groups' );

		// Bail if not the correct type
		if ( ! in_array( $type, $hooks, true ) ) {
			return;
		}

		// Get the taxonomy
		$tax     = get_taxonomy( $this->taxonomy );
		$user_id = ! empty( $_GET['user_id'] )
			? (int) $_GET['user_id']
			: get_current_user_id();

		// Make sure the user can assign terms of the group taxonomy before proceeding.
		if ( ! current_user_can( 'edit_user', $user_id ) || ! current_user_can( $tax->cap->assign_terms ) ) {
			return;
		}

		// Bail if no UI for taxonomy
		if ( false === $tax->show_ui ) {
			return;
		}

		// Get the terms of the taxonomy.
		$terms = get_terms( $this->taxonomy, array(
			'hide_empty' => false
		) );

		// Maybe add the metabox
		add_meta_box(
			'wp_user_taxonomy_' . $this->taxonomy,
			$tax->label,
			array( $this, 'user_profile_metabox' ),
			$hooks[0],
			'normal',
			'default',
			array(
				'user_id' => $user_id,
				'tax'     => $tax,
				'terms'   => $terms
			)
		);
	}

	/**
	 * Save terms for a user for this taxonomy
	 *
	 * @since 0.1.0
	 *
	 * @param int $user_id
	 */
	public function save_terms_for_user( $user_id = 0 ) {

		// Additional checks if User Profiles is active
		if ( function_exists( 'wp_user_profiles_get_section_hooknames' ) ) {

			// Bail if no page
			if ( empty( $_GET['page'] ) ) {
				return;
			}

			// Bail if not saving this section
			if ( sanitize_key( $_GET['page'] ) !== 'groups' ) {
				return;
			}
		}

		// Set terms for user
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

		// Fallback to this taxonomy
		if ( empty( $taxonomy ) ) {
			$taxonomy = $this->taxonomy;
		}

		// Update counts
		_update_generic_term_count( $terms, $taxonomy );
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
		$columns['users'] = esc_html__( 'Users', 'wp-user-groups' );

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

		// Users column gets custom content
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

		// Bail if no UI for taxonomy
		if ( false === $tax->show_ui ) {
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
				<?php esc_html_e( 'Relationships', 'wp-user-groups' ); ?>
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
				<td>
					<?php $this->table_contents( $user, $tax, $terms ); ?>
				</td>
			</tr>
		</table>

	<?php
	}

	/**
	 * Output metabox for user profiles
	 *
	 * @since 0.1.6
	 */
	public function user_profile_metabox( $user = null, $args = array() ) {
		$this->table_contents( $user, $args['args']['tax'], $args['args']['terms'] );
	}

	/**
	 * Output metabox contents
	 *
	 * @since 0.1.6
	 */
	protected function table_contents( $user, $tax, $terms ) {
		?>

		<table class="wp-list-table widefat fixed striped user-groups">
			<thead>
				<tr>
					<td id="cb" class="manage-column column-cb check-column">
						<label class="screen-reader-text" for="cb-select-all-1"><?php esc_html_e( 'Select All', 'wp-user-groups' ); ?></label>
						<input id="cb-select-all-1" type="checkbox">
					</td>
					<th scope="col" class="manage-column column-name column-primary"><?php esc_html_e( 'Name', 'wp-user-groups' ); ?></th>
					<th scope="col" class="manage-column column-description"><?php esc_html_e( 'Description', 'wp-user-groups' ); ?></th>
					<th scope="col" class="manage-column column-users"><?php esc_html_e( 'Users', 'wp-user-groups' ); ?></th>
				</tr>
			</thead>
			<tbody>

				<?php if ( ! empty( $terms ) ) :

					foreach ( $terms as $term ) :
						$active = is_object_in_term( $user->ID, $this->taxonomy, $term->slug ); ?>

						<tr class="<?php echo ( true === $active ) ? 'active' : 'inactive'; ?>">
							<th scope="row" class="check-column">
								<input type="checkbox" name="<?php echo esc_attr( $this->taxonomy ); ?>[]" id="<?php echo esc_attr( $this->taxonomy ); ?>-<?php echo esc_attr( $term->slug ); ?>" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( $active ); ?> />
								<label for="<?php echo esc_attr( $this->taxonomy ); ?>-<?php echo esc_attr( $term->slug ); ?>"></label>
							</th>
							<td class="column-primary">
								<strong><?php echo esc_html( $term->name ); ?></strong>
								<div class="row-actions">
									<?php echo $this->row_actions( $tax, $term ); ?>
								</div>
							</td>
							<td class="column-description"><?php echo ! empty( $term->description ) ? esc_html( $term->description ) : '&#8212;'; ?></td>
							<td class="column-users"><?php echo esc_html( $term->count ); ?></td>
						</tr>

					<?php

					endforeach;

				// If there are no user groups
				else : ?>

					<tr>
						<td colspan="4">

							<?php echo esc_html( $tax->labels->not_found ); ?>

						</td>
					</tr>

				<?php endif; ?>

			</tbody>
			<tfoot>
				<tr>
					<td class="manage-column column-cb check-column">
						<label class="screen-reader-text" for="cb-select-all-2"><?php esc_html_e( 'Select All', 'wp-user-groups' ); ?></label>
						<input id="cb-select-all-2" type="checkbox">
					</td>
					<th scope="col" class="manage-column column-name column-primary"><?php esc_html_e( 'Name', 'wp-user-groups' ); ?></th>
					<th scope="col" class="manage-column column-description"><?php esc_html_e( 'Description', 'wp-user-groups' ); ?></th>
					<th scope="col" class="manage-column column-users"><?php esc_html_e( 'Users', 'wp-user-groups' ); ?></th>
				</tr>
			</tfoot>
		</table>

		<?php
	}

	/**
	 * Output row actions when editing a user
	 *
	 * @since 0.1.1
	 *
	 * @param object $term
	 */
	protected function row_actions( $tax = array(), $term = false ) {
		$actions = array();

		// View users
		if ( current_user_can( 'list_users' ) ) {
			$args      = array( $tax->name => $term->slug );
			$users     = admin_url( 'users.php' );
			$url       = add_query_arg( $args, $users );
			$actions[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'View', 'wp-user-groups' ) . '</a>';
		}

		// Edit term
		if ( current_user_can( $tax->cap->assign_terms ) ) {
			$args      = array( 'action' => 'edit', 'taxonomy' => $tax->name, 'tag_ID' => $term->term_id, 'post_type' => 'post' );
			$edit_tags = admin_url( 'edit-tags.php' );
			$url       = add_query_arg( $args, $edit_tags );
			$actions[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Edit', 'wp-user-groups' ) . '</a>';
		}

		// Filter
		$actions = apply_filters( 'wp_user_groups_row_actions', $actions, $tax, $term, $this );

		return implode( ' | ', $actions );
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
	 * Register the taxonomy
	 *
	 * @since 0.1.0
	 */
	protected function register_user_taxonomy() {
		register_taxonomy(
			$this->taxonomy,
			'user',
			$this->parse_options()
		);
	}

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
			'search_items'               => sprintf( __( 'Search %s', 'wp-user-groups' ),                $this->tax_plural ),
			'popular_items'              => sprintf( __( 'Popular %s', 'wp-user-groups' ),               $this->tax_plural ),
			'all_items'                  => sprintf( __( 'All %s', 'wp-user-groups' ),                   $this->tax_plural ),
			'parent_item'                => sprintf( __( 'Parent %s', 'wp-user-groups' ),                $this->tax_singular ),
			'parent_item_colon'          => sprintf( __( 'Parent %s:', 'wp-user-groups' ),               $this->tax_singular ),
			'edit_item'                  => sprintf( __( 'Edit %s', 'wp-user-groups' ),                  $this->tax_singular ),
			'view_item'                  => sprintf( __( 'View %s', 'wp-user-groups' ),                  $this->tax_singular ),
			'update_item'                => sprintf( __( 'Update %s', 'wp-user-groups' ),                $this->tax_singular ),
			'add_new_item'               => sprintf( __( 'Add New %s', 'wp-user-groups' ),               $this->tax_singular ),
			'new_item_name'              => sprintf( __( 'New %s Name', 'wp-user-groups' ),              $this->tax_singular ),
			'separate_items_with_commas' => sprintf( __( 'Separate %s with commas', 'wp-user-groups' ),  $this->tax_plural_low ),
			'add_or_remove_items'        => sprintf( __( 'Add or remove %s', 'wp-user-groups' ),         $this->tax_plural_low ),
			'choose_from_most_used'      => sprintf( __( 'Choose from most used %s', 'wp-user-groups' ), $this->tax_plural_low ),
			'not_found'                  => sprintf( __( 'No %s found', 'wp-user-groups' ),              $this->tax_plural_low ),
			'no_item'                    => sprintf( __( 'No %s', 'wp-user-groups' ),                    $this->tax_singular ),
			'no_items'                   => sprintf( __( 'No %s', 'wp-user-groups' ),                    $this->tax_plural_low )
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
			'user_group'   => true, // Custom
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
				'manage_terms' => 'list_users',
				'edit_terms'   => 'list_users',
				'delete_terms' => 'list_users',
				'assign_terms' => 'read',
			),

			// @see _update_post_term_count()
			'update_count_callback' => array( $this, 'update_term_user_count' )
		) );
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

		// Setup the empty users array
		$users = array();

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
		if ( ! current_user_can( 'list_users' ) ) {
			return $views;
		}

		// Get taxonomy & terms
		$tax   = get_taxonomy( $this->taxonomy );
		$terms = get_terms( $this->taxonomy, array(
			'hide_empty' => false
		) ); ?>

		<form method="post" class="user-tax-form user-tax-form-<?php echo esc_attr( $this->taxonomy ); ?>">
			<fieldset class="alignleft">
				<legend class="screen-reader-text"><?php esc_html_e( 'Update Groups', 'wp-user-groups' ); ?></legend>

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
					<?php esc_html_e( 'Users', 'wp-user-groups' ); ?>

					<?php if ( current_user_can( 'create_users' ) ) : ?>

						<a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>" class="page-title-action"><?php echo esc_html_x( 'Add New', 'user', 'wp-user-groups' ); ?></a>

					<?php elseif ( is_multisite() && current_user_can( 'promote_users' ) ) : ?>

						<a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>" class="page-title-action"><?php echo esc_html_x( 'Add Existing', 'user', 'wp-user-groups' ); ?></a>

					<?php endif; ?>

					<span class="subtitle"><?php printf( esc_html__( 'Viewing users of %s: %s', 'wp-user-groups' ), $this->tax_singular_low, '<a href="' . esc_url( $url ) . '">' . $terms[ $viewing ]->name . '</a>' ); ?></span>
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

		// Bail if not looking at taxonomy
		if ( empty( $_GET[ $this->taxonomy ] ) ) {
			return;
		}

		// Sanitize taxonomies
		$groups = array_map( 'sanitize_key', explode( ',', $_GET[ $this->taxonomy ] ) );

		// Get terms
		foreach ( $groups as $group ) {
			$term     = get_term_by( 'slug', $group, $this->taxonomy );
			$user_ids = get_objects_in_term( $term->term_id, $this->taxonomy );
		}

		// If no users are in this group, pass a 0 user ID
		if ( empty( $user_ids ) ) {
			$user_ids = array( 0 );
		}

		// Set IDs to be included
		$user_query->query_vars['include'] = $user_ids;
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

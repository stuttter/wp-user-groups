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
	 * Array of taxonomy capabilities, if you'd like to customize them completely
	 *
	 * @since 2.2.0
	 *
	 * @var array
	 */
	public $caps = array();

	/**
	 * Singular label
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	protected $tax_singular = '';

	/**
	 * Plural label
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	protected $tax_plural = '';

	/**
	 * Lowercase singular label
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	protected $tax_singular_low = '';

	/**
	 * Lowercase plural label
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	protected $tax_plural_low = '';

	/**
	 * Main constructor
	 *
	 * @since 0.1.0
	 *
	 * @param  string  $taxonomy
	 * @param  string  $slug
	 * @param  array   $args
	 * @param  array   $labels
	 * @param  array   $caps
	 */
	public function __construct( $taxonomy = '', $slug = '', $args = array(), $labels = array(), $caps = array() ) {

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
		$this->caps     = $caps;

		// Label helpers
		$this->tax_singular     = $args['singular'];
		$this->tax_plural       = $args['plural'];
		$this->tax_singular_low = strtolower( $this->tax_singular );
		$this->tax_plural_low   = strtolower( $this->tax_plural   );

		// Register the taxonomy
		$this->register_user_taxonomy();

		// Hook into actions & filters
		$this->hooks();

		// JIT
		do_action( 'wp_user_taxonomy', $this );
	}

	/**
	 * Hook in to actions & filters
	 *
	 * @since 0.1.1
	 */
	protected function hooks() {

		// Bulk edit
		add_filter( 'admin_notices',             array( $this, 'bulk_notice'         )        );
		add_filter( 'bulk_actions-users',        array( $this, 'bulk_actions'        )        );
		add_filter( 'bulk_actions-users',        array( $this, 'bulk_actions_sort'   ), 99    );
		add_action( 'handle_bulk_actions-users', array( $this, 'handle_bulk_actions' ), 10, 3 );

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
		if ( ! function_exists( '_wp_user_profiles' ) ) {
			add_action( 'show_user_profile', array( $this, 'edit_user_relationships' ), 99 );
			add_action( 'edit_user_profile', array( $this, 'edit_user_relationships' ), 99 );
		}

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
			$classes .= " tax-{$this->taxonomy}";
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
			body.users-php.tax-{$this->taxonomy} .wrap > h1,
			body.users-php.tax-{$this->taxonomy} .wrap > h1 + .page-title-action {
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

		// Bail if current user cannot assign terms to this user for this taxonomy
		if ( ! $this->can_assign( $user_id ) ) {
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

		// Bail if nonce problem
		if ( ! $this->verify_nonce() ) {
			return;
		}

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

		// Make sure the current user can edit the user and assign terms before proceeding
		if ( ! $this->can_assign( $user_id ) ) {
			return false;
		}

		// Get terms from the $_POST global if available
		$terms = isset( $_POST[ $this->taxonomy ] )
			? $_POST[ $this->taxonomy ]
			: null;

		// Set terms for user
		wp_set_terms_for_user( $user_id, $this->taxonomy, $terms );
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
			$term    = get_term( $term_id, $this->taxonomy );
			$args    = array( $this->taxonomy => $term->slug );
			$users   = admin_url( 'users.php' );
			$url     = add_query_arg( $args, $users );
			$text    = number_format_i18n( $term->count );
			$display = '<a href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>';
		}

		// Return the new content for display
		return $display;
	}

	/**
	 * Output a "Relationships" section to show off taxonomy groupings
	 *
	 * @since 0.1.0
	 *
	 * @param  mixed  $user
	 */
	public function edit_user_relationships( $user = false ) {

		// Bail if current user cannot assign terms to this user for this taxonomy
		if ( ! $this->can_assign( $user->ID ) ) {
			return;
		}

		$tax = get_taxonomy( $this->taxonomy );

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
						<?php if ( ! $this->is_managed() && ! $this->is_exclusive() ) : ?>
							<label class="screen-reader-text" for="cb-select-all-1"><?php esc_html_e( 'Select All', 'wp-user-groups' ); ?></label>
							<input id="cb-select-all-1" type="checkbox">
						<?php endif; ?>
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
								<?php if ( ! $this->is_managed() ) : ?>
									<input type="<?php echo $this->is_exclusive() ? 'radio' : 'checkbox'; ?>" name="<?php echo esc_attr( $this->taxonomy ); ?>[]" id="<?php echo esc_attr( $this->taxonomy ); ?>-<?php echo esc_attr( $term->slug ); ?>" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( $active ); ?> />
									<label for="<?php echo esc_attr( $this->taxonomy ); ?>-<?php echo esc_attr( $term->slug ); ?>"></label>
								<?php endif; ?>
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
						<?php if ( ! $this->is_managed() && ! $this->is_exclusive() ) : ?>
							<label class="screen-reader-text" for="cb-select-all-2"><?php esc_html_e( 'Select All', 'wp-user-groups' ); ?></label>
							<input id="cb-select-all-2" type="checkbox">
						<?php endif; ?>
					</td>
					<th scope="col" class="manage-column column-name column-primary"><?php esc_html_e( 'Name', 'wp-user-groups' ); ?></th>
					<th scope="col" class="manage-column column-description"><?php esc_html_e( 'Description', 'wp-user-groups' ); ?></th>
					<th scope="col" class="manage-column column-users"><?php esc_html_e( 'Users', 'wp-user-groups' ); ?></th>
				</tr>
			</tfoot>
		</table>

		<?php

		// Nonce for table fields
		$this->nonce_field();
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

		// List users in group
		if ( current_user_can( 'list_users' ) ) {
			$args      = array( $tax->name => $term->slug );
			$users     = admin_url( 'users.php' );
			$url       = add_query_arg( $args, $users );
			$actions[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'View', 'wp-user-groups' ) . '</a>';
		}

		// Edit term
		if ( current_user_can( 'edit_term', $term->term_id ) ) {
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

		// Parse the options
		$options = $this->parse_options();

		/**
		 * Filter the objects for this taxonomy, allowing for multiple
		 * relationships to exist. This is risky, as ID collisions may occur, so
		 * make sure that you're using it correctly
		 *
		 * @since 2.4.0
		 *
		 * @param array  $defaults Default object types. 'user' by default.
		 * @param string $taxonomy The current taxonomy
		 * @param
		 */
		$objects = (array) apply_filters( 'wp_user_groups_taxonomy_objects', array(
			'user'
		) , $this->taxonomy, $options );

		// Register the taxonomy
		register_taxonomy(
			$this->taxonomy,
			$objects,
			$options
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
	 * Parse taxonomy capabilities
	 *
	 * @since 2.2.0
	 *
	 * @return array
	 */
	protected function parse_caps() {
		return wp_parse_args( $this->caps, array(
			'manage_terms' => 'list_users',
			'edit_terms'   => 'list_users',
			'delete_terms' => 'list_users',
			'assign_terms' => $this->is_managed()
				? 'list_users'
				: 'read'
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

			// Custom
			'user_group'   => true,  // Make it easy to identify user groups
			'exclusive'    => false, // Check vs. Radio

			// Core
			'hierarchical' => true,
			'public'       => false,
			'show_ui'      => true,
			'meta_box_cb'  => '',
			'labels'       => $this->parse_labels(),
			'capabilities' => $this->parse_caps(),
			'rewrite'      => array(
				'with_front'   => false,
				'slug'         => $this->slug,
				'hierarchical' => true
			),

			// @see _update_post_term_count()
			'update_count_callback' => array( $this, 'update_term_user_count' )
		) );
	}

	/** Bulk Edit *************************************************************/

	/**
	 * Add custom bulk actions
	 *
	 * @since 1.0.0
	 *
	 * @param array $actions
	 *
	 * @return array
	 */
	public function bulk_actions( $actions = array() ) {

		// Get taxonomy & terms
		$tax   = get_taxonomy( $this->taxonomy );
		$terms = get_terms( $this->taxonomy, array(
			'hide_empty' => false
		) );

		// Add to bulk actions array
		if ( ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$actions[ "add-{$term->slug}-{$this->taxonomy}"    ] = sprintf( esc_html__( 'Add to %s %s',      'wp-user-groups' ), $term->name, $tax->labels->singular_name );
				$actions[ "remove-{$term->slug}-{$this->taxonomy}" ] = sprintf( esc_html__( 'Remove from %s %s', 'wp-user-groups' ), $term->name, $tax->labels->singular_name );
			}
		}

		// Return actions, maybe with our bulks added
		return $actions;
	}

	/**
	 * Group add/remove options together for improved UX
	 *
	 * @since 1.0.0
	 *
	 * @param array $actions
	 */
	public function bulk_actions_sort( $actions = array() ) {

		// Actions array
		$old_actions = $add_actions = $rem_actions = array();

		// Loop through and separate out actions
		foreach ( $actions as $key => $name ) {

			// Add
			if ( 0 === strpos( $key, 'add-' ) ) {
				$add_actions[ $key ] = $name;

			// Remove
			} elseif ( 0 === strpos( $key, 'remove-' ) ) {
				$rem_actions[ $key ] = $name;

			// Old
			} else {
				$old_actions[ $key ] = $name;
			}
		}

		$new = array_merge( $old_actions, $add_actions, $rem_actions );

		return $new;
	}

	/**
	 * Is this an exclusive user group type, where a user can only belong to one
	 * group within the taxonomy?
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_exclusive() {
		return ! empty( $this->args['exclusive'] );
	}

	/**
	 * Is this a managed user group type, where a user cannot assign their own
	 * groups within the taxonomy?
	 *
	 * @since 2.2.0
	 *
	 * @return bool
	 */
	public function is_managed() {
		return ! empty( $this->args['managed'] );
	}

	/**
	 * Handle bulk editing of users
	 *
	 * @since 1.0.0
	 */
	public function handle_bulk_actions( $redirect_to = '', $action = '', $user_ids = array() ) {

		// Get terms
		$terms = get_terms( $this->taxonomy, array(
			'hide_empty' => false
		) );

		// Bail if no users or terms to work with
		if ( empty( $user_ids ) || empty( $terms ) ) {
			return $redirect_to;
		}

		// New actions array
		$actions = $changed_users = array();

		// Compile available actions
		foreach ( $terms as $term ) {
			$key       = "{$term->slug}-{$this->taxonomy}";
			$actions[] = "add-{$key}";
			$actions[] = "remove-{$key}";
		}

		// Bail if not a supported bulk action
		if ( ! in_array( $action, $actions, true ) ) {
			return $redirect_to;
		}

		// Type & term
		$type = strstr( $action, '-', true );
		$term = str_replace( "{$type}-", '', $action );
		$term = str_replace( "-{$this->taxonomy}", '', $term );

		// Loop through users
		foreach ( $user_ids as $user_id ) {

			// Should we update this user's terms?
			$should_update = false;

			// Skip if current user cannot assign terms to this user for this taxonomy
			if ( ! $this->can_assign( $user_id )  ) {
				continue;
			}

			// Get term slugs of user for this taxonomy
			$terms        = wp_get_terms_for_user( $user_id, $this->taxonomy );
			$update_terms = wp_list_pluck( $terms, 'slug' );

			// Adding
			if ( 'add' === $type ) {
				if ( ! in_array( $term, $update_terms, true ) ) {
					$update_terms[] = $term;
					$should_update  = true;
				}

			// Removing
			} elseif ( 'remove' === $type ) {

				// Skip if nothing to remove
				if ( empty( $update_terms ) ) {
					continue;
				}

				// Check the terms for this one
				$index = array_search( $term, $update_terms );
				if ( ( false !== $index ) && isset( $update_terms[ $index ] ) ) {
					unset( $update_terms[ $index ] );
					$should_update = true;
				}
			}

			// Delete all groups if they're empty
			if ( empty( $update_terms ) ) {
				$update_terms = null;
			}

			// Update terms for users
			if ( ( $update_terms !== $terms ) && ( true === $should_update ) ) {
				$changed_users[] = $user_id;
				wp_set_terms_for_user( $user_id, $this->taxonomy, $update_terms, true );
			}
		}

		// Add count to redirection
		$redirect_to = add_query_arg( array(
			'user_groups_count' => count( $changed_users ),
			'action_type'       => $type,
			'term_slug'         => $term,
			'tax'               => $this->taxonomy
		), $redirect_to );

		// Return redirection
		return $redirect_to;
	}

	/**
	 * Maybe output a notice when bulk actions occur
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function bulk_notice() {

		// Bail if no count
		if ( ! isset( $_REQUEST['user_groups_count'] ) || empty( $_REQUEST['action_type'] ) || empty( $_REQUEST['tax'] ) ) {
			return;
		}

		// Get the changed count and sanitize a few keys
		$count  = intval( $_REQUEST['user_groups_count'] );
		$action = sanitize_key( $_REQUEST['action_type'] );
		$group  = sanitize_key( $_REQUEST['term_slug']   );
		$tax    = sanitize_key( $_REQUEST['tax']         );

		// Bail if group is not for this taxonomy
		if ( $this->taxonomy !== $tax ) {
			return;
		}

		// Get the labels
		$tax    = get_taxonomy( $this->taxonomy )->labels->singular_name;
		$term   = get_term_by( 'slug', $group, $this->taxonomy )->name;

		// Bail if term does not exist in taxonomy
		if ( empty( $term ) ) {
			return;
		}

		// No users
		if ( 0 === $count ) {
			$type = 'warning';
			$text = ( 'add' === $action )
				? sprintf( __( 'No users added to the "%s" %s.',     'wp-user-groups' ), $term, $tax )
				: sprintf( __( 'No users removed from the "%s" %s.', 'wp-user-groups' ), $term, $tax );

		// Add/remove
		} else {
			$type = 'success';
			$text = ( 'add' === $action )
				? sprintf( _n( '%s user added to the "%s" %s.',     '%s users added to the "%s" %s.',     $count, 'wp-user-groups' ), number_format_i18n( $count ), $term, $tax )
				: sprintf( _n( '%s user removed from the "%s" %s.', '%s users removed from the "%s" %s.', $count, 'wp-user-groups' ), number_format_i18n( $count ), $term, $tax );
		}

		// Output message
		?><div id="message" class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible"><p><?php
			echo esc_html( $text );
			?><button type="button" class="notice-dismiss"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'wp-user-groups' ); ?></span></button>
		</p></div><?php
	}

	/** Views *****************************************************************/

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

	/** Nonce *****************************************************************/

	/**
	 * Return the concatenated nonce key
	 *
	 * @since 2.1.0
	 *
	 * @return string
	 */
	private function get_nonce_key() {
		return "wp_user_taxonomy_{$this->taxonomy}";
	}

	/**
	 * Output the nonce field for this user taxonomy table
	 *
	 * @since 2.1.0
	 */
	private function nonce_field() {
		wp_nonce_field( $this->taxonomy, $this->get_nonce_key() );
	}

	/**
	 * Try to verify the nonce for this use taxonomy
	 *
	 * @since 2.1.0
	 *
	 * @return boolean
	 */
	private function verify_nonce() {

		// Nonce exists?
		$retval = false;
		$key    = $this->get_nonce_key();
		$nonce  = isset( $_REQUEST[ $key ] )
			? $_REQUEST[ $key ]
			: $retval;

		// Return true if nonce was verified
		if ( ! empty( $nonce ) && wp_verify_nonce( $nonce, $this->taxonomy ) ) {
			$retval = true;
		}

		// Default return value
		return $retval;
	}

	/** Caps ******************************************************************/

	/**
	 * Whether the current user can assign terms to another user
	 *
	 * @since 2.2.0
	 *
	 * @param int $user_id
	 *
	 * @return boolean
	 */
	private function can_assign( $user_id = 0 ) {

		// Default return value
		$retval = false;

		// Get the taxonomy
		$tax    = get_taxonomy( $this->taxonomy );

		// Check edit_user and assign
		if ( current_user_can( 'edit_user', $user_id ) && current_user_can( $tax->cap->assign_terms ) ) {
			$retval = true;
		}

		// Return
		return (bool) $retval;
	}
}
endif;

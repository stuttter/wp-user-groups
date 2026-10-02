<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class TestableUserTaxonomy extends WP_User_Taxonomy {
	public function render_table( $user, $taxonomy, $terms ): string {
		ob_start();
		$this->table_contents( $user, $taxonomy, $terms );
		return (string) ob_get_clean();
	}

	public function render_list_table_views(): string {
		ob_start();
		$this->list_table_views();
		return (string) ob_get_clean();
	}
}

final class CustomRowActionsUserTaxonomy extends TestableUserTaxonomy {
	protected function row_actions( $tax = array(), $term = false ) {
		return 'Custom row action';
	}
}

final class UserTaxonomyTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['wpug_test'] = array();
		$_GET = array();
	}

	private function taxonomy( array $args = array() ): TestableUserTaxonomy {
		$reflection = new ReflectionClass( TestableUserTaxonomy::class );
		$taxonomy   = $reflection->newInstanceWithoutConstructor();
		$taxonomy->taxonomy = 'user-group';
		$taxonomy->args     = $args;
		$taxonomy->tax_singular_low = 'group';
		return $taxonomy;
	}

	public function test_public_properties_are_declared(): void {
		$properties = array_map(
			static function ( ReflectionProperty $property ): string { return $property->getName(); },
			( new ReflectionClass( WP_User_Taxonomy::class ) )->getProperties( ReflectionProperty::IS_PUBLIC )
		);
		$expected = array( 'taxonomy', 'slug', 'args', 'labels', 'caps', 'tax_singular', 'tax_plural', 'tax_singular_low', 'tax_plural_low' );

		sort( $properties );
		sort( $expected );

		$this->assertSame( $expected, $properties );
	}

	public function test_managed_taxonomy_remains_editable_for_administrators(): void {
		$GLOBALS['wpug_test']['returns']['current_user_can'] = true;
		$this->assertFalse( $this->taxonomy( array( 'managed' => true ) )->is_managed() );
	}

	public function test_managed_taxonomy_is_read_only_for_other_users(): void {
		$GLOBALS['wpug_test']['returns']['current_user_can'] = false;
		$this->assertTrue( $this->taxonomy( array( 'managed' => true ) )->is_managed() );
	}

	public function test_table_preserves_selection_fields_and_nonce(): void {
		$GLOBALS['wpug_test']['returns']['current_user_can']    = true;
		$GLOBALS['wpug_test']['returns']['is_object_in_term']   = true;
		$term = (object) array( 'term_id' => 8, 'slug' => 'editors', 'name' => 'Editors', 'description' => '', 'count' => 3 );
		$tax  = (object) array( 'name' => 'user-group', 'labels' => (object) array( 'not_found' => 'No groups found' ) );

		$html = $this->taxonomy()->render_table( (object) array( 'ID' => 7 ), $tax, array( $term ) );

		$this->assertStringContainsString( 'name="user-group[]"', $html );
		$this->assertStringContainsString( 'value="editors"', $html );
		$this->assertStringContainsString( 'checked="checked"', $html );
		$this->assertStringContainsString( 'name="wp_user_taxonomy_user-group"', $html );
		$this->assertStringContainsString( '>Editors<', $html );
		$this->assertCount( 1, $GLOBALS['wpug_test']['calls']['is_object_in_term'] );
	}

	public function test_table_preserves_subclass_row_actions_extension_point(): void {
		$GLOBALS['wpug_test']['returns']['current_user_can']  = false;
		$GLOBALS['wpug_test']['returns']['is_object_in_term'] = false;
		$term = (object) array( 'term_id' => 8, 'slug' => 'editors', 'name' => 'Editors', 'description' => '', 'count' => 3 );
		$tax  = (object) array( 'name' => 'user-group', 'labels' => (object) array( 'not_found' => 'No groups found' ) );
		$reflection = new ReflectionClass( CustomRowActionsUserTaxonomy::class );
		$taxonomy   = $reflection->newInstanceWithoutConstructor();
		$taxonomy->taxonomy = 'user-group';
		$taxonomy->args     = array();

		$html = $taxonomy->render_table( (object) array( 'ID' => 7 ), $tax, array( $term ) );

		$this->assertStringContainsString( 'Custom row action', $html );
	}

	public function test_table_uses_taxonomy_column_filters(): void {
		$GLOBALS['wpug_test']['returns']['current_user_can']  = false;
		$GLOBALS['wpug_test']['returns']['is_object_in_term'] = false;
		$GLOBALS['wpug_test']['callbacks']['apply_filters:manage_edit-user-group_columns'] = static function ( $columns ) {
			$columns['favorite_color'] = 'Favorite color';
			return $columns;
		};
		$GLOBALS['wpug_test']['callbacks']['apply_filters:manage_user-group_custom_column'] = static function ( $display, $column_name, $term_id ) {
			return 'favorite_color' === $column_name ? "Color for {$term_id}" : $display;
		};
		$term = (object) array( 'term_id' => 8, 'slug' => 'editors', 'name' => 'Editors', 'description' => '', 'count' => 3 );
		$tax  = (object) array( 'name' => 'user-group', 'labels' => (object) array( 'not_found' => 'No groups found' ) );

		$html = $this->taxonomy()->render_table( (object) array( 'ID' => 7 ), $tax, array( $term ) );

		$this->assertStringContainsString( '>Favorite color<', $html );
		$this->assertStringContainsString( '>Color for 8<', $html );
	}

	public function test_exclusive_table_uses_radios_without_select_all(): void {
		$GLOBALS['wpug_test']['returns']['current_user_can']  = false;
		$GLOBALS['wpug_test']['returns']['is_object_in_term'] = false;
		$term = (object) array( 'term_id' => 8, 'slug' => 'editors', 'name' => 'Editors', 'description' => '', 'count' => 3 );
		$tax  = (object) array( 'name' => 'user-group', 'labels' => (object) array( 'not_found' => 'No groups found' ) );

		$html = $this->taxonomy( array( 'exclusive' => true ) )->render_table( (object) array( 'ID' => 7 ), $tax, array( $term ) );

		$this->assertStringContainsString( 'type="radio"', $html );
		$this->assertStringNotContainsString( 'cb-select-all-', $html );
	}

	public function test_managed_table_has_no_relationship_inputs_for_non_administrators(): void {
		$GLOBALS['wpug_test']['returns']['current_user_can']  = false;
		$GLOBALS['wpug_test']['returns']['is_object_in_term'] = true;
		$term = (object) array( 'term_id' => 8, 'slug' => 'editors', 'name' => 'Editors', 'description' => '', 'count' => 3 );
		$tax  = (object) array( 'name' => 'user-group', 'labels' => (object) array( 'not_found' => 'No groups found' ) );

		$html = $this->taxonomy( array( 'managed' => true ) )->render_table( (object) array( 'ID' => 7 ), $tax, array( $term ) );

		$this->assertStringNotContainsString( 'name="user-group[]"', $html );
		$this->assertStringContainsString( 'name="wp_user_taxonomy_user-group"', $html );
	}

	public function test_list_table_view_sanitizes_term_heading_and_description(): void {
		$GLOBALS['wpug_test']['returns']['current_user_can'] = false;
		$GLOBALS['wpug_test']['returns']['get_terms'] = array(
			(object) array(
				'term_id'    => 8,
				'slug'       => 'editors',
				'name'       => 'Editors <script>alert("name")</script>',
				'description' => '<strong>Trusted</strong><script>alert("description")</script>',
			),
		);
		$_GET['user-group'] = 'editors';

		$html = $this->taxonomy()->render_list_table_views();

		$this->assertStringContainsString( '<a href="https://example.test/wp-admin/edit-tags.php?action=edit&amp;taxonomy=user-group&amp;tag_ID=8">Editors &lt;script&gt;alert(&quot;name&quot;)&lt;/script&gt;</a>', $html );
		$this->assertStringContainsString( '<p><strong>Trusted</strong></p>', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	/** Bulk removal replaces a user's terms instead of appending them. */
	public function test_bulk_remove_replaces_existing_terms(): void {
		$taxonomy = new WP_Taxonomy();

		$taxonomy->cap = (object) array( 'assign_terms' => 'assign_user_groups' );

		$GLOBALS['wpug_test']['returns']['get_taxonomy'] = $taxonomy;

		$GLOBALS['wpug_test']['returns']['current_user_can'] = true;

		$GLOBALS['wpug_test']['returns']['get_terms']           = array(
			(object) array( 'slug' => 'editors' ),
		);
		$GLOBALS['wpug_test']['returns']['wp_get_object_terms'] = array(
			(object) array( 'slug' => 'editors' ),
			(object) array( 'slug' => 'authors' ),
		);

		$this->taxonomy()->handle_bulk_actions(
			'https://example.test/wp-admin/users.php',
			'remove-editors-user-group',
			array( 7 )
		);

		$this->assertSame(
			array( 7, array( 1 => 'authors' ), 'user-group', false ),
			$GLOBALS['wpug_test']['calls']['wp_set_object_terms'][0]
		);
	}

	/** Bulk addition replaces the complete list after adding the requested term. */
	public function test_bulk_add_preserves_existing_terms(): void {
		$taxonomy = new WP_Taxonomy();

		$taxonomy->cap = (object) array( 'assign_terms' => 'assign_user_groups' );

		$GLOBALS['wpug_test']['returns']['get_taxonomy'] = $taxonomy;

		$GLOBALS['wpug_test']['returns']['current_user_can'] = true;

		$GLOBALS['wpug_test']['returns']['get_terms']           = array(
			(object) array( 'slug' => 'editors' ),
		);
		$GLOBALS['wpug_test']['returns']['wp_get_object_terms'] = array(
			(object) array( 'slug' => 'authors' ),
		);

		$this->taxonomy()->handle_bulk_actions(
			'https://example.test/wp-admin/users.php',
			'add-editors-user-group',
			array( 7 )
		);

		$this->assertSame(
			array( 7, array( 'authors', 'editors' ), 'user-group', false ),
			$GLOBALS['wpug_test']['calls']['wp_set_object_terms'][0]
		);
	}

	/** Bulk addition does not rewrite an existing relationship. */
	public function test_bulk_add_skips_users_who_already_have_the_term(): void {
		$taxonomy = new WP_Taxonomy();

		$taxonomy->cap = (object) array( 'assign_terms' => 'assign_user_groups' );

		$GLOBALS['wpug_test']['returns']['get_taxonomy'] = $taxonomy;

		$GLOBALS['wpug_test']['returns']['current_user_can'] = true;

		$GLOBALS['wpug_test']['returns']['get_terms']           = array(
			(object) array( 'slug' => 'editors' ),
		);
		$GLOBALS['wpug_test']['returns']['wp_get_object_terms'] = array(
			(object) array( 'slug' => 'editors' ),
		);

		$this->taxonomy()->handle_bulk_actions(
			'https://example.test/wp-admin/users.php',
			'add-editors-user-group',
			array( 7 )
		);

		$this->assertArrayNotHasKey( 'wp_set_object_terms', $GLOBALS['wpug_test']['calls'] );
	}

	/** The WordPress callback shape may pass the taxonomy object directly. */
	public function test_term_count_callback_accepts_a_taxonomy_object(): void {
		$taxonomy = new WP_Taxonomy();

		$this->taxonomy()->update_term_user_count( array( 8 ), $taxonomy );

		$this->assertSame(
			array( array( 8 ), $taxonomy ),
			$GLOBALS['wpug_test']['calls']['_update_generic_term_count'][0]
		);
		$this->assertArrayNotHasKey( 'get_taxonomy', $GLOBALS['wpug_test']['calls'] );
	}

	/** A taxonomy name is resolved before the generic count callback runs. */
	public function test_term_count_callback_resolves_a_taxonomy_name(): void {
		$taxonomy = new WP_Taxonomy();

		$GLOBALS['wpug_test']['returns']['get_taxonomy'] = $taxonomy;

		$this->taxonomy()->update_term_user_count( array( 8 ), 'user-group' );

		$this->assertSame(
			array( array( 8 ), $taxonomy ),
			$GLOBALS['wpug_test']['calls']['_update_generic_term_count'][0]
		);
	}

	/** An empty taxonomy name falls back to the instance taxonomy. */
	public function test_term_count_callback_uses_the_instance_taxonomy_by_default(): void {
		$taxonomy = new WP_Taxonomy();

		$GLOBALS['wpug_test']['returns']['get_taxonomy'] = $taxonomy;

		$this->taxonomy()->update_term_user_count( array( 8 ) );

		$this->assertSame(
			array( 'user-group' ),
			$GLOBALS['wpug_test']['calls']['get_taxonomy'][0]
		);
	}

	/** A missing taxonomy does not invoke the generic count callback. */
	public function test_term_count_callback_skips_a_missing_taxonomy(): void {
		$GLOBALS['wpug_test']['returns']['get_taxonomy'] = false;

		$this->taxonomy()->update_term_user_count( array( 8 ), 'missing' );

		$this->assertArrayNotHasKey( '_update_generic_term_count', $GLOBALS['wpug_test']['calls'] );
	}
}

<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class TestableUserTaxonomy extends WP_User_Taxonomy {
	public function render_table( $user, $taxonomy, $terms ): string {
		ob_start();
		$this->table_contents( $user, $taxonomy, $terms );
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
	}

	private function taxonomy( array $args = array() ): TestableUserTaxonomy {
		$reflection = new ReflectionClass( TestableUserTaxonomy::class );
		$taxonomy   = $reflection->newInstanceWithoutConstructor();
		$taxonomy->taxonomy = 'user-group';
		$taxonomy->args     = $args;
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
}

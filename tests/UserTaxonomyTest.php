<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TestableUserTaxonomy extends WP_User_Taxonomy {
	public function render_table( $user, $taxonomy, $terms ): string {
		ob_start();
		$this->table_contents( $user, $taxonomy, $terms );
		return (string) ob_get_clean();
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

		$this->assertSame(
			array( 'taxonomy', 'slug', 'args', 'labels', 'caps', 'tax_singular', 'tax_plural', 'tax_singular_low', 'tax_plural_low' ),
			$properties
		);
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
	}
}

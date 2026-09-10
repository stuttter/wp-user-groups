<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CommonFunctionsTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['wpug_test'] = array();
	}

	public function test_get_terms_rejects_an_empty_user(): void {
		$this->assertFalse( wp_get_terms_for_user( 0, 'user-group' ) );
		$this->assertArrayNotHasKey( 'wp_get_object_terms', $GLOBALS['wpug_test']['calls'] ?? array() );
	}

	public function test_get_terms_uses_an_object_id(): void {
		$GLOBALS['wpug_test']['returns']['wp_get_object_terms'] = array( 'term' );

		$result = wp_get_terms_for_user( (object) array( 'ID' => 42 ), 'user-group' );

		$this->assertSame( array( 'term' ), $result );
		$this->assertSame(
			array( 42, 'user-group', array( 'fields' => 'all_with_object_id' ) ),
			$GLOBALS['wpug_test']['calls']['wp_get_object_terms'][0]
		);
	}

	public function test_empty_terms_delete_relationships_and_clean_cache(): void {
		wp_set_terms_for_user( 7, 'user-group', array() );

		$this->assertSame( array( 7, 'user-group' ), $GLOBALS['wpug_test']['calls']['wp_delete_object_term_relationships'][0] );
		$this->assertSame( array( 7, 'user-group' ), $GLOBALS['wpug_test']['calls']['clean_object_term_cache'][0] );
		$this->assertArrayNotHasKey( 'wp_set_object_terms', $GLOBALS['wpug_test']['calls'] );
	}

	public function test_terms_are_replaced_and_cache_is_cleaned(): void {
		wp_set_terms_for_user( 7, 'user-group', array( 'editors' ) );

		$this->assertSame(
			array( 7, array( 'editors' ), 'user-group', false ),
			$GLOBALS['wpug_test']['calls']['wp_set_object_terms'][0]
		);
		$this->assertSame( array( 7, 'user-group' ), $GLOBALS['wpug_test']['calls']['clean_object_term_cache'][0] );
	}
}

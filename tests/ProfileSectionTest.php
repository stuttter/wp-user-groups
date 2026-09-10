<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProfileSectionTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['wpug_test'] = array();
	}

	public function test_section_is_omitted_without_user_taxonomies(): void {
		$GLOBALS['wpug_test']['returns']['get_taxonomies'] = array();

		$this->assertSame( array( 'profile' => array() ), wp_user_groups_add_profile_section( array( 'profile' => array() ) ) );
	}

	public function test_section_is_omitted_when_taxonomy_has_no_terms(): void {
		$GLOBALS['wpug_test']['returns']['get_taxonomies'] = array( 'user-group' );
		$GLOBALS['wpug_test']['returns']['get_terms']      = array();

		$this->assertSame( array(), wp_user_groups_add_profile_section() );
	}

	public function test_section_is_added_when_any_taxonomy_has_a_term(): void {
		$GLOBALS['wpug_test']['returns']['get_taxonomies'] = array( 'user-group', 'user-type' );
		$GLOBALS['wpug_test']['callbacks']['get_terms'] = static function ( $arguments ) {
			return 'user-type' === $arguments['taxonomy'] ? array( 12 ) : array();
		};

		$sections = wp_user_groups_add_profile_section();

		$this->assertSame( 'groups', $sections['groups']['id'] );
		$this->assertSame( 'Groups', $sections['groups']['name'] );
		$this->assertSame( 'dashicons-groups', $sections['groups']['icon'] );
	}
}

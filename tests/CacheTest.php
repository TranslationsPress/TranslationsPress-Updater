<?php
/**
 * Tests for the Cache class.
 *
 * @package TranslationsPress\Updater\Tests
 */

declare(strict_types=1);

namespace TranslationsPress\Tests;

use PHPUnit\Framework\TestCase;
use TranslationsPress\Cache;

/**
 * Cache class tests.
 *
 * @coversDefaultClass \TranslationsPress\Cache
 */
class CacheTest extends TestCase {

	/**
	 * Clears transients before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		global $wp_test_transients;
		$wp_test_transients = [];
	}

	/**
	 * Tests that cache uses default expiration.
	 *
	 * @covers ::__construct
	 * @covers ::get_expiration
	 */
	public function test_default_expiration(): void {
		$cache = new Cache();
		$this->assertSame( Cache::DEFAULT_EXPIRATION, $cache->get_expiration() );
	}

	/**
	 * Tests that cache respects custom expiration.
	 *
	 * @covers ::__construct
	 * @covers ::get_expiration
	 */
	public function test_custom_expiration(): void {
		$cache = new Cache( 3600 );
		$this->assertSame( 3600, $cache->get_expiration() );
	}

	/**
	 * Tests cache get returns null for missing keys.
	 *
	 * @covers ::get
	 */
	public function test_get_returns_null_for_missing_keys(): void {
		$cache = new Cache();
		$this->assertNull( $cache->get( 'nonexistent_key' ) );
	}

	/**
	 * Tests cache set and get.
	 *
	 * @covers ::set
	 * @covers ::get
	 */
	public function test_set_and_get(): void {
		$cache = new Cache();
		$data  = [ 'translations' => [ [ 'language' => 'fr_FR' ] ] ];

		$cache->set( 'test_key', $data );

		$this->assertSame( $data, $cache->get( 'test_key' ) );
	}

	/**
	 * Tests cache delete.
	 *
	 * @covers ::delete
	 */
	public function test_delete(): void {
		$cache = new Cache();
		$cache->set( 'test_key', [ 'data' ] );
		$cache->delete( 'test_key' );

		$this->assertNull( $cache->get( 'test_key' ) );
	}

	/**
	 * Tests project-specific cache operations.
	 *
	 * @covers ::get_project
	 * @covers ::set_project
	 */
	public function test_project_cache(): void {
		$cache = new Cache();
		$data  = [ 'project' => 'data' ];

		$cache->set_project( 'api_key', 'my-plugin', $data );
		$result = $cache->get_project( 'api_key', 'my-plugin' );

		$this->assertSame( $data, $result );
	}

	/**
	 * Tests project cache returns null when parent cache missing.
	 *
	 * @covers ::get_project
	 */
	public function test_project_cache_returns_null_when_parent_missing(): void {
		$cache  = new Cache();
		$result = $cache->get_project( 'missing_api', 'my-plugin' );

		$this->assertNull( $result );
	}

	/**
	 * Tests cleanup protection timing.
	 *
	 * @covers ::maybe_clean
	 */
	public function test_cleanup_protection(): void {
		$cache = new Cache();

		// Set data first.
		$cache->set( 'test_key', [ 'data' => 'value' ] );

		// Try to clean immediately - should be skipped due to MIN_CACHE_LIFESPAN.
		$result = $cache->maybe_clean( 'test_key' );

		// Should return false because cache is too young.
		$this->assertFalse( $result );

		// Data should still exist.
		$this->assertNotNull( $cache->get( 'test_key' ) );
	}
}

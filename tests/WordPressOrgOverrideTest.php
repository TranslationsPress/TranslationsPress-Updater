<?php
/**
 * Tests for the WordPressOrgOverride class.
 *
 * @package TranslationsPress\Updater\Tests
 */

declare(strict_types=1);

namespace TranslationsPress\Tests;

use PHPUnit\Framework\TestCase;
use TranslationsPress\API;
use TranslationsPress\Cache;
use TranslationsPress\Project;
use TranslationsPress\WordPressOrgOverride;

/**
 * WordPressOrgOverride class tests.
 *
 * @coversDefaultClass \TranslationsPress\WordPressOrgOverride
 */
class WordPressOrgOverrideTest extends TestCase {

	/**
	 * Clears globals before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		global $wp_test_transients, $wp_test_filters;
		$wp_test_transients = [];
		$wp_test_filters    = [];
	}

	/**
	 * Creates a test project.
	 *
	 * @param array $translations Translations data.
	 * @return Project Project instance.
	 */
	private function create_project( array $translations = [] ): Project {
		$cache = new Cache();
		$api   = new API( 'https://example.com/packages.json', false, $cache );

		if ( ! empty( $translations ) ) {
			$cache->set( $api->get_cache_key(), [ 'translations' => $translations ] );
		}

		return new Project( 'plugin', 'my-plugin', $api, true, true );
	}

	/**
	 * Tests mode constants.
	 *
	 * @covers ::MODE_REPLACE
	 * @covers ::MODE_FALLBACK
	 */
	public function test_mode_constants(): void {
		$this->assertSame( 'replace', WordPressOrgOverride::MODE_REPLACE );
		$this->assertSame( 'fallback', WordPressOrgOverride::MODE_FALLBACK );
	}

	/**
	 * Tests enable registers hooks.
	 *
	 * @covers ::enable
	 */
	public function test_enable_registers_hooks(): void {
		global $wp_test_filters;

		$project  = $this->create_project();
		$override = new WordPressOrgOverride( $project, WordPressOrgOverride::MODE_FALLBACK );

		$override->enable();

		// Check that translations_api filter was registered.
		$this->assertArrayHasKey( 'translations_api', $wp_test_filters );
	}

	/**
	 * Tests enable in replace mode registers additional hooks.
	 *
	 * @covers ::enable
	 */
	public function test_enable_replace_mode_registers_http_filter(): void {
		global $wp_test_filters;

		$project  = $this->create_project();
		$override = new WordPressOrgOverride( $project, WordPressOrgOverride::MODE_REPLACE );

		$override->enable();

		// Check that pre_http_request filter was registered for replace mode.
		$this->assertArrayHasKey( 'pre_http_request', $wp_test_filters );
	}

	/**
	 * Tests is_enabled returns correct status.
	 *
	 * @covers ::is_enabled
	 */
	public function test_is_enabled(): void {
		$project  = $this->create_project();
		$override = new WordPressOrgOverride( $project, WordPressOrgOverride::MODE_FALLBACK );

		$this->assertFalse( $override->is_enabled() );

		$override->enable();
		$this->assertTrue( $override->is_enabled() );

		$override->disable();
		$this->assertFalse( $override->is_enabled() );
	}

	/**
	 * Tests get_mode returns correct mode.
	 *
	 * @covers ::get_mode
	 */
	public function test_get_mode(): void {
		$project = $this->create_project();

		$fallback_override = new WordPressOrgOverride( $project, WordPressOrgOverride::MODE_FALLBACK );
		$this->assertSame( WordPressOrgOverride::MODE_FALLBACK, $fallback_override->get_mode() );

		$replace_override = new WordPressOrgOverride( $project, WordPressOrgOverride::MODE_REPLACE );
		$this->assertSame( WordPressOrgOverride::MODE_REPLACE, $replace_override->get_mode() );
	}

	/**
	 * Tests filter_translations_api returns T15S data.
	 *
	 * @covers ::filter_translations_api
	 */
	public function test_filter_translations_api_returns_t15s_data(): void {
		$translations = [
			[
				'language' => 'fr_FR',
				'updated'  => '2024-06-15 12:00:00',
				'package'  => 'https://example.com/fr_FR.zip',
			],
		];

		$project  = $this->create_project( $translations );
		$override = new WordPressOrgOverride( $project, WordPressOrgOverride::MODE_FALLBACK );

		$result = $override->filter_translations_api(
			false,
			'plugins',
			(object) [ 'slug' => 'my-plugin' ]
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'translations', $result );
		$this->assertSame( $translations, $result['translations'] );
	}

	/**
	 * Tests filter_translations_api ignores non-matching slugs.
	 *
	 * @covers ::filter_translations_api
	 */
	public function test_filter_translations_api_ignores_other_slugs(): void {
		$project  = $this->create_project();
		$override = new WordPressOrgOverride( $project, WordPressOrgOverride::MODE_FALLBACK );

		$result = $override->filter_translations_api(
			false,
			'plugins',
			(object) [ 'slug' => 'other-plugin' ]
		);

		// Should return original value (false).
		$this->assertFalse( $result );
	}

	/**
	 * Tests filter_translations_api ignores non-matching types.
	 *
	 * @covers ::filter_translations_api
	 */
	public function test_filter_translations_api_ignores_other_types(): void {
		$project  = $this->create_project();
		$override = new WordPressOrgOverride( $project, WordPressOrgOverride::MODE_FALLBACK );

		$result = $override->filter_translations_api(
			false,
			'themes', // Project is a plugin, not a theme.
			(object) [ 'slug' => 'my-plugin' ]
		);

		$this->assertFalse( $result );
	}

	/**
	 * Tests filter_translations_api fallback mode returns original on empty T15S.
	 *
	 * @covers ::filter_translations_api
	 */
	public function test_filter_translations_api_fallback_on_empty(): void {
		$project  = $this->create_project( [] ); // Empty translations.
		$override = new WordPressOrgOverride( $project, WordPressOrgOverride::MODE_FALLBACK );

		$original = [ 'translations' => [ [ 'language' => 'wporg_translation' ] ] ];

		$result = $override->filter_translations_api(
			$original,
			'plugins',
			(object) [ 'slug' => 'my-plugin' ]
		);

		// Should return original (wp.org) data in fallback mode.
		$this->assertSame( $original, $result );
	}

	/**
	 * Tests filter_translations_api replace mode returns empty on empty T15S.
	 *
	 * @covers ::filter_translations_api
	 */
	public function test_filter_translations_api_replace_on_empty(): void {
		$project  = $this->create_project( [] ); // Empty translations.
		$override = new WordPressOrgOverride( $project, WordPressOrgOverride::MODE_REPLACE );

		$original = [ 'translations' => [ [ 'language' => 'wporg_translation' ] ] ];

		$result = $override->filter_translations_api(
			$original,
			'plugins',
			(object) [ 'slug' => 'my-plugin' ]
		);

		// Should return empty translations in replace mode.
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'translations', $result );
		$this->assertEmpty( $result['translations'] );
	}

	/**
	 * Tests block_wporg_request blocks matching URLs.
	 *
	 * @covers ::block_wporg_request
	 */
	public function test_block_wporg_request_blocks_matching_urls(): void {
		$project  = $this->create_project();
		$override = new WordPressOrgOverride( $project, WordPressOrgOverride::MODE_REPLACE );

		$url    = 'https://api.wordpress.org/translations/plugins/1.0/?slug=my-plugin';
		$result = $override->block_wporg_request( false, [], $url );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'body', $result );
		$this->assertArrayHasKey( 'response', $result );
		$this->assertSame( 200, $result['response']['code'] );
	}

	/**
	 * Tests block_wporg_request ignores non-matching URLs.
	 *
	 * @covers ::block_wporg_request
	 */
	public function test_block_wporg_request_ignores_other_urls(): void {
		$project  = $this->create_project();
		$override = new WordPressOrgOverride( $project, WordPressOrgOverride::MODE_REPLACE );

		// Different plugin.
		$url1    = 'https://api.wordpress.org/translations/plugins/1.0/?slug=other-plugin';
		$result1 = $override->block_wporg_request( false, [], $url1 );
		$this->assertFalse( $result1 );

		// Non-translations API.
		$url2    = 'https://api.wordpress.org/plugins/info/1.0/';
		$result2 = $override->block_wporg_request( false, [], $url2 );
		$this->assertFalse( $result2 );
	}

	/**
	 * Tests set_logger propagates to project.
	 *
	 * @covers ::set_logger
	 */
	public function test_set_logger(): void {
		$project  = $this->create_project();
		$override = new WordPressOrgOverride( $project, WordPressOrgOverride::MODE_FALLBACK );

		$logged = [];
		$logger = function ( $message ) use ( &$logged ) {
			$logged[] = $message;
		};

		$override->set_logger( $logger );

		// The logger is now set - we can verify by enabling which logs a message.
		$override->enable();

		// Should have logged something.
		$this->assertNotEmpty( $logged );
	}
}

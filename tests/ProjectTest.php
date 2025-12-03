<?php
/**
 * Tests for the Project class.
 *
 * @package TranslationsPress\Updater\Tests
 */

declare(strict_types=1);

namespace TranslationsPress\Tests;

use PHPUnit\Framework\TestCase;
use TranslationsPress\API;
use TranslationsPress\Cache;
use TranslationsPress\Project;

/**
 * Project class tests.
 *
 * @coversDefaultClass \TranslationsPress\Project
 */
class ProjectTest extends TestCase {

	/**
	 * Clears globals before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		global $wp_test_transients, $wp_test_available_languages, $wp_test_installed_translations, $wp_test_locale;
		$wp_test_transients             = [];
		$wp_test_available_languages    = [ 'en_US', 'fr_FR', 'de_DE' ];
		$wp_test_installed_translations = [];
		$wp_test_locale                 = 'en_US';

		Project::clear_caches();
	}

	/**
	 * Creates a test API with cached data.
	 *
	 * @param array $translations Translations data.
	 * @return API API instance.
	 */
	private function create_api_with_data( array $translations ): API {
		$cache = new Cache();
		$api   = new API( 'https://example.com/packages.json', false, $cache );

		$cache->set( $api->get_cache_key(), [ 'translations' => $translations ] );

		return $api;
	}

	/**
	 * Tests project type constants.
	 *
	 * @covers ::TYPE_PLUGIN
	 * @covers ::TYPE_THEME
	 */
	public function test_type_constants(): void {
		$this->assertSame( 'plugin', Project::TYPE_PLUGIN );
		$this->assertSame( 'theme', Project::TYPE_THEME );
	}

	/**
	 * Tests project getters.
	 *
	 * @covers ::__construct
	 * @covers ::get_type
	 * @covers ::get_slug
	 * @covers ::get_identifier
	 */
	public function test_getters(): void {
		$api     = $this->create_api_with_data( [] );
		$project = new Project( 'plugin', 'my-plugin', $api );

		$this->assertSame( 'plugin', $project->get_type() );
		$this->assertSame( 'my-plugin', $project->get_slug() );
		$this->assertSame( 'plugin_my-plugin', $project->get_identifier() );
	}

	/**
	 * Tests WordPress.org override flags.
	 *
	 * @covers ::has_wporg_override
	 * @covers ::has_wporg_fallback
	 */
	public function test_wporg_flags(): void {
		$api = $this->create_api_with_data( [] );

		$project_no_override = new Project( 'plugin', 'my-plugin', $api );
		$this->assertFalse( $project_no_override->has_wporg_override() );

		$project_replace = new Project( 'plugin', 'my-plugin', $api, true, false );
		$this->assertTrue( $project_replace->has_wporg_override() );
		$this->assertFalse( $project_replace->has_wporg_fallback() );

		$project_fallback = new Project( 'plugin', 'my-plugin', $api, true, true );
		$this->assertTrue( $project_fallback->has_wporg_override() );
		$this->assertTrue( $project_fallback->has_wporg_fallback() );
	}

	/**
	 * Tests get_translations returns translations array.
	 *
	 * @covers ::get_translations
	 */
	public function test_get_translations(): void {
		$translations = [
			[ 'language' => 'fr_FR', 'package' => 'https://example.com/fr_FR.zip' ],
			[ 'language' => 'de_DE', 'package' => 'https://example.com/de_DE.zip' ],
		];

		$api     = $this->create_api_with_data( $translations );
		$project = new Project( 'plugin', 'my-plugin', $api );

		$result = $project->get_translations();

		// API returns data as-is from cache, which is wrapped in 'translations' key.
		$this->assertSame( [ 'translations' => $translations ], $result );
	}

	/**
	 * Tests get_translations returns empty array on API failure.
	 *
	 * @covers ::get_translations
	 */
	public function test_get_translations_returns_empty_on_failure(): void {
		$cache   = new Cache();
		$api     = new API( 'https://example.com/packages.json', false, $cache );
		$project = new Project( 'plugin', 'my-plugin', $api );

		// No cached data and no HTTP mock = failure.
		$result = $project->get_translations();

		$this->assertSame( [], $result );
	}

	/**
	 * Tests get_translation_updates filters by available locales.
	 *
	 * @covers ::get_translation_updates
	 */
	public function test_get_translation_updates_filters_by_locale(): void {
		global $wp_test_available_languages;
		$wp_test_available_languages = [ 'en_US', 'fr_FR' ]; // No de_DE.

		$translations = [
			[
				'language' => 'fr_FR',
				'updated'  => '2024-01-15 12:00:00',
				'version'  => '1.0.0',
				'package'  => 'https://example.com/fr_FR.zip',
			],
			[
				'language' => 'de_DE',
				'updated'  => '2024-01-15 12:00:00',
				'version'  => '1.0.0',
				'package'  => 'https://example.com/de_DE.zip',
			],
		];

		$api     = $this->create_api_with_data( $translations );
		$project = new Project( 'plugin', 'my-plugin', $api );

		// Clear caches to pick up new languages.
		Project::clear_caches();

		$updates = $project->get_translation_updates();

		// Should only include fr_FR since de_DE is not in available languages.
		$this->assertCount( 1, $updates );
		$this->assertSame( 'fr_FR', $updates[0]['language'] );
	}

	/**
	 * Tests get_translation_updates skips up-to-date translations.
	 *
	 * @covers ::get_translation_updates
	 * @covers ::needs_update
	 */
	public function test_get_translation_updates_skips_up_to_date(): void {
		global $wp_test_installed_translations;
		$wp_test_installed_translations = [
			'plugins' => [
				'my-plugin' => [
					'fr_FR' => [
						'PO-Revision-Date' => '2024-06-01 12:00:00',
					],
				],
			],
		];

		$translations = [
			[
				'language' => 'fr_FR',
				'updated'  => '2024-01-15 12:00:00', // Older than installed.
				'version'  => '1.0.0',
				'package'  => 'https://example.com/fr_FR.zip',
			],
		];

		$api     = $this->create_api_with_data( $translations );
		$project = new Project( 'plugin', 'my-plugin', $api );

		// Clear caches to pick up new installed translations.
		Project::clear_caches();

		$updates = $project->get_translation_updates();

		// Should be empty since remote is older.
		$this->assertCount( 0, $updates );
	}

	/**
	 * Tests get_translation_updates includes newer translations.
	 *
	 * @covers ::get_translation_updates
	 * @covers ::needs_update
	 */
	public function test_get_translation_updates_includes_newer(): void {
		global $wp_test_installed_translations;
		$wp_test_installed_translations = [
			'plugins' => [
				'my-plugin' => [
					'fr_FR' => [
						'PO-Revision-Date' => '2024-01-01 12:00:00',
					],
				],
			],
		];

		$translations = [
			[
				'language' => 'fr_FR',
				'updated'  => '2024-06-15 12:00:00', // Newer than installed.
				'version'  => '1.1.0',
				'package'  => 'https://example.com/fr_FR.zip',
			],
		];

		$api     = $this->create_api_with_data( $translations );
		$project = new Project( 'plugin', 'my-plugin', $api );

		// Clear caches to pick up new installed translations.
		Project::clear_caches();

		$updates = $project->get_translation_updates();

		$this->assertCount( 1, $updates );
		$this->assertSame( 'fr_FR', $updates[0]['language'] );
		$this->assertSame( 'plugin', $updates[0]['type'] );
		$this->assertSame( 'my-plugin', $updates[0]['slug'] );
	}

	/**
	 * Tests translation update format.
	 *
	 * @covers ::get_translation_updates
	 */
	public function test_translation_update_format(): void {
		$translations = [
			[
				'language' => 'fr_FR',
				'updated'  => '2024-06-15 12:00:00',
				'version'  => '2.0.0',
				'package'  => 'https://example.com/fr_FR.zip',
			],
		];

		$api     = $this->create_api_with_data( $translations );
		$project = new Project( 'plugin', 'my-plugin', $api );

		$updates = $project->get_translation_updates();

		$this->assertArrayHasKey( 'type', $updates[0] );
		$this->assertArrayHasKey( 'slug', $updates[0] );
		$this->assertArrayHasKey( 'language', $updates[0] );
		$this->assertArrayHasKey( 'version', $updates[0] );
		$this->assertArrayHasKey( 'updated', $updates[0] );
		$this->assertArrayHasKey( 'package', $updates[0] );
	}

	/**
	 * Tests clear_caches static method.
	 *
	 * @covers ::clear_caches
	 */
	public function test_clear_caches(): void {
		// This test mainly verifies the method exists and doesn't throw.
		Project::clear_caches();
		$this->assertTrue( true );
	}

	/**
	 * Tests get_api returns the API instance.
	 *
	 * @covers ::get_api
	 */
	public function test_get_api(): void {
		$api     = $this->create_api_with_data( [] );
		$project = new Project( 'plugin', 'my-plugin', $api );

		$this->assertSame( $api, $project->get_api() );
	}

	/**
	 * Tests theme project type.
	 *
	 * @covers ::__construct
	 * @covers ::get_type
	 */
	public function test_theme_project(): void {
		global $wp_test_installed_translations;
		$wp_test_installed_translations = [
			'themes' => [],
		];

		$translations = [
			[
				'language' => 'fr_FR',
				'updated'  => '2024-06-15 12:00:00',
				'version'  => '1.0.0',
				'package'  => 'https://example.com/fr_FR.zip',
			],
		];

		$api     = $this->create_api_with_data( $translations );
		$project = new Project( 'theme', 'my-theme', $api );

		$this->assertSame( 'theme', $project->get_type() );
		$this->assertSame( 'theme_my-theme', $project->get_identifier() );

		// Clear caches.
		Project::clear_caches();

		$updates = $project->get_translation_updates();
		$this->assertCount( 1, $updates );
		$this->assertSame( 'theme', $updates[0]['type'] );
	}
}

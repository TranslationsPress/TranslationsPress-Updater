<?php
/**
 * Tests for the main Updater class.
 *
 * @package TranslationsPress\Updater\Tests
 */

declare(strict_types=1);

namespace TranslationsPress\Tests;

use PHPUnit\Framework\TestCase;
use TranslationsPress\Updater;
use TranslationsPress\Project;

/**
 * Updater class tests.
 *
 * @coversDefaultClass \TranslationsPress\Updater
 */
class UpdaterTest extends TestCase {

	/**
	 * Clears globals and resets singleton before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		global $wp_test_transients, $wp_test_filters, $wp_test_available_languages, $wp_test_installed_translations, $wp_test_http_responses;
		$wp_test_transients             = [];
		$wp_test_filters                = [];
		$wp_test_available_languages    = [ 'en_US', 'fr_FR', 'de_DE' ];
		$wp_test_installed_translations = [];
		$wp_test_http_responses         = [];

		// Reset singleton for each test.
		Updater::reset();
	}

	/**
	 * Resets singleton after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Updater::reset();
		parent::tearDown();
	}

	/**
	 * Tests singleton pattern.
	 *
	 * @covers ::get_instance
	 */
	public function test_singleton(): void {
		$instance1 = Updater::get_instance();
		$instance2 = Updater::get_instance();

		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Tests version constant.
	 *
	 * @covers ::VERSION
	 */
	public function test_version(): void {
		$this->assertSame( '2.0.0', Updater::VERSION );
	}

	/**
	 * Tests project registration.
	 *
	 * @covers ::register
	 * @covers ::is_registered
	 */
	public function test_register(): void {
		$updater = Updater::get_instance();

		$project = $updater->register(
			'plugin',
			'my-plugin',
			'https://example.com/packages.json'
		);

		$this->assertInstanceOf( Project::class, $project );
		$this->assertTrue( $updater->is_registered( 'plugin', 'my-plugin' ) );
	}

	/**
	 * Tests project registration with options.
	 *
	 * @covers ::register
	 */
	public function test_register_with_options(): void {
		$updater = Updater::get_instance();

		$project = $updater->register(
			'plugin',
			'my-plugin',
			'https://example.com/packages.json',
			[
				'override_wporg' => true,
				'wporg_fallback' => false,
			]
		);

		$this->assertTrue( $project->has_wporg_override() );
		$this->assertFalse( $project->has_wporg_fallback() );
	}

	/**
	 * Tests getting a registered project.
	 *
	 * @covers ::get_project
	 */
	public function test_get_project(): void {
		$updater = Updater::get_instance();

		$updater->register( 'plugin', 'my-plugin', 'https://example.com/packages.json' );

		$project = $updater->get_project( 'plugin', 'my-plugin' );

		$this->assertInstanceOf( Project::class, $project );
		$this->assertSame( 'my-plugin', $project->get_slug() );
	}

	/**
	 * Tests getting a non-existent project.
	 *
	 * @covers ::get_project
	 */
	public function test_get_project_returns_null_for_unregistered(): void {
		$updater = Updater::get_instance();

		$project = $updater->get_project( 'plugin', 'nonexistent' );

		$this->assertNull( $project );
	}

	/**
	 * Tests getting all projects.
	 *
	 * @covers ::get_projects
	 */
	public function test_get_projects(): void {
		$updater = Updater::get_instance();

		$updater->register( 'plugin', 'plugin-one', 'https://example.com/one.json' );
		$updater->register( 'plugin', 'plugin-two', 'https://example.com/two.json' );
		$updater->register( 'theme', 'my-theme', 'https://example.com/theme.json' );

		$projects = $updater->get_projects();

		$this->assertCount( 3, $projects );
		$this->assertArrayHasKey( 'plugin_plugin-one', $projects );
		$this->assertArrayHasKey( 'plugin_plugin-two', $projects );
		$this->assertArrayHasKey( 'theme_my-theme', $projects );
	}

	/**
	 * Tests unregistering a project.
	 *
	 * @covers ::unregister
	 */
	public function test_unregister(): void {
		$updater = Updater::get_instance();

		$updater->register( 'plugin', 'my-plugin', 'https://example.com/packages.json' );
		$this->assertTrue( $updater->is_registered( 'plugin', 'my-plugin' ) );

		$result = $updater->unregister( 'plugin', 'my-plugin' );

		$this->assertTrue( $result );
		$this->assertFalse( $updater->is_registered( 'plugin', 'my-plugin' ) );
	}

	/**
	 * Tests unregistering a non-existent project.
	 *
	 * @covers ::unregister
	 */
	public function test_unregister_returns_false_for_unregistered(): void {
		$updater = Updater::get_instance();

		$result = $updater->unregister( 'plugin', 'nonexistent' );

		$this->assertFalse( $result );
	}

	/**
	 * Tests registering multiple add-ons with centralized API.
	 *
	 * @covers ::register_addons
	 */
	public function test_register_addons(): void {
		$updater = Updater::get_instance();

		$projects = $updater->register_addons(
			'https://example.com/all-packages.json',
			[ 'main-plugin', 'addon-one', 'addon-two' ]
		);

		$this->assertCount( 3, $projects );
		$this->assertTrue( $updater->is_registered( 'plugin', 'main-plugin' ) );
		$this->assertTrue( $updater->is_registered( 'plugin', 'addon-one' ) );
		$this->assertTrue( $updater->is_registered( 'plugin', 'addon-two' ) );
	}

	/**
	 * Tests that add-ons share the same API instance.
	 *
	 * @covers ::register_addons
	 */
	public function test_register_addons_share_api(): void {
		$updater = Updater::get_instance();

		$projects = $updater->register_addons(
			'https://example.com/all-packages.json',
			[ 'addon-one', 'addon-two' ]
		);

		// Both projects should use the same API instance.
		$api1 = $projects['addon-one']->get_api();
		$api2 = $projects['addon-two']->get_api();

		$this->assertSame( $api1, $api2 );
	}

	/**
	 * Tests logger setting.
	 *
	 * @covers ::set_logger
	 */
	public function test_set_logger(): void {
		$logged = [];
		$logger = function ( $message ) use ( &$logged ) {
			$logged[] = $message;
		};

		$updater = Updater::get_instance();
		$updater->set_logger( $logger );

		// Register a project which should trigger logging.
		$updater->register( 'plugin', 'my-plugin', 'https://example.com/packages.json' );

		$this->assertNotEmpty( $logged );
		$this->assertStringContainsString( 'TranslationsPress', $logged[0] );
	}

	/**
	 * Tests filter_plugin_translations.
	 *
	 * @covers ::filter_plugin_translations
	 */
	public function test_filter_plugin_translations(): void {
		global $wp_test_transients, $wp_test_http_responses;

		$url = 'https://example.com/packages.json';

		$wp_test_http_responses[ $url ] = [
			'body'     => json_encode( [
				'translations' => [
					[
						'language' => 'fr_FR',
						'updated'  => '2024-06-15 12:00:00',
						'version'  => '1.0.0',
						'package'  => 'https://example.com/fr_FR.zip',
					],
				],
			] ),
			'response' => [ 'code' => 200, 'message' => 'OK' ],
		];

		$updater = Updater::get_instance();
		$updater->register( 'plugin', 'my-plugin', $url );

		$value                = new \stdClass();
		$value->translations = [];

		$result = $updater->filter_plugin_translations( $value );

		$this->assertIsObject( $result );
		$this->assertObjectHasProperty( 'translations', $result );
		$this->assertNotEmpty( $result->translations );
	}

	/**
	 * Tests reset clears the singleton.
	 *
	 * @covers ::reset
	 */
	public function test_reset(): void {
		$instance1 = Updater::get_instance();
		$instance1->register( 'plugin', 'my-plugin', 'https://example.com/packages.json' );

		Updater::reset();

		$instance2 = Updater::get_instance();

		$this->assertNotSame( $instance1, $instance2 );
		$this->assertFalse( $instance2->is_registered( 'plugin', 'my-plugin' ) );
	}

	/**
	 * Tests refresh_all.
	 *
	 * @covers ::refresh_all
	 */
	public function test_refresh_all(): void {
		global $wp_test_http_responses;

		$url = 'https://example.com/packages.json';

		$wp_test_http_responses[ $url ] = [
			'body'     => json_encode( [ 'translations' => [] ] ),
			'response' => [ 'code' => 200, 'message' => 'OK' ],
		];

		$updater = Updater::get_instance();
		$updater->register( 'plugin', 'my-plugin', $url );

		$count = $updater->refresh_all();

		$this->assertSame( 1, $count );
	}
}

<?php
/**
 * Tests for the Installer class.
 *
 * @package TranslationsPress\Updater\Tests
 */

declare(strict_types=1);

namespace TranslationsPress\Tests;

use PHPUnit\Framework\TestCase;
use TranslationsPress\API;
use TranslationsPress\Cache;
use TranslationsPress\Installer;
use TranslationsPress\Project;

/**
 * Test case for the Installer class.
 *
 * @covers \TranslationsPress\Installer
 */
class InstallerTest extends TestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		global $wp_test_transients, $wp_test_locale;
		$wp_test_transients = [];
		$wp_test_locale     = 'fr_FR';
	}

	/**
	 * Creates a test project with mock translations.
	 *
	 * @param array $translations Translations data.
	 * @return Project Project instance.
	 */
	private function create_project( array $translations = [] ): Project {
		$default_translations = [
			[
				'language' => 'fr_FR',
				'version'  => '1.0.0',
				'updated'  => '2024-06-15 12:00:00',
				'package'  => 'https://example.com/fr_FR.zip',
			],
			[
				'language' => 'de_DE',
				'version'  => '1.0.0',
				'updated'  => '2024-06-15 12:00:00',
				'package'  => 'https://example.com/de_DE.zip',
			],
		];

		if ( empty( $translations ) ) {
			$translations = $default_translations;
		}

		$cache = new Cache();
		$api   = new API( 'https://example.com/packages.json', false, $cache );

		// Pre-populate cache with translations.
		$cache->set( $api->get_cache_key(), [ 'translations' => $translations ] );

		return new Project( 'plugin', 'my-plugin', $api, false, true );
	}

	/**
	 * Tests constructor.
	 *
	 * @covers ::__construct
	 */
	public function test_constructor(): void {
		$project   = $this->create_project();
		$installer = new Installer( $project );

		$this->assertInstanceOf( Installer::class, $installer );
	}

	/**
	 * Tests constructor with logger.
	 *
	 * @covers ::__construct
	 */
	public function test_constructor_with_logger(): void {
		$project = $this->create_project();
		$logs    = [];
		$logger  = function ( $message ) use ( &$logs ) {
			$logs[] = $message;
		};

		$installer = new Installer( $project, $logger );

		$this->assertInstanceOf( Installer::class, $installer );
	}

	/**
	 * Tests set_logger method.
	 *
	 * @covers ::set_logger
	 */
	public function test_set_logger(): void {
		$project   = $this->create_project();
		$installer = new Installer( $project );
		$logs      = [];
		$logger    = function ( $message ) use ( &$logs ) {
			$logs[] = $message;
		};

		$installer->set_logger( $logger );

		// Logger should be set (we can't easily verify without triggering a log).
		$this->assertInstanceOf( Installer::class, $installer );
	}

	/**
	 * Tests install skips en_US locale.
	 *
	 * @covers ::install
	 */
	public function test_install_skips_en_us(): void {
		$project   = $this->create_project();
		$installer = new Installer( $project );

		$result = $installer->install( 'en_US' );

		$this->assertTrue( $result );
		$this->assertEmpty( $installer->get_installed_locales() );
	}

	/**
	 * Tests install returns false when no translations available.
	 *
	 * @covers ::install
	 */
	public function test_install_returns_false_when_no_translations(): void {
		// Create project with empty translations.
		$cache   = new Cache();
		$api     = new API( 'https://example.com/packages.json', false, $cache );
		$project = new Project( 'plugin', 'my-plugin', $api, false, true );

		$installer = new Installer( $project );
		$result    = $installer->install( 'fr_FR' );

		$this->assertFalse( $result );
	}

	/**
	 * Tests install returns false when locale not found.
	 *
	 * @covers ::install
	 */
	public function test_install_returns_false_when_locale_not_found(): void {
		$project   = $this->create_project();
		$installer = new Installer( $project );

		$result = $installer->install( 'es_ES' );

		$this->assertFalse( $result );
	}

	/**
	 * Tests get_installed_locales returns empty by default.
	 *
	 * @covers ::get_installed_locales
	 */
	public function test_get_installed_locales_empty_by_default(): void {
		$project   = $this->create_project();
		$installer = new Installer( $project );

		$this->assertEmpty( $installer->get_installed_locales() );
	}

	/**
	 * Tests clear_installed clears the list.
	 *
	 * @covers ::clear_installed
	 */
	public function test_clear_installed(): void {
		$project   = $this->create_project();
		$installer = new Installer( $project );

		$installer->clear_installed();

		$this->assertEmpty( $installer->get_installed_locales() );
	}

	/**
	 * Tests install with fr_FR locale (download will fail but flow is tested).
	 *
	 * @covers ::install
	 */
	public function test_install_flow_for_valid_locale(): void {
		$project   = $this->create_project();
		$installer = new Installer( $project );

		// Will fail because download fails in test environment.
		$result = $installer->install( 'fr_FR' );

		// Should fail due to mock download failure.
		$this->assertFalse( $result );
	}

	/**
	 * Tests install with de_DE locale.
	 *
	 * @covers ::install
	 */
	public function test_install_flow_for_different_locale(): void {
		$project   = $this->create_project();
		$installer = new Installer( $project );

		// Will fail because download fails in test environment.
		$result = $installer->install( 'de_DE' );

		// Should fail due to mock download failure.
		$this->assertFalse( $result );
	}

	/**
	 * Tests install returns false when translation package is empty.
	 *
	 * @covers ::install
	 */
	public function test_install_returns_false_when_package_empty(): void {
		// Create project with translation without package URL.
		$translations = [
			[
				'language' => 'fr_FR',
				'version'  => '1.0.0',
				'updated'  => '2024-06-15 12:00:00',
				'package'  => '', // Empty package.
			],
		];
		$project      = $this->create_project( $translations );
		$installer    = new Installer( $project );

		$result = $installer->install( 'fr_FR' );

		$this->assertFalse( $result );
	}

	/**
	 * Tests logging is called when logger is set.
	 *
	 * @covers ::install
	 */
	public function test_install_logs_messages(): void {
		$project = $this->create_project();
		$logs    = [];
		$logger  = function ( $message ) use ( &$logs ) {
			$logs[] = $message;
		};

		$installer = new Installer( $project, $logger );
		$installer->install( 'en_US' );

		// Should have logged at least one message.
		$this->assertNotEmpty( $logs );
	}
}

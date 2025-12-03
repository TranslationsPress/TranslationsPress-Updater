<?php
/**
 * Tests for the API class.
 *
 * @package TranslationsPress\Updater\Tests
 */

declare(strict_types=1);

namespace TranslationsPress\Tests;

use PHPUnit\Framework\TestCase;
use TranslationsPress\API;
use TranslationsPress\Cache;

/**
 * API class tests.
 *
 * @coversDefaultClass \TranslationsPress\API
 */
class APITest extends TestCase {

	/**
	 * Clears globals before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		global $wp_test_transients, $wp_test_http_responses;
		$wp_test_transients     = [];
		$wp_test_http_responses = [];
	}

	/**
	 * Tests API URL getter.
	 *
	 * @covers ::__construct
	 * @covers ::get_api_url
	 */
	public function test_get_api_url(): void {
		$cache = new Cache();
		$api   = new API( 'https://example.com/packages.json', false, $cache );

		$this->assertSame( 'https://example.com/packages.json', $api->get_api_url() );
	}

	/**
	 * Tests API cache key generation.
	 *
	 * @covers ::get_cache_key
	 */
	public function test_get_cache_key(): void {
		$cache = new Cache();
		$api   = new API( 'https://example.com/packages.json', false, $cache );

		$this->assertNotEmpty( $api->get_cache_key() );
		$this->assertIsString( $api->get_cache_key() );
	}

	/**
	 * Tests centralized flag.
	 *
	 * @covers ::is_centralized
	 */
	public function test_is_centralized(): void {
		$cache = new Cache();

		$single_api = new API( 'https://example.com/packages.json', false, $cache );
		$this->assertFalse( $single_api->is_centralized() );

		$central_api = new API( 'https://example.com/all-packages.json', true, $cache );
		$this->assertTrue( $central_api->is_centralized() );
	}

	/**
	 * Tests get_translations returns cached data.
	 *
	 * @covers ::get_translations
	 */
	public function test_get_translations_returns_cached_data(): void {
		$cache     = new Cache();
		$api       = new API( 'https://example.com/packages.json', false, $cache );
		$cache_key = $api->get_cache_key();

		$cached_data = [
			'translations' => [
				[ 'language' => 'fr_FR', 'package' => 'https://example.com/fr_FR.zip' ],
			],
		];

		$cache->set( $cache_key, $cached_data );

		$result = $api->get_translations();

		$this->assertSame( $cached_data, $result );
	}

	/**
	 * Tests get_translations makes HTTP request when cache is empty.
	 *
	 * @covers ::get_translations
	 */
	public function test_get_translations_makes_http_request(): void {
		global $wp_test_http_responses;

		$url   = 'https://example.com/packages.json';
		$cache = new Cache();
		$api   = new API( $url, false, $cache );

		$response_data = [
			'translations' => [
				[ 'language' => 'de_DE', 'package' => 'https://example.com/de_DE.zip' ],
			],
		];

		$wp_test_http_responses[ $url ] = [
			'body'     => json_encode( $response_data ),
			'response' => [ 'code' => 200, 'message' => 'OK' ],
		];

		$result = $api->get_translations();

		$this->assertSame( $response_data, $result );
	}

	/**
	 * Tests get_translations returns empty array on HTTP error.
	 *
	 * @covers ::get_translations
	 */
	public function test_get_translations_returns_empty_on_error(): void {
		$cache = new Cache();
		$api   = new API( 'https://example.com/nonexistent.json', false, $cache );

		// No mock response = WP_Error.
		$result = $api->get_translations();

		$this->assertSame( [], $result );
	}

	/**
	 * Tests get_translations returns empty array on non-200 response.
	 *
	 * @covers ::get_translations
	 */
	public function test_get_translations_returns_empty_on_non_200(): void {
		global $wp_test_http_responses;

		$url   = 'https://example.com/packages.json';
		$cache = new Cache();
		$api   = new API( $url, false, $cache );

		$wp_test_http_responses[ $url ] = [
			'body'     => 'Not Found',
			'response' => [ 'code' => 404, 'message' => 'Not Found' ],
		];

		$result = $api->get_translations();

		$this->assertSame( [], $result );
	}

	/**
	 * Tests get_translations returns empty array on invalid JSON.
	 *
	 * @covers ::get_translations
	 */
	public function test_get_translations_returns_empty_on_invalid_json(): void {
		global $wp_test_http_responses;

		$url   = 'https://example.com/packages.json';
		$cache = new Cache();
		$api   = new API( $url, false, $cache );

		$wp_test_http_responses[ $url ] = [
			'body'     => 'not valid json',
			'response' => [ 'code' => 200, 'message' => 'OK' ],
		];

		$result = $api->get_translations();

		$this->assertSame( [], $result );
	}

	/**
	 * Tests get_translations for single project API with slug.
	 *
	 * @covers ::get_translations
	 */
	public function test_get_translations_single_api(): void {
		$cache     = new Cache();
		$api       = new API( 'https://example.com/packages.json', false, $cache );
		$cache_key = $api->get_cache_key();

		$data = [
			'translations' => [
				[ 'language' => 'es_ES', 'package' => 'https://example.com/es_ES.zip' ],
			],
		];

		$cache->set( $cache_key, $data );

		$result = $api->get_translations( 'my-plugin' );

		$this->assertSame( $data, $result );
	}

	/**
	 * Tests get_translations for centralized API.
	 *
	 * @covers ::get_translations
	 */
	public function test_get_translations_centralized_api(): void {
		global $wp_test_http_responses;

		$url   = 'https://example.com/all-packages.json';
		$cache = new Cache();
		$api   = new API( $url, true, $cache );

		$data = [
			'my-plugin' => [
				'translations' => [
					[ 'language' => 'it_IT', 'package' => 'https://example.com/my-plugin/it_IT.zip' ],
				],
			],
			'my-addon' => [
				'translations' => [
					[ 'language' => 'it_IT', 'package' => 'https://example.com/my-addon/it_IT.zip' ],
				],
			],
		];

		$wp_test_http_responses[ $url ] = [
			'body'     => json_encode( $data ),
			'response' => [ 'code' => 200, 'message' => 'OK' ],
		];

		$result = $api->get_translations( 'my-addon' );

		$expected = [
			'translations' => [
				[ 'language' => 'it_IT', 'package' => 'https://example.com/my-addon/it_IT.zip' ],
			],
		];

		$this->assertSame( $expected, $result );
	}

	/**
	 * Tests refresh clears cache and fetches fresh data.
	 *
	 * @covers ::refresh
	 */
	public function test_refresh(): void {
		global $wp_test_http_responses;

		$url   = 'https://example.com/packages.json';
		$cache = new Cache();
		$api   = new API( $url, false, $cache );

		// Set old cached data.
		$cache->set( $api->get_cache_key(), [ 'old' => 'data' ] );

		// Setup fresh response.
		$fresh_data = [ 'fresh' => 'data' ];
		$wp_test_http_responses[ $url ] = [
			'body'     => json_encode( $fresh_data ),
			'response' => [ 'code' => 200, 'message' => 'OK' ],
		];

		$result = $api->refresh();

		$this->assertTrue( $result );
		$this->assertSame( $fresh_data, $api->get_translations() );
	}

	/**
	 * Tests cache getter.
	 *
	 * @covers ::get_cache
	 */
	public function test_get_cache(): void {
		$cache = new Cache();
		$api   = new API( 'https://example.com/packages.json', false, $cache );

		$this->assertSame( $cache, $api->get_cache() );
	}
}

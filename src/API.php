<?php
/**
 * API client for TranslationsPress.
 *
 * Handles communication with the TranslationsPress API for fetching translations.
 *
 * @package TranslationsPress\Updater
 * @since   2.0.0
 */

declare(strict_types=1);

namespace TranslationsPress;

/**
 * API class for fetching translation data from TranslationsPress.
 *
 * Supports both single-project endpoints and centralized multi-project APIs
 * (like GravityForms uses for all their add-ons).
 *
 * @since 2.0.0
 */
class API {

	/**
	 * Default request timeout in seconds.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	public const DEFAULT_TIMEOUT = 3;

	/**
	 * API URL.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	private string $api_url;

	/**
	 * Whether this is a centralized (multi-project) API.
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	private bool $is_centralized;

	/**
	 * Cache instance.
	 *
	 * @since 2.0.0
	 * @var Cache
	 */
	private Cache $cache;

	/**
	 * Request timeout in seconds.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	private int $timeout;

	/**
	 * Whether V2 API was successfully resolved.
	 *
	 * @since 2.1.0
	 * @var bool|null Null = not yet tried, true = V2 available, false = V1 only.
	 */
	private ?bool $v2_supported = null;

	/**
	 * Logger callback.
	 *
	 * @since 2.0.0
	 * @var callable|null
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param string        $api_url        Full API URL for translations.
	 * @param bool          $is_centralized Whether the API serves multiple projects.
	 * @param Cache|null    $cache          Cache instance. Created if not provided.
	 * @param int           $timeout        Request timeout in seconds.
	 * @param callable|null $logger         Optional logger callback.
	 */
	public function __construct(
		string $api_url,
		bool $is_centralized = false,
		?Cache $cache = null,
		int $timeout = self::DEFAULT_TIMEOUT,
		?callable $logger = null
	) {
		$this->api_url        = $api_url;
		$this->is_centralized = $is_centralized;
		$this->cache          = $cache ?? new Cache();
		$this->timeout        = $timeout;
		$this->logger         = $logger;

		// Share logger with cache.
		if ( null !== $logger ) {
			$this->cache->set_logger( $logger );
		}
	}

	/**
	 * Gets translations for a project.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug Project slug. Only used for centralized APIs.
	 * @return array<string, mixed> Translation data with 'translations' key.
	 */
	public function get_translations( string $slug = '' ): array {
		$cache_key = $this->get_cache_key();

		// For centralized APIs, check if we have cached data for this specific project.
		if ( $this->is_centralized && '' !== $slug ) {
			$project_data = $this->cache->get_project( $cache_key, $slug );
			if ( null !== $project_data ) {
				return $project_data;
			}

			// Fetch all projects and return the requested one.
			$all_data = $this->fetch_and_cache_all();
			return $all_data[ $slug ] ?? [];
		}

		// For single-project APIs, use standard caching.
		$cached = $this->cache->get( $cache_key );
		if ( null !== $cached ) {
			return $cached;
		}

		return $this->fetch_and_cache_single();
	}

	/**
	 * Fetches and caches all translations from a centralized API.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array<string, mixed>> All projects data.
	 */
	private function fetch_and_cache_all(): array {
		$data = $this->fetch();

		if ( empty( $data ) ) {
			return [];
		}

		$cache_key = $this->get_cache_key();
		$this->cache->set( $cache_key, $data );

		return $data;
	}

	/**
	 * Fetches and caches translations from a single-project API.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, mixed> Translation data.
	 */
	private function fetch_and_cache_single(): array {
		$data = $this->fetch();

		if ( empty( $data ) ) {
			return [];
		}

		$cache_key = $this->get_cache_key();
		$this->cache->set( $cache_key, $data );

		return $data;
	}

	/**
	 * Fetches translation data from the API.
	 *
	 * For non-centralized APIs, tries packages-v2.json first for version-aware
	 * translation matching, then falls back to the original packages.json URL.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, mixed> Decoded JSON response or empty array on failure.
	 */
	private function fetch(): array {
		// Try V2 first for non-centralized APIs.
		if ( ! $this->is_centralized && null === $this->v2_supported ) {
			$v2_url = $this->get_v2_url();

			if ( '' !== $v2_url ) {
				$this->log( sprintf( 'Trying V2 API: %s', $v2_url ) );

				$v2_data = $this->fetch_url( $v2_url );

				if ( ! empty( $v2_data ) && isset( $v2_data['api_version'] ) && 2 === (int) $v2_data['api_version'] ) {
					$this->v2_supported = true;
					$this->log( 'V2 API available, using versioned translations' );
					return $v2_data;
				}

				$this->v2_supported = false;
				$this->log( 'V2 API not available, falling back to V1' );
			}
		}

		// V2 already confirmed available on a previous fetch.
		if ( true === $this->v2_supported && ! $this->is_centralized ) {
			$v2_url  = $this->get_v2_url();
			$v2_data = '' !== $v2_url ? $this->fetch_url( $v2_url ) : [];

			if ( ! empty( $v2_data ) ) {
				return $v2_data;
			}

			// V2 failed this time, fall through to V1.
			$this->log( 'V2 fetch failed, falling back to V1' );
		}

		return $this->fetch_url( $this->api_url );
	}

	/**
	 * Fetches JSON data from a URL.
	 *
	 * @since 2.1.0
	 *
	 * @param string $url URL to fetch.
	 * @return array<string, mixed> Decoded JSON response or empty array on failure.
	 */
	private function fetch_url( string $url ): array {
		$this->log( sprintf( 'Fetching translations from: %s', $url ) );

		$response = wp_remote_get(
			$url,
			[
				'timeout' => $this->timeout,
				'headers' => [
					'Accept' => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			$this->log(
				sprintf(
					'API request failed: %s',
					$response->get_error_message()
				),
				'error'
			);
			return [];
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			$this->log(
				sprintf( 'API request returned status %d', $status_code ),
				'error'
			);
			return [];
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			$this->log( 'API response is not valid JSON', 'error' );
			return [];
		}

		$this->log( sprintf( 'Successfully fetched translations (%d bytes)', strlen( $body ) ) );

		return $data;
	}

	/**
	 * Refreshes the cache by fetching fresh data from the API.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True on success, false on failure.
	 */
	public function refresh(): bool {
		$cache_key = $this->get_cache_key();
		$this->cache->delete( $cache_key );

		$data = $this->fetch();

		if ( empty( $data ) ) {
			return false;
		}

		return $this->cache->set( $cache_key, $data );
	}

	/**
	 * Gets the V2 API URL by replacing packages.json with packages-v2.json.
	 *
	 * @since 2.1.0
	 *
	 * @return string V2 URL, or empty string if URL cannot be transformed.
	 */
	private function get_v2_url(): string {
		$suffix = '/packages.json';

		if ( substr( $this->api_url, -strlen( $suffix ) ) === $suffix ) {
			return substr( $this->api_url, 0, -strlen( $suffix ) ) . '/packages-v2.json';
		}

		return '';
	}

	/**
	 * Checks whether the API responded with V2 format.
	 *
	 * @since 2.1.0
	 *
	 * @return bool True if V2 is supported.
	 */
	public function is_v2(): bool {
		return true === $this->v2_supported;
	}

	/**
	 * Gets the cache key for this API.
	 *
	 * @since 2.0.0
	 *
	 * @return string Cache key.
	 */
	public function get_cache_key(): string {
		// Use a hash of the URL to create a unique, safe key.
		return 'api_' . md5( $this->api_url );
	}

	/**
	 * Gets the API URL.
	 *
	 * @since 2.0.0
	 *
	 * @return string API URL.
	 */
	public function get_api_url(): string {
		return $this->api_url;
	}

	/**
	 * Checks if this is a centralized API.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if centralized, false otherwise.
	 */
	public function is_centralized(): bool {
		return $this->is_centralized;
	}

	/**
	 * Gets the cache instance.
	 *
	 * @since 2.0.0
	 *
	 * @return Cache Cache instance.
	 */
	public function get_cache(): Cache {
		return $this->cache;
	}

	/**
	 * Sets the request timeout.
	 *
	 * @since 2.0.0
	 *
	 * @param int $timeout Timeout in seconds.
	 * @return void
	 */
	public function set_timeout( int $timeout ): void {
		$this->timeout = max( 1, $timeout );
	}

	/**
	 * Sets the logger callback.
	 *
	 * @since 2.0.0
	 *
	 * @param callable|null $logger Logger callback or null to disable.
	 * @return void
	 */
	public function set_logger( ?callable $logger ): void {
		$this->logger = $logger;
		$this->cache->set_logger( $logger );
	}

	/**
	 * Logs a message if logger is configured.
	 *
	 * @since 2.0.0
	 *
	 * @param string $message Message to log.
	 * @param string $level   Log level: 'debug', 'error'.
	 * @return void
	 */
	private function log( string $message, string $level = 'debug' ): void {
		if ( null !== $this->logger && is_callable( $this->logger ) ) {
			$prefix = 'error' === $level ? '[TranslationsPress API ERROR] ' : '[TranslationsPress API] ';
			call_user_func( $this->logger, $prefix . $message );
		}
	}
}

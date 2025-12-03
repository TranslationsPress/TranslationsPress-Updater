<?php
/**
 * Cache management for TranslationsPress translations.
 *
 * Handles transient storage with intelligent expiration and cleanup.
 *
 * @package TranslationsPress\Updater
 * @since   2.0.0
 */

declare(strict_types=1);

namespace TranslationsPress;

/**
 * Cache class for managing translation data transients.
 *
 * @since 2.0.0
 */
class Cache {

	/**
	 * Default cache expiration in seconds (12 hours).
	 *
	 * @since 2.0.0
	 * @var int
	 */
	public const DEFAULT_EXPIRATION = 12 * HOUR_IN_SECONDS;

	/**
	 * Minimum cache lifespan before cleanup (15 seconds).
	 *
	 * Prevents cache thrashing during rapid transient updates.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	public const MIN_CACHE_LIFESPAN = 15;

	/**
	 * Transient key prefix.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public const TRANSIENT_PREFIX = 't15s_';

	/**
	 * Cache expiration in seconds.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	private int $expiration;

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
	 * @param int           $expiration Cache expiration in seconds. Default 12 hours.
	 * @param callable|null $logger     Optional logger callback for debug messages.
	 */
	public function __construct( int $expiration = self::DEFAULT_EXPIRATION, ?callable $logger = null ) {
		$this->expiration = $expiration;
		$this->logger     = $logger;
	}

	/**
	 * Gets cached translations data.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key Cache key (project slug or API identifier).
	 * @return array<string, mixed>|null Cached data or null if not found/expired.
	 */
	public function get( string $key ): ?array {
		$transient_key = $this->get_transient_key( $key );
		$cached        = get_site_transient( $transient_key );

		if ( false === $cached || ! is_object( $cached ) ) {
			$this->log( sprintf( 'Cache miss for key: %s', $key ) );
			return null;
		}

		// Check if cache has expired based on our own timestamp.
		if ( $this->is_expired( $cached ) ) {
			$this->log( sprintf( 'Cache expired for key: %s', $key ) );
			return null;
		}

		$this->log( sprintf( 'Cache hit for key: %s', $key ) );

		return (array) $cached->data;
	}

	/**
	 * Gets cached data for a specific project from a multi-project cache.
	 *
	 * @since 2.0.0
	 *
	 * @param string $cache_key   Main cache key.
	 * @param string $project_key Project identifier within the cache.
	 * @return array<string, mixed>|null Project data or null if not found.
	 */
	public function get_project( string $cache_key, string $project_key ): ?array {
		$data = $this->get( $cache_key );

		if ( null === $data || ! isset( $data[ $project_key ] ) ) {
			return null;
		}

		return is_array( $data[ $project_key ] ) ? $data[ $project_key ] : null;
	}

	/**
	 * Sets cached translations data.
	 *
	 * @since 2.0.0
	 *
	 * @param string               $key  Cache key.
	 * @param array<string, mixed> $data Data to cache.
	 * @return bool True on success, false on failure.
	 */
	public function set( string $key, array $data ): bool {
		$transient_key = $this->get_transient_key( $key );

		$cached_data = (object) [
			'data'          => $data,
			'_last_checked' => time(),
			'_version'      => '2.0.0',
		];

		$result = set_site_transient( $transient_key, $cached_data, $this->expiration );

		$this->log(
			sprintf(
				'Cache %s for key: %s (expiration: %d seconds)',
				$result ? 'set' : 'failed to set',
				$key,
				$this->expiration
			)
		);

		return $result;
	}

	/**
	 * Updates a specific project within a multi-project cache.
	 *
	 * @since 2.0.0
	 *
	 * @param string               $cache_key   Main cache key.
	 * @param string               $project_key Project identifier.
	 * @param array<string, mixed> $project_data Project data to cache.
	 * @return bool True on success, false on failure.
	 */
	public function set_project( string $cache_key, string $project_key, array $project_data ): bool {
		$data = $this->get( $cache_key ) ?? [];

		$data[ $project_key ] = $project_data;

		return $this->set( $cache_key, $data );
	}

	/**
	 * Deletes cached data.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key Cache key.
	 * @return bool True on success, false on failure.
	 */
	public function delete( string $key ): bool {
		$transient_key = $this->get_transient_key( $key );
		$result        = delete_site_transient( $transient_key );

		$this->log( sprintf( 'Cache %s for key: %s', $result ? 'deleted' : 'failed to delete', $key ) );

		return $result;
	}

	/**
	 * Cleans up stale cache entries based on minimum lifespan.
	 *
	 * This prevents cache thrashing when transients are updated multiple
	 * times during a single request.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key Cache key to potentially clean.
	 * @return bool True if cache was cleaned, false otherwise.
	 */
	public function maybe_clean( string $key ): bool {
		$transient_key = $this->get_transient_key( $key );
		$cached        = get_site_transient( $transient_key );

		if ( false === $cached || ! is_object( $cached ) ) {
			return false;
		}

		// Don't delete if recently updated.
		if ( ! isset( $cached->_last_checked ) ) {
			return false;
		}

		$age = time() - $cached->_last_checked;

		if ( $age <= self::MIN_CACHE_LIFESPAN ) {
			$this->log( sprintf( 'Cache cleanup skipped for key: %s (age: %d seconds)', $key, $age ) );
			return false;
		}

		return $this->delete( $key );
	}

	/**
	 * Checks if cached data has expired.
	 *
	 * @since 2.0.0
	 *
	 * @param object $cached Cached data object.
	 * @return bool True if expired, false otherwise.
	 */
	private function is_expired( object $cached ): bool {
		if ( ! isset( $cached->_last_checked ) ) {
			return true;
		}

		$age = time() - $cached->_last_checked;

		return $age > $this->expiration;
	}

	/**
	 * Generates a transient key from a cache key.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key Cache key.
	 * @return string Transient key.
	 */
	private function get_transient_key( string $key ): string {
		// Transient keys have a max length of 172 characters for site transients.
		$key = self::TRANSIENT_PREFIX . sanitize_key( $key );

		if ( strlen( $key ) > 167 ) {
			// Use hash for long keys, preserving prefix.
			$key = self::TRANSIENT_PREFIX . md5( $key );
		}

		return $key;
	}

	/**
	 * Logs a debug message if logger is configured.
	 *
	 * @since 2.0.0
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	private function log( string $message ): void {
		if ( null !== $this->logger && is_callable( $this->logger ) ) {
			call_user_func( $this->logger, '[TranslationsPress Cache] ' . $message );
		}
	}

	/**
	 * Gets the cache expiration value.
	 *
	 * @since 2.0.0
	 *
	 * @return int Expiration in seconds.
	 */
	public function get_expiration(): int {
		return $this->expiration;
	}

	/**
	 * Sets the cache expiration value.
	 *
	 * @since 2.0.0
	 *
	 * @param int $expiration Expiration in seconds.
	 * @return void
	 */
	public function set_expiration( int $expiration ): void {
		$this->expiration = max( 0, $expiration );
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
	}
}

<?php
/**
 * TranslationsPress Updater - Standalone Version
 *
 * A single-file version for use without Composer.
 * Simply include this file and call TranslationsPress_Updater::register().
 *
 * @package TranslationsPress\Updater
 * @version 2.0.0
 * @license GPL-3.0-or-later
 *
 * @example
 * // Include this file.
 * require_once plugin_dir_path( __FILE__ ) . 'class-translationspress-updater.php';
 *
 * // Simple registration.
 * TranslationsPress_Updater::register(
 *     'plugin',
 *     'my-plugin',
 *     'https://packages.translationspress.com/my-vendor/my-plugin/packages.json'
 * );
 *
 * // With WordPress.org override.
 * TranslationsPress_Updater::register(
 *     'plugin',
 *     'my-plugin',
 *     'https://packages.translationspress.com/my-vendor/my-plugin/packages.json',
 *     [
 *         'override_wporg' => true,
 *         'wporg_fallback' => true,
 *     ]
 * );
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent multiple inclusions.
if ( class_exists( 'TranslationsPress_Updater' ) ) {
	return;
}

/**
 * TranslationsPress Updater class.
 *
 * Handles translation updates from TranslationsPress CDN.
 *
 * @since 2.0.0
 */
final class TranslationsPress_Updater {

	/**
	 * Library version.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const VERSION = '2.1.0';

	/**
	 * Default cache expiration (12 hours).
	 *
	 * @since 2.0.0
	 * @var int
	 */
	const CACHE_EXPIRATION = 43200;

	/**
	 * WordPress.org override mode: replace completely.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const WPORG_MODE_REPLACE = 'replace';

	/**
	 * WordPress.org override mode: fallback if T15S fails.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	const WPORG_MODE_FALLBACK = 'fallback';

	/**
	 * Singleton instance.
	 *
	 * @since 2.0.0
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Registered projects.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	private $projects = array();

	/**
	 * Cached API responses.
	 *
	 * @since 2.0.0
	 * @var array
	 */
	private $api_caches = array();

	/**
	 * Logger callback.
	 *
	 * @since 2.0.0
	 * @var callable|null
	 */
	private $logger = null;

	/**
	 * Whether hooks have been registered.
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	private $hooks_registered = false;

	/**
	 * Cache for available languages.
	 *
	 * @since 2.0.0
	 * @var array|null
	 */
	private $available_languages = null;

	/**
	 * Cache for installed translations.
	 *
	 * @since 2.0.0
	 * @var array|null
	 */
	private $installed_translations = null;

	/**
	 * Gets the singleton instance.
	 *
	 * @since 2.0.0
	 *
	 * @return self Singleton instance.
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 *
	 * @since 2.0.0
	 */
	private function __construct() {
		$this->register_hooks();
	}

	/**
	 * Registers a project for translation updates.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type    Project type: 'plugin' or 'theme'.
	 * @param string $slug    Project directory slug.
	 * @param string $api_url TranslationsPress API URL.
	 * @param array  $options {
	 *     Optional. Registration options.
	 *
	 *     @type bool $is_centralized  Whether API serves multiple projects. Default false.
	 *     @type bool $override_wporg  Whether to override wp.org translations. Default false.
	 *     @type bool $wporg_fallback  Whether to fallback to wp.org. Default true.
	 *     @type string $version       Plugin/theme version for V2 version matching. Default ''.
	 *     @type int  $cache_expiration Cache expiration in seconds. Default 43200.
	 * }
	 * @return bool True on success.
	 */
	public static function register( $type, $slug, $api_url, $options = array() ) {
		return self::instance()->add_project( $type, $slug, $api_url, $options );
	}

	/**
	 * Registers multiple add-ons with a centralized API.
	 *
	 * @since 2.0.0
	 *
	 * @param string $api_url Centralized API URL.
	 * @param array  $slugs   Array of plugin slugs.
	 * @param array  $options Registration options.
	 * @return bool True on success.
	 */
	public static function register_addons( $api_url, $slugs, $options = array() ) {
		$options['is_centralized'] = true;
		$success                   = true;

		foreach ( $slugs as $slug ) {
			if ( ! self::register( 'plugin', $slug, $api_url, $options ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Sets the logger callback.
	 *
	 * @since 2.0.0
	 *
	 * @param callable|null $logger Logger callback.
	 * @return void
	 */
	public static function set_logger( $logger ) {
		self::instance()->logger = $logger;
	}

	/**
	 * Adds a project to the registry.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type    Project type.
	 * @param string $slug    Project slug.
	 * @param string $api_url API URL.
	 * @param array  $options Options.
	 * @return bool True on success.
	 */
	private function add_project( $type, $slug, $api_url, $options ) {
		$options = wp_parse_args(
			$options,
			array(
				'is_centralized'   => false,
				'override_wporg'   => false,
				'wporg_fallback'   => true,
				'version'          => '',
				'cache_expiration' => self::CACHE_EXPIRATION,
			)
		);

		$identifier = $type . '_' . $slug;

		$this->projects[ $identifier ] = array(
			'type'             => $type,
			'slug'             => $slug,
			'api_url'          => $api_url,
			'is_centralized'   => (bool) $options['is_centralized'],
			'override_wporg'   => (bool) $options['override_wporg'],
			'wporg_fallback'   => (bool) $options['wporg_fallback'],
			'version'          => (string) $options['version'],
			'cache_expiration' => (int) $options['cache_expiration'],
		);

		if ( $options['override_wporg'] ) {
			$this->register_wporg_override( $identifier );
		}

		$this->log(
			sprintf(
				'Registered %s "%s" with API: %s',
				$type,
				$slug,
				$api_url
			)
		);

		return true;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function register_hooks() {
		if ( $this->hooks_registered ) {
			return;
		}

		add_filter( 'site_transient_update_plugins', array( $this, 'filter_plugin_updates' ) );
		add_filter( 'site_transient_update_themes', array( $this, 'filter_theme_updates' ) );
		add_filter( 'translations_api', array( $this, 'handle_translations_api' ), 10, 3 );

		add_action( 'set_site_transient_update_plugins', array( $this, 'clean_caches' ) );
		add_action( 'delete_site_transient_update_plugins', array( $this, 'clean_caches' ) );
		add_action( 'set_site_transient_update_themes', array( $this, 'clean_caches' ) );
		add_action( 'delete_site_transient_update_themes', array( $this, 'clean_caches' ) );

		$this->hooks_registered = true;
	}

	/**
	 * Registers WordPress.org override for a project.
	 *
	 * @since 2.0.0
	 *
	 * @param string $identifier Project identifier.
	 * @return void
	 */
	private function register_wporg_override( $identifier ) {
		$project = $this->projects[ $identifier ];
		$type    = $project['type'];
		$slug    = $project['slug'];
		$mode    = $project['wporg_fallback'] ? self::WPORG_MODE_FALLBACK : self::WPORG_MODE_REPLACE;

		// Filter translations_api for this specific project.
		add_filter(
			'translations_api',
			function ( $result, $requested_type, $args ) use ( $type, $slug, $identifier, $mode ) {
				return $this->filter_wporg_api( $result, $requested_type, $args, $type, $slug, $identifier, $mode );
			},
			5,
			3
		);

		// Block wp.org requests for replace mode.
		if ( self::WPORG_MODE_REPLACE === $mode ) {
			add_filter(
				'pre_http_request',
				function ( $preempt, $args, $url ) use ( $type, $slug ) {
					return $this->block_wporg_request( $preempt, $args, $url, $type, $slug );
				},
				10,
				3
			);
		}
	}

	/**
	 * Filters WordPress.org translation API for overridden projects.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed  $result         Current result.
	 * @param string $requested_type Requested type (plugins, themes).
	 * @param array  $args           Request arguments.
	 * @param string $type           Project type.
	 * @param string $slug           Project slug.
	 * @param string $identifier     Project identifier.
	 * @param string $mode           Override mode.
	 * @return mixed Filtered result.
	 */
	private function filter_wporg_api( $result, $requested_type, $args, $type, $slug, $identifier, $mode ) {
		$args = (array) $args;

		if ( ! isset( $args['slug'] ) || $args['slug'] !== $slug ) {
			return $result;
		}

		$expected_type = $type . 's';
		if ( $requested_type !== $expected_type ) {
			return $result;
		}

		$this->log( sprintf( 'Intercepting wp.org API for %s (mode: %s)', $identifier, $mode ) );

		// Fetch from T15S.
		$translations = $this->get_project_translations( $identifier );

		if ( ! empty( $translations ) ) {
			return array( 'translations' => $translations );
		}

		// Fallback mode: return false to let wp.org handle it.
		if ( self::WPORG_MODE_FALLBACK === $mode ) {
			$this->log( sprintf( 'T15S empty, falling back to wp.org for %s', $identifier ) );
			return $result;
		}

		// Replace mode: return empty result.
		return array( 'translations' => array() );
	}

	/**
	 * Blocks wp.org translation requests in replace mode.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed  $preempt Preempt value.
	 * @param array  $args    Request arguments.
	 * @param string $url     Request URL.
	 * @param string $type    Project type.
	 * @param string $slug    Project slug.
	 * @return mixed Filtered preempt value.
	 */
	private function block_wporg_request( $preempt, $args, $url, $type, $slug ) {
		// Only block translations API requests.
		if ( false === strpos( $url, 'api.wordpress.org/translations/' ) ) {
			return $preempt;
		}

		// Check if URL contains our slug.
		$pattern = sprintf( '/%ss/[^/]+/%s/', $type, preg_quote( $slug, '/' ) );
		if ( ! preg_match( $pattern, $url ) ) {
			return $preempt;
		}

		$this->log( sprintf( 'Blocking wp.org request for %s_%s: %s', $type, $slug, $url ) );

		// Return empty response.
		return array(
			'headers'       => array(),
			'body'          => wp_json_encode( array( 'translations' => array() ) ),
			'response'      => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'       => array(),
			'http_response' => null,
		);
	}

	/**
	 * Filters plugin update transient.
	 *
	 * @since 2.0.0
	 *
	 * @param object $value Transient value.
	 * @return object Filtered value.
	 */
	public function filter_plugin_updates( $value ) {
		return $this->filter_updates( $value, 'plugin' );
	}

	/**
	 * Filters theme update transient.
	 *
	 * @since 2.0.0
	 *
	 * @param object $value Transient value.
	 * @return object Filtered value.
	 */
	public function filter_theme_updates( $value ) {
		return $this->filter_updates( $value, 'theme' );
	}

	/**
	 * Filters update transient for a type.
	 *
	 * @since 2.0.0
	 *
	 * @param object $value Transient value.
	 * @param string $type  Project type.
	 * @return object Filtered value.
	 */
	private function filter_updates( $value, $type ) {
		if ( ! $value ) {
			$value = new stdClass();
		}

		if ( ! isset( $value->translations ) ) {
			$value->translations = array();
		}

		foreach ( $this->projects as $identifier => $project ) {
			if ( $project['type'] !== $type ) {
				continue;
			}

			$updates = $this->get_translation_updates( $identifier );

			foreach ( $updates as $update ) {
				$value->translations[] = $update;
			}
		}

		return $value;
	}

	/**
	 * Handles translations API requests.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed  $result         Current result.
	 * @param string $requested_type Requested type.
	 * @param array  $args           Request arguments.
	 * @return mixed Filtered result.
	 */
	public function handle_translations_api( $result, $requested_type, $args ) {
		$args = (array) $args;

		if ( ! isset( $args['slug'] ) ) {
			return $result;
		}

		$slug = $args['slug'];
		$type = rtrim( $requested_type, 's' );

		$identifier = $type . '_' . $slug;

		if ( ! isset( $this->projects[ $identifier ] ) ) {
			return $result;
		}

		$project = $this->projects[ $identifier ];

		// Skip if override is enabled (handled separately).
		if ( $project['override_wporg'] ) {
			return $result;
		}

		$translations = $this->get_project_translations( $identifier );

		return array( 'translations' => $translations );
	}

	/**
	 * Gets translation updates for a project.
	 *
	 * @since 2.0.0
	 *
	 * @param string $identifier Project identifier.
	 * @return array Translation updates.
	 */
	private function get_translation_updates( $identifier ) {
		$project = $this->projects[ $identifier ];
		$updates = array();

		$translations = $this->get_project_translations( $identifier );
		$locales      = $this->get_available_languages();
		$installed    = $this->get_installed_translations( $project['type'] );

		foreach ( $translations as $translation ) {
			if ( ! isset( $translation['language'] ) || ! in_array( $translation['language'], $locales, true ) ) {
				continue;
			}

			$locale     = $translation['language'];
			$slug       = $project['slug'];
			$type       = $project['type'];
			$updated    = isset( $translation['updated'] ) ? $translation['updated'] : '';
			$local_date = isset( $installed[ $slug ][ $locale ]['PO-Revision-Date'] )
				? $installed[ $slug ][ $locale ]['PO-Revision-Date']
				: '';

			// Compare dates to see if update is needed.
			if ( $local_date && $updated ) {
				try {
					$local_time  = new DateTime( $local_date );
					$remote_time = new DateTime( $updated );

					if ( $remote_time <= $local_time ) {
						continue;
					}
				} catch ( Exception $e ) {
					// Continue on date parse error.
					$this->log( sprintf( 'Date parse error for %s: %s', $identifier, $e->getMessage() ) );
				}
			}

			$updates[] = array(
				'type'       => $type,
				'slug'       => $slug,
				'language'   => $locale,
				'version'    => isset( $translation['version'] ) ? $translation['version'] : '',
				'updated'    => $updated,
				'package'    => isset( $translation['package'] ) ? $translation['package'] : '',
				'autoupdate' => true,
			);
		}

		return $updates;
	}

	/**
	 * Gets translations for a project.
	 *
	 * Tries V2 API first (packages-v2.json) for non-centralized projects,
	 * then falls back to V1 (packages.json). When V2 is available and a
	 * project version is set, resolves version-specific translations.
	 *
	 * @since 2.0.0
	 *
	 * @param string $identifier Project identifier.
	 * @return array Translations data.
	 */
	private function get_project_translations( $identifier ) {
		$project = $this->projects[ $identifier ];
		$api_url = $project['api_url'];

		// Try V2 first for non-centralized APIs.
		$data = null;
		if ( ! $project['is_centralized'] ) {
			$v2_url = $this->get_v2_url( $api_url );

			if ( '' !== $v2_url ) {
				$v2_data = $this->fetch_api( $v2_url, $project['cache_expiration'] );

				if ( ! empty( $v2_data ) && isset( $v2_data['api_version'] ) && 2 === (int) $v2_data['api_version'] ) {
					$data = $v2_data;
				}
			}
		}

		// Fallback to V1.
		if ( null === $data ) {
			$data = $this->fetch_api( $api_url, $project['cache_expiration'] );
		}

		if ( empty( $data ) ) {
			return array();
		}

		// Centralized API: look for project-specific data.
		if ( $project['is_centralized'] ) {
			$slug = $project['slug'];

			if ( isset( $data['projects'][ $slug ]['translations'] ) ) {
				return $data['projects'][ $slug ]['translations'];
			}

			// Try with type prefix.
			$key = $project['type'] . '_' . $slug;
			if ( isset( $data['projects'][ $key ]['translations'] ) ) {
				return $data['projects'][ $key ]['translations'];
			}

			return array();
		}

		// Resolve versioned translations for V2 data.
		if ( isset( $data['api_version'] ) && 2 === (int) $data['api_version'] && '' !== $project['version'] ) {
			return $this->resolve_versioned_translations( $data, $project['version'] );
		}

		// Single project API (V1).
		if ( isset( $data['translations'] ) ) {
			return $data['translations'];
		}

		return array();
	}

	/**
	 * Resolves version-specific translations from V2 API data.
	 *
	 * @since 2.1.0
	 *
	 * @param array  $data    V2 API response data.
	 * @param string $version Requested version.
	 * @return array Translations array.
	 */
	private function resolve_versioned_translations( $data, $version ) {
		$current_version = isset( $data['current_version'] ) ? $data['current_version'] : '';

		// If our version matches current, translations are already correct.
		if ( $version === $current_version ) {
			return isset( $data['translations'] ) ? $data['translations'] : array();
		}

		// Look for our version in the versions map.
		$versions = isset( $data['versions'] ) ? $data['versions'] : array();

		if ( empty( $versions ) || ! isset( $versions[ $version ] ) ) {
			// Requested version not available, fall back to current.
			return isset( $data['translations'] ) ? $data['translations'] : array();
		}

		$version_data = $versions[ $version ];

		// Build locale metadata index from current translations.
		$locale_meta = array();
		if ( isset( $data['translations'] ) ) {
			foreach ( $data['translations'] as $translation ) {
				if ( isset( $translation['language'] ) ) {
					$locale_meta[ $translation['language'] ] = $translation;
				}
			}
		}

		// Build resolved translations for the requested version.
		$resolved = array();
		foreach ( $version_data as $locale => $info ) {
			$info = (array) $info;

			if ( isset( $locale_meta[ $locale ] ) ) {
				$entry            = $locale_meta[ $locale ];
				$entry['version'] = $version;
				$entry['package'] = $info['package'];

				if ( ! empty( $info['updated'] ) ) {
					$entry['updated'] = $info['updated'];
				}

				$resolved[] = $entry;
			} else {
				$resolved[] = array(
					'language' => $locale,
					'version'  => $version,
					'updated'  => isset( $info['updated'] ) ? $info['updated'] : '',
					'package'  => $info['package'],
				);
			}
		}

		return $resolved;
	}

	/**
	 * Gets the V2 API URL from a V1 URL.
	 *
	 * @since 2.1.0
	 *
	 * @param string $api_url V1 API URL.
	 * @return string V2 URL or empty string.
	 */
	private function get_v2_url( $api_url ) {
		$suffix = '/packages.json';

		if ( substr( $api_url, -strlen( $suffix ) ) === $suffix ) {
			return substr( $api_url, 0, -strlen( $suffix ) ) . '/packages-v2.json';
		}

		return '';
	}

	/**
	 * Fetches data from API with caching.
	 *
	 * @since 2.0.0
	 *
	 * @param string $api_url    API URL.
	 * @param int    $expiration Cache expiration.
	 * @return array|null API data or null on failure.
	 */
	private function fetch_api( $api_url, $expiration ) {
		$cache_key = 't15s_' . md5( $api_url );

		// Check transient cache.
		$cached = get_site_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$this->log( sprintf( 'Fetching: %s', $api_url ) );

		$response = wp_remote_get(
			$api_url,
			array(
				'timeout' => 3,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log( sprintf( 'API error: %s', $response->get_error_message() ) );
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$this->log( sprintf( 'API returned HTTP %d', $code ) );
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			$this->log( 'API returned invalid JSON' );
			return null;
		}

		// Cache the result.
		set_site_transient( $cache_key, $data, $expiration );

		return $data;
	}

	/**
	 * Gets available languages.
	 *
	 * @since 2.0.0
	 *
	 * @return array Available language codes.
	 */
	private function get_available_languages() {
		if ( null === $this->available_languages ) {
			$this->available_languages = get_available_languages();

			// Include site locale even if not yet installed.
			$site_locale = get_locale();
			if ( ! in_array( $site_locale, $this->available_languages, true ) && 'en_US' !== $site_locale ) {
				$this->available_languages[] = $site_locale;
			}
		}

		return $this->available_languages;
	}

	/**
	 * Gets installed translations for a type.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type Project type.
	 * @return array Installed translations.
	 */
	private function get_installed_translations( $type ) {
		if ( null === $this->installed_translations ) {
			$this->installed_translations = array(
				'plugin' => wp_get_installed_translations( 'plugins' ),
				'theme'  => wp_get_installed_translations( 'themes' ),
			);
		}

		return isset( $this->installed_translations[ $type ] )
			? $this->installed_translations[ $type ]
			: array();
	}

	/**
	 * Cleans translation caches.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function clean_caches() {
		static $cleaned = false;

		// Only clean once per request.
		if ( $cleaned ) {
			return;
		}

		// Check cleanup protection.
		$protection_key = 't15s_cleanup_protection';
		$last_cleanup   = get_site_transient( $protection_key );

		if ( $last_cleanup && ( time() - $last_cleanup ) < 15 ) {
			return;
		}

		set_site_transient( $protection_key, time(), 60 );

		// Clear API caches.
		$urls_cleaned = array();

		foreach ( $this->projects as $project ) {
			$api_url = $project['api_url'];

			if ( in_array( $api_url, $urls_cleaned, true ) ) {
				continue;
			}

			$cache_key = 't15s_' . md5( $api_url );
			delete_site_transient( $cache_key );
			$urls_cleaned[] = $api_url;
		}

		// Clear static caches.
		$this->available_languages    = null;
		$this->installed_translations = null;

		$cleaned = true;

		$this->log( sprintf( 'Cleaned %d API caches', count( $urls_cleaned ) ) );
	}

	/**
	 * Logs a message.
	 *
	 * @since 2.0.0
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	private function log( $message ) {
		if ( null !== $this->logger && is_callable( $this->logger ) ) {
			call_user_func( $this->logger, '[T15S] ' . $message );
		}
	}
}

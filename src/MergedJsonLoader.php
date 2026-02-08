<?php
/**
 * Merged JSON translation loader for TranslationsPress.
 *
 * Uses the `pre_load_script_translations` filter to serve a single merged
 * JSON file for all JS script handles of a given textdomain, instead of
 * loading one JSON file per script handle.
 *
 * This dramatically reduces the number of file_get_contents() calls on
 * the block editor and other JS-heavy admin pages.
 *
 * How it works:
 * - TranslationsPress Language Packs include a `{slug}-{locale}-merged.json`
 *   file in each ZIP, containing ALL JS translations merged into one JED 1.x JSON.
 * - When the ZIP is extracted to `wp-content/languages/plugins/`, the merged
 *   file is available alongside the individual per-script JSON files.
 * - This class hooks into `pre_load_script_translations` and returns the
 *   merged JSON for any registered textdomain, short-circuiting the default
 *   per-file loading.
 *
 * @package TranslationsPress\Updater
 * @since   2.2.0
 */

declare(strict_types=1);

namespace TranslationsPress;

/**
 * Serves merged JSON translations to avoid per-script file lookups.
 *
 * @since 2.2.0
 */
class MergedJsonLoader {

	/**
	 * Registered text domains mapped to project type.
	 *
	 * @since 2.2.0
	 * @var array<string, string> textdomain => type ('plugin' or 'theme')
	 */
	private array $domains = [];

	/**
	 * Cached merged JSON contents per domain+locale.
	 *
	 * @since 2.2.0
	 * @var array<string, string|false> cache_key => JSON string or false
	 */
	private array $cache = [];

	/**
	 * Whether the filter hook is registered.
	 *
	 * @since 2.2.0
	 * @var bool
	 */
	private bool $hooked = false;

	/**
	 * Singleton instance.
	 *
	 * @since 2.2.0
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Gets the singleton instance.
	 *
	 * @since 2.2.0
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor for singleton.
	 *
	 * @since 2.2.0
	 */
	private function __construct() {}

	/**
	 * Registers a text domain for merged JSON loading.
	 *
	 * @since 2.2.0
	 *
	 * @param string $textdomain The plugin/theme text domain (= slug).
	 * @param string $type       Project type: 'plugin' or 'theme'.
	 * @return void
	 */
	public function register( string $textdomain, string $type = 'plugin' ): void {
		$this->domains[ $textdomain ] = $type;
		$this->ensure_hooked();
	}

	/**
	 * Unregisters a text domain.
	 *
	 * @since 2.2.0
	 *
	 * @param string $textdomain Text domain to unregister.
	 * @return void
	 */
	public function unregister( string $textdomain ): void {
		unset( $this->domains[ $textdomain ] );
	}

	/**
	 * Ensures the WordPress filter is registered.
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	private function ensure_hooked(): void {
		if ( $this->hooked ) {
			return;
		}

		add_filter( 'pre_load_script_translations', [ $this, 'filter_script_translations' ], 10, 4 );
		$this->hooked = true;
	}

	/**
	 * Filters script translations to return the merged JSON file.
	 *
	 * Hooked to `pre_load_script_translations`. Returning a non-null value
	 * short-circuits WordPress's default per-file loading.
	 *
	 * @since 2.2.0
	 *
	 * @param string|false|null $translations JSON-encoded translation data. Default null.
	 * @param string|false      $file         Path to the translation file to load.
	 * @param string            $handle       Name of the script to register a translation domain to.
	 * @param string            $domain       The text domain.
	 * @return string|false|null Merged JSON string, or null to use default behavior.
	 */
	public function filter_script_translations( $translations, $file, string $handle, string $domain ) {
		// Only intercept for registered domains.
		if ( ! isset( $this->domains[ $domain ] ) ) {
			return $translations;
		}

		$locale    = determine_locale();
		$cache_key = $domain . ':' . $locale;

		// Return from memory cache if already loaded this request.
		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ] ?: null;
		}

		// Build the merged file path.
		$type       = $this->domains[ $domain ];
		$merged_file = $this->get_merged_file_path( $domain, $locale, $type );

		if ( $merged_file && is_readable( $merged_file ) ) {
			$contents = file_get_contents( $merged_file );

			if ( ! empty( $contents ) ) {
				$this->cache[ $cache_key ] = $contents;
				return $contents;
			}
		}

		// No merged file found — mark as checked and fall through to default behavior.
		$this->cache[ $cache_key ] = false;
		return $translations;
	}

	/**
	 * Gets the path to the merged JSON file.
	 *
	 * Looks in the standard WordPress languages directory for the project type.
	 *
	 * @since 2.2.0
	 *
	 * @param string $domain Text domain (= plugin/theme slug).
	 * @param string $locale WordPress locale (e.g., 'fr_FR').
	 * @param string $type   Project type: 'plugin' or 'theme'.
	 * @return string|null File path if it exists, null otherwise.
	 */
	private function get_merged_file_path( string $domain, string $locale, string $type ): ?string {
		$dir = WP_LANG_DIR . '/' . $type . 's/';
		$file = $dir . $domain . '-' . $locale . '-merged.json';

		if ( file_exists( $file ) ) {
			return $file;
		}

		return null;
	}

	/**
	 * Clears the internal cache.
	 *
	 * Useful after installing new translations.
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->cache = [];
	}

	/**
	 * Checks if a domain is registered for merged loading.
	 *
	 * @since 2.2.0
	 *
	 * @param string $domain Text domain.
	 * @return bool True if registered.
	 */
	public function is_registered( string $domain ): bool {
		return isset( $this->domains[ $domain ] );
	}

	/**
	 * Gets all registered domains.
	 *
	 * @since 2.2.0
	 *
	 * @return array<string, string> domain => type mapping.
	 */
	public function get_domains(): array {
		return $this->domains;
	}

	/**
	 * Resets the singleton instance (for testing).
	 *
	 * @since 2.2.0
	 *
	 * @return void
	 */
	public static function reset(): void {
		if ( null !== self::$instance && self::$instance->hooked ) {
			remove_filter( 'pre_load_script_translations', [ self::$instance, 'filter_script_translations' ] );
		}

		self::$instance = null;
	}
}

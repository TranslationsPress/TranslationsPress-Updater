<?php
/**
 * Project representation for TranslationsPress.
 *
 * Represents a WordPress plugin or theme project registered for translations.
 *
 * @package TranslationsPress\Updater
 * @since   2.0.0
 */

declare(strict_types=1);

namespace TranslationsPress;

/**
 * Project class representing a plugin or theme.
 *
 * @since 2.0.0
 */
class Project {

	/**
	 * Project type: plugin.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public const TYPE_PLUGIN = 'plugin';

	/**
	 * Project type: theme.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public const TYPE_THEME = 'theme';

	/**
	 * Project type (plugin or theme).
	 *
	 * @since 2.0.0
	 * @var string
	 */
	private string $type;

	/**
	 * Project slug (directory name).
	 *
	 * @since 2.0.0
	 * @var string
	 */
	private string $slug;

	/**
	 * API instance for this project.
	 *
	 * @since 2.0.0
	 * @var API
	 */
	private API $api;

	/**
	 * Whether to override WordPress.org translations.
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	private bool $override_wporg;

	/**
	 * Fallback mode for WordPress.org.
	 *
	 * When true, T15S translations are used first, then fallback to wp.org.
	 * When false, wp.org calls are completely blocked for this project.
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	private bool $wporg_fallback;

	/**
	 * Cached installed translations.
	 *
	 * @since 2.0.0
	 * @var array<string, array<string, mixed>>|null
	 */
	private static ?array $installed_translations_cache = null;

	/**
	 * Cached available languages.
	 *
	 * @since 2.0.0
	 * @var array<int, string>|null
	 */
	private static ?array $available_languages_cache = null;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type           Project type: 'plugin' or 'theme'.
	 * @param string $slug           Project directory slug.
	 * @param API    $api            API instance for fetching translations.
	 * @param bool   $override_wporg Whether to override WordPress.org translations.
	 * @param bool   $wporg_fallback Whether to fallback to wp.org if T15S fails.
	 *
	 * @throws \InvalidArgumentException If type is invalid.
	 */
	public function __construct(
		string $type,
		string $slug,
		API $api,
		bool $override_wporg = false,
		bool $wporg_fallback = true
	) {
		if ( ! in_array( $type, [ self::TYPE_PLUGIN, self::TYPE_THEME ], true ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Invalid project type "%s". Must be "plugin" or "theme".', esc_html( $type ) )
			);
		}

		$this->type           = $type;
		$this->slug           = $slug;
		$this->api            = $api;
		$this->override_wporg = $override_wporg;
		$this->wporg_fallback = $wporg_fallback;
	}

	/**
	 * Gets the project type.
	 *
	 * @since 2.0.0
	 *
	 * @return string Project type.
	 */
	public function get_type(): string {
		return $this->type;
	}

	/**
	 * Gets the project type in plural form (for WordPress hooks).
	 *
	 * @since 2.0.0
	 *
	 * @return string Project type plural.
	 */
	public function get_type_plural(): string {
		return $this->type . 's';
	}

	/**
	 * Gets the project slug.
	 *
	 * @since 2.0.0
	 *
	 * @return string Project slug.
	 */
	public function get_slug(): string {
		return $this->slug;
	}

	/**
	 * Gets the API instance.
	 *
	 * @since 2.0.0
	 *
	 * @return API API instance.
	 */
	public function get_api(): API {
		return $this->api;
	}

	/**
	 * Checks if WordPress.org override is enabled.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if override is enabled.
	 */
	public function has_wporg_override(): bool {
		return $this->override_wporg;
	}

	/**
	 * Checks if WordPress.org fallback is enabled.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if fallback is enabled.
	 */
	public function has_wporg_fallback(): bool {
		return $this->wporg_fallback;
	}

	/**
	 * Sets the WordPress.org override mode.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $override Whether to override WordPress.org.
	 * @return void
	 */
	public function set_wporg_override( bool $override ): void {
		$this->override_wporg = $override;
	}

	/**
	 * Sets the WordPress.org fallback mode.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $fallback Whether to fallback to WordPress.org.
	 * @return void
	 */
	public function set_wporg_fallback( bool $fallback ): void {
		$this->wporg_fallback = $fallback;
	}

	/**
	 * Gets translations for this project.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, mixed> Translation data.
	 */
	public function get_translations(): array {
		if ( $this->api->is_centralized() ) {
			return $this->api->get_translations( $this->slug );
		}

		return $this->api->get_translations();
	}

	/**
	 * Gets translation updates available for this project.
	 *
	 * Compares remote translations with locally installed ones and returns
	 * only those that need updating.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array<string, mixed>> Array of translation updates.
	 */
	public function get_translation_updates(): array {
		$translations = $this->get_translations();

		if ( empty( $translations['translations'] ) ) {
			return [];
		}

		$updates             = [];
		$installed           = $this->get_installed_translations();
		$available_languages = $this->get_available_languages();

		foreach ( $translations['translations'] as $translation ) {
			if ( ! $this->should_update_translation( $translation, $installed, $available_languages ) ) {
				continue;
			}

			$translation['type'] = $this->type;
			$translation['slug'] = $this->slug;

			$updates[] = $translation;
		}

		return $updates;
	}

	/**
	 * Determines if a translation should be updated.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed>                $translation         Remote translation data.
	 * @param array<string, array<string, mixed>> $installed           Installed translations.
	 * @param array<int, string>                  $available_languages Available site languages.
	 * @return bool True if translation should be updated.
	 */
	private function should_update_translation(
		array $translation,
		array $installed,
		array $available_languages
	): bool {
		// Only update for languages available on the site.
		if ( ! in_array( $translation['language'], $available_languages, true ) ) {
			return false;
		}

		// If not installed, it needs updating.
		if ( ! isset( $installed[ $this->slug ][ $translation['language'] ] ) ) {
			return true;
		}

		// If no update timestamp, can't compare - assume needs update.
		if ( empty( $translation['updated'] ) ) {
			return true;
		}

		// Compare timestamps.
		$local_data = $installed[ $this->slug ][ $translation['language'] ];

		if ( empty( $local_data['PO-Revision-Date'] ) ) {
			return true;
		}

		try {
			$local_date  = new \DateTime( $local_data['PO-Revision-Date'] );
			$remote_date = new \DateTime( $translation['updated'] );

			return $remote_date > $local_date;
		} catch ( \Exception $e ) {
			// If dates can't be parsed, assume update is needed.
			return true;
		}
	}

	/**
	 * Gets installed translations for the project type.
	 *
	 * Results are cached for performance.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array<string, mixed>> Installed translations.
	 */
	private function get_installed_translations(): array {
		$type_plural = $this->get_type_plural();

		if ( null === self::$installed_translations_cache ) {
			self::$installed_translations_cache = [];
		}

		if ( ! isset( self::$installed_translations_cache[ $type_plural ] ) ) {
			self::$installed_translations_cache[ $type_plural ] = wp_get_installed_translations( $type_plural );
		}

		return self::$installed_translations_cache[ $type_plural ];
	}

	/**
	 * Gets available languages for the site.
	 *
	 * Results are cached for performance.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, string> Available language codes.
	 */
	private function get_available_languages(): array {
		if ( null === self::$available_languages_cache ) {
			self::$available_languages_cache = get_available_languages();
		}

		return self::$available_languages_cache;
	}

	/**
	 * Clears the static caches.
	 *
	 * Useful after installing new translations.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function clear_caches(): void {
		self::$installed_translations_cache = null;
		self::$available_languages_cache    = null;
	}

	/**
	 * Creates a unique identifier for this project.
	 *
	 * @since 2.0.0
	 *
	 * @return string Unique project identifier.
	 */
	public function get_identifier(): string {
		return $this->type . '_' . $this->slug;
	}
}

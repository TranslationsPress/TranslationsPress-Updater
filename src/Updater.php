<?php
/**
 * Main TranslationsPress Updater class.
 *
 * Orchestrates translation updates from TranslationsPress CDN.
 *
 * @package TranslationsPress\Updater
 * @since   2.0.0
 */

declare(strict_types=1);

namespace TranslationsPress;

/**
 * Updater class - Main entry point for TranslationsPress integration.
 *
 * This class uses a registry pattern to manage multiple projects efficiently.
 * It's designed to work seamlessly whether you have one plugin or a galaxy of add-ons.
 *
 * @since 2.0.0
 */
class Updater {

	/**
	 * Library version.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public const VERSION = '2.0.0';

	/**
	 * Singleton instance.
	 *
	 * @since 2.0.0
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Registered projects.
	 *
	 * @since 2.0.0
	 * @var array<string, Project>
	 */
	private array $projects = [];

	/**
	 * Registered API instances (for sharing between projects).
	 *
	 * @since 2.0.0
	 * @var array<string, API>
	 */
	private array $apis = [];

	/**
	 * WordPress.org override handlers.
	 *
	 * @since 2.0.0
	 * @var array<string, WordPressOrgOverride>
	 */
	private array $overrides = [];

	/**
	 * Whether WordPress hooks have been registered.
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	private bool $hooks_registered = false;

	/**
	 * Logger callback.
	 *
	 * @since 2.0.0
	 * @var callable|null
	 */
	private $logger;

	/**
	 * Gets the singleton instance.
	 *
	 * @since 2.0.0
	 *
	 * @return self Singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor for singleton pattern.
	 *
	 * @since 2.0.0
	 */
	private function __construct() {
		// Register hooks on init.
		$this->register_hooks();
	}

	/**
	 * Prevents cloning of the singleton.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Prevents unserialization of the singleton.
	 *
	 * @since 2.0.0
	 *
	 * @throws \RuntimeException Always.
	 */
	public function __wakeup() {
		throw new \RuntimeException( 'Cannot unserialize singleton.' );
	}

	/**
	 * Registers a project for translation updates.
	 *
	 * Simple usage:
	 *     $updater->register( 'plugin', 'my-plugin', 'https://t15s.com/api/my-plugin/packages.json' );
	 *
	 * With WordPress.org override:
	 *     $updater->register( 'plugin', 'my-plugin', 'https://t15s.com/api/my-plugin/packages.json', [
	 *         'override_wporg' => true,
	 *         'wporg_fallback' => true,
	 *     ] );
	 *
	 * @since 2.0.0
	 *
	 * @param string               $type    Project type: 'plugin' or 'theme'.
	 * @param string               $slug    Project directory slug.
	 * @param string               $api_url TranslationsPress API URL.
	 * @param array<string, mixed> $options {
	 *     Optional. Additional options.
	 *
	 *     @type bool $is_centralized  Whether the API serves multiple projects. Default false.
	 *     @type bool $override_wporg  Whether to override WordPress.org translations. Default false.
	 *     @type bool $wporg_fallback  Whether to fallback to wp.org if T15S fails. Default true.
	 *                                 Only applies if override_wporg is true.
	 *     @type int  $cache_expiration Cache expiration in seconds. Default 12 hours.
	 *     @type int  $timeout          API request timeout in seconds. Default 3.
	 * }
	 * @return Project The registered project instance.
	 */
	public function register(
		string $type,
		string $slug,
		string $api_url,
		array $options = []
	): Project {
		$options = wp_parse_args(
			$options,
			[
				'is_centralized'   => false,
				'override_wporg'   => false,
				'wporg_fallback'   => true,
				'cache_expiration' => Cache::DEFAULT_EXPIRATION,
				'timeout'          => API::DEFAULT_TIMEOUT,
			]
		);

		// Get or create API instance.
		$api = $this->get_or_create_api(
			$api_url,
			(bool) $options['is_centralized'],
			(int) $options['cache_expiration'],
			(int) $options['timeout']
		);

		// Create project.
		$project = new Project(
			$type,
			$slug,
			$api,
			(bool) $options['override_wporg'],
			(bool) $options['wporg_fallback']
		);

		// Store project.
		$this->projects[ $project->get_identifier() ] = $project;

		// Setup WordPress.org override if requested.
		if ( $options['override_wporg'] ) {
			$this->setup_wporg_override( $project, $options['wporg_fallback'] );
		}

		$this->log(
			sprintf(
				'Registered %s "%s" with API: %s (centralized: %s, override_wporg: %s)',
				$type,
				$slug,
				$api_url,
				$options['is_centralized'] ? 'yes' : 'no',
				$options['override_wporg'] ? 'yes' : 'no'
			)
		);

		return $project;
	}

	/**
	 * Registers multiple add-ons using a centralized API.
	 *
	 * Perfect for plugin ecosystems like GravityForms with multiple add-ons.
	 *
	 * Usage:
	 *     $updater->register_addons(
	 *         'https://packages.t15s.com/mycompany/packages.json',
	 *         [
	 *             'my-main-plugin',
	 *             'my-addon-one',
	 *             'my-addon-two',
	 *         ],
	 *         [
	 *             'override_wporg' => true,
	 *         ]
	 *     );
	 *
	 * @since 2.0.0
	 *
	 * @param string               $api_url Centralized API URL.
	 * @param array<int, string>   $slugs   Array of plugin slugs.
	 * @param array<string, mixed> $options Options passed to each registration.
	 * @return array<string, Project> Registered project instances.
	 */
	public function register_addons( string $api_url, array $slugs, array $options = [] ): array {
		$options['is_centralized'] = true;
		$projects                  = [];

		foreach ( $slugs as $slug ) {
			$projects[ $slug ] = $this->register( 'plugin', $slug, $api_url, $options );
		}

		return $projects;
	}

	/**
	 * Gets or creates an API instance for the given URL.
	 *
	 * @since 2.0.0
	 *
	 * @param string $api_url          API URL.
	 * @param bool   $is_centralized   Whether this is a centralized API.
	 * @param int    $cache_expiration Cache expiration in seconds.
	 * @param int    $timeout          Request timeout in seconds.
	 * @return API API instance.
	 */
	private function get_or_create_api(
		string $api_url,
		bool $is_centralized,
		int $cache_expiration,
		int $timeout
	): API {
		$api_key = md5( $api_url );

		if ( ! isset( $this->apis[ $api_key ] ) ) {
			$cache = new Cache( $cache_expiration, $this->logger );
			$api   = new API( $api_url, $is_centralized, $cache, $timeout, $this->logger );

			$this->apis[ $api_key ] = $api;
		}

		return $this->apis[ $api_key ];
	}

	/**
	 * Sets up WordPress.org override for a project.
	 *
	 * @since 2.0.0
	 *
	 * @param Project $project  Project instance.
	 * @param bool    $fallback Whether to enable fallback mode.
	 * @return void
	 */
	private function setup_wporg_override( Project $project, bool $fallback ): void {
		$mode = $fallback ? WordPressOrgOverride::MODE_FALLBACK : WordPressOrgOverride::MODE_REPLACE;

		$override = new WordPressOrgOverride( $project, $mode, $this->logger );
		$override->enable();

		$this->overrides[ $project->get_identifier() ] = $override;
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		if ( $this->hooks_registered ) {
			return;
		}

		// Register cache cleanup handlers.
		add_action( 'init', [ $this, 'register_cache_cleanup_hooks' ], 9999 );

		// Filter translation transients.
		add_filter( 'site_transient_update_plugins', [ $this, 'filter_plugin_translations' ] );
		add_filter( 'site_transient_update_themes', [ $this, 'filter_theme_translations' ] );

		// Handle translation API requests for non-override projects.
		add_filter( 'translations_api', [ $this, 'handle_translations_api' ], 10, 3 );

		$this->hooks_registered = true;
	}

	/**
	 * Registers cache cleanup hooks.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function register_cache_cleanup_hooks(): void {
		add_action( 'set_site_transient_update_plugins', [ $this, 'maybe_clean_plugin_caches' ] );
		add_action( 'delete_site_transient_update_plugins', [ $this, 'maybe_clean_plugin_caches' ] );
		add_action( 'set_site_transient_update_themes', [ $this, 'maybe_clean_theme_caches' ] );
		add_action( 'delete_site_transient_update_themes', [ $this, 'maybe_clean_theme_caches' ] );
	}

	/**
	 * Filters plugin translations transient.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $value Transient value.
	 * @return mixed Filtered transient value.
	 */
	public function filter_plugin_translations( $value ) {
		return $this->filter_translations( $value, 'plugin' );
	}

	/**
	 * Filters theme translations transient.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $value Transient value.
	 * @return mixed Filtered transient value.
	 */
	public function filter_theme_translations( $value ) {
		return $this->filter_translations( $value, 'theme' );
	}

	/**
	 * Filters translations transient for a specific type.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed  $value Transient value.
	 * @param string $type  Project type.
	 * @return mixed Filtered transient value.
	 */
	private function filter_translations( $value, string $type ) {
		if ( ! $value ) {
			$value = new \stdClass();
		}

		if ( ! isset( $value->translations ) ) {
			$value->translations = [];
		}

		foreach ( $this->projects as $project ) {
			if ( $project->get_type() !== $type ) {
				continue;
			}

			$updates = $project->get_translation_updates();

			foreach ( $updates as $update ) {
				$value->translations[] = $update;
			}
		}

		return $value;
	}

	/**
	 * Handles translations API requests for projects without wp.org override.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed  $result         The result object. Default false.
	 * @param string $requested_type The type of translations being requested.
	 * @param mixed  $args           Translation API arguments.
	 * @return mixed Filtered result.
	 */
	public function handle_translations_api( $result, string $requested_type, $args ) {
		$args = (array) $args;

		if ( ! isset( $args['slug'] ) ) {
			return $result;
		}

		$slug = $args['slug'];
		$type = rtrim( $requested_type, 's' ); // Convert 'plugins' to 'plugin'.

		$identifier = $type . '_' . $slug;

		// Skip if project not registered or has wp.org override (handled separately).
		if ( ! isset( $this->projects[ $identifier ] ) ) {
			return $result;
		}

		$project = $this->projects[ $identifier ];

		// Skip if this project has wp.org override enabled (handled by WordPressOrgOverride).
		if ( $project->has_wporg_override() ) {
			return $result;
		}

		$this->log( sprintf( 'Handling translations_api for %s', $identifier ) );

		return $project->get_translations();
	}

	/**
	 * Cleans plugin translation caches if needed.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function maybe_clean_plugin_caches(): void {
		$this->maybe_clean_caches( 'plugin' );
	}

	/**
	 * Cleans theme translation caches if needed.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function maybe_clean_theme_caches(): void {
		$this->maybe_clean_caches( 'theme' );
	}

	/**
	 * Cleans translation caches for a specific type if needed.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type Project type.
	 * @return void
	 */
	private function maybe_clean_caches( string $type ): void {
		$cleaned_apis = [];

		foreach ( $this->projects as $project ) {
			if ( $project->get_type() !== $type ) {
				continue;
			}

			$api     = $project->get_api();
			$api_key = $api->get_cache_key();

			// Only clean each API's cache once.
			if ( in_array( $api_key, $cleaned_apis, true ) ) {
				continue;
			}

			$api->get_cache()->maybe_clean( $api_key );
			$cleaned_apis[] = $api_key;
		}

		// Clear static caches.
		Project::clear_caches();
	}

	/**
	 * Gets a registered project.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type Project type.
	 * @param string $slug Project slug.
	 * @return Project|null Project instance or null if not found.
	 */
	public function get_project( string $type, string $slug ): ?Project {
		$identifier = $type . '_' . $slug;

		return $this->projects[ $identifier ] ?? null;
	}

	/**
	 * Gets all registered projects.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, Project> Registered projects.
	 */
	public function get_projects(): array {
		return $this->projects;
	}

	/**
	 * Checks if a project is registered.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type Project type.
	 * @param string $slug Project slug.
	 * @return bool True if registered.
	 */
	public function is_registered( string $type, string $slug ): bool {
		return null !== $this->get_project( $type, $slug );
	}

	/**
	 * Unregisters a project.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type Project type.
	 * @param string $slug Project slug.
	 * @return bool True if project was unregistered.
	 */
	public function unregister( string $type, string $slug ): bool {
		$identifier = $type . '_' . $slug;

		if ( ! isset( $this->projects[ $identifier ] ) ) {
			return false;
		}

		// Disable wp.org override if enabled.
		if ( isset( $this->overrides[ $identifier ] ) ) {
			$this->overrides[ $identifier ]->disable();
			unset( $this->overrides[ $identifier ] );
		}

		unset( $this->projects[ $identifier ] );

		$this->log( sprintf( 'Unregistered %s "%s"', $type, $slug ) );

		return true;
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

		// Propagate to existing APIs.
		foreach ( $this->apis as $api ) {
			$api->set_logger( $logger );
		}

		// Propagate to existing overrides.
		foreach ( $this->overrides as $override ) {
			$override->set_logger( $logger );
		}
	}

	/**
	 * Logs a message if logger is configured.
	 *
	 * @since 2.0.0
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	private function log( string $message ): void {
		if ( null !== $this->logger && is_callable( $this->logger ) ) {
			call_user_func( $this->logger, '[TranslationsPress Updater] ' . $message );
		}
	}

	/**
	 * Refreshes all translation caches.
	 *
	 * @since 2.0.0
	 *
	 * @return int Number of APIs refreshed.
	 */
	public function refresh_all(): int {
		$count = 0;

		foreach ( $this->apis as $api ) {
			if ( $api->refresh() ) {
				++$count;
			}
		}

		Project::clear_caches();

		return $count;
	}

	/**
	 * Resets the singleton instance.
	 *
	 * Primarily for testing purposes.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function reset(): void {
		if ( null !== self::$instance ) {
			// Disable all overrides.
			foreach ( self::$instance->overrides as $override ) {
				$override->disable();
			}

			self::$instance = null;
		}
	}
}

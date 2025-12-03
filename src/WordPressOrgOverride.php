<?php
/**
 * WordPress.org translations override handler.
 *
 * Provides methods to replace or fallback WordPress.org translation API calls.
 *
 * @package TranslationsPress\Updater
 * @since   2.0.0
 */

declare(strict_types=1);

namespace TranslationsPress;

/**
 * WordPressOrgOverride class for managing WordPress.org translation overrides.
 *
 * @since 2.0.0
 */
class WordPressOrgOverride {

	/**
	 * Override mode: Complete replacement (no wp.org calls).
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public const MODE_REPLACE = 'replace';

	/**
	 * Override mode: Fallback (T15S first, then wp.org).
	 *
	 * @since 2.0.0
	 * @var string
	 */
	public const MODE_FALLBACK = 'fallback';

	/**
	 * Project instance.
	 *
	 * @since 2.0.0
	 * @var Project
	 */
	private Project $project;

	/**
	 * Override mode.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	private string $mode;

	/**
	 * Whether hooks are registered.
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
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param Project       $project Project instance.
	 * @param string        $mode    Override mode: 'replace' or 'fallback'.
	 * @param callable|null $logger  Optional logger callback.
	 */
	public function __construct( Project $project, string $mode = self::MODE_FALLBACK, ?callable $logger = null ) {
		$this->project = $project;
		$this->mode    = $this->validate_mode( $mode );
		$this->logger  = $logger;
	}

	/**
	 * Validates and returns a valid override mode.
	 *
	 * @since 2.0.0
	 *
	 * @param string $mode Provided mode.
	 * @return string Valid mode.
	 */
	private function validate_mode( string $mode ): string {
		if ( ! in_array( $mode, [ self::MODE_REPLACE, self::MODE_FALLBACK ], true ) ) {
			return self::MODE_FALLBACK;
		}

		return $mode;
	}

	/**
	 * Enables the WordPress.org override for this project.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function enable(): void {
		if ( $this->hooks_registered ) {
			return;
		}

		// Override translations API for this specific project.
		add_filter( 'translations_api', [ $this, 'filter_translations_api' ], 10, 3 );

		// Block wp.org translation updates for this project if in replace mode.
		if ( self::MODE_REPLACE === $this->mode ) {
			add_filter( 'pre_http_request', [ $this, 'block_wporg_request' ], 10, 3 );
		}

		$this->hooks_registered = true;

		$this->log(
			sprintf(
				'WordPress.org override enabled for %s "%s" (mode: %s)',
				$this->project->get_type(),
				$this->project->get_slug(),
				$this->mode
			)
		);
	}

	/**
	 * Disables the WordPress.org override for this project.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function disable(): void {
		if ( ! $this->hooks_registered ) {
			return;
		}

		remove_filter( 'translations_api', [ $this, 'filter_translations_api' ], 10 );
		remove_filter( 'pre_http_request', [ $this, 'block_wporg_request' ], 10 );

		$this->hooks_registered = false;

		$this->log(
			sprintf(
				'WordPress.org override disabled for %s "%s"',
				$this->project->get_type(),
				$this->project->get_slug()
			)
		);
	}

	/**
	 * Filters the translations API response.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed                $result         The result object. Default false.
	 * @param string               $requested_type The type of translations being requested.
	 * @param object|array<string> $args           Translation API arguments.
	 * @return mixed Filtered result or TranslationsPress translations.
	 */
	public function filter_translations_api( $result, string $requested_type, $args ) {
		$args = (array) $args;

		// Check if this request is for our project.
		if ( $this->project->get_type_plural() !== $requested_type ) {
			return $result;
		}

		if ( ! isset( $args['slug'] ) || $this->project->get_slug() !== $args['slug'] ) {
			return $result;
		}

		$this->log(
			sprintf(
				'Intercepting translations_api for %s "%s"',
				$this->project->get_type(),
				$this->project->get_slug()
			)
		);

		// Get TranslationsPress translations.
		$t15s_translations = $this->project->get_translations();

		// Check if T15S has actual translations.
		$has_translations = ! empty( $t15s_translations )
			&& isset( $t15s_translations['translations'] )
			&& ! empty( $t15s_translations['translations'] );

		if ( $has_translations ) {
			$this->log( 'Returning TranslationsPress translations' );
			return $t15s_translations;
		}

		// In fallback mode, return original result if T15S fails.
		if ( self::MODE_FALLBACK === $this->mode ) {
			$this->log( 'TranslationsPress returned empty, falling back to WordPress.org' );
			return $result;
		}

		// In replace mode, return empty array if T15S fails.
		$this->log( 'TranslationsPress returned empty, blocking WordPress.org (replace mode)' );
		return [ 'translations' => [] ];
	}

	/**
	 * Blocks HTTP requests to WordPress.org translations API for this project.
	 *
	 * @since 2.0.0
	 *
	 * @param false|array<string, mixed>|\WP_Error $preempt     Whether to preempt the request.
	 * @param array<string, mixed>                 $parsed_args HTTP request arguments.
	 * @param string                               $url         The request URL.
	 * @return false|array<string, mixed>|\WP_Error Filtered preempt value.
	 */
	public function block_wporg_request( $preempt, array $parsed_args, string $url ) {
		// Only block requests in replace mode.
		if ( self::MODE_REPLACE !== $this->mode ) {
			return $preempt;
		}

		// Check if this is a WordPress.org translations request for our project.
		if ( ! $this->is_wporg_translations_request( $url ) ) {
			return $preempt;
		}

		$this->log(
			sprintf( 'Blocking WordPress.org request for %s: %s', $this->project->get_slug(), $url )
		);

		// Return empty translations response.
		return [
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'body'     => wp_json_encode( [ 'translations' => [] ] ),
		];
	}

	/**
	 * Checks if a URL is a WordPress.org translations request for this project.
	 *
	 * @since 2.0.0
	 *
	 * @param string $url Request URL.
	 * @return bool True if this is a wp.org translations request for this project.
	 */
	private function is_wporg_translations_request( string $url ): bool {
		// WordPress.org translations API URL pattern.
		if ( false === strpos( $url, 'api.wordpress.org/translations' ) ) {
			return false;
		}

		// Check if it's for our project type.
		$type_plural = $this->project->get_type_plural();
		if ( false === strpos( $url, '/' . $type_plural . '/' ) ) {
			return false;
		}

		// Check if it's for our project slug.
		// URL format: https://api.wordpress.org/translations/plugins/1.0/?slug=xxx.
		$slug = $this->project->get_slug();

		// Check query string.
		$query_string = wp_parse_url( $url, PHP_URL_QUERY );
		if ( $query_string ) {
			parse_str( $query_string, $query_args );
			if ( isset( $query_args['slug'] ) && $query_args['slug'] === $slug ) {
				return true;
			}
		}

		// Check path for slug.
		if ( false !== strpos( $url, 'slug=' . $slug ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Gets the current override mode.
	 *
	 * @since 2.0.0
	 *
	 * @return string Override mode.
	 */
	public function get_mode(): string {
		return $this->mode;
	}

	/**
	 * Sets the override mode.
	 *
	 * @since 2.0.0
	 *
	 * @param string $mode Override mode.
	 * @return void
	 */
	public function set_mode( string $mode ): void {
		$was_enabled = $this->hooks_registered;

		if ( $was_enabled ) {
			$this->disable();
		}

		$this->mode = $this->validate_mode( $mode );

		if ( $was_enabled ) {
			$this->enable();
		}
	}

	/**
	 * Checks if hooks are registered.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if hooks are registered.
	 */
	public function is_enabled(): bool {
		return $this->hooks_registered;
	}

	/**
	 * Gets the project instance.
	 *
	 * @since 2.0.0
	 *
	 * @return Project Project instance.
	 */
	public function get_project(): Project {
		return $this->project;
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
			call_user_func( $this->logger, '[TranslationsPress Override] ' . $message );
		}
	}
}

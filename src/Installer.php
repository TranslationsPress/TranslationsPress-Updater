<?php
/**
 * Translation package installer for TranslationsPress.
 *
 * Handles downloading and installing translation packages directly.
 *
 * @package TranslationsPress\Updater
 * @since   2.0.0
 */

declare(strict_types=1);

namespace TranslationsPress;

/**
 * Installer class for downloading and installing translations.
 *
 * @since 2.0.0
 */
class Installer {

	/**
	 * The project to install translations for.
	 *
	 * @since 2.0.0
	 * @var Project
	 */
	private Project $project;

	/**
	 * Logger callback.
	 *
	 * @since 2.0.0
	 * @var callable|null
	 */
	private $logger;

	/**
	 * Locales already installed during this request.
	 *
	 * @since 2.0.0
	 * @var array<string>
	 */
	private array $installed = [];

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param Project       $project Project to install translations for.
	 * @param callable|null $logger  Optional logger callback.
	 */
	public function __construct( Project $project, ?callable $logger = null ) {
		$this->project = $project;
		$this->logger  = $logger;
	}

	/**
	 * Sets the logger callback.
	 *
	 * @since 2.0.0
	 *
	 * @param callable|null $logger Logger callback.
	 * @return void
	 */
	public function set_logger( ?callable $logger ): void {
		$this->logger = $logger;
	}

	/**
	 * Logs a message using the logger callback.
	 *
	 * @since 2.0.0
	 *
	 * @param string $message Log message.
	 * @param string $level   Log level (debug, info, error).
	 * @return void
	 */
	private function log( string $message, string $level = 'debug' ): void {
		if ( null !== $this->logger ) {
			call_user_func( $this->logger, $message, $level );
		}
	}

	/**
	 * Installs translations for the current site locale.
	 *
	 * Downloads and installs the translation package for the current WordPress
	 * locale if a translation is available and not already installed/up-to-date.
	 *
	 * @since 2.0.0
	 *
	 * @param string $locale Optional. Specific locale to install. Default current locale.
	 * @return bool True if installation succeeded, false otherwise.
	 */
	public function install( string $locale = '' ): bool {
		if ( '' === $locale ) {
			$locale = determine_locale();
		}

		// Skip en_US as it doesn't need translations.
		if ( 'en_US' === $locale ) {
			$this->log( 'Skipping en_US locale (no translation needed)' );
			return true;
		}

		// Check if already installed this request.
		if ( in_array( $locale, $this->installed, true ) ) {
			$this->log( sprintf( 'Locale %s already installed this request', $locale ) );
			return true;
		}

		$translations = $this->project->get_translations();

		if ( empty( $translations['translations'] ) ) {
			$this->log( sprintf( 'No translations available for %s', $this->project->get_slug() ), 'error' );
			return false;
		}

		// Find translation for requested locale.
		$translation = $this->find_translation( $translations['translations'], $locale );

		if ( null === $translation ) {
			$this->log( sprintf( 'No translation found for locale %s', $locale ) );
			return false;
		}

		// Check if translation needs updating.
		if ( ! $this->needs_update( $translation ) ) {
			$this->log( sprintf( 'Translation for %s is already up to date', $locale ) );
			return true;
		}

		return $this->download_and_install( $translation );
	}

	/**
	 * Installs translations for all available site locales.
	 *
	 * @since 2.0.0
	 *
	 * @return int Number of translations installed.
	 */
	public function install_all(): int {
		$installed_count = 0;
		$locales         = $this->get_site_locales();

		foreach ( $locales as $locale ) {
			if ( $this->install( $locale ) ) {
				++$installed_count;
			}
		}

		return $installed_count;
	}

	/**
	 * Finds a translation for a specific locale.
	 *
	 * @since 2.0.0
	 *
	 * @param array<int, array<string, mixed>> $translations Available translations.
	 * @param string                           $locale       Locale to find.
	 * @return array<string, mixed>|null Translation data or null if not found.
	 */
	private function find_translation( array $translations, string $locale ): ?array {
		foreach ( $translations as $translation ) {
			if ( isset( $translation['language'] ) && $translation['language'] === $locale ) {
				return $translation;
			}
		}

		return null;
	}

	/**
	 * Checks if a translation needs to be updated.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $translation Translation data.
	 * @return bool True if update is needed.
	 */
	private function needs_update( array $translation ): bool {
		$installed = $this->get_installed_translation_data( $translation['language'] );

		// Not installed yet.
		if ( null === $installed ) {
			return true;
		}

		// No update timestamp available.
		if ( empty( $translation['updated'] ) || empty( $installed['PO-Revision-Date'] ) ) {
			return true;
		}

		try {
			$local_date  = new \DateTime( $installed['PO-Revision-Date'] );
			$remote_date = new \DateTime( $translation['updated'] );

			return $remote_date > $local_date;
		} catch ( \Exception $e ) {
			return true;
		}
	}

	/**
	 * Gets installed translation data for a locale.
	 *
	 * @since 2.0.0
	 *
	 * @param string $locale Locale to check.
	 * @return array<string, string>|null PO file header data or null.
	 */
	private function get_installed_translation_data( string $locale ): ?array {
		$po_file = $this->get_translation_path( $locale, 'po' );

		if ( ! file_exists( $po_file ) ) {
			return null;
		}

		return wp_get_pomo_file_data( $po_file );
	}

	/**
	 * Gets the path to a translation file.
	 *
	 * @since 2.0.0
	 *
	 * @param string $locale    Locale.
	 * @param string $extension File extension (mo, po, zip).
	 * @return string Full file path.
	 */
	private function get_translation_path( string $locale, string $extension ): string {
		$dir = $this->get_translations_directory();
		return sprintf( '%s%s-%s.%s', $dir, $this->project->get_slug(), $locale, $extension );
	}

	/**
	 * Gets the translations directory for this project type.
	 *
	 * @since 2.0.0
	 *
	 * @return string Directory path with trailing slash.
	 */
	private function get_translations_directory(): string {
		return WP_LANG_DIR . '/' . $this->project->get_type_plural() . '/';
	}

	/**
	 * Downloads and installs a translation package.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $translation Translation data with 'package' URL.
	 * @return bool True on success, false on failure.
	 */
	private function download_and_install( array $translation ): bool {
		global $wp_filesystem;

		if ( empty( $translation['package'] ) ) {
			$this->log( 'Translation package URL is empty', 'error' );
			return false;
		}

		// Check user capabilities.
		if ( ! current_user_can( 'install_languages' ) ) {
			$this->log( 'User does not have install_languages capability', 'error' );
			return false;
		}

		// Initialize filesystem.
		if ( ! $this->init_filesystem() ) {
			return false;
		}

		// Ensure language directory exists.
		$lang_dir = $this->get_translations_directory();
		if ( ! $wp_filesystem->is_dir( $lang_dir ) ) {
			$wp_filesystem->mkdir( $lang_dir, FS_CHMOD_DIR );
		}

		$locale = $translation['language'];

		$this->log( sprintf( 'Downloading translation package: %s', $translation['package'] ) );

		// Download the package.
		$temp_file = download_url( $translation['package'] );

		if ( is_wp_error( $temp_file ) ) {
			$this->log(
				sprintf(
					'Error downloading package: %s - %s',
					$temp_file->get_error_code(),
					$temp_file->get_error_message()
				),
				'error'
			);
			return false;
		}

		// Move to language directory.
		$zip_path    = $this->get_translation_path( $locale, 'zip' );
		$copy_result = $wp_filesystem->copy( $temp_file, $zip_path, true, FS_CHMOD_FILE );

		// Clean up temp file.
		$wp_filesystem->delete( $temp_file );

		if ( ! $copy_result ) {
			$this->log( sprintf( 'Unable to move package to: %s', $lang_dir ), 'error' );
			return false;
		}

		// Extract the package.
		$result = unzip_file( $zip_path, $lang_dir );

		// Clean up zip file.
		wp_delete_file( $zip_path );

		if ( is_wp_error( $result ) ) {
			$this->log(
				sprintf(
					'Error extracting package: %s - %s',
					$result->get_error_code(),
					$result->get_error_message()
				),
				'error'
			);
			return false;
		}

		$this->installed[] = $locale;

		// Clear merged JSON cache so newly extracted merged files are picked up.
		MergedJsonLoader::get_instance()->clear_cache();

		$this->log(
			sprintf(
				'Successfully installed %s translation for %s',
				$locale,
				$this->project->get_slug()
			),
			'info'
		);

		return true;
	}

	/**
	 * Initializes the WordPress filesystem.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if filesystem is ready, false otherwise.
	 */
	private function init_filesystem(): bool {
		global $wp_filesystem;

		if ( $wp_filesystem ) {
			return true;
		}

		require_once ABSPATH . '/wp-admin/includes/admin.php';

		// Capture any output from filesystem credentials request.
		ob_start();
		$credentials_available = request_filesystem_credentials( self_admin_url() );
		ob_end_clean();

		if ( ! $credentials_available ) {
			$this->log( 'Filesystem credentials required', 'error' );
			return false;
		}

		if ( ! \WP_Filesystem() ) {
			$this->log( 'Unable to initialize WP_Filesystem', 'error' );
			return false;
		}

		return true;
	}

	/**
	 * Gets all locales used by the site.
	 *
	 * Includes the main site locale and any user-specific locales.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string> Array of locale codes.
	 */
	private function get_site_locales(): array {
		$locales = get_available_languages();

		// Add current site locale.
		$site_locale = determine_locale();
		if ( ! in_array( $site_locale, $locales, true ) ) {
			$locales[] = $site_locale;
		}

		// Remove en_US as it doesn't need translations.
		return array_filter(
			$locales,
			static function ( string $locale ): bool {
				return 'en_US' !== $locale;
			}
		);
	}

	/**
	 * Gets the list of locales installed during this request.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string> Installed locale codes.
	 */
	public function get_installed_locales(): array {
		return $this->installed;
	}

	/**
	 * Clears the list of installed locales.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function clear_installed(): void {
		$this->installed = [];
	}
}

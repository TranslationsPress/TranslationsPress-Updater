<?php
/**
 * WordPress function stubs for unit testing.
 *
 * @package TranslationsPress\Updater\Tests
 */

// phpcs:disable WordPress.NamingConventions, Squiz.Commenting, Generic.Commenting

// WordPress time constants.
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS );
}
if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS );
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * Merge user defined arguments into defaults array.
	 *
	 * @param array|object $args     Value to merge with defaults.
	 * @param array        $defaults Array that serves as the defaults.
	 * @return array Merged user defined values with defaults.
	 */
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$parsed_args = get_object_vars( $args );
		} else {
			$parsed_args = $args;
		}

		if ( is_array( $defaults ) && ! empty( $defaults ) ) {
			return array_merge( $defaults, $parsed_args );
		}

		return $parsed_args;
	}
}

if ( ! function_exists( 'get_site_transient' ) ) {
	/**
	 * Retrieves the value of a site transient.
	 *
	 * @param string $transient Transient name.
	 * @return mixed Transient value or false.
	 */
	function get_site_transient( $transient ) {
		global $wp_test_transients;
		return isset( $wp_test_transients[ $transient ] ) ? $wp_test_transients[ $transient ] : false;
	}
}

if ( ! function_exists( 'set_site_transient' ) ) {
	/**
	 * Sets/updates the value of a site transient.
	 *
	 * @param string $transient  Transient name.
	 * @param mixed  $value      Transient value.
	 * @param int    $expiration Time until expiration in seconds.
	 * @return bool True on success.
	 */
	function set_site_transient( $transient, $value, $expiration = 0 ) {
		global $wp_test_transients;
		if ( ! is_array( $wp_test_transients ) ) {
			$wp_test_transients = array();
		}
		$wp_test_transients[ $transient ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_site_transient' ) ) {
	/**
	 * Deletes a site transient.
	 *
	 * @param string $transient Transient name.
	 * @return bool True on success.
	 */
	function delete_site_transient( $transient ) {
		global $wp_test_transients;
		if ( isset( $wp_test_transients[ $transient ] ) ) {
			unset( $wp_test_transients[ $transient ] );
			return true;
		}
		return false;
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	/**
	 * Performs an HTTP request using the GET method.
	 *
	 * @param string $url  The request URL.
	 * @param array  $args Request arguments.
	 * @return array|WP_Error Response or WP_Error on failure.
	 */
	function wp_remote_get( $url, $args = array() ) {
		global $wp_test_http_responses;

		if ( isset( $wp_test_http_responses[ $url ] ) ) {
			return $wp_test_http_responses[ $url ];
		}

		return new WP_Error( 'http_request_failed', 'Mock HTTP failure' );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * Retrieve only the response code from the raw response.
	 *
	 * @param array|WP_Error $response HTTP response.
	 * @return int|string Response code or empty string.
	 */
	function wp_remote_retrieve_response_code( $response ) {
		if ( is_wp_error( $response ) || ! isset( $response['response']['code'] ) ) {
			return '';
		}
		return $response['response']['code'];
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * Retrieve only the body from the raw response.
	 *
	 * @param array|WP_Error $response HTTP response.
	 * @return string Response body.
	 */
	function wp_remote_retrieve_body( $response ) {
		if ( is_wp_error( $response ) || ! isset( $response['body'] ) ) {
			return '';
		}
		return $response['body'];
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Checks whether the given variable is a WordPress Error.
	 *
	 * @param mixed $thing Check if unknown variable is a WP_Error object.
	 * @return bool True if WP_Error.
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * WordPress Error class.
	 */
	class WP_Error {
		protected $errors = array();
		protected $error_data = array();

		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( ! empty( $code ) ) {
				$this->errors[ $code ][] = $message;
				if ( ! empty( $data ) ) {
					$this->error_data[ $code ] = $data;
				}
			}
		}

		public function get_error_code() {
			$codes = array_keys( $this->errors );
			return ! empty( $codes ) ? $codes[0] : '';
		}

		public function get_error_message( $code = '' ) {
			if ( empty( $code ) ) {
				$code = $this->get_error_code();
			}
			if ( isset( $this->errors[ $code ] ) && ! empty( $this->errors[ $code ][0] ) ) {
				return $this->errors[ $code ][0];
			}
			return '';
		}
	}
}

if ( ! function_exists( 'get_available_languages' ) ) {
	/**
	 * Get all available languages based on the presence of *.mo files.
	 *
	 * @param string $dir Directory to search for language files.
	 * @return array An array of language codes.
	 */
	function get_available_languages( $dir = null ) {
		global $wp_test_available_languages;
		return ! empty( $wp_test_available_languages ) ? $wp_test_available_languages : array( 'en_US' );
	}
}

if ( ! function_exists( 'get_locale' ) ) {
	/**
	 * Retrieves the current locale.
	 *
	 * @return string The locale of the site.
	 */
	function get_locale() {
		global $wp_test_locale;
		return ! empty( $wp_test_locale ) ? $wp_test_locale : 'en_US';
	}
}

if ( ! function_exists( 'wp_get_installed_translations' ) ) {
	/**
	 * Get installed translations.
	 *
	 * @param string $type Type of translations to get.
	 * @return array Translations.
	 */
	function wp_get_installed_translations( $type ) {
		global $wp_test_installed_translations;
		return isset( $wp_test_installed_translations[ $type ] ) ? $wp_test_installed_translations[ $type ] : array();
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Encode a variable into JSON.
	 *
	 * @param mixed $data    Variable to encode.
	 * @param int   $options Options to be passed to json_encode().
	 * @param int   $depth   Maximum depth to walk through.
	 * @return string|false JSON encoded string or false on failure.
	 */
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Adds a callback function to a filter hook.
	 *
	 * @param string   $hook_name     The name of the filter to add the callback to.
	 * @param callable $callback      The callback to be run when the filter is applied.
	 * @param int      $priority      Priority of the function.
	 * @param int      $accepted_args Number of arguments to accept.
	 * @return bool Always returns true.
	 */
	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		global $wp_test_filters;
		if ( ! is_array( $wp_test_filters ) ) {
			$wp_test_filters = array();
		}
		if ( ! isset( $wp_test_filters[ $hook_name ] ) ) {
			$wp_test_filters[ $hook_name ] = array();
		}
		$wp_test_filters[ $hook_name ][] = array(
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
		return true;
	}
}

if ( ! function_exists( 'remove_filter' ) ) {
	/**
	 * Removes a callback function from a filter hook.
	 *
	 * @param string   $hook_name The filter hook to which the function to be removed is hooked.
	 * @param callable $callback  The callback to be removed from running when the filter is applied.
	 * @param int      $priority  The exact priority used when adding the original filter callback.
	 * @return bool Whether the function was registered as a filter before it was removed.
	 */
	function remove_filter( $hook_name, $callback, $priority = 10 ) {
		global $wp_test_filters;
		// Simplified implementation for testing.
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Adds a callback function to an action hook.
	 *
	 * @param string   $hook_name The name of the action to add the callback to.
	 * @param callable $callback  The callback to be run when the action is called.
	 * @param int      $priority  Priority of the function.
	 * @param int      $accepted_args Number of arguments.
	 * @return bool Always returns true.
	 */
	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_filter( $hook_name, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'remove_action' ) ) {
	/**
	 * Removes a callback function from an action hook.
	 *
	 * @param string   $hook_name The action hook to which the function to be removed is hooked.
	 * @param callable $callback  The callback to be removed.
	 * @param int      $priority  The priority of the function.
	 * @return bool Whether the function is removed.
	 */
	function remove_action( $hook_name, $callback, $priority = 10 ) {
		return remove_filter( $hook_name, $callback, $priority );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Sanitizes a string key.
	 *
	 * @param string $key String key.
	 * @return string Sanitized key.
	 */
	function sanitize_key( $key ) {
		$key = strtolower( $key );
		$key = preg_replace( '/[^a-z0-9_\-]/', '', $key );
		return $key;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * A wrapper for PHP's parse_url() function.
	 *
	 * @param string $url       The URL to parse.
	 * @param int    $component The specific component to retrieve.
	 * @return mixed Parsed result or false.
	 */
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'determine_locale' ) ) {
	/**
	 * Determines the current locale.
	 *
	 * @return string Current locale.
	 */
	function determine_locale() {
		return get_locale();
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Checks if current user has capability.
	 *
	 * @param string $capability Capability name.
	 * @return bool True if user has capability.
	 */
	function current_user_can( $capability ) {
		return true; // For testing, assume user has capability.
	}
}

if ( ! function_exists( 'download_url' ) ) {
	/**
	 * Downloads a URL to a local temporary file.
	 *
	 * @param string $url URL to download.
	 * @return string|\WP_Error Filename or WP_Error on failure.
	 */
	function download_url( $url ) {
		global $wp_test_download_results;

		if ( isset( $wp_test_download_results[ $url ] ) ) {
			return $wp_test_download_results[ $url ];
		}

		return new WP_Error( 'download_failed', 'Mock download failure' );
	}
}

if ( ! function_exists( 'unzip_file' ) ) {
	/**
	 * Unzips a file to a directory.
	 *
	 * @param string $file File path.
	 * @param string $to   Destination directory.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	function unzip_file( $file, $to ) {
		return true; // For testing.
	}
}

if ( ! function_exists( 'self_admin_url' ) ) {
	/**
	 * Returns admin URL for the current site.
	 *
	 * @param string $path Path to append.
	 * @return string Admin URL.
	 */
	function self_admin_url( $path = '' ) {
		return 'http://example.com/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'request_filesystem_credentials' ) ) {
	/**
	 * Requests filesystem credentials from the user.
	 *
	 * @param string $form_post URL to post to.
	 * @return bool|array Credentials or false.
	 */
	function request_filesystem_credentials( $form_post ) {
		return true; // For testing.
	}
}

if ( ! function_exists( 'WP_Filesystem' ) ) {
	/**
	 * Initializes and connects the WordPress Filesystem.
	 *
	 * @param array $args Connection args.
	 * @return bool True on success.
	 */
	function WP_Filesystem( $args = array() ) {
		return true; // For testing.
	}
}

if ( ! function_exists( 'wp_get_pomo_file_data' ) ) {
	/**
	 * Extracts headers from a PO file.
	 *
	 * @param string $po_file Path to PO file.
	 * @return array Headers.
	 */
	function wp_get_pomo_file_data( $po_file ) {
		return array(
			'PO-Revision-Date' => '2024-01-01 12:00:00',
		);
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	/**
	 * Deletes a file.
	 *
	 * @param string $file Path to file.
	 * @return void
	 */
	function wp_delete_file( $file ) {
		if ( file_exists( $file ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink
			@unlink( $file );
		}
	}
}

if ( ! defined( 'FS_CHMOD_DIR' ) ) {
	define( 'FS_CHMOD_DIR', 0755 );
}

if ( ! defined( 'FS_CHMOD_FILE' ) ) {
	define( 'FS_CHMOD_FILE', 0644 );
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', '/var/www/html/wp-content' );
}

if ( ! defined( 'WP_LANG_DIR' ) ) {
	define( 'WP_LANG_DIR', WP_CONTENT_DIR . '/languages' );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

// Set up a mock global $wp_filesystem to prevent file loading.
global $wp_filesystem;
$wp_filesystem = new class {
	public function mkdir( $path, $chmod = false, $chown = false, $chgrp = false ) {
		return true;
	}
	public function is_dir( $path ) {
		return true;
	}
	public function exists( $path ) {
		return false;
	}
};

// phpcs:enable

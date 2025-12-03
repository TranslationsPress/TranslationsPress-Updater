<?php
/**
 * PHPUnit bootstrap file for TranslationsPress Updater tests.
 *
 * @package TranslationsPress\Updater\Tests
 */

// Load Composer autoload.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define ABSPATH for standalone tests.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}

// Load WordPress test functions stubs.
require_once __DIR__ . '/stubs/wordpress-stubs.php';

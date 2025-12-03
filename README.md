# TranslationsPress Updater

[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://www.php.net/)
[![WordPress 6.0+](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![WPCS](https://img.shields.io/badge/WPCS-3.0-green.svg)](https://github.com/WordPress/WordPress-Coding-Standards)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

A modern, PSR-4 compliant library to enable WordPress translation updates from [TranslationsPress](https://translationspress.com/) CDN for plugins and themes hosted outside WordPress.org.

## Features

- 🚀 **Modern PHP**: PHP 7.4+ with strict types and typed properties
- 📦 **Composer Ready**: PSR-4 autoloading with proper namespacing
- 🔧 **Standalone Version**: Single-file version for non-Composer projects
- 🌐 **Centralized API**: Support for plugin ecosystems with shared translation servers
- 🔄 **WordPress.org Override**: Replace or fallback to wp.org translations
- ⚡ **Intelligent Caching**: Smart transient-based caching with cleanup protection
- 📝 **WPCS 3.0 Compliant**: 100% WordPress Coding Standards compliance
- 🧪 **Fully Tested**: PHPUnit test suite included

## Requirements

- PHP 7.4 or higher
- WordPress 6.0 or higher

## ⚠️ Important: Avoiding Conflicts

If you're distributing a plugin that uses this library, **you must prefix the namespace** to avoid conflicts with other plugins using the same library.

**Use [Mozart](https://github.com/developer-developer/mozart)** to automatically prefix:

```bash
composer require --dev coenjacobs/mozart
```

See [docs/INTEGRATION-SANS-CONFLIT.md](docs/INTEGRATION-SANS-CONFLIT.md) for complete integration guide.

## Installation

### Via Composer (Recommended)

```bash
composer require translationspress/updater
```

### Standalone (Without Composer)

Copy `standalone/class-translationspress-updater.php` to your plugin/theme and include it:

```php
require_once plugin_dir_path( __FILE__ ) . 'class-translationspress-updater.php';
```

## Quick Start

### Basic Usage (Composer)

```php
use TranslationsPress\Updater;

// Simple - Register a plugin for translation updates
Updater::get_instance()->register_plugin(
    'my-plugin',
    'https://api.translationspress.com/my-plugin/packages.json'
);

// Simple - Register a theme
Updater::get_instance()->register_theme(
    'my-theme',
    'https://api.translationspress.com/my-theme/packages.json'
);
```

### With WordPress.org Override

```php
use TranslationsPress\Updater;

// Replace wp.org translations with yours (fallback if your API fails)
Updater::get_instance()->register_plugin(
    'my-plugin',
    'https://api.translationspress.com/my-plugin/packages.json',
    [
        'override_wporg' => true,  // Intercept wp.org API calls
        'wporg_fallback' => true,  // Fallback to wp.org if your API fails
    ]
);

// Completely replace wp.org (no fallback)
Updater::get_instance()->register_plugin(
    'my-plugin',
    'https://api.translationspress.com/my-plugin/packages.json',
    [
        'override_wporg' => true,
        'wporg_fallback' => false,
    ]
);
```

### Centralized API (Plugin Ecosystem)

```php
use TranslationsPress\Updater;

// One API for multiple add-ons (like GravityForms, WooCommerce, etc.)
$updater = Updater::get_instance();

$updater->register_plugin(
    'my-main-plugin',
    'https://api.translationspress.com/ecosystem/packages.json',
    [ 'is_centralized' => true ]
);

$updater->register_plugin(
    'my-addon-1',
    'https://api.translationspress.com/ecosystem/packages.json',
    [ 'is_centralized' => true ]
);

$updater->register_plugin(
    'my-addon-2',
    'https://api.translationspress.com/ecosystem/packages.json',
    [ 'is_centralized' => true ]
);
```

### Standalone Version (Without Composer)

```php
// Include the standalone file
require_once plugin_dir_path( __FILE__ ) . 'class-translationspress-updater.php';

// Register your plugin (static method)
TranslationsPress_Updater::register(
    'plugin',
    'my-plugin',
    'https://packages.translationspress.com/my-vendor/my-plugin/packages.json'
);
```

## Usage Examples

### Basic Plugin Registration

```php
use TranslationsPress\Updater;

add_action( 'init', function() {
    Updater::get_instance()->register(
        'plugin',                    // Type: 'plugin' or 'theme'
        'my-awesome-plugin',         // Plugin/theme directory slug
        'https://t15s.example.com/my-plugin/packages.json'
    );
} );
```

### Theme Registration

```php
use TranslationsPress\Updater;

add_action( 'init', function() {
    Updater::get_instance()->register(
        'theme',
        'my-theme',
        'https://t15s.example.com/my-theme/packages.json'
    );
} );
```

### Override WordPress.org Translations

Use this when your plugin is on WordPress.org but you want to serve your own translations:

```php
use TranslationsPress\Updater;

add_action( 'init', function() {
    Updater::get_instance()->register(
        'plugin',
        'my-plugin',
        'https://t15s.example.com/my-plugin/packages.json',
        [
            'override_wporg' => true,   // Override wp.org translations
            'wporg_fallback' => true,   // Fallback to wp.org if T15S fails
        ]
    );
} );
```

#### Override Modes

| Mode | Description |
|------|-------------|
| `wporg_fallback: true` | Try T15S first, use wp.org if T15S has no translations |
| `wporg_fallback: false` | Block wp.org completely, only use T15S |

### Plugin Ecosystem (Centralized API)

Perfect for plugins with multiple add-ons sharing a single translation server:

```php
use TranslationsPress\Updater;

add_action( 'init', function() {
    Updater::get_instance()->register_addons(
        'https://packages.translationspress.com/my-company/packages.json',
        [
            'my-main-plugin',
            'my-addon-forms',
            'my-addon-commerce',
            'my-addon-analytics',
        ],
        [
            'override_wporg' => true,
        ]
    );
} );
```

### With Custom Options

```php
use TranslationsPress\Updater;

Updater::get_instance()->register(
    'plugin',
    'my-plugin',
    'https://t15s.example.com/packages.json',
    [
        'is_centralized'   => false,      // Single project API (default)
        'override_wporg'   => false,      // Don't override wp.org (default)
        'wporg_fallback'   => true,       // Fallback mode (default)
        'cache_expiration' => 43200,      // 12 hours in seconds (default)
        'timeout'          => 3,          // HTTP timeout in seconds (default)
    ]
);
```

### Enable Debug Logging

```php
use TranslationsPress\Updater;

$updater = Updater::get_instance();

// Log to error_log
$updater->set_logger( function( $message ) {
    error_log( $message );
} );

// Or log to a custom system
$updater->set_logger( function( $message ) {
    MyLogger::info( $message );
} );
```

### Programmatic Control

```php
use TranslationsPress\Updater;

$updater = Updater::get_instance();

// Check if a project is registered
if ( $updater->is_registered( 'plugin', 'my-plugin' ) ) {
    // ...
}

// Get a specific project
$project = $updater->get_project( 'plugin', 'my-plugin' );

// Get all registered projects
$projects = $updater->get_projects();

// Unregister a project
$updater->unregister( 'plugin', 'my-plugin' );

// Force refresh all translation caches
$updater->refresh_all();
```

## API Response Format

### Single Project API

```json
{
    "translations": [
        {
            "language": "fr_FR",
            "version": "1.2.0",
            "updated": "2024-06-15 10:30:00",
            "package": "https://example.com/translations/my-plugin-fr_FR.zip"
        },
        {
            "language": "de_DE",
            "version": "1.2.0",
            "updated": "2024-06-14 08:00:00",
            "package": "https://example.com/translations/my-plugin-de_DE.zip"
        }
    ]
}
```

### Centralized API (Multiple Projects)

```json
{
    "projects": {
        "my-main-plugin": {
            "translations": [
                {
                    "language": "fr_FR",
                    "version": "2.0.0",
                    "updated": "2024-06-15 10:30:00",
                    "package": "https://example.com/translations/main/fr_FR.zip"
                }
            ]
        },
        "my-addon": {
            "translations": [
                {
                    "language": "fr_FR",
                    "version": "1.5.0",
                    "updated": "2024-06-10 14:00:00",
                    "package": "https://example.com/translations/addon/fr_FR.zip"
                }
            ]
        }
    }
}
```

## Architecture

```
src/
├── Updater.php              # Main orchestrator (Singleton)
├── Project.php              # Plugin/theme representation
├── API.php                  # HTTP client with caching
├── Cache.php                # Transient management
└── WordPressOrgOverride.php # wp.org API override handler

standalone/
└── class-translationspress-updater.php  # Single-file version
```

### Class Responsibilities

| Class | Responsibility |
|-------|---------------|
| `Updater` | Singleton entry point, WordPress hooks integration, project registry |
| `Project` | Represents a plugin/theme, handles translation comparison logic |
| `API` | HTTP requests to T15S CDN, single/centralized API support |
| `Cache` | Transient caching with intelligent expiration and cleanup protection |
| `WordPressOrgOverride` | Intercepts wp.org API calls, replace/fallback modes |

## Conflict Prevention with Mozart

When distributing a plugin that uses this library, you should namespace-prefix the library
to avoid conflicts with other plugins using the same library.

### Automatic Setup

Add to your plugin's `composer.json`:

```json
{
    "require": {
        "translationspress/updater": "^2.0"
    },
    "require-dev": {
        "coenjacobs/mozart": "^0.7"
    },
    "extra": {
        "mozart": {
            "dep_namespace": "MyPlugin\\Vendor\\",
            "dep_directory": "/includes/vendor/",
            "packages": ["translationspress/updater"],
            "delete_vendor_directories": true
        }
    },
    "scripts": {
        "mozart": "mozart compose",
        "post-install-cmd": ["@mozart"],
        "post-update-cmd": ["@mozart"]
    }
}
```

Mozart runs automatically after `composer install` or `composer update`.

### Usage After Mozart

```php
// Use the prefixed namespace
use MyPlugin\Vendor\TranslationsPress\Updater;

Updater::get_instance()->register_plugin(
    'my-plugin',
    'https://api.translationspress.com/packages.json'
);
```

See [docs/INTEGRATION-SANS-CONFLIT.md](docs/INTEGRATION-SANS-CONFLIT.md) for complete details.

## Development

### Install Dependencies

```bash
composer install
```

### Run Tests

```bash
composer test
```

### Check Coding Standards

```bash
composer lint
```

### Auto-fix Coding Standards

```bash
composer lint:fix
```

## Migrating from Original T15S Registry

### Before (Original)

```php
// Old functional approach
\Required\Traduttore_Registry\add_project(
    'plugin',
    'my-plugin',
    'https://example.com/packages.json'
);
```

### After (This Library)

```php
use TranslationsPress\Updater;

// New object-oriented approach
Updater::get_instance()->register(
    'plugin',
    'my-plugin',
    'https://example.com/packages.json'
);
```

### Key Differences

| Feature | Original | This Library |
|---------|----------|--------------|
| PHP Version | 7.1+ | 7.4+ |
| Pattern | Functional | Object-Oriented (Singleton) |
| wp.org Override | No | Yes (replace/fallback) |
| Centralized API | No | Yes |
| Removable Hooks | No (closures) | Yes (named methods) |
| Static Caching | No | Yes |
| Logging | No | Yes (optional) |

## Contributing

Contributions are welcome! Please ensure your code follows WPCS 3.0 standards and includes appropriate tests.

## License

This project is licensed under the GPL-3.0-or-later License - see the [LICENSE.txt](LICENSE.txt) file for details.

## Credits

Inspired by:
- [TranslationsPress/t15s-registry](https://github.com/developer-developer/t15s-registry) - Original implementation
- [GravityForms TranslationsPress Updater](https://github.com/developer-developer/gravityforms-gravityforms) - Centralized API pattern
- [Polylang PLL_T15S](https://polylang.pro/) - Static caching approach

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.
